<?php

namespace App\Console\Commands;

use Log;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\RedmineComment;
use App\Setting;
use App\User;
use App\Http\Controllers\RedmineController;
use App\Http\Controllers\JiraController;

class RedmineComments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redminecomments:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs comments from Redmine and Jira.';

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
        // Grab comments from Redmine
        $comments = $this->getRedmineComments();

        // Send comments to Jira
        $this->sendCommentsJira($comments);
    }

    /**
     * Use user "thaissa" to grab comments from all tasks
     * that have been added in the current date
     * and have not been sent to Jira yet
     */
    private function getRedmineComments() {
        // Get user
        $user = User::find(1);

        // Set user as logged-in user
        $request = new Request();
        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        Auth::setUser($user);

        // Current date
        $date = date('Y-m-d');

        // Get Redmine tasks updated this date
        $RedmineController = new RedmineController;
        $Redmine = $RedmineController->connect();
        $args = array(
            'updated_on' => $date,
            'limit'      => 100,
            'sort'       => 'updated_on:desc',
        );
        $redmine_entries = $Redmine->issue->all($args);

        #Log::info('All Redmine entries found.');
        #Log::info(print_r($redmine_entries, true));

        $comments = array();

        foreach ($redmine_entries['issues'] as $_issue) {
            $jira = false;

            #Log::info('Checking Redmine task '.$_issue['id']);

            foreach ($_issue['custom_fields'] as $_field) {
                if ($_field['name'] == 'Jira ID' && !empty($_field['value']))
                    $jira = $_field['value'];
            }

            if (!$jira) {
                #Log::info('It doesnt have Jira ID');
                continue;
            }

            #Log::info('It has Jira ID '.$jira);

            $args   = array('include' => 'journals');

            $_entry = $Redmine->issue->show($_issue['id'], $args);

            #Log::info('Entry details:');
            #Log::info(print_r($_entry, true));

            foreach ($_entry['issue']['journals'] as $_journal) {
                if (isset($_journal['notes']) && !empty($_journal['notes'])) {

                    // Check if comment has been created within the past 10 min
                    $created = strtotime($_journal['created_on']);
                    $lastmin = mktime(date('H'), date('i')-10);

                    if ($created < $lastmin) continue;

                    // Check if comment hasn't already been sent to jira
                    $redmine_comment = RedmineComment::where('redmine_comment_id', $_journal['id'])->first();

                    if ($redmine_comment) continue;

                    // NOT SETTING DATE - Jira apparently ignores that
                    $date = preg_replace('/([-+][0-9]{2}):([0-9]{2})$/', '.000${1}${2}', date('c', strtotime($_journal['created_on'])));

                    $comment = array(
                        'id'   => $_journal['id'],
                        'body' => $_journal['notes'],
                        'created' => $date,
                        //'updated' => $date,
                    );
                    $comments[$_journal['user']['name']][$jira][] = $comment;
                }
            }
        }

        return $comments;
    }

    private function sendCommentsJira($comments)
    {
        if (!$comments) return false;

        $JiraController = new JiraController;

        foreach ($comments as $_user => $_tasks) {
            // Get user
            $settings = Setting::where('redmine_user', $_user)->first();

            if (!$settings) {
                $this->error("No user {$_user} found.");
                continue;
            }

            $user = $settings->user;

            // Set user as logged-in user
            $request = new Request();
            $request->merge(['user' => $user]);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            Auth::setUser($user);

            $Jira = $JiraController->connect($request);

            foreach ($_tasks as $_task => $_comments) {
                foreach ($_comments as $_comment) {
                    $comment_id = $_comment['id'];
                    unset($_comment['id']);

                    $result = $Jira->addComment($_task, $_comment);

                    if ($result) {
                        $Comment = new RedmineComment();
                        $Comment->redmine_comment_id = $comment_id;
                        $Comment->save();
                    }
                }
            }
        }
    }
}
