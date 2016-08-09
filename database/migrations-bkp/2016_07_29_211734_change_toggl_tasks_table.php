<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTogglTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('toggl_tasks', function (Blueprint $table) {
            $table->integer('estimated')->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('toggl_tasks', function (Blueprint $table) {
            $table->dropColumn('estimated');
        });
    }
}
