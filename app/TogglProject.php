<?php

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
