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
 * Control connection with Toggl, using Toggl Client and Reports Client
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 28, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Config};
use Illuminate\Routing\Redirector;
use AJT\Toggl\TogglClient;
use AJT\Toggl\ReportsClient;
use App\{Setting, Report, TimeEntry};
use DateTime;

class TogglController extends Controller
{
    /**
     * Check if Toggl API Token has been set on Settings
     */
    public function __construct(Request $request=null, Redirector $redirect=null)
    {
        // $this->middleware(function ($request, $next) {
        //     $setting = Setting::find(Auth::user()->id);
        //
        //     if (!$setting || !$setting->toggl) {
        //         $request->session()->flash('alert-warning', 'Please set your Toggl API Token before importing data.');
        //         $redirect->to('/settings')->send();
        //     }
        // });
    }

    /**
     * Connect into Toggl API
     */
    public function toggl_connect($omg = false)
    {
        if($omg){
            $token = Config::get('toggl.omg_api_key');
            $client  = TogglClient::factory(array('api_key' => $token));
        }
        else{
            $setting = Setting::find(Auth::user()->id);
            $token   = $setting->toggl;
            $client  = TogglClient::factory(array('api_key' => $token));
        }

        return $client;
    }

    /**
     * Connect into Reports API
     */
    public function reports_connect()
    {
        $token  = Setting::find(Auth::user()->id)->toggl;
        $client = ReportsClient::factory(array('api_key' => $token));

        return $client;
    }

    /**
     * Test Toggl connection
     */
    public function test()
    {
        try {
            $client = $this->toggl_connect();
            $me     = $client->GetCurrentUser();
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Import everything - workspaces, clients, projects and tasks
     */
    public function import(Request $request, $omg = false)
    {
        app('App\Http\Controllers\TogglWorkspaceController')->import($request);
        app('App\Http\Controllers\TogglClientController')   ->import($request);
        app('App\Http\Controllers\TogglProjectController')  ->import($request);
        app('App\Http\Controllers\TogglTaskController')     ->import($request);

        $request->session()->flash('alert-success', 'All your Toggl data have been imported successfully!');

        return redirect()->action('TogglWorkspaceController@index');
    }

    /**
     * Show report time entries
     */
    public function show(Report $report, Request $request)
    {
        if ($report->user_id != Auth::user()->id) {
            abort(403, 'Unauthorized action.');
        }

        set_time_limit(0);

        $toggl_entries = $this->getTogglEntries($report->start_date, $report->end_date);

        // Get all time entries from Report, but only those
        // that have Redmine field filled
        $redmine_entries = $report->getTimeEntries();
        $entries       = array();

        // First create arrays and fill with Redmine information
        foreach ($redmine_entries as $_entry) {
            // Ignore entries without Jira ID
            if (!$_entry->jira_issue_id) {
                continue;
            }

            // Create default arrays
            if (!isset($entries[$_entry->date])) {
                $entries[$_entry->date] = array(
                    'toggl_total' => 0,
                    'third_total' => 0,
                );
            }

            if (!isset($entries[$_entry->date][$_entry->redmine_issue_id])) {
                $entries[$_entry->date][$_entry->redmine_issue_id] = array(
                    'toggl_entries' => array(),
                    'toggl_total'   => 0,
                    'third_total'   => 0,
                    'third_entries' => array(),
                );
            }

            // Fill arrays
            $entries[$_entry->date][$_entry->redmine_issue_id]['toggl_entries'][] = $_entry;
            $entries[$_entry->date][$_entry->redmine_issue_id]['toggl_total']    += $_entry->round_decimal_duration;
            $entries[$_entry->date]['toggl_total']                               += $_entry->round_decimal_duration;
        }

        // Then, through all Redmine's entries, add Toggl's entries
        foreach ($entries as $_date => $_entries) {
            foreach ($_entries as $_issue_id => $_entries) {
                // Get Toggl time entries
                foreach ($toggl_entries['data'] as $_toggl) {
                    // Ignore issues that are different than current,
                    // and with date different than current
                    $formatted_date = new DateTime($_toggl['end']);

                    if ($formatted_date->format('Y-m-d') != $_date) {
                        continue;
                    }

                    preg_match('/#([0-9]+)/', $_toggl['description'], $matches);
                    $_redmine_issue_id_found = $matches[1] ?? false;

                    if ($_redmine_issue_id_found != $_issue_id) {
                        continue;
                    }

                    $_toggl['comments']  = $_toggl['description'] ?? $_toggl['task'];
                    $_toggl['time']      = number_format((float)$_toggl['dur'] / 3600000, 2, '.', '');

                    $entries[$_date][$_issue_id]['third_entries'][] = $_toggl;
                    $entries[$_date][$_issue_id]['third_total']    += number_format((float)$_toggl['time'], 2, '.', '');;
                    $entries[$_date]['third_total']                += number_format((float)$_toggl['time'], 2, '.', '');;
                }
            }
        }

        // Sort entries based on first key (date), ascending
        ksort($entries);

        return view('toggl.show', [
            'entries'   => $entries,
            'report_id' => $report->id,
        ]);
    }

    /**
     * Send time to Toggl
     */
    public function send(Request $request)
    {
        if (!$request->task) {
            $request->session()->flash('alert-success', 'No tasks sent - nothing to do.');
            return back();
        }

        // Connect into Toggl
        $client = $this->toggl_connect();

        // Get worspace ID
        $settings = Setting::where(['toggl_redmine_sync' => true, 'id' => Auth::user()->id])->first();
        $workspace_id = unserialize($settings->toggl_redmine_data)['workspace'];

        foreach ($request->task as $_entry_id) {
            $_entry = TimeEntry::find($_entry_id);

            if (!$_entry || $_entry->user_id != Auth::user()->id || !$_entry->redmine_issue_id) {
                continue;
            }
            $_data = [
                'time_entry' => [
                    'description'   => $_entry['description'],
                    'wid'           => (int)$workspace_id,
                    'billable'      => true,
                    'duration'      => $_entry['duration'] / 1000,
                    'start'         => date('c', strtotime($_entry['date_time'])),
                    'created_with'  => 'curl'
                ]
            ];

            $_create = $client->createTimeEntry($_data);
            $response = $_create->toArray();
        }

        // Remove report from session, so when we show previous page again, it's updated
        if ($request->isMethod('post') && isset($response['data']['id'])) {
            $request->session()->flash('alert-success', 'All tasks have been sent successfully to Toggl!');
        }

        return back()->withInput();
    }

    /**
     * Get Toggl report entries
     */
    public function getTogglEntries($start_date, $end_date)
    {
        // Get user logged in
        $user = Auth::user();

        // Connect to Toggl
        $togglClient = $this->toggl_connect();

        // Get current user Toggl info
        $current_user = $togglClient->GetCurrentUser();

        // Connect to Toggl Report API
        $reportClient = $this->reports_connect();

        // Get worspace ID
        $settings = Setting::where(['toggl_redmine_sync' => true, 'id' => $user->id])->first();
        $workspace_id = unserialize($settings->toggl_redmine_data)['workspace'];

        // Get all Toggl's entries for this user, on these dates
        $args = array(
            'user_agent' => (string)$user->email,
            'workspace_id' => (int)$workspace_id,
            'user_ids' => $current_user['data']['id'],
            'since' => $start_date,
            'until' => $end_date,
        );

        $toggl_entries = $reportClient->details($args);
        return $toggl_entries;
    }
}
