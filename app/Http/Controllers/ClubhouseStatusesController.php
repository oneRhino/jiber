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

        $newClubhouseStatuses = 0;

        foreach ($workflows as $_workflow) {

            foreach ($_workflow['states'] as $_state) {

                // Check if it exists
                $exists = ClubhouseStatus::where('clubhouse_id', $_state['id'])->first();

                if (!$exists) {
                    $newClubhouseStatuses++;

                    $status                 = new ClubhouseStatus;
                    $status->clubhouse_id   = $_state['id'];
                    $status->clubhouse_name = $_state['name'];
                    $status->workflow_id    = $_workflow['id'];
                    $status->save();
                }
            }
        }

        $request->session()->flash('alert-success', "All statuses have been imported successfully! New Clubhouse statuses: {$newClubhouseStatuses}");

        return back()->withInput();
    }
}
