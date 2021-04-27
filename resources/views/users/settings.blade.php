@extends('layouts.app')

@section('styles')
<style>
.form-group.alert-danger,.form-group.alert-success{padding:10px 0}
</style>
@endsection

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Settings</div>
                <div class="panel-body">
                    <form class="form-horizontal" role="form" method="POST" action="{{ action('UsersController@settings') }}">
                        {{ csrf_field() }}

                        @include('users.settings_field', ['boolean' => $toggl   , 'name' => 'toggl'        , 'label' => 'Toggl API Token'   , 'value' => $setting->toggl ])
                        @include('users.settings_field', ['boolean' => $redmine , 'name' => 'redmine'      , 'label' => 'Redmine API Token' , 'value' => $setting->redmine ])
                        @include('users.settings_field', ['boolean' => $redmine , 'name' => 'redmine_user' , 'label' => 'Redmine User' , 'value' => $setting->redmine_user ])
                        @include('users.settings_field', ['boolean' => $jira    , 'name' => 'jira'         , 'label' => 'Jira Username'     , 'value' => $setting->jira ])
                        @include('users.settings_field', ['boolean' => $jira    , 'name' => 'jira_email'   , 'label' => 'Jira Email Address', 'value' => $setting->jira_email ])
                        @include('users.settings_field', ['boolean' => $jira    , 'name' => 'jira_password', 'label' => 'Jira API Token'    , 'value' => $setting->jira_password ])
                        {{-- @include('users.settings_field', ['boolean' => $basecamp, 'name' => 'basecamp', 'label' => 'Basecamp Username', 'value' => $setting->basecamp ]) --}}

                        <div id="daily_sync_div" class="panel-body">
                            <fieldset class="form-group">
                                <label for="daily_sync">Select sync type:</label>
                                <select name="daily_sync" class="form-control" id="daily_sync">
                                    <option value="">No sync</option>
                                    <option value="toggl_redmine_sync" @if ($setting->toggl_redmine_sync) selected @endif>Toggl to Redmine</option>
                                    <option value="redmine_toggl_sync" @if ($setting->redmine_toggl_sync) selected @endif>Redmine to Toggl</option>
                                </select>
                            </fieldset>
                        </div>

                        <div id="toggl_redmine_sync_div" class="panel-body @unless ($setting->toggl_redmine_sync) hidden @endunless">
                            <h4 class="text-center">Please select Workspace, Client(s) and Project(s) (if any)</h4>

                            <fieldset class="form-group">
                                <label for="workspace">Workspace</label>
                                <select name="workspace" class="form-control" id="workspace">
                                    @foreach ($workspaces as $_workspace)
                                        <option value="{{ $_workspace->toggl_id }}" {{ $setting->equalTo('workspace', $_workspace->toggl_id, 'selected') }}>{{ $_workspace->name }}</option>
                                    @endforeach
                                </select>
                            </fieldset>

                            @if (count($clients) > 0)
                                <fieldset class="form-group">
                                    <label for="clients">Clients</label>
                                    <select name="clients[]" class="form-control" id="clients" multiple>
                                        @foreach ($clients as $_client)
                                            <option value="{{ $_client->toggl_id }}" {{ $setting->equalTo('clients', $_client->toggl_id, 'selected') }}>{{ $_client->name }}</option>
                                        @endforeach
                                    </select>
                                </fieldset>
                            @endif

                            @foreach ($workspaces as $_workspace)
                                @if (count($_workspace->projects) > 0)
                                    <fieldset class="form-group">
                                        <label for="projects">Projects</label>
                                        <select name="projects[]" class="form-control" id="projects" multiple>
                                            @foreach ($_workspace->projects as $_project)
                                                <option value="{{ $_project->toggl_id }}" {{ $setting->equalTo('clients', $_project->toggl_id, 'selected') }}>{{ $_project->name }}</option>
                                            @endforeach
                                        </select>
                                    </fieldset>
                                @endif
                            @endforeach
                        </div>

                        <div class="form-group">
                            <div class="col-md-12 col-md-offset-4">
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
jQuery(document).ready(function($){
    $('#daily_sync').on('change', function() {
        if ($('#daily_sync').val() != 'toggl_redmine_sync') {
            $('#toggl_redmine_sync_div').addClass('hidden');
        }
        else {
            $('#toggl_redmine_sync_div').removeClass('hidden');
        }
    });
});
</script>
@endsection
