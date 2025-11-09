<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;

echo "Monitoring device updates (press Ctrl+C to stop)...\n";
echo str_repeat("-", 80) . "\n";

$lastUpdate = null;

while (true) {
    $device = Device::first();

    if ($device) {
        $currentUpdate = $device->last_updated_at->format('Y-m-d H:i:s');

        if ($currentUpdate !== $lastUpdate) {
            echo "\n[" . date('H:i:s') . "] ⚡ UPDATE DETECTED!\n";
            echo "  Device: {$device->device_model}\n";
            echo "  Battery: {$device->battery_percentage}%\n";
            echo "  Connection: {$device->connection_type}\n";
            echo "  Signal: {$device->signal_strength_label}\n";
            echo "  Status: {$device->app_running_status}\n";
            echo "  Updated: {$currentUpdate}\n";

            $lastUpdate = $currentUpdate;
        } else {
            $minutesAgo = $device->last_updated_at->diffInMinutes(now());
            $nextIn = 5 - ($minutesAgo % 5);
            echo "\r[" . date('H:i:s') . "] Last update: {$minutesAgo}m ago | Next in: ~{$nextIn}m | " .
                 "Battery: {$device->battery_percentage}% | " .
                 "Connection: {$device->connection_type}    ";
        }
    }

    sleep(5); // Check every 5 seconds
}
