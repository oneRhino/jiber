<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RedmineStatus extends Model
{
    public function getClubhouseNameAttribute($value) {
        $ids = json_decode($value, true);

        if (!$ids) return '';

        $statuses = ClubhouseStatus::whereIn('clubhouse_id', $ids)->get();

        return $statuses->implode('clubhouse_name', ', ');
    }

    public function getClubhouseIdsAttribute() {
        $ids = json_decode($this->attributes['clubhouse_name'], true);

        return $ids ?? [];
    }
}
