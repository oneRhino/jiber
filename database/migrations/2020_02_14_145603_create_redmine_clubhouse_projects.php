<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRedmineClubhouseProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('redmine_clubhouse_projects', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('redmine_id')->unsigned()->nullable();
            $table->string('redmine_name')->nullable();
            $table->integer('clubhouse_id')->unsigned()->nullable();
            $table->string('clubhouse_name')->nullable();
            $table->text('content')->nullable();
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
        Schema::drop('redmine_clubhouse_projects');
    }
}
