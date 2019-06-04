<?php

namespace App\Console\Commands;

use Mail;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use App\Setting;
use App\TogglClient;
use App\TogglReport;
use App\TogglProject;
use App\TogglTask;
use App\TimeEntry;
use App\TogglTimeEntry;
use App\RedmineReport;
use App\Report;
use App\Http\Controllers\TogglReportController;
use App\Http\Controllers\RedmineReportController;
use Illuminate\Contracts\Encryption\DecryptException;
use Crypt;

class TimeEntriesSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'time-entries:sync {method} {date=current} {user=all}
                            method : Toggl-Redmine or Redmine-Jira
                            date : For which date(s) should system sync. Ex: "2017-04-11" or "2017-04-01|2017-04-15" or "yesterday". Default is current server date.';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily Sync between Toggl/Redmine and Redmine/Jira';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        switch ($this->argument('date')) {
            case 'current':
                $date = date('Y-m-d');
                break;
            case 'yesterday':
                $date = date('Y-m-d', strtotime('-1 day'));
                break;
            default:
                $date = $this->argument('date');
        }

        if (strpos($date, '|') !== false)
            $date = explode('|', $date);

        switch ($this->argument('method')) {
            case 'Toggl-Redmine':
                $this->toggl_redmine($date, $this->argument('user'));
                break;
            case 'Redmine-Jira':
                $this->redmine_jira($date, $this->argument('user'));
                break;
            default:
                $this->error('Please select a valid method.');
                break;
        }
    }

    private function redmine_jira($date, $user) {
        // Get all OneRhino hours from all users that set Redmine/Jira sync as ON
        if ($user != 'all')
            $settings = Setting::where('jira', $user)->get();
        else
            $settings = Setting::where('redmine_jira_sync', true)->get();

        if ($settings) {
            foreach ($settings as $_settings) {
                // Get user info
                $_user = $_settings->user;
                $this->info('Running sync for '.$_user->name);

                // Check if user has jira password
                if (!$_settings->jira_password) continue;

                // Set user as logged-in user
                $request = new Request();
                $request->merge(['user' => $_user]);
                $request->setUserResolver(function () use ($_user) {
                    return $_user;
                });

                Auth::setUser($_user);

                //$request->jira_password = $jira_password;

                // Create a report for the requested date(s)
                $RedmineController = new RedmineReportController($request);

                if (is_array($date)) {
                    $request->date = $date[0].' - '.$date[1];
                } else {
                    $request->date = $date.' - '.$date;
                }

                $request->filter_user = true;

                $report = $RedmineController->save($request, false);

                // Send all time entries to jira
                $sent = $RedmineController->sendAllToJira($report, $request);

                if ($sent) {
                    $report = Report::find($report);

                    if (is_array($date))
                        $_date = date('F j, Y', strtotime($date[0])).' to '.date('F j, Y', strtotime($date[1]));
                    else
                        $_date = date('F j, Y', strtotime($date));

                    $data = array(
                        'entries' => $report->getTimeEntries(),
                        'date'    => $_date,
                    );

                    Mail::send('emails.redmine_jira_report', $data, function ($m) use ($_user) {
                        $m->from('noreply@tmisoft.com', 'Jiber');
                        $m->cc(['thaissa@onerhino.com', 'pablo@onerhino.com', 'william@onerhino.com', 'billy@onerhino.com']);
                        $m->to($_user->email, $_user->name)->subject('Redmine-to-Jira Daily Report');
                    });
                }
            }
        }
    }

    private function toggl_redmine($date) {
        // Get all OneRhino hours from all users that set Toggl/Redmine sync as ON
        $settings = Setting::where('toggl_redmine_sync', true)->get();

        if ($settings) {
            foreach ($settings as $_settings) {
                // Get user info
                $_user = $_settings->user;
                $settings = unserialize($_settings->toggl_redmine_data);

                if (!$settings) continue;

                // Set user as logged-in user
                $request = new Request();
                $request->merge(['user' => $_user]);
                $request->setUserResolver(function () use ($_user) {
                    return $_user;
                });

                Auth::setUser($_user);

                // Create a report for the requested date(s)
                $TogglController = new TogglReportController($request);

                if (is_array($date)) {
                    $request->date = $date[0].' - '.$date[1];
                } else {
                    $request->date = $date.' - '.$date;
                }

                $request->workspace = $settings['workspace'];
                $request->clients   = $settings['clients'];
                $request->projects  = $settings['projects'];

                $report = $TogglController->save($request, false);

                // Send all time entries to redmine
                $sent = $TogglController->sendAllToRedmine($report);

                if ($sent) {
                    $report = Report::find($report);

                    if (is_array($date))
                        $_date = date('F j, Y', strtotime($date[0])).' to '.date('F j, Y', strtotime($date[1]));
                    else
                        $_date = date('F j, Y', strtotime($date));

                    $data = array(
                        'entries' => $report->getTimeEntries(),
                        'date'    => $_date,
                    );

                    Mail::send('emails.toggl_redmine_report', $data, function ($m) use ($_user) {
                        $m->from('noreply@tmisoft.com', 'Jiber');
                        $m->to($_user->email, $_user->name)->subject('Toggl-to-Redmine Daily Report');
                    });
                }
            }
        }
    }
}
