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
}
