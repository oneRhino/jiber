<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTogglTicketIdToClubhouseStories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clubhouse_stories', function (Blueprint $table) {
            $table->integer('toggl_task_id')->nullable()->unsigned();
            //Remove the following line if disable foreign key
            $table->foreign('toggl_task_id')->references('id')->on('toggl_tasks');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clubhouse_stories', function (Blueprint $table) {
            $table->dropForeign(['clubhouse_stories_toggl_task_id_foreign']);
            $table->dropColumn('toggl_task_id');
        });
    }
}
