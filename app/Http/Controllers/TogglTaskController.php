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
 * List and import Toggl Tasks
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 29, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\TogglTask;
use App\TogglWorkspace;
use App\TogglProject;

class TogglTaskController extends TogglController
{
    /**
     * List tasks saved on system
     */
    public function index($omg = false)
    {
        if($omg){
            $tasks = TogglTask::getAllByUserID(null, 'project_id');
        }
        else{
            $tasks = TogglTask::getAllByUserID(Auth::user()->id, 'project_id');
        }

        return view('toggl_task.index', [
            'tasks' => $tasks,
            'omg' => $omg
        ]);
    }

    /**
     * Import tasks from Toggl
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
            $tasks = $toggl_client->getWorkspaceTasks(array('id' => (int)$_workspace->toggl_id, 'active' => 'both'));

            if ($tasks) {
                foreach ($tasks as $_task) {
                    $task      =      TogglTask::getByTogglID($_task['id'] , $user);
                    $workspace = TogglWorkspace::getByTogglID($_task['wid'], $user);
                    $project   =   TogglProject::getByTogglID($_task['pid'], $user);

                    if (!$workspace || !$project) {
                        continue;
                    }

                    if (!$task) {
                        $task           = new TogglTask();
                        $task->user_id  = $user;
                        $task->toggl_id = $_task['id'];
                    }

                    $task->workspace_id = $workspace->id;
                    $task->project_id   = $project->id;
                    $task->active       = $_task['active'];
                    $task->estimated    = $_task['estimated_seconds'];
                    if (isset($_task['tracked_seconds'])) {
                        $task->tracked  = $_task['tracked_seconds'];
                    }
                    $task->name         = $_task['name'];
                    $task->save();
                }
            }

            sleep(1); // Toggl only allows 1 request per second
        }

        $request->session()->flash('alert-success', 'All tasks have been successfully imported!');

        return back()->withInput();
    }

    public function createTaskFromClubhouseAction($content, $omg = false){
        $toggl_client = $this->toggl_connect($omg);
        $task = $toggl_client->createTask(['task'=> $content]);
        $request = new Request();
        $this->import($request, $omg);
        $togglTask = TogglTask::where('toggl_id', $task->id)->first();
        return $togglTask;
    }

    public function updateTask($id, $content, $omg = false){
        $toggl_client = $this->toggl_connect($omg);
        $task = $toggl_client->updateTask(['id' => $id, 'task'=> $content]);
        $request = new Request();
        $this->import($request, $omg);
        $togglTask = TogglTask::where('toggl_id', $task->id)->first();
        return $togglTask;
    }
}
