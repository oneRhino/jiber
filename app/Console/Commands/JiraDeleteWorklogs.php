<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Setting;
use App\Http\Controllers\JiraController;

class JiraDeleteWorklogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jira:delete-worklogs {user} {date}
                            user : Jira User
                            date : For which date(s) should system delete worklogs. Ex: "2017-04-11" or "2017-04-01|2017-04-15".';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Jira worklogs for a given date or date range.';

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
        $jira_user = $this->argument('user');

        $settings = Setting::where('jira', $jira_user)->first();

        if (!$settings) {
            $this->error("No user {$jira_user} found.");
            die;
        }

        $user = $settings->user;

        // Set user as logged-in user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        Auth::setUser($user);

        $date = $this->argument('date');

        if (strpos($date, '|') !== false)
            $date = explode('|', $date);
        else
            $date = array($date, $date);

        $JiraController = new JiraController;
        $Jira = $JiraController->connect($request);

        // Get all worklogs for the given dates
        $JQL = "worklogAuthor = {$jira_user} and worklogDate >= {$date[0]} and worklogDate <= {$date[1]}";
        $return = $Jira->search($JQL, 0, 100, 'key');
        $result = $return->getResult();

        if (isset($result['issues']) && $result['issues']) {
            foreach ($result['issues'] as $_issue) {
                $this->info($_issue['key']);

                $returnWL = $Jira->getWorklogs($_issue['key'], array());
                $resultWL = $returnWL->getResult();

                foreach ($resultWL['worklogs'] as $_worklog) {
                    if ($_worklog['author']['emailAddress'] != $jira_user) continue;
                    if (strtotime($_worklog['started']) < strtotime($date[0].' 00:00:00') || strtotime($_worklog['started']) > strtotime($date[1].' 23:59:59')) continue;

                    $this->info("- Deleting {$_worklog['timeSpent']} - {$_worklog['started']} - user {$_worklog['author']['name']}");

                    $response = $Jira->deleteWorklog($_worklog['issueId'], $_worklog['id']);
                }
            }
        }
    }
}
