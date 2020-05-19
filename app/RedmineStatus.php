<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RedmineStatus extends Model
{
    public function getClubhouseNameAttribute($value) {
        $id = $this->attributes['clubhouse_main_id'];

        if (!$id) return '';

        $statusAsArray = json_decode($id, TRUE);

        // Gets the name of the first status.
        if (is_array($statusAsArray))
            $id = $statusAsArray[0];

        $status = ClubhouseStatus::where('clubhouse_id', $id)->first();

        return $status->clubhouse_name;
    }

    public function getClubhouseIdsAttribute($value) {
        $id = $this->attributes['clubhouse_id'];

        if (!$id) return [];

        $ids = json_decode($id);

        return $ids;
    }

    public function getClubhouseMainIdAttribute($value) {
        $id = $this->attributes['clubhouse_main_id'];

        if (!$id) return [];

        $ids = json_decode($id);

        // This field used to be a string.
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        return $ids;
    }
}
