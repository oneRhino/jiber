<?php

/**
* Copyright 2016 Thaissa Mendes
*
* This file is part of Jiber.
*
* Jiber is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jiber is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jiber. If not, see <http://www.gnu.org/licenses/>.
*/

/**
* Connect into Clubhouse, get and send information using its API
*
* @author João Cortela <cortelas@gmail.com>
* @since 8 August, 2016
* @version 0.1
*/

namespace App\Http\Controllers;

use App\{ClubhouseEpic, ClubhouseStory, Setting, RedmineClubhouseProject, RedmineClubhouseUser, User};
use Mikkelson\Clubhouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Config};
use Log;

class ClubhouseController extends Controller {
    /**
     * Used by webhook, to hold redmine link object
     */
    private $redmine;

    /**
     * Content being processed by webhook
     */
    private $content;

    public function createComment ($storyId, $comment) {

        $commentFields = array ();
        $commentFields['text'] = $comment;

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "stories/{$storyId}/comments";

        $clubhouseStoryComment = $clubhouseApi->create($apiUri, $commentFields);

        return $clubhouseStoryComment;
    }

    public function createStory ($storyDetails) {

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $clubhouseStory = $clubhouseApi->create('stories', $storyDetails);

        return $clubhouseStory;
    }

    public function getProjects () {

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $projectsAsArray = $clubhouseApi->get('projects');

        return $projectsAsArray;
    }

    public function getTickets ($projectId) {

        if (!$projectId)
            return false;

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "projects/{$projectId}/stories";

        $ticketsAsArray = $clubhouseApi->get($apiUri);

        return $ticketsAsArray;
    }

    public function getUsers () {

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "users";

        $usersAsArray = $clubhouseApi->get($apiUri);

        return $usersAsArray;
    }

    public function updateComment ($storyId, $commentId, $comment) {

        $commentFields = array ();
        $commentFields['text'] = $comment;

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "stories/{$storyId}/comments";

        $clubhouseStoryComment = $clubhouseApi->update($apiUri, $commentId, $commentFields);

        return $clubhouseStoryComment;
    }

    public function updateStory ($storyId, $updateData) {

        if (!$storyId)
            return false;

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $clubhouseStory = $clubhouseApi->update("stories", $storyId,  $updateData);

        return $clubhouseStory;
    }

    /**
     * Receives the request from Clubhouse, and delegates depending on the data received.
     *
     * @param Request $request
     */
    public function webhook(Request $request) {
        Log::debug('CLUBHOUSE WEBHOOK ACTIVATED');

        $this->content = $request->getContent();
        $this->content = json_decode($this->content);
        if (!$this->content) die;

        // Get first action - the main one
        $action = $this->content->actions[0];

        // Create method name using entity and action
        $method = "{$action->entity_type}_$action->action";
        $method = str_replace('-', '_', $method);

        if (!method_exists($this, $method)) {
            $error = "Method {$method} needs to be created on Clubhouse Controller.";
            $this->errorEmail($error, 'missing method error');
            die;
        }

        try {
            $this->userLogin();

            $RedmineController = new RedmineController;
            $this->redmine = $RedmineController->connect();

            $this->$method();
        } catch (Exception $e) {
            $this->errorEmail($e->getMessage());
        }
    }

    private function userLogin() {
        $clubhouse_user_id = $this->getUserFromContent();

        // Get RedmineClubhouseUser based on clubhouse user id
        $user = RedmineClubhouseUser::where('clubhouse_user_id', $clubhouse_user_id)->first();

        if (!$user) {
            throw new Exception("User {$user_id} not found. Please re-import clubhouse users.");
        }

        // Get redmine user
        $user = $this->getRedmineUser($user);

        // Connect on Redmine using this user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        Auth::setUser($user);
    }

    private function getRedmineUser($redmine_clubhouse_user) {
        if (!$redmine_clubhouse_user->redmine_names) {
            throw new Exception("User {$redmine_clubhouse_user->clubhouse_name} does not have a redmine user associated.");
        }

        $redmine_names = json_decode($redmine_clubhouse_user->redmine_names);
        $redmine_user  = reset($redmine_names); // First user by default

        // Check if there is more than one redmine user associated
        if (count($redmine_names) > 1) {
            // If geisermenoia, get geiser's user
            if ('geisermenoia' === $redmine_clubhouse_user->clubhouse_name) {
                $redmine_user = 'geiser';
            }
        }

        // Get user settings
        $settings = Setting::where('redmine_user', $redmine_user)->first();

        if (!$settings) {
            throw new Exception("Settings not found for {$redmine_user}.");
        }

        $user = User::find($settings->id);

        return $user;
    }

    private function getUserFromContent() {
        if (empty($this->content->member_id)) {
            throw new Exception("User (member_id) not found on json content: ".print_r($this->content, true));
        }

        return $this->content->member_id;
    }

    /**
     * "Epic" will be a simple ticket on Redmine. We should record the Epic ID
     * with the Redmine ticket ID created, so, when a story is added to this
     * Epic, we can create that story as a child ticket on Redmine.

     * So, this method should:
     * 1. Check if this "Epic" has already been created, just in case, by
     * trying to get its ID from clubhouse_epics table
     * 2. If it hasn't been created:
     * 2.1. Create ticket on redmine
     * 2.2. Save Epic ID + Redmine Ticket (the one we just created) to clubhouse_epics table
     * 3. If it has been created, ignore.
     */
    private function epic_create() {
        $epic_id = $this->content->actions[0]->id;

        // Check if epic has been created
        $epic = ClubhouseEpic::where('epic_id', $epic_id)->first();

        if ($epic) {
            return true; // Epic has already been created
        }


    }

