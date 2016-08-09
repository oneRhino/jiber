<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddJiraToTogglTimeEntries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('toggl_time_entries', function (Blueprint $table) {
					$table->string('jira', 60);
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
					$table->dropColumn('jira');
        });
    }
}
