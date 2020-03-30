<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\RedmineController;
use App\{ClubhouseProject, RedmineProject};

class RedmineProjectsController extends Controller
{
    public function index()
    {
        $projects = RedmineProject::get();

        return view('redmine_projects.index', [
            'projects' => $projects,
        ]);
    }

    public function edit(RedmineProject $project)
    {
        $clubhouse_projects = ClubhouseProject::all();

        return view('redmine_projects.form', [
            'project' => $project,
            'clubhouse_projects' => $clubhouse_projects,
        ]);
    }

    public function update(RedmineProject $project, Request $request)
    {
        switch ($request->third_party) {
            case 'clubhouse':
                list($third_party_project_id, $third_party_project_name) = explode('|', $request->third_party_clubhouse);
                break;

            case 'jira':
            default:
                $third_party_project_id   = null;
                $third_party_project_name = $request->third_party_project_name;
                break;
        }

        // Save project
        $project->project_name             = $request->project_name;
        $project->third_party              = $request->third_party;
        $project->third_party_project_id   = $third_party_project_id;
        $project->third_party_project_name = $third_party_project_name;
        $project->content                  = $request->content;
        $project->save();

        $request->session()->flash('alert-success', 'Project updated successfully!');

        return redirect()->action('RedmineProjectsController@index');
    }

    public function destroy(RedmineProject $project, Request $request)
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
            $project = RedmineProject::where('project_name', $_project['name'])->get()->first();

            if (!$project) {
                $project = new RedmineProject();
                $project->project_name = $_project['name'];
            }

            $project->project_id = $_project['id'];
            $project->save();
        }

        $request->session()->flash('alert-success', 'All projects have been imported successfully!');

        return back()->withInput();
    }
}
