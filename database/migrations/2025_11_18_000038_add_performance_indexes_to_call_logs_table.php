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
        // Check which indexes already exist
        $existingIndexes = collect(DB::select('SHOW INDEX FROM call_logs'))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        Schema::table('call_logs', function (Blueprint $table) use ($existingIndexes) {
            // Index for search by caller name (scopeSearch)
            // Note: idx_caller_number already exists, so we skip it
            // Using prefix index (191 chars) to avoid "Specified key was too long" error
            // 191 chars * 4 bytes (utf8mb4) = 764 bytes, which is under the 1000 byte limit
            if (!in_array('idx_caller_name', $existingIndexes)) {
                DB::statement('ALTER TABLE call_logs ADD INDEX idx_caller_name (caller_name(191))');
            }

            // Note: The following indexes already exist with different names:
            // - idx_duplicate_check (equivalent to idx_duplicate_detection)
            // - idx_user_caller_timestamp (equivalent to idx_user_number_time)
            // - idx_caller_number already exists
            // We only add idx_caller_name which was missing
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
            // Only drop the index we added (idx_caller_name)
            // Other indexes (idx_duplicate_check, idx_user_caller_timestamp, idx_caller_number)
            // were added manually and should not be dropped by this migration
            $table->dropIndex('idx_caller_name');
        });
    }
}
