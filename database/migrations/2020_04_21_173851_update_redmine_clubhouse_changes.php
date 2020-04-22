<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRedmineClubhouseChanges extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('redmine_clubhouse_changes', function($table) {
            $table->string('clubhouse_comment_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('redmine_clubhouse_changes', function($table) {
            $table->dropColumn('clubhouse_comment_id');
        });
    }
}
