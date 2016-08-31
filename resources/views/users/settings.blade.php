@extends('layouts.app')

<style>
.form-group.alert-danger,.form-group.alert-success{padding:10px 0}
</style>

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Settings</div>
                <div class="panel-body">
                    <form class="form-horizontal" role="form" method="POST" action="{{ action('UsersController@settings') }}">
                        {{ csrf_field() }}

                        @include('users.settings_field', ['boolean' => $toggl   , 'name' => 'toggl'   , 'label' => 'Toggl API Token'  , 'value' => $setting->toggl ])
                        @include('users.settings_field', ['boolean' => $redmine , 'name' => 'redmine' , 'label' => 'Redmine API Token', 'value' => $setting->redmine ])
                        @include('users.settings_field', ['boolean' => $jira    , 'name' => 'jira'    , 'label' => 'Jira Username'    , 'value' => $setting->jira ])
                        {{-- @include('users.settings_field', ['boolean' => $basecamp, 'name' => 'basecamp', 'label' => 'Basecamp Username', 'value' => $setting->basecamp ]) --}}

                        <div class="form-group">
                            <div class="col-md-6 col-md-offset-4">
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
