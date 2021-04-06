<?php

namespace App\Console\Commands\RedmineToToggl;

use Illuminate\Console\Command;

class TimeEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redmine-to-toggl:time-entries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync time entries between Redmine and Toggl';

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
        // Date should be previous day from now
        $date = new \DateTime('-1 day');

        $this->call('time-entries:sync', [
            'method' => 'Redmine-Toggl',
            'date'   => $date->format('Y-m-d')
        ]);
    }
}
