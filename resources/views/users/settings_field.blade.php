<?php
$alert = $sign = '';

switch ($boolean) {
    case '0': $alert = 'alert-danger';  $sign = '<i class="fa fa-exclamation-circle"></i>'; break;
    case '1': $alert = 'alert-success'; $sign = '<i class="fa fa-check-circle"></i>'; break;
}
?>
<div class="form-group {{ $alert }}">
	<label for="{{ $name }}" class="col-md-4 control-label">{!! $sign !!} {{ $label }}</label>
	<div class="col-md-8 input-group">
		<input id="{{ $name }}" type="text" class="form-control" name="{{ $name }}" value="{{ $value or '' }}">
		@if ($name == 'toggl')
			@if ($boolean == 1)
				<a href="{{ action('TogglController@import') }}" class="btn btn-success input-group-addon" data-toggle="tooltip" data-placement="bottom" title="This will import all your Workspaces, Clients, Projects and Tasks. Depending on how many records you have, it might take a while!">Import</a>
			@elseif ($boolean < 0)
				<p><a href="https://toggl.com/app/profile" target="_blank">https://toggl.com/app/profile</a></p>
			@endif
		@elseif ($name == 'redmine')
			@if ($boolean < 0)
				<p><a href="{{ Config::get('redmine.url') }}my/account" target="_blank">{{ Config::get('redmine.url') }}my/account</a></p>
			@endif
		@elseif ($name == 'jira')
			@if ($value != '')
				<a href="{{ action('JiraController@set_password') }}">Reset Password</a>
			@endif
		@endif
	</div>
</div>
