<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john@user.com',
                'password' => Hash::make('password123'),
                'mobile' => '+1234567890',
                'status' => 'active',
                'branch_id' => 1, // Main Office
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@user.com',
                'password' => Hash::make('password123'),
                'mobile' => '+1234567891',
                'status' => 'active',
                'branch_id' => 2, // East Branch
            ],
            [
                'name' => 'Bob Johnson',
                'email' => 'bob@user.com',
                'password' => Hash::make('password123'),
                'mobile' => '+1234567892',
                'status' => 'active',
                'branch_id' => 3, // West Branch
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
