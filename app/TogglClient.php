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
 * Manage Toggl Clients
 */

namespace App;

class TogglClient extends Toggl
{
    /**
     * Get OneRhino Toggl Client record, based on User ID
     */
    public function getOneRhinoID($user_id)
    {
        return self::where(array(
            'name'    => 'OneRhino',
            'user_id' => $user_id,
        ))->first()->id;
    }

    /**
     * TogglClient belongs to TogglWorkspace
     */
    public function workspace()
    {
        return $this->belongsTo('App\TogglWorkspace');
    }

    /**
     * Get records based on User ID
     */
    public static function getAllByUserID($user_id, $orderBy = 'name', $sort = 'ASC')
    {
        return self::where(array('toggl_clients.user_id'  => $user_id))
            ->leftJoin('toggl_workspaces', 'workspace_id', '=', 'toggl_workspaces.id')
            ->select(array('toggl_clients.id', 'toggl_clients.name', 'toggl_clients.toggl_id', 'toggl_workspaces.name as workspace_name'))
            ->orderBy('name')
            ->get();
    }
}
