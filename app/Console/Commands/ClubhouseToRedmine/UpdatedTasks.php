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
    protected $signature = 'clubhouse-to-redmine:sync-updated-tasks {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs updated tickets from Redmine and Clubhouse.';

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

        $newChanges = $this->getRedmineChanges ();
        
        $this->updateClubhouse ($newChanges);

        $this->writeLog('***** END *****');
    }

    /**
    * Get recent Redmine ticket changed (only realted to Clubhouse Projects).
    *
    * @return array
    */
    private function getRedmineChanges () {

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
            
            $this->writeLog('-- Checking for new tickets on project: ' . $redmineClubhouseProjectObj->redmine_name);

            $args = array(
                'updated_on' => '>=' . $date,
                'limit' => 100,
                'sort' => 'created_on:desc',
                'include' => 'attachments',
                'project_id' => $redmineClubhouseProjectObj->redmine_id, 
            );

            $redmineEntries = $Redmine->issue->all($args);

            foreach ($redmineEntries as $redmineEntry) {

                if (!isset($redmineEntry[0])) {
                    continue;
                }

                $redmineTicketId = $redmineEntry[0]['id']; 
                
                $args = array('include' => 'journals');
                $entryJournals = $Redmine->issue->show($redmineTicketId, $args);
                $entryJournals = $entryJournals['issue']['journals'];

                foreach ($entryJournals as $entryJournal) {

                    // Check if 'comment' exists.
                    if (!$entryJournal['notes'])
                        continue;

                    // Check if 'comment' was already sent to Clubhouse.
                    if (RedmineClubhouseChange::where('redmine_change_id', $entryJournal['id'])->first()) {
                        $this->writeLog('-- Change already sent to Clubhouse: ' . $entryJournal['id'] . ', CONTINUE');
                        continue; 
                    }

                    $entryJournal['redmine_ticket_id'] = $redmineTicketId;

                    $this->writeLog('-- Change ' . $entryJournal['id'] . ' will be sent to Clubhouse.');
                    $newChanges[] = $entryJournal;
                }
            }
        }

        return $newChanges;
    }

    private function updateClubhouse ($newChanges) {
    
        if (!$newChanges) {
            $this->writeLog('-- No new changes on Redmine tickets.');
            return;
        }

        foreach ($newChanges as $newChange) {
            
            $redmineTicketId = $newChange['redmine_ticket_id'];
            $storyObj = RedmineClubhouseTask::where('redmine_task', $redmineTicketId)->first();

            if (!$storyObj) {
                $this->writeLog('-- Task not mapped in database, CONTINUE');
                continue;
            }

            $storyId = $storyObj->clubhouse_task;
            $comment = "(" . $newChange['user']['name'] . ") " . $newChange['notes'];
            
            // Send comment to Clubhouse Story.
            if (!$this->debug) {
                $clubhouseControllerObj = new ClubhouseController();
                $clubhouseComment = $clubhouseControllerObj->createComment($storyId, $comment);

                $redmineClubhouseChangeObj = new RedmineClubhouseChange;
                $redmineClubhouseChangeObj->redmine_change_id = $newChange['id'];
                $redmineClubhouseChangeObj->save();
                
                $this->writeLog('-- Change sent to Clubhouse: ' . $newChange['id']);
            } else {
                $this->writeLog('-- Change NOT sent to Clubhouse due to Debug Mode');
            }
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
