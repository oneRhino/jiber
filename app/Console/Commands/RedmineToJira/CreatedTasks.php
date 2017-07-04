<?php

namespace App\Console\Commands\RedmineToJira;

use Log;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\RedmineJiraProject;
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
    protected $description = 'Syncs created tickets from Redmine and Jira.';

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
        // Grab tickets recently created/updated on Redmine
        $tickets = $this->getRedmineTickets();

        die(print_r($tickets));

        /*// Grab new tickets from Redmine
        $tickets = $this->getRedmineNewTickets();

        // Create tickets on Jira, and get Redmine ID/Jira ID combinations
        $tickets = $this->createTicketsJira($tickets);

        // Update tickets on Redmine, setting Jira ID
        $this->updateRedmineTickets($tickets);*/
    }

    private function getRedmineTickets()
    {
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

        // Get Redmine tasks updated this date
        $RedmineController = new RedmineController;
        $Redmine = $RedmineController->connect();
        $args = array(
            'created_on' => $date,
            'limit'      => 100,
            'sort'       => 'created_on:desc',
        );
        $redmine_entries = $Redmine->issue->all($args);
        die(print_r($redmine_entries));
    }

    /**
     * Get all Redmine today tickets, except the ones
     * with Jira ID or project not on RedmineJiraProjects
     */
    /*private function getRedmineNewTickets() {
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

        // Get Redmine tasks updated this date
        $RedmineController = new RedmineController;
        $Redmine = $RedmineController->connect();
        $args = array(
            'created_on' => $date,
            'limit'      => 100,
            'sort'       => 'created_on:desc',
        );
        $redmine_entries = $Redmine->issue->all($args);
        //die(print_r($redmine_entries));

        #Log::info('All Redmine entries found.');
        #Log::info(print_r($redmine_entries, true));

        $tickets = array();

        foreach ($redmine_entries['issues'] as $_issue) {
            $skip = false;

            // Check if issue has a Jira ID, if so, skip
            foreach ($_issue['custom_fields'] as $_field) {
                if ($_field['name'] == 'Jira ID' && !empty($_field['value']))
                    $skip = true;
            }

            if ($skip) {
                continue;
            }

            // Check for OMG projects
            if (!RedmineJiraProject::projectExists($_issue['project']['name']))
                continue;

            $tickets[] = $_issue;
        }

        return $tickets;
    }

    private function createTicketsJira($tickets)
    {
        if (!$tickets) return false;

        $JiraController = new JiraController;

        foreach ($tickets as $_ticket) {
            // Get user
            $settings = Setting::where('redmine_user', $_ticket['author']['name'])->first();

            if (!$settings) {
                $this->error("No user {$_user} found.");
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

            $Jira = $JiraController->connect($request);
        }
    }*/
}
