<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameRedmineIssueId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('redmine_time_entries', function (Blueprint $table) {
            $table->renameColumn('issue_id', 'redmine_issue_id');
        });

        Schema::table('toggl_time_entries', function (Blueprint $table) {
            $table->renameColumn('redmine', 'redmine_issue_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('redmine_time_entries', function (Blueprint $table) {
            $table->renameColumn('redmine_issue_id', 'issue_id');
        });

        Schema::table('toggl_time_entries', function (Blueprint $table) {
            $table->renameColumn('redmine_issue_id', 'redmine');
        });
    }
}
