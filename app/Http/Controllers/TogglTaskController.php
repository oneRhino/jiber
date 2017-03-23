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
    public function index()
    {
        $tasks = TogglTask::getAllByUserID(Auth::user()->id, 'project_id');

        return view('toggl_task.index', [
            'tasks' => $tasks,
        ]);
    }

    /**
     * Import tasks from Toggl
     */
    public function import(Request $request)
    {
        $toggl_client = $this->toggl_connect();

        $workspaces = TogglWorkspace::getAllByUserID(Auth::user()->id);

        foreach ($workspaces as $_workspace) {
            $tasks = $toggl_client->GetWorkspaceTasks(array('id' => (int)$_workspace->toggl_id, 'active' => 'both'));

            if ($tasks) {
                foreach ($tasks as $_task) {
                    $task      =      TogglTask::getByTogglID($_task['id'] , Auth::user()->id);
                    $workspace = TogglWorkspace::getByTogglID($_task['wid'], Auth::user()->id);
                    $project   =   TogglProject::getByTogglID($_task['pid'], Auth::user()->id);

                    if (!$workspace || !$project) {
                        continue;
                    }

                    if (!$task) {
                        $task           = new TogglTask();
                        $task->user_id  = Auth::user()->id;
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
}
