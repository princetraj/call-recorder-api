<?php

$file = 'app/Http/Controllers/Api/DeviceController.php';
$content = file_get_contents($file);

// Add current_call_status to validation in updateStatus method
$old_validation = "'app_running_status' => 'nullable|string|in:active,background,stopped',";
$new_validation = "'app_running_status' => 'nullable|string|in:active,background,stopped',\n            'current_call_status' => 'nullable|string|in:idle,ringing,offhook,incoming,outgoing',\n            'current_call_number' => 'nullable|string|max:20',";

$content = str_replace($old_validation, $new_validation, $content);

// Add fields to update array in updateStatus method
$old_update = "'app_running_status' => \$request->app_running_status ?? \$device->app_running_status,\n                'last_updated_at' => now(),";
$new_update = "'app_running_status' => \$request->app_running_status ?? \$device->app_running_status,\n                'current_call_status' => \$request->current_call_status ?? \$device->current_call_status,\n                'current_call_number' => \$request->current_call_number ?? \$device->current_call_number,\n                'call_started_at' => \$request->current_call_status !== 'idle' ? (\$device->current_call_status === 'idle' ? now() : \$device->call_started_at) : null,\n                'last_updated_at' => now(),";

$content = str_replace($old_update, $new_update, $content);

// Add to index response
$old_response = "'is_online' => \$device->isOnline(),\n                ];";
$new_response = "'is_online' => \$device->isOnline(),\n                    'current_call_status' => \$device->current_call_status,\n                    'current_call_number' => \$device->current_call_number,\n                    'call_started_at' => \$device->call_started_at,\n                ];";

$content = str_replace($old_response, $new_response, $content);

//  Add to show response (second occurrence)
$content = preg_replace(
    "/'is_online' => \\$device->isOnline\(\),\n                \]\n            \]\, 200\);/",
    "'is_online' => \$device->isOnline(),\n                    'current_call_status' => \$device->current_call_status,\n                    'current_call_number' => \$device->current_call_number,\n                    'call_started_at' => \$device->call_started_at,\n                ]\n            ], 200);",
    $content
);

file_put_contents($file, $content);
echo "Updated DeviceController\n";
