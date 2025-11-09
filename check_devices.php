<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$devices = DB::table('devices')
    ->select('id', 'device_model', 'current_call_status', 'current_call_number')
    ->get();

echo json_encode($devices, JSON_PRETTY_PRINT);
