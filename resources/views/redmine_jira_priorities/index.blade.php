@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
	    <h1>Redmine/Jira Priorities</h1>

        <div class="panel panel-default">
            <div class="panel-heading">
                <a href="{{ action('RedmineJiraPrioritiesController@import') }}" class="btn btn-default"><i class="glyphicon glyphicon-import"></i> Import/Merge Priorities from Redmine</a>
            </div>
        </div>

        @if (count($priorities) > 0)
            <div class="panel panel-default">
                <div class="panel-body">
                    <table class="table table-striped table-hover task-table datatable">
                        <colgroup>
                            <col/>
                            <col/>
                            <col width="100"/>
                        </colgroup>
                        <thead>
                            <th>Redmine Priority</th>
                            <th>Jira Priority</th>
                            <th class="no-sort"></th>
                        </thead>

                        <tbody>
                            @foreach ($priorities as $_priority)
                                <tr>
                                    <td>{{ $_priority->redmine_name }}</td>
                                    <td>{{ $_priority->jira_name }}</td>
                                    <td>
                                        <a href="{{ action('RedmineJiraPrioritiesController@edit', ['priority' => $_priority->id]) }}" class="btn btn-default"><i class="fa fa-pencil"></i></a>
                                        <form action="{{ action('RedmineJiraPrioritiesController@destroy', ['priority' => $_priority->id]) }}" method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline">
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
            <p>No priorities found.</p>
        @endif
    </div>
  </div>
@endsection
