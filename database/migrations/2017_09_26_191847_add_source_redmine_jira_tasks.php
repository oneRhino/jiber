<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSourceRedmineJiraTasks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('redmine_jira_tasks', function (Blueprint $table) {
            $table->string('source');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('redmine_jira_tasks', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
}
