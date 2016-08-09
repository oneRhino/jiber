<?php

/**
 * List and import Toggl Workspaces
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 29, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\TogglWorkspace;

use App\Http\Requests;

class TogglWorkspaceController extends TogglController
{
	/**
	 * List workspaces saved on system
	 */
  function index(Request $request)
  {
    $workspaces = TogglWorkspace::getAllByUserID($request->user()->id);

    return view('toggl_workspace.index', [
      'workspaces' => $workspaces
    ]);
  }

	/**
	 * Import workspaces from Toggl
	 */
  function import(Request $request)
  {
    $toggl_client = $this->toggl_connect($request);

    $workspaces = $toggl_client->getWorkspaces(array());

    if ($workspaces)
    {
      foreach ($workspaces as $_workspace)
      {
				$workspace = TogglWorkspace::where(array(
					'toggl_id' => $_workspace['id'], 
					'user_id'  => $request->user()->id
				))->get()->first();

				if (!$workspace)
				{
	        $workspace = new TogglWorkspace;
  	      $workspace->user_id  = $request->user()->id;
  	      $workspace->toggl_id = $_workspace['id'];
				}

				$workspace->name = $_workspace['name'];
				$workspace->save();

				sleep(1); // Toggl only allows 1 request per second
      }

      $request->session()->flash('alert-success', 'All workspaces have been successfully imported!');
    }

    return back()->withInput();
  }
}
