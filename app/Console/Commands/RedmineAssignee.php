<?php

namespace App\Console\Commands;

use Log;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\RedmineComment;
use App\Setting;
use App\User;
use App\Http\Controllers\RedmineController;
use App\Http\Controllers\JiraController;

class RedmineAssignee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redmineassignee:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs assignees from Redmine and Jira.';

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
        $assignees = $this->getRedmineAssignees();

        $this->setJiraAssignees($assignees);
    }

    /**
     * Use user "thaissa" to grab comments from all tasks
     * that have been added in the current date
     * and have not been sent to Jira yet
     */
    private function getRedmineAssignees() {
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
            'updated_on' => $date,
            'limit'      => 100,
            'sort'       => 'updated_on:desc',
        );
        $redmine_entries = $Redmine->issue->all($args);

        $assignees = array();

        foreach ($redmine_entries['issues'] as $_issue) {
            $jira = false;

            foreach ($_issue['custom_fields'] as $_field) {
                if ($_field['name'] == 'Jira ID' && !empty($_field['value']))
                    $jira = $_field['value'];
            }

            if (!$jira) {
                continue;
            }

            $args = array('include' => 'journals');

            $_entry = $Redmine->issue->show($_issue['id'], $args);

            foreach ($_entry['issue']['journals'] as $_journal) {
                if ($_journal['details']) {
                    foreach ($_journal['details'] as $_detail) {
                        // Only assigned in the last 5 min
                        $created = strtotime($_journal['created_on']);
                        $lastmin = mktime(date('H'), date('i')-5);

                        if ($created < $lastmin) continue;

                        if ($_detail['name'] == 'assigned_to_id') {
                            $assignees[] = array(
                                'assignee' => $_entry['issue']['assigned_to']['name'],
                                'jira_id'  => $jira,
                            );
                        }
                    }
                }
            }
        }

        return $assignees;
    }

    private function setJiraAssignees($assignees)
    {
        if (!$assignees) return false;

        $JiraController = new JiraController;

        foreach ($assignees as $_task) {
            // Use thaissa user
            $settings = Setting::where('redmine_user', 'thaissa')->first();
            $user = $settings->user;

            // Set user as logged-in user
            $request = new Request();
            $request->merge(['user' => $user]);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            Auth::setUser($user);

            $Jira = $JiraController->connect($request);

            // Get jira username
            $assignee = Setting::where('redmine_user', $_task['assignee'])->first();

            if (!$assignee) {

                switch ($_task['assignee']) {
                    case 'k.lyon':     $assignee = 'klyon';            break;
                    case 'm.lovascio': $assignee = 'mlovascio';        break;
                    default:           $assignee = $_task['assignee']; break;
                }
            } else {
                $assignee = $assignee->jira;
            }

            $args = array('fields' => array('assignee' => array('name' => $assignee)));

            $Jira->editIssue($_task['jira_id'], $args);
        }
    }
}
