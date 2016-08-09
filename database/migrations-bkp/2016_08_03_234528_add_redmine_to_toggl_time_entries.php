<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRedmineToTogglTimeEntries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('toggl_time_entries', function (Blueprint $table) {
					$table->string('redmine', 30)->nullable();
					$table->string('jira', 60)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('toggl_time_entries', function (Blueprint $table) {
					$table->dropColumn('redmine');
					$table->string('jira', 60)->change();
        });
    }
}
