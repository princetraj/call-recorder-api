<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set a test device to in_call status
DB::table('devices')
    ->where('id', 1)
    ->update([
        'current_call_status' => 'in_call',
        'current_call_number' => '+1234567890',
        'call_started_at' => now(),
    ]);

echo "Device updated to in_call status with number +1234567890\n";
echo "Check the admin panel now at /devices\n";
echo "\nTo reset back to idle, run this script with 'reset' argument\n";

// If reset argument provided
if (isset($argv[1]) && $argv[1] === 'reset') {
    DB::table('devices')
        ->where('id', 1)
        ->update([
            'current_call_status' => 'idle',
            'current_call_number' => null,
            'call_started_at' => null,
        ]);
    echo "Device reset to idle status\n";
}
