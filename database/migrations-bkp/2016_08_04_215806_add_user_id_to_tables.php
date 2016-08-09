<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUserIdToTables extends Migration
{
		private $tables = array('toggl_workspaces','toggl_clients','toggl_projects','toggl_reports','toggl_tasks','toggl_time_entries','redmine_sent','jira_sent');

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
			foreach ($this->tables as $_table)
			{
        Schema::table($_table, function (Blueprint $table) {
					$table->integer('user_id')->unsigned();
					$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
			}
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
			foreach ($this->tables as $_table)
			{
        Schema::table($_table, function (Blueprint $table) {
					$table->dropForeign('user_id');
					$table->dropColumn('user_id');
        });
			}
    }
}
