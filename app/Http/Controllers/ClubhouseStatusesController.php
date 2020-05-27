<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Mikkelson\Clubhouse;

use App\Http\Requests;
use App\ClubhouseStatus;

class ClubhouseStatusesController extends Controller
{
    public function import(Request $request)
    {
        // Get projects from Clubhouse
        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $workflows = $clubhouseApi->get('workflows');

        $newClubhouseStatuses = $updatedClubhouseStatuses = 0;

        foreach ($workflows as $_workflow) {

            foreach ($_workflow['states'] as $_state) {

                // Check if it exists
                $ch_status = ClubhouseStatus::where('clubhouse_id', $_state['id'])->first();

                if (!$ch_status) {
                    $newClubhouseStatuses++;

                    $ch_status                 = new ClubhouseStatus;
                    $ch_status->clubhouse_id   = $_state['id'];
                    $ch_status->clubhouse_name = $_state['name'];
                    $ch_status->workflow_id    = $_workflow['id'];
                    $ch_status->save();
                } else {
                    $updatedClubhouseStatuses++;

                    $ch_status->clubhouse_name = $_state['name'];
                    $ch_status->workflow_id    = $_workflow['id'];
                    $ch_status->save();
                }
            }
        }

        $request->session()->flash('alert-success', "All statuses have been imported successfully! New: {$newClubhouseStatuses}; Updated: {$updatedClubhouseStatuses}");

        return back()->withInput();
    }
}
