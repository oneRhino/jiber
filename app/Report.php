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

class Report extends MainModel
{
    /**
     * Report has many Toggl TimeEntries
     */
    public function toggl_entries()
    {
        return $this->hasMany('App\TimeEntry', 'report_id')
            ->select(array(
                'toggl_time_entries.id',
                'toggl_time_entries.project_id',
                'toggl_time_entries.task_id',
                'time_entries.description',
                'time_entries.date_time',
                'time_entries.duration',
                'time_entries.redmine_issue_id',
                'time_entries.jira_issue_id',
                'toggl_projects.name as project_name',
                'toggl_tasks.name as task_name',
            ))
            ->leftJoin('toggl_time_entries', 'time_entries.id', '=', 'toggl_time_entries.id')
            ->leftJoin('toggl_projects'    , 'project_id'     , '=', 'toggl_projects.id')
            ->leftJoin('toggl_tasks'       , 'task_id'        , '=', 'toggl_tasks.id')
            ->orderBy('date_time')
            ->orderBy('project_name')
            ->orderBy('redmine_issue_id')
            ->orderBy('duration');
    }

    /**
     * Report has many Redmine TimeEntries
     */
    public function redmine_entries()
    {
        return $this->hasMany('App\TimeEntry', 'report_id')
            ->select(array(
                'id',
                'description',
                'date_time',
                'duration',
                'redmine_issue_id',
                'jira_issue_id',
            ))
            ->orderBy('date_time')
            ->orderBy('redmine_issue_id')
            ->orderBy('duration');
    }

    /**
     * Report has many TimeEntries
     */
    public function entries()
    {
        return $this->hasMany('App\TimeEntry', 'report_id')
            ->select(array(
                'id',
                'description',
                'date_time',
                'duration',
                'redmine_issue_id',
                'jira_issue_id',
            ))
            ->orderBy('date_time')
            ->orderBy('redmine_issue_id')
            ->orderBy('duration');
    }

    public function getTimeEntries()
    {
        return $this->entries()->get();
    }

    /**
     * Get all entries that have Redmine field filled
     */
    public function getAllRedmine($user_id)
    {
        return $this->toggl_entries()->whereNotNull('redmine_issue_id')->get();
    }

    /**
     * Check if report has been sent to Jira or Redmine,
     * if so, it shouldn't be deleted
     */
    public function canDelete()
    {
        $count = RedmineSent::where('report_id', $this->id)->count();
        if ($count > 0) {
            return false;
        }

        $count = JiraSent::where('report_id', $this->id)->count();
        if ($count > 0) {
            return false;
        }

        return true;
    }

    /**
     * Report has one TogglReport
     */
    public function toggl_report()
    {
        return $this->hasOne('App\TogglReport', 'id');
    }
}
