@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
      @if (count($entries) > 0)
        <h2>Jira Time Entries Comparison</h2>

        <div class="panel panel-default">
          <div class="panel-heading">
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
                            <?php $difference = round($_all_entries['third_total'] - $_all_entries['entry_total'], 2) ?>
                            <div class="panel-heading">
                                <h4 class="panel-title"><a data-toggle="collapse" href="#collapse-{{ date('mdy', strtotime($_date)) }}">{{ date('F d, Y', strtotime($_date)) }} <span class="fa fa-angle-down"></span></a></h4>
                                Difference: {{ $difference }} h
                            </div>

                            <div id="collapse-{{ date('mdy', strtotime($_date)) }}" class="panel-collapse collapse in" rel="{{ $difference }}">
                                <div class="panel-group">
                                    <div class="panel panel-default">
                                        @foreach ($_all_entries as $_redmine_issue_id => $_entries)
                                            <?php if ($_redmine_issue_id == 'entry_total' || $_redmine_issue_id == 'third_total') continue ?>
                                            <?php $_difference = round($_entries['third_total'] - $_entries['entry_total'], 2) ?>

                                            <div class="panel-heading">
                                                <h4 class="panel-title"><a data-toggle="collapse" href="#collapse-{{ date('mdy', strtotime($_date)) }}-{{ $_redmine_issue_id }}">#{{ $_redmine_issue_id }} - {{ $_entries['entry_entries'][0]->jira_issue_id }} <span class="fa fa-angle-down"></span></a></h4>
                                                Difference: {{ $_difference }} h
                                            </div>

                                            <div id="collapse-{{ date('mdy', strtotime($_date)) }}-{{ $_redmine_issue_id }}" class="panel-collapse collapse in" rel="{{ $_difference }}">
                                                <table class="table table-striped table-hover task-table">
                                                    <colgroup>
                                                        <col width="100"/>
                                                        <col/>
                                                        <col width="100"/>
                                                    </colgroup>
                                                    @foreach ($_entries['entry_entries'] as $_entry)
                                                        <tr class="disabled active">
                                                            <td>{{ $_entry->user }}</td>
                                                            <td>{{ $_entry->description }}</td>
                                                            <td>{{ $_entry->round_decimal_duration }} h ({{ $_entry->hour_duration }})</td>
                                                        </tr>
                                                    @endforeach

                                                    <tr class="total active">
                                                        <th></th>
                                                        <th>Total</th>
                                                        <th>{{ $_entries['entry_total'] }} h</th>
                                                    </tr>
                                                    <tr class="danger total">
                                                        <th></th>
                                                        <th>Jira</th>
                                                        <th>@unless ($_entries['third_entries']) 0 h @endunless</th>
                                                    </tr>

                                                    @if ($_entries['third_entries'])
                                                        @foreach ($_entries['third_entries'] as $_entry)
                                                            <tr class="danger">
                                                                <td>{{ $_entry['user'] }}</td>
                                                                <td>{{ $_entry['description'] }}</td>
                                                                <td>{{ $_entry['time'] }} h</td>
                                                            </tr>
                                                        @endforeach

                                                        <tr class="danger total">
                                                            <th></th>
                                                            <th>Total</th>
                                                            <th>{{ $_entries['third_total'] }} h</th>
                                                        </tr>
                                                    @endif
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

@section('styles')
<link rel="stylesheet" href="/css/bootstrap-switch.min.css"/>
@endsection
@section('scripts')
<script type="text/javascript" src="/js/bootstrap-switch.min.js"></script>
<script type="text/javascript" src="/js/show-compare.js"></script>
@endsection
