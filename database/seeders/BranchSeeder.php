<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $branches = [
            [
                'name' => 'Main Office',
                'location' => '123 Main Street, City Center',
                'phone' => '+1234567890',
                'email' => 'main@office.com',
                'status' => 'active',
            ],
            [
                'name' => 'East Branch',
                'location' => '456 East Avenue',
                'phone' => '+1234567891',
                'email' => 'east@office.com',
                'status' => 'active',
            ],
            [
                'name' => 'West Branch',
                'location' => '789 West Boulevard',
                'phone' => '+1234567892',
                'email' => 'west@office.com',
                'status' => 'active',
            ],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }
    }
}
