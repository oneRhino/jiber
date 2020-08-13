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
 * List and import Toggl Projects
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 29, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\TogglProject;
use App\TogglWorkspace;
use App\TogglClient;
use App\ClubhouseProject;

class TogglProjectController extends TogglController
{
    /**
     * List projects saved on system
     */
    public function index($omg = false)
    {
        if($omg){
            $projects = TogglProject::getAllByUserID(null);
        }
        else{
            $projects = TogglProject::getAllByUserID(Auth::user()->id);
        }

        return view('toggl_project.index', [
            'projects' => $projects,
            'omg' => $omg
        ]);
    }

    /**
     * Import projects from Toggl
     */
    public function import(Request $request, $omg = false)
    {
        $toggl_client = $this->toggl_connect($omg);
        $user = Auth::user()->id;
        if($omg){
            $user = null;
        }

        $workspaces = TogglWorkspace::getAllByUserID($user);

        foreach ($workspaces as $_workspace) {
            $projects = $toggl_client->GetWorkspaceProjects(array('id' => (int)$_workspace->toggl_id, 'active' => 'both'));

            if ($projects) {
                foreach ($projects as $_project) {
                    $project = TogglProject::getByTogglID($_project['id'], $user);

                    if (!$project) {
                        $project           = new TogglProject();
                        $project->user_id  = $user;
                        $project->toggl_id = $_project['id'];
                    }
                    $project->workspace_id = TogglWorkspace::getByTogglID($_project['wid'], $user)->id;
                    if (isset($_project['cid'])) {
                        $project->client_id = TogglClient::getByTogglID($_project['cid'], $user)->id;
                    }
                    $project->active = $_project['active'];
                    $project->name   = $_project['name'];
                    $project->save();
                }
            }

            sleep(1); // Toggl only allows 1 request per second
        }

        $request->session()->flash('alert-success', 'All projects have been successfully imported!');

        return back()->withInput();
    }

    /**
     * Edit project
     */
    public function edit(TogglProject $project, $omg = false)
    {
        $clubhouse_projects = ClubhouseProject::all();
        return view('toggl_project.edit', [
            'project' => $project,
            'clubhouse_projects' => $clubhouse_projects
        ]);
    }


    /**
     * Save report, and all entries
     */
    public function save(TogglProject $project, Request $request, $omg = false)
    {
        if($request->clubhouse_project !== '0'){
            $clubhouse_project = ClubhouseProject::find($request->clubhouse_project);
            $project->clubhouse_id = $clubhouse_project->id;
            $project->save();
        }
        else{
            $project->clubhouse_id = null;
            $project->save();
        }
        if($omg){
            return redirect()->route('omg.toggl.projects');
        }
        else{
            return redirect()->route('user.toggl.projects');
        }
    }
}
