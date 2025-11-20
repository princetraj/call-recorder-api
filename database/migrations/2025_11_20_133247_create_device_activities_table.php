<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeviceActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('device_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->enum('action_type', ['logout', 'removal'])->comment('Type of action: logout or removal');
            $table->string('device_name')->nullable()->comment('Device name at the time of action');
            $table->string('device_model')->nullable()->comment('Device model at the time of action');
            $table->string('device_id_value')->nullable()->comment('Device ID value at the time of action');
            $table->string('ip_address')->nullable()->comment('IP address of admin who performed the action');
            $table->text('user_agent')->nullable()->comment('User agent of admin who performed the action');
            $table->text('notes')->nullable()->comment('Additional notes or reason for the action');
            $table->timestamp('performed_at')->useCurrent()->comment('When the action was performed');
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['admin_id', 'performed_at']);
            $table->index(['user_id', 'performed_at']);
            $table->index(['action_type', 'performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('device_activities');
    }
}
