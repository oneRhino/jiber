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
 * List and import Toggl Workspaces
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 29, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\TogglWorkspace;

class TogglWorkspaceController extends TogglController
{
    /**
     * List workspaces saved on system
     */
    public function index(Request $request)
    {
        $workspaces = TogglWorkspace::getAllByUserID($request->user()->id);

        return view('toggl_workspace.index', [
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * Import workspaces from Toggl
     */
    public function import(Request $request)
    {
        $toggl_client = $this->toggl_connect($request);

        $workspaces = $toggl_client->getWorkspaces(array());

        if ($workspaces) {
            foreach ($workspaces as $_workspace) {
                $workspace = TogglWorkspace::where(array(
                    'toggl_id' => $_workspace['id'], 
                    'user_id'  => $request->user()->id,
                ))->get()->first();

                if (!$workspace) {
                    $workspace           = new TogglWorkspace();
                    $workspace->user_id  = $request->user()->id;
                    $workspace->toggl_id = $_workspace['id'];
                }

                $workspace->name = $_workspace['name'];
                $workspace->save();
            }

            $request->session()->flash('alert-success', 'All workspaces have been successfully imported!');
        }

        return back()->withInput();
    }
}
