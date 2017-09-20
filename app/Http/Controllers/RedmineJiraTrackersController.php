<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\RedmineController;
use App\RedmineJiraTracker;

class RedmineJiraTrackersController extends Controller
{
    public function index()
    {
        $trackers = RedmineJiraTracker::get();

        return view('redmine_jira_trackers.index', [
            'trackers' => $trackers,
        ]);
    }

    public function edit(RedmineJiraTracker $tracker)
    {
        return view('redmine_jira_trackers.form', [
            'tracker' => $tracker,
        ]);
    }

    public function update(RedmineJiraTracker $tracker, Request $request)
    {
        // Save tracker
        $tracker->redmine_name = $request->redmine_name;
        $tracker->jira_name    = $request->jira_name;
        $tracker->save();

        $request->session()->flash('alert-success', 'Tracker updated successfully!');

        return redirect()->action('RedmineJiraTrackersController@index');
    }

    public function destroy(RedmineJiraTracker $tracker, Request $request)
    {
        $tracker->delete();

        $request->session()->flash('alert-success', 'Tracker has been successfully deleted!');

        return back()->withInput();
    }

    public function import(Request $request)
    {
        // Get all trackers from Redmine
        $redmineController = new RedmineController;
        $redmine = $redmineController->connect();

        $trackers = $redmine->tracker->all(array('limit' => 1000));

        foreach ($trackers['trackers'] as $_tracker)
        {
            // Sync tracker
            $tracker = RedmineJiraTracker::where('redmine_name', $_tracker['name'])->get()->first();

            if (!$tracker) {
                $tracker = new RedmineJiraTracker();
                $tracker->redmine_name = $_tracker['name'];
            }

            $tracker->redmine_id = $_tracker['id'];
            $tracker->save();
        }

        $request->session()->flash('alert-success', 'All trackers have been imported successfully!');

        return back()->withInput();
    }
}
