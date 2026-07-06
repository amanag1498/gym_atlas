<?php

namespace Database\Seeders;

use App\Models\PlatformSubscriptionPlan;
use Illuminate\Database\Seeder;

class PlatformSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $plans = collect([
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Core platform presence, admin operations, and discovery eligibility for independent gyms.',
                'status' => 'active',
                'billing_period' => 'month',
                'billing_interval_count' => 1,
                'price' => 1999,
                'setup_fee' => 1499,
                'trial_days' => 7,
                'is_default' => true,
                'sort_order' => 10,
                'included_services' => [
                    'Platform admin access',
                    'Public discovery listing',
                    'Branch and trainer management',
                    'Member and attendance operations',
                ],
                'feature_highlights' => [
                    'Best for single-location gyms',
                    'Includes baseline onboarding support',
                ],
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'description' => 'Higher visibility and operational tooling for growing gyms with stronger acquisition needs.',
                'status' => 'active',
                'billing_period' => 'month',
                'billing_interval_count' => 1,
                'price' => 3999,
                'setup_fee' => 1999,
                'trial_days' => 10,
                'is_default' => false,
                'sort_order' => 20,
                'included_services' => [
                    'Everything in Starter',
                    'Featured listing readiness',
                    'Priority approval reviews',
                    'Operational support for multiple branches',
                ],
                'feature_highlights' => [
                    'Best for featured gyms and active expansion',
                    'Balanced plan for recurring growth',
                ],
            ],
            [
                'name' => 'Scale',
                'slug' => 'scale',
                'description' => 'Premium commercial tier for promoted gyms and higher-touch platform growth support.',
                'status' => 'active',
                'billing_period' => 'month',
                'billing_interval_count' => 1,
                'price' => 6999,
                'setup_fee' => 2499,
                'trial_days' => 14,
                'is_default' => false,
                'sort_order' => 30,
                'included_services' => [
                    'Everything in Growth',
                    'Promoted discovery support',
                    'Priority billing management',
                    'Commercial visibility support',
                ],
                'feature_highlights' => [
                    'Best for promoted gyms',
                    'Higher-touch platform relationship',
                ],
            ],
        ])->map(function (array $attributes): PlatformSubscriptionPlan {
            return PlatformSubscriptionPlan::query()->updateOrCreate(
                ['slug' => $attributes['slug']],
                $attributes,
            );
        })->keyBy('slug');

        PlatformSubscriptionPlan::query()
            ->where('slug', '!=', 'starter')
            ->update(['is_default' => false]);
    }
}
