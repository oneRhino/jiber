<?php

/**
 * List and import Toggl Tasks
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 29, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\TogglTask;
use App\TogglWorkspace;
use App\TogglProject;

use App\Http\Requests;

class TogglTaskController extends TogglController
{
	/**
	 * List tasks saved on system
	 */
  function index(Request $request)
  {
    $tasks = TogglTask::getAllByUserID($request->user()->id, 'project_id');

    return view('toggl_task.index', [
      'tasks' => $tasks
    ]);
  }

	/**
	 * Import tasks from Toggl
	 */
  function import(Request $request)
  {
    $toggl_client = $this->toggl_connect($request);

		$workspaces = TogglWorkspace::getAllByUserID($request->user()->id);

		foreach ($workspaces as $_workspace)
		{
	    $tasks = $toggl_client->GetWorkspaceTasks(array('id' => (int)$_workspace->toggl_id, 'active' => 'both'));

			if ($tasks)
			{
				foreach ($tasks as $_task)
				{
					$task      = TogglTask::getByTogglID($_task['id'], $request->user()->id);
					$workspace = TogglWorkspace::getByTogglID($_task['wid'], $request->user()->id);
					$project   = TogglProject::getByTogglID($_task['pid'], $request->user()->id);

					if (!$workspace || !$project) continue;

					if (!$task)
					{
						$task = new TogglTask;
  	      	$task->user_id  = $request->user()->id;
						$task->toggl_id = $_task['id'];
					}

					$task->workspace_id = $workspace->id;
					$task->project_id   = $project->id;
					$task->active       = $_task['active'];
					$task->estimated    = $_task['estimated_seconds'];
					if (isset($_task['tracked_seconds']))
						$task->tracked    = $_task['tracked_seconds'];
					$task->name         = $_task['name'];
					$task->save();

					sleep(1); // Toggl only allows 1 request per second
				}
			}
    }

		$request->session()->flash('alert-success', 'All tasks have been successfully imported!');

    return back()->withInput();
  }
}
