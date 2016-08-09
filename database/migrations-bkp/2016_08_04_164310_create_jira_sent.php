<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateJiraSent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jira_sent', function (Blueprint $table) {
            $table->increments('id');
						$table->integer('report_id')->unsigned();
						$table->foreign('report_id')->references('id')->on('toggl_reports')->onDelete('cascade')->onUpdate('cascade');
						$table->string('task', 100);
						$table->string('date', 30);
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
        Schema::drop('jira_sent');
    }
}
