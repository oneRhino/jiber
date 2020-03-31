@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="col-sm-offset-2 col-sm-8">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h1>Update Redmine Tracker</h1>

                    <form action="{{ action('RedmineTrackersController@update', ['tracker' => $tracker->id]) }}" method="post">
                        <input name="_method" type="hidden" value="PUT"/>
                        {{ csrf_field() }}

                        <fieldset class="form-group">
                            <label for="redmine_name">Redmine Tracker name</label>
                            <input type="text" name="redmine_name" readonly id="redmine_name" class="form-control" @if($tracker)value="{{ $tracker->redmine_name }}"@endif>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="jira_name">Jira Issue Type(s)</label>
                            <input type="text" name="jira_name" id="jira_name" class="form-control" @if($tracker)value="{{ $tracker->jira_name }}"@endif>
                            <p><small>If more than one, separate by comma.</small></p>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="jira_name">Clubhouse Type</label>
                            <input type="text" name="clubhouse_name" id="clubhouse_name" class="form-control" @if($tracker)value="{{ $tracker->clubhouse_name }}"@endif>
                        </fieldset>

                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
