@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="col-sm-offset-2 col-sm-8">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h1>Update Redmine/Clubhouse Project</h1>

                    <form action="{{ action('RedmineClubhouseProjectsController@update', ['project' => $project->id]) }}" method="post">
                        <input name="_method" type="hidden" value="PUT"/>
                        {{ csrf_field() }}

                        <fieldset class="form-group">
                            <label for="clubhouse_name">Clubhouse Project Name</label>
                            <input type="text" name="clubhouse_name" readonly id="clubhouse_name" class="form-control" @if($project)value="{{ $project->clubhouse_name }}"@endif>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="project_name">Redmine Project Name</label>
                            <select name="project_id" class="form-control" id="project_id">
                                @if ($redmine_projects->count())
                                    <option value="0" {{ $project->project_id == 0 ? 'selected="selected"' : '' }}>NOT SET</option>
                                    @foreach($redmine_projects as $redmine_project)
                                        <option value="{{ $redmine_project->project_id }}" {{ $project->redmine_id == $redmine_project->project_id ? 'selected="selected"' : '' }}>{{ $redmine_project->project_name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="content">Content</label>
                            <textarea name="content" id="content" class="form-control">@if($project){{ $project->content }}@endif</textarea>
			                <p>This will be added at the end of the ticket's description when it's created.</p>
                        </fieldset>

                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
