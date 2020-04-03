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

use App\{RedmineJiraUser, RedmineProject, ClubhouseComment, ClubhouseTask, ClubhouseEpic, ClubhouseStory, Setting, RedmineClubhouseProject, RedmineClubhouseUser, User};
use Mikkelson\Clubhouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Config};
use Log;
use Mail;

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

        $json_content = $request->getContent();

        // On some calls, there is no content, so we'll ignore
        if (empty($json_content)) die;

        $this->content = json_decode($json_content);
        if (!$this->content || json_last_error() !== JSON_ERROR_NONE) {
            $this->invalidJSON($json_content);
            die;
        }

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

    private function invalidJSON($json_content) {
        $motive = '';

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $motive = 'No errors';
            break;
            case JSON_ERROR_DEPTH:
                $motive = 'Maximum stack depth exceeded';
            break;
            case JSON_ERROR_STATE_MISMATCH:
                $motive = 'Underflow or the modes mismatch';
            break;
            case JSON_ERROR_CTRL_CHAR:
                $motive = 'Unexpected control character found';
            break;
            case JSON_ERROR_SYNTAX:
                $motive = 'Syntax error, malformed JSON';
            break;
            case JSON_ERROR_UTF8:
                $motive = 'Malformed UTF-8 characters, possibly incorrectly encoded';
            break;
            default:
                $motive = 'Unknown error';
            break;
        }

        $error = "Invalid JSON coming from Clubhouse ({$motive}): {$json_content}";
        $this->errorEmail($error, 'invalid json error');
    }

    private function userLogin() {
        $clubhouse_user_permissions_id = $this->getUserFromContent();

        // Get RedmineClubhouseUser based on clubhouse user id
        $user = RedmineClubhouseUser::where('clubhouse_user_permissions_id', $clubhouse_user_permissions_id)->first();

        if (!$user) {
            throw new \Exception("User {$clubhouse_user_permissions_id} not found. Please re-import clubhouse users.");
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
     * WEBHOOK: Creates the epic as a issue on Redmine.
     * This function is not called by the Webhook itself but by the 'story_create' function ('cause of missing projectId).
     */
    private function epic_create($storyReferenceId) {

        $projectId = $this->content->actions[0]->project_id;

        $redmineProjectObj = RedmineProject::where('third_party_project_id', $projectId)->first();
        $epicDetails = $this->getEpic($storyReferenceId);

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
        $clubhouseStoryObj->epic_id = $storyReferenceId;
        $clubhouseStoryObj->save();
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
     */
    private function createRedmineTicket() {

        try {
            $clubhouseDetails = $this->content->actions[0];

            $redmineProjectObj = RedmineProject::where('third_party_project_id', $clubhouseDetails->project_id)->first();

            if (!$redmineProjectObj) {
                $this->writeLog ("Clubhouse project {$clubhouseDetails->project_id} is not mapped to any Redmine project.");
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
     * Create a sub ticket on Redmine.
     */
    private function createRedmineSubTicket() {

        try {
            $clubhouseDetails = $this->content->actions[0];
            $storyId = $this->content->actions[1]->id;

            $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();

            if (!$clubhouseStoryObj) {
                $this->writeLog ("Clubhouse story {$storyId} is not mapped to any Redmine project.");
                die ("Clubhouse story {$storyId} is not mapped to any Redmine project.");
            }

            // Gets parent ticket.
            $redmineParentTicketId = $clubhouseStoryObj->redmine_ticket_id;
            $redmineParentTicket = $this->redmine->issue->show($redmineParentTicketId);

            $redmineCreateIssueObj = array ();
            $redmineCreateIssueObj['project_id'] = $redmineParentTicket['issue']['project']['id'];
            $redmineCreateIssueObj['parent_issue_id'] = $redmineParentTicketId;
            $redmineCreateIssueObj['subject'] = $clubhouseDetails->description;
            $redmineCreateIssueObj['assigned_to_id'] = $redmineParentTicket['issue']['assigned_to']['id'];
            $redmineCreateIssueObj['description'] = $clubhouseDetails->description;
            $redmineCreateIssueObj['watcher_user_ids'] = [1, 105, 89]; // Billy, Alejandro, Pablo

            $redmineApiResponse = $this->redmine->issue->create($redmineCreateIssueObj);

            return $redmineApiResponse;

        } catch (\Exeption $e) {
            $this->errorEmail($e->getMessage());
        }
    }

    /**
     * WEBHOOK: Creates the story as a issue on Redmine.
     */
    private function story_create() {

        $storyId = $this->content->actions[0]->id;

        // Check if story has been created
        $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
        if ($clubhouseStoryObj) {
            $this->writeLog ("-- Story {$storyId} has already been created on Redmine.");
            die ("-- Story {$storyId} has already been created on Redmine.");
        }

        $redmineApiResponse = $this->createRedmineTicket();

        $clubhouseStoryObj = new ClubhouseStory();
        $clubhouseStoryObj->redmine_ticket_id = $redmineApiResponse->id;
        $clubhouseStoryObj->story_id = $storyId;
        $clubhouseStoryObj->save();

        // Check if story has epics related to it and create them on Redmine as tickets.
        $storyReferences = $this->content->references;
        foreach ($storyReferences as $storyReference) {
            $entityType = $storyReference->entity_type;
            if ($entityType != 'epic')
                continue;

            $this->epic_create($storyReference->id);
        }

        $this->writeLog ("-- Story {$storyId} has been created on Redmine.");
        die ("-- Story {$storyId} has been created on Redmine.");
    }

    /**
     * WEBHOOK: Creates the story as a issue on Redmine.
     * This method works for stories and epics (they are treated as a stories after created)
     */
    private function story_update() {

        $storyId = $this->content->actions[0]->id;
        $changesOnStory = $this->content->actions[0]->changes;

        // Checks if the story/ticket exists.
        $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
        if (!$clubhouseStoryObj) {
            $this->writeLog ("-- Story {$storyId} not created on Redmine.");
            $clubhouseStoryObj = ClubhouseEpic::where('epic_id', $storyId)->first();
            if (!$clubhouseStoryObj) {
                $this->writeLog ("-- Epic {$storyId} not created on Redmine.");
                die ("-- Story/Epic {$storyId} not created on Redmine.");
            }
        }

        $updatesAsIssueUpdateArray = array();
        $listOfFollowersToAdd = array();
        $listOfFollowersToRemove = array();

        foreach ($changesOnStory as $key => $changeOnStory) {

            switch ($key) {
                case "description":
                    $updatesAsIssueUpdateArray['description'] = $changeOnStory->new;
                    break;
                case "started":
                    $updatesAsIssueUpdateArray['status'] = $changeOnStory->new ? 'In Progress' : 'Assigned';
                    break;
                case "workflow_state_id":
                    $workflowStateId = $this->getWorkflowStateId($changeOnStory->new);
                    if ($workflowStateId)
                        $updatesAsIssueUpdateArray['status'] = $workflowStateId;
                    break;
                case "story_type":
                    $storyTypeId = $this->getStoryType($changeOnStory->new);
                    if ($storyTypeId)
                        $updatesAsIssueUpdateArray['tracker'] = $storyTypeId;
                    break;
                case "started_at":
                    $startDate = strtotime($changeOnStory->new);
                    $updatesAsIssueUpdateArray['start_date'] = date('Y-m-d', $startDate);
                    break;
                case "deadline":
                    $deadlineDate = strtotime($changeOnStory->new);
                    $updatesAsIssueUpdateArray['due_date'] = date('Y-m-d', $deadlineDate);
                    break;
                case "follower_ids":
                    if (isset($changeOnStory->adds)) {
                        foreach ($changeOnStory->adds as $followerId) {
                            $followersRedmineIds = RedmineClubhouseUser::where('clubhouse_user_id', $followerId)->first();
                            $listOfFollowersToAdd = json_decode($followersRedmineIds->redmine_names, TRUE);
                        }
                    }
                    if (isset($changeOnStory->removes)) {
                        foreach ($changeOnStory->removes as $followerId) {
                            $followersRedmineIds = RedmineClubhouseUser::where('clubhouse_user_id', $followerId)->first();
                            $listOfFollowersToRemove = json_decode($followersRedmineIds->redmine_names, TRUE);
                        }
                    }
                    break;
            }
        }

        /* NOTE: Not in use so far, API returns FALSE, don't know why yet.
        // Add follow users to ticket
        if ($listOfFollowersToAdd)
            $this->addFollowersToIssue ($clubhouseStoryObj->redmine_ticket_id, $listOfFollowersToAdd);

        // Remove follow users from ticket
        if ($listOfFollowersToRemove)
            $this->removeFollowersFromIssue ($clubhouseStoryObj->redmine_ticket_id, $listOfFollowersToRemove);
        */

        if ($updatesAsIssueUpdateArray) {
            $redmineTicketId = $clubhouseStoryObj->redmine_ticket_id;
            $redmineTicket = $this->redmine->issue->update($redmineTicketId, $updatesAsIssueUpdateArray);
        }

        $this->writeLog ("-- Story {$storyId} was updated on Redmine.");
        die ("-- Story {$storyId} was updated on Redmine.");
    }

    /**
     * WEBHOOK: Creates the task as a child issue on Redmine.
     */
    private function story_task_create() {

        $storyId = $this->content->actions[1]->id;
        $taskId = $this->content->actions[0]->id;

        // Check if story exists on Redmine
        $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
        if (!$clubhouseStoryObj) {
            $this->writeLog ("-- Story {$storyId} was not created on Redmine.");
            die ("-- Story {$storyId} was not created on Redmine.");
        }

        // Check if task has been created already
        $clubhouseTaskObj = ClubhouseTask::where('task_id', $taskId)->first();
        if ($clubhouseTaskObj) {
            $this->writeLog ("-- Task {$taskId} has already been created on Redmine.");
            die ("-- Task {$taskId} has already been created on Redmine.");
        }

        $redmineApiResponse = $this->createRedmineSubTicket();

        $clubhouseTaskObj = new ClubhouseTask();
        $clubhouseTaskObj->redmine_ticket_id = $redmineApiResponse->id;
        $clubhouseTaskObj->task_id = $taskId;
        $clubhouseTaskObj->save();

        $this->writeLog ("-- Task {$taskId} has been created on Redmine as a child ticket.");
        die ("-- Task {$taskId} has been created on Redmine as a child ticket.");
    }

    /**
     * WEBHOOK: Updates the issue on Redmine.
     */
    private function story_task_update() {

        $taskId = $this->content->actions[0]->id;
        $changesOnTask = $this->content->actions[0]->changes;

        // Checks if the story/ticket exists.
        $clubhouseTaskObj = ClubhouseTask::where('task_id', $taskId)->first();
        if (!$clubhouseTaskObj) {
            $this->writeLog ("-- Task {$taskId} not created on Redmine.");
            die ("-- Task {$taskId} not created on Redmine.");
        }

        $updatesAsIssueUpdateArray = array();

        foreach ($changesOnTask as $key => $changeOnTask) {

            switch ($key) {
                case "description":
                    $updatesAsIssueUpdateArray['description'] = $changeOnTask->new;
                    break;
            }
        }

        if ($updatesAsIssueUpdateArray) {
            $redmineTicketId = $clubhouseTaskObj->redmine_ticket_id;
            $redmineTicket = $this->redmine->issue->update($redmineTicketId, $updatesAsIssueUpdateArray);
        }

        $this->writeLog ("-- Task {$taskId} was updated on Redmine.");
        die ("-- Task {$taskId} was updated on Redmine.");
    }

    /**
     * WEBHOOK: Created a comment on Redmine's issue.
     */
    private function story_comment_create() {

        $contentActions = $this->content->actions;

        $commentId = $contentActions[0]->id;
        $storyId = $contentActions[1]->id;

        // Checks if the story/ticket exists.
        $redmineClubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
        if (!$redmineClubhouseStoryObj) {
            $this->writeLog ("-- Story {$storyId} not created on Redmine.");
            die ("-- Story {$storyId} not created on Redmine.");
        }

        // Checks if the comment was already sent to Redmine.
        $clubhouseCommentObj = ClubhouseComment::where('comment_id', $commentId)->first();
        if ($clubhouseCommentObj) {
            $this->writeLog ("-- Comment {$commentId} already created on Redmine.");
            die ("-- Comment {$commentId} already created on Redmine.");
        }

        try {
            $redmineTicketId = $redmineClubhouseStoryObj->redmine_ticket_id;
            $commentBody = $contentActions[0]->text;

            // This method does not return anything (no comment ID).
            $this->redmine->issue->addNoteToIssue($redmineTicketId, $commentBody);

            $clubhouseCommentObj = new ClubhouseComment ();
            $clubhouseCommentObj->comment_id = $commentId;
            $clubhouseCommentObj->redmine_comment_id = 0;
            $clubhouseCommentObj->save();

            $this->writeLog ("-- Comment {$commentId} sent to Redmine.");
            die ("-- Comment {$commentId} sent to Redmine.");
        } catch (\Exception $e) {
            $this->writeLog ("-- Exception: " . $e->getMessage());
        }
    }

    /**
     * WEBHOOK: Sent a comment update to Redmine ticket.
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
        $redmineClubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
        if (!$redmineClubhouseStoryObj) {
            $this->writeLog ("-- Story {$storyId} not created on Redmine.");
            die ("-- Story {$storyId} not created on Redmine.");
        }

        try {
            $redmineTicketId = $redmineClubhouseStoryObj->redmine_ticket_id;

            $commentBody = "h3. Comment Update: \n\n";
            $commentBody .= "h4. OLD Body: \n\n";
            $commentBody .= $contentActions[0]->changes->text->old;
            $commentBody .= "\n\n";
            $commentBody .= "h4. NEW Body: \n\n";
            $commentBody .= $contentActions[0]->changes->text->new;

            // This method does not return anything (no comment ID).
            $this->redmine->issue->addNoteToIssue($redmineTicketId, $commentBody);

            $this->writeLog ("-- Comment {$commentId} update sent to Redmine.");
            die ("-- Comment {$commentId} update sent to Redmine.");
        } catch (\Exception $e) {
            $this->writeLog ("-- Exception: " . $e->getMessage());
            die ("-- Exception: " . $e->getMessage());
        }
    }

    /**
     * WEBHOOK: 'Deletes' a comment on Redmine ticket.
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
        $redmineClubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
        if (!$redmineClubhouseStoryObj) {
            $this->writeLog ("-- Story {$storyId} not created on Redmine.");
            die ("-- Story {$storyId} not created on Redmine.");
        }

        try {
            $redmineTicketId = $redmineClubhouseStoryObj->redmine_ticket_id;
            $changedAt = strtotime($this->content->changed_at);
            $changedAtFormatted = date("Y-m-d h:i:sa", $changedAt);

            $commentBody = "h3. Comment {$commentId} was deleted on Clubhouse. ({$changedAtFormatted})";

            // This method does not return anything (no comment ID).
            $this->redmine->issue->addNoteToIssue($redmineTicketId, $commentBody);

            $this->writeLog ("-- Comment {$commentId} delete comment sent to Redmine.");
            die ("-- Comment {$commentId} delete comment sent to Redmine.");
        } catch (\Exception $e) {
            $this->writeLog ("-- Exception: " . $e->getMessage());
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

        file_put_contents(storage_path() . '/logs/clubhouse-webhook.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
    }

    private function getWorkflowStateId ($workflowStateId) {

        $workflowStates = array();
        $workflowStates['500000008'] = 'New'; // Uncheduled
        $workflowStates['500000007'] = 'Assigned'; // Ready for Development
        $workflowStates['500000006'] = 'In Progress'; // In Progress
        $workflowStates['500000604'] = 'In Review'; // QA Dev
        $workflowStates['500000010'] = 'In Review'; // QA Staging
        $workflowStates['500000009'] = 'In Review'; // Ready for Deploy
        $workflowStates['500000011'] = 'Resolved'; // Completed

        if (array_key_exists($workflowStateId, $workflowStates))
            return $workflowStates[$workflowStateId];
        else
            return FALSE;

    }

    private function getStoryType ($storyTypeId) {

        $storyTypes = array();
        $storyTypes['bug'] = 'Bug'; // Uncheduled
        $storyTypes['feature'] = 'Feature'; // Ready for Development
        $storyTypes['chore'] = 'Support'; // Ready for Development

        if (array_key_exists($storyTypeId, $storyTypes))
            return $storyTypes[$storyTypeId];
        else
            return FALSE;
    }

    private function addFollowersToIssue ($issueId, $listOfFollowers) {

        $listOfFollowersIds = array();

        foreach ($listOfFollowers as $follower) {

            $redmineJiraUserObj = RedmineJiraUser::where('redmine_name', $follower)->select('redmine_id')->first();
            $userAsArray = $redmineJiraUserObj->toArray();

            $this->redmine->issue->addWatcher($issueId, $userAsArray['redmine_id']);
        }
    }

    private function removeFollowersFromIssue ($issueId, $listOfFollowers) {

        $listOfFollowersIds = array();

        foreach ($listOfFollowers as $follower) {

            $redmineJiraUserObj = RedmineJiraUser::where('redmine_name', $follower)->select('redmine_id')->first();
            $userAsArray = $redmineJiraUserObj->toArray();

            $this->redmine->issue->removeWatcher($issueId, $userAsArray['redmine_id']);
        }
    }
}
