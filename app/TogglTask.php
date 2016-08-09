<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TogglTask extends Toggl
{
	/**
	 * TogglTask belongs to TogglWorkspace
	 */
	public function workspace()
	{
		return $this->belongsTo('App\TogglWorkspace');
	}

	/**
	 * TogglTask belongs to TogglProject
	 */
	public function project()
	{
		return $this->belongsTo('App\TogglProject');
	}

	/**
	 * Format Estimated attribute - H:M:S
	 */
	public function getEstimatedAttribute($seconds)
	{
		if (!$seconds) return null;

		$minutes = floor($seconds / 60);
		$hours   = floor($minutes / 60);
		$seconds = $seconds % 60;
		$minutes = $minutes % 60;

		$format = '%u:%02u:%02u';
		$time   = sprintf($format, $hours, $minutes, $seconds);

		return $time;
	}

	/**
	 * Format Tracked attribute - H:M:S
	 */
	public function getTrackedAttribute($seconds)
	{
		if (!$seconds) return null;

		$minutes = floor($seconds / 60);
		$hours   = floor($minutes / 60);
		$seconds = $seconds % 60;
		$minutes = $minutes % 60;

		$format = '%u:%02u:%02u';
		$time   = sprintf($format, $hours, $minutes, $seconds);

		return $time;
	}

	/**
	 * Checks if it has been spent more time than estimated
	 * - No estimated time, returns 'active' (gray row)
	 * - No tracked time, returns 'warning' (pink row)
	 * - More tracked time than Estimated, returns 'danger' (red row)
	 * - More Estimated time than Tracked, returns 'success' (green row)
	 */
	public function getExceededAttribute()
	{
		if (!$this->estimated)                 return 'active';
		if (!$this->tracked)                   return 'warning';
		if ($this->estimated < $this->tracked) return 'danger';

		return 'success';
	}
}
