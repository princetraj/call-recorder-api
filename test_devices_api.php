<?php
/**
 * Quick API test script for device endpoints
 * Run with: php test_devices_api.php
 */

$baseUrl = 'http://localhost:8000/api';
$testEmail = 'admin@example.com'; // Change this to your test user
$testPassword = 'password'; // Change this to your test password

function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

echo "=== Device API Tests ===\n\n";

// 1. Login to get token
echo "1. Logging in...\n";
$loginResponse = makeRequest('POST', $baseUrl . '/login', [
    'email' => $testEmail,
    'password' => $testPassword
]);

if ($loginResponse['code'] !== 200 || !$loginResponse['body']['success']) {
    die("Login failed! Please check credentials.\n");
}

$token = $loginResponse['body']['data']['token'];
echo "✓ Logged in successfully\n\n";

// 2. Register a device
echo "2. Registering a test device...\n";
$deviceData = [
    'device_id' => 'test_device_' . time(),
    'device_model' => 'Test Phone',
    'manufacturer' => 'Test Manufacturer',
    'os_version' => '13',
    'app_version' => '1.0.0'
];

$registerResponse = makeRequest('POST', $baseUrl . '/devices/register', $deviceData, $token);
echo "Status Code: " . $registerResponse['code'] . "\n";
echo "Response: " . json_encode($registerResponse['body'], JSON_PRETTY_PRINT) . "\n\n";

// 3. Update device status
echo "3. Updating device status...\n";
$statusData = [
    'device_id' => $deviceData['device_id'],
    'connection_type' => 'wifi',
    'battery_percentage' => 85,
    'signal_strength' => 4,
    'is_charging' => false,
    'app_running_status' => 'active'
];

$statusResponse = makeRequest('POST', $baseUrl . '/devices/status', $statusData, $token);
echo "Status Code: " . $statusResponse['code'] . "\n";
echo "Response: " . json_encode($statusResponse['body'], JSON_PRETTY_PRINT) . "\n\n";

// 4. Get all devices
echo "4. Getting all devices...\n";
$devicesResponse = makeRequest('GET', $baseUrl . '/devices', null, $token);
echo "Status Code: " . $devicesResponse['code'] . "\n";
echo "Total devices: " . ($devicesResponse['body']['data']['pagination']['total'] ?? 0) . "\n\n";

// 5. Test filtering - online devices
echo "5. Getting online devices...\n";
$onlineResponse = makeRequest('GET', $baseUrl . '/devices?status=online', null, $token);
echo "Status Code: " . $onlineResponse['code'] . "\n";
echo "Online devices: " . ($onlineResponse['body']['data']['pagination']['total'] ?? 0) . "\n\n";

echo "=== All tests completed ===\n";
