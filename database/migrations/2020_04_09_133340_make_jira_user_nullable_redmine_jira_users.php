<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeJiraUserNullableRedmineJiraUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('redmine_jira_users', function (Blueprint $table) {
            $table->string('jira_name')->nullable()->change();
            $table->string('jira_code')->nullable()->change();
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
            $table->string('jira_name')->change();
            $table->string('jira_code')->change();
        });
    }
}
