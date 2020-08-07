@extends('layouts.toggl')

@section('import_button_action', $omg ? route('omg.toggl.workspaces.import') : route('user.toggl.workspaces.import'))
@section('import_button_label', 'Workspaces')

@section('table')
	<h1>{{$omg ? 'OMG ' : ''}}Toggl Workspaces</h1>

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
