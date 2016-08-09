<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTogglProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('toggl_projects', function (Blueprint $table) {
					$table->dropForeign('toggl_projects_client_id_foreign');
        });
        Schema::table('toggl_projects', function (Blueprint $table) {
					$table->integer('client_id')->unsigned()->nullable()->change();
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
        Schema::table('toggl_projects', function (Blueprint $table) {
					$table->dropForeign('toggl_projects_client_id_foreign');
        });
        Schema::table('toggl_projects', function (Blueprint $table) {
					$table->integer('client_id')->unsigned()->change();
					$table->foreign('client_id')->references('id')->on('toggl_clients')->onDelete('cascade')->onUpdate('cascade');
        });
    }
}
