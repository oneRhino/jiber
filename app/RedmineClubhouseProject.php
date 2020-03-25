<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RedmineClubhouseProject extends Model
{
    public static function projectExists ($clubhouse_name)
    {
        if (self::where('redmine_name', $clubhouse_name)->count() > 0)
            return true;

        return false;
    }
}
