<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUploadSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('upload_id', 191)->unique(); // UUID for upload session
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('call_log_id')->constrained()->onDelete('cascade');
            $table->integer('total_chunks');
            $table->integer('received_chunks')->default(0);
            $table->bigInteger('total_size')->nullable(); // Total file size in bytes
            $table->string('original_filename', 255);
            $table->string('file_type', 50)->nullable();
            $table->integer('duration')->nullable();
            $table->string('checksum', 32)->nullable(); // MD5 checksum of complete file
            $table->enum('status', ['pending', 'uploading', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Auto-cleanup old sessions
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('upload_sessions');
    }
}
