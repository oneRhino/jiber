<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Mikkelson\Clubhouse;

use App\Http\Requests;
use App\RedmineClubhouseProject;
use App\RedmineJiraProject;
use App\RedmineProject;

class RedmineClubhouseProjectsController extends Controller
{
    public function index()
    {
        $projects = RedmineClubhouseProject::get();

        return view('redmine_clubhouse_projects.index', [
            'projects' => $projects,
        ]);
    }

    public function edit(RedmineClubhouseProject $project)
    {

        $redmineProjectObj = new RedmineProject();
        $redmineProjects = $redmineProjectObj->orderBy('project_name', 'asc')->get(['project_id', 'project_name']);

        return view('redmine_clubhouse_projects.form', [
            'project' => $project,
            'redmine_projects' => $redmineProjects
        ]);
    }

    public function update(RedmineClubhouseProject $project, Request $request)
    {
        $redmineProjectObj = new RedmineJiraProject();
        $redmineProject = $redmineProjectObj->where('redmine_id', $request->project_id)->first();

        // Save project
        $project->redmine_id = $request->project_id;
        $project->redmine_name = $redmineProject->redmine_name;
        $project->clubhouse_name = $request->clubhouse_name;
        $project->content = $request->content;
        $project->save();

        $request->session()->flash('alert-success', 'Project updated successfully!');

        return redirect()->action('RedmineClubhouseProjectsController@index');
    }

    public function destroy(RedmineClubhouseProject $project, Request $request)
    {
        $project->delete();

        $request->session()->flash('alert-success', 'Project has been successfully deleted!');

        return back()->withInput();
    }

    public function import(Request $request)
    {
        // Get projects from Clubhouse
        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);
        
        $projectsAsArray = $clubhouseApi->get('projects');

        $newClubhouseProjects = 0;
        
        foreach ($projectsAsArray as $project) {
            
            // Ignore ARCHIVED projects.
            if ($project['archived']) {
                continue;
            }

            $projectObj = RedmineClubhouseProject::where('clubhouse_id', $project['id'])->get()->first();

            if (!$projectObj) {
                $newClubhouseProjects++;
                $projectObj = new RedmineClubhouseProject();
                $projectObj->clubhouse_id = $project['id'];
                $projectObj->clubhouse_name = $project['name'];
                $projectObj->redmine_id = "0";
            }

            $projectObj->save();
        }
        
        // Get projects from Redmine
        $redmineController = new RedmineController;
        $redmine = $redmineController->connect();

        $projects = $redmine->project->all(array('limit' => 1000));

        $newRedmineProjects = 0;

        foreach ($projects['projects'] as $_project)
        {
            // Only merge/import OMG projects
            if ($_project['id'] != 166 && (!isset($_project['parent']) || $_project['parent']['id'] != 166)) continue;
            
            // Sync project
            $project = RedmineProject::where('project_name', $_project['name'])->get()->first();

            if (!$project) {
                $newRedmineProjects++;
                $project = new RedmineProject();
                $project->project_name = $_project['name'];
            }

            $project->project_id = $_project['id'];
            $project->save();
        }

        $request->session()->flash('alert-success', "All projects have been imported successfully! New Clubhouse projects: {$newClubhouseProjects} | New Redmine projects: {$newRedmineProjects}");

        return back()->withInput();
    }
}
