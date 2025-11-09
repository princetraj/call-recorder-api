<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCallStatusToDevicesTable extends Migration
{
    public function up()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->enum('current_call_status', ['idle', 'ringing', 'offhook', 'incoming', 'outgoing'])->default('idle')->after('app_running_status');
            $table->string('current_call_number', 20)->nullable()->after('current_call_status');
            $table->timestamp('call_started_at')->nullable()->after('current_call_number');
        });
    }

    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['current_call_status', 'current_call_number', 'call_started_at']);
        });
    }
}
