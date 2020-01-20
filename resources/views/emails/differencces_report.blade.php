<p>These are the time differences for the range {{ $start_date }} - {{ $end_date }}</p>
<p>Negative time means Jira has more time logged than Redmine.</p>

@foreach ($differences as $_user => $_diffs)
    <p>{{ $_user }}</p>
    <ul>
        @foreach ($_diffs as $_ids => $_diff)
            <li>{{ $_ids }}: <strong>{{ $_diff }}h</strong></li>
        @endforeach
    </ul>
@endforeach
