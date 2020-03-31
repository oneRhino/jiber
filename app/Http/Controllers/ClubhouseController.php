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

use App\{RedmineClubhouseTask, RedmineProject, ClubhouseComment, ClubhouseEpic, ClubhouseStory, Setting, RedmineClubhouseProject, RedmineClubhouseUser, User};
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

    public function getEpic ($epicId) {

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "epics/{$epicId}";

        $projectsAsArray = $clubhouseApi->get($apiUri);

        return $projectsAsArray;
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
            throw new \Exception("User {$clubhouse_user_id} not found. Please re-import clubhouse users.");
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
     * Epic is not related to any project. Function implemented but not in use.
     *
     * ------------------------------------------------------------------------------------
     *
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
        
        die ('Epic Create'); 
        $epicId = $this->content->actions[0]->id;
        
        // Check if epic has been created
        $clubhouseEpicObj = ClubhouseEpic::where('epic_id', $epicId)->first();
        if ($clubhouseEpicObj) {
            die ("-- Epic {$epicId} has already been created on Redmine.");
        }

        $redmineApiResponse = $this->createRedmineTicket();

        $clubhouseEpicObj = new ClubhouseEpic();
        $clubhouseEpicObj->redmine_ticket_id = $redmineApiResponse->id;
        $clubhouseEpicObj->epic_id = $clubhouseEpicDetails->id;
        $clubhouseEpicObj->save();
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

    /**
     * Create a ticket on Redmine.
     * Only 'stories' so far, 'epics' are not related to any project.
     */
    private function createRedmineTicket() {
        
        try {
            $clubhouseDetails = $this->content->actions[0];

            $redmineProjectObj = RedmineProject::where('third_party_project_id', $clubhouseDetails->project_id)->first();

            if (!$redmineProjectObj) {
                die ("Clubhouse project {$clubhouseDetails->project_id} is not mapped to any Redmine project.");
            }

            $redmineCreateIssueObj = array ();
            $redmineCreateIssueObj['project_id'] = $redmineProjectObj->project_name;
            $redmineCreateIssueObj['subject'] = $clubhouseDetails->name;
            $redmineCreateIssueObj['assigned_to_id'] = '1';
            $redmineCreateIssueObj['description'] = $clubhouseDetails->description; 
            $redmineCreateIssueObj['watcher_user_ids'] = [1, 105, 89]; // Billy, Alejandro, Pablo
            if ($redmineProjectObj->content) {
                $redmineCreateIssueObj['description'] .= "\n\n" . $redmineProjectObj->content;
            }

            $redmineApiResponse = $this->redmine->issue->create($redmineCreateIssueObj);

            return $redmineApiResponse;

        } catch (\Exeption $e) { 
            $this->errorEmail($e->getMessage());
        }
    }

    /**
     * Epic update JSON is not available for development. Skipping for now.
     *
     * ------------------------------------------------------------------------------------
     *
     * This method should:
     * 1. Check if this "Epic" has already been created, by trying to get its
     * ID from clubhouse_epics table
     * 2. If it hasn't been created, send data to "epic_create" method, so it's create
     * 3. If it has been created:
     * 3.1. Update whatever data needed (it's inside action's "changes" property)
     */ 
    private function epic_update($content) {

        die ("Epic Update"); 
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
    private function story_create() {

        $storyId = $this->content->actions[0]->id;

        // Check if story has been created
        $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
        if ($clubhouseStoryObj) {
            die ("-- Story {$storyId} has already been created on Redmine.");
        }

        // START - Create story ticket.
        $redmineApiResponse = $this->createRedmineTicket();

        $clubhouseStoryObj = new ClubhouseStory();
        $clubhouseStoryObj->redmine_ticket_id = $redmineApiResponse->id;
        $clubhouseStoryObj->story_id = $storyId;
        $clubhouseStoryObj->save();
        // END - Create story ticket.

        // START - Create epic ticket.
        $storyReferences = $this->content->references;

        foreach ($storyReferences as $storyReference) {
            $entityType = $storyReference->entity_type;
            if ($entityType != 'epic')
                continue;

            $projectId = $this->content->actions[0]->project_id;

            $redmineProjectObj = RedmineProject::where('third_party_project_id', $projectId)->first();
            $epicDetails = $this->getEpic($storyReference->id);

            // Send epic to Redmine.
            $redmineCreateIssueObj = array ();
            $redmineCreateIssueObj['project_id'] = $projectId;
            $redmineCreateIssueObj['subject'] = "(Epic)" . $epicDetails['name'];
            $redmineCreateIssueObj['assigned_to_id'] = '1';
            $redmineCreateIssueObj['description'] = $epicDetails['description'];
            $redmineCreateIssueObj['watcher_user_ids'] = [1, 105, 89]; // Billy, Alejandro, Pablo
            if ($redmineProjectObj->content) {
                $redmineCreateIssueObj['description'] .= "\n\n" . $redmineProjectObj->content;
            }
            $redmineApiResponse = $this->redmine->issue->create($redmineCreateIssueObj);

            // Save Redmine/Clubhouse epic relationship.
            $clubhouseStoryObj = new ClubhouseEpic();
            $clubhouseStoryObj->redmine_ticket_id = $redmineApiResponse->id;
            $clubhouseStoryObj->epic_id = $storyReference->id;
            $clubhouseStoryObj->save();
        }
        // END - Create epic ticket.

        die ("-- Story {$storyId} has been created on Redmine.");
    }

    /**
     * This method should:
     * 1. Check if this "Story" has already been created, by trying to get its
     * ID from clubhouse_stories table
     * 2. If it hasn't been created, send data to "story_create" method, so it's created
     * 3. If it has been created:
     * 3.1. Update whatever data needed (it's inside action's "changes" property)
     */
    private function story_update() {

        die ("Story Update");

        $storyId = $this->content->actions[0]->id;
        $changesOnStory = $this->content->actions[0]->changes;
    }

    /**
     * Sent a update to Redmine ticket.
     *
     * NOTE:
     * 'addNoteToIssue' does not return anything, so there's no redmine_comment_id.
     */
    private function story_comment_create() {

        $contentActions = $this->content->actions;

        $commentId = $contentActions[0]->id;
        $storyId = $contentActions[1]->id;

        // Checks if the story/ticket exists.
        $redmineClubhouseTaskObj = RedmineClubhouseTask::where('clubhouse_task', $storyId)->first();
        if (!$redmineClubhouseTaskObj) {
            $this->writeLog("-- Story {$storyId} not created on Redmine.");
            die ("-- Story {$storyId} not created on Redmine.");
        }

        // Checks if the comment was already sent to Redmine.
        $clubhouseCommentObj = ClubhouseComment::where('comment_id', $commentId)->first();
        if ($clubhouseCommentObj) {
            $this->writeLog("-- Comment {$commentId} already created on Redmine.");
            die ("-- Comment {$commentId} already created on Redmine.");
        }

        try {
            $redmineTicketId = $redmineClubhouseTaskObj->redmine_task;
            $commentBody = $contentActions[0]->text;

            // This method does not return anything (no comment ID).
            $this->redmine->issue->addNoteToIssue($redmineTicketId, $commentBody);

            $clubhouseCommentObj = new ClubhouseComment ();
            $clubhouseCommentObj->comment_id = $commentId;
            $clubhouseCommentObj->redmine_comment_id = 0;
            $clubhouseCommentObj->save();

            die ("-- Comment {$commentId} sent to Redmine.");
        } catch (\Exception $e) {
            die ("-- Exception: " . $e->getMessage());
        }
    }

    /**
     * Sent a comment update to Redmine ticket.
     *
     * NOTE:
     * There's no way to update/delete a note/comment using the Redmine API.
     * This function sends the old comment and the new one to keep tracking.
     */
    private function story_comment_update() {

        $contentActions = $this->content->actions;

        $commentId = $contentActions[0]->id;
        $storyId = $contentActions[0]->story_id;

        // Checks if the story/ticket exists.
        $redmineClubhouseTaskObj = RedmineClubhouseTask::where('clubhouse_task', $storyId)->first();
        if (!$redmineClubhouseTaskObj) {
            $this->writeLog("-- Story {$storyId} not created on Redmine.");
            die ("-- Story {$storyId} not created on Redmine.");
        }

        try {
            $redmineTicketId = $redmineClubhouseTaskObj->redmine_task;

            $commentBody = "h3. Comment Update: \n\n";
            $commentBody .= "h4. OLD Body: \n\n";
            $commentBody .= $contentActions[0]->changes->text->old;
            $commentBody .= "\n\n";
            $commentBody .= "h4. NEW Body: \n\n";
            $commentBody .= $contentActions[0]->changes->text->new;

            // This method does not return anything (no comment ID).
            $this->redmine->issue->addNoteToIssue($redmineTicketId, $commentBody);

            die ("-- Comment {$commentId} update sent to Redmine.");
        } catch (\Exception $e) {
            die ("-- Exception: " . $e->getMessage());
        }
    }

    /**
     * 'Deletes' a comment on Redmine ticket.
     *
     * NOTE:
     * There's no way to update/delete a note/comment using the Redmine API.
     * This function sends a new comment with the information about deleted comment.
     */
    private function story_comment_delete() {

        $contentActions = $this->content->actions;

        $commentId = $contentActions[0]->id;
        $storyId = $contentActions[1]->id;

        // Checks if the story/ticket exists.
        $redmineClubhouseTaskObj = RedmineClubhouseTask::where('clubhouse_task', $storyId)->first();
        if (!$redmineClubhouseTaskObj) {
            $this->writeLog("-- Story {$storyId} not created on Redmine.");
            die ("-- Story {$storyId} not created on Redmine.");
        }

        try {
            $redmineTicketId = $redmineClubhouseTaskObj->redmine_task;
            $changedAt = strtotime($this->content->changed_at);
            $changedAtFormatted = date("Y-m-d h:i:sa", $changedAt);

            $commentBody = "h3. Comment {$commentId} was deleted on Clubhouse. ({$changedAtFormatted})";

            // This method does not return anything (no comment ID).
            $this->redmine->issue->addNoteToIssue($redmineTicketId, $commentBody);

            die ("-- Comment {$commentId} delete comment sent to Redmine.");
        } catch (\Exception $e) {
            die ("-- Exception: " . $e->getMessage());
        }
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

    private function writeLog($message) {

        file_put_contents('clubhouse-webhook.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
    }
}
