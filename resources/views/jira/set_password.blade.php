@extends('layouts.app')

@section('content')
  <div class="container">
    <div class="col-sm-offset-2 col-sm-8">
			<div class="panel panel-default">
				<div class="panel-body">
					<form action="{{ action('JiraController@set_password') }}" method="post">
						{{ csrf_field() }}

            <fieldset class="form-group">
							<label for="password">Set your Jira's password (will be stored for this session)</label>
							<input type="password" name="jira_password" id="password" class="form-control" required="required">
						</fieldset>

						<button type="submit" class="btn btn-primary">Send</button>
					</form>
				</div>
			</div>
		</div>
	</div>
@endsection
