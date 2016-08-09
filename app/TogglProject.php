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
}
