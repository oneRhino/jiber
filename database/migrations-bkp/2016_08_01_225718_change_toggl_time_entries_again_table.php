<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTogglTimeEntriesAgainTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('toggl_time_entries', function (Blueprint $table) {
					$table->integer('client_id')->nullable()->unsigned()->after('report_id');
					$table->foreign('client_id')->references('id')->on('toggl_clients')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('toggl_time_entries', function (Blueprint $table) {
					$table->dropForeign('client_id');
					$table->dropColumn('client_id');
        });
    }
}
