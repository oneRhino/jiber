<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRedmineClubhouseTrackers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('redmine_clubhouse_trackers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('redmine_name');
            $table->integer('redmine_id');
            $table->string('clubhouse_name');
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
        Schema::drop('clubhouse_trackers');
    }
}
