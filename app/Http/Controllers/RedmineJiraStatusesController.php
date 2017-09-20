<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\RedmineController;
use App\RedmineJiraStatus;

class RedmineJiraStatusesController extends Controller
{
    public function index()
    {
        $statuses = RedmineJiraStatus::get();

        return view('redmine_jira_statuses.index', [
            'statuses' => $statuses,
        ]);
    }

    public function edit(RedmineJiraStatus $status)
    {
        return view('redmine_jira_statuses.form', [
            'status' => $status,
        ]);
    }

    public function update(RedmineJiraStatus $status, Request $request)
    {
        // Save status
        $status->redmine_name = $request->redmine_name;
        $status->jira_name    = $request->jira_name;
        $status->save();

        $request->session()->flash('alert-success', 'Status updated successfully!');

        return redirect()->action('RedmineJiraStatusesController@index');
    }

    public function destroy(RedmineJiraStatus $status, Request $request)
    {
        $status->delete();

        $request->session()->flash('alert-success', 'Status has been successfully deleted!');

        return back()->withInput();
    }

    public function import(Request $request)
    {
        // Get all statuses from Redmine
        $redmineController = new RedmineController;
        $redmine = $redmineController->connect();

        $statuses = $redmine->issue_status->all(array('limit' => 1000));

        foreach ($statuses['issue_statuses'] as $_status)
        {
            // Sync status
            $status = RedmineJiraStatus::where('redmine_name', $_status['name'])->get()->first();

            if (!$status) {
                $status = new RedmineJiraStatus();
                $status->redmine_name = $_status['name'];
            }

            $status->redmine_id = $_status['id'];
            $status->save();
        }

        $request->session()->flash('alert-success', 'All statuses have been imported successfully!');

        return back()->withInput();
    }
}
