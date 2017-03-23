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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\JiraSent;
use App\Report;
use App\Setting;
use App\TimeEntry;
use chobie\Jira\Api;
use chobie\Jira\Api\Authentication\Basic;

class JiraController extends Controller
{
    /**
     * Connect into Jira API
     */
    public function connect(Request $request)
    {
        $redirect = app('Illuminate\Routing\Redirector');
        $url      = Config::get('jira.url');
        $username = Setting::find(Auth::user()->id)->jira;
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

        $request->session()->forget('jira.report');

        // Only enter if no session exists for jira.report
        // It'll be reset elsewhere
        if (!$request->session()->has('jira.report.' . $report->id)) {
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
                            if ($_time['author']['name'] != $jira_username) {
                                continue;
                            }

                            // Only add worklog for this specific date
                            if (strtotime($_date) != strtotime(date('Y-m-d', strtotime($_time['started'])))) {
                                continue;
                            }

                            $_time['description'] = ($_time['comment'] ? $_time['comment'] : '<No comment>');
                            $_time['time']        = round(($_time['timeSpentSeconds'] / 3600), 2);

                            $entries[$_date][$_redmine]['third_entries'][] = $_time;
                            $entries[$_date][$_redmine]['third_total']    += round(($_time['timeSpentSeconds'] / 3600), 2);
                            $entries[$_date]['third_total']               += round(($_time['timeSpentSeconds'] / 3600), 2);
                        }
                    }
                }
            }

            // Sort entries based on first key (date), ascending
            ksort($entries);

            $request->session()->put('jira.report.' . $report->id, $entries);
        } else {
            $entries = $request->session()->get('jira.report.' . $report->id);
        }

        return view('jira.show', [
            'entries'   => $entries,
            'report_id' => $report->id,
        ]);
    }

    /**
     * Send time to Jira
     */
    public function send(Request $request)
    {
        if ($request->isMethod('post')) {
            if (!$request->task) {
                $request->session()->flash('alert-success', 'No tasks sent - nothing to do.');

                return back();
            }

            // Connect into Jira
            $jira = $this->connect($request);

            foreach ($request->task as $_entry_id) {
                $_entry = TimeEntry::find($_entry_id);

                if (!$_entry || $_entry->user_id != Auth::user()->id) {
                    continue;
                }

                // Transforming date into Jira's format
                // removing ':' from timezone and adding '.000' after seconds
                $_date = preg_replace('/([-+][0-9]{2}):([0-9]{2})$/', '.000${1}${2}', date('c', strtotime($_entry->date_time)));

                $_data = array(
                    'timeSpentSeconds' => $_entry->decimal_duration * 3600,
                    'started'          => $_date,
                    'comment'          => htmlentities($_entry->description),
                    'issueId'          => trim($_entry->jira_issue_id),
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
            $request->session()->forget('jira.report.' . $request->report_id);
            $request->session()->flash('alert-success', 'All tasks have been sent successfully to Jira!');
        }

        return back()->withInput();
    }
}
