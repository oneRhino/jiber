<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTogglTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('toggl_tasks', function (Blueprint $table) {
            $table->increments('id');
						$table->integer('user_id')->unsigned();
						$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
						$table->integer('toggl_id')->unsigned();
						$table->integer('workspace_id')->unsigned();
						$table->foreign('workspace_id')->references('id')->on('toggl_workspaces')->onDelete('cascade')->onUpdate('cascade');
						$table->integer('project_id')->unsigned();
						$table->foreign('project_id')->references('id')->on('toggl_projects')->onDelete('cascade')->onUpdate('cascade');
						$table->boolean('active');
						$table->integer('tracked')->unsigned()->nullable();
            $table->integer('estimated')->unsigned()->nullable();
						$table->string('name', 200);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('toggl_tasks');
    }
}
