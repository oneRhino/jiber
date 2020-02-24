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
    protected $signature = 'clubhouse-to-redmine:sync-created-tasks';

    /**
    * The console command description.
    *
    * @var string
    */
    protected $description = 'Syncs created tickets on Clubhouse and Redmine.';

    private $debug = true;

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

        // Grab tickets recently created on Clubhouse
        $tickets = $this->getClubhouseTickets();

        // Create tickets on Clubhouse, and get Redmine ID/Clubhouse ID combinations
        $this->createTicketsRedmine($tickets);

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

            $isProjectSynced = RedmineClubhouseProject::where('clubhouse_id', $clubhouseProject['id'])->first();

            if (!$isProjectSynced) {
                $this->writeLog("-- Project {$clubhouseProject['id']} was not yet imported to Jiber, CONTINUE");
                continue;
            }

            $projectTickets = $clubhouseControllerObj->getTickets($clubhouseProject['id']);

            foreach ($projectTickets as $projectTicket) {
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
    * Sends the tickets to Redmine using the API.
    */
    private function createTicketsRedmine ($tickets) {

        if (!$tickets) {
            return false;
        }

        $user = User::find(7);
        $this->loginUser($user);

        $redmineController = new RedmineController();
        $redmineControllerInstance = $redmineController->connect();
        
        foreach ($tickets as $ticket) {
            try {
                
                $redmineProjectName = RedmineClubhouseProject::where('clubhouse_id', $ticket['project_id'])->get(['redmine_name'])->first();
                
                if ($this->debug) {
                    $redmineProjectName = $redmineProjectName->redmine_name;
                } else {
                    $redmineProjectName = 'omg-test';
                }

                $redmineCreateIssueObj = array ();
                $redmineCreateIssueObj['project_id'] = $redmineProjectName;
                $redmineCreateIssueObj['subject'] = $ticket['name'];
                $redmineCreateIssueObj['description'] = $ticket['description'];
                $redmineCreateIssueObj['assigned_to'] = 'admin';

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
                
                $redmineClubhouseTaskInstance->save();
                $this->writeLog("-- Task {$ticket['id']} saved on database."); 
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
        if ($this->debug) {
        	file_put_contents('redmine-create.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
        }
    }
}
