<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateClubhouseStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clubhouse_statuses', function($table) {
            $table->json('projects')->nullable();
            $table->string('workflow_group')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clubhouse_statuses', function($table) {
            $table->dropColumn('projects');
            $table->dropColumn('workflow_group');
        });
    }
}
