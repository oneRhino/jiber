<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTogglReports extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('toggl_reports', function (Blueprint $table) {
            $table->increments('id');
						$table->text('client_ids')->nullable();
						$table->text('project_ids')->nullable();
						$table->date('start_date');
						$table->date('end_date');
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
        Schema::drop('toggl_reports');
    }
}
