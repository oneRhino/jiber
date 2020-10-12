<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClubhouseStory extends Model
{
	/**
	 * Get the Toggl Tag related to this Story.
	 */
	public function toggl_task()
	{
		return $this->belongsTo('App\TogglTask');
	}
}
