@extends('layouts.show_compare')
@section('form_send_action', action('JiraController@send'))
@section('checkbox', '<input type="checkbox" name="task[{{ $_entry->time }}][{{ $_entry->jira }}][{{ $_entry->round_duration }}]" value="{{ $_entry->description }}" class="switch">')
@section('name', 'Jira')
