@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
	    <h1>Redmine/Jira Projects</h1>

        <div class="panel panel-default">
            <div class="panel-heading">
                <a href="{{ action('RedmineJiraProjectsController@import') }}" class="btn btn-default"><i class="glyphicon glyphicon-import"></i> Import/Merge Projects from Redmine</a>
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
                            <th>Jira Project</th>
                            <th class="no-sort"></th>
                        </thead>

                        <tbody>
                            @foreach ($projects as $_project)
                                <tr>
                                    <td>{{ $_project->redmine_name }}</td>
                                    <td>{{ $_project->jira_name }}</td>
                                    <td>
                                        <a href="{{ action('RedmineJiraProjectsController@edit', ['project' => $_project->id]) }}" class="btn btn-default"><i class="fa fa-pencil"></i></a>
                                        <form action="{{ action('RedmineJiraProjectsController@destroy', ['project' => $_project->id]) }}" method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline">
                                            {{ csrf_field() }}
                                            {{ method_field('DELETE') }}
                                            <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                        @if ($_project->jira_name)
                                            <a href="{{ action('JiraController@legacy', ['project' => $_project->jira_name]) }}" class="btn btn-default" alt="Create legacy tickets in Redmine" title="Create legacy tickets in Redmine" onClick="return confirm('This will grab all this Project\'s tickets from Jira, and create them on Redmine. Proceed?')"><i class="fa fa-upload"></i></a>
                                        @endif
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
