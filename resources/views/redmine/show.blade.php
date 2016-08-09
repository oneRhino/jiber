@extends('layouts.show_compare')
@section('form_send_action', action('RedmineController@send'))
@section('checkbox', '<input type="checkbox" name="task[{{ $_entry->date }}][{{ $_entry->redmine }}][]" value="{{ $_entry->round_duration }}" class="switch">')
@section('name', 'Redmine')
