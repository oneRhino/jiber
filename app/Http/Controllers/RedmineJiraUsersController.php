<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use App\RedmineJiraUser;
use App\User;

class RedmineJiraUsersController extends Controller
{
    public function index()
    {
        $users = RedmineJiraUser::get();

        return view('redmine_jira_users.index', [
            'users' => $users,
        ]);
    }

    /*public function login()
    {
        $user = User::find($_GET['user']);
        Auth::login($user);
    }*/

    public function edit(RedmineJiraUser $user)
    {
        return view('redmine_jira_users.form', [
            'user' => $user,
        ]);
    }

    public function update(RedmineJiraUser $user, Request $request)
    {
        // Save user
        $user->jira_name    = $request->jira_name;
        $user->save();

        $request->session()->flash('alert-success', 'User updated successfully!');

        return redirect()->action('RedmineJiraUsersController@index');
    }

    public function destroy(RedmineJiraUser $user, Request $request)
    {
        $user->delete();

        $request->session()->flash('alert-success', 'User has been successfully deleted!');

        return back()->withInput();
    }

    public function import(Request $request)
    {
        // Get user (billy/admin)
        $user = User::find(7);

        // Set user as logged-in user
        $_request = new Request();
        $_request->merge(['user' => $user]);
        $_request->setUserResolver(function () use ($user) {
            return $user;
        });
        Auth::setUser($user);

        // Get all users from Redmine
        $redmineController = new RedmineController;
        $redmine = $redmineController->connect();

        $users = $redmine->user->all(array('limit' => 300));

        foreach ($users['users'] as $_user)
        {
            // Sync user
            $user = RedmineJiraUser::where('redmine_name', $_user['login'])->get()->first();

            if (!$user) {
                $user = new RedmineJiraUser();
                $user->redmine_name = $_user['login'];
            }

            $user->redmine_id = $_user['id'];
            $user->save();
        }

        $request->session()->flash('alert-success', 'All users have been imported successfully!');

        return back()->withInput();
    }
}
