<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClubhouseIdToTogglProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('toggl_projects', function (Blueprint $table) {
            $table->integer('clubhouse_id')->nullable()->unsigned();
            //Remove the following line if disable foreign key
            $table->foreign('clubhouse_id')->references('id')->on('clubhouse_projects');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('toggl_projects', function (Blueprint $table) {
            $table->dropForeign(['toggl_projects_clubhouse_id_foreign']);
            $table->dropColumn('clubhouse_id');
        });
    }
}
