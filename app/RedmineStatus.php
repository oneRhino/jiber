<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RedmineStatus extends Model
{
    public function getClubhouseNameAttribute($value) {
        $id = $this->attributes['clubhouse_main_id'];

        if (!$id) return '';

        $status = ClubhouseStatus::where('clubhouse_id', $id)->first();

        return $status->clubhouse_name;
    }

    public function getClubhouseIdsAttribute($value) {
        $id = $this->attributes['clubhouse_id'];

        if (!$id) return [];

        $ids = json_decode($id);

        return $ids;
    }
}
