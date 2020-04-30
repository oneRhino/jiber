<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Mikkelson\Clubhouse;

use App\Libraries as Libraries;
use App\Http\Requests;
use App\ClubhouseStatus;

class ClubhouseStatusesController extends Controller
{
    public function import(Request $request)
    {
        // Get projects from Clubhouse
        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $newClubhouseStatuses = 0;
        $updatedClubhouseStatuses = 0;

        // Using API V3.
        $clubhouseApiV3 = new Libraries\ClubhouseApi($token);
        $workflows = $clubhouseApiV3->get('workflows');

        foreach ($workflows as $_workflow) {

            foreach ($_workflow['states'] as $_state) {

                // Check if it exists
                $status = ClubhouseStatus::where('clubhouse_id', $_state['id'])->first();

                if (!$status) {
                    $newClubhouseStatuses++;
                    $status = new ClubhouseStatus;
                } else {
                    $updatedClubhouseStatuses++;
                }

                $status->clubhouse_id   = $_state['id'];
                $status->clubhouse_name = $_state['name'];
                $status->projects       = json_encode($_workflow['project_ids']);
                $status->workflow_group = $_workflow['id'];
                $status->save();
            }
        }

        $request->session()->flash('alert-success', "All statuses have been imported successfully! New Clubhouse statuses: {$newClubhouseStatuses} | Updated Clubhouse statuses: {$updatedClubhouseStatuses}");

        return back()->withInput();
    }
}
