<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\TogglClient;

class TogglReport extends Toggl
{
	/**
	 * TogglReport has many TogglTimeEntries
	 */
  public function entries()
  {
    return $this->hasMany('App\TogglTimeEntry', 'report_id');
  }

	/**
	 * Get all entries that have Redmine field filled
	 */
	public function getAllRedmine($user_id)
	{
		return $this->entries()->whereNotNull('redmine')->orderBy('redmine')->orderBy('duration')->get();
	}

	/**
	 * Create an array with TogglClient names
	 * based on client_ids field
	 */
  public function getClientsAttribute()
  {
    $clients = explode(',', $this->client_ids);

    $return = array();

    foreach ($clients as $_id)
    {
      $_client = TogglClient::getByTogglID($_id, $this->user_id);
      if ($_client)
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
    $projects = explode(',', $this->project_ids);

    $return = array();

    foreach ($projects as $_id)
    {
      $_project = TogglProject::getByTogglID($_id, $this->user_id);
      if ($_project)
        $return[] = $_project->name;
    }

    return implode(', ', $return);
  }
}
