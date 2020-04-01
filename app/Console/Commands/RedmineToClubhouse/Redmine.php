<?php

namespace App\Console\Commands\RedmineToClubhouse;

use App\User;
use App\Http\Controllers\RedmineController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

trait Redmine {
    private $redmine;

    private function login() {
        $user = User::find(7);

        // Set user as logged-in user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        Auth::setUser($user);

        $RedmineController = new RedmineController;
        $this->redmine = $RedmineController->connect();
    }
}
