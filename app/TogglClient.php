<?php

/**
 * Manage Toggl Clients
 */

namespace App;

use Illuminate\Database\Eloquent\Model;

class TogglClient extends Toggl
{
	/**
	 * Get OneRhino Toggl Client record, based on User ID
	 */
	public function getOneRhinoID($user_id)
	{
    return self::where(array(
			'name'    => 'OneRhino',
			'user_id' => $user_id
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
			->select(array('toggl_clients.id','toggl_clients.name','toggl_clients.toggl_id','toggl_workspaces.name as workspace_name'))
			->orderBy('name')
			->get();
	}
}
