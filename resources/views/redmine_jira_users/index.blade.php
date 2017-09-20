@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
	    <h1>Redmine/Jira Users</h1>

        <div class="panel panel-default">
            <div class="panel-heading">
                <a href="{{ action('RedmineJiraUsersController@import') }}" class="btn btn-default"><i class="glyphicon glyphicon-import"></i> Import/Merge Users from Redmine</a>
            </div>
        </div>

        @if (count($users) > 0)
            <div class="panel panel-default">
                <div class="panel-body">
                    <table class="table table-striped table-hover task-table datatable">
                        <colgroup>
                            <col/>
                            <col/>
                            <col width="100"/>
                        </colgroup>
                        <thead>
                            <th>Redmine Username</th>
                            <th>Jira Username</th>
                            <th class="no-sort"></th>
                        </thead>

                        <tbody>
                            @foreach ($users as $_user)
                                <tr>
                                    <td>{{ $_user->redmine_name }}</td>
                                    <td>{{ $_user->jira_name }}</td>
                                    <td>
                                        <a href="{{ action('RedmineJiraUsersController@edit', ['user' => $_user->id]) }}" class="btn btn-default"><i class="fa fa-pencil"></i></a>
                                        <form action="{{ action('RedmineJiraUsersController@destroy', ['user' => $_user->id]) }}" method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline">
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
            <p>No users found.</p>
        @endif
    </div>
  </div>
@endsection
