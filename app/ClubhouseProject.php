<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClubhouseProject extends Model
{
    public static function projectExists ($clubhouse_name)
    {
        if (self::where('clubhouse_name', $clubhouse_name)->count() > 0)
            return true;

        return false;
    }
}
