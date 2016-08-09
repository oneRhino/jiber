@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
      @if (count($entries) > 0)
        <div class="panel panel-default">
          <div class="panel-heading">
            Time Entries
						[ <a href="javascript:void(0)" id="invert-all">invert all</a> ]
          </div>

          <div class="panel-body">
						<form action="@yield('form_send_action')" method="post">
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

										<div id="collapse-{{ date('mdy', strtotime($_date)) }}" class="panel-collapse collapse in">
											<div class="panel-group">
												<div class="panel panel-default">
													@foreach ($_all_entries as $_redmine_task_id => $_entries)
														<?php if ($_redmine_task_id == 'toggl_total' || $_redmine_task_id == 'third_total') continue ?>
														<?php $_difference = round($_entries['third_total'] - $_entries['toggl_total'], 2) ?>

														<div class="panel-heading">
															<h4 class="panel-title"><a data-toggle="collapse" href="#collapse-{{ date('mdy', strtotime($_date)) }}-{{ $_redmine_task_id }}">@yield('task_title') <span class="fa fa-angle-down"></span></a></h4>
															Difference: {{ $_difference }} h
														</div>

														<div id="collapse-{{ date('mdy', strtotime($_date)) }}-{{ $_redmine_task_id }}" class="panel-collapse collapse in">
															<table class="table table-striped table-hover task-table">
																<colgroup>
																	<col width="120"/>
																	<col width="200"/>
																	<col/>
																</colgroup>
																@foreach ($_entries['toggl_entries'] as $_entry)
																	<tr class="disabled active">
																		<td></td>
																		<td>@yield('checkbox')</td>
																		<td>{{ $_entry->round_duration }} h ({{ $_entry->duration }})</td>
																	</tr>
																@endforeach

																<tr class="total active">
																	<th></th>
																	<th>Total</th>
																	<th>{{ $_entries['toggl_total'] }} h</th>
																</tr>
																<tr class="danger total">
																	<th></th>
																	<th>@yield('name')</th>
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
<script>
$(document).ready(function($) {
	$('input.switch[type=checkbox]').bootstrapSwitch({
		size   : 'mini',
		onText : 'send',
		offText: 'ignore',
		onSwitchChange: function(event, state){
			if (state)
				$(this).parents('tr').removeClass('disabled');
			else
				$(this).parents('tr').addClass('disabled');
		}
	});

	$('#invert-all').click(function(){
		$('input.switch').each(function(){
			$(this).attr('checked', !$(this).attr('checked')).bootstrapSwitch('toggleState');
		});
	});

	$('.panel-title a').click(function(){
		$('span', this).toggleClass('fa-angle-down').toggleClass('fa-angle-up');
	});
});
</script>
@endsection
