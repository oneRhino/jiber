@extends('layouts.toggl')

@section('import_button_action', action('TogglProjectController@import'))
@section('import_button_label', 'Projects')

@section('table')
	@if (count($projects) > 0)
		<div class="panel panel-default">
			<div class="panel-heading">
				Projects
			</div>

			<div class="panel-body">
				<table class="table table-striped table-hover task-table">
					<colgroup>
						<col width="120"/>
						<col width="140"/>
						<col/>
						<col width="100"/>
					</colgroup>
					<thead>
						<th>Workspace</th>
						<th>Client</th>
						<th>Name</th>
						<th class="text-center">Active</th>
					</thead>

					<tbody>
						@foreach ($projects as $_project)
							<tr>
								<td>{{ $_project->workspace->name }}</td>
								<td>{{ $_project->client ? $_project->client->name : '' }}</td>
								<td>{{ $_project->name }}</td>
								<td class="text-center">{{ $_project->active ? 'Yes' : 'No' }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	@else
		<p>No Projects found. Click "Import" above to import them from Toggl.</p>
	@endif
@endsection
