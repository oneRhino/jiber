<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRedmineJiraUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('redmine_jira_users', function (Blueprint $table) {
            $table->string('redmine_name');
            $table->integer('redmine_id');
            $table->string('jira_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('redmine_jira_users', function (Blueprint $table) {
            $table->dropColumn('redmine_name');
            $table->dropColumn('redmine_id');
            $table->dropColumn('jira_name');
        });
    }
}
