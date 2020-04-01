@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="col-sm-offset-2 col-sm-8">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h1>Update Redmine Status</h1>

                    <form action="{{ action('RedmineStatusesController@update', ['status' => $status->id]) }}" method="post">
                        <input name="_method" type="hidden" value="PUT"/>
                        {{ csrf_field() }}

                        <fieldset class="form-group">
                            <label for="redmine_name">Redmine Status</label>
                            <input type="text" name="redmine_name" readonly id="redmine_name" class="form-control" @if($status)value="{{ $status->redmine_name }}"@endif>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="jira_name">Jira Status</label>
                            <input type="text" name="jira_name" id="jira_name" class="form-control" @if($status)value="{{ $status->jira_name }}"@endif>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="jira_name">Clubhouse State</label>
                            <select multiple name="clubhouse_name[]" id="clubhouse_name" class="form-control" size="10">
                                @foreach($clubhouse_statuses as $_clubhouse_status)
                                    <option value="{{ $_clubhouse_status->clubhouse_id }}" @if(in_array($_clubhouse_status->clubhouse_id, $status->clubhouse_ids)) selected @endif>{{ $_clubhouse_status->clubhouse_id }} - {{ $_clubhouse_status->clubhouse_name }}</option>
                                @endforeach
                            </select>
                        </fieldset>

                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection