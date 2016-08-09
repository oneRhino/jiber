<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTogglClients extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('toggl_clients', function (Blueprint $table) {
            $table->increments('id');
						$table->integer('workspace_id')->unsigned();
						$table->string('name', 200);
						$table->string('services')->nullable();
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
        Schema::drop('toggl_clients');
    }
}
