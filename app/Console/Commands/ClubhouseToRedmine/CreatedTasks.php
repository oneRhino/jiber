<?php

namespace App\Console\Commands\ClubhouseToRedmine;

use Mail;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\User;
use App\Setting;
use App\RedmineClubhouseProject;
use App\RedmineClubhouseTask;
use App\RedmineClubhouseTracker;
use App\Http\Controllers\ClubhouseController;
use App\Http\Controllers\RedmineController;
use Redmine\Client as RedmineClient;

class CreatedTasks extends Command
{
    /**
    * The name and signature of the console command.
    *
    * @var string
    */
    protected $signature = 'clubhouse-to-redmine:sync-created-tasks {--limit=} {--source=} {--debug}';

    /**
    * The console command description.
    *
    * @var string
    */
    protected $description = 'Syncs created tickets on Clubhouse and Redmine.';

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
    public function handle()
    {
        $this->writeLog('***** INIT *****');

        if ($this->option('debug')) {
            $this->debug = true;
        }

        if (!$this->option('source')) {
            $this->writeLog('-- Getting tickets from Clubhouse');
            // Grab tickets recently created on Clubhouse
            $tickets = $this->getClubhouseTickets();
            // Create tickets on Clubhouse, and get Redmine ID/Clubhouse ID combinations
            $this->createTicketsRedmine($tickets);
            
            $this->writeLog('-- Getting tickets from Redmine');
            // Grab tickets recently created on Redmine
            $tickets = $this->getRedmineTickets();
            // Create tickets on Redmine, and get Redmine ID/Clubhouse ID combinations
            $this->createTicketsClubhouse($tickets);
        } else {
            if ($this->option('source') == 'clubhouse') {
                $this->writeLog('-- Getting tickets from Clubhouse');
                // Grab tickets recently created on Clubhouse
                $tickets = $this->getClubhouseTickets();
                // Create tickets on Clubhouse, and get Redmine ID/Clubhouse ID combinations
                $this->createTicketsRedmine($tickets);
            }

            if ($this->option('source') == 'redmine') {
                $this->writeLog('-- Getting tickets from Redmine');
                // Grab tickets recently created on Redmine
                $tickets = $this->getRedmineTickets();
                // Create tickets on Redmine, and get Redmine ID/Clubhouse ID combinations
                $this->createTicketsClubhouse($tickets);
            }
        }

        $this->writeLog('***** END *****');
    }

    /**
    * Get all Clubhouse tickets, except the ones not on RedmineClubhouseProjects
    */
    private function getClubhouseTickets () {

        $clubhouseControllerObj = new clubhouseController() ;

        $clubhouseProjects = $clubhouseControllerObj->getProjects();

        $newTickets = array ();

        foreach ($clubhouseProjects as $clubhouseProject) {

            // Ignore ARCHIVED projects.
            if ($clubhouseProject['archived']) {
                $this->writeLog("-- Project {$clubhouseProject['id']} is already archived, CONTINUE");
                continue;
            }

            $redmineClubhouseProjectObj = RedmineClubhouseProject::where('clubhouse_id', $clubhouseProject['id'])->first();

            // Ignore projects imported yet.
            if (!$redmineClubhouseProjectObj) {
                $this->writeLog("-- Project {$clubhouseProject['id']} was not yet imported to Jiber, CONTINUE");
                continue;
            }
            
            // Ignore projects that has no relation with a Redmine project.
            if (!$redmineClubhouseProjectObj->redmine_id) {
                $this->writeLog("-- Project {$clubhouseProject['id']} is not related to any project on Redmine, CONTINUE");
                continue;
            }
            
            $projectTickets = $clubhouseControllerObj->getTickets($clubhouseProject['id']);

            foreach ($projectTickets as $projectTicket) {
                
                // Ignore ARCHIVED tickets.
                if ($projectTicket['archived']) {
                    $this->writeLog("-- Task {$projectTicket['id']} is already archived, CONTINUE");
                    continue;
                }

                $isTicketSynced = RedmineClubhouseTask::where('clubhouse_task', $projectTicket['id'])->first(); 
            
                if ($isTicketSynced) {
                    $this->writeLog("-- Task {$projectTicket['id']} from Project {$clubhouseProject['id']} already exists on Redmine, CONTINUE");
                    continue;
                } else {
                    $this->writeLog("-- Task {$projectTicket['id']} from Project {$clubhouseProject['id']} will be created on Redmine,");
                    $newTickets[] = $projectTicket;
                }
            
            }
        }

        return $newTickets;
    }

