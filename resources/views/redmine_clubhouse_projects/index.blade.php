@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
	    <h1>Redmine/Clubhouse Projects</h1>

        <div class="panel panel-default">
            <div class="panel-heading">
                <a href="{{ action('RedmineClubhouseProjectsController@import') }}" class="btn btn-default"><i class="glyphicon glyphicon-import"></i> Import/Merge Projects from Clubhouse and Redmine</a>
            </div>
        </div>

        @if (count($projects) > 0)
            <div class="panel panel-default">
                <div class="panel-body">
                    <table class="table table-striped table-hover task-table datatable">
                        <colgroup>
                            <col/>
                            <col/>
                            <col width="150"/>
                        </colgroup>
                        <thead>
                            <th>Redmine Project</th>
                            <th>Clubhouse Project</th>
                            <th class="no-sort"></th>
                        </thead>

                        <tbody>
                            @foreach ($projects as $_project)
                                <tr>
                                    @if ($_project->redmine_name)
                                    <td>{{ $_project->redmine_name }}</td>
                                    @else
                                    <td>-</td>
                                    @endif
                                    <td>{{ $_project->clubhouse_name }}</td>
                                    <td>
                                        <a href="{{ action('RedmineClubhouseProjectsController@edit', ['project' => $_project->id]) }}" class="btn btn-default"><i class="fa fa-pencil"></i></a>
                                        <form action="{{ action('RedmineClubhouseProjectsController@destroy', ['project' => $_project->id]) }}" method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline">
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
            <p>No projects found.</p>
        @endif
    </div>
  </div>
@endsection
