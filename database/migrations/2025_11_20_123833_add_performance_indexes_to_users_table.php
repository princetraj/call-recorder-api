<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexesToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Add indexes for frequently searched columns
            $table->index('name'); // Used in LIKE searches
            $table->index('mobile'); // Used in searches
            $table->index('created_at'); // Used in ORDER BY

            // Note: username already has unique index
            // Note: branch_id already has index from foreign key constraint
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes in reverse order
            $table->dropIndex(['created_at']);
            $table->dropIndex(['mobile']);
            $table->dropIndex(['name']);
        });
    }
}
