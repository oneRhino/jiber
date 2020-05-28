<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPositionClubhouseStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clubhouse_statuses', function (Blueprint $table) {
            $table->integer('position')->after('workflow_id');
            $table->string('type')->after('workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clubhouse_statuses', function (Blueprint $table) {
            $table->dropColumn('position');
            $table->dropColumn('type');
        });
    }
}
