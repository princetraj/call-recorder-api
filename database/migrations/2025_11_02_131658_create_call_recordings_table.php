<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCallRecordingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('call_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_log_id')->constrained()->onDelete('cascade');
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->bigInteger('file_size');
            $table->string('file_type', 50);
            $table->integer('duration')->nullable();
            $table->timestamps();

            $table->index('call_log_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('call_recordings');
    }
}
