@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
	    <h1>Redmine Statuses</h1>

        <div class="panel panel-default">
            <div class="panel-heading">
                <a href="{{ action('RedmineStatusesController@import') }}" class="btn btn-default"><i class="glyphicon glyphicon-import"></i> Import/Merge Statuses from Redmine</a>
            </div>
        </div>

        @if (count($statuses) > 0)
            <div class="panel panel-default">
                <div class="panel-body">
                    <table class="table table-striped table-hover task-table datatable">
                        <colgroup>
                            <col/>
                            <col/>
                            <col width="100"/>
                        </colgroup>
                        <thead>
                            <th>Redmine Status</th>
                            <th>Jira Status</th>
                            <th>Clubhouse Status</th>
                            <th class="no-sort"></th>
                        </thead>

                        <tbody>
                            @foreach ($statuses as $_status)
                                <tr>
                                    <td>{{ $_status->redmine_name }}</td>
                                    <td>{{ $_status->jira_name }}</td>
                                    <td>{{ $_status->clubhouse_name }}</td>
                                    <td>
                                        <a href="{{ action('RedmineStatusesController@edit', ['status' => $_status->id]) }}" class="btn btn-default"><i class="fa fa-pencil"></i></a>
                                        <form action="{{ action('RedmineStatusesController@destroy', ['status' => $_status->id]) }}" method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline">
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
            <p>No statuses found.</p>
        @endif
    </div>
  </div>
@endsection