    /**
    * Get all Redmine tickets, except the ones not on RedmineClubhouseProjects
    */
    private function getRedmineTickets() {

        $tickets = array();

        // Get user
        $user = User::find(7);

        // Set user as logged-in user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        Auth::setUser($user);

        $RedmineController = new RedmineController;
        $Redmine = $RedmineController->connect();

        $redmineClubhouseProjectObjs = RedmineClubhouseProject::get();
        
        foreach ($redmineClubhouseProjectObjs as $redmineClubhouseProjectObj) {
            if (!$redmineClubhouseProjectObj->redmine_id) {
                $this->writeLog('-- No Redmine project related to: ' . $redmineClubhouseProjectObj->clubhouse_name);
                continue;
            }
            
            $this->writeLog('-- Checking for new tickets on project: ' . $redmineClubhouseProjectObj->redmine_name);

            $args = array(
                'limit' => 100,
                'sort' => 'created_on:desc',
                'include' => 'attachments',
                'project_id' => $redmineClubhouseProjectObj->redmine_id, 
            );

            $redmine_entries = $Redmine->issue->all($args);
            $this->writeLog('Redmine new tasks');

            foreach ($redmine_entries['issues'] as $_issue) {
                // Check if task has already been created on Clubhouse (RedmineClubhouseTask)
                $task = RedmineClubhouseTask::where('redmine_task', $_issue['id'])->first();
                if ($task) {
                    $this->writeLog('-- Task '.$_issue['id'].' already been created on Clubhouse, CONTINUE');
                    continue;
                }

                $this->writeLog("Ticket {$_issue['id']} will be created on Clubhouse");

                $ticketArray = array();
                $ticketArray['clubhouse_project_id'] = $redmineClubhouseProjectObj['clubhouse_id'];
                $ticketArray['ticket_details'] = $_issue;

                $tickets[] = $ticketArray;
            }
        }
        
        return $tickets;
    }
    
    /**
    * Sends the tickets to Clubhouse using the API.
    */
    private function createTicketsClubhouse ($tickets) {

        if (!$tickets) {
            return false;
        }

        if ($this->option('limit')) {
            $tickets = array_slice ($tickets, 0, $this->option('limit'));
        }
        
        foreach ($tickets as $ticket) {
            try {
                if (RedmineClubhouseTask::where('clubhouse_task', $ticket['clubhouse_project_id'])->first()) {
                    $this->writeLog("-- Task {$ticket['clubhouse_project_id']} already been created on Clubhouse, CONTINUE");
                    continue;
                } 
                
                $clubhouseProjectId = $ticket['clubhouse_project_id'];
                $redmineTicket = $ticket['ticket_details'];
                $redmineTicketName = '(' .  $redmineTicket['author']['name'] . ') ' . $redmineTicket['subject'];
                $redmineTicketType = RedmineClubhouseTracker::where('redmine_name', $redmineTicket['tracker']['name'])->select('clubhouse_name')->first();

                $clubhouseCreateIssueObj = array ();
                $clubhouseCreateIssueObj['project_id'] = $clubhouseProjectId;
                $clubhouseCreateIssueObj['name'] = $redmineTicketName;
                $clubhouseCreateIssueObj['story_type'] = $redmineTicketType->clubhouse_name;
                $clubhouseCreateIssueObj['description'] = $redmineTicket['description'];

                if ($this->debug) {
                    $this->writeLog("-- Task {$redmineTicket['id']} NOT sent to Clubhouse due to debug mode."); 
                } else {
                    $clubhouseControllerObj = new clubhouseController() ;
                    $clubhouseStory = $clubhouseControllerObj->createStory($clubhouseCreateIssueObj);
                    $this->writeLog("-- Task {$redmineStory['id']} sent to Clubhouse."); 
                }

                $redmineClubhouseTaskInstance = new RedmineClubhouseTask();
                $redmineClubhouseTaskInstance->redmine_task = $redmineTicket['id'];
                $redmineClubhouseTaskInstance->clubhouse_task =$this->debug ? 'debug_mode' : $clubhouseStory['id'];
                $redmineClubhouseTaskInstance->source = 'Redmine';
                
                if ($this->debug) {
                    $this->writeLog("-- Task {$redmineTicket['id']} NOT saved on database due to debug mode."); 
                } else {
                    $redmineClubhouseTaskInstance->save();
                    $this->writeLog("-- Task {$redmineTicket['id']} saved on database."); 
                }

            } catch (\Exeption $e) { 
                $this->writeLog("-- Task {$ticket['id']} could not be sent to Redmine, Error: {$e->getMessage()}"); 
            }
        }

        return TRUE;
    }

