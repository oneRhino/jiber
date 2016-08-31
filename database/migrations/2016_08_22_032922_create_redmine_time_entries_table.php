<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRedmineTimeEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('redmine_time_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('report_id')->unsigned();
            $table->foreign('report_id')->references('id')->on('redmine_reports')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('redmine_id')->unsigned();
            $table->string('jira', 60)->nullable();
            $table->date('date');
            $table->string('time', 30);
            $table->string('description', 200);
            $table->float('duration');
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
        Schema::drop('redmine_time_entries');
    }
}
