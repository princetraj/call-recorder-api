<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCallLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('caller_name', 255)->nullable();
            $table->string('caller_number', 50);
            $table->enum('call_type', ['incoming', 'outgoing', 'missed', 'rejected']);
            $table->integer('call_duration')->default(0);
            $table->dateTime('call_timestamp');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'call_type']);
            $table->index('call_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('call_logs');
    }
}
