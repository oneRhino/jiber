<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\RedmineController;
use App\RedmineJiraProject;

class RedmineJiraProjectsController extends Controller
{
    public function index()
    {
        $projects = RedmineJiraProject::get();

        return view('redmine_jira_projects.index', [
            'projects' => $projects,
        ]);
    }

    public function edit(RedmineJiraProject $project)
    {
        return view('redmine_jira_projects.form', [
            'project' => $project,
        ]);
    }

    public function update(RedmineJiraProject $project, Request $request)
    {
        // Save project
        $project->redmine_name = $request->redmine_name;
        $project->jira_name    = $request->jira_name;
        $project->content      = $request->content;
        $project->save();

        $request->session()->flash('alert-success', 'Project updated successfully!');

        return redirect()->action('RedmineJiraProjectsController@index');
    }

    public function destroy(RedmineJiraProject $project, Request $request)
    {
        $project->delete();

        $request->session()->flash('alert-success', 'Project has been successfully deleted!');

        return back()->withInput();
    }

    public function import(Request $request)
    {
        // Get all projects from Redmine
        $redmineController = new RedmineController;
        $redmine = $redmineController->connect();

        $projects = $redmine->project->all(array('limit' => 1000));

        foreach ($projects['projects'] as $_project)
        {
            // Only merge/import OMG projects
            if ($_project['id'] != 166 && (!isset($_project['parent']) || $_project['parent']['id'] != 166)) continue;

            // Sync project
            $project = RedmineJiraProject::where('redmine_name', $_project['name'])->get()->first();

            if (!$project) {
                $project = new RedmineJiraProject();
                $project->redmine_name = $_project['name'];
            }

            $project->redmine_id = $_project['id'];
            $project->save();
        }

        $request->session()->flash('alert-success', 'All projects have been imported successfully!');

        return back()->withInput();
    }
}
