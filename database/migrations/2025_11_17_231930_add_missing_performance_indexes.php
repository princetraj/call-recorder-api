<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add user_type index to user_login_activities table
        // This column is frequently filtered in LoginActivityController
        Schema::table('user_login_activities', function (Blueprint $table) {
            $table->index('user_type', 'idx_user_type');
        });

        // Add status index to branches table (optional but useful if branches table grows)
        Schema::table('branches', function (Blueprint $table) {
            $table->index('status', 'idx_branches_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_login_activities', function (Blueprint $table) {
            $table->dropIndex('idx_user_type');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex('idx_branches_status');
        });
    }
}
