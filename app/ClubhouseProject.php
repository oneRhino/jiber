<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClubhouseProject extends Model
{
    /**
     * ClubhouseProject belongs to TogglProject
     */
    public function togglProject()
    {
        return $this->hasOne('App\TogglProject', 'clubhouse_id');
    }

    public static function projectExists ($clubhouse_name)
    {
        if (self::where('clubhouse_name', $clubhouse_name)->count() > 0)
            return true;

        return false;
    }
}
