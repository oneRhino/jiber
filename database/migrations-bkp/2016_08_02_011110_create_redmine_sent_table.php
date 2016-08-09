<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRedmineSentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('redmine_sent', function (Blueprint $table) {
            $table->increments('id');
						$table->integer('report_id')->unsigned();
						$table->foreign('report_id')->references('id')->on('toggl_reports')->onDelete('cascade')->onUpdate('cascade');
						$table->date('date');
						$table->integer('task');
						$table->float('duration');
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
        Schema::drop('redmine_sent');
    }
}
