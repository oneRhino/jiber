<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\RedmineController;
use App\{ClubhouseStatus, RedmineStatus};

class RedmineStatusesController extends Controller
{
    public function index()
    {
        $statuses = RedmineStatus::get();

        return view('redmine_statuses.index', [
            'statuses' => $statuses,
        ]);
    }

    public function edit(RedmineStatus $status)
    {
        $clubhouse_statuses = ClubhouseStatus::orderby('clubhouse_name')->get();

        return view('redmine_statuses.form', [
            'status'             => $status,
            'clubhouse_statuses' => $clubhouse_statuses,
        ]);
    }

    public function update(RedmineStatus $status, Request $request)
    {
        // Save status
        $status->redmine_name      = $request->redmine_name;
        $status->jira_name         = $request->jira_name;
        $status->clubhouse_main_id = json_encode($request->clubhouse_main_id);
        $status->clubhouse_id      = json_encode($request->clubhouse_id);
        $status->save();

        $request->session()->flash('alert-success', 'Status updated successfully!');

        return redirect()->action('RedmineStatusesController@index');
    }

    public function destroy(RedmineStatus $status, Request $request)
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
            $status = RedmineStatus::where('redmine_name', $_status['name'])->get()->first();

            if (!$status) {
                $status = new RedmineStatus();
                $status->redmine_name = $_status['name'];
            }

            $status->redmine_id = $_status['id'];
            $status->save();
        }

        $request->session()->flash('alert-success', 'All statuses have been imported successfully!');

        return back()->withInput();
    }
}
