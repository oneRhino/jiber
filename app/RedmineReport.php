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

class RedmineReport extends MainModel
{
    public $timestamps = false;

    /**
     * TogglReport has many TogglTimeEntries
     */
    public function entries()
    {
        return $this->hasMany('App\RedmineTimeEntry', 'report_id')
            ->orderBy('date')
            ->orderBy('duration');
    }

    /**
     * Check if report has been sent to Jira,
     * if so, it shouldn't be deleted
     */
    public function canDelete()
    {
        $count = JiraSent::where('report_id', $this->id)->count();

        if ($count > 0) {
            return false;
        }

        return true;
    }

    /**
     * Get records based on User ID
     */
    public static function getAllByUserID($user_id, $orderBy = 'name', $sort = 'ASC')
    {
        return self::where(array(
            'user_id'  => $user_id,
        ))
        ->leftJoin('reports', 'reports.id', '=', 'redmine_reports.id')
        ->orderBy($orderBy, $sort)->get();
    }
}
