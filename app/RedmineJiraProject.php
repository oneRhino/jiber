<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RedmineJiraProject extends Model
{
    public static function projectExists($redmine_name)
    {
        if (self::where('redmine_name', $redmine_name)->count() > 0)
            return true;

        return false;
    }
}
