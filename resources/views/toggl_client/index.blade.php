@extends('layouts.toggl')

@section('import_button_action', $omg ? route('omg.toggl.clients.import') : route('user.toggl.clients.import'))
@section('import_button_label', 'Clients')

@section('table')
<h1>{{$omg ? 'OMG ' : ''}}Toggl Clients</h1>

	@if (count($clients) > 0)
		<div class="panel panel-default">
			<div class="panel-body">
				<table class="table table-striped table-hover task-table datatable" data-order="[[ 1, &quot;asc&quot; ]]">
					<colgroup>
						<col width="120"/>
						<col/>
					</colgroup>
					<thead>
						<th>Workspace</th>
						<th>Name</th>
					</thead>

					<tbody>
						@foreach ($clients as $_client)
							<tr>
								<td>{{ $_client->workspace_name }}</td>
								<td>{{ $_client->name }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	@else
		<p>No Clients found. Click "Import" above to import them from Toggl.</p>
	@endif
@endsection