    /**
    * Sends the tickets to Redmine using the API.
    */
    private function createTicketsRedmine ($tickets) {

        if (!$tickets) {
            return false;
        }

        if ($this->option('limit')) {
            $tickets = array_slice ($tickets, 0, $this->option('limit'));
        }

        $user = User::find(7);
        $this->loginUser($user);

        $redmineController = new RedmineController();
        $redmineControllerInstance = $redmineController->connect();
        
        foreach ($tickets as $ticket) {
            try {
                $redmineClubhouseProject = RedmineClubhouseProject::where('clubhouse_id', $ticket['project_id'])->get(['redmine_name', 'content'])->first();
                
                if (!$redmineClubhouseProject->redmine_name ) {
                    continue;
                }

                $redmineCreateIssueObj = array ();
                $redmineCreateIssueObj['project_id'] = $redmineProjectName;
                $redmineCreateIssueObj['subject'] = $ticket['name'];
                $redmineCreateIssueObj['assigned_to'] = 'admin';
                $redmineCreateIssueObj['description'] = $ticket['description']; 
                if ($redmineClubhouseProject->content) {
		            $redmineCreateIssueObj['description'] .= "\n" . $redmineClubhouseProject->content;
	            }

                if ($this->debug) {
                    $this->writeLog("-- Task {$ticket['id']} NOT sent to Redmine due to debug mode."); 
                } else {
                    $redmineApiResponse = $redmineControllerInstance->issue->create($redmineCreateIssueObj);
                    $this->writeLog("-- Task {$ticket['id']} sent to Redmine."); 
                }

                $redmineClubhouseTaskInstance = new RedmineClubhouseTask();
                $redmineClubhouseTaskInstance->redmine_task = $this->debug ? "debug mode" : $redmineApiResponse->id;
                $redmineClubhouseTaskInstance->clubhouse_task = $ticket['id'];
                $redmineClubhouseTaskInstance->source = 'Clubhouse';
                
                if ($this->debug) {
                    $this->writeLog("-- Task {$ticket['id']} NOT saved on database due to debug mode."); 
                } else {
                    $redmineClubhouseTaskInstance->save();
                    $this->writeLog("-- Task {$ticket['id']} saved on database."); 
                }

            } catch (\Exeption $e) { 
                $this->writeLog("-- Task {$ticket['id']} could not be sent to Redmine, Error: {$e->getMessage()}"); 
            }
        }

        return TRUE;
    }

    /**
    * Helper to access Remine API. 
    */
    private function loginUser($user) {

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
        file_put_contents('redmine-create.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
    }
}
