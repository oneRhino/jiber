<?php

namespace App\Console\Commands\ClubhouseToRedmine;

//use Log;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\User;
use App\Setting;
use App\RedmineClubhouseChange;
use App\RedmineClubhouseProject;
use App\RedmineClubhouseTask;
use App\Http\Controllers\ClubhouseController;
use App\Http\Controllers\RedmineController;

class UpdatedTasks extends Command
{
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

        $user = User::find(7);
        $this->loginUser($user);

        // Current date
	    $date = date('Y-m-d', strtotime("-20 minutes"));;

        // Get Redmine tasks updated this date
        $RedmineController = new RedmineController;
        $Redmine = $RedmineController->connect();

        $redmineClubhouseProjectObjs = RedmineClubhouseProject::get();

        $newChanges = array();

        foreach ($redmineClubhouseProjectObjs as $redmineClubhouseProjectObj) {
            if (!$redmineClubhouseProjectObj->redmine_id) {
                $this->writeLog('-- No Redmine project related to: ' . $redmineClubhouseProjectObj->clubhouse_name);
                continue;
            }

            $this->writeLog('-- Checking for new changes on project: ' . $redmineClubhouseProjectObj->redmine_name);

            // Current date
	        $date = date('Y-m-d', strtotime("-20 minutes"));;

            $args = array(
                'updated_on' => '>=' . $date,
                'limit' => 100,
                'sort' => 'updated_on:desc',
                'include' => 'attachments',
                'project_id' => $redmineClubhouseProjectObj->redmine_id,
            );

            $redmineEntries = $Redmine->issue->all($args);

            foreach ($redmineEntries as $redmineEntry) {

                if (!$redmineEntry || !is_array($redmineEntry)) {
                    continue;
                }

                foreach ($redmineEntry as $entryDetail) {

                    $entryDetailId = $entryDetail['id'];

                    $args = array('include' => 'journals');
                    $entryJournals = $Redmine->issue->show($entryDetailId, $args);
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
                                        $this->writeLog('-- Status field not exists on Clubhouse, CONTINUE');
                                        break;
                                    case 'priority_id':
                                        $this->writeLog('-- Priority field not exists on Clubhouse, CONTINUE');
                                        break;
                                    case 'start_date':
                                        $this->writeLog('-- Start Date field not exists on Clubhouse, CONTINUE');
                                        break;
                                    case 'due_date':
                                        $this->writeLog('-- Due Date field not exists on Clubhouse, CONTINUE');
                                        break;
                                    case 'estimated_hours':
                                        $this->writeLog('-- Estimate Hours field not exists on Clubhouse, CONTINUE');
                                        break;
                                    case 'assigned_to_id':
                                        $this->writeLog('-- Assigned To ID field not exists on Clubhouse, CONTINUE');
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
            $this->writeLog('-- Change aleready sent to Clubhouse: ' . $redmineChangeId);
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


    private function loginUser ($user) {

        // Set user as logged-in user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        Auth::setUser($user);

        return $request;
    }

    private function writeLog($message) {

	    file_put_contents('redmine-update.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
    }
}
