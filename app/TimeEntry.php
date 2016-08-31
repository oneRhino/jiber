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

class TimeEntry extends MainModel
{
    // Up to how many minutes should we round duration?
    private $duration_round = 5;

    public function getDateAttribute()
    {
        $date_time = $this->attributes['date_time'];

        return date('Y-m-d', strtotime($date_time));
    }

    /**
     * Return duration, rounded up
     * Format: H.MMMMMMMM
     */
    public function getDecimalDurationAttribute()
    {
        $duration     = $this->attributes['duration'];
        $round_to     = $this->duration_round * 60;
        $milliseconds = $duration;
        $seconds      = floor($milliseconds / 1000);

        if ($seconds % $round_to != 0) {
            $seconds = $seconds + ($round_to - ($seconds % $round_to));
        }

        return $seconds / 3600;
    }

    /**
     * Return duration, rounded up, and 2 digits after decimal point
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
        $duration = $this->attributes['duration'];

        $milliseconds = $duration;
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
     * Checks if entry is a Jira entry,
     * based on jira field, or getting Redmine Jira ID custom
     * field and returning it
     * @return Jira ID or false if none found
     */
    public function isJira()
    {
        if ($this->jira_issue_id) {
            return true;
        }

        // Connect into Redmine
        $redmine = app('App\Http\Controllers\RedmineController')->connect();

        $details = $redmine->issue->show($this->redmine_issue_id);

        if (isset($details['issue']['custom_fields']) && is_array($details['issue']['custom_fields'])) {
            foreach ($details['issue']['custom_fields'] as $_array) {
                if (isset($_array['name']) && $_array['name'] == 'Jira ID' && !empty($_array['value'])) {
                    return $_array['value'];
                }
            }
        }

        return false;
    }

    /**
     * Checks if entry is a Redmine entry,
     * based on Redmine field, or comparing client_id
     * with OneRhino client id (from TogglClient)
     * @return Redmine ID or false if none found
     */
    public function isRedmine()
    {
        if ($this->redmine_issue_id) {
            return true;
        }

        return $this->getRedmineIssueID();
    }

    /**
     * Tries to match a Redmine ID within task description
     * @return Redmine ID or false if none found
     */
    public function getRedmineIssueID()
    {
        preg_match('/#([0-9]+)/', $this->description, $matches);

        if (isset($matches[1])) {
            return (int)$matches[1];
        }

        return false;
    }
}
