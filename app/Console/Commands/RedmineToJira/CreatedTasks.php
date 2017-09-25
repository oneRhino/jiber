<?php

namespace App\Console\Commands\RedmineToJira;

use Log;
use Mail;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\User;
use App\Setting;
use App\RedmineJiraPriority;
use App\RedmineJiraProject;
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
        // Grab tickets recently created on Redmine
        $tickets = $this->getRedmineTickets();

        // Create tickets on Jira, and get Redmine ID/Jira ID combinations
        $tickets = $this->createTicketsJira($tickets);

        $this->updateRedmineTickets($tickets);
    }

    /**
     * Get all Redmine today tickets, except the ones
     * with Jira ID or project not on RedmineJiraProjects
     */
    private function getRedmineTickets()
    {
        $tickets = array();

        // Get user
        $user = User::find(1);

        // Set user as logged-in user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        Auth::setUser($user);

        // Current date
        $date = date('Y-m-d');

        // Get Redmine tasks created this date
        $RedmineController = new RedmineController;
        $Redmine = $RedmineController->connect();
        $args = array(
            'created_on' => $date,
            'limit'      => 100,
            'sort'       => 'created_on:desc',
        );
        $redmine_entries = $Redmine->issue->all($args);

        foreach ($redmine_entries['issues'] as $_issue)
        {
            // Check if project exists on Redmine/Jira Projects
            $project = RedmineJiraProject::where('redmine_name', $_issue['project']['name'])->first();

            if (!$project) continue;

            // Project exists, check if it has a Jira ID set
            $jira_id = false;

            foreach ($_issue['custom_fields'] as $_field)
            {
                if ($_field['id'] == Config::get('redmine.jira_id') && !empty($_field['value']))
                    $jira_id = $_field['value'];
            }

            // Jira ID exists, so ignore
            if ($jira_id) continue;

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
                    $this->errorEmail("No user {$_user} found.");
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

                $jira_types = $Jira->getProjectIssueTypes($_ticket['JiraProject']);
                $jira_type  = null;

                foreach ($jira_types as $_type) {
                    if (stripos($tracker->jira_name, $_type['name']) !== false)
                        $jira_type = $_type['id'];
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
                $user = RedmineJiraUser::where('redmine_name', $_ticket['assigned_to']['name'])->first();

                if (!$user) {
                    $this->errorEmail("No user {$_ticket['assigned_to']['name']} found.");
                    continue;
                }

            // Create data array
                $issue = array(
                    'description' => $_ticket['description'],
                    'priority'    => array('id' => $jira_priority),
                    'assignee'    => array('name' => $user->jira_name),
                );

            // Send everything to Jira, to create ticket
                $return = $Jira->createIssue($_ticket['JiraProject'], $_ticket['subject'], $jira_type, $issue);
                $result = $return->getResult();

            // Save Jira ID into Redmine's Task
            $jira_redmine_tickets[$result['key']] = $_ticket['id'];
        }

        return $jira_redmine_tickets;
    }

    private function updateRedmineTickets($tickets)
    {
        if (!$tickets) die;

        // Get user
        $user = User::find(1);

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
                'custom_fields' => array(
                    'custom_value' => array(
                        'id'    => Config::get('redmine.jira_id'),
                        'value' => $_jira
                    )
                )
            );

            $Redmine->issue->update($_redmine, $data);
        }
    }

    private function errorEmail($errors)
    {
        if (!$errors) die;

        if (!is_array($errors))
            $errors = array($errors);

        Mail::send('emails.error', ['errors' => $errors], function ($m) {
            $m->from('jiber@tmisoft.com', 'Jiber');
            $m->to('thaissa.mendes@gmail.com', 'Thaissa Mendes')->subject('Redmine/Jira (CreatedTasks) sync error found');
        });
    }
}