    private function getProjectId() {
        if (empty($this->content->actions[0]) || empty($this->content->actions[0]->project_id)) {
            throw new Exception('Project not found: '.print_r($this->content->actions[0], true));
        }

        $clubhouse_project_id = $this->content->actions[0]->project_id;

        $redmine_clubhouse_project = RedmineClubhouseProject::where('clubhouse_id', $clubhouse_project_id)->first();

        if (empty($redmine_clubhouse_project)) {
            throw new Exception('Clubhouse Project not found: '.$clubhouse_project_id);
        }

        if (empty($redmine_clubhouse_project->redmine_id)) {
            throw new Exception('Clubhouse Project not linked to a Redmine Project: '.$clubhouse_project_id);
        }

        return $redmine_clubhouse_project->redmine_id;
    }

    private function createRedmineTicket() {
        try {

            $project = $this->getProjectId();
            // $tracker = $this->getProjectId();

        } catch (Exception $e) {
            $this->errorEmail($e->getMessage());
        }

        

        // $data = array(
        //     'project_id'       => $project->redmine_id,
        //     'tracker_id'       => $tracker->redmine_id,
        //     'status_id'        => $status->redmine_id,
        //     'priority_id'      => $priority->redmine_id,
        //     'assigned_to_id'   => $assignee->redmine_id,
        //     'subject'          => $subject,
        //     'description'      => $description,
        //     'due_date'         => $content->issue->fields->duedate,
        //     'custom_fields'     => array(
        //         'custom_value' => array(
        //             'id'       => Config::get('redmine.jira_id'),
        //             'value'    => $content->issue->key,
        //         )
        //     ),
        //     'watcher_user_ids' => [1, 105, 89], // Billy, Alejandro, Pablo
        // );
    }

    private function epic_update($content) {
        // This method should:
        // 1. Check if this "Epic" has already been created, by trying to get its
        // ID from clubhouse_epics table
        // 2. If it hasn't been created, send data to "epic_create" method, so it's created
        // 3. If it has been created:
        // 3.1. Update whatever data needed (it's inside action's "changes" property)
    }

    /**
     * "Story" will be a simple ticket on Redmine. We should record the Story ID
     * with the Redmine ticket ID created.
     * If this "Story" has a "epic_id" property, then we should make this ticket
     * child of the one related to that epic_id: we have the epic_id/redmine
     * ticket id relationship on clubhouse_epics table.

     * So, this method should:
     * 1. Check if this "Story" has already been created, just in case, by
     * trying to get its ID from clubhouse_stories table
     * 2. If it has been created, ignore.
     * 3. If it hasn't been created:
     * 3.1. Create ticket on redmine
     * 3.2. Save Story ID + Redmine Ticket (the one we just created) to clubhouse_stories table
     */
    private function story_create($content) {
        // Check if story has already been created
        $story_id = $content->actions[0]->id;

        $story = ClubhouseStory::where('story_id', $story_id)->first();

        if ($story) {
            return true; // Story has already been created
        }

        $this->createRedmineTicket($content);
    }

    private function story_update($content) {
        // This method should:
        // 1. Check if this "Story" has already been created, by trying to get its
        // ID from clubhouse_stories table
        // 2. If it hasn't been created, send data to "story_create" method, so it's created
        // 3. If it has been created:
        // 3.1. Update whatever data needed (it's inside action's "changes" property)
    }

    private function story_comment_create($content) {
        // This method should:
        // 1. Check if this "Comment" has already been created, by trying to get its
        // ID from clubhouse_comments table
        // 2. If it has been created, ignore
        // 3. If it hasn't been created:
        // 3.1. Create comment on redmine (get redmine ticket id from clubhouse_stories
        // table, using "story_id" property to match redmine ticket)
        // 3.2. Save Clubhouse Comment ID + Redmine Comment ID (the one we just created) to clubhouse_comments table
    }

    private function story_comment_update($content) {
        // This method should:
        // 1. Check if this "Comment" has already been created, by trying to get its
        // ID from clubhouse_comments table
        // 2. If it hasn't been created, send data to story_comment_create, so it gets created
        // 3. If it has been created:
        // 3.1. Update comment on redmine (get redmine comment id from clubhouse_comments table)
    }

    private function story_comment_delete($content) {
        // This method should:
        // 1. Check if this "Comment" exists on redmine, by trying to get its
        // ID from clubhouse_comments table
        // 2. If it hasn't been created, ignore
        // 3. If it has been created:
        // CHECK WITH BILLY, IF WE SHOULD DELETE COMMENT, OR EDIT IT MARKING IT AS "DELETED" SOMEHOW
        // 3.1. Delete comment on redmine (get redmine comment id from clubhouse_comments table)
    }

    /**
     * Send email when something goes wrong.
     *
     * @param array|string $errors List of errors to be sent
     * @param string $level Error Level
     */
    private function errorEmail($errors, string $level = 'error') {
        if (!$errors) die;

        if (!is_array($errors)) {
            $errors = array($errors);
        }

        $subject = 'Redmine/Clubhouse (Clubhouse Webhook) sync '.$level;

        Mail::send('emails.error', ['errors' => $errors], function ($m) use($subject) {
            $m->from('jiber@onerhino.com', 'Jiber');
            // $m->cc(['a.bastos@onerhino.com', 'pablo@onerhino.com', 'billy@onerhino.com']);
            $m->cc(['j.cortela@onerhino.com']);
            $m->to('thaissa@onerhino.com', 'Thaissa Mendes')->subject($subject);
        });
    }
}
