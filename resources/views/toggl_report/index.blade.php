@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
			<h1>Reports</h1>

      @if (count($workspaces) > 0)
        <div class="panel panel-default">
          <div class="panel-body">
            <form action="{{ action('TogglReportController@save') }}" method="post">
							{{ csrf_field() }}

              <fieldset class="form-group">
                <label for="date">Start/End Date</label>
                <input type="text" name="date" id="date" class="form-control daterange">
              </fieldset>

              <fieldset class="form-group">
                <label for="workspace">Workspace</label>
                <select name="workspace" class="form-control" id="workspace">
                  @foreach ($workspaces as $_workspace)
                    <option value="{{ $_workspace->toggl_id }}">{{ $_workspace->name }}</option>
                  @endforeach
                </select>
              </fieldset>

              @if (count($clients) > 0)
                <fieldset class="form-group">
                  <label for="clients">Clients</label>
                  <select name="clients[]" class="form-control" id="clients" multiple>
                    @foreach ($clients as $_client)
                      <option value="{{ $_client->toggl_id }}">{{ $_client->name }}</option>
                    @endforeach
                  </select>
                </fieldset>
              @endif

              @foreach ($workspaces as $_workspace)
                @if (count($_workspace->projects) > 0)
                  <fieldset class="form-group">
                    <label for="projects">Projects</label>
                    <select name="projects[]" class="form-control" id="projects" multiple>
                      @foreach ($_workspace->projects as $_project)
                        <option value="{{ $_project->toggl_id }}">{{ $_project->name }}</option>
                      @endforeach
                    </select>
                  </fieldset>
                @endif
              @endforeach

              <button type="submit" class="btn btn-primary" data-toggle="tooltip" data-placement="right" title="Depending on the amount of records, this might take a while to load.">Save</button>
            </form>
          </div>
        </div>
      @endif

      @if (count($reports) > 0)
        <div class="panel panel-default">
          <div class="panel-heading">
            Reports
          </div>

          <div class="panel-body">
            <table class="table table-striped task-table datatable">
              <colgroup>
                <col width="110"/>
                <col width="100"/>
                <col/>
                <col/>
                <col width="100"/>
              </colgroup>
              <thead>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Clients</th>
                <th>Projects</th>
                <th class="no-sort"></th>
              </thead>

              <tbody>
                @foreach ($reports as $_report)
                  <tr>
                    <td>{{ date('d/m/Y', strtotime($_report->start_date)) }}</td>
                    <td>{{ date('d/m/Y', strtotime($_report->end_date)) }}</td>
                    <td>{{ $_report->client_ids ? $_report->clients : 'All' }}</td>
                    <td>{{ $_report->project_ids ? $_report->projects : 'All' }}</td>
                    <td>
                      <a href="{{ action('TogglReportController@show', ['report' => $_report->id]) }}" class="btn btn-default"><i class="fa fa-folder-open"></i></a>
											@if ($_report->canDelete())
	                      <form action="{{ action('TogglReportController@delete', ['report' => $_report->id]) }}" method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline">
  	                      {{ csrf_field() }}
    	                    {{ method_field('DELETE') }}
      	                  <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i></button>
        	              </form>
											@endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
			@else
				<p>Please import Toggl data to generate reports.</p>
      @endif
    </div>
  </div>
@endsection

@section('scripts')
<link rel="stylesheet" type="text/css" href="/css/daterangepicker.css"/>

<script type="text/javascript" src="/js/moment.min.js"></script>
<script type="text/javascript" src="/js/daterangepicker.js"></script>

<script type="text/javascript">
jQuery(document).ready(function($){
	$('input.daterange').daterangepicker({
		'startDate': '{{ date('m/d/Y', strtotime('-6 days')) }}',
		'endDate'  : '{{ date('m/d/Y') }}'
	});
});
</script>
@endsection
