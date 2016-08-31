<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTogglTimeEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('toggl_time_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('toggl_id')->unsigned();
            $table->integer('report_id')->unsigned();
            $table->foreign('report_id')->references('id')->on('toggl_reports')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('client_id')->unsigned()->nullable();
            $table->foreign('client_id')->references('id')->on('toggl_clients')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('project_id')->unsigned()->nullable();
            $table->foreign('project_id')->references('id')->on('toggl_projects')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('task_id')->unsigned()->nullable();
            $table->foreign('task_id')->references('id')->on('toggl_tasks')->onDelete('cascade')->onUpdate('cascade');
            $table->string('redmine', 30)->nullable();
            $table->string('jira', 60)->nullable();
            $table->date('date');
            $table->string('time', 30);
            $table->string('description', 200);
            $table->integer('duration')->unsigned();
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
        Schema::drop('toggl_time_entries');
    }
}
