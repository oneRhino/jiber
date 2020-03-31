@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
	    <h1>Redmine Trackers</h1>

        <div class="panel panel-default">
            <div class="panel-heading">
                <a href="{{ action('RedmineTrackersController@import') }}" class="btn btn-default"><i class="glyphicon glyphicon-import"></i> Import/Merge Trackers from Redmine</a>
            </div>
        </div>

        @if (count($trackers) > 0)
            <div class="panel panel-default">
                <div class="panel-body">
                    <table class="table table-striped table-hover task-table datatable">
                        <colgroup>
                            <col/>
                            <col/>
                            <col/>
                            <col width="100"/>
                        </colgroup>
                        <thead>
                            <th>Redmine Tracker</th>
                            <th>Jira Issue Type(s)</th>
                            <th>Clubhouse Type</th>
                            <th class="no-sort"></th>
                        </thead>

                        <tbody>
                            @foreach ($trackers as $_tracker)
                                <tr>
                                    <td>{{ $_tracker->redmine_name }}</td>
                                    <td>{{ $_tracker->jira_name }}</td>
                                    <td>{{ $_tracker->clubhouse_name }}</td>
                                    <td>
                                        <a href="{{ action('RedmineTrackersController@edit', ['tracker' => $_tracker->id]) }}" class="btn btn-default"><i class="fa fa-pencil"></i></a>
                                        <form action="{{ action('RedmineTrackersController@destroy', ['tracker' => $_tracker->id]) }}" method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline">
                                            {{ csrf_field() }}
                                            {{ method_field('DELETE') }}
                                            <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <p>No trackers found.</p>
        @endif
    </div>
  </div>
@endsection
