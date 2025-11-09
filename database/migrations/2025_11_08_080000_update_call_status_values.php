<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateCallStatusValues extends Migration
{
    public function up()
    {
        // First, update existing data to match new values
        DB::table('devices')
            ->whereIn('current_call_status', ['ringing', 'offhook', 'incoming', 'outgoing'])
            ->update(['current_call_status' => 'in_call']);

        // Now alter the column to only allow 'idle' and 'in_call'
        DB::statement("ALTER TABLE devices MODIFY current_call_status ENUM('idle', 'in_call') DEFAULT 'idle'");
    }

    public function down()
    {
        // Revert back to the old enum values
        DB::statement("ALTER TABLE devices MODIFY current_call_status ENUM('idle', 'ringing', 'offhook', 'incoming', 'outgoing') DEFAULT 'idle'");
    }
}
