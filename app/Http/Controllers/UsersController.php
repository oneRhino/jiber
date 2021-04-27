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
 * Manage user's settings, like Toggl token, Redmine
 * token, Jira and Basecamp username
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 28, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Setting;
use App\TogglReport;
use App\TogglWorkspace;
use App\TogglClient;
use Crypt;

class UsersController extends Controller
{
    /**
     * Get setting record from DB, save it, and
     * send to template
     * Accept GET and POST calls
     */
    public function settings(Request $request)
    {
        $setting = Setting::find(Auth::user()->id);

        if (!$setting) {
            $setting     = new Setting();
            $setting->id = Auth::user()->id;
            $setting->save();
        }

        if ($request->isMethod('post')) {
            $toggl_redmine_data = null;

            if ($request->toggl_redmine_sync) {
                $toggl_redmine_data = array(
                    'workspace' => $request->workspace,
                    'clients'   => $request->clients,
                    'projects'  => $request->projects,
                );
            }

            $setting->id                 = Auth::user()->id;
            $setting->toggl              = $request->toggl;
            $setting->redmine            = $request->redmine;
            $setting->redmine_user       = $request->redmine_user;
            $setting->jira               = $request->jira;
            $setting->jira_password      = $request->jira_password;
            $setting->basecamp           = $request->basecamp;
            $setting->toggl_redmine_sync = $request->daily_sync == 'toggl_redmine_sync' ? 1 : 0;
            $setting->redmine_toggl_sync = $request->daily_sync == 'redmine_toggl_sync' ? 1 : 0;
            if ($toggl_redmine_data) {
                $setting->toggl_redmine_data = serialize($toggl_redmine_data);
            }
            $setting->save();
            $request->session()->flash('alert-success', 'Settings successfully saved.');
        }

        // These variables have three states:
        // -1: undefined
        //  0: error while connecting
        //  1: connected successfully
        $toggl = $redmine = $jira = $basecamp = -1;

        if ($setting->toggl) {
            $toggl   = app('App\Http\Controllers\TogglController')  ->test($request);
        }

        if ($setting->redmine) {
            $redmine = app('App\Http\Controllers\RedmineController')->test($request);
        }

        if ($setting->jira) {
            $jira    = app('App\Http\Controllers\JiraController')   ->test($request);
        }

        $reports    =    TogglReport::getAllByUserID(Auth::user()->id, 'toggl_reports.id', 'DESC');
        $workspaces = TogglWorkspace::getAllByUserID(Auth::user()->id);
        $clients    =    TogglClient::getAllByUserID(Auth::user()->id);

        return view('users.settings', [
            'setting'  => $setting,
            'toggl'    => $toggl,
            'redmine'  => $redmine,
            'jira'     => $jira,
            'basecamp' => $basecamp,
            'reports'    => $reports,
            'workspaces' => $workspaces,
            'clients'    => $clients,
        ]);
    }
}
