<?php

namespace App\Console\Commands\RedmineToJira;

//use Log;
use Mail;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\User;
use App\Setting;
use App\RedmineJiraPriority;
use App\RedmineJiraProject;
use App\RedmineJiraTask;
use App\RedmineJiraTracker;
use App\RedmineJiraUser;
use App\Http\Controllers\JiraController;
use App\Http\Controllers\RedmineController;

class CreatedTasks extends Command
{
    /**
    * The name and signature of the console command.
    *
    * @var string
    */
    protected $signature = 'redmine-to-jira:sync-created-tasks';

    /**
    * The console command description.
    *
    * @var string
    */
    protected $description = 'Syncs created tickets on Redmine and Jira.';

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

        // Grab tickets recently created on Redmine
        $tickets = $this->getRedmineTickets();

        // Create tickets on Jira, and get Redmine ID/Jira ID combinations
        $tickets = $this->createTicketsJira($tickets);

        $this->updateRedmineTickets($tickets);

        $this->writeLog('***** END *****');
    }

    /**
    * Get all Redmine today tickets, except the ones
    * with Jira ID or project not on RedmineJiraProjects
    */
    private function getRedmineTickets()
    {
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

        // Current date
	$date = date('Y-m-d', strtotime("-20 minutes"));

        // Get Redmine tasks created this date
        $RedmineController = new RedmineController;
        $Redmine = $RedmineController->connect();
        $args = array(
            'created_on' => '>='.$date,
            'limit'      => 100,
            'sort'       => 'created_on:desc',
		'include' => 'attachments',
        );
        $redmine_entries = $Redmine->issue->all($args);

        $this->writeLog('Redmine new tasks');
        $this->writeLog(print_r($redmine_entries, true));

        foreach ($redmine_entries['issues'] as $_issue)
        {
            // Check if task has already been created on Jira (RedmineJiraTask)
            $task = RedmineJiraTask::where('redmine_task', $_issue['id'])->first();
            if ($task) {
                $this->writeLog('-- Task '.$_issue['id'].' already been created on Jira, CONTINUE');
                continue;
            }

            // Check if project exists on Redmine/Jira Projects
            $project = RedmineJiraProject::where('redmine_name', $_issue['project']['name'])->first();

            if (!$project) {
                $this->writeLog('-- Project doesnt match Jira, CONTINUE');
                continue;
            }

            // Project exists, check if it has a Jira ID set
            $jira_id = false;

            foreach ($_issue['custom_fields'] as $_field)
            {
                if ($_field['id'] == Config::get('redmine.jira_id') && !empty($_field['value']))
                $jira_id = $_field['value'];
            }

            // Jira ID exists, so ignore
            if ($jira_id) {
                $this->writeLog('-- Jira ID exists, CONTINUE');
                continue;
            }

            $this->writeLog("Ticket {$_issue['id']} will be created on Jira");

            // Jira ID doesn't exist, and project is OMG
            $_issue['JiraProject'] = $project->jira_name;
            $tickets[] = $_issue;
        }

        return $tickets;
    }

    private function createTicketsJira($tickets)
    {
        if (!$tickets) return false;

        $jira_redmine_tickets = array();

        $JiraController = new JiraController;

        foreach ($tickets as $_ticket) {
            // Get user
            $settings = Setting::where('redmine_user', $_ticket['author']['name'])->first();

            if (!$settings) {
                $this->errorEmail("No user {$_ticket['author']['name']} found.");
                continue;
            }

            $user = $settings->user;

            // Set user as logged-in user
            $request = new Request();
            $request->merge(['user' => $user]);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            Auth::setUser($user);

            // Connect into Jira
            $Jira = $JiraController->connect($request);

            // Get Tracker/Type
            $tracker = RedmineJiraTracker::where('redmine_name', $_ticket['tracker']['name'])->first();

            if (!$tracker) {
                $this->errorEmail("No tracker {$_ticket['tracker']['name']} found.");
                continue;
            }

            $trackers = explode(',', $tracker->jira_name);

            $jira_types = $Jira->getProjectIssueTypes($_ticket['JiraProject']);
            $jira_type  = null;

            foreach ($jira_types as $_type) {
                if (isset($_type['name']) && in_array($_type['name'], $trackers)) {
                    $jira_type = $_type['id'];
                }
            }

            if (!$jira_type) {
                $this->errorEmail("Jira type {$tracker->jira_name} not found (project '{$_ticket['JiraProject']}'): <pre>". print_r($tracker, true) . print_r($jira_types, true) . print_r($user, true) . '</pre>');
                continue;
            }

            // Get Priority
            $priority = RedmineJiraPriority::where('redmine_name', $_ticket['priority']['name'])->first();

            if (!$priority) {
                $this->errorEmail("No priority {$_ticket['priority']['name']} found.");
                continue;
            }

            $jira_priorities = $Jira->getPriorities();
            $jira_priority = null;

            foreach ($jira_priorities as $_priority) {
                if (stripos($priority->jira_name, $_priority['name']) !== false)
                $jira_priority = $_priority['id'];
            }

            // Get Assignee
            if (!isset($_ticket['assigned_to'])) {
                // If assignee is not set on Redmine, assign task to author
                $_ticket['assigned_to'] = array('name' => $_ticket['author']['name']);
            }

            $user = RedmineJiraUser::where('redmine_name', $_ticket['assigned_to']['name'])->first();

            if (!$user) {
                $this->errorEmail("No user {$_ticket['assigned_to']['name']} found.");
                continue;
            }

            // Create data array
            $issue = array(
                'description' => (isset($_ticket['description'])?$_ticket['description']:''),
                'priority'    => array('id' => $jira_priority),
                'assignee'    => array('name' => $user->jira_name),
            );

            if ($jira_type == '6') {
    		$issue['customfield_10301'] = $_ticket['subject']; // Epic Name
    	    }

            if (!$issue['description']) {
                $issue['description'] = 'TODO';
            }

            $project = RedmineJiraProject::where('redmine_name', $_ticket['project']['name'])->first();

            if ($project->content) {
                $issue['description'] .= "\n".$project->content;
            }

            if (isset($_ticket['due_date']) && !empty($_ticket['due_date'])) {
                $issue['duedate'] = $_ticket['due_date'];
            }

            //if (isset($_ticket['start_date']) && !empty($_ticket['start_date'])) {
            //    $issue['customfield_11700'] = $_ticket['start_date'];
            //}

            /*if (isset($_ticket['estimated_hours'])) {
                $issue['timetracking'] = array('originalEstimate' => ($_ticket['estimated_hours'] * 60));
            }*/

            // Send everything to Jira, to create ticket
            $this->writeLog(print_r($issue, true));
            $return = $Jira->createIssue($_ticket['JiraProject'], $_ticket['subject'], $jira_type, $issue);
            $result = $return->getResult();

            if (!isset($result['key'])) {
                $this->errorEmail('Jira result error: <br><pre>'. print_r($result, true).'<br>'.print_r($_ticket, true).'<br>'.print_r($issue, true).'</pre><br>'.$jira_type);
                continue;
            }

            // Save association on RedmineJiraTask
            $RedmineJiraTask = new RedmineJiraTask();
            $RedmineJiraTask->jira_task    = $result['key'];
            $RedmineJiraTask->redmine_task = $_ticket['id'];
            $RedmineJiraTask->source       = 'Redmine';
            $RedmineJiraTask->save();

            // Add Jira ID to ticket description
            $issue['description'] .= "\n* JIRA Ticket: https://flypilot.atlassian.net/browse/{$result['key']}";

            // Save Jira ID into Redmine's Task
            $jira_redmine_tickets[$result['key']] = [
                'id'          => $_ticket['id'],
                'description' => $issue['description'],
            ];
        }

        return $jira_redmine_tickets;
    }

    private function updateRedmineTickets($tickets)
    {
        if (!$tickets) die;

        // Get user
        $user = User::find(7);

        // Set user as logged-in user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        Auth::setUser($user);

        // Connect into Redmine
        $RedmineController = new RedmineController;
        $Redmine = $RedmineController->connect();

        foreach ($tickets as $_jira => $_redmine)
        {
            $data = array(
                'description' => htmlentities($_redmine['description'], ENT_XML1),
                'custom_fields' => array(
                    'custom_value' => array(
                        'id'    => Config::get('redmine.jira_id'),
                        'value' => $_jira
                    )
                )
            );

            $Redmine->issue->update($_redmine['id'], $data);
        }
    }

    private function errorEmail($errors, $level='error')
    {
        if (!$errors) die;

        if (!is_array($errors)) {
            $errors = array($errors);
        }

        $subject = 'Redmine/Jira (CreatedTasks) sync '.$level;

        Mail::send('emails.error', ['errors' => $errors], function ($m) use($subject) {
            $m->from('jiber@onerhino.com', 'Jiber');
            $m->to('thaissa@tmisoft.com', 'Thaissa Mendes')->subject($subject);
        });
    }

    private function writeLog($message) {
        if ($this->debug) {
        	file_put_contents('redmine-create.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
        }
    }
}
