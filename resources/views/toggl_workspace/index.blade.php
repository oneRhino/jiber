@extends('layouts.toggl')

@section('import_button_action', action('TogglWorkspaceController@import'))
@section('import_button_label', 'Workspaces')

@section('table')
	<h1>Toggl Workspaces</h1>

	@if (count($workspaces) > 0)
		<div class="panel panel-default">
			<div class="panel-body">
				<table class="table table-striped table-hover task-table datatable">
					<colgroup>
						<col/>
					</colgroup>
					<thead>
						<th>Name</th>
					</thead>

					<tbody>
						@foreach ($workspaces as $_workspace)
							<tr>
								<td>{{ $_workspace->name }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	@else
		<p>No Workspaces found. Click "Import" above to import them from Toggl.</p>
	@endif
@endsection
