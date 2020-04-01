<?php

namespace App\Console\Commands\RedmineToClubhouse;

use Mail;
use Illuminate\Console\Command;
use App\{RedmineProject, RedmineStatus, RedmineClubhouseChange, RedmineClubhouseTask, RedmineClubhouseUser, RedmineJiraUser};
use App\Http\Controllers\ClubhouseController;

class UpdatedTasks extends Command
{
    use Redmine;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redmine-to-clubhouse:sync-updated-tasks {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs updated tickets from Redmine to Clubhouse.';

    private $debug = false;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {

        $this->writeLog('***** INIT *****');

        if ($this->option('debug')) {
            $this->debug = true;
        }

        $this->sendRedmineChangesToClubhouse ();

        $this->writeLog('***** END *****');
    }

    /**
    * Get recent Redmine ticket changed (only realted to Clubhouse Projects).
    *
    * @return array
    */
    private function sendRedmineChangesToClubhouse () {

        $this->login();

        // Current date
	    $date = date('Y-m-d', strtotime("-20 minutes"));;

        $redmineProjectObjs = RedmineProject::clubhouse()->get();

        $newChanges = array();

        foreach ($redmineProjectObjs as $redmineProjectObj) {
            if (!$redmineProjectObj->third_party_project_id) {
                $this->writeLog('-- No Clubhouse project related to: ' . $redmineProjectObj->project_name);
                continue;
            }

            $this->writeLog('-- Checking for new changes on project: ' . $redmineProjectObj->project_name);

            // Current date
	        $date = date('Y-m-d', strtotime("-20 minutes"));;

            $args = array(
                'updated_on' => '>=' . $date,
                'limit'      => 100,
                'sort'       => 'updated_on:desc',
                'include'    => 'attachments',
                'project_id' => $redmineProjectObj->project_id,
            );

            $redmineEntries = $this->redmine->issue->all($args);

            foreach ($redmineEntries as $redmineEntry) {

                if (!$redmineEntry || !is_array($redmineEntry)) {
                    continue;
                }

                foreach ($redmineEntry as $entryDetail) {

                    $entryDetailId = $entryDetail['id'];

                    $args = array('include' => 'journals');
                    $entryJournals = $this->redmine->issue->show($entryDetailId, $args);
                    $entryJournals = $entryJournals['issue']['journals'];

                    foreach ($entryJournals as $entryJournal) {

                        $created = strtotime($entryJournal['created_on']);
                        $lastmin = mktime(date('H'), date('i')-10);

                        if ($created < $lastmin) {
                            $this->writeLog("Old entry ({$entryJournal['created_on']}), CONTINUE");
                            continue;
                        }

                        // Ticket comments.
                        if ($entryJournal['notes']) {
                            // ADD Redmine Journal ID.
                            $redmineChangeId = $entryJournal['id'];
                            $this->createClubhouseStoryComment($entryDetailId, $redmineChangeId, $entryJournal);
                        }

                        // Check if change was already sent to Clubhouse.
                        if (RedmineClubhouseChange::where('redmine_change_id', $entryJournal['id'])->first()) {
                            if (!$entryJournal['notes'])
                                $this->writeLog('-- Change already sent to Clubhouse: ' . $entryJournal['id'] . ', CONTINUE');
                            continue;
                        }

                        // Ticket updates.
                        if ($entryJournal['details']) {
                            foreach ($entryJournal['details'] as $detail) {
                                // ADD Redmine Journal ID.
                                $redmineChangeId = $entryJournal['id'];

                                switch ($detail['name']) {
                                    case 'subject':
                                        $changeArray = array ();
                                        $changeArray['name'] = $detail['new_value'];
                                        $this->updateClubhouseStory ($entryDetailId, $redmineChangeId, $changeArray);
                                        break;
                                    case 'description':
                                        $changeArray = array ();
                                        $changeArray['description'] = $detail['new_value'];
                                        $this->updateClubhouseStory ($entryDetailId, $redmineChangeId, $changeArray);
                                        break;
                                    case 'status_id':
                                        $changeArray = array ();
                                        $status = $this->getWorkflowStateID($detail['new_value']);
                                        if ($status) {
                                            $changeArray['workflow_state_id'] = $status;
                                            $this->updateClubhouseStory ($entryDetailId, $redmineChangeId, $changeArray);
                                        }
                                        break;
                                    case 'priority_id':
                                        $this->writeLog('-- Priority field not exists on Clubhouse, CONTINUE');
                                        break;
                                    case 'start_date':
                                        $this->writeLog('-- Start Date field not exists on Clubhouse, CONTINUE');
                                        break;
                                    case 'due_date':
                                        $changeArray = array ();
                                        $changeArray['deadline'] = $detail['new_value'];
                                        $this->updateClubhouseStory ($entryDetailId, $redmineChangeId, $changeArray);
                                        break;
                                    case 'estimated_hours':
                                        $this->writeLog('-- Estimate Hours field not exists on Clubhouse, CONTINUE');
                                        break;
                                    case 'assigned_to_id':
                                        $changeArray = array ();
                                        $owner = $this->getClubhouseUserID($detail['new_value']);
                                        if ($owner) {
                                            $changeArray['owner_ids'] = $owner;
                                            $this->updateClubhouseStory ($entryDetailId, $redmineChangeId, $changeArray);
                                        }
                                        break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Send comment to a Clubhouse story.
    private function createClubhouseStoryComment ($redmineTicketId, $redmineChangeId, $change) {

        if (!$change) {
            $this->writeLog('-- No new changes on Redmine tickets.');
            return;
        }

        if (RedmineClubhouseChange::where('redmine_change_id', $redmineChangeId)->first()) {
            $this->writeLog('-- Comment will be updated on Clubhouse: ' . $redmineChangeId);

            $storyObj = RedmineClubhouseTask::where('redmine_task', $redmineTicketId)->first();
            $storyId = $storyObj->clubhouse_task;

            $comment = "(" . $change['user']['name'] . ") " . $change['notes'];

            // Send comment to Clubhouse Story.
            if (!$this->debug) {

                $changeObj = RedmineClubhouseChange::where('redmine_change_id', $redmineChangeId)->first();
                $changeId = $changeObj->clubhouse_change_id;

                $clubhouseControllerObj = new ClubhouseController();
                $clubhouseComment = $clubhouseControllerObj->updateComment($storyId, $changeId, $comment);

                $this->writeLog('-- Comment update sent to Clubhouse: ' . $redmineChangeId);
            } else {
                $this->writeLog('-- Comment update NOT sent to Clubhouse due to Debug Mode');
            }
        } else {
            $this->writeLog('-- Comment will be sent on Clubhouse: ' . $redmineChangeId);
            $storyObj = RedmineClubhouseTask::where('redmine_task', $redmineTicketId)->first();
            $storyId = $storyObj->clubhouse_task;

            $comment = "(" . $change['user']['name'] . ") " . $change['notes'];

            // Send comment to Clubhouse Story.
            if (!$this->debug) {
                $clubhouseControllerObj = new ClubhouseController();
                $clubhouseComment = $clubhouseControllerObj->createComment($storyId, $comment);

                $redmineClubhouseChangeObj = new RedmineClubhouseChange;
                $redmineClubhouseChangeObj->redmine_change_id = $redmineChangeId;
                $redmineClubhouseChangeObj->clubhouse_change_id = $clubhouseComment['id'];
                $redmineClubhouseChangeObj->save();

                $this->writeLog('-- Comment sent to Clubhouse: ' . $redmineChangeId);
            } else {
                $this->writeLog('-- Comment NOT sent to Clubhouse due to Debug Mode');
            }
        }
    }

    // Update a Clubhouse story.
    private function updateClubhouseStory ($redmineTicketId, $redmineChangeId, $change) {

        if (!$change) {
            return null;
        }

        if (RedmineClubhouseChange::where('redmine_change_id', $redmineChangeId)->first()) {
            $this->writeLog('-- Change already sent to Clubhouse: ' . $redmineChangeId);
            return null;
        }

        $storyObj = RedmineClubhouseTask::where('redmine_task', $redmineTicketId)->first();
        $storyId = $storyObj->clubhouse_task;

        // Send update to Clubhouse Story.
        if (!$this->debug) {
            $clubhouseControllerObj = new ClubhouseController();
            $clubhouseUpdate = $clubhouseControllerObj->updateStory($storyId, $change);

            $redmineClubhouseChangeObj = new RedmineClubhouseChange;
            $redmineClubhouseChangeObj->redmine_change_id = $redmineChangeId;
            $redmineClubhouseChangeObj->clubhouse_change_id = $clubhouseUpdate['id'];
            $redmineClubhouseChangeObj->save();

            $this->writeLog('-- Change sent to Clubhouse: ' . $redmineChangeId);
        } else {
            $this->writeLog('-- Change NOT sent to Clubhouse due to Debug Mode');
        }
    }

    private function getWorkflowStateID($redmine_id) {
        $redmine_status = RedmineStatus::where('redmine_id', $redmine_id)->first();

        if (!$redmine_status) {
            $this->writeLog("-- Status {$redmine_id} not found on Redmine Statuses");
            return false;
        }

        $clubhouse_statuses = $redmine_status->clubhouse_ids;

        if (!$clubhouse_statuses) {
            $this->writeLog("-- Status {$redmine_status->redmine_name} not linked to a Clubhouse Status");
            return false;
        }

        // Return first clubhouse status
        return reset($clubhouse_statuses);
    }

    private function getClubhouseUserId($redmine_id) {
        // Get Redmine name from redmine_jira_users
        $redmine_jira_user = RedmineJiraUser::where('redmine_id', $redmine_id)->first();

        if (!$redmine_jira_user) {
            $this->writeLog("-- User {$redmine_id} not found on RedmineJiraUser");
            return false;
        }

        $redmine_name = $redmine_jira_user->redmine_name;

        $redmine_clubhouse_user = RedmineClubhouseUser::where('redmine_names', 'like', "%{$redmine_name}%")->first();

        if (!$redmine_clubhouse_user) {
            $this->writeLog("-- User {$redmine_name} not found on RedmineClubhouseUser");
            return false;
        }

        return $redmine_clubhouse_user->clubhouse_user_id;
    }

    private function writeLog($message) {

        file_put_contents('redmine-clubhouse-update.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
    }
}
