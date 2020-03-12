@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
	    <h1>Redmine/Clubhouse Users</h1>
        
        <div class="panel panel-default">
            <div class="panel-heading">
                <a href="{{ action('RedmineClubhouseUsersController@import') }}" class="btn btn-default"><i class="glyphicon glyphicon-import"></i> Get Clubhouse Users</a>
            </div>
        </div>

        @if (count($list_of_users) > 0)
            <div class="panel panel-default">
                <div class="panel-body">
                    <table class="table table-striped table-hover task-table datatable">
                        <colgroup>
                            <col/>
                            <col/>
                            <col width="100"/>
                        </colgroup>
                        <thead>
                            <th>Redmine Username(s)</th>
                            <th>Clubhouse Username</th>
                            <th class="no-sort"></th>
                        </thead>

                        <tbody>
                        @foreach ($list_of_users as $user)
                                <tr>
                                    <td>
                                    @if ($user['redmine_names'])
                                        @foreach ($user['redmine_names'] as $redmine_name)
                                            <li>{{ $redmine_name }}</li>
                                        @endforeach
                                    @else
                                        NOT SET
                                    @endif
                                    </td>
                                    <td>{{ $user['clubhouse_name'] }}</td>
                                    <td>
                                        <a href="{{ action('RedmineClubhouseUsersController@edit', ['user' => $user['id']]) }}" class="btn btn-default"><i class="fa fa-pencil"></i></a>
                                        <form action="{{ action('RedmineClubhouseUsersController@destroy', ['user' => $user['id']]) }}" method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline">
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
