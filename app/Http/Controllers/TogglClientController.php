<?php

/**
 * List and import Toggl Clients
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 29, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\TogglClient;
use App\TogglWorkspace;

class TogglClientController extends TogglController
{
	/**
	 * List clients saved on system
	 */
  function index(Request $request)
  {
    $clients = TogglClient::getAllByUserID($request->user()->id);

    return view('toggl_client.index', [
      'clients' => $clients
    ]);
  }

	/**
	 * Import clients from Toggl
	 */
  function import(Request $request)
  {
		// Connect into Toggl
    $toggl_client = $this->toggl_connect($request);

		// Get all clients from Toggl
    $clients = $toggl_client->getClients(array());

    if ($clients)
    {
      foreach ($clients as $_client)
      {
				// Check if client already exists - if so, only update information
				$client = TogglClient::getByTogglID($_client['id'], $request->user()->id);

				if (!$client)
				{
	        $client = new TogglClient;
  	      $client->toggl_id = $_client['id'];
  	      $client->user_id  = $request->user()->id;
				}

				$client->workspace_id = TogglWorkspace::getByTogglID($_client['wid'], $request->user()->id)->id;
				$client->name         = $_client['name'];
				$client->save();
      }

      $request->session()->flash('alert-success', 'All clients have been successfully imported!');
    }

    return back()->withInput();
  }
}
