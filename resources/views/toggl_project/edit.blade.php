@extends('layouts.app')

@section('content')
<div class="container">
  <div class="col-sm-offset-2 col-sm-8">
      <div class="panel panel-default">
          <div class="panel-body">
          <h4>Edit Toggl Project - {{$project->name}}</h4>

              <form action="{{ route('omg.toggl.projects.save', ['project' => $project->id]) }}" method="post">
                  {{ csrf_field() }}

                  <fieldset class="form-group">
                      <label for="clubhouse_project">Clubhouse Project</label>
                      <select name="clubhouse_project">
                        <option value="0">No clubhouse Project</option>
                        @foreach ($clubhouse_projects as $_project)
                      <option {{ $project->clubhouseProject && $project->clubhouseProject->id == $_project->id ? 'selected' : ''}} 
                        value="{{ $_project->id }}">{{ $_project->clubhouse_name }}</option>
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
