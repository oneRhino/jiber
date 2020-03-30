<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRedmineProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('redmine_projects', function (Blueprint $table) {
            $table->string('third_party', 20)->nullable()->after('project_name');
            $table->string('third_party_project_name', 50)->nullable()->after('third_party');
            $table->string('third_party_project_id', 20)->nullable()->after('third_party_project_name');
            $table->text('content')->nullable()->after('third_party_project_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('redmine_projects', function (Blueprint $table) {
            $table->dropColumn('third_party');
            $table->dropColumn('third_party_project_name');
            $table->dropColumn('third_party_project_id');
        });
    }
}
