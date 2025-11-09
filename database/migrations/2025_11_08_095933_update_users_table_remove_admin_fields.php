<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUsersTableRemoveAdminFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign keys first
            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropForeign(['branch_id']);
            }
            if (Schema::hasColumn('users', 'center_id')) {
                $table->dropForeign(['center_id']);
            }

            // Drop columns
            $table->dropColumn(['role', 'center_id', 'phone']);

            // Add mobile column
            $table->string('mobile', 20)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'user'])->default('user')->after('email');
            $table->foreignId('center_id')->nullable()->constrained('centers')->onDelete('set null')->after('branch_id');
            $table->index(['role', 'status']);
            $table->dropColumn('mobile');
            $table->string('phone', 20)->nullable()->after('email');
        });
    }
}
