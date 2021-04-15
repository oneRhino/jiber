<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\TimeEntriesSync::class,
        Commands\JiraDeleteWorklogs::class,
        Commands\RedmineToJira\CreatedTasks::class,
        Commands\RedmineToJira\UpdatedTasks::class,
        Commands\RedmineToJira\TimeEntries::class,
        Commands\RedmineToJira\CheckTimeEntries::class,
        Commands\RedmineToClubhouse\CreatedTasks::class,
        Commands\RedmineToClubhouse\UpdatedTasks::class,
        Commands\RedmineToToggl\TimeEntries::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
