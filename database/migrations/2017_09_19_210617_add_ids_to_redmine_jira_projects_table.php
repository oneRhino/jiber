<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIdsToRedmineJiraProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('redmine_jira_projects', function (Blueprint $table) {
            $table->integer('id_redmine')->after('name_redmine')->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('redmine_jira_projects', function (Blueprint $table) {
            $table->dropColumn('id_redmine');
        });
    }
}
