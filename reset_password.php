<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'user@example.com')->first();

if ($user) {
    $user->password = Hash::make('password');
    $user->save();
    echo "Password reset successfully for: " . $user->email . "\n";
    echo "New password: password\n";

    // Test login
    $token = $user->createToken('test-device')->plainTextToken;
    echo "\nGenerated test token (first 50 chars): " . substr($token, 0, 50) . "...\n";
} else {
    echo "User not found!\n";
}
