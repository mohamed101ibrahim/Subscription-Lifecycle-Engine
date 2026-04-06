<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => bcrypt('password123'),
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => bcrypt('password123'),
            ],
            [
                'name' => 'Ahmed Hassan',
                'email' => 'ahmed@example.com',
                'password' => bcrypt('password123'),
            ],
            [
                'name' => 'Fatima Al-Mansouri',
                'email' => 'fatima@example.com',
                'password' => bcrypt('password123'),
            ],
            [
                'name' => 'Mohammed Ali',
                'email' => 'mohammed@example.com',
                'password' => bcrypt('password123'),
            ],
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah@example.com',
                'password' => bcrypt('password123'),
            ],
            [
                'name' => 'Tom Wilson',
                'email' => 'tom@example.com',
                'password' => bcrypt('password123'),
            ],
            [
                'name' => 'Lisa Brown',
                'email' => 'lisa@example.com',
                'password' => bcrypt('password123'),
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
            $this->command->line("Created user: {$user['name']} ({$user['email']})");
        }
    }
}
