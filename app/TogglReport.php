<?php

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
			->orderBy('description');
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
		return $this->entries()->whereNotNull('redmine')->orderBy('redmine')->orderBy('duration')->get();
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
