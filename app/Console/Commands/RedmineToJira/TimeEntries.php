<?php

namespace App\Console\Commands\RedmineToJira;

use Illuminate\Console\Command;

class TimeEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redmine-to-jira:time-entries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync time entries between Redmine and Jira';

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
        // Check the last execution date
        $start_date = file_get_contents('last-exec-redmine-jira.log');
        $start_date = explode('-', date('Y-n-j', strtotime($start_date)));
        $start_date = mktime(0,0,0, $start_date[1], $start_date[2]+1, $start_date[0]);
        $start_date = date('Y-m-d', $start_date);

        // From last execution date, until today - 2 days
        $end_date   = date('Y-m-d', mktime(0,0,0,date('n'),date('j')-2));

        $this->call('time-entries:sync', [
            'method' => 'Redmine-Jira', 'date' => $start_date.'|'.$end_date
        ]);

        file_put_contents('last-exec-redmine-jira.log', $end_date);
    }
}
