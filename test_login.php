<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'admin@example.com')->first();

if ($user) {
    echo "User found: " . $user->name . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Role: " . $user->role . "\n";
    echo "Status: " . $user->status . "\n";

    $passwordCheck = Illuminate\Support\Facades\Hash::check('password123', $user->password);
    echo "Password check: " . ($passwordCheck ? 'CORRECT' : 'INCORRECT') . "\n";
} else {
    echo "User not found!\n";
}
