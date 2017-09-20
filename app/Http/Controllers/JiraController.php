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

use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\RedmineController;
use App\JiraSent;
use App\RedmineJiraPriority;
use App\RedmineJiraProject;
use App\RedmineJiraStatus;
use App\RedmineJiraTracker;
use App\RedmineJiraUser;
use App\Report;
use App\Setting;
use App\TimeEntry;
use App\User;
use chobie\Jira\Api;
use chobie\Jira\Api\Authentication\Basic;
use Crypt;

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
        $username = $settings->jira;
        if ($settings->jira_password) {
            try {
                $password = Crypt::decrypt($settings->jira_password);
            } catch (DecryptException $e) {
                die(print_r($e));
            }
        }
        else
            $password = $request->session()->get('jira.password');

        if (!$password) {
            $request->session()->flash('alert-warning', 'Please set your Jira Password (it will be stored only for this session).');
            $request->session()->put('back', $request->path());
            $redirect->to('/jira/set-password')->send();
        }

        $api = new Api(
            $url,
            new Basic($username, $password)
        );

        return $api;
    }

    /**
     * Show set Jira password form and save it into session
     */
    public function set_password(Request $request)
    {
        $redirect = app('Illuminate\Routing\Redirector');

        if (!Setting::find(Auth::user()->id)->jira) {
            $request->session()->flash('alert-warning', 'Please set your Jira Username.');
            $request->session()->put('back', $request->path());
            $redirect->to('/settings')->send();
        }

        if ($request->isMethod('post')) {
            $request->session()->put('jira.password', $request->jira_password);

            // Test Connection
            if ($this->test($request)) {
                $request->session()->flash('alert-success', 'Your Jira password has been successfully set.');
                if ($request->session()->has('back')) {
                    return redirect($request->session()->get('back'));
                } else {
                    return redirect('/settings');
                }
            } else {
                $request->session()->flash('alert-warning', 'Connection failed. Please check your Jira password.');
                return view('jira.set_password');
            }
        } else {
            return view('jira.set_password');
        }
    }

    /**
     * Test Jira connection
     */
    public function test(Request $request)
    {
        $connection = $this->connect($request);

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
    public function show(Report $report, Request $request)
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
            $request->session()->flash('alert-warning', 'No Jira tasks have been found in the period.');

            return back()->withInput();
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
                $worklog = $jira->getWorklogs($__entries['entry_entries'][0]->jira_issue_id, array());
                $results = $worklog->getResult();

                if (isset($results['worklogs'])) {
                    foreach ($results['worklogs'] as $_time) {
                        // Worklog author isn't current jira user? Continue!
                        // Unless report isn't filtering out other users
                        if ($report->filter_user && $_time['author']['name'] != $jira_username) {
                            continue;
                        }

                        // Only add worklog for this specific date
                        if (strtotime($_date) != strtotime(date('Y-m-d', strtotime($_time['started'])))) {
                            continue;
                        }

                        $_time['description'] = ($_time['comment'] ? $_time['comment'] : '<No comment>');
                        $_time['time']        = round(($_time['timeSpentSeconds'] / 3600), 2);
                        $_time['user']        = $_time['author']['name'];

                        $entries[$_date][$_redmine]['third_entries'][] = $_time;
                        $entries[$_date][$_redmine]['third_total']    += round(($_time['timeSpentSeconds'] / 3600), 2);
                        $entries[$_date]['third_total']               += round(($_time['timeSpentSeconds'] / 3600), 2);
                    }
                }
            }
        }

        // Sort entries based on first key (date), ascending
        ksort($entries);

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

                    $entries[$date][$_jira_id][$_time['author']['name']]['jira'][] = array(
                        'user'        => $_time['author']['name'],
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
        if (!$request->task) {
            $request->session()->flash('alert-success', 'No tasks sent - nothing to do.');

            return back();
        }

        // Connect into Jira
        $jira = $this->connect($request);

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

        // Remove report from session, so when we show previous page again, it's updated
        if ($request->isMethod('post')) {
            $request->session()->flash('alert-success', 'All tasks have been sent successfully to Jira!');
        }

        return back()->withInput();
    }

    public function webhook(Request $request)
    {
        // Check if content has been sent, and it's JSON
            $content = $request->getContent();
            $content = json_decode($content);
            if (!$content) return false;

        Log::debug('JIRA WEBHOOK ACTIVATED');
        Log::debug(print_r($content, true));

        // Check if project is supported by Jiber
            if (!isset($_GET['project'])) return false;
            $project = RedmineJiraProject::where('jira_name', $_GET['project'])->first();
            if (!$project) return false;
            Log::debug('- Project Found');

        // Check if user who is performing the action exists on Jiber
            if (!isset($_GET['user_id'])) return false;
            $user = Setting::where('jira', $_GET['user_id'])->first();
            if (!$user) return false;
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
            if (isset($content->issue_event_type_name))
                $event = $content->issue_event_type_name;
            else
                $event = $content->webhookEvent;

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
            case 'issue_commented':
                $this->comment('created', $content, $request);
                break;
            /*case 'issue_comment_updated':
                $this->comment('updated', $content, $request);
                break;
            case 'issue_comment_deleted':
                $this->comment('deleted', $content, $request);
                break;*/
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
            // Get data
                $project  =  RedmineJiraProject::where('jira_name', $content->issue->fields->project->key)->first();
                Log::debug('-- Redmine project found');
                $tracker  =  RedmineJiraTracker::where('jira_name', 'like', '%' . $content->issue->fields->issuetype->name . '%')->first();
                Log::debug('-- Redmine tracker found');
                $status   =   RedmineJiraStatus::where('jira_name', $content->issue->fields->status->name)->first();
                Log::debug('-- Redmine status found');
                $priority = RedmineJiraPriority::where('jira_name', $content->issue->fields->priority->name)->first();
                Log::debug('-- Redmine priority found');
                $assignee =     RedmineJiraUser::where('jira_name', $content->issue->fields->assignee->key)->first();
                Log::debug('-- Redmine assignee found');

            // Create data array
                $data = array(
                    'project_id'      =>  $project->redmine_id,
                    'tracker_id'      =>  $tracker->redmine_id,
                    'status_id'       =>   $status->redmine_id,
                    'priority_id'     => $priority->redmine_id,
                    'assigned_to_id'  => $assignee->redmine_id,
                    'subject'         => $content->issue->fields->summary,
                    'description'     => $content->issue->fields->description,
                    'custom_fields'   => array(
                        'custom_value' => array(
                            'id'    => Config::get('redmine.jira_id'),
                            'value' => $content->issue->key,
                        )
                    )
                );
                Log::debug('-- Redmine data:');
                Log::debug(print_r($data, true));

            // Send data to Redmine
                $redmine->issue->create($data);
        }
        elseif ($action == 'updated')
        {
            // if changelog doesn't exist, ignore - it's a comment
                if (!isset($content->changelog)) return false;

            // Get Redmine Project
                $project  =  RedmineJiraProject::where('jira_name', $content->issue->fields->project->key)->first();
                Log::debug('-- Redmine project found');

            // Search task on Redmine based on Jira ID
                $args = array(
                    'limit' => 100,
                    'sort'  => 'created_on:desc',
                    'cf_9'  => $_GET['issue'],
                    'project_id' => $project->redmine_id,
                );
                Log::debug(print_r($args, true));
                $redmine_entries = $redmine->issue->all($args);
                Log::debug('-- Redmine tasks found:');
                Log::debug(print_r($redmine_entries, true));

            // Get first Redmine task
                $redmine_task = reset($redmine_entries['issues']);

            // Get updated fields
                $data = array();

                foreach ($content->changelog->items as $_item) {
                    switch ($_item->field)
                    {
                        case 'summary':
                            $data['subject'] = $_item->toString;
                            break;
                        case 'description':
                            $data['description'] = $_item->toString;
                            break;
                        case 'priority':
                            $priority = RedmineJiraPriority::where('jira_name', $_item->toString)->first();
                            $data['priority_id'] = $priority->redmine_id;
                            break;
                        case 'assignee':
                            $assignee = RedmineJiraUser::where('jira_name', $_item->to)->first();
                            $data['assigned_to_id'] = $assignee->redmine_id;
                            break;
                        case 'status':
                            $status = RedmineJiraStatus::where('jira_name', $_item->toString)->first();
                            $data['status_id'] = $status->redmine_id;
                            break;
                        case 'issuetype':
                            $tracker = RedmineJiraTracker::where('jira_name', 'like', '%' . $_item->toString . '%')->first();
                            $data['tracker_id'] = $tracker->redmine_id;
                            break;
                    }
                }
                Log::debug('-- Redmine data:');
                Log::debug(print_r($data, true));

            // Send data to Redmine
                $redmine->issue->update($redmine_task['id'], $data);
        }
        elseif ($action == 'deleted')
        {
            // Get Redmine Project
                $project = RedmineJiraProject::where('jira_name', $content->issue->fields->project->key)->first();
                Log::debug('-- Redmine project found');

            // Search task on Redmine based on Jira ID
                $args = array(
                    'limit' => 100,
                    'sort'  => 'created_on:desc',
                    'cf_9'  => $_GET['issue'],
                    'project_id' => $project->redmine_id,
                );
                Log::debug(print_r($args, true));
                $redmine_entries = $redmine->issue->all($args);
                Log::debug('-- Redmine tasks found:');
                Log::debug(print_r($redmine_entries, true));

            // Get first Redmine task
                $redmine_task = reset($redmine_entries['issues']);

            // Remove task
                $redmine->issue->remove($redmine_task['id']);
                Log::debug('-- Redmine task removed: '.$redmine_task['id']);
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
                $project  =  RedmineJiraProject::where('jira_name', $content->issue->fields->project->key)->first();
                Log::debug('-- Redmine project found');

            // Search task on Redmine based on Jira ID
                $args = array(
                    'limit' => 100,
                    'sort'  => 'created_on:desc',
                    'cf_9'  => $_GET['issue'],
                    'project_id' => $project->redmine_id,
                );
                Log::debug(print_r($args, true));
                $redmine_entries = $redmine->issue->all($args);
                Log::debug('-- Redmine tasks found:');
                Log::debug(print_r($redmine_entries, true));

            // Get first Redmine task
                $redmine_task = reset($redmine_entries['issues']);

            // Build data array
                $data = array(
                    'notes' => $content->comment->body,
                );
                Log::debug('-- Redmine data:');
                Log::debug(print_r($data, true));

            // Send data to Redmine
                $redmine->issue->update($redmine_task['id'], $data);
        }
    }
}
