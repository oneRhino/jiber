<?php

namespace App\Console\Commands\RedmineToJira;

use App\{Report, Setting};
use App\Http\Controllers\{JiraController, RedmineReportController};
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mail;

class CheckTimeEntries extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redmine-to-jira:check-time-entries {timeframe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check time entries between Redmine and Jira';

    /**
     * Current Request
     */
    private $request;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $differences = [];

        $this->request = new Request();

        list($start_date, $end_date) = $this->getTimeframe();

        $this->request->date = $start_date->format('Y-m-d') . ' - ' . $end_date->format('Y-m-d');

        // Get all users
        $users = Setting::where(['redmine_jira_sync' => true])->get();

        foreach ($users as $_user) {
            try {
                // Login user
                $this->loginUser($_user);

                // Create Redmine report
                $report = $this->createReport();

                // Get Jira Entries
                $JiraController = new JiraController();
                $entries = $JiraController->show($report, $this->request, true);

                if (!$entries) {
                    $this->info('No Jira tasks have been found in the period.');
                    continue;
                }

                // Get Entries differences
                $differences[$_user->user->name] = $this->getEntriesDifferences($entries);

                if (!$differences[$_user->user->name]) {
                    unset($differences[$_user->user->name]);
                    $this->info('No differences have been found in the period.');
                    continue;
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }

        if ($differences) {
            $data = [
                'start_date'  => $start_date->format('m/d/Y'),
                'end_date'    => $end_date->format('m/d/Y'),
                'differences' => $differences,
            ];

            Mail::send('emails.differencces_report', $data, function ($m) {
                $m->from('noreply@onerhino.com', 'Jiber');
                $m->to('thaissa.mendes@gmail.com')->subject('Redmine-Jira Time Differences Report');
            });
        }
    }

    private function getEntriesDifferences(array $entries):array {
        $differences = [];

        foreach ($entries as $_entry) {
            foreach ($_entry as $_key => $_value) {
                // Ignore values that are not integers
                if (!is_int($_key)) continue;

                // Ignore when redmine time is equal to jira time
                if ($_entry['entry_total'] == $_value['entry_total']) continue;

                // Ignore when difference between redmine and jira is less than the discrepancy
                $difference = $_entry['entry_total'] - $_value['entry_total'];
                if ($difference < $this->getDiscrepancy()) continue;

                // Add redmine and jira ids to differences array
                foreach ($_value['entry_entries'] as $_jira_entry) {
                    $key = $_key . '|' . $_jira_entry->jira_issue_id;
                    $differences[$key] = $difference;
                }
            }
        }

        return $differences;
    }

    private function getDiscrepancy():float {
        switch ($this->argument('timeframe')) {
            case '72h':
                $discrepancy = 0.5;
                break;

            case 'week':
                $discrepancy = 1;
                break;

            case 'month':
                $discrepancy = 2;
                break;
        }

        return $discrepancy;
    }

    private function createReport():Report {
        // Create a report for the requested date(s)
        $RedmineController = new RedmineReportController($this->request);

        $this->request->filter_user = true;

        $report_id = $RedmineController->save($this->request, false);

        return Report::find($report_id);
    }

    private function loginUser(Setting $settings):void {
        $user = $settings->user;

        $this->info('Running sync for '.$user->name);

        // Check if user has jira password
        if (!$settings->jira_password) {
            throw new \Exception("User {$user->name} does not have a Jira Password.");
        }

        // Set user as logged-in user
        $this->request->merge(['user' => $user]);
        $this->request->setUserResolver(function () use ($user) {
            return $user;
        });

        Auth::setUser($user);
    }

    private function getTimeframe():array {
        switch ($this->argument('timeframe')) {
            case '72h':
                $start_date = '-3 days';
                break;

            case 'week':
                $start_date = '-7 days';
                break;

            case 'month':
                $start_date = '-1 month';
                break;
        }

        $end_date   = '-1 day';

        $start_date = new \DateTime($start_date);
        $end_date   = new \DateTime($end_date);

        return [$start_date, $end_date];
    }
}
