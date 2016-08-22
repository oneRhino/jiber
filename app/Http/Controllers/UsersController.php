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
use App\Setting;

class UsersController extends Controller
{
    /**
     * Get setting record from DB, save it, and
     * send to template
     * Accept GET and POST calls
     */
    public function settings(Request $request)
    {
        $setting = Setting::find($request->user()->id);

        if (!$setting) {
            $setting     = new Setting();
            $setting->id = $request->user()->id;
            $setting->save();
        }

        if ($request->isMethod('post')) {
            $setting->id       = $request->user()->id;
            $setting->toggl    = $request->toggl;
            $setting->redmine  = $request->redmine;
            $setting->jira     = $request->jira;
            $setting->basecamp = $request->basecamp;
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

        return view('users.settings', [
            'setting'  => $setting,
            'toggl'    => $toggl,
            'redmine'  => $redmine,
            'jira'     => $jira,
            'basecamp' => $basecamp,
        ]);
    }
}
