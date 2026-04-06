<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding database...');

        // Seed users first
        $this->command->info('👥 Creating users...');
        $this->call(UserSeeder::class);

        // Then seed plans with billing cycles and pricing
        $this->command->info('📋 Creating plans...');
        $this->call(PlanSeeder::class);

        // Finally create subscriptions for users
        $this->command->info('🔄 Creating subscriptions...');
        $this->call(SubscriptionSeeder::class);

        $this->command->info('✅ Database seeding completed successfully!');
    }
}
