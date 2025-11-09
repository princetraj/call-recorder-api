<?php

$baseUrl = 'http://localhost:8000/api';

// Login
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'user@example.com',
    'password' => 'password123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$loginData = json_decode($response, true);

if (!$loginData['success']) {
    die("Login failed: " . $loginData['message'] . "\n");
}

$token = $loginData['data']['token'];
echo "✓ Login successful\n";
echo "Token: " . substr($token, 0, 30) . "...\n\n";

// Get devices
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/devices');
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$devicesData = json_decode($response, true);

curl_close($ch);

echo "API Response:\n";
echo json_encode($devicesData, JSON_PRETTY_PRINT);
echo "\n\n";

if ($devicesData['success']) {
    echo "✓ API returned success\n";
    echo "Total devices: " . ($devicesData['data']['pagination']['total'] ?? 0) . "\n";

    if (isset($devicesData['data']['pagination']['data'])) {
        foreach ($devicesData['data']['pagination']['data'] as $device) {
            echo "\nDevice: " . $device['device_model'] . "\n";
            echo "  User: " . $device['user']['name'] . "\n";
            echo "  Battery: " . $device['battery_percentage'] . "%\n";
            echo "  Connection: " . $device['connection_type'] . "\n";
            echo "  Status: " . ($device['is_online'] ? 'Online' : 'Offline') . "\n";
        }
    }
} else {
    echo "✗ API returned failure\n";
}
