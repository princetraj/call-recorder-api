<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::take(5)->get(['id', 'username', 'name', 'status']);
foreach ($users as $user) {
    echo "ID: {$user->id}, Username: {$user->username}, Name: {$user->name}, Status: {$user->status}\n";
}
