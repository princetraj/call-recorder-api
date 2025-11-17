<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Update user10000's password to "test123"
$user = User::where('username', 'user10000')->first();
if ($user) {
    $user->password = Hash::make('test123');
    $user->save();
    echo "Updated user10000 password to 'test123'\n";
    echo "Username: {$user->username}\n";
    echo "Name: {$user->name}\n";
    echo "Status: {$user->status}\n";
} else {
    echo "User not found\n";
}
