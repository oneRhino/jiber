<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TogglTimeEntry extends Toggl
{
	/**
	 * TogglTimeEntry belongs to a TogglProject
	 */
	public function project()
	{
		return $this->belongsTo('App\TogglProject');
	}

	/**
	 * TogglTimeEntry belongs to a TogglTask
	 */
	public function task()
	{
		return $this->belongsTo('App\TogglTask');
	}

	/**
	 * Return duration, rounded up to 5 minutes
	 * Format: H.MM
	 */
	public function getRoundDurationAttribute()
	{
		$round_to = 5 * 60;

		$milliseconds = $this->attributes['duration'];

		$seconds      = floor($milliseconds / 1000);
		$minutes      = floor($seconds / 60);
		$hours        = floor($minutes / 60);
		$milliseconds = $milliseconds % 1000;
		$seconds      = $seconds % 60;
		$minutes      = $minutes % 60;

		$seconds = $minutes * 60 + $seconds;

		if ($seconds % $round_to != 0)
			$seconds = $seconds + ($round_to - ($seconds % $round_to));

		$time = $hours + ($seconds / 3600);
		$time = round($time, 2);

		return $time;
	}

	/**
	 * Return duration in different format
	 * Format: H:MM:SS
	 */
	public function getDurationAttribute($milliseconds)
	{
		$seconds      = floor($milliseconds / 1000);
		$minutes      = floor($seconds / 60);
		$hours        = floor($minutes / 60);
		$milliseconds = $milliseconds % 1000;
		$seconds      = $seconds % 60;
		$minutes      = $minutes % 60;

		$format = '%u:%02u:%02u';
		$time   = sprintf($format, $hours, $minutes, $seconds);

		return $time;
	}

	/**
	 * Tries to match a Redmine ID within task description
	 * @return Redmine ID or false if none found
	 */
	public function getRedmineTaskID()
	{
		preg_match('/#([0-9]+)/', $this->description, $matches);
		if (isset($matches[1]))
	    return (int)$matches[1];

		return false;
	}

	/**
	 * Checks if entry is a Redmine entry,
	 * based on Redmine field, or comparing client_id
	 * with OneRhino client id (from TogglClient)
	 * @return Redmine ID or false if none found
	 */
	public function isRedmine($user_id)
	{
		if ($this->redmine) return true;

		$onerhino = app('App\TogglClient')->getOneRhinoID($user_id);

		if ($onerhino === $this->client_id)
			return $this->getRedmineTaskID();

		return false;
	}

	/**
	 * Checks if entry is a Jira entry,
	 * based on jira field, or getting Redmine Jira ID custom
	 * field and returning it
	 * @return Jira ID or false if none found
	 */
	public function isJira()
	{
		if ($this->jira) return true;

		// Connect into Redmine
		$redmine = app('App\Http\Controllers\RedmineController')->connect();

		$details = $redmine->issue->show($this->redmine);

		if (isset($details['issue']['custom_fields']) && is_array($details['issue']['custom_fields']))
		{
			foreach ($details['issue']['custom_fields'] as $_array)
			{
				if (isset($_array['name']) && $_array['name'] == 'Jira ID' && !empty($_array['value']))
				{
					return $_array['value'];
				}
			}
		}

		return false;
	}
}
