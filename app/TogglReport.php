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

namespace App;

class TogglReport extends Report
{
    public $timestamps = false;

    /**
     * TogglReport has one Report
     */
    public function report()
    {
        return $this->hasOne('App\Report', 'id');
    }

    /**
     * Create a string with TogglClient names
     * based on client_ids field
     */
    public function getClientsAttribute()
    {
        if (!$this->client_ids) {
            return false;
        }

        $clients = explode(',', $this->client_ids);

        $clients = TogglClient::whereIn('toggl_id', $clients)->where('user_id', $this->report->user_id)->distinct()->get();

        $return = array();

        foreach ($clients as $_client) {
            $return[] = $_client->name;
        }

        return implode(', ', $return);
    }

    /**
     * Create an array with TogglProject names
     * based on project_ids field
     */
    public function getProjectsAttribute()
    {
        if (!$this->project_ids) {
            return false;
        }

        $projects = explode(',', $this->project_ids);

        $projects = TogglProject::whereIn('toggl_id', $projects)->where('user_id', $this->report->user_id)->distinct()->get();

        $return = array();

        foreach ($projects as $_project) {
            $return[] = $_project->name;
        }

        return implode(', ', $return);
    }

    /**
     * Get records based on User ID
     */
    public static function getAllByUserID($user_id, $orderBy = 'name', $sort = 'ASC')
    {
        return self::where(array(
            'user_id'  => $user_id,
        ))
        ->leftJoin('reports', 'reports.id', '=', 'toggl_reports.id')
        ->orderBy($orderBy, $sort)->get();
    }
}
