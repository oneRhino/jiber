@extends('layouts.toggl')

@section('import_button_action', action('TogglProjectController@import'))
@section('import_button_label', 'Projects')

@section('table')
	<h1>Toggl Projects</h1>

	@if (count($projects) > 0)
		<div class="panel panel-default">
			<div class="panel-body">
				<table class="table table-striped table-hover task-table datatable" data-order="[[ 2, &quot;asc&quot; ]]">
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
								<td>{{ $_project->workspace_name }}</td>
								<td>{{ $_project->client_name }}</td>
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
