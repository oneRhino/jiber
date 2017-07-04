@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
      <div class="panel panel-default">
        <div class="panel-heading">
    		<a href="@yield('import_button_action')" class="btn btn-default" data-toggle="tooltip" data-placement="bottom" title="Import or re-import data. No data will be deleted - it will only save new information about current records and save new ones."><i class="glyphicon glyphicon-import"></i> Import @yield('import_button_label') from Toggl</a>
        </div>
      </div>

			@yield('table')
    </div>
  </div>
@endsection
