<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexesToCallLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('call_logs', function (Blueprint $table) {
            // Index for caller_number searches and productivity tracking
            $table->index('caller_number', 'idx_caller_number');

            // Index for call_duration sorting and filtering
            $table->index('call_duration', 'idx_call_duration');

            // Composite index for productivity tracking queries
            // Optimizes queries: WHERE user_id = X AND caller_number = Y ORDER BY call_timestamp
            $table->index(['user_id', 'caller_number', 'call_timestamp'], 'idx_user_caller_timestamp');

            // Composite index for duplicate detection
            // Optimizes: WHERE user_id = X AND caller_number = Y AND call_type = Z AND call_timestamp BETWEEN ...
            $table->index(['user_id', 'caller_number', 'call_type', 'call_timestamp'], 'idx_duplicate_check');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropIndex('idx_caller_number');
            $table->dropIndex('idx_call_duration');
            $table->dropIndex('idx_user_caller_timestamp');
            $table->dropIndex('idx_duplicate_check');
        });
    }
}
