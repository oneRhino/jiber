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
 * Show Toggl Report form and list
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 30, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Report;
use App\TimeEntry;
use App\TogglClient;
use App\TogglProject;
use App\TogglReport;
use App\TogglTask;
use App\TogglTimeEntry;
use App\TogglWorkspace;

class TogglReportController extends TogglController
{
    /**
     * List all reports on system, and show form to create a new report
     */
    public function index()
    {
        $reports    =    TogglReport::getAllByUserID(Auth::user()->id, 'toggl_reports.id', 'DESC');
        $workspaces = TogglWorkspace::getAllByUserID(Auth::user()->id);
        $clients    =    TogglClient::getAllByUserID(Auth::user()->id);

        return view('toggl_report.index', [
            'reports'    => $reports,
            'workspaces' => $workspaces,
            'clients'    => $clients,
        ]);
    }

    /**
     * Save report, and all entries
     */
    public function save(Request $request)
    {
        set_time_limit(0);

        $toggl_client = $this->toggl_connect();

        if (!$request->date) {
            $request->start_date = date('Y-m-d', strtotime('-6 days'));
            $request->end_date   = date('Y-m-d');
        } else {
            list($start_date, $end_date) = explode(' - ', $request->date);

            $request->start_date = date('Y-m-d', strtotime($start_date));
            $request->end_date   = date('Y-m-d', strtotime($end_date));
        }

        // Save Report
        $report              = new Report();
        $report->user_id     = Auth::user()->id;
        $report->start_date  = $request->start_date;
        $report->end_date    = $request->end_date;
        $report->save();

        $toggl_report              = new TogglReport();
        $toggl_report->id          = $report->id;
        $toggl_report->client_ids  = ($request->clients  ? implode(',', $request->clients)  : null);
        $toggl_report->project_ids = ($request->projects ? implode(',', $request->projects) : null);
        $toggl_report->save();

        // Get current user from Toggl, so we can filter time entries
        $current_user = $toggl_client->GetCurrentUser();

        // Toggl's arguments array
        $args = array(
            'user_agent'   => 'Jiber <thaissa.mendes@gmail.com>',
            'workspace_id' => (int)$request->workspace,
            'user_ids'     => $current_user['id'],
        );

        if ($request->start_date) {
            $args['since'] = $request->start_date;
        }

        if ($request->end_date) {
            $args['until'] = $request->end_date;
        }

        if ($request->clients) {
            $args['client_ids'] = implode(',', $request->clients);
        }

        $results = $this->getToggle($args);

        // Toggl has a limit of time entries per page
        $pages = ceil($results['total_count'] / $results['per_page']);

        for ($page=1; $page<=$pages; $page++) {
            if ($page > 1) {
                $args['page'] = $page;
                $results      = $this->getToggle($args);
            }

            foreach ($results['data'] as $_data) {
                $client  =  TogglClient::getByName($_data['client'] , Auth::user()->id);
                $project = TogglProject::getByName($_data['project'], Auth::user()->id);
                $task    =    TogglTask::getByTogglID($_data['tid'] , Auth::user()->id);

                // Create Time Entry
                $entry              = new TimeEntry();
                $entry->user_id     = Auth::user()->id;
                $entry->report_id   = $report->id;
                $entry->date_time   = date('Y-m-d H:i:s', strtotime($_data['start']));
                $entry->duration    = $_data['dur'];
                $entry->description = $_data['description'];

                // If client is OneRhino, get Redmine and Jira related task IDs and save on time entry
                if ($redmine_issue_id = $entry->isRedmine()) {
                    $entry->redmine_issue_id = $redmine_issue_id;

                    if ($jira_issue_id = $entry->isJira()) {
                        $entry->jira_issue_id = $jira_issue_id;
                    }
                }

                $entry->save();

                // Create Toggl Time Entry
                $toggl_entry             = new TogglTimeEntry();
                $toggl_entry->id         = $entry->id;
                $toggl_entry->toggl_id   = $_data['id'];
                $toggl_entry->client_id  = $client->id;
                $toggl_entry->project_id = ($project ? $project->id : null);
                $toggl_entry->task_id    = ($task ? $task->id : null);
                $toggl_entry->save();
            }
        }

        return redirect()->action('TogglReportController@show', [$report->id]);
    }

    /**
     * Get Toggl time entries based on arguments
     */
    private function getToggle($args)
    {
        $reports_client = $this->reports_connect();

        return $reports_client->details($args);
    }

    /**
     * Show report time entries
     */
    public function show(Report $report, Request $request)
    {
        if ($report->user_id != Auth::user()->id) {
            abort(403, 'Unauthorized action.');
        }

        // Empty Redmine and Jira report from session
        if ($request->session()->has('redmine.report.' . $report->id)) {
            $request->session()->forget('redmine.report.' . $report->id);
        }

        if ($request->session()->has('jira.report.' . $report->id)) {
            $request->session()->forget('jira.report.' . $report->id);
        }

        return view('toggl_report.show', [
            'report' => $report,
        ]);
    }

    /**
     * Remove report from database
     */
    public function delete(Report $report, Request $request)
    {
        if ($report->user_id != Auth::user()->id) {
            abort(403, 'Unauthorized action.');
        }

        if (!$report->canDelete()) {
            $request->session()->flash('alert-danger', 'This report cannot be deleted.');

            return back()->withInput();
        }

        $report->delete();
        $request->session()->flash('alert-success', 'Report has been successfully deleted!');

        return back()->withInput();
    }
}
