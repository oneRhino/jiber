<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTogglTasks extends Migration
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
						$table->integer('workspace_id')->unsigned();
						$table->integer('project_id')->unsigned();
						$table->foreign('project_id')->references('id')->on('toggl_projects')->onDelete('cascade')->onUpdate('cascade');
						$table->boolean('active');
						$table->integer('tracked');
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
