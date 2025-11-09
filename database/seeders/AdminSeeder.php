<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admins = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@office.com',
                'password' => Hash::make('password123'),
                'admin_role' => 'super_admin',
                'status' => 'active',
                'branch_id' => 1, // Main Office
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@office.com',
                'password' => Hash::make('password123'),
                'admin_role' => 'manager',
                'status' => 'active',
                'branch_id' => 2, // East Branch
            ],
            [
                'name' => 'Trainee User',
                'email' => 'trainee@office.com',
                'password' => Hash::make('password123'),
                'admin_role' => 'trainee',
                'status' => 'active',
                'branch_id' => 3, // West Branch
            ],
        ];

        foreach ($admins as $admin) {
            Admin::create($admin);
        }
    }
}
