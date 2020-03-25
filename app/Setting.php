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

class Setting extends MainModel
{
    public function user()
    {
        return $this->belongsTo('App\User', 'id');
    }

    public function equalTo($toggl_section, $value, $return) {
        $toggl_redmine_data = unserialize($this->toggl_redmine_data);

        if ($toggl_redmine_data && isset($toggl_redmine_data[$toggl_section]) && $toggl_redmine_data[$toggl_section]) {
            if (is_array($toggl_redmine_data[$toggl_section]))
                return (in_array($value, $toggl_redmine_data[$toggl_section]) ? $return : false);
            else
                return ($value == $toggl_redmine_data[$toggl_section] ? $return : false);
        }
    }

    public static function getAllWithRedmine()
    {
        return self::join('users', function($join) {
            $join->on('settings.id', 'users.id')->whereNotNull('redmine');
        })->where('users.enabled', true)->get();
    }
}
