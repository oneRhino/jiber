<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClubhouseStatus extends Model
{
    /**
     * Get the workflow that owns the status.
     */
    public function workflow()
    {
        return $this->belongsTo('App\ClubhouseWorkflow');
    }
}
