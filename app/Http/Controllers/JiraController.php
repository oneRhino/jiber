<?php

/**
* Copyright 2016 Thaissa Mendes
*
* This file is part of Jiber.
*
* Jiber is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jiber is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jiber. If not, see <http://www.gnu.org/licenses/>.
*/

/**
* Connect into Jira, get and send information using its API
*
* @author Thaissa Mendes <thaissa.mendes@gmail.com>
* @since 8 August, 2016
* @version 0.1
*/

namespace App\Http\Controllers;

use App\{JiraSent, RedmineChange, RedmineJiraPriority, RedmineProject, RedmineStatus, RedmineJiraTask, RedmineTracker, RedmineJiraUser, Report, Setting, TimeEntry, User};
use App\Http\Controllers\RedmineController;
use chobie\Jira\Api;
use chobie\Jira\Api\Authentication\Basic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Config};
use Log;
use Mail;

class JiraController extends Controller
{
    /**
    * Connect into Jira API
    */
    public function connect(Request $request)
    {
        $redirect = app('Illuminate\Routing\Redirector');
        $url      = Config::get('jira.url');
        $settings = Setting::find(Auth::user()->id);
        $email    = $settings->jira_email;
        $token    = $settings->jira_password;

        if (!$token) return false;

        $api = new Api(
            $url,
            new Basic($email, $token)
        );

        return $api;
    }

