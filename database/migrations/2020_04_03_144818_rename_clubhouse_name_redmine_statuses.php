<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameClubhouseNameRedmineStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('redmine_statuses', function (Blueprint $table) {
            $table->renameColumn('clubhouse_name', 'clubhouse_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('redmine_statuses', function (Blueprint $table) {
            $table->renameColumn('clubhouse_id', 'clubhouse_name');
        });
    }
}
