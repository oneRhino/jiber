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
	 * Format: H.MMMMMMMM
	 */
	public function getDecimalDurationAttribute()
	{
		$round_to = 5 * 60;
		$milliseconds = $this->attributes['duration'];
		$seconds      = floor($milliseconds / 1000);

		if ($seconds % $round_to != 0)
			$seconds = $seconds + ($round_to - ($seconds % $round_to));

		return $seconds / 3600;
	}

	/**
	 * Return duration, rounded up to 5 minutes, and 2 digits after decimal point
	 * Format: H.MM
	 */
	public function getRoundDecimalDurationAttribute()
	{
		$time = $this->getDecimalDurationAttribute();

		return round($time, 2);
	}

	/**
	 * Return duration in hour:minutes:seconds format
	 * Format: H:MM:SS
	 */
	public function getHourDurationAttribute()
	{
		$milliseconds = $this->attributes['duration'];
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
