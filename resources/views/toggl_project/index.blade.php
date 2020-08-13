@extends('layouts.toggl')

@section('import_button_action', $omg ? route('omg.toggl.projects.import') : route('user.toggl.projects.import'))
@section('import_button_label', 'Projects')

@if ($omg)
@section('import_clubhouse_action', route('clubhouse.projects.import'))
@endif

@section('table')
	<h1>{{$omg ? 'OMG ' : ''}}Toggl Projects</h1>

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
						@if ($omg)
						<th>Clubhouse Project</th>
						@endif
						<th class="text-center">Active</th>
						@if ($omg)
						<th data-orderable="false"></th>
						@endif
					</thead>

					<tbody>
						@foreach ($projects as $_project)
							<tr>
								<td>{{ $_project->workspace_name }}</td>
								<td>{{ $_project->client_name }}</td>
								<td>{{ $_project->name }}</td>
								@if ($omg)
								<td>{{ $_project->clubhouse_name ? $_project->clubhouse_name : 'No project related' }}</td>
								@endif
								<td class="text-center">{{ $_project->active ? 'Yes' : 'No' }}</td>
								@if ($omg)
								<td><a href={{ route('omg.toggl.projects.edit', ['project' => $_project->id]) }}>Edit</a></td>
								@endif
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
