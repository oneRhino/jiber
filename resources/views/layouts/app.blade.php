<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"/>

    <title>Jiber - Toggl/Redmine/Jira Import/Export</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="/css/font-awesome.min.css"/>

    <!-- Styles -->
    <link rel="stylesheet" href="/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="/css/datatables.min.css"/>
    <link rel="stylesheet" href="/css/default.css"/>
    @yield('styles')
</head>
<body id="app-layout">
    <nav class="navbar navbar-default navbar-static-top">
        <div class="container">
            <div class="navbar-header">

                <!-- Collapsed Hamburger -->
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#app-navbar-collapse">
                    <span class="sr-only">Toggle Navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>

                <!-- Branding Image -->
                <a class="navbar-brand" href="{{ url('/') }}">Jiber</a>
            </div>

            <div class="collapse navbar-collapse" id="app-navbar-collapse">
                <!-- Left Side Of Navbar -->
                <ul class="nav navbar-nav">
                    <li>
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Redmine <span class="caret"></span></a>
                        <ul class="dropdown-menu">
                            <li><a href="{{ action('RedmineReportController@index') }}">Reports</a></li>
                            @if (!Auth::guest() && Auth::user()->administrator)
                                <li><a href="{{ action('RedmineJiraUsersController@index') }}">Redmine/Jira Users</a></li>
                                <li><a href="{{ action('RedmineJiraProjectsController@index') }}">Redmine/Jira Projects</a></li>
                                <li><a href="{{ action('RedmineJiraTrackersController@index') }}">Redmine/Jira Trackers</a></li>
                                <li><a href="{{ action('RedmineJiraStatusesController@index') }}">Redmine/Jira Statuses</a></li>
                                <li><a href="{{ action('RedmineJiraPrioritiesController@index') }}">Redmine/Jira Priorities</a></li>
                            @endif
                        </ul>
                    </li>
                    <li>
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Toggl <span class="caret"></span></a>
                        <ul class="dropdown-menu">
                            <li><a href="{{ action('TogglWorkspaceController@index') }}">Workspaces</a></li>
                            <li><a href="{{ action('TogglClientController@index') }}">Clients</a></li>
                            <li><a href="{{ action('TogglProjectController@index') }}">Projects</a></li>
                            <li><a href="{{ action('TogglTaskController@index') }}">Tasks</a></li>
                            <li><a href="{{ action('TogglReportController@index') }}">Reports</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Right Side Of Navbar -->
                <ul class="nav navbar-nav navbar-right">
                    <!-- Authentication Links -->
                    @if (Auth::guest())
                        <li><a href="{{ url('/login') }}"><i class="fa fa-btn fa-sign-in"></i> Login</a></li>
                        <li><a href="{{ url('/register') }}"><i class="fa fa-btn fa-user-plus"></i> Register</a></li>
                    @else
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
                                {{ Auth::user()->name }} <span class="caret"></span>
                            </a>

                            <ul class="dropdown-menu" role="menu">
                                <li><a href="{{ url('settings') }}"><i class="fa fa-btn fa-cogs"></i>Settings</a></li>
                                <li><a href="{{ url('/logout') }}"><i class="fa fa-btn fa-sign-out"></i>Logout</a></li>
                            </ul>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    <div class="flash-message">
      @foreach (['danger', 'warning', 'success', 'info'] as $msg)
        @if(Session::has('alert-' . $msg))

        <p class="alert alert-{{ $msg }}">{{ Session::get('alert-' . $msg) }} <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a></p>
        @endif
      @endforeach
    </div> <!-- end .flash-message -->

    @yield('content')

    <!-- JavaScripts -->
    <script type="text/javascript" src="/js/jquery.min.js"></script>
    <script type="text/javascript" src="/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="/js/datatables.min.js"></script>
    <script type="text/javascript" src="/js/default.js"></script>
    @yield('scripts')
</body>
</html>
