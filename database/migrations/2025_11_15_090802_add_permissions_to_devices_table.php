<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPermissionsToDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('devices', function (Blueprint $table) {
            // Add permission status fields
            $table->boolean('perm_read_call_log')->default(false)->after('app_version');
            $table->boolean('perm_read_phone_state')->default(false)->after('perm_read_call_log');
            $table->boolean('perm_read_contacts')->default(false)->after('perm_read_phone_state');
            $table->boolean('perm_read_external_storage')->default(false)->after('perm_read_contacts');
            $table->boolean('perm_read_media_audio')->default(false)->after('perm_read_external_storage');
            $table->boolean('perm_post_notifications')->default(false)->after('perm_read_media_audio');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'perm_read_call_log',
                'perm_read_phone_state',
                'perm_read_contacts',
                'perm_read_external_storage',
                'perm_read_media_audio',
                'perm_post_notifications',
            ]);
        });
    }
}
