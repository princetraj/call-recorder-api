<?php

$file = 'app/Http/Controllers/Api/DeviceController.php';
$content = file_get_contents($file);

$old = <<<'OLD'
            return response()->json([
                'success' => true,
                'data' => $devices
            ], 200);
OLD;

$new = <<<'NEW'
            return response()->json([
                'success' => true,
                'data' => [
                    'pagination' => [
                        'data' => $devices->items(),
                        'current_page' => $devices->currentPage(),
                        'per_page' => $devices->perPage(),
                        'total' => $devices->total(),
                        'last_page' => $devices->lastPage(),
                        'from' => $devices->firstItem(),
                        'to' => $devices->lastItem(),
                    ]
                ]
            ], 200);
NEW;

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Fixed DeviceController.php\n";
