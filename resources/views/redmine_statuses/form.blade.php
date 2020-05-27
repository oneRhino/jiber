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
                            <label for="clubhouse_main_id">Clubhouse Main Status</label>
                            <select name="clubhouse_main_id" id="clubhouse_main_id" class="form-control">
                                @foreach($ch_workflows as $_workflow => $_statuses)
                                    <optgroup label="{{ $_workflow }}">
                                        @foreach($_statuses as $_clubhouse_status)
                                            <option value="{{ $_clubhouse_status->clubhouse_id }}" @if($_clubhouse_status->clubhouse_id == $status->clubhouse_main_id) selected @endif>{{ $_clubhouse_status->clubhouse_name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p><small>Will be used when Status is changed on Redmine</small></p>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="jira_name">Clubhouse State</label>
                            <select multiple name="clubhouse_id[]" id="clubhouse_id" class="form-control" size="10">
                                @foreach($ch_workflows as $_workflow => $_statuses)
                                    <optgroup label="{{ $_workflow }}">
                                        @foreach($_statuses as $_clubhouse_status)
                                            <option value="{{ $_clubhouse_status->clubhouse_id }}" @if(in_array($_clubhouse_status->clubhouse_id, $status->clubhouse_ids)) selected @endif>{{ $_clubhouse_status->clubhouse_name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p><small>Will be used when Status is changed on Clubhouse</small></p>
                        </fieldset>

                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
