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
 * Control connection with Toggl, using Toggl Client and Reports Client
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 28, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

use App\Http\Requests;
use Illuminate\Support\Facades\Config;
use AJT\Toggl\TogglClient;
use AJT\Toggl\ReportsClient;
use App\Setting;

class TogglController extends Controller
{
	/**
	 * Check if Toggl API Token has been set on Settings
	 */
	function __construct(Request $request, Redirector $redirect)
	{
		$setting = Setting::find($request->user()->id);

		if (!$setting || !$setting->toggl)
		{
			$request->session()->flash('alert-warning', 'Please set your Toggl API Token before importing data.');
			$redirect->to('/settings')->send();
		}
	}

  /**
	 * Connect into Toggl API
	 */
  function toggl_connect(Request $request)
  {
		$setting = Setting::find($request->user()->id);
    $token   = $setting->toggl;
    $client  = TogglClient::factory(array('api_key' => $token));

    return $client;
  }

	/**
   * Connect into Reports API
	 */
  function reports_connect(Request $request)
  {
    $token  = Setting::find($request->user()->id)->toggl;
    $client = ReportsClient::factory(array('api_key' => $token));

    return $client;
  }

	/**
	 * Test Toggl connection
	 */
	function test(Request $request)
	{
		try {
			$client = $this->toggl_connect($request);
			$me = $client->GetCurrentUser();
		}
		catch(\Exception $e) {
			return false;
		}

		return true;
	}

	/**
	 * Import everything - workspaces, clients, projects and tasks
	 */
	function import(Request $request)
	{
		app('App\Http\Controllers\TogglWorkspaceController')->import($request);
		app('App\Http\Controllers\TogglClientController')   ->import($request);
		app('App\Http\Controllers\TogglProjectController')  ->import($request);
		app('App\Http\Controllers\TogglTaskController')     ->import($request);

		$request->session()->flash('alert-success', 'All your Toggl data have been imported successfully!');
		return redirect()->action('TogglWorkspaceController@index');
	}
}
