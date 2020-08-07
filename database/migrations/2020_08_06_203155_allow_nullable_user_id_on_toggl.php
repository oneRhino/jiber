<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AllowNullableUserIdOnToggl extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('toggl_workspaces',function(Blueprint $table){
            //Or disable foreign check with: 
            //Schema::disableForeignKeyConstraints();
            $table->dropForeign('toggl_workspaces_user_id_foreign');
            $table->integer('user_id')->nullable()->unsigned()->change();
            //Remove the following line if disable foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('toggl_clients',function(Blueprint $table){
            //Or disable foreign check with: 
            //Schema::disableForeignKeyConstraints();
            $table->dropForeign('toggl_clients_user_id_foreign');
            $table->integer('user_id')->nullable()->unsigned()->change();
            //Remove the following line if disable foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('toggl_projects',function(Blueprint $table){
            //Or disable foreign check with: 
            //Schema::disableForeignKeyConstraints();
            $table->dropForeign('toggl_projects_user_id_foreign');
            $table->integer('user_id')->nullable()->unsigned()->change();
            //Remove the following line if disable foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('toggl_tasks',function(Blueprint $table){
            //Or disable foreign check with: 
            //Schema::disableForeignKeyConstraints();
            $table->dropForeign('toggl_tasks_user_id_foreign');
            $table->integer('user_id')->nullable()->unsigned()->change();
            //Remove the following line if disable foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('toggl_workspaces',function(Blueprint $table){
            //Or disable foreign check with: 
            //Schema::disableForeignKeyConstraints();
            $table->dropForeign('toggl_workspaces_user_id_foreign');
            $table->integer('user_id')->unsigned()->change();
            //Remove the following line if disable foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('toggl_clients',function(Blueprint $table){
            //Or disable foreign check with: 
            //Schema::disableForeignKeyConstraints();
            $table->dropForeign('toggl_clients_user_id_foreign');
            $table->integer('user_id')->unsigned()->change();
            //Remove the following line if disable foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('toggl_projects',function(Blueprint $table){
            //Or disable foreign check with: 
            //Schema::disableForeignKeyConstraints();
            $table->dropForeign('toggl_projects_user_id_foreign');
            $table->integer('user_id')->unsigned()->change();
            //Remove the following line if disable foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('toggl_tasks',function(Blueprint $table){
            //Or disable foreign check with: 
            //Schema::disableForeignKeyConstraints();
            $table->dropForeign('toggl_tasks_user_id_foreign');
            $table->integer('user_id')->unsigned()->change();
            //Remove the following line if disable foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
}
