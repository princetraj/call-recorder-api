<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

// Logout user10000 from all devices
$user = User::where('username', 'user10000')->first();
if ($user) {
    // Delete all tokens
    $tokenCount = $user->tokens()->count();
    $user->tokens()->delete();

    // Clear active_device_id
    $user->active_device_id = null;
    $user->save();

    echo "✅ Logged out user10000 from all devices\n";
    echo "Username: {$user->username}\n";
    echo "Name: {$user->name}\n";
    echo "Tokens deleted: {$tokenCount}\n";
    echo "Active device ID cleared: Yes\n";
} else {
    echo "❌ User not found\n";
}
