<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Http\Requests;
use App\RedmineClubhouseUser;
use App\RedmineJiraUser;
use App\User;

class RedmineClubhouseUsersController extends Controller
{
    public function index()
    {
        $listOfUsers = array();

        $users = RedmineClubhouseUser::get();
        foreach ($users as $user) {
            $tmpArray = array();
            $tmpArray['id'] = $user->id;
            $tmpArray['clubhouse_name'] = $user->clubhouse_name;
            $tmpArray['redmine_names'] = json_decode($user->redmine_names, TRUE);
            array_push($listOfUsers, $tmpArray);
        }

        return view('redmine_clubhouse_users.index', [
            'list_of_users' => $listOfUsers,
        ]);
    }

    public function edit(RedmineClubhouseUser $user)
    {
        $clubhouse_name = $user->clubhouse_name;
        $redmine_names = json_decode($user->redmine_names, true);
        $redmine_names_list = RedmineJiraUser::select('redmine_name')->get();

        return view('redmine_clubhouse_users.form', [
            'user' => $user,
            'clubhouse_name' => $clubhouse_name,
            'redmine_names' => $redmine_names,
            'redmine_names_list' => $redmine_names_list,
        ]);
    }

    public function update(RedmineClubhouseUser $user, Request $request)
    {
        // Save user
        $user->clubhouse_name = $request->clubhouse_name;
        $user->redmine_names = json_encode($request->redmine_names);
        $user->save();

        $request->session()->flash('alert-success', 'User updated successfully!');

        return redirect()->action('RedmineClubhouseUsersController@index');
    }

    public function destroy(RedmineClubhouseUser $user, Request $request)
    {
        $user->delete();

        $request->session()->flash('alert-success', 'User has been successfully deleted!');

        return back()->withInput();
    }

    public function import(Request $request)
    {
        // TODO: We have only one Clubhouse user for now. We can get all of them in the API if needed in the future.
        if (!RedmineClubhouseUser::where('clubhouse_name', Config::get('clubhouse.clubhouse_dev_user'))->first()) {
            // DEV User.
            $user = new RedmineClubhouseUser();
            $user->clubhouse_name = Config::get('clubhouse.clubhouse_dev_user');
            $user->redmine_names = "[]";
            $user->save();
        }

        // TODO: We have only one Clubhouse user for now. We can get all of them in the API if needed in the future.
        if (!RedmineClubhouseUser::where('clubhouse_name', Config::get('clubhouse.clubhouse_pm_user'))->first()) {
            // PM User.
            $user = new RedmineClubhouseUser();
            $user->clubhouse_name = Config::get('clubhouse.clubhouse_pm_user');
            $user->redmine_names = "[]";
            $user->save();
        }

        return back()->withInput();
    }
}
