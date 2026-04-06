<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing users and plans
        $users = User::all();
        $plans = Plan::with('billingCycles.prices')->get();

        if ($plans->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Please run PlanSeeder and UserSeeder first!');
            return;
        }

        // Create subscriptions for each user
        foreach ($users as $user) {
            // Each user gets 2-3 subscriptions with different plans
            $randomPlans = $plans->random(rand(1, 3))->unique();

            foreach ($randomPlans as $plan) {
                // Pick a random billing cycle
                $billingCycle = $plan->billingCycles()->inRandomOrder()->first();

                if (!$billingCycle) {
                    continue;
                }

                // Pick a random price/currency
                $planPrice = $billingCycle->prices()->inRandomOrder()->first();

                if (!$planPrice) {
                    continue;
                }

                // Create subscription with random status
                $statuses = ['trialing', 'active', 'past_due', 'canceled'];
                $randomStatus = $statuses[array_rand($statuses)];

                $startDate = Carbon::now('UTC')->subDays(rand(0, 90));
                $trialEnds = $startDate->copy()->addDays(14);

                $subscription = $user->subscriptions()->create([
                    'plan_id' => $plan->id,
                    'plan_billing_cycle_id' => $billingCycle->id,
                    'plan_price_id' => $planPrice->id,
                    'status' => $randomStatus,
                    'trial_ends_at' => $randomStatus === 'trialing' ? $trialEnds : null,
                    'started_at' => $startDate,
                    'current_period_start' => $startDate,
                    'current_period_end' => $startDate->copy()->addDays($billingCycle->duration_in_days),
                    'grace_period_ends_at' => $randomStatus === 'past_due' 
                        ? Carbon::now('UTC')->addDays(rand(1, 5)) 
                        : null,
                    'canceled_at' => $randomStatus === 'canceled' 
                        ? Carbon::now('UTC')->subDays(rand(1, 30)) 
                        : null,
                    'cancellation_reason' => $randomStatus === 'canceled' 
                        ? ['Not enough features', 'Too expensive', 'Found alternative', 'Temporary cancel'][array_rand(['Not enough features', 'Too expensive', 'Found alternative', 'Temporary cancel'])]
                        : null,
                ]);

                // Create subscription history
                \App\Models\SubscriptionHistory::create([
                    'subscription_id' => $subscription->id,
                    'previous_status' => null,
                    'new_status' => $randomStatus,
                    'reason' => 'Subscription created by seeder',
                ]);

                $this->command->line("Created {$randomStatus} subscription for {$user->email} on {$plan->name} plan");
            }
        }
    }
}
