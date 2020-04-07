<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\RedmineController;
use App\RedmineTracker;

class RedmineTrackersController extends Controller
{
    public function index()
    {
        $trackers = RedmineTracker::get();

        return view('redmine_trackers.index', [
            'trackers' => $trackers,
        ]);
    }

    public function edit(RedmineTracker $tracker)
    {
        return view('redmine_trackers.form', [
            'tracker' => $tracker,
        ]);
    }

    public function update(RedmineTracker $tracker, Request $request)
    {
        // Save tracker
        $tracker->redmine_name   = $request->redmine_name;
        $tracker->jira_name      = $request->jira_name;
        $tracker->clubhouse_name = $request->clubhouse_name;
        $tracker->save();

        $request->session()->flash('alert-success', 'Tracker updated successfully!');

        return redirect()->action('RedmineTrackersController@index');
    }

    public function destroy(RedmineTracker $tracker, Request $request)
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
            $tracker = RedmineTracker::where('redmine_name', $_tracker['name'])->get()->first();

            if (!$tracker) {
                $tracker = new RedmineTracker();
                $tracker->redmine_name = $_tracker['name'];
            }

            $tracker->redmine_id = $_tracker['id'];
            $tracker->save();
        }

        $request->session()->flash('alert-success', 'All trackers have been imported successfully!');

        return back()->withInput();
    }
}
