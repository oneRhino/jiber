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
use App\TogglClient;
use App\JiraSent;
use App\RedmineSent;

class TogglReport extends Toggl
{
	/**
	 * TogglReport has many TogglTimeEntries
	 */
  public function entries()
  {
    return $this->hasMany('App\TogglTimeEntry', 'report_id')
			->select(array('toggl_time_entries.id','toggl_time_entries.project_id','toggl_time_entries.task_id','toggl_time_entries.description','toggl_time_entries.date','toggl_time_entries.time','toggl_time_entries.duration','toggl_time_entries.redmine','toggl_time_entries.jira','toggl_projects.name as project_name','toggl_tasks.name as task_name'))
			->leftJoin('toggl_projects', 'project_id', '=', 'toggl_projects.id')
			->leftJoin('toggl_tasks', 'task_id', '=', 'toggl_tasks.id')
			->orderBy('date')
			->orderBy('project_name')
			->orderBy('redmine')
			->orderBy('duration');
	}

	/**
	 * Check if report has been sent to Jira or Redmine,
	 * if so, it shouldn't be deleted
	 */
	public function canDelete()
	{
		$count = RedmineSent::where('report_id', $this->id)->count();
		if ($count > 0) return false;

		$count = JiraSent::where('report_id', $this->id)->count();
		if ($count > 0) return false;

		return true;
	}

	public function getTimeEntries()
	{
		return $this->entries()
			->get();
  }

	/**
	 * Get all entries that have Redmine field filled
	 */
	public function getAllRedmine($user_id)
	{
		return $this->entries()->whereNotNull('redmine')->get();
	}

	/**
	 * Create a string with TogglClient names
	 * based on client_ids field
	 */
  public function getClientsAttribute()
  {
		if (!$this->client_ids) return false;

    $clients = explode(',', $this->client_ids);

		$clients = TogglClient::whereIn('toggl_id', $clients)->where('user_id', $this->user_id)->distinct()->get();

    $return = array();

    foreach ($clients as $_client)
    {
			$return[] = $_client->name;
    }

    return implode(', ', $return);
  }

	/**
	 * Create an array with TogglProject names
	 * based on project_ids field
	 */
  public function getProjectsAttribute()
  {
		if (!$this->project_ids) return false;

    $projects = explode(',', $this->project_ids);

		$projects = TogglProject::whereIn('toggl_id', $projects)->where('user_id', $this->user_id)->distinct()->get();

    $return = array();

    foreach ($projects as $_project)
    {
			$return[] = $_project->name;
    }

    return implode(', ', $return);
  }
}
