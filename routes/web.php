<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/'    , ['middleware' => 'auth', 'uses' => 'RedmineReportController@index']);
Route::get('/home', ['middleware' => 'auth', 'uses' => 'RedmineReportController@index']);
Route::get('/logout', 'Auth\LoginController@logout');

Route::match(['get', 'post'], 'settings', [
	'middleware' => 'auth',
	'uses' => 'UsersController@settings'
]);

// Toggl routes
Route::get('/toggl/import'            , ['middleware' => 'auth', 'uses' => 'TogglController@import']);

Route::get('/toggl/workspaces'        , ['middleware' => 'auth', 'uses' => 'TogglWorkspaceController@index']);
Route::get('/toggl/workspaces/import' , ['middleware' => 'auth', 'uses' => 'TogglWorkspaceController@import']);

Route::get('/toggl/clients'           , ['middleware' => 'auth', 'uses' => 'TogglClientController@index']);
Route::get('/toggl/clients/import'    , ['middleware' => 'auth', 'uses' => 'TogglClientController@import']);

Route::get('/toggl/projects'          , ['middleware' => 'auth', 'uses' => 'TogglProjectController@index']);
Route::get('/toggl/projects/import'   , ['middleware' => 'auth', 'uses' => 'TogglProjectController@import']);

Route::get('/toggl/tasks'             , ['middleware' => 'auth', 'uses' => 'TogglTaskController@index']);
Route::get('/toggl/tasks/import'      , ['middleware' => 'auth', 'uses' => 'TogglTaskController@import']);

Route::get   ('/toggl/reports'        , ['middleware' => 'auth', 'uses' => 'TogglReportController@index']);
Route::get   ('/toggl/report/{report}', ['middleware' => 'auth', 'uses' => 'TogglReportController@show']);
Route::post  ('/toggl/report/save'    , ['middleware' => 'auth', 'uses' => 'TogglReportController@save']);
Route::delete('/toggl/report/{report}', ['middleware' => 'auth', 'uses' => 'TogglReportController@delete']);

// Redmine routes
Route::get   ('/redmine/reports'        , ['middleware' => 'auth', 'uses' => 'RedmineReportController@index']);
Route::get   ('/redmine/report/{report}', ['middleware' => 'auth', 'uses' => 'RedmineReportController@show']);
Route::post  ('/redmine/report/save'    , ['middleware' => 'auth', 'uses' => 'RedmineReportController@save']);
Route::delete('/redmine/report/{report}', ['middleware' => 'auth', 'uses' => 'RedmineReportController@delete']);

Route::get ('/redmine/show/{report}', ['middleware' => 'auth', 'uses' => 'RedmineController@show']);
Route::post('/redmine/send'         , ['middleware' => 'auth', 'uses' => 'RedmineController@send']);

Route::get('/redmine/jira/users/import', ['middleware' => 'auth', 'uses' => 'RedmineJiraUsersController@import']);
Route::get('/redmine/jira/users/login' , ['middleware' => 'auth', 'uses' => 'RedmineJiraUsersController@login']);
Route::group(['middleware' => 'auth'], function() {
    Route::resource('/redmine/jira/users', 'RedmineJiraUsersController', ['parameters' => [
        'users' => 'user'
    ]]);
});

Route::get('/redmine/jira/projects/import', ['middleware' => 'auth', 'uses' => 'RedmineJiraProjectsController@import']);
Route::group(['middleware' => 'auth'], function() {
    Route::resource('/redmine/jira/projects', 'RedmineJiraProjectsController', ['parameters' => [
        'projects' => 'project'
    ]]);
});

Route::get('/redmine/jira/trackers/import', ['middleware' => 'auth', 'uses' => 'RedmineJiraTrackersController@import']);
Route::group(['middleware' => 'auth'], function() {
    Route::resource('/redmine/jira/trackers', 'RedmineJiraTrackersController', ['parameters' => [
        'trackers' => 'tracker'
    ]]);
});

Route::get('/redmine/jira/statuses/import', ['middleware' => 'auth', 'uses' => 'RedmineJiraStatusesController@import']);
Route::group(['middleware' => 'auth'], function() {
    Route::resource('/redmine/jira/statuses', 'RedmineJiraStatusesController', ['parameters' => [
        'statuses' => 'status'
    ]]);
});

Route::get('/redmine/jira/priorities/import', ['middleware' => 'auth', 'uses' => 'RedmineJiraPrioritiesController@import']);
Route::group(['middleware' => 'auth'], function() {
    Route::resource('/redmine/jira/priorities', 'RedmineJiraPrioritiesController', ['parameters' => [
        'priorities' => 'priority'
    ]]);
});

// Jira routes
Route::match(['get','post'], '/jira/set-password', ['middleware' => 'auth', 'uses' => 'JiraController@set_password']);

Route::get ('/jira/show/{report}', ['middleware' => 'auth', 'uses' => 'JiraController@show']);
Route::get ('/jira/csv/{report}' , ['middleware' => 'auth', 'uses' => 'JiraController@csv']);
Route::post('/jira/send'         , ['middleware' => 'auth', 'uses' => 'JiraController@send']);

Route::get ('/jira/legacy/{project}', ['middleware' => 'auth', 'uses' => 'JiraController@legacy']);

Route::match(['get','post'], '/jira/webhook', ['uses' => 'JiraController@webhook']);
