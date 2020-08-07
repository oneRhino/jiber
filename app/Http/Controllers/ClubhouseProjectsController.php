<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Nshonda\Clubhouse;

use App\Http\Requests;
use App\ClubhouseProject;
use Illuminate\Support\Facades\Log;

class ClubhouseProjectsController extends Controller
{
    public function import(Request $request)
    {
        // Get projects from Clubhouse
        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $projectsAsArray = $clubhouseApi->get('projects');

        $newClubhouseProjects = 0;

        foreach ($projectsAsArray as $project) {


            // Ignore ARCHIVED projects.
            if ($project['archived']) {
                continue;
            }

            $projectObj = ClubhouseProject::where('clubhouse_id', $project['id'])->get()->first();

            if (!$projectObj) {
                $newClubhouseProjects++;
                $projectObj = new ClubhouseProject();
                $projectObj->clubhouse_id = $project['id'];
                $projectObj->clubhouse_name = $project['name'];
            }

            $projectObj->save();
        }

        $request->session()->flash('alert-success', "All projects have been imported successfully! New Clubhouse projects: {$newClubhouseProjects}");

        return back()->withInput();
    }
}
