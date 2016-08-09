<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTogglTimeEntries extends Migration
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
						$table->integer('entry_id')->unsigned();
						$table->integer('report_id')->unsigned();
						$table->foreign('report_id')->references('id')->on('toggl_reports')->onDelete('cascade')->onUpdate('cascade');
						$table->integer('project_id')->unsigned();
						$table->foreign('project_id')->references('id')->on('toggl_projects')->onDelete('cascade')->onUpdate('cascade');
						$table->integer('task_id')->unsigned()->nullable();
						$table->foreign('task_id')->references('id')->on('toggl_tasks')->onDelete('cascade')->onUpdate('cascade');
						$table->date('date');
						$table->integer('duration')->unsigned();
						$table->boolean('ignored');
						$table->boolean('sent');
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
