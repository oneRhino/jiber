<?php

namespace App\Console\Commands\RedmineToJira;

use Log;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\User;
use App\Setting;
use App\RedmineChange;
use App\Http\Controllers\JiraController;
use App\Http\Controllers\RedmineController;

class UpdatedTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redmine-to-jira:sync-updated-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs updated tickets from Redmine and Jira.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private $jira_id      = null;
    private $jira_updates = array();

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Grab tickets recently created/updated on Redmine
        $this->getRedmineChanges();

        $this->updateJira();
    }

    private function getRedmineChanges()
    {
        // Get user - use thaissa (redmine user)
        $user = User::find(1);

        $this->loginUser($user);

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

        if (!$redmine_entries['issues']) return;

        foreach ($redmine_entries['issues'] as $_issue)
        {
            // Check if ticket has a Jira ID, otherwise ignore
            $this->jira_id = false;
            
            foreach ($_issue['custom_fields'] as $_field)
            {
                if ($_field['id'] == Config::get('redmine.jira_id') && !empty($_field['value']))
                    $this->jira_id = $_field['value'];
            }

            if (!$this->jira_id) continue;

            // Get ticket details
            $args = array('include' => 'journals');
            $_entry = $Redmine->issue->show($_issue['id'], $args);

            /**
             * Run through each journal:
             * - multiple 'details' (changes, as in assignee, description, jira id, etc)
             * - 'notes' (comment)
             * - 'user' (who made that change)
             * - 'id'
             * - 'created_on' (when the change has been made)
             */
            foreach ($_entry['issue']['journals'] as $_journal)
            {
                // Check if change has been done within the past 10 min
                $created = strtotime($_journal['created_on']);
                $lastmin = mktime(date('H'), date('i')-10);

                if ($created < $lastmin) continue;

                // Check if change has already been saved on database
                $redmine_change = RedmineChange::where('redmine_change_id', $_journal['id'])->first();

                if ($redmine_change) continue;

                // Run through details
                if ($_journal['details'])
                {
                    // Get change date/time and creator (get her/his Jira username)
                    $created_on = $_journal['created_on'];
                    $created_by = $this->getJiraUser($_journal['user']['name']);

                    foreach ($_journal['details'] as $_detail)
                    {
                        switch ($_detail['name'])
                        {
                            case 'subject':
                                $this->jiraSubject    ($created_on, $created_by, $_detail['new_value']);
                                break;
                            case 'description':
                                $this->jiraDescription($created_on, $created_by, $_detail['new_value']);
                                break;
                            case 'status_id':
                                $this->jiraStatus     ($created_on, $created_by, $_detail['new_value']);
                                break;
                            case 'assigned_to_id':
                                // Don't change assignee if ticket status is "Feedback"
                                if ($_entry['issue']['status']['id'] == 4) break;

                                $this->jiraAssignee   ($created_on, $created_by, $_detail['new_value']);
                                break;
                        }
                    }
                }

                if (isset($_journal['notes']) && !empty($_journal['notes']))
                {
                    $this->jiraComment($created_on, $created_by, $_journal['notes']);
                }

                $Change = new RedmineChange();
                $Change->redmine_change_id = $_journal['id'];
                $Change->save();
            }
        }
    }

    private function jiraSubject($created_on, $created_by, $content)
    {
        $this->jira_updates[$created_by][$this->jira_id]['subject'] = $content;
    }

    private function jiraDescription($created_on, $created_by, $content)
    {
        $this->jira_updates[$created_by][$this->jira_id]['description'] = $content;
    }

    private function jiraStatus($created_on, $created_by, $content)
    {
        // Get status based on Redmine status ID
        $status = Config::get('redmine.statuses.'.$content);

        // Map Redmine status to Jira
        switch ($status)
        {
            case 'New'     :
            case 'Assigned':
                $jira_status = Config::get('jira.transictions.todo');
                break;
            case 'Feedback':
            case 'Resolved':
            case 'Closed'  :
            case 'Rejected':
                $jira_status = Config::get('jira.transictions.review');
                break;
        }

        $this->jira_updates[$created_by][$this->jira_id]['status'] = $jira_status;
    }

    private function jiraAssignee($created_on, $created_by, $content)
    {
        // Get assignee based on Redmine user ID
        $redmine_assignee = Config::get('redmine.users.'.$content);

        $assignee = $this->getJiraUser($redmine_assignee);

        $this->jira_updates[$created_by][$this->jira_id]['assignee'] = $assignee;
    }

    private function jiraComment($created_on, $created_by, $content)
    {
        $created_on = preg_replace('/([-+][0-9]{2}):([0-9]{2})$/', '.000${1}${2}', date('c', strtotime($created_on)));

        $comment = array(
            'body'    => $content,
            'created' => $created_on,
        );

        $this->jira_updates[$created_by][$this->jira_id]['comment'][] = $content;
    }

    private function updateJira()
    {
        $JiraController = new JiraController;

        if (!$this->jira_updates) return;

        // Run through all updates, per user
        foreach ($this->jira_updates as $_user => $_user_updates)
        {
            // Get user based on jira user (to login on jira)
            $settings = Setting::where('jira', $_user)->first();

            if (!$settings) {
                $this->error("No user {$_user} found.");
                continue;
            }

            $user    = $settings->user;
            $request = $this->loginUser($user);
            $Jira    = $JiraController->connect($request);

            // Run through all user's updates, per Jira Task ID
            foreach ($_user_updates as $_jira_id => $_task_updates)
            {
                // Run through all Jira task updates, per type
                foreach ($_task_updates as $_type => $_content)
                {
                    switch ($_type)
                    {
                        case 'assignee':
                            $args = array('fields' => array('assignee' => array('name' => $_content)));
                            $Jira->editIssue($_jira_id, $args);
                            break;

                        case 'subject':
                            $args = array('fields' => array('summary' => $_content));
                            $Jira->editIssue($_jira_id, $args);
                            break;

                        case 'description':
                            $args = array('fields' => array('description' => $_content));
                            $Jira->editIssue($_jira_id, $args);
                            break;

                        case 'comment':
                            foreach ($_content as $_comment) {
                                $Jira->addComment($_jira_id, $_comment);
                            }
                            break;

                        case 'status':
                            $transiction = null;

                            // Get available transictions, so we can get its ID
                            $result   = $Jira->getTransitions($_jira_id, array());
                            $statuses = $result->getResult();

                            foreach ($statuses['transitions'] as $_status)
                            {
                                if ($_status['name'] == $_content)
                                    $transiction = $_status['id'];
                            }

                            if (!$transiction) break;

                            $args = array('transition' => $transiction);
                            $Jira->transition($_jira_id, $args);

                            break;
                    }
                }
            }
        }
    }

    private function getJiraUser($redmine_user)
    {
        // Get user details (if on Jiber)
        $user = Setting::where('redmine_user', $redmine_user)->first();

        if (!$user) {
            switch ($redmine_user) {
                case 'k.lyon':     return 'klyon';       break;
                case 'm.lovascio': return 'mlovascio';   break;
                default:           return $redmine_user; break;
            }
        }

        return $user->jira;
    }

    private function loginUser($user)
    {
        // Set user as logged-in user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        Auth::setUser($user);

        return $request;
    }
}
