<p>This is your Daily Report for {{ $date }}.</p>
<p>The following tasks have been sent from Redmine to Jira:</p>

<table width="100%">
    <tr>
        <th>Date</th>
        <th>Description</th>
        <th>Redmine ID</th>
        <th>Jira ID</th>
        <th>Time Spent</th>
    </tr>
    <?php foreach ($entries as $_entry): ?>
        <?php if (!$_entry->jira_issue_id) continue; ?>
        <tr>
            <td>{{ date('F j, Y', strtotime($_entry->date_time)) }}</td>
            <td>{{ $_entry->description }}</td>
            <td>{{ $_entry->redmine_issue_id }}</td>
            <td>{{ $_entry->jira_issue_id }}</td>
            <td>{{ $_entry->round_decimal_duration }} h ({{ $_entry->hour_duration }})</td>
        </tr>
    <?php endforeach ?>
</table>
