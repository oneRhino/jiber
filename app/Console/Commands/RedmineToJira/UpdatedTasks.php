<?php

namespace App\Console\Commands\RedmineToJira;

//use Log;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\User;
use App\Setting;
use App\RedmineChange;
use App\RedmineJiraPriority;
use App\RedmineJiraStatus;
use App\RedmineJiraUser;
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

    private $jira_id      = null;
    private $jira_updates = array();

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->writeLog('***** INIT *****');

        // Grab tickets recently created/updated on Redmine
        $this->getRedmineChanges();

        $this->updateJira();

        $this->writeLog('***** END *****');
    }

    private function getRedmineChanges()
    {
        // Get user - use thaissa (redmine user)
        //$this->writeLog('Login Start');
        $user = User::find(7);

        $this->loginUser($user);
        //$this->writeLog('Login End');

        // Current date
	$date = date('Y-m-d', strtotime("-20 minutes"));;

        // Get Redmine tasks updated this date
        //$this->writeLog('Get Redmine tasks');
        $RedmineController = new RedmineController;
        $Redmine = $RedmineController->connect();
        $args = array(
            'updated_on' => '>='.$date,
            'limit'      => 100,
            'sort'       => 'updated_on:desc',
        );
        //$this->writeLog(print_r($args, true));
        $redmine_entries = $Redmine->issue->all($args);
        //$this->writeLog(print_r($redmine_entries, true));

        if (!$redmine_entries['issues']) {
            $this->writeLog('No tasks found, END');
            return;
        }

        foreach ($redmine_entries['issues'] as $_issue)
        {
            // Check if ticket has a Jira ID, otherwise ignore
            $this->writeLog("Check task {$_issue['id']}");
            $this->jira_id = false;

            foreach ($_issue['custom_fields'] as $_field)
            {
                if ($_field['id'] == Config::get('redmine.jira_id') && !empty($_field['value']))
                    $this->jira_id = $_field['value'];
            }

            if (!$this->jira_id) {
                //$this->writeLog('No Jira ID found, CONTINUE');
                continue;
            }

            //$this->writeLog("Jira ID {$this->jira_id}");

            // Get ticket details
            $args = array('include' => 'journals');
            $_entry = $Redmine->issue->show($_issue['id'], $args);
            $this->writeLog('Ticket details:');
            $this->writeLog(print_r($_entry['issue']['journals'], true));

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
                //$this->writeLog('Check if change '.$_journal['id'].' has been done within the past 10 min');
                $created = strtotime($_journal['created_on']);
                $lastmin = mktime(date('H'), date('i')-10);

                if ($created < $lastmin) {
                    //$this->writeLog("Old entry ({$_journal['created_on']}), CONTINUE");
                    continue;
                }

                // Check if change has already been saved on database
                //$this->writeLog('Check if change has already been saved on database');
                $redmine_change = RedmineChange::where('redmine_change_id', $_journal['id'])->first();

                if ($redmine_change) {
                    $this->writeLog("Change {$_journal['id']} already saved, CONTINUE");
                    continue;
                }

                // Get change date/time and creator (get her/his Jira username)
                $created_on = $_journal['created_on'];
                $created_by = $this->getJiraUser($_journal['user']['name']);

                // Run through details
                if ($_journal['details'])
                {
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
                                $this->jiraStatus($created_on, $created_by, $_detail['new_value']);
                                break;
                            case 'priority_id':
                                $this->jiraPriority($created_on, $created_by, $_detail['new_value']);
                                break;
                            case 'start_date':
                                $this->jiraStartDate($created_on, $created_by, $_detail['new_value']);
                                break;
                            case 'due_date':
                                $this->jiraDueDate($created_on, $created_by, $_detail['new_value']);
                                break;
                            case 'estimated_hours':
                                if (isset($_detail['new_value']))
	                                $this->jiraEstimated($created_on, $created_by, $_detail['new_value']);
                                break;
                            case 'assigned_to_id':
                                // Don't change assignee if ticket status is "Feedback"
                                //if ($_entry['issue']['status']['id'] == 4) break;

                                if (empty($_detail['new_value'])) {
                                    $this->writeLog("Missing new_value parameter from details:");
                                    $this->writeLog(print_r($_detail, true));
                                    breeak;
                                }

                                $this->jiraAssignee($created_on, $created_by, $_detail['new_value']);
                                break;
                        }
                    }
                }

                if (isset($_journal['notes']) && !empty($_journal['notes']))
                {
                    $this->jiraComment($created_on, $created_by, $_journal['notes']);
                }

                $this->writeLog("Changes to be sent to Jira");
                $this->writeLog(print_r($this->jira_updates, true));

                $Change = new RedmineChange();
                $Change->redmine_change_id = $_journal['id'];
                $Change->save();
            }
        }
    }

    private function jiraEstimated($created_on, $created_by, $content)
    {
        $this->jira_updates[$created_by][$this->jira_id]['estimated'] = $content;
    }

    private function jiraStartDate($created_on, $created_by, $content)
    {
        $this->jira_updates[$created_by][$this->jira_id]['startdate'] = $content;
    }

    private function jiraDueDate($created_on, $created_by, $content)
    {
        $this->jira_updates[$created_by][$this->jira_id]['duedate'] = $content;
    }

    private function jiraSubject($created_on, $created_by, $content)
    {
        $this->jira_updates[$created_by][$this->jira_id]['subject'] = $content;
    }

    private function jiraDescription($created_on, $created_by, $content)
    {
        $this->jira_updates[$created_by][$this->jira_id]['description'] = $content;
    }

    private function jiraPriority($created_on, $created_by, $content)
    {
        $priority = RedmineJiraPriority::where('redmine_id', $content)->first();

        $this->jira_updates[$created_by][$this->jira_id]['priority'] = $priority->jira_name;
    }

    private function jiraStatus($created_on, $created_by, $content)
    {
        $status = RedmineJiraStatus::where('redmine_id', $content)->first();

	$status = explode(',', $status->jira_name);
	$status = reset($status);

        $this->jira_updates[$created_by][$this->jira_id]['status'] = $status;
    }

    private function jiraAssignee($created_on, $created_by, $content)
    {
        // Get assignee based on Redmine user ID
        $user = RedmineJiraUser::where('redmine_id', $content)->first();

        if ($user) {
            $this->jira_updates[$created_by][$this->jira_id]['assignee'] = $user->jira_code;
        }
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
        $this->writeLog('Run through each Jira update');
        foreach ($this->jira_updates as $_user => $_user_updates)
        {
            // Get user based on jira user (to login on jira)
            $settings = Setting::where('jira', $_user)->first();

            if (!$settings) {
                $this->writeLog("ERROR: No user {$_user} found");
                $this->error("No user {$_user} found.");
                continue;
            }

            $user    = $settings->user;
            $request = $this->loginUser($user);
            $Jira    = $JiraController->connect($request);

            if (!$Jira) {
                $this->writeLog("ERROR: User {$_user} do not have jira token set");
                continue;
            }

            // Run through all user's updates, per Jira Task ID
            foreach ($_user_updates as $_jira_id => $_task_updates)
            {
                // Run through all Jira task updates, per type
                foreach ($_task_updates as $_type => $_content)
                {
                    switch ($_type)
                    {
                        case 'assignee':
                            //$this->writeLog('JIRA: Assignee');
                            $args = array('fields' => array('assignee' => array('accountId' => $_content)));
                            $result = $Jira->editIssue($_jira_id, $args);
                            //$this->writeLog(print_r($result, true));
                            break;

                        case 'subject':
                            //$this->writeLog('JIRA: Subject');
                            $args = array('fields' => array('summary' => $_content));
                            $result = $Jira->editIssue($_jira_id, $args);
                            //$this->writeLog(print_r($result, true));
                            break;

                        case 'priority':
                            //$this->writeLog('JIRA: Priority');
                            $args = array('fields' => array('priority' => array('name' => $_content)));
                            $result = $Jira->editIssue($_jira_id, $args);
                            //$this->writeLog(print_r($result, true));
                            break;

                        case 'description':
                            //$this->writeLog('JIRA: Description');
                            $args = array('fields' => array('description' => $_content));
                            $result = $Jira->editIssue($_jira_id, $args);
                            //$this->writeLog(print_r($result, true));
                            break;

                        case 'estimated':
                            // $this->writeLog('JIRA: Estimated Time');
                            // $args = array('fields' => array('timetracking' => array('originalEstimate' => $_content)));
                            //$args = array('fields' => array('customfield_11702' => $_content));
                            // $this->writeLog(print_r($args, true));
                            // $result = $Jira->editIssue($_jira_id, $args);
                            // $this->writeLog(print_r($result, true));
                            break;

                        case 'duedate':
                            //$this->writeLog('JIRA: Due date');
                            $args = array('fields' => array('duedate' => $_content));
                            $result = $Jira->editIssue($_jira_id, $args);
                            //$this->writeLog(print_r($result, true));
                            break;

                        case 'startdate':
                            $this->writeLog('JIRA: Start Date (customfield_11700)');
                            $args = array('fields' => array('customfield_11700' => $_content));
                            $result = $Jira->editIssue($_jira_id, $args);
                            break;

                        case 'comment':
                            //$this->writeLog('JIRA: Comments');
                            foreach ($_content as $_comment) {
                                $result = $Jira->addComment($_jira_id, $_comment);
                                //$this->writeLog(print_r($result, true));
                            }
                            break;

                        case 'status':
                            $this->writeLog('JIRA: Status');
                            $transiction = null;

                            // Get available transictions, so we can get its ID
                            $result     = $Jira->getTransitions($_jira_id, array());
                            $statuses   = $result->getResult();
                            $this->writeLog(print_r($statuses, true));
                            $transition = null;

                            if (isset($statuses['transitions']))
                            {
                                foreach ($statuses['transitions'] as $_status)
                                {
                                    if (strtolower($_status['to']['name']) == strtolower($_content))
                                        $transition = $_status['id'];
                                }
                            }

                            if (!$transition) {
                                $this->writeLog("ERROR: Status not found ({$_content})");
                                break;
                            }

                            $args = array('transition' => $transition);
                            $this->writeLog(print_r($args, true));
                            $result = $Jira->transition($_jira_id, $args);
                            $this->writeLog(print_r($result, true));

                            break;
                    }
                }
            }
        }
    }

    private function getJiraUser($redmine_user)
    {
        $user = RedmineJiraUser::where('redmine_name', $redmine_user)->first();

        if (!$user || !$user->jira_name) {
            return $redmine_user;
        }

        return $user->jira_name;
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

    private function writeLog($message)
    {
	if ($this->debug)
	        file_put_contents('redmine-update.log', date('Y-m-d H:i:s').' - '.$message."\n", FILE_APPEND);
    }
}
