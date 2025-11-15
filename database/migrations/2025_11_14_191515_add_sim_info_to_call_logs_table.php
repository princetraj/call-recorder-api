<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSimInfoToCallLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->integer('sim_slot_index')->nullable()->after('call_timestamp');
            $table->string('sim_name', 100)->nullable()->after('sim_slot_index');
            $table->string('sim_number', 50)->nullable()->after('sim_name');
            $table->string('sim_serial_number', 100)->nullable()->after('sim_number');
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
            $table->dropColumn(['sim_slot_index', 'sim_name', 'sim_number', 'sim_serial_number']);
        });
    }
}
