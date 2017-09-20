<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\RedmineController;
use App\RedmineJiraPriority;

class RedmineJiraPrioritiesController extends Controller
{
    public function index()
    {
        $priorities = RedmineJiraPriority::get();

        return view('redmine_jira_priorities.index', [
            'priorities' => $priorities,
        ]);
    }

    public function edit(RedmineJiraPriority $priority)
    {
        return view('redmine_jira_priorities.form', [
            'priority' => $priority,
        ]);
    }

    public function update(RedmineJiraPriority $priority, Request $request)
    {
        // Save priority
        $priority->redmine_name = $request->redmine_name;
        $priority->jira_name    = $request->jira_name;
        $priority->save();

        $request->session()->flash('alert-success', 'Priority updated successfully!');

        return redirect()->action('RedmineJiraPrioritiesController@index');
    }

    public function destroy(RedmineJiraPriority $priority, Request $request)
    {
        $priority->delete();

        $request->session()->flash('alert-success', 'Priority has been successfully deleted!');

        return back()->withInput();
    }

    public function import(Request $request)
    {
        // Get all priorities from Redmine
        $redmineController = new RedmineController;
        $redmine = $redmineController->connect();

        $priorities = $redmine->issue_priority->all(array('limit' => 1000));

        foreach ($priorities['issue_priorities'] as $_priority)
        {
            // Sync priority
            $priority = RedmineJiraPriority::where('redmine_name', $_priority['name'])->get()->first();

            if (!$priority) {
                $priority = new RedmineJiraPriority();
                $priority->redmine_name = $_priority['name'];
            }

            $priority->redmine_id = $_priority['id'];
            $priority->save();
        }

        $request->session()->flash('alert-success', 'All priorities have been imported successfully!');

        return back()->withInput();
    }
}
