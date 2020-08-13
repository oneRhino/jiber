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
Route::get('/toggl/import'            , ['as' => 'user.toggl.import','middleware' => 'auth', 'uses' => 'TogglController@import']);

Route::get('/toggl/workspaces'        , ['as' => 'user.toggl.workspaces','middleware' => 'auth', 'uses' => 'TogglWorkspaceController@index']);
Route::get('/toggl/workspaces/import' , ['as' => 'user.toggl.workspaces.import','middleware' => 'auth', 'uses' => 'TogglWorkspaceController@import']);

Route::get('/toggl/clients'           , ['as' => 'user.toggl.clients','middleware' => 'auth', 'uses' => 'TogglClientController@index']);
Route::get('/toggl/clients/import'    , ['as' => 'user.toggl.clients.import','middleware' => 'auth', 'uses' => 'TogglClientController@import']);

Route::get('/toggl/projects'          , ['as' => 'user.toggl.projects','middleware' => 'auth', 'uses' => 'TogglProjectController@index']);
Route::get('/toggl/projects/import'   , ['as' => 'user.toggl.projects.import','middleware' => 'auth', 'uses' => 'TogglProjectController@import']);

Route::get('/toggl/tasks'             , ['as' => 'user.toggl.tasks','middleware' => 'auth', 'uses' => 'TogglTaskController@index']);
Route::get('/toggl/tasks/import'      , ['as' => 'user.toggl.tasks.import','middleware' => 'auth', 'uses' => 'TogglTaskController@import']);

Route::get   ('/toggl/reports'        , ['as' => 'user.toggl.reports','middleware' => 'auth', 'uses' => 'TogglReportController@index']);
Route::get   ('/toggl/report/{report}', ['as' => 'user.toggl.reports.show','middleware' => 'auth', 'uses' => 'TogglReportController@show']);
Route::post  ('/toggl/report/save'    , ['as' => 'user.toggl.reports.save','middleware' => 'auth', 'uses' => 'TogglReportController@save']);
Route::delete('/toggl/report/{report}', ['as' => 'user.toggl.reports.delete','middleware' => 'auth', 'uses' => 'TogglReportController@delete']);

// OMG Toggl routes
Route::prefix('omg')->group(function () {
    Route::get('/toggl/import'            , ['as' => 'omg.toggl.import','middleware' => 'auth', 'uses' => 'TogglController@import'])->defaults('omg', true);
;
    Route::get('/toggl/workspaces'        , ['as' => 'omg.toggl.workspaces','middleware' => 'auth', 'uses' => 'TogglWorkspaceController@index'])->defaults('omg', true);
;
    Route::get('/toggl/workspaces/import' , ['as' => 'omg.toggl.workspaces.import','middleware' => 'auth', 'uses' => 'TogglWorkspaceController@import'])->defaults('omg', true);
;

    Route::get('/toggl/clients'           , ['as' => 'omg.toggl.clients','middleware' => 'auth', 'uses' => 'TogglClientController@index'])->defaults('omg', true);
    Route::get('/toggl/clients/import'    , ['as' => 'omg.toggl.clients.import','middleware' => 'auth', 'uses' => 'TogglClientController@import'])->defaults('omg', true);

    Route::get('/toggl/projects'          , ['as' => 'omg.toggl.projects','middleware' => 'auth', 'uses' => 'TogglProjectController@index'])->defaults('omg', true);
    Route::get('/toggl/projects/import'   , ['as' => 'omg.toggl.projects.import','middleware' => 'auth', 'uses' => 'TogglProjectController@import'])->defaults('omg', true);
    Route::get('/toggl/projects/edit/{project}'   , ['as' => 'omg.toggl.projects.edit','middleware' => 'auth', 'uses' => 'TogglProjectController@edit'])->defaults('omg', true);
    Route::post('/toggl/projects/save/{project}'   , ['as' => 'omg.toggl.projects.save','middleware' => 'auth', 'uses' => 'TogglProjectController@save'])->defaults('omg', true);

    Route::get('/toggl/tasks'             , ['as' => 'omg.toggl.tasks','middleware' => 'auth', 'uses' => 'TogglTaskController@index'])->defaults('omg', true);
    Route::get('/toggl/tasks/import'      , ['as' => 'omg.toggl.tasks.import','middleware' => 'auth', 'uses' => 'TogglTaskController@import'])->defaults('omg', true);
});
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

Route::get('/redmine/projects/import', ['middleware' => 'auth', 'uses' => 'RedmineProjectsController@import']);
Route::get('/clubhouse/projects/import', ['as' => 'clubhouse.projects.import', 'middleware' => 'auth', 'uses' => 'ClubhouseProjectsController@import']);

Route::group(['middleware' => 'auth'], function() {
    Route::resource('/redmine/projects', 'RedmineProjectsController', ['parameters' => [
        'projects' => 'project'
    ]]);
});

Route::get('/redmine/clubhouse/users/import', ['middleware' => 'auth', 'uses' => 'RedmineClubhouseUsersController@import']);
Route::group(['middleware' => 'auth'], function() {
    Route::resource('/redmine/clubhouse/users', 'RedmineClubhouseUsersController', ['parameters' => [
        'users' => 'user'
    ]]);
});


Route::get('/redmine/trackers/import', ['middleware' => 'auth', 'uses' => 'RedmineTrackersController@import']);

Route::group(['middleware' => 'auth'], function() {
    Route::resource('/redmine/trackers', 'RedmineTrackersController', ['parameters' => [
        'trackers' => 'tracker'
    ]]);
});

Route::get('/redmine/statuses/import', ['middleware' => 'auth', 'uses' => 'RedmineStatusesController@import']);
Route::get('/clubhouse/statuses/import', ['middleware' => 'auth', 'uses' => 'ClubhouseStatusesController@import']);

Route::group(['middleware' => 'auth'], function() {
    Route::resource('/redmine/statuses', 'RedmineStatusesController', ['parameters' => [
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

Route::any('/jira/webhook', ['uses' => 'JiraController@webhook']);

// Clubhouse Routes
Route::any('/clubhouse/webhook', ['uses' => 'ClubhouseController@webhook']);
