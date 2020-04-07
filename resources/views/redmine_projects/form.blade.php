@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="col-sm-offset-2 col-sm-8">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h1>Update Redmine Project</h1>

                    <form action="{{ action('RedmineProjectsController@update', ['project' => $project->id]) }}" method="post">
                        <input name="_method" type="hidden" value="PUT"/>
                        {{ csrf_field() }}

                        <fieldset class="form-group">
                            <label for="project_name">Redmine Project name</label>
                            <input type="text" name="project_name" readonly id="project_name" class="form-control" @if($project)value="{{ $project->project_name }}"@endif>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="third_party">Third Party</label>
                            <select name="third_party" id="third_party" class="form-control">
                                <option value="">-- None --</option>
                                <option value="jira" @if($project && $project->third_party === 'jira') selected @endif>Jira</option>
                                <option value="clubhouse" @if($project && $project->third_party === 'clubhouse') selected @endif>Clubhouse</option>
                            </select>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="third_party_name">Third Party Project name</label>

                            <div id="third_party_default">
                                <input type="text" name="third_party_project_name" id="third_party_project_name" class="form-control" @if($project)value="{{ $project->third_party_project_name }}"@endif>
                            </div>

                            <div id="third_party_clubhouse">
                                <select name="third_party_clubhouse" id="third_party_clubhouse" class="form-control">
                                    <option value="">-- None --</option>
                                    @foreach($clubhouse_projects as $_clubhouse_project)
                                        <option value="{{ $_clubhouse_project->clubhouse_id }}|{{ $_clubhouse_project->clubhouse_name }}" @if($project && $project->third_party === 'clubhouse' && $_clubhouse_project->clubhouse_id == $project->third_party_project_id) selected @endif>{{ $_clubhouse_project->clubhouse_name }}</option>
                                    @endforeach
                                </select>
                            </div>
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

@section('scripts')
    <script>
    jQuery(document).ready(function($){
        show_third_party();

        $('#third_party').change(function(){
            show_third_party();
        });

        function show_third_party() {
            console.log($('#third_party').val())

            if ($('#third_party').val() === 'clubhouse') {
                $('#third_party_default').hide();
                $('#third_party_clubhouse').show();
            } else {
                $('#third_party_default').show();
                $('#third_party_clubhouse').hide();
            }
        }
    })
    </script>
@endsection
