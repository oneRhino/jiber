<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RedmineProject extends Model
{
    public function scopeClubhouse($query) {
        return $query->where('third_party', 'clubhouse');
    }
}
