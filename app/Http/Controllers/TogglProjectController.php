<?php

/**
 * List and import Toggl Projects
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 29, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\TogglProject;
use App\TogglWorkspace;
use App\TogglClient;

use App\Http\Requests;

class TogglProjectController extends TogglController
{
	/**
	 * List projects saved on system
	 */
  function index(Request $request)
  {
    $projects = TogglProject::getAllByUserID($request->user()->id);

    return view('toggl_project.index', [
      'projects' => $projects
    ]);
  }

	/**
	 * Import projects from Toggl
	 */
  function import(Request $request)
  {
    $toggl_client = $this->toggl_connect($request);

		$workspaces = TogglWorkspace::getAllByUserID($request->user()->id);

		foreach ($workspaces as $_workspace)
		{
	    $projects = $toggl_client->GetWorkspaceProjects(array('id' => (int)$_workspace->toggl_id, 'active' => 'both'));

			if ($projects)
			{
				foreach ($projects as $_project)
				{
					$project = TogglProject::getByTogglID($_project['id'], $request->user()->id);

					if (!$project)
					{
						$project = new TogglProject;
  	      	$project->user_id  = $request->user()->id;
						$project->toggl_id = $_project['id'];
					}

					$project->workspace_id = TogglWorkspace::getByTogglID($_project['wid'], $request->user()->id)->id;
					if (isset($_project['cid']))
						$project->client_id  = TogglClient::getByTogglID($_project['cid'], $request->user()->id)->id;
					$project->active       = $_project['active'];
					$project->name         = $_project['name'];
					$project->save();
				}
			}

			sleep(1); // Toggl only allows 1 request per second
    }

		$request->session()->flash('alert-success', 'All projects have been successfully imported!');

    return back()->withInput();
  }
}
