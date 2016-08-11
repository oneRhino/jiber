@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
      @if (count($entries) > 0)
        <h2>Time Entries</h2>

        <div class="panel panel-default">
          <div class="panel-heading">
						<button type="button" class="btn btn-default btn-xs" id="send-all">Set all as to be sent</button>
						<button type="button" class="btn btn-default btn-xs" id="ignore-all">Set all as to be ignored</button>
						<button type="button" class="btn btn-default btn-xs" id="invert-all">Invert all</button>
						<button type="button" class="btn btn-default btn-xs" id="close-all">Close all</button>
						<button type="button" class="btn btn-default btn-xs" id="open-all">Open all</button>
						<button type="button" class="btn btn-default btn-xs" id="close-settled">Close all settled</button>
          </div>

          <div class="panel-body">
						<form action="{{ action('JiraController@send') }}" method="post">
							<input type="hidden" name="report_id" value="{{ $report_id }}"/>
							{{ csrf_field() }}

							<div class="panel-group">
								<div class="panel panel-default">
									@foreach ($entries as $_date => $_all_entries)
										<?php $difference = round($_all_entries['third_total'] - $_all_entries['toggl_total'], 2) ?>
										<div class="panel-heading">
											<h4 class="panel-title"><a data-toggle="collapse" href="#collapse-{{ date('mdy', strtotime($_date)) }}">{{ date('F d, Y', strtotime($_date)) }} <span class="fa fa-angle-down"></span></a></h4>
											Difference: {{ $difference }} h
										</div>

										<div id="collapse-{{ date('mdy', strtotime($_date)) }}" class="panel-collapse collapse in" rel="{{ $difference }}">
											<div class="panel-group">
												<div class="panel panel-default">
													@foreach ($_all_entries as $_redmine_task_id => $_entries)
														<?php if ($_redmine_task_id == 'toggl_total' || $_redmine_task_id == 'third_total') continue ?>
														<?php $_difference = round($_entries['third_total'] - $_entries['toggl_total'], 2) ?>

														<div class="panel-heading">
															<h4 class="panel-title"><a data-toggle="collapse" href="#collapse-{{ date('mdy', strtotime($_date)) }}-{{ $_redmine_task_id }}">#{{ $_redmine_task_id }} - {{ $_entries['toggl_entries'][0]->jira }} <span class="fa fa-angle-down"></span></a></h4>
															Difference: {{ $_difference }} h
														</div>

														<div id="collapse-{{ date('mdy', strtotime($_date)) }}-{{ $_redmine_task_id }}" class="panel-collapse collapse in" rel="{{ $_difference }}">
															<table class="table table-striped table-hover task-table">
																<colgroup>
																	<col width="120"/>
																	<col width="200"/>
																	<col/>
																</colgroup>
																@foreach ($_entries['toggl_entries'] as $_entry)
																	<tr class="disabled active">
																		<td></td>
																		<td><input type="checkbox" name="task[]" value="{{ $_entry->id }}" class="switch"></td>
																		<td>{{ $_entry->round_decimal_duration }} h ({{ $_entry->hour_duration }})</td>
																	</tr>
																@endforeach

																<tr class="total active">
																	<th></th>
																	<th>Total</th>
																	<th>{{ $_entries['toggl_total'] }} h</th>
																</tr>
																<tr class="danger total">
																	<th></th>
																	<th>Jira</th>
																	<th></th>
																</tr>

																@foreach ($_entries['third_entries'] as $_entry)
																	<tr class="danger">
																		<td></td>
																		<td>{{ $_entry['description'] }}</td>
																		<td>{{ $_entry['time'] }} h</td>
																	</tr>
																@endforeach

																<tr class="danger total">
																	<th></th>
																	<th>Total</th>
																	<th>{{ $_entries['third_total'] }} h</th>
																</tr>
															</table>
														</div>
													@endforeach
												</div>
											</div>
										</div>
										<br/>
									@endforeach
								</div>
							</div>

							<button type="submit" class="btn btn-primary">Send</button>
						</form>
          </div>
        </div>
      @endif
    </div>
  </div>
@endsection

@section('scripts')
<link href="/css/bootstrap-switch.min.css" rel="stylesheet">
<script src="/js/bootstrap-switch.min.js"></script>
<script src="/js/show-compare.js"></script>
@endsection
