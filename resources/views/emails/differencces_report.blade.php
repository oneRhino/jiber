<p>These are the time differences for the range {{ $start_date }} - {{ $end_date }}</p>

@foreach ($differences as $_user => $_diffs)
    <p>{{ $_user }}</p>
    <ul>
        @foreach ($_diffs as $_ids => $_diff)
            <li>{{ $_ids }}: {{ $_diff }}</li>
        @endforeach
    </ul>
@endforeach
