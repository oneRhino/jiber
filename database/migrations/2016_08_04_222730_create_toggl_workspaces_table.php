<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTogglWorkspacesTable extends Migration
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
           $table->integer('toggl_id')->unsigned();
           $table->integer('user_id')->unsigned();
           $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::drop('toggl_workspaces');
    }
}
