<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClubhouseWorkflow extends Model
{
    /**
     * Get the statuses for the clubhouse workflow.
     */
    public function statuses()
    {
        return $this->hasMany('App\ClubhouseStatus', 'workflow_id', 'clubhouse_id')->orderBy('position', 'ASC');
    }
}
