<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('device_id', 191)->unique(); // Unique device identifier (Android ID)
            $table->string('device_model', 100)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('connection_type', 20)->nullable(); // wifi, mobile, none
            $table->integer('battery_percentage')->nullable();
            $table->integer('signal_strength')->nullable(); // 0-4 (NONE, POOR, MODERATE, GOOD, GREAT)
            $table->boolean('is_charging')->default(false);
            $table->enum('app_running_status', ['active', 'background', 'stopped'])->default('active');
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('last_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('devices');
    }
}
