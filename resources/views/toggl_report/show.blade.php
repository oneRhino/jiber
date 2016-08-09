@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
      <h1>Report</h1>
      <dl class="dl-horizontal">
        <dt>Start Date:</dt>
        <dd>{{ date('m/d/Y', strtotime($report->start_date)) }}</dd>
        <dt>End Date:</dt>
        <dd>{{ date('m/d/Y', strtotime($report->end_date))   }}</dd>
        <dt>Clients:</dt>
        <dd>{{ $report->client_ids  ? $report->clients  : 'All' }}</dd>
        <dt>Projects:</dt>
        <dd>{{ $report->project_ids ? $report->projects : 'All' }}</dd>
      </dl>

      <a href="{{ action('RedmineController@show', ['report' => $report->id]) }}" class="btn btn-default" data-toggle="tooltip" data-placement="bottom" title="Depending on the amount of records, this might take a while to load."><i class="aui-icon redmine"></i> Compare entries to Redmine</a>
      <a href="{{ action('JiraController@show', ['report' => $report->id]) }}" class="btn btn-default" data-toggle="tooltip" data-placement="bottom" title="Depending on the amount of records, this might take a while to load."><i class="aui-icon aui-icon-small aui-iconfont-jira"></i> Compare entries to Jira</a>

			@if ($report->entries)
				<table class="table table-striped task-table">
					<colgroup>
						<col width="120"/>
						<col width="100"/>
						<col/>
						<col width="100"/>
						<col width="110"/>
					</colgroup>

					<thead>
						<th>Project</th>
						<th>Task</th>
						<th>Description</th>
						<th>Date</th>
						<th>Duration</th>
					</thead>

					<tbody>
						@foreach ($report->entries as $_entry)
							<tr>
								<td>{{ $_entry->project_id ? $_entry->project_name : '' }}</td>
								<td>{{ $_entry->task_id ? $_entry->task_name : '' }}</td>
								<td>{{ $_entry->description }}</td>
								<td>{{ date('d/m/Y', strtotime($_entry->date)) }}</td>
								<td>{{ $_entry->round_duration }} ({{ $_entry->duration }})</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			@else
				<p>No results matched by your search.</p>
			@endif
    </div>
  </div>
@endsection
