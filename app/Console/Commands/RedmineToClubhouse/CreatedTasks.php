<?php

namespace App\Console\Commands\RedmineToClubhouse;

use Mail;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Config};
use App\{Setting, User};
use App\{RedmineProject, RedmineStatus, RedmineTracker, RedmineClubhouseTask, RedmineClubhouseUser};
use App\Http\Controllers\{ClubhouseController, RedmineController};
use Redmine\Client as RedmineClient;

class CreatedTasks extends Command
{
    /**
    * The name and signature of the console command.
    *
    * @var string
    */
    protected $signature = 'redmine-to-clubhouse:sync-created-tasks {--limit=} {--debug}';

    /**
    * The console command description.
    *
    * @var string
    */
    protected $description = 'Syncs created tickets from Redmine to Clubhouse.';

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

        $this->writeLog('-- Getting tickets from Redmine');

        // Grab tickets recently created on Redmine
        $tickets = $this->getRedmineTickets();

        // Create tickets on Redmine, and get Redmine ID/Clubhouse ID combinations
        $this->createTicketsClubhouse($tickets);

        $this->writeLog('***** END *****');
    }

    /**
    * Get all Redmine tickets, except the ones not on RedmineProjects
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

        $redmineProjectObjs = RedmineProject::clubhouse()->get();

        foreach ($redmineProjectObjs as $redmineProjectObj) {
            if (!$redmineProjectObj->third_party_project_id) {
                $this->writeLog('-- No Clubhouse project related to: ' . $redmineProjectObj->project_name);
                continue;
            }

            $this->writeLog('-- Checking for new tickets on project: ' . $redmineProjectObj->project_name);

            $args = array(
                'limit'      => 100,
                'sort'       => 'created_on:desc',
                'include'    => 'attachments',
                'project_id' => $redmineProjectObj->project_id,
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
                $ticketArray['clubhouse_project_id'] = $redmineProjectObj['third_party_project_id'];
                $ticketArray['ticket_details']       = $_issue;

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

                print_r($ticket);

                $clubhouseCreateIssueObj = [
                    'project_id'        => $ticket['clubhouse_project_id'],
                    'name'              => $this->getTicketName($ticket),
                    'story_type'        => $this->getStoryType($ticket),
                    'description'       => $this->getTicketDescription($ticket),
                    'deadline'          => $this->getDeadline($ticket),
                    'requested_by_id'   => $this->getRequestedBy($ticket),
                    'owner_ids'         => $this->getOwnerIDs($ticket),
                    'workflow_state_id' => $this->getWorkflowStateID($ticket),
                ];

                if ($this->debug) {
                    $this->writeLog("-- Task {$ticket['ticket_details']['id']} NOT sent to Clubhouse due to debug mode.");
                } else {
                    $clubhouseControllerObj = new clubhouseController() ;
                    $clubhouseStory         = $clubhouseControllerObj->createStory($clubhouseCreateIssueObj);

                    $this->writeLog("-- Task {$ticket['ticket_details']['id']} sent to Clubhouse.");
                }

                $redmineClubhouseTaskInstance = new RedmineClubhouseTask();
                $redmineClubhouseTaskInstance->redmine_task   = $ticket['ticket_details']['id'];
                $redmineClubhouseTaskInstance->clubhouse_task = $this->debug ? 'debug_mode' : $clubhouseStory['id'];
                $redmineClubhouseTaskInstance->source         = 'Redmine';

                if ($this->debug) {
                    $this->writeLog("-- Task {$ticket['ticket_details']['id']} NOT saved on database due to debug mode.");
                } else {
                    $redmineClubhouseTaskInstance->save();
                    $this->writeLog("-- Task {$ticket['ticket_details']['id']} saved on database.");
                }

            } catch (\Exeption $e) {
                $this->writeLog("-- Task {$ticket['ticket_details']['id']} could not be sent to Redmine, Error: {$e->getMessage()}");
            }
        }

        return TRUE;
    }

    private function getTicketName($ticket) {
        $redmineTicket     = $ticket['ticket_details'];
        $redmineTicketName = '(' .  $redmineTicket['author']['name'] . ') ' . $redmineTicket['subject'];

        return $redmineTicketName;
    }

    private function getStoryType($ticket) {
        $redmineTicket     = $ticket['ticket_details'];

        $redmineTicketType  = RedmineTracker::where('redmine_name', $redmineTicket['tracker']['name'])->select('clubhouse_name')->first();

        return $redmineTicketType->clubhouse_name;
    }

    private function getTicketDescription($ticket) {
        return $ticket['ticket_details']['description'];
    }

    private function getDeadline($ticket) {
        return $ticket['ticket_details']['due_date'] ?? '';
    }

    private function getRequestedBy($ticket) {
        $author = $ticket['ticket_details']['author']['name'];

        return $this->getClubhouseUserId($author);
    }

    private function getOwnerIDs($ticket) {
        $assignee = $ticket['ticket_details']['assigned_to']['name'];

        return [$this->getClubhouseUserId($assignee)];
    }

    private function getClubhouseUserId($redmine_name) {
        $redmine_clubhouse_user = RedmineClubhouseUser::where('redmine_names', 'like', "%{$redmine_name}%")->first();

        if (!$redmine_clubhouse_user) {
            $this->writeLog("-- User {$redmine_name} not found on RedmineClubhouseUser");
            return false;
        }

        return $redmine_clubhouse_user->clubhouse_user_id;
    }

    private function getWorkflowStateID($ticket) {
        $status = $ticket['ticket_details']['status']['name'];

        $redmine_status = RedmineStatus::where('redmine_name', $status)->first();

        if (!$redmine_status) {
            $this->writeLog("-- Status {$status} not found on Redmine Statuses");
            return false;
        }

        $clubhouse_statuses = $redmine_status->clubhouse_ids;

        if (!$clubhouse_statuses) {
            $this->writeLog("-- Status {$status} not linked to a Clubhouse Status");
            return false;
        }

        // Return first clubhouse status
        return reset($clubhouse_statuses);
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
        file_put_contents('redmine-clubhouse-create.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
    }
}