    /**
    * Test Jira connection
    */
    public function test(Request $request)
    {
        $connection = $this->connect($request);

        if (!$connection) return false;

        try {
            $details = $connection->api('GET', '/rest/api/2/mypermissions');

            if ($details) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
    * Show Jira's time entries grouped by date and Redmine's task ID
    */
    public function show(Report $report, Request $request, $return = false)
    {
        if ($report->user_id != Auth::user()->id) {
            abort(403, 'Unauthorized action.');
        }

        set_time_limit(0);

        // Connect into Jira
        $jira = $this->connect($request);

        // Get Jira current username
        $setting       = Setting::find(Auth::user()->id);
        $jira_username = $setting->jira;

        // Entries array - that will contain all Toggl's and Jira's entries to display
        $entries = array();

        // Get all report's entries that have a Jira Task ID set
        $jira_entries = $report->entries()->whereNotNull('jira_issue_id')->get();

        if (!$jira_entries->count()) {
            if ($return) {
                return false;
            } else {
                $request->session()->flash('alert-warning', 'No Jira tasks have been found in the period.');

                return back()->withInput();
            }
        }

        // First create arrays and fill with entry information
        foreach ($jira_entries as $_entry) {
            // Create default arrays
            if (!isset($entries[$_entry->date])) {
                $entries[$_entry->date] = array(
                    'entry_total' => 0,
                    'third_total' => 0,
                );
            }

            if (!isset($entries[$_entry->date][$_entry->redmine_issue_id])) {
                $entries[$_entry->date][$_entry->redmine_issue_id] = array(
                    'entry_entries' => array(),
                    'entry_total'   => 0,
                    'third_total'   => 0,
                    'third_entries' => array(),
                );
            }

            // Fill arrays
            $entries[$_entry->date][$_entry->redmine_issue_id]['entry_entries'][] = $_entry;
            $entries[$_entry->date][$_entry->redmine_issue_id]['entry_total']    += $_entry->round_decimal_duration;
            $entries[$_entry->date]['entry_total']                               += $_entry->round_decimal_duration;
        }

        // Then, through all entries, get Jira's worklog
        foreach ($entries as $_date => $_entries) {
            foreach ($_entries as $_redmine => $__entries) {
                // Skip *_total keys
                if (in_array($_redmine, array('entry_total', 'third_total'))) {
                    continue;
                }

                // Get Jira worklog for this task
                //Log::debug('JIRA ID '.$__entries['entry_entries'][0]->jira_issue_id);
                $worklog = $jira->getWorklogs($__entries['entry_entries'][0]->jira_issue_id, array());
                $results = $worklog->getResult();
                //Log::debug(print_r($results, true));

                if (isset($results['worklogs'])) {
                    foreach ($results['worklogs'] as $_time) {
                        if ('Tempo Timesheets' === $_time['author']['displayName']) {
                            // Ignore this "user"
                            continue;
                        }

                        // Worklog author isn't current jira user? Continue!
                        // Unless report isn't filtering out other users
                        $author = RedmineJiraUser::where('jira_code', $_time['author']['accountId'])->first();

                        if (!$author) {
                            $this->errorEmail("User is not properly setup: <pre>".print_r($_time['author'], true).'</pre>', 'alert');
                            continue;
                        }

                        if ($report->filter_user && $author->jira_name != $jira_username) {
                            continue;
                        }

                        // Only add worklog for this specific date
                        if (strtotime($_date) != strtotime(date('Y-m-d', strtotime($_time['started'])))) {
                            continue;
                        }

                        $comment = '<No comment>';

                        if (isset($_time['comment']) && !empty($_time['comment'])) {
                            $comment = $_time['comment'];
                        }

                        $_time['description'] = $comment;
                        $_time['time']        = round(($_time['timeSpentSeconds'] / 3600), 2);
                        $_time['user']        = $_time['author']['displayName'];

                        $entries[$_date][$_redmine]['third_entries'][] = $_time;
                        $entries[$_date][$_redmine]['third_total']    += round(($_time['timeSpentSeconds'] / 3600), 2);
                        $entries[$_date]['third_total']               += round(($_time['timeSpentSeconds'] / 3600), 2);
                    }
                }
            }
        }

        // Sort entries based on first key (date), ascending
        ksort($entries);

        if ($return) {
            return $entries;
        } else {
            if ($report->filter_user) {
                return view('jira.show', [
                    'entries'   => $entries,
                    'report_id' => $report->id,
                ]);
            } else {
                return view('jira.show_all', [
                    'entries'   => $entries,
                    'report_id' => $report->id,
                ]);
            }
        }
    }

    /**
    * Export CSV comparing Redmine and Jira
    */
    public function csv(Report $report, Request $request)
    {
        if ($report->user_id != Auth::user()->id) {
            abort(403, 'Unauthorized action.');
        }

        set_time_limit(0);

        // Connect into Jira
        $jira = $this->connect($request);

        // Get Jira current username
        $setting       = Setting::find(Auth::user()->id);
        $jira_username = $setting->jira;

        // Entries array - that will contain all Toggl's and Jira's entries to display
        $entries = array();
        $jira_time_entries = array();
        $jira_ids = array();

        // Get all report's entries that have a Jira Task ID set
        $redmine_entries = $report->entries()->whereNotNull('jira_issue_id')->get();

        if (!$redmine_entries->count()) {
            $request->session()->flash('alert-warning', 'No Jira tasks have been found in the period.');

            return back()->withInput();
        }

        // First create arrays and fill with entry information
        foreach ($redmine_entries as $_entry) {

            // Fill arrays
            $jira_ids[] = $_entry->jira_issue_id;

            $entries[$_entry->date][$_entry->jira_issue_id][$_entry->user]['redmine'][] = array(
                'jira_id'     => $_entry->jira_issue_id,
                'user'        => $_entry->user,
                'description' => $_entry->description,
                'date'        => $_entry->date,
                'duration'    => $_entry->round_decimal_duration,
            );
        }

        $jira_ids = array_unique($jira_ids);
        sort($jira_ids);

        // Then, through all entries, get Jira's worklog
        foreach ($jira_ids as $_jira_id) {
            // Get Jira worklog for this task
            $worklog = $jira->getWorklogs($_jira_id, array());
            $results = $worklog->getResult();

            if (isset($results['worklogs'])) {
                foreach ($results['worklogs'] as $_time) {
                    $date = date('Y-m-d', strtotime($_time['started']));

                    if (!isset($entries[$date])) continue;

                    $entries[$date][$_jira_id][$_time['author']['displayName']]['jira'][] = array(
                        'user'        => $_time['author']['displayName'],
                        'description' => ($_time['comment'] ? $_time['comment'] : '<No comment>'),
                        'duration'    => round(($_time['timeSpentSeconds'] / 3600), 2),
                    );
                }
            }
        }

        // Sort entries based on first key (date), ascending
        ksort($entries);

        $csv = array();

        foreach ($entries as $_date => $_array1) {
            foreach ($_array1 as $_jira_id => $_array2) {
                foreach ($_array2 as $_user => $_array3) {
                    if (isset($_array3['redmine'])) {
                        foreach ($_array3['redmine'] as $_entry) {
                            $csv[] = "{$_date},{$_jira_id},{$_user},\"{$_entry['description']}\",Redmine,{$_entry['duration']}";
                        }
                    }
                    if (isset($_array3['jira'])) {
                        foreach ($_array3['jira'] as $_entry) {
                            $csv[] = "{$_date},{$_jira_id},{$_user},\"{$_entry['description']}\",Jira,{$_entry['duration']}";
                        }
                    }
                }
            }
        }

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=report-{$report->id}.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo implode("\n",$csv);
        die;
    }

    /**
    * Send time to Jira
    */
    public function send(Request $request)
    {
        if (!$request->task && !$request->delete) {
            $request->session()->flash('alert-success', 'No tasks sent - nothing to do.');

            return back();
        }

        // Connect into Jira
        $jira = $this->connect($request);

        if ($request->task) {
            foreach ($request->task as $_entry_id) {
                $_entry = TimeEntry::find($_entry_id);

                $_entry->jira_issue_id = trim($_entry->jira_issue_id);

                if (!$_entry || $_entry->user_id != Auth::user()->id) {
                    continue;
                }

                // Transforming date into Jira's format
                // removing ':' from timezone and adding '.000' after seconds
                $_date = preg_replace('/([-+][0-9]{2}):([0-9]{2})$/', '.000${1}${2}', date('c', strtotime($_entry->date_time)));

                $_data = array(
                    'timeSpentSeconds' => $_entry->duration_in_seconds,
                    'started'          => $_date,
                    'comment'          => htmlentities($_entry->description),
                    'issueId'          => $_entry->jira_issue_id,
                );

                $response = $jira->addWorklog($_entry->jira_issue_id, $_data);

                if ($response) {
                    // Create a JiraSent (log)
                    $sent            = new JiraSent();
                    $sent->report_id = $request->report_id;
                    $sent->task      = $_entry->jira_issue_id;
                    $sent->date      = $_date;
                    $sent->duration  = $_entry->decimal_duration;
                    $sent->user_id   = Auth::user()->id;
                    $sent->save();
                }
            }
        }

        if ($request->delete) {
            foreach ($request->delete as $_entry_id) {
                $_temp = explode('-', $_entry_id);
                $_worklog_id = array_shift($_temp);
                $_issue_id   = implode('-', $_temp);

                $jira->deleteWorklog($_issue_id, $_worklog_id);
            }
        }

        // Remove report from session, so when we show previous page again, it's updated
        if ($request->isMethod('post')) {
            $request->session()->flash('alert-success', 'All tasks have been sent successfully to Jira!');
        }

        return back()->withInput();
    }

    /**
    * Get all opened tickets from project, and create them on Redmine
    */
    public function legacy(Request $request)
    {
        // Connect into Jira
        $jira = $this->connect($request);

        // Connect into Redmine
        $redmineController = new RedmineController();
        $redmine = $redmineController->connect();

        $walker = new \chobie\Jira\Issues\Walker($jira);
        $walker->push("project = {$request->project}", "*navigable");

        foreach ($walker as $k => $issue) {
            $reporter = $issue->getReporter();
            $jira_key = $issue->getKey();

            if (!empty($reporter['accountId'])) {
                $redmine_jira_user = RedmineJiraUser::where('jira_code', $reporter['accountId'])->first();

                $user = Setting::where('jira', $redmine_jira_user->jira_name)->first();
            } else {
                if ($reporter['name'] == 'addon_zendesk_for_jira') {
                    $user = Setting::where('jira', 'klyon')->first();
                } else {
                    $errors = [
                        'Reporter malformed:',
                        'app/Http/Controllers/JiraController.php:406',
                        print_r($reporter, true)
                    ];
                    $this->errorEmail($errors);
                    die;
                }
            }

            if (!$user) {
                Log::debug('-- ERROR - User not found on Jiber ('.$reporter['name'].')');
                continue;
            }

            $user = User::find($user->id);
            Log::debug('- User Found');

            // Connect on Redmine using this user
            $_request = new Request();
            $_request->merge(['user' => $user]);
            $_request->setUserResolver(function () use ($user) {
                return $user;
            });
            Auth::setUser($user);
            Log::debug('- User Authenticated');

            $jira_project = $issue->getProject();
            $project =  RedmineProject::where('third_party_project_name', $jira_project['key'])->where('third_party', 'jira')->first();
            Log::debug('-- Redmine project found');

            // First, check if it hasn't already been created on Redmine
            // Current date
            $date = date('Y-m-d');

            $args = array(
                'limit' => 1,
                'sort'  => 'created_on:desc',
                'cf_9'  => $jira_key,
                'project_id' => $project->project_id,
            );
            $redmine_entries = $redmine->issue->all($args);

            if (isset($redmine_entries['issues']) && !empty($redmine_entries['issues']))
            {
                Log::debug('-- ERROR: Redmine ticket found using this Jira ID');
                continue;
            }

            // Check if Jira ID exists on RedmineJiraTask, if so, ignore
            $task = RedmineJiraTask::where('jira_task', $jira_key)->first();
            if ($task) {
                Log::debug('-- IGNORED - RedmineJiraTask found');
                continue;
            }

            // Get data
            $jira_tracker = $issue->getIssueType();
            $tracker = RedmineTracker::where('jira_name', 'like', '%' . $jira_tracker['name'] . '%')->first();
            Log::debug('-- Redmine tracker found');

            $jira_status = $issue->getStatus();
            $status = RedmineStatus::where('jira_name', 'like', '%' . $jira_status['name'] . '%')->first();
            Log::debug('-- Redmine status found');

            $jira_priority = $issue->getPriority();
            $priority = RedmineJiraPriority::where('jira_name', $jira_priority['name'])->first();
            Log::debug('-- Redmine priority found');

            $jira_assignee = $issue->getAssignee();
            $assignee = RedmineJiraUser::where('jira_code', $jira_assignee['accountId'])->first();
            Log::debug('-- Redmine assignee found');

            // Check data
            $errors = array();

            if (!$tracker)
            $errors[] = "Tracker not found: {$jira_tracker['name']}";
            if (!$status)
            $errors[] = "Status not found: {$jira_status['name']}";
            if (!$priority)
            $errors[] = "Priority not found: {$jira_priority['name']}";
            if (!$assignee)
            $errors[] = "Assignee not found: {$jira_assignee['email']}";

            if ($errors) {
                Log::debug('-- Errors found:');
                Log::debug(print_r($errors, true));
                $this->errorEmail($errors);
                continue;
            }

            // Create data array
            $data = array(
                'project_id'      =>  $project->project_id,
                'tracker_id'      =>  $tracker->redmine_id,
                'status_id'       =>   $status->redmine_id,
                'priority_id'     => $priority->redmine_id,
                'assigned_to_id'  => $assignee->redmine_id,
                'subject'         => htmlentities($issue->getSummary()),
                'description'     => $issue->getDescription(),//htmlentities($issue->getDescription()),
                'due_date'        => $issue->getDueDate(),
                'custom_fields'   => array(
                    'custom_value' => array(
                        'id'    => Config::get('redmine.jira_id'),
                        'value' => $jira_key,
                    )
                )
            );
            //Log::debug('-- Redmine data:');
            //Log::debug(print_r($data, true));

            // Send data to Redmine
            $redmine->issue->create($data);

            // Get Redmine recently created task
            $args = array(
                'limit' => 1,
                'sort'  => 'created_on:desc',
                'cf_9'  => $jira_key,
                'project_id' => $project->project_id,
            );
            $redmine_entries = $redmine->issue->all($args);

            // Get first
            $redmine_task = reset($redmine_entries['issues']);

            // Save association on RedmineJiraTask
            $RedmineJiraTask = new RedmineJiraTask();
            $RedmineJiraTask->jira_task    = $jira_key;
            $RedmineJiraTask->redmine_task = $redmine_task['id'];
            $RedmineJiraTask->source       = 'Jira';
            $RedmineJiraTask->save();
        }

        // show previous page again, it's finished
        $request->session()->flash('alert-success', 'All tasks have been created successfully to Redmine!');

        return back()->withInput();
    }

    public function webhook(Request $request)
    {
        // Check if content has been sent, and it's JSON
        $content = $request->getContent();
        $content = json_decode($content);
        if (!$content) die;

        Log::debug('JIRA WEBHOOK ACTIVATED');
        //Log::debug(print_r($content, true));

        // Check if project is supported by Jiber
        if (empty($_GET['project'])) {
            Log::debug('-- ERROR - Project not found (GET)');
            die;
        }

        $project = RedmineProject::where('third_party_project_name', $_GET['project'])->where('third_party', 'jira')->first();

        if (!$project) {
            Log::debug('-- ERROR - Project not found');
            die;
        }
        Log::debug('- Project Found');

        // Check if user who is performing the action exists on Jiber
        if (empty($_GET['user_id'])) {
            Log::error('-- ERROR - User not found:');
            Log::error(print_r($_GET, true));
            die;
        }

        // When ticket is created from 3rd party (like Zendesk), a different user is set on GET
        // So if user isn't found on Jiber, try getting it by ticket's reporter
        $user = Setting::where('jira', $_GET['user_id'])->first();

        if (empty($user)) {
            if (isset($content->issue) && isset($content->issue->fields->reporter)) {
                if (empty($content->issue->fields->reporter->key)) {
                    Log::debug(print_r($content->issue->fields->reporter, true));
                } else {
                    $user = Setting::where('jira', $content->issue->fields->reporter->key)->first();
                }
            } elseif (isset($content->comment)) {
                if (isset($content->comment->author->accountId)) {
                    $redmine_jira_user = RedmineJiraUser::where('jira_code', $content->comment->author->accountId)->first();

                    $user = Setting::where('jira', $redmine_jira_user->jira_name);
                } elseif (isset($content->comment->author->key)) {
                    $user = Setting::where('jira', $content->comment->author->key)->first();
                }
            }

            if (empty($user) && $_GET['user_id'] == 'addon_zendesk_for_jira') {
                $user = Setting::where('jira', 'klyon')->first();
            }

            if (empty($user)) {
                Log::debug('-- ERROR - User not found on Jiber ('.$_GET['user_id'].')');
                die;
            }
        }

        $user = User::find($user->id);
        Log::debug('- User Found');

        // Connect on Redmine using this user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        Auth::setUser($user);
        Log::debug('- User Authenticated');

        // Get event
        if (isset($content->issue_event_type_name)) {
            $event = $content->issue_event_type_name;
        } else {
            $event = $content->webhookEvent;
        }

        Log::debug("- Event: {$event}");

        switch ($event)
        {
            case 'issue_created':
                $this->issue('created', $content, $request);
                break;

            case 'issue_updated':
            case 'issue_assigned':
            case 'issue_generic':
                $this->issue('updated', $content, $request);
                break;

            case 'jira:issue_deleted':
                $this->issue('deleted', $content, $request);
                break;

            case 'comment_created':
                $this->comment('created', $content, $request);
                break;
        }
    }

    private function issue($action, $content, $request)
    {
        Log::debug('-- Issue method called: action '.$action);

        // Connect into Redmine
        $redmineController = new RedmineController();
        $redmine = $redmineController->connect();

        // Create task on Redmine
        if ($action == 'created')
        {
            // First, check if it hasn't already been created on Redmine
            // Current date
            $date = date('Y-m-d');

            $args = array(
                'created_on' => $date,
                'limit'      => 100,
                'sort'       => 'created_on:desc',
            );
            $redmine_entries = $redmine->issue->all($args);

            if (isset($redmine_entries['issues']))
            {
                foreach ($redmine_entries['issues'] as $_issue)
                {
                    foreach ($_issue['custom_fields'] as $_field)
                    {
                        if (!isset($_field['id']) || !isset($_field['value'])) continue;

                        //Log::debug(print_r($_field, true));

                        if ($_field['id'] == Config::get('redmine.jira_id') && $_field['value'] == $content->issue->key) {
                            Log::debug('-- ERROR: Redmine ticket found using this Jira ID');
                            die;
                        }
                    }
                }
            }

            // Check if Jira ID exists on RedmineJiraTask, if so, ignore
            $task = RedmineJiraTask::where('jira_task', $content->issue->key)->first();
            if ($task) {
                Log::debug('-- ERROR - RedmineJiraTask found');
                die;
            }

            // Get data
            $project  =  RedmineProject::where('third_party_project_name', $content->issue->fields->project->key)->where('third_party', 'jira')->first();
            Log::debug('-- Redmine project found');
            $tracker  =  RedmineTracker::where('jira_name', 'like', '%' . $content->issue->fields->issuetype->name . '%')->first();
            Log::debug('-- Redmine tracker found');
            $status   =   RedmineStatus::where('jira_name', 'like', '%' . $content->issue->fields->status->name . '%')->first();
            Log::debug('-- Redmine status found');
            $priority = RedmineJiraPriority::where('jira_name', $content->issue->fields->priority->name)->first();
            Log::debug('-- Redmine priority found');

            if (!empty($content->issue->fields->assignee->accountId)) {
                $assignee = RedmineJiraUser::where('jira_code', $content->issue->fields->assignee->accountId)->first();
            } elseif (!empty($content->issue->fields->assignee->key)) {
                $assignee = RedmineJiraUser::where('jira_name', $content->issue->fields->assignee->key)->first();
            }

            if (empty($assignee)) {
                Log::error(print_r($content->issue->fields->assignee, true));
                $errors = [
                    'Assignee malformed:',
                    'app/Http/Controllers/JiraController.php:705',
                    print_r($content->issue->fields->assignee, true)
                ];
                $this->errorEmail($errors);
                die;
            }
            Log::debug('-- Redmine assignee found');

            // Check data
            $errors = array();

            if (!$tracker) {
                $errors[] = "Tracker not found: {$content->issue->fields->issuetype->name}";
            }
            if (!$status) {
                $errors[] = "Status not found: {$content->issue->fields->status->name}";
            }
            if (!$priority) {
                $errors[] = "Priority not found: {$content->issue->fields->priority->name}";
            }
            if (!$assignee) {
                $errors[] = "Assignee not found: {$content->issue->fields->assignee->email}";
            }

            if ($errors) {
                $this->errorEmail($errors);
                die;
            }

            $subject      = $content->issue->fields->summary;
            $description  = $this->transformDescription($content->issue->fields->description);
            $description .= "\n\nh1. Resources\n";
            $description .= "\n* JIRA Ticket: https://flypilot.atlassian.net/browse/{$content->issue->key}";

            if ($project->content) {
                $description .= "\n" . $project->content;
            }

            // If there are attachments, add their links to the description
            if (!empty($content->issue->fields->attachment)) {
                $description .= "\n* Attachment(s):";

                foreach ($content->issue->fields->attachment as $_attachment) {
                    $description .= "\n** {$_attachment->content}";
                }
            }

            // Create data array
            $data = array(
                'project_id'       => $project->project_id,
                'tracker_id'       => $tracker->redmine_id,
                'status_id'        => $status->redmine_id,
                'priority_id'      => $priority->redmine_id,
                'assigned_to_id'   => $assignee->redmine_id,
                'subject'          => $subject,
                'description'      => $description,
                'due_date'         => $content->issue->fields->duedate,
                'custom_fields'     => array(
                    'custom_value' => array(
                        'id'       => Config::get('redmine.jira_id'),
                        'value'    => $content->issue->key,
                    )
                ),
                'watcher_user_ids' => [1, 105, 89], // Billy, Alejandro, Pablo
            );
            Log::debug('-- Redmine data:');
            Log::debug(print_r($data, true));

            // Send data to Redmine
            $redmine->issue->create($data);

            // Get Redmine recently created task
            $args = array(
                'limit' => 1,
                'sort'  => 'created_on:desc',
                'cf_9'  => $content->issue->key,
                'project_id' => $project->project_id,
            );
            $redmine_entries = $redmine->issue->all($args);

            if (!empty($redmine_entries['issues'])) {
                // Get first
                $redmine_task = reset($redmine_entries['issues']);

                // Save association on RedmineJiraTask
                $RedmineJiraTask = new RedmineJiraTask();
                $RedmineJiraTask->jira_task    = $content->issue->key;
                $RedmineJiraTask->redmine_task = $redmine_task['id'];
                $RedmineJiraTask->source       = 'Jira';
                $RedmineJiraTask->save();
            }
        }
        elseif ($action == 'updated')
        {
            // if changelog doesn't exist, ignore - it's a comment
            if (!isset($content->changelog)) die;

            // Get Redmine Project
            $project = RedmineProject::where('third_party_project_name', $content->issue->fields->project->key)->where('third_party', 'jira')->first();
            Log::debug('-- Redmine project found');

            // Search task on Redmine based on Jira ID
            $args = array(
                'limit' => 100,
                'sort'  => 'created_on:desc',
                'cf_9'  => $_GET['issue'],
                'project_id' => $project->project_id,
            );
            Log::debug(print_r($args, true));
            $redmine_entries = $redmine->issue->all($args);
            //Log::debug('-- Redmine tasks found:');
            //Log::debug(print_r($redmine_entries, true));

            // Check data
            if (empty($redmine_entries['issues'])) {
                $this->errorEmail("No task using Jira ID {$_GET['issue']} - so it was created", 'alert');
                return $this->issue('created', $content, $request);
            }

            // Get first Redmine task
            $redmine_task = reset($redmine_entries['issues']);

            // Get updated fields
            $data = array();

            Log::debug(print_r($content->changelog->items, true));

            foreach ($content->changelog->items as $_item) {
                switch ($_item->field)
                {
                    case 'summary':
                        $data['subject'] = $_item->toString;
                        break;
                    case 'description':
                        $data['description'] = $this->transformDescription($_item->toString);
                        break;
                    case 'duedate':
                        $data['due_date'] = substr($_item->toString, 0, strpos($_item->toString, ' '));
                        break;
                    case 'priority':
                        $priority = RedmineJiraPriority::where('jira_name', $_item->toString)->first();

                        if (!$priority) {
                            $this->errorEmail("Priority not found: {$content->issue->fields->priority->name}");
                            die;
                        }

                        $data['priority_id'] = $priority->redmine_id;

                        break;
                    case 'assignee':
                        $assignee = RedmineJiraUser::where('jira_name', $_item->to)->first();

                        if (!$assignee) {
                            $assignee = RedmineJiraUser::where('jira_code', $_item->to)->first();
                        }

                        if (!$assignee) {
                            Log::debug('Assignee not found: '.print_r($content->issue->fields->assignee, true));
                            $this->errorEmail("Assignee not found: ".print_r($content->issue->fields->assignee, true));
                            die;
                        }

                        $data['assigned_to_id'] = $assignee->redmine_id;

                        break;
                    case 'status':
                        $status = RedmineStatus::where('jira_name', 'like', '%' . $_item->toString . '%')->first();

                        if (!$status) {
                            $this->errorEmail("Status not found: {$content->issue->fields->status->name}");
                            die;
                        }

                        $data['status_id'] = $status->redmine_id;

                        break;
                    case 'issuetype':
                        $tracker = RedmineTracker::where('jira_name', 'like', '%' . $_item->toString . '%')->first();

                        if (!$tracker) {
                            $this->errorEmail("Tracker not found: {$content->issue->fields->issuetype->name}");
                            die;
                        }

                        $data['tracker_id'] = $tracker->redmine_id;

                        break;
                    case 'Attachment':
                        // Run through all attachments, and add the one who matches
                        foreach ($content->issue->fields->attachment as $_attachment) {
                            if ($_attachment->filename == $_item->toString) {
                                $data['notes'] = "Attachment added: {$_attachment->content}";
                            }
                        }

                        break;
                }
            }
            Log::debug('-- Redmine data:');
            Log::debug(print_r($data, true));

            // Send data to Redmine
            $redmine->issue->update($redmine_task['id'], $data);

            // Get data from Redmine
            $args = array('include' => 'journals');
            $task = $redmine->issue->show($redmine_task['id'], $args);
            Log::debug('-- Redmine Task '.$redmine_task['id'].':');
            Log::debug(print_r($task, true));

            foreach ($task['issue']['journals'] as $_journal)
            {
                // Check if change has been done within the past min
                $created = strtotime($_journal['created_on']);
                $lastmin = mktime(date('H'), date('i')-1);

                if ($created < $lastmin) continue;

                // Check if change has already been saved on database
                $redmine_change = RedmineChange::where('redmine_change_id', $_journal['id'])->first();

                if ($redmine_change) continue;

                // Run through details
                if ($_journal['details'])
                {
                    $saveid = false;

                    foreach ($_journal['details'] as $_detail)
                    {
                        // Check if it's one of the changes we just did
                        if (array_key_exists($_detail['name'], $data) && $_detail['new_value'] == $data[$_detail['name']])
                        $saveid = true;
                    }

                    if ($saveid) {
                        $Change = new RedmineChange();
                        $Change->redmine_change_id = $_journal['id'];
                        $Change->save();
                    }
                }
            }
        }
        elseif ($action == 'deleted')
        {
            // Get Redmine Project
            $project = RedmineProject::where('third_party_project_name', $content->issue->fields->project->key)->where('third_party', 'jira')->first();
            Log::debug('-- Redmine project found');

            // Search task on Redmine based on Jira ID
            $args = array(
                'limit' => 100,
                'sort'  => 'created_on:desc',
                'cf_9'  => $_GET['issue'],
                'project_id' => $project->project_id,
            );
            Log::debug(print_r($args, true));
            $redmine_entries = $redmine->issue->all($args);
            Log::debug('-- Redmine tasks found:');
            Log::debug(print_r($redmine_entries, true));

            // For each redmine task, remove Jira ID
            foreach ($redmine_entries['issues'] as $_task)
            {
                $data = array(
                    'custom_fields' => array(
                        'custom_value' => array(
                            'id'    => Config::get('redmine.jira_id'),
                            'value' => ''
                        )
                    )
                );

                $redmine->issue->update($_task['id'], $data);
            }

            /*// Get first Redmine task
            $redmine_task = reset($redmine_entries['issues']);

            // Remove task
            $redmine->issue->remove($redmine_task['id']);
            Log::debug('-- Redmine task removed: '.$redmine_task['id']);*/
        }
    }

    private function comment($action, $content, $request)
    {
        Log::debug('-- Comment method called: action '.$action);

        // Connect into Redmine
        $redmineController = new RedmineController();
        $redmine = $redmineController->connect();

        // Create task on Redmine
        if ($action == 'created')
        {
            // Get Redmine Project
            $project = RedmineProject::where('third_party_project_name', $content->issue->fields->project->key)->where('third_party', 'jira')->first();
            Log::debug('-- Redmine project found');

            // Search task on Redmine based on Jira ID
            $args = array(
                'limit' => 100,
                'sort'  => 'created_on:desc',
                'cf_9'  => $_GET['issue'],
                'project_id' => $project->project_id,
            );
            Log::debug(print_r($args, true));
            $redmine_entries = $redmine->issue->all($args);
            Log::debug('-- Redmine tasks found:');
            Log::debug(print_r($redmine_entries, true));

            // Check data
            if (!isset($redmine_entries['issues']) || !$redmine_entries['issues']) {
                $this->errorEmail("No task using Jira ID {$_GET['issue']}", 'alert');
                die;
            }

            // Get first Redmine task
            $redmine_task = reset($redmine_entries['issues']);

            // Check if comment is already on Redmine
            $send_comment = true;
            $args = array('include' => 'journals');
            $task = $redmine->issue->show($redmine_task['id'], $args);
            Log::debug('-- Redmine Task '.$redmine_task['id'].':');
            Log::debug(print_r($task, true));

            foreach ($task['issue']['journals'] as $_journal)
            {
                // Run through details
                if (isset($_journal['notes']) && !empty($_journal['notes']))
                {
                    if (isset($content->comment) && $content->comment->body == $_journal['notes']) {
                        $send_comment = false;
                    }
                }
            }

            if ($send_comment && isset($content->comment)) {
                // Build data array
                $treated_content = $this->transformDescription($content->comment->body);

                $data = array(
                    'notes' => $treated_content,
                );
                Log::debug('-- Redmine data:');
                Log::debug(print_r($data, true));

                // Send data to Redmine
                Log::debug('-- Sent data to Redmine:');
                Log::debug($redmine->issue->update($redmine_task['id'], $data));

                // Get data from Redmine
                $args = array('include' => 'journals');
                $task = $redmine->issue->show($redmine_task['id'], $args);
                Log::debug('-- Redmine Task '.$redmine_task['id'].':');
                Log::debug(print_r($task, true));

                foreach ($task['issue']['journals'] as $_journal)
                {
                    // Check if change has been done within the past min
                    $created = strtotime($_journal['created_on']);
                    $lastmin = mktime(date('H'), date('i')-1);

                    if ($created < $lastmin) continue;

                    // Check if change has already been saved on database
                    $redmine_change = RedmineChange::where('redmine_change_id', $_journal['id'])->first();

                    if ($redmine_change) continue;

                    // Run through details
                    if (isset($_journal['notes']) && !empty($_journal['notes']))
                    {
                        if ($data['notes'] == $treated_content) {
                            $Change = new RedmineChange();
                            $Change->redmine_change_id = $_journal['id'];
                            $Change->save();
                            Log::debug('-- Comment saved: '.$_journal['id']);
                        }
                    }
                }
            }
        }
    }

    private function errorEmail($errors, $level='error')
    {
        if (!$errors) die;

        if (!is_array($errors))
        $errors = array($errors);

        $subject = 'Redmine/Jira (Jira Webhook) sync '.$level;

        Mail::send('emails.error', ['errors' => $errors], function ($m) use($subject) {
            $m->from('jiber@onerhino.com', 'Jiber');
            $m->cc(['a.bastos@onerhino.com', 'pablo@onerhino.com', 'billy@onerhino.com']);
            $m->to('thaissa@onerhino.com', 'Thaissa Mendes')->subject($subject);
        });
    }

    private function transformDescription($description) {
        // Citation transformation
        $description = $this->replaceCitation($description);

    	// HELPDESK Transformations
    	if (strpos($description, '{quote}') !== false) {
    		// Remove all {color*} tags
    		$pattern = '|{color(:#?[a-z0-9]*)?}|i';
    		$description = preg_replace($pattern, '', $description);

    		// Add a ">" on all lines between {quote} and {quote}
    		$lines = preg_split('/\R/', $description);
    		$inside_quote = false;

    		foreach ($lines as &$line) {
    			// First of all, remove pipes and images
    			$patterns = [
    				'|\s\||', // pipes with space or breakline before it
    				'|\|\s|', // pipes with space or breakline after it
    				'|!([a-z0-9:\/\.\-?=&%;])*!|i', // images
    			];
    			$line = preg_replace($patterns, '', $line);

    			// Starting quote
    			if (!$inside_quote && strpos($line, '{quote}') !== false) {
    				$inside_quote = true;
    			}
    			// Ending quote
    			elseif ($inside_quote && strpos($line, '{quote}') !== false) {
    				$inside_quote = false;
    			}
    			// Inside quote
    			elseif ($inside_quote) {
    				$line = '> ' . $line;
    			}
    		}
    		$description = implode("\r\n", $lines);
    		// Remove {quote}
    		$description = str_replace('{quote}', '', $description);
    	}

        // Remove \r from line breaks
        $description = str_replace('\r\n', "\n", $description);

        // Remove escaping double quotes
    	$description = str_replace('\"', '"', $description);

    	// Remove escaping minus signs
    	$description = str_replace('\-', '-', $description);

    	// Fix Unicode characters
    	$description = html_entity_decode($description);

            // If description starts with a double quote, remove it
            if (strpos($description, '"') === 0) {
                $description = substr($description, 1, -1);
    	}

    	$url = 'http([a-z0-9:_\/\.\-?=&%;])*';

        // Adjust links
        $pattern = [
            '|\[(http(.)*)]|i',
            '/\[([^|]*)\|(http(.)*)]/i',
            '/(http[^|]*)\|(http[\S]*)/i',
        ];
        $replacement = [
            '$1',
            '$1 ($2)',
            '$1',
        ];
        $description = preg_replace($pattern, $replacement, $description);

        // Transform Special Chars
        //$description  = htmlspecialchars($description, ENT_XML1, 'UTF-8');

        return $description;
    }

    private function replaceCitation($description) {
        // Check if there's an account being mentioned in the description
        $pattern = '|\[~accountid:([a-z0-9:-]*)]|i';

        preg_match_all($pattern, $description, $matches);

        // Check if it found any matches
        if (empty($matches[1])) {
            return $description;
        }

        // Run through all accounts found, and replace them with user's name, if any
        foreach ($matches[1] as $_jira_code) {
            $user = RedmineJiraUser::where('jira_code', $_jira_code)->first();

            if ($user) {
                $pattern = '|\[~accountid:'.$_jira_code.']|i';
                $description = preg_replace($pattern, "_{$user->redmine_name}_", $description);
            }
        }

        return $description;
    }
}
