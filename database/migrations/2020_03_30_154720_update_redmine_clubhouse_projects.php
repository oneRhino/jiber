<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRedmineClubhouseProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('redmine_clubhouse_projects', 'clubhouse_projects');

        Schema::table('clubhouse_projects', function (Blueprint $table) {
            $table->dropColumn('redmine_id');
            $table->dropColumn('redmine_name');
            $table->dropColumn('content');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('clubhouse_projects', 'redmine_clubhouse_projects');

        Schema::table('redmine_clubhouse_projects', function (Blueprint $table) {
            $table->int('redmine_id');
            $table->string('redmine_name');
            $table->text('content');
        });
    }
}
