@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="col-sm-offset-2 col-sm-8">
            <h1>Redmine Reports</h1>

            <div class="panel panel-default">
                <div class="panel-body">
                    <form action="{{ action('RedmineReportController@save') }}" method="post">
                        {{ csrf_field() }}

                        <fieldset class="form-group">
                            <label for="date">Start/End Date</label>
                            <input type="text" name="date" id="date" class="form-control daterange">
                        </fieldset>

                        <fieldset class="form-group">
                            <input type="checkbox" name="filter_user" id="filter_user" value="1" checked="checked" /> <label for="filter_user">Only display my entries</label>
                        </fieldset>

                        <button type="submit" class="btn btn-primary" data-toggle="tooltip" data-placement="right" title="Depending on the amount of records, this might take a while to load.">Save</button>
                    </form>
                </div>
            </div>

            @if (count($reports) > 0)
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Saved Reports
                    </div>

                    <div class="panel-body">
                        <table class="table table-striped task-table datatable" data-order="[[ 0, &quot;desc&quot; ]]">
                            <colgroup>
                                <col/>
                                <col/>
                                <col width="100"/>
                            </colgroup>
                            <thead>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th class="no-sort"></th>
                            </thead>

                            <tbody>
                                @foreach ($reports as $_report)
                                    <tr>
                                        <td>{{ date('Y-m-d', strtotime($_report->start_date)) }}</td>
                                        <td>{{ date('Y-m-d', strtotime($_report->end_date)) }}</td>
                                        <td>
                                            <a href="{{ action('RedmineReportController@show', ['report' => $_report->id]) }}" class="btn btn-default"><i class="fa fa-folder-open"></i></a>
                                            @if ($_report->canDelete())
                                                <form action="{{ action('RedmineReportController@delete', ['report' => $_report->id]) }}" method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline">
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
            @endif
        </div>
    </div>
@endsection

@section('styles')
<link rel="stylesheet" href="/css/daterangepicker.css"/>
@endsection

@section('scripts')
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
