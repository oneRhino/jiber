<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TogglWorkspace extends Toggl
{
	/**
	 * TogglWorkspace has many TogglClients
	 */
  public function clients()
  {
    return $this->hasMany('App\TogglClient','workspace_id')->orderBy('name');
  }

	/**
	 * TogglWorkspace has many TogglProjects
	 */
  public function projects()
  {
    return $this->hasMany('App\TogglProject','workspace_id')->orderBy('name');
  }

	/**
	 * TogglWorkspace has many TogglTasks
	 */
  public function tasks()
  {
    return $this->hasMany('App\TogglTask','workspace_id')->orderBy('name');
  }
}
