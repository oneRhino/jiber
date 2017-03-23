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
 * List and import Toggl Clients
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 29, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\TogglClient;
use App\TogglWorkspace;

class TogglClientController extends TogglController
{
    /**
     * List clients saved on system
     */
    public function index()
    {
        $clients = TogglClient::getAllByUserID(Auth::user()->id);

        return view('toggl_client.index', [
            'clients' => $clients,
        ]);
    }

    /**
     * Import clients from Toggl
     */
    public function import(Request $request)
    {
        // Connect into Toggl
        $toggl_client = $this->toggl_connect();

        // Get all clients from Toggl
        $clients = $toggl_client->getClients(array());

        if ($clients) {
            foreach ($clients as $_client) {
                // Check if client already exists - if so, only update information
                $client = TogglClient::getByTogglID($_client['id'], Auth::user()->id);

                if (!$client) {
                    $client           = new TogglClient();
                    $client->toggl_id = $_client['id'];
                    $client->user_id  = Auth::user()->id;
                }

                $client->workspace_id = TogglWorkspace::getByTogglID($_client['wid'], Auth::user()->id)->id;
                $client->name         = $_client['name'];
                $client->save();
            }

            $request->session()->flash('alert-success', 'All clients have been successfully imported!');
        }

        return back()->withInput();
    }
}
