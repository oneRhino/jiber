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
* @version 0.1
*/

namespace App\Http\Controllers;

use App\{ClubhouseComment, ClubhouseEpic, ClubhouseStory, ClubhouseTask, ClubhouseProject, ClubhouseStatus};
use App\{RedmineClubhouseChange, RedmineClubhouseProject, RedmineClubhouseUser};
use App\{RedmineJiraUser, RedmineProject, RedmineStatus, RedmineTracker};
use App\TogglProject;
use App\Http\Controllers\TogglTaskController;
use App\{Setting, User};
use Nshonda\Clubhouse;
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

    /**
     * Clubhouse base URL (used to add URL to ticket description on Redmine)
     */
    private $clubhouseBaseUrl = "https://app.clubhouse.io/flypilot";

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

    public function getStory ($storyId) {

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "stories/{$storyId}";

        $projectsAsArray = $clubhouseApi->get($apiUri);

        if (!empty($projectsAsArray['message']) && $projectsAsArray['message'] == 'Resource not found.') {
            throw new \Exception("Clubhouse story {$storyId} not found.");
        }

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

        $apiUri = "members";

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
        $this->writeLog('CLUBHOUSE WEBHOOK ACTIVATED');

        $json_content = $request->getContent();

        // On some calls, there is no content, so we'll ignore
        if (empty($json_content)) {
            $this->writeLog('-- No JSON Content, ignore.');
            die;
        }

        $this->content = json_decode($json_content);
        if (!$this->content || json_last_error() !== JSON_ERROR_NONE) {
            if ($json_content) {
                $this->writeLog('-- Invalid JSON Content, ignore.');
                $this->invalidJSON($json_content);
                die;
            }
        }

        $ignored_actions = ['branch', 'pull-request', 'reaction'];

        // Run through all actions
        foreach ($this->content->actions as $action) {
            // Some actions should be ignored
            if (
                in_array($action->entity_type, $ignored_actions) ||
                in_array($action->action, $ignored_actions)
            ) {
                $this->writeLog("-- {$action->entity_type} {$action->action} call, ignored.");
                continue;
            }

            // Ignore updates from onerhinodev user (to avoid duplicates)
            if (!empty($this->content->member_id)) {
                $authorId = $this->content->member_id;
                $ignoredUserId = RedmineClubhouseUser::where('clubhouse_name', 'onerhinodev')->first();

                if ($authorId == $ignoredUserId->clubhouse_user_permissions_id) {
                    $this->writeLog ("-- Update from -onerhinodev- user, ignore.");
                    die ("-- Update from -onerhinodev- user, ignoring it.");
                }
            }

            // Create method name using entity and action
            $method = "{$action->entity_type}_{$action->action}";
            $method = str_replace('-', '_', $method);

            if (!method_exists($this, $method)) {
                $error = "Method {$method} needs to be created on Clubhouse Controller.";
                $this->errorEmail($error, 'missing method error');
                die;
            }

            try {
                if ($this->userLogin()) {
                    $RedmineController = new RedmineController;
                    $this->redmine = $RedmineController->connect();

                    $this->writeLog("-- Calling {$method} method");
                    $this->writeLog(print_r($action, true));
                    $this->$method($action);
                }
            } catch (\Exception $e) {
                $this->errorEmail($e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
            }
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
        // Remove this comment to start sending those emails again.
        //$this->errorEmail($error, 'invalid json error');
    }

    private function userLogin() {
        $clubhouse_user_permissions_id = $this->getUserFromContent();

        if ($clubhouse_user_permissions_id) {
            $this->writeLog ("-- Searching for user {$clubhouse_user_permissions_id}");

            // Get RedmineClubhouseUser based on clubhouse user permissions id
            $user = RedmineClubhouseUser::where('clubhouse_user_permissions_id', $clubhouse_user_permissions_id)->first();

            // If not found, try by user id
            if (!$user) {
                $this->writeLog("Not found based on permissions id, search based on user id");
                $user = RedmineClubhouseUser::where('clubhouse_user_id', $clubhouse_user_permissions_id)->first();
            }

            if (!$user) {
                $this->writeLog("User '{$clubhouse_user_permissions_id}' not found.");
                throw new \Exception("User '{$clubhouse_user_permissions_id}' not found. Please re-import clubhouse users.");
            }

            $this->writeLog ("-- User found, get Redmine User");

            // Get redmine user
            $user = $this->getRedmineUser($user);

            if (!$user) return false;
        } else {
            // Use OMG user by default
            $user = User::find(33);
        }

        // Connect on Redmine using this user
        $this->writeLog ("-- Login");
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        Auth::setUser($user);

        return true;
    }

    private function getRedmineUser($redmineClubhouseUserObj) {
        if (!$redmineClubhouseUserObj->redmine_names) {
            throw new \Exception("User {$redmineClubhouseUserObj->clubhouse_name} does not have a redmine user associated.");
        }

        $redmine_names = json_decode($redmineClubhouseUserObj->redmine_names);
        $redmine_user  = reset($redmine_names); // First user by default

        // Check if there is more than one redmine user associated
        if (count($redmine_names) > 1) {
            // If geisermenoia, get geiser's user
            if ('geisermenoia' === $redmineClubhouseUserObj->clubhouse_name) {
                $redmine_user = 'geiser';
            }
            // If onerhinodev, get billy's user
            elseif ('onerhinodev' === $redmineClubhouseUserObj->clubhouse_name) {
                $redmine_user = 'admin';
            }
        }

        // If there's no Redmine User set, sets Billy's user.
        if (!$redmine_user) {
            $redmine_user = 'admin';
        }

        $this->writeLog ("-- Use Redmine '{$redmine_user}' user");

        // Get user settings
        $settings = Setting::where('redmine_user', $redmine_user)->first();

        if (!$settings) {
            $this->writeLog ("-- Settings not found for Redmine '{$redmine_user}' user");
            throw new \Exception("Settings not found for {$redmine_user}.");
        }

        $user = User::find($settings->id);

        return $user;
    }

    private function getRedmineAssignToUser ($clubhouseUserId) {

        // If no clubhouse id, set it as null
        if (!$clubhouseUserId) return null;

        $redmineClubhouseUserObj = RedmineClubhouseUser::where('clubhouse_user_permissions_id', $clubhouseUserId)->first();

        // If no clubhouse user, set it as null
        if (!$redmineClubhouseUserObj) return null;

        if (!$redmineClubhouseUserObj->redmine_names) {
            throw new \Exception("User {$redmineClubhouseUserObj->clubhouse_name} does not have a redmine user associated.");
        }

        // If assigned to "onerhinodev", use Alejandro's user
        if ($redmineClubhouseUserObj->clubhouse_name === 'onerhinodev') {
            $redmine_user = 'alejandro.b';
        }
        // Otherwise, get first Redmine user from redmine_names list
        else {
            $redmine_names = json_decode($redmineClubhouseUserObj->redmine_names);
            $redmine_user  = reset($redmine_names); // First user by default
        }

        // Get user settings
        $redmineUser = RedmineJiraUser::where('redmine_name', $redmine_user)->first();

        if (!$redmineUser) {
            throw new \Exception("Redmine/Clubhouse user not found for {$redmine_user}.");
        }

        return $redmineUser->redmine_id;
    }

    private function getUserFromContent() {
        if (empty($this->content->member_id)) {
            return false;
        }

        return $this->content->member_id;
    }

    /**
     * Gets a owner of an epic. Since 'owner_ids' is not mandatory the order is this:
     *
     * - first owner_id
     * - first follower_id
     * - none (will be assigned to Alejadro as discussed in Slack)
     */
    private function getOwnerFromEpic ($epicDetails) {

        // Set the same owner as the story related to the epic.
        $epicOwnerId = '';
        if (array_key_exists(0, $epicDetails['owner_ids'])) {
            $epicOwnerId = $epicDetails['owner_ids'][0];
            $this->writeLog ("-- Epic {$epicDetails['id']} has owner: {$epicOwnerId}");
        }

        if (!$epicOwnerId) {
            if (array_key_exists(0, $epicDetails['follower_ids'])) {
                $epicOwnerId = $epicDetails['follower_ids'][0];
                $this->writeLog ("-- Epic {$epicDetails['id']} has no owner. Follower assigned as owner: {$epicOwnerId}");
            } else {
                $this->writeLog ("-- Epic {$epicDetails['id']} has no owner or follower. Alejandro assigned as owner.");
            }
        }

        if ($epicOwnerId) {
            $epicOwnerId = RedmineClubhouseUser::where('clubhouse_user_id', $epicOwnerId)->orWhere('clubhouse_user_permissions_id', $epicOwnerId)->first();
            $epicOwnerId = $epicOwnerId->clubhouse_user_permissions_id;
        }

        return ($epicOwnerId);
    }

    /**
     * Gets a owner of a story. Since 'owner_ids' is not mandatory the order is this:
     *
     * - first owner_id
     * - first follower_id
     * - none (will be assigned to Alejadro as discussed in Slack)
     */
    private function getOwnerFromStory ($storyDetails) {

        if (empty($storyDetails['owner_ids'])) {
            return null;
        }

        $storyOwnerId = reset($storyDetails['owner_ids']);

        $storyOwnerId = RedmineClubhouseUser::where('clubhouse_user_id', $storyOwnerId)->orWhere('clubhouse_user_permissions_id', $storyOwnerId)->first();

        $storyOwnerId = $storyOwnerId->clubhouse_user_permissions_id;

        return $storyOwnerId;
    }

    /**
     * WEBHOOK: Creates the epic as a issue on Redmine.
     * This function is not called by the Webhook itself but by the 'story_create' function ('cause of missing projectId).
     */
    private function epic_create($storyReferenceId) {

        $projectId = $this->content->actions[0]->project_id;

        $redmineProjectObj = RedmineProject::where('third_party', 'clubhouse')
            ->where('third_party_project_id', $projectId)
            ->first();
        $epicDetails = $this->getEpic($storyReferenceId);

        $epicOwnerId = $this->getOwnerFromEpic ($epicDetails);

        // Send epic to Redmine.
        $redmineCreateIssueObj = array ();
        $redmineCreateIssueObj['project_id']       = $projectId;
        $redmineCreateIssueObj['subject']          = "(Epic)" . $epicDetails['name'];
        $redmineCreateIssueObj['assigned_to_id']   = $this->getRedmineAssignToUser($epicOwnerId);
        $redmineCreateIssueObj['description']      = $epicDetails['description'];
        $redmineCreateIssueObj['description']     .= "\n\n* Clubhouse URL: {$this->clubhouseBaseUrl}/epic/{$storyReferenceId}";
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
            throw new \Exception('Project not found: '.print_r($this->content->actions[0], true));
        }

        $clubhouse_project_id = $this->content->actions[0]->project_id;

        $redmine_clubhouse_project = RedmineClubhouseProject::where('clubhouse_id', $clubhouse_project_id)->first();

        if (empty($redmine_clubhouse_project)) {
            throw new \Exception('Clubhouse Project not found: '.$clubhouse_project_id);
        }

        if (empty($redmine_clubhouse_project->redmine_id)) {
            throw new \Exception('Clubhouse Project not linked to a Redmine Project: '.$clubhouse_project_id);
        }

        return $redmine_clubhouse_project->redmine_id;
    }

    private function createMissingRedmineTicket ($storyId) {

        try {
            $clubhouseDetails = (array) $this->getStory($storyId);

            if (empty($clubhouseDetails['project_id'])) {
                $msg = "Clubhouse project not found inside story details: {$storyId}" . print_r($clubhouseDetails, true);
                $this->writeLog($msg);
                $this->errorEmail($msg);
                die($msg);
            }

            $redmineProjectObj = RedmineProject::where('third_party', 'clubhouse')
                ->where('third_party_project_id', $clubhouseDetails['project_id'])
                ->first();

            if (!$redmineProjectObj) {
                $this->writeLog ("Clubhouse project {$clubhouseDetails['project_id']} is not mapped to any Redmine project.");
                die ("Clubhouse project {$clubhouseDetails['project_id']} is not mapped to any Redmine project.");
            }

            $storyOwnerId = $this->getOwnerFromStory ($clubhouseDetails);

            $redmineCreateIssueObj = array ();
            $redmineCreateIssueObj['project_id']       = $redmineProjectObj->project_id;
            $redmineCreateIssueObj['subject']          = $clubhouseDetails['name'];
            $redmineCreateIssueObj['assigned_to_id']   = $this->getRedmineAssignToUser($storyOwnerId);
            $redmineCreateIssueObj['description']      = $clubhouseDetails['description'];
            $redmineCreateIssueObj['watcher_user_ids'] = [1, 105, 89]; // Billy, Alejandro, Pablo
            $redmineCreateIssueObj['description']     .= "\n\n* Clubhouse URL: {$this->clubhouseBaseUrl}/story/{$storyId}";
            if ($redmineProjectObj->content) {
                $redmineCreateIssueObj['description'] .= "\n\n" . $redmineProjectObj->content;
            }

            $redmineCreateIssueObj['status'] = $this->getRedmineStatus($clubhouseDetails['workflow_state_id']);
            $redmineCreateIssueObj['tracker'] = $this->getRedmineTracker($clubhouseDetails['story_type']);

            if ($redmineProjectObj['content']) {
                $redmineCreateIssueObj['description'] .= "\n\n" . $redmineProjectObj['content'];
            }

            if (!empty($clubhouseDetails['deadline'])) {
                $redmineCreateIssueObj['due_date'] = $this->getRedmineDueDate($clubhouseDetails['deadline']);
            }


            $redmineApiResponse = $this->redmine->issue->create($redmineCreateIssueObj);
            $toggleApiResponse = $this->createTogglTask();

            if (! $redmineApiResponse) {
                $this->writeLog("Missing API Response from Redmine");
                $this->writeLog($redmineApiResponse);
                $this->errorEmail("Missing API Response from Redmine", print_r($redmineApiResponse, true));
                throw new \Exception("Missing API Response from Redmine");
            }
            if($toggleApiResponse){
                $this->createClubhouseStory($redmineApiResponse->id, $toggleApiResponse->id, $storyId);
            }
            else{
                $this->createClubhouseStory($redmineApiResponse->id, null, $storyId);
            }

            $this->writeLog ("-- Missing story {$storyId} has been created on Redmine.");

            // Check if there's comments in the story and sends them to Redmine.
            if (array_key_exists('comments', $clubhouseDetails)) {
                foreach ($clubhouseDetails['comments'] as $storyComment) {
                    $startDate = strtotime($storyComment['created_at']);
                    $startDateFormatted = date('Y-m-d H:i:s', $startDate);
                    $commentBody = "Clubhouse: ({$startDateFormatted}) {$storyComment['text']}";
                    $this->redmine->issue->addNoteToIssue($redmineApiResponse->id, $commentBody);

                    $clubhouseCommentObj = new ClubhouseComment ();
                    $clubhouseCommentObj->comment_id = $storyComment['id'];
                    $clubhouseCommentObj->redmine_comment_id = 0;
                    $clubhouseCommentObj->save();

					$this->setAllRedmineChangesAsSent($redmineApiResponse->id, $storyId, $storyComment['id']);

                    $this->writeLog ("-- Missing story comment {$storyComment['id']} on story {$storyId} has been created on Redmine.");
                }
            }
        } catch (\Exception $e) {
            $this->writeLog("createMissingRedmineTicket Problem");
            $this->writeLog($e->getMessage());
            // $this->writeLog($e->getTrace());
            $this->errorEmail($e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Create a ticket on Redmine.
     */
    private function createRedmineTicket() {

        try {
            $clubhouseDetails = $this->content->actions[0];

            $redmineProjectObj = RedmineProject::where('third_party', 'clubhouse')
                ->where('third_party_project_id', $clubhouseDetails->project_id)
                ->first();

            if (!$redmineProjectObj) {
                $this->writeLog ("Clubhouse project {$clubhouseDetails->project_id} is not mapped to any Redmine project.");
                die ("Clubhouse project {$clubhouseDetails->project_id} is not mapped to any Redmine project.");
            }

            $clubhouseDetailsAsArray = json_decode(json_encode($clubhouseDetails), TRUE);
            $storyOwnerId = $this->getOwnerFromStory ($clubhouseDetailsAsArray);

            $redmineCreateIssueObj                     = array ();
            $redmineCreateIssueObj['project_id']       = $redmineProjectObj->project_id;
            $redmineCreateIssueObj['status']           = $this->getRedmineStatus($clubhouseDetails->workflow_state_id);
            $redmineCreateIssueObj['tracker']          = $this->getRedmineTracker($clubhouseDetails->story_type);
            $redmineCreateIssueObj['subject']          = $clubhouseDetails->name;
            $redmineCreateIssueObj['assigned_to_id']   = $this->getRedmineAssignToUser($storyOwnerId);
            $redmineCreateIssueObj['description']      = $clubhouseDetails->description;
            $redmineCreateIssueObj['watcher_user_ids'] = [1, 105, 89]; // Billy, Alejandro, Pablo
            $redmineCreateIssueObj['custom_fields']    = [
                'custom_value' => [
                    'id'    => Config::get('redmine.jira_id'),
                    'value' => $clubhouseDetails->id,
                ]
            ];
            $redmineCreateIssueObj['description']     .= "\n\n* Clubhouse URL: {$this->clubhouseBaseUrl}/story/{$clubhouseDetails->id}";
            if ($redmineProjectObj->content) {
                $redmineCreateIssueObj['description'] .= "\n\n" . $redmineProjectObj->content;
            }

            if (!empty($clubhouseDetails->deadline)) {
                $redmineCreateIssueObj['due_date'] = $this->getRedmineDueDate($clubhouseDetails->deadline);
            }

            $this->writeLog("-- Sending data to Redmine, to create ticket");
            $this->writeLog(print_r($redmineCreateIssueObj, true));

            $redmineApiResponse = $this->redmine->issue->create($redmineCreateIssueObj);

            return $redmineApiResponse;

        } catch (\Exeption $e) {
            $this->errorEmail($e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
        }
    }


    /**
     * Create a missing sub ticket on Redmine.
     */
    private function createMissingRedmineSubTicket() {

        try {
            $clubhouseDetails = $this->content->actions[0];
            $storyId = $this->content->actions[0]->story_id;

            $storyDetails = (array) $this->getStory($storyId);

            $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
            $redmineProjectObj = RedmineProject::where('third_party', 'clubhouse')
                ->where('third_party_project_id', $storyDetails['project_id'])
                ->first();

            if (!$clubhouseStoryObj) {
                $this->writeLog ("-- Story {$storyId} not created on Redmine. Canceling...");
                die ("-- Story {$storyId} not created on Redmine. Canceling...");
            }

            // Gets parent ticket.
            $redmineParentTicketId = $clubhouseStoryObj->redmine_ticket_id;
            $redmineParentTicket = $this->redmine->issue->show($redmineParentTicketId);

            if ($redmineParentTicket) {
                $redmineCreateIssueObj = array ();
                $redmineCreateIssueObj['project_id'] = $redmineParentTicket['issue']['project']['id'];
                $redmineCreateIssueObj['parent_issue_id'] = $redmineParentTicketId;
                $redmineCreateIssueObj['subject'] = $clubhouseDetails->description;
                $redmineCreateIssueObj['assigned_to_id'] = $redmineParentTicket['issue']['assigned_to']['id'];
                $redmineCreateIssueObj['description'] = $clubhouseDetails->description;
                $redmineCreateIssueObj['description'] .= "\n\n (Clubhouse URL): {$this->clubhouseBaseUrl}/story/{$storyId}";
                $redmineCreateIssueObj['watcher_user_ids'] = [1, 105, 89]; // Billy, Alejandro, Pablo
                if ($redmineProjectObj->content) {
                    $redmineCreateIssueObj['description'] .= "\n\n" . $redmineProjectObj->content;
                }

                $redmineApiResponse = $this->redmine->issue->create($redmineCreateIssueObj);

                return $redmineApiResponse;
            } else {
                $this->errorEmail("Issue {$redmineParentTicketId} should exist and be a parent, but it is not, check." . print_r($redmineParentTicket, true));
            }

        } catch (\Exeption $e) {
            $this->errorEmail($e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Create a sub ticket on Redmine.
     */
    private function createRedmineSubTicket() {

        try {
            $clubhouseDetails = $this->content->actions[0];
            $storyId = $this->content->actions[1]->id;

            $storyDetails =(array) $this->getStory($storyId);

            $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
            $redmineProjectObj = RedmineProject::where('third_party', 'clubhouse')
                ->where('third_party_project_id', $storyDetails['project_id'])
                ->first();

            if (!$clubhouseStoryObj) {
                $this->writeLog ("-- Story {$storyId} not created on Redmine. Creating...");
                $this->createMissingRedmineTicket($storyId);
            }

            // Gets parent ticket.
            $redmineParentTicketId = $clubhouseStoryObj->redmine_ticket_id;
            $redmineParentTicket = $this->redmine->issue->show($redmineParentTicketId);

            $redmineCreateIssueObj = array ();
            $redmineCreateIssueObj['project_id']       = $redmineParentTicket['issue']['project']['id'];
            $redmineCreateIssueObj['parent_issue_id']  = $redmineParentTicketId;
            $redmineCreateIssueObj['subject']          = $clubhouseDetails->description;
            $redmineCreateIssueObj['assigned_to_id']   = $redmineParentTicket['issue']['assigned_to']['id'] ?? '';
            $redmineCreateIssueObj['description']      = $clubhouseDetails->description;
            $redmineCreateIssueObj['description']     .= "\n\n* Clubhouse URL: {$this->clubhouseBaseUrl}/stories/{$storyId}";
            $redmineCreateIssueObj['watcher_user_ids'] = [1, 105, 89]; // Billy, Alejandro, Pablo
            if ($redmineProjectObj->content) {
                $redmineCreateIssueObj['description'] .= "\n\n" . $redmineProjectObj->content;
            }

            $redmineApiResponse = $this->redmine->issue->create($redmineCreateIssueObj);

            return $redmineApiResponse;

        } catch (\Exeption $e) {
            $this->errorEmail($e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Create a task on Toggl.
     */
    private function createTogglTask() {

        try {
            $clubhouseDetails = $this->content->actions[0];
            if(isset($clubhouseDetails->project_id)){
                $clubhouseProjectId = $clubhouseDetails->project_id;
                $clubhouseProject = ClubhouseProject::where('clubhouse_id', $clubhouseDetails->project_id)->first();
                $togglProjectObj = $clubhouseProject ? $clubhouseProject->togglProject : null;

                if (!$togglProjectObj) {
                    $this->writeLog ("Clubhouse project {$clubhouseDetails->project_id} is not mapped to any Toggl project.");
                    return null;
                }

                $clubhouseDetailsAsArray = (array) $clubhouseDetails;
            }
            else{
                $clubhouseDetails = (array) $this->getStory($clubhouseDetails->id);
                $clubhouseProject = ClubhouseProject::where('clubhouse_id', $clubhouseDetails['project_id'])->first();
                $togglProjectObj = $clubhouseProject ? $clubhouseProject->togglProject : null;

                if (!$togglProjectObj) {
                    $this->writeLog ("Clubhouse project {$clubhouseDetails['project_id']} is not mapped to any Toggl project.");
                    return null;
                }
            }
                $togglCreateTaskObj = $this->generateTogglTaskObj($togglProjectObj, $clubhouseDetails);
                $togglController = new TogglTaskController();
                $togglApiResponse = $togglController->createTaskFromClubhouseAction($togglCreateTaskObj, true);
                $this->writeLog ("-- Toggl task.");
                return $togglApiResponse;

        } catch (\Exeption $e) {
            $this->errorEmail($e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
        }
    }


    /**
     * Update a task on Toggl.
     */
    private function updateTogglTask($clubhouseStoryObj, $content, $action) {
        try {
            $clubhouseDetails = (array) $this->getStory($action->id);
            // $this->writeLog("Clubhouse Story Details:");
            // $this->writeLog(print_r($clubhouseDetails, true));
            $clubhouseProject = ClubhouseProject::where('clubhouse_id', $clubhouseDetails['project_id'])->first();
            $togglProjectObj = $clubhouseProject ? $clubhouseProject->togglProject : null;

            if (!$togglProjectObj) {
                $this->writeLog ("Clubhouse project {$clubhouseDetails['project_id']} is not mapped to any Toggl project.");
                return null;
            }

            if ($clubhouseStoryObj->toggl_task) {
                $togglTaskId = $clubhouseStoryObj->toggl_task->toggl_id;
            }

            $togglController = new TogglTaskController();

            if (!empty($togglTaskId)) {
                $this->writeLog("Update Toggl Task {$togglTaskId}");
                $togglApiResponse = $togglController->updateTask($togglTaskId, $content, true);
            } else {
                $this->writeLog("Create Toggl Task");
                $togglCreateTaskObj = $this->generateTogglTaskObj($togglProjectObj, $clubhouseDetails);
                $this->writeLog(print_r($togglCreateTaskObj, true));
                $togglApiResponse = $togglController->createTaskFromClubhouseAction($togglCreateTaskObj, true);
                $this->writeLog("Toggl API Response:");
                $this->writeLog(print_r($togglApiResponse, true));
            }

            if (!empty($togglApiResponse)) {
                $this->writeLog("Update CH Story Object, set Toggl Task ID: {$togglApiResponse->id}");
                $clubhouseStoryObj->toggl_task_id = $togglApiResponse->id;
                $clubhouseStoryObj->save();
            }
        } catch (\Exeption $e) {
            $this->errorEmail($e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
        }
    }

    private function generateTogglTaskObj($togglProjectObj, $clubhouseDetails) {
        $clubhouseDetails = (array) $clubhouseDetails;

        $togglCreateTaskObj         = array ();
        $togglCreateTaskObj['pid']  = $togglProjectObj->toggl_id;
        $togglCreateTaskObj['name'] = $clubhouseDetails['id'] . ' - ' . $clubhouseDetails['name'];

        if( array_key_exists('estimate', $clubhouseDetails) )
        {
            $togglCreateTaskObj['estimated_seconds'] = $this->convertClubhouseEstimateToToggl($clubhouseDetails['estimate']);
        }

        return $togglCreateTaskObj;
    }

    private function convertClubhouseEstimateToToggl($estimate){
        $value = 0;
        if(strlen($estimate) >= 1){
            switch($estimate){
                case 0:
            $value = 1800; break;
                case 1:
                $value = 3600; break;
                case 2:
                $value = 7200; break;
                case 4:
                $value = 14400; break;
                case 8:
                $value = 28800; break;
            }
        }
        return $value;
    }


    /**
     * WEBHOOK: Creates the story as a issue on Redmine.
     */
    private function story_create($action) {

        // Trigger sync task on Toggl

        $storyId = $action->id;

        // Check if story has been created
        $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();
        if ($clubhouseStoryObj) {
            $this->writeLog ("-- Story {$storyId} already created on Redmine.");
            die ("-- Story {$storyId} already created on Redmine.");
        }

        $this->writeLog("-- Story {$storyId} not created on Redmine.");

        $toggleApiResponse = $this->createTogglTask();
        $redmineApiResponse = $this->createRedmineTicket();

        $this->writeLog("-- Redmine response:");
        $this->writeLog(print_r($redmineApiResponse, true));

        if($toggleApiResponse){
            $this->createClubhouseStory($redmineApiResponse->id, $toggleApiResponse->id, $storyId);
        }
        else{
            $this->createClubhouseStory($redmineApiResponse->id, null, $storyId);
        }

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
    private function story_update($action) {
        $storyId = $action->id;
        $changesOnStory = $action->changes;

        // Checks if the story/ticket exists.
        $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();

        if (!$clubhouseStoryObj) {
            $this->writeLog ("-- Story/Epic {$storyId} not created on Redmine.");
            $clubhouseStoryObj = ClubhouseEpic::where('epic_id', $storyId)->first();
            if (!$clubhouseStoryObj) {
                $this->writeLog ("-- Story/Epic {$storyId} not created on Redmine. Creating...");
                $this->createMissingRedmineTicket($storyId);
                $this->writeLog ("-- Story/Epic {$storyId} not updated on Redmine since the ticket is updated.");
                die ("-- Story/Epic {$storyId} not updated on Redmine since the ticket is updated.");
            }
        }

        $storyObj = (array) $this->getStory($storyId);

        if (empty($storyObj['project_id'])) {
            $msg = "-- Empty project ID: Story ID {$storyId}; Story data ".print_r($storyObj, true);
            $this->writeLog($msg);
            $this->errorEmail($msg);
            die($msg);
        }

        $projectId = $storyObj['project_id'];
        $redmineProjectObj = RedmineProject::where('third_party', 'clubhouse')
            ->where('third_party_project_id', $projectId)
            ->first();

        $updatesAsIssueUpdateArray = array();
        $updatesAsTaskUpdateArray = array();
        $listOfFollowersToAdd = array();
        $listOfFollowersToRemove = array();

        foreach ($changesOnStory as $key => $changeOnStory) {

            switch ($key) {
                case "name":
                    $updatesAsIssueUpdateArray['subject'] = $changeOnStory->new;
                    $updatesAsTaskUpdateArray['name']     = "{$storyId} - {$changeOnStory->new}";
                    break;

                case "description":
                    $newDescription = $changeOnStory->new;
                    $newDescription .= "\n\n* Clubhouse URL: {$this->clubhouseBaseUrl}/story/{$storyId}";
                    if ($redmineProjectObj->content) {
                        $newDescription .= "\n\n" . $redmineProjectObj->content;
                    }
                    $updatesAsIssueUpdateArray['description'] = $newDescription;
                    break;

                case "workflow_state_id":
                    $updatesAsIssueUpdateArray['status'] = $this->getRedmineStatus($changeOnStory->new);
                    $updatesAsTaskUpdateArray['active'] = $this->getTogglStatus($changeOnStory->new);
                    break;

                case "story_type":
                    $updatesAsIssueUpdateArray['tracker'] = $this->getRedmineTracker($changeOnStory->new);
                    break;

                case "deadline":
                    $updatesAsIssueUpdateArray['due_date'] = $this->getRedmineDueDate($changeOnStory->new);
                    break;

                case "owner_ids":
                    // Ignore removing owners, only change it when an owner is added
                    if (!empty($changeOnStory->adds)) {
                        $storyOwnerId = reset($changeOnStory->adds);
                        $redmine_user = $this->getRedmineAssignToUser($storyOwnerId);
                        $updatesAsIssueUpdateArray['assigned_to_id'] = $redmine_user;
                    }

                    break;

                case "estimate":
                    $updatesAsTaskUpdateArray['estimated_seconds'] = $this->convertClubhouseEstimateToToggl($changeOnStory->new);
                    break;
            }
        }

        if ($updatesAsIssueUpdateArray) {
            $this->writeLog("Changes to be sent to redmine:");
            $this->writeLog(print_r($updatesAsIssueUpdateArray, true));

            $redmineTicketId = $clubhouseStoryObj->redmine_ticket_id;
            $redmineTicket = $this->redmine->issue->update($redmineTicketId, $updatesAsIssueUpdateArray);
            $this->setAllRedmineChangesAsSent($redmineTicketId, $storyId);
        }
        $this->writeLog ("-- Story {$storyId} was updated on Redmine.");

        if ($updatesAsTaskUpdateArray) {
            $this->writeLog("Changes to be sent to Toggl:");
            $this->writeLog(print_r($updatesAsTaskUpdateArray, true));
            $this->updateTogglTask($clubhouseStoryObj, $updatesAsTaskUpdateArray, $action);
            $this->writeLog ("-- Story {$storyId} was updated on Toggl.");
        }

        die;
    }

    private function story_delete() {
        // We won't deal with this now.
        return false;
    }

    private function createClubhouseTask($redmine_ticket_id, $task_id) {
        $clubhouseTaskObj = new ClubhouseTask();
        $clubhouseTaskObj->redmine_ticket_id = $redmine_ticket_id;
        $clubhouseTaskObj->task_id           = $task_id;
        $clubhouseTaskObj->save();
    }

    private function createClubhouseStory($redmine_ticket_id, $toggl_task_id = null, $story_id) {
        $clubhouseStoryObj = new ClubhouseStory();
        $clubhouseStoryObj->redmine_ticket_id = $redmine_ticket_id;
        $clubhouseStoryObj->toggl_task_id = $toggl_task_id;
        $clubhouseStoryObj->story_id          = $story_id;
        $clubhouseStoryObj->save();
    }

    /**
     * WEBHOOK: Creates the task as a child issue on Redmine.
     */
    private function story_task_create($action) {
        $storyId = $this->content->actions[1]->id;
        $taskId  = $this->content->actions[0]->id;

        // Check if story exists on Redmine
        $clubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();

        if (!$clubhouseStoryObj) {
            $this->writeLog ("-- Story {$storyId} not created on Redmine. Creating...");
            $this->createMissingRedmineTicket($storyId);
        }

        // Check if task has been created already
        $clubhouseTaskObj = ClubhouseTask::where('task_id', $taskId)->first();

        if ($clubhouseTaskObj) {
            $this->writeLog ("-- Task {$taskId} has already been created on Redmine.");
            die ("-- Task {$taskId} has already been created on Redmine.");
        }

        $redmineApiResponse = $this->createRedmineSubTicket();

        $this->createClubhouseTask($redmineApiResponse->id, $taskId);
        $this->createClubhouseStory($redmineApiResponse->id, null, $taskId);

        $this->writeLog ("-- Task {$taskId} has been created on Redmine as a child ticket.");
        die ("-- Task {$taskId} has been created on Redmine as a child ticket.");
    }

    /**
     * WEBHOOK: Updates the issue on Redmine.
     */
    private function story_task_update($action) {

        $storyId       = $action->story_id;
        $taskId        = $action->id;
        $changesOnTask = $action->changes;

        // Checks if the story/ticket exists.
        $redmineClubhouseStoryObj = ClubhouseStory::where('story_id', $storyId)->first();

        if (!$redmineClubhouseStoryObj) {
            $this->writeLog ("-- Story {$storyId} not created on Redmine. Creating...");
            $this->createMissingRedmineTicket($storyId);
        }

        // Checks if the task exists.
        $clubhouseTaskObj = ClubhouseTask::where('task_id', $taskId)->first();

        if (!$clubhouseTaskObj) {
            $this->writeLog ("-- Task {$taskId} not created on Redmine. Creating...");
            $redmineApiResponse = $this->createMissingRedmineSubTicket($taskId);

            $this->createClubhouseStory($redmineApiResponse->id, null, $taskId);

            $this->writeLog ("-- Task {$taskId} created on Redmine.");

            return;
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

            $this->setAllRedmineChangesAsSent($redmineTicketId, $storyId);
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
            $this->writeLog ("-- Story {$storyId} not created on Redmine. Creating...");
            $this->createMissingRedmineTicket($storyId);
            $this->writeLog ("-- Comment {$commentId} not created on Redmine since the ticket is updated.");
            die ("-- Comment {$commentId} not created on Redmine since the ticket is updated.");
        }

        try {
            $redmineTicketId = $redmineClubhouseStoryObj->redmine_ticket_id;
            $commentBody = $contentActions[0]->text;

            // Try to fix problem while sending certain comments to Redmine
            $commentBody = htmlspecialchars($commentBody, ENT_XML1, 'UTF-8');
            $this->writeLog("commentBody:");
            $this->writeLog($commentBody);

            // This method does not return anything (no comment ID).
            $this->redmine->issue->addNoteToIssue($redmineTicketId, $commentBody);

            $this->setAllRedmineChangesAsSent($redmineTicketId, $storyId, $commentId);

            $this->writeLog ("-- Comment {$commentId} sent to Redmine.");
            die ("-- Comment {$commentId} sent to Redmine.");
        } catch (\Exception $e) {
            $this->writeLog ("-- Exception: " . $e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
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
            $this->writeLog ("-- Story {$storyId} not created on Redmine. Creating...");
            $this->createMissingRedmineTicket($storyId);
            $this->writeLog ("-- Comment {$commentId} not updated on Redmine since the ticket is updated.");
            die ("-- Comment {$commentId} not updated on Redmine since the ticket is updated.");
        }

        try {
            $redmineTicketId = $redmineClubhouseStoryObj->redmine_ticket_id;

            // Check if it's a reaction, if so, ignore
            if (!empty($contentActions[0]->changes->reactions)) {
                return;
            }

            $commentBody = "Clubhouse: h3. Comment Update: \n\n";
            $commentBody .= "h4. OLD Body: \n\n";
            $commentBody .= $contentActions[0]->changes->text->old;
            $commentBody .= "\n\n";
            $commentBody .= "h4. NEW Body: \n\n";
            $commentBody .= $contentActions[0]->changes->text->new;

            // This method does not return anything (no comment ID).
            $this->redmine->issue->addNoteToIssue($redmineTicketId, $commentBody);

            $this->setAllRedmineChangesAsSent($redmineTicketId, $storyId);

            $this->writeLog ("-- Comment {$commentId} update sent to Redmine.");
            die ("-- Comment {$commentId} update sent to Redmine.");
        } catch (\Exception $e) {
            $this->writeLog ("-- Exception: " . $e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
            die ("-- Exception: " . $e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
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
            $this->writeLog ("-- Story {$storyId} not created on Redmine. Creating...");
            $this->createMissingRedmineTicket($storyId);
            $this->writeLog ("-- Comment {$commentId} not deleted on Redmine since the ticket is updated.");
            die ("-- Comment {$commentId} not deleted on Redmine since the ticket is updated.");
        }

        try {
            $redmineTicketId = $redmineClubhouseStoryObj->redmine_ticket_id;
            $changedAt = strtotime($this->content->changed_at);
            $changedAtFormatted = date("Y-m-d h:i:sa", $changedAt);

            $commentBody = "Clubhouse: h3. Comment {$commentId} was deleted on Clubhouse. ({$changedAtFormatted})";

            // This method does not return anything (no comment ID).
            $this->redmine->issue->addNoteToIssue($redmineTicketId, $commentBody);

            $this->setAllRedmineChangesAsSent($redmineTicketId, $storyId);

            $this->writeLog ("-- Comment {$commentId} delete comment sent to Redmine.");
            die ("-- Comment {$commentId} delete comment sent to Redmine.");
        } catch (\Exception $e) {
            $this->writeLog ("-- Exception: " . $e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
            die ("-- Exception: " . $e->getMessage() . '<br>Trace: ' . $e->getTraceAsString());
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
            $m->cc(['natali.h@onerhino.com']);
            $m->to('thaissa@onerhino.com', 'Thaissa Mendes')->subject($subject);
        });
    }
    private function writeLog($message) {
        if (is_array($message)) {
            $message = print_r($message, true);
        }

        file_put_contents(storage_path() . '/logs/clubhouse-webhook.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
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

    private function getRedmineStatus(string $workflow_state_id):string {
        $redmine_status = RedmineStatus::where('clubhouse_id', 'like', '%"'.$workflow_state_id.'"%')->first();

        if (!$redmine_status) {
            throw new \Exception("Redmine Status related to Workflow State ID {$workflow_state_id} not found.");
        }

        return $redmine_status->redmine_name;
    }

    private function getTogglStatus(string $workflow_state_id):bool {
        $status = ClubhouseStatus::where('clubhouse_id', $workflow_state_id)->first();

        if (!$status) {
            throw new \Exception("Redmine Status related to Workflow State ID {$workflow_state_id} not found.");
        }

        return $status->type === 'done' ? false : true;
    }

    private function getRedmineTracker(string $story_type):string {
        $redmine_tracker = RedmineTracker::where('clubhouse_name', $story_type)->first();

        if (!$redmine_tracker) {
            throw new \Exception("Redmine Tracker related to Story Type {$story_type} not found.");
        }

        return $redmine_tracker->redmine_name;
    }

    private function getRedmineDueDate(string $deadline):string {
        $deadlineDate = strtotime($deadline);

        return date('Y-m-d', $deadlineDate);
    }

    private function setAllRedmineChangesAsSent($redmine_ticket_id, $clubhouse_story_id, $comment_id = NULL) {
        // Get all changes from Redmine's ticket
        $ticket_details = $this->redmine->issue->show($redmine_ticket_id, ['include' => 'journals']);

        if (!isset($ticket_details['issue'])) {
            $this->writeLog("Missing Issue key from Redmine Ticket");
            $this->writeLog("Ticket ID: ".$redmine_ticket_id);
            $this->writeLog($ticket_details);
            $this->errorEmail("Missing Issue key from Redmine Ticket: ".print_r($ticket_details, true));
            return;
        }

        $ticket_changes = $ticket_details['issue']['journals'];

        foreach ($ticket_changes as $_change) {
            // Check if change has already been recorded
            $RedmineClubhouseChange = RedmineClubhouseChange::where('redmine_change_id', $_change['id'])->first();

            if (!$RedmineClubhouseChange) {
                // Change not recorded, so save it
                $redmineClubhouseChangeObj = new RedmineClubhouseChange;
                $redmineClubhouseChangeObj->redmine_change_id   = $_change['id'];
                $redmineClubhouseChangeObj->clubhouse_change_id = $clubhouse_story_id;
                $redmineClubhouseChangeObj->clubhouse_comment_id = $comment_id ? $comment_id : NULL;
                $redmineClubhouseChangeObj->save();
            }
        }
    }
}
