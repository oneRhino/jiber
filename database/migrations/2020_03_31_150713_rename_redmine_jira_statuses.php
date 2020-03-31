<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameRedmineJiraStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('redmine_jira_statuses', 'redmine_statuses');

        Schema::table('redmine_statuses', function (Blueprint $table) {
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
        Schema::rename('redmine_statuses', 'redmine_jira_statuses');

        Schema::table('redmine_jira_statuses', function (Blueprint $table) {
            $table->dropColumn('clubhouse_name');
        });
    }
}
