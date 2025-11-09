<?php

$file = 'app/Models/Device.php';
$content = file_get_contents($file);

// Add new fields to fillable
$content = str_replace(
    "        'app_running_status',\n        'last_updated_at',",
    "        'app_running_status',\n        'current_call_status',\n        'current_call_number',\n        'call_started_at',\n        'last_updated_at',",
    $content
);

// Add call_started_at to casts
$content = str_replace(
    "        'last_updated_at' => 'datetime',\n        'created_at' => 'datetime',\n        'updated_at' => 'datetime',",
    "        'call_started_at' => 'datetime',\n        'last_updated_at' => 'datetime',\n        'created_at' => 'datetime',\n        'updated_at' => 'datetime',",
    $content
);

file_put_contents($file, $content);
echo "Updated Device model\n";
