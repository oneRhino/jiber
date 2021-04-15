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
 * Show Redmine Report form and results
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since August 21, 2016
 * @version 0.1
 */


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use App\Report;
use App\RedmineReport;
use App\RedmineTimeEntry;
use App\TimeEntry;

class RedmineReportController extends RedmineController
{
    /**
     * Show form to create a new report
     */
    public function index(Request $request)
    {
        $reports = RedmineReport::getAllByUserID(Auth::user()->id, 'redmine_reports.id', 'DESC');

        return view('redmine_report.index',[
            'reports' => $reports,
        ]);
    }

    public function save(Request $request, $redirect = true)
    {
        list($start_date, $end_date) = explode(' - ', $request->date);

        $start_date  = date('Y-m-d', strtotime($start_date));
        $end_date    = date('Y-m-d', strtotime($end_date));
        $filter_user = ($request->filter_user ? true : false);

        // Get Redmine Entries
        $redmine_entries = $this->getRedmineEntries($start_date, $end_date, $filter_user);

        if (!$redmine_entries) {
            return;
        }

        // Save Report
        $report              = new Report();
        $report->user_id     = Auth::user()->id;
        $report->start_date  = $start_date;
        $report->end_date    = $end_date;
        $report->filter_user = $filter_user;
        $report->save();

        $redmine_report     = new RedmineReport();
        $redmine_report->id = $report->id;
        $redmine_report->save();

        // Save Redmine Entries
        foreach ($redmine_entries['time_entries'] as $_entry) {
            if (!isset($_entry['issue'])) continue;

    		if (isset($_entry['comments'])) {
    			$description = $_entry['comments'];
    		} elseif (isset($_entry['activity'])) {
    			$description = $_entry['activity']['name'];
    		} else {
    			$description = 'Development';
    		}

            // Make description not to have more than 100 chars
            $description = substr($description, 0, 100);

            $time_entry = new TimeEntry();
            $time_entry->user_id           = Auth::user()->id;
            $time_entry->user              = $_entry['user']['name'];
            $time_entry->report_id         = $report->id;
            $time_entry->description       = $description;
            $time_entry->date_time         = $_entry['spent_on'] . ' ' . date('H:i:s', strtotime($_entry['created_on']));
            $time_entry->duration          = $_entry['hours'] * 60 * 60 * 1000;
            $time_entry->redmine_issue_id  = $_entry['issue']['id'];
            if ($jira_issue_id = $time_entry->isJira()) {
                $time_entry->jira_issue_id = $jira_issue_id;
            }
            $time_entry->save();

            $redmine_time_entry             = new RedmineTimeEntry();
            $redmine_time_entry->id         = $time_entry->id;
            $redmine_time_entry->redmine_id = $_entry['id'];
            $redmine_time_entry->save();
        }

        if ($redirect)
            return redirect()->action('RedmineReportController@show', [$report->id]);
        else
            return $report->id;
    }

    /**
     * Show report time entries
     */
    public function show(Report $report, Request $request)
    {
        if ($report->user_id != Auth::user()->id) {
            abort(403, 'Unauthorized action.');
        }

        return view('redmine_report.show', [
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

    public function sendAllToJira($report_id, $request) {
        $report = Report::find($report_id);

        if (!$report)
            abort(403, 'Report not found.');

        $request->task      = array();
        $request->report_id = $report_id;

        foreach ($report->redmine_entries as $_entry) {
            if ($_entry->jira_issue_id)
                $request->task[] = $_entry->id;
        }

        if ($request->task) {
            $redmine = new JiraController();
            $redmine->send($request);
            return true;
        }

        return false;
    }

    public function sendAllToToggl($report_id, $request) {
        $report = Report::find($report_id);

        if (!$report)
            abort(403, 'Report not found.');

        $request->task      = array();
        $request->report_id = $report_id;

        foreach ($report->redmine_entries as $_entry) {
            if ($_entry->jira_issue_id)
                $request->task[] = $_entry->id;
        }

        if ($request->task) {
            $toggl = new TogglController();
            $toggl->send($request);
            return true;
        }

        return false;
    }
}
