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

use Illuminate\Database\Eloquent\Model;

class TogglProject extends Toggl
{
	/**
	 * TogglProject belongs to TogglWorkspace
	 */
	public function workspace()
	{
		return $this->belongsTo('App\TogglWorkspace');
	}

	/**
	 * TogglProject belongs to TogglClient
	 */
	public function client()
	{
		return $this->belongsTo('App\TogglClient');
	}

	/**
	 * Get records based on User ID
	 */
	public static function getAllByUserID($user_id, $orderBy = 'name', $sort = 'ASC')
	{
		return self::where(array('toggl_projects.user_id'  => $user_id))
			->leftJoin('toggl_workspaces', 'workspace_id', '=', 'toggl_workspaces.id')
			->leftJoin('toggl_clients', 'client_id', '=', 'toggl_clients.id')
			->select(array('toggl_projects.id','toggl_projects.name','toggl_projects.active','toggl_workspaces.name as workspace_name','toggl_clients.name as client_name'))
			->orderBy('client_name')
			->orderBy('name')
			->get();
	}
}
