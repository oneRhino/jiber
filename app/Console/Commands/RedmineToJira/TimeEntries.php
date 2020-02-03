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
        $start_date = new \DateTime($start_date);

        // Start date should be the day after the last execution date
        $start_date->modify('+1 day');

        // End date should be previous day from now
        $end_date = new \DateTime('-1 day');

        $this->call('time-entries:sync', [
            'method' => 'Redmine-Jira',
            'date'   => $start_date->format('Y-m-d').'|'.$end_date->format('Y-m-d')
        ]);

        file_put_contents('last-exec-redmine-jira.log', $end_date->format('Y-m-d'));
    }
}
