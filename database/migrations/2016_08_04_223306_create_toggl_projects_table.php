<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTogglProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('toggl_projects', function (Blueprint $table) {
            $table->increments('id');
						$table->integer('user_id')->unsigned();
						$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
						$table->integer('toggl_id')->unsigned();
						$table->integer('workspace_id')->unsigned();
						$table->foreign('workspace_id')->references('id')->on('toggl_workspaces')->onDelete('cascade')->onUpdate('cascade');
						$table->integer('client_id')->nullable()->unsigned();
						$table->foreign('client_id')->references('id')->on('toggl_clients')->onDelete('cascade')->onUpdate('cascade');
						$table->boolean('active');
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
        Schema::drop('toggl_projects');
    }
}
