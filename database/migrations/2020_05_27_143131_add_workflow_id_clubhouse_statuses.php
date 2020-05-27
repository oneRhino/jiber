<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWorkflowIdClubhouseStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clubhouse_statuses', function (Blueprint $table) {
            $table->string('workflow_id')->after('clubhouse_name');
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
            $table->dropColumn('workflow_id');
        });
    }
}
