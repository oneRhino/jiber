<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTogglWorkspaces extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('toggl_workspaces', function (Blueprint $table) {
            $table->increments('id');
						$table->string('name', 200);
            $table->timestamps();
        });

				Schema::table('toggl_clients', function (Blueprint $table) {
					$table->foreign('workspace_id')->references('id')->on('toggl_workspaces')->onDelete('cascade')->onUpdate('cascade');
				});

				Schema::table('toggl_projects', function (Blueprint $table) {
					$table->foreign('workspace_id')->references('id')->on('toggl_workspaces')->onDelete('cascade')->onUpdate('cascade');
				});

				Schema::table('toggl_tasks', function (Blueprint $table) {
					$table->foreign('workspace_id')->references('id')->on('toggl_workspaces')->onDelete('cascade')->onUpdate('cascade');
				});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('toggl_workspaces');
				Schema::table('toggl_clients', function (Blueprint $table) {
					$table->dropForeign('workspace_id');
				});
				Schema::table('toggl_projects', function (Blueprint $table) {
					$table->dropForeign('workspace_id');
				});
				Schema::table('toggl_tasks', function (Blueprint $table) {
					$table->dropForeign('workspace_id');
				});
    }
}
