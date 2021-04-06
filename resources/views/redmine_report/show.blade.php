@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="col-sm-offset-2 col-sm-8">
            <h1>Redmine Report</h1>

            <dl class="dl-horizontal">
                <dt>Start Date:</dt>
                <dd>{{ date('m/d/Y', strtotime($report->start_date)) }}</dd>
                <dt>End Date:</dt>
                <dd>{{ date('m/d/Y', strtotime($report->end_date))   }}</dd>
            </dl>

            <div class="text-center" style="margin-bottom:10px">
                <a href="{{ action('TogglController@show', ['report' => $report->id]) }}" class="btn btn-default" data-toggle="tooltip" data-placement="bottom" title="Depending on the amount of records, this might take a while to load."><i class="aui-icon aui-icon-small aui-iconfont-toggl"></i> Compare entries to Toggl's</a>
            </div>

            @if ($report->redmine_entries)
                <table class="table table-striped table-hover task-table datatable" data-order="[[ 2, &quot;asc&quot; ]]">
                    <colgroup>
                        @if (!$report->filter_user)
                        <col width="120">
                        @endif
                        <col width="100"/>
                        <col/>
                        <col width="100"/>
                        <col width="120"/>
                    </colgroup>

                    <thead>
                        @if (!$report->filter_user)
                        <th>User</th>
                        @endif
                        <th>Issue</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Duration</th>
                    </thead>

                    <tbody>
                        <?php $total = 0 ?>
                        @foreach ($report->redmine_entries as $_entry)
                            <?php $total += $_entry->round_decimal_duration ?>
                            <tr>
                                @if (!$report->filter_user)
                                <td>{{ $_entry->user }}</td>
                                @endif
                                <td>#{{ $_entry->redmine_issue_id }}</td>
                                <td>{{ $_entry->description }}</td>
                                <td>{{ date('d/m/Y', strtotime($_entry->date)) }}</td>
                                <td>{{ $_entry->round_decimal_duration }} h ({{ $_entry->hour_duration }})</td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr>
                            @if (!$report->filter_user)
                            <th colspan="3"></th>
                            @else
                            <th colspan="2"></th>
                            @endif
                            <th>Total</th>
                            <th>{{ $total }} h</td>
                        </tr>
                    </tfoot>
                </table>
            @else
                <p>No results matched by your search.</p>
            @endif
        </div>
    </div>
@endsection
