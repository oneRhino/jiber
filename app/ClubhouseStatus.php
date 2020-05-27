<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClubhouseStatus extends Model
{
    private $workflow_names = [
        '500000005' => 'Wordpress & PHP Development',
        '500000108' => 'React Development',
        '500000163' => 'Ticket Manager',
        '500001796' => 'Support',
    ];

    public function getWorkflowNameAttribute($value) {
        $id = $this->attributes['workflow_id'];

        if (!$id) return '';

        return $this->workflow_names[$id] ?? '';
    }
}
