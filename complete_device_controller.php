<?php

$file = 'app/Http/Controllers/Api/DeviceController.php';
$content = file_get_contents($file);

// 1. Update validation in updateStatus
$old_val = "'app_running_status' => 'nullable|string|in:active,background,stopped',";
$new_val = "'app_running_status' => 'nullable|string|in:active,background,stopped',
            'current_call_status' => 'nullable|string|in:idle,ringing,offhook,incoming,outgoing',
            'current_call_number' => 'nullable|string|max:20',";
$content = str_replace($old_val, $new_val, $content);

// 2. Update the update array in updateStatus
$old_update = "'app_running_status' => \$request->app_running_status ?? \$device->app_running_status,
                'last_updated_at' => now(),";
$new_update = "'app_running_status' => \$request->app_running_status ?? \$device->app_running_status,
                'current_call_status' => \$request->current_call_status ?? \$device->current_call_status,
                'current_call_number' => \$request->current_call_number ?? \$device->current_call_number,
                'call_started_at' => \$request->current_call_status && \$request->current_call_status !== 'idle' ? (\$device->current_call_status === 'idle' ? now() : \$device->call_started_at) : null,
                'last_updated_at' => now(),";
$content = str_replace($old_update, $new_update, $content);

// 3. Add to index response
$old_index = "'is_online' => \$device->isOnline(),
                ];";
$new_index = "'is_online' => \$device->isOnline(),
                    'current_call_status' => \$device->current_call_status,
                    'current_call_number' => \$device->current_call_number,
                    'call_started_at' => \$device->call_started_at,
                ];";
$content = str_replace($old_index, $new_index, $content);

// 4. Add to show response  
$old_show = "'is_online' => \$device->isOnline(),
                ]
            ], 200);";
$new_show = "'is_online' => \$device->isOnline(),
                    'current_call_status' => \$device->current_call_status,
                    'current_call_number' => \$device->current_call_number,
                    'call_started_at' => \$device->call_started_at,
                ]
            ], 200);";
$content = str_replace($old_show, $new_show, $content);

file_put_contents($file, $content);
echo "✓ Updated DeviceController.php\n";
