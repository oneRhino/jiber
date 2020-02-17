<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Mikkelson\Clubhouse;

use App\Http\Requests;
use App\Http\Controllers\RedmineController;
use App\RedmineClubhouseProject;

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
        return view('redmine_clubhouse_projects.form', [
            'project' => $project,
        ]);
    }

    public function update(RedmineClubhouseProject $project, Request $request)
    {
        // Save project
        $project->redmine_id = $request->redmine_id;
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

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);
        
        $projectsAsArray = $clubhouseApi->get('projects');

        foreach ($projectsAsArray as $project) {

            $projectObj = RedmineClubhouseProject::where('clubhouse_id', $project['id'])->get()->first();

            if (!$projectObj) {
                $projectObj = new RedmineClubhouseProject();
                $projectObj->clubhouse_id = $project['id'];
                $projectObj->clubhouse_name = $project['name'];
                $projectObj->redmine_id = "0";
            }

            $projectObj->save();
        }

        $request->session()->flash('alert-success', 'All projects have been imported successfully!');

        return back()->withInput();
    }
}
