<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanBillingCycle;
use App\Models\PlanPrice;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Basic Plan
        $basicPlan = Plan::create([
            'name' => 'Basic',
            'description' => 'Perfect for getting started',
            'features' => json_encode([
                'Up to 100 API calls/month',
                'Basic support',
                'Email notifications',
            ]),
            'is_active' => true,
        ]);

        $this->createBillingCyclesAndPricing($basicPlan, [
            'daily' => 2.99,
            'weekly' => 15.99,
            'monthly' => 49.99,
            'quarterly' => 139.99,
            'semi_annual' => 269.99,
            'yearly' => 499.99,
        ]);

        // Create Professional Plan
        $professionalPlan = Plan::create([
            'name' => 'Professional',
            'description' => 'For growing teams and businesses',
            'features' => json_encode([
                'Up to 10,000 API calls/month',
                'Priority support',
                'Advanced analytics',
                'Custom integrations',
                'Webhook support',
                'API access',
            ]),
            'is_active' => true,
        ]);

        $this->createBillingCyclesAndPricing($professionalPlan, [
            'daily' => 9.99,
            'weekly' => 49.99,
            'monthly' => 99.99,
            'quarterly' => 279.99,
            'semi_annual' => 539.99,
            'yearly' => 999.99,
        ]);

        // Create Enterprise Plan
        $enterprisePlan = Plan::create([
            'name' => 'Enterprise',
            'description' => 'For large-scale operations',
            'features' => json_encode([
                'Unlimited API calls',
                '24/7 dedicated support',
                'Advanced reporting',
                'Custom SLA',
                'Multi-user management',
                'Advanced security',
                'Custom workflows',
                'Dedicated account manager',
            ]),
            'is_active' => true,
        ]);

        $this->createBillingCyclesAndPricing($enterprisePlan, [
            'monthly' => 499.99,
            'quarterly' => 1399.99,
            'semi_annual' => 2699.99,
            'yearly' => 4999.99,
        ]);

        // Create Starter Plan (alternative low-cost)
        $starterPlan = Plan::create([
            'name' => 'Starter',
            'description' => 'For small projects and testing',
            'features' => json_encode([
                'Up to 10 API calls/month',
                'Community support',
                'Basic notifications',
                '1 GB storage',
            ]),
            'is_active' => true,
        ]);

        $this->createBillingCyclesAndPricing($starterPlan, [
            'weekly' => 4.99,
            'monthly' => 9.99,
            'yearly' => 99.99,
        ]);
    }

    /**
     * Create billing cycles and pricing for a plan in multiple currencies
     */
    private function createBillingCyclesAndPricing(Plan $plan, array $cyclePrices): void
    {
        $cycleConfig = [
            'daily' => ['display_name' => 'Daily', 'duration' => 1],
            'weekly' => ['display_name' => 'Weekly', 'duration' => 7],
            'monthly' => ['display_name' => 'Monthly', 'duration' => 30],
            'quarterly' => ['display_name' => 'Quarterly', 'duration' => 90],
            'semi_annual' => ['display_name' => 'Semi-Annual', 'duration' => 180],
            'yearly' => ['display_name' => 'Yearly', 'duration' => 365],
        ];

        $currencies = [
            'USD' => 1.0,           // Base rate
            'AED' => 3.67,          // UAE Dirham
            'EGP' => 30.90,         // Egyptian Pound
            'SAR' => 3.75,          // Saudi Riyal
            'KWD' => 0.307,         // Kuwaiti Dinar
            'QAR' => 3.64,          // Qatari Riyal
            'BHD' => 0.376,         // Bahraini Dinar
            'OMR' => 0.385,         // Omani Rial
            'EUR' => 0.92,          // Euro
            'GBP' => 0.79,          // British Pound
        ];

        foreach ($cyclePrices as $cycleType => $usdPrice) {
            if (!isset($cycleConfig[$cycleType])) {
                continue;
            }

            $config = $cycleConfig[$cycleType];
            $billingCycle = $plan->billingCycles()->create([
                'cycle_type' => $cycleType,
                'duration_in_days' => $config['duration'],
                'display_name' => $config['display_name'],
            ]);

            // Add pricing for each currency
            foreach ($currencies as $currency => $rate) {
                $billingCycle->prices()->create([
                    'currency' => $currency,
                    'price' => round($usdPrice * $rate, 2),
                    'is_active' => true,
                ]);
            }
        }
    }
}
