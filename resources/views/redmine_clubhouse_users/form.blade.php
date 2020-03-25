@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="col-sm-offset-2 col-sm-8">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h1>Update Redmine/Clubhouse User</h1>

                    <form action="{{ action('RedmineClubhouseUsersController@update', ['user' => $user->id]) }}" method="post">
                        <input name="_method" type="hidden" value="PUT"/>
                        {{ csrf_field() }}

                        <fieldset class="form-group">
                            <label for="redmine_name">Clubhouse Username</label>
                            <input type="text" name="clubhouse_name" id="clubhouse_name" class="form-control" @if($user)value="{{ $user->clubhouse_name }}"@endif>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="jira_name">Redmine Usernames</label>
                            <select multiple="multiple" name="redmine_names[]" id="redmine_names" class="form-control" size="20">
                            @foreach($redmine_names_list as $key => $name)
                                <option value="{{ $name->redmine_name }}" @if(in_array($name->redmine_name, $redmine_names)) selected @endif >{{ $name->redmine_name }}</option>
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
