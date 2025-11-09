<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('admin_role', ['super_admin', 'manager', 'trainee'])->default('trainee');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->rememberToken();
            $table->timestamps();

            $table->index(['admin_role', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admins');
    }
}
