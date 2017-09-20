<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRedmineJiraProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('redmine_jira_projects', function (Blueprint $table) {
            $table->renameColumn('name_redmine', 'redmine_name');
            $table->renameColumn('id_redmine', 'redmine_id');
            $table->renameColumn('name_jira', 'jira_name');
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
            $table->renameColumn('redmine_name','name_redmine');
            $table->renameColumn('redmine_id','id_redmine');
            $table->renameColumn('jira_name','name_jira');
        });
    }
}
