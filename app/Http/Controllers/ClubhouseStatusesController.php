<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Nshonda\Clubhouse;

use App\Http\Requests;
use App\{ClubhouseStatus, ClubhouseWorkflow};

class ClubhouseStatusesController extends Controller
{
    public function import(Request $request)
    {
        // Get projects from Clubhouse
        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $workflows = $clubhouseApi->get('workflows');

        $newClubhouseWorkflows = $updatedClubhouseWorkflows = 0;
        $newClubhouseStatuses  = $updatedClubhouseStatuses = 0;

        foreach ($workflows as $_workflow) {
            $ch_workflow = ClubhouseWorkflow::where('clubhouse_id', $_workflow['id'])->first();

            if (!$ch_workflow) {
                $newClubhouseWorkflows++;

                $ch_workflow                 = new ClubhouseWorkflow;
                $ch_workflow->clubhouse_id   = $_workflow['id'];
                $ch_workflow->clubhouse_name = $_workflow['name'];
                $ch_workflow->project_ids    = json_encode($_workflow['project_ids']);
                $ch_workflow->save();
            } else {
                $updatedClubhouseWorkflows++;

                $ch_workflow->clubhouse_name = $_workflow['name'];
                $ch_workflow->project_ids    = json_encode($_workflow['project_ids']);
                $ch_workflow->save();
            }

            foreach ($_workflow['states'] as $_state) {
                // Check if it exists
                $ch_status = ClubhouseStatus::where('clubhouse_id', $_state['id'])->first();

                if (!$ch_status) {
                    $newClubhouseStatuses++;

                    $ch_status                 = new ClubhouseStatus;
                    $ch_status->clubhouse_id   = $_state['id'];
                    $ch_status->clubhouse_name = $_state['name'];
                    $ch_status->position       = $_state['position'];
                    $ch_status->type           = $_state['type'];
                    $ch_status->workflow_id    = $_workflow['id'];
                    $ch_status->save();
                } else {
                    $updatedClubhouseStatuses++;

                    $ch_status->clubhouse_name = $_state['name'];
                    $ch_status->position       = $_state['position'];
                    $ch_status->type           = $_state['type'];
                    $ch_status->workflow_id    = $_workflow['id'];
                    $ch_status->save();
                }
            }
        }

        $request->session()->flash('alert-success', "All statuses have been imported successfully! New workflows: {$newClubhouseWorkflows}; Updated workflows: {$updatedClubhouseWorkflows}; New statuses: {$newClubhouseStatuses}; Updated statuses: {$updatedClubhouseStatuses}");

        return back()->withInput();
    }
}
