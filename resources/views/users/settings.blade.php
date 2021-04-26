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

                        <div class="checkbox">
                            <label><input type="checkbox" name="redmine_jira_sync" id="redmine_jira_sync" value="1" @if ($setting->redmine_jira_sync) checked="checked" @endif> Enable Redmine/Jira Daily Sync</label>
                        </div>

                        <div class="checkbox">
                            <label><input type="checkbox" name="toggl_redmine_sync" id="toggl_redmine_sync" value="1" @if ($setting->toggl_redmine_sync) checked="checked" @endif> Enable Toggl/Redmine Daily Sync</label>
                        </div>

                        <div class="checkbox">
                            <label><input type="checkbox" name="redmine_toggl_sync" id="redmine_toggl_sync" value="1" @if ($setting->redmine_toggl_sync) checked="checked" @endif> Enable Redmine/Toggl Daily Sync</label>
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
    $('#toggl_redmine_sync').click(function(){
        $('#toggl_redmine_sync_div').toggleClass('hidden');
    });
});
</script>
@endsection
