<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameRedmineJiraTrackers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('redmine_jira_trackers', 'redmine_trackers');

        Schema::table('redmine_trackers', function (Blueprint $table) {
            $table->string('clubhouse_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('redmine_trackers', 'redmine_jira_trackers');

        Schema::table('redmine_jira_trackers', function (Blueprint $table) {
            $table->dropColumn('clubhouse_name');
        });
    }
}
