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
        Schema::create('redmine_jira_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('redmine_name');
            $table->integer('redmine_id');
            $table->string('jira_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('redmine_jira_statuses');
    }
}
