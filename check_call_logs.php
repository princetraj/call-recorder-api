<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CallLog;

$count = CallLog::count();
echo "Total call logs in database: {$count}\n";

if ($count > 0) {
    $latest = CallLog::latest()->first();
    echo "\nLatest call log:\n";
    echo "ID: {$latest->id}\n";
    echo "Caller: {$latest->caller_number}\n";
    echo "Type: {$latest->call_type}\n";
    echo "Timestamp: {$latest->call_timestamp}\n";
    echo "Created: {$latest->created_at}\n";
}
