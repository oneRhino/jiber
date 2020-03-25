<?php

/**
* Copyright 2016 Thaissa Mendes
*
* This file is part of Jiber.
*
* Jiber is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jiber is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jiber. If not, see <http://www.gnu.org/licenses/>.
*/

/**
* Connect into Clubhouse, get and send information using its API
*
* @author João Cortela <cortelas@gmail.com>
* @since 8 August, 2016
* @version 0.1
*/

namespace App\Http\Controllers;

use Mikkelson\Clubhouse;
use Illuminate\Support\Facades\Config;

class ClubhouseController extends Controller {

    public function createComment ($storyId, $comment) {

        $commentFields = array ();
        $commentFields['text'] = $comment;

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "stories/{$storyId}/comments";

        $clubhouseStoryComment = $clubhouseApi->create($apiUri, $commentFields);

        return $clubhouseStoryComment;
    }

    public function createStory ($storyDetails) {
        
        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);
        
        $clubhouseStory = $clubhouseApi->create('stories', $storyDetails);

        return $clubhouseStory;
    }

    public function getProjects () {
        
        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);
        
        $projectsAsArray = $clubhouseApi->get('projects');

        return $projectsAsArray;
    }
    
    public function getTickets ($projectId) {

        if (!$projectId)
            return false;

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "projects/{$projectId}/stories";

        $ticketsAsArray = $clubhouseApi->get($apiUri);

        return $ticketsAsArray;
    }

    public function getUsers () {

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "users";

        $usersAsArray = $clubhouseApi->get($apiUri);

        return $usersAsArray;
    }

    public function updateComment ($storyId, $commentId, $comment) {

        $commentFields = array ();
        $commentFields['text'] = $comment;

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $apiUri = "stories/{$storyId}/comments";

        $clubhouseStoryComment = $clubhouseApi->update($apiUri, $commentId, $commentFields);

        return $clubhouseStoryComment;
    }

    public function updateStory ($storyId, $updateData) {

        if (!$storyId)
            return false;

        $token = Config::get('clubhouse.api_key');
        $clubhouseApi = new Clubhouse($token);

        $clubhouseStory = $clubhouseApi->update("stories", $storyId,  $updateData);

        return $clubhouseStory;
    }
}
