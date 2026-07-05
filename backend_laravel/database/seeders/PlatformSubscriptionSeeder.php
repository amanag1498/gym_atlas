<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\GymPlatformSubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        /** @var User|null $platformAdmin */
        $platformAdmin = User::query()
            ->role('platform_admin')
            ->orderBy('id')
            ->first();

        Gym::query()
            ->with('currentPlatformSubscription')
            ->get()
            ->each(function (Gym $gym) use ($plans, $platformAdmin): void {
                $plan = $this->planForGym($gym, $plans);
                $currentSubscription = $gym->currentPlatformSubscription;
                $startsAt = $currentSubscription?->starts_at?->toDateString() ?? now()->toDateString();
                $trialEndsAt = $plan->trial_days > 0 ? CarbonImmutable::parse($startsAt)->addDays($plan->trial_days)->toDateString() : null;
                $renewsAt = $this->resolveRenewalDate($startsAt, $plan)->toDateString();
                $status = $trialEndsAt && CarbonImmutable::parse($trialEndsAt)->isFuture() ? 'trialing' : 'active';

                $payload = [
                    'platform_subscription_plan_id' => $plan->id,
                    'assigned_by_user_id' => $platformAdmin?->id,
                    'status' => $status,
                    'starts_at' => $startsAt,
                    'renews_at' => $renewsAt,
                    'ends_at' => null,
                    'trial_ends_at' => $trialEndsAt,
                    'billing_amount' => $plan->price,
                    'setup_fee_amount' => $plan->setup_fee,
                    'auto_renew' => true,
                    'included_services' => $plan->included_services ?? [],
                    'plan_snapshot' => $this->planSnapshot($plan),
                    'notes' => 'Seeded platform billing assignment.',
                ];

                $existing = $currentSubscription;

                if ($existing) {
                    $existing->update($payload);
                } else {
                    $existing = GymPlatformSubscription::query()->create([
                        'gym_id' => $gym->id,
                        ...$payload,
                    ]);
                }

                GymPlatformSubscription::query()
                    ->where('gym_id', $gym->id)
                    ->whereKeyNot($existing->id)
                    ->whereIn('status', ['trialing', 'active', 'past_due'])
                    ->update([
                        'status' => 'cancelled',
                        'auto_renew' => false,
                        'ends_at' => now()->toDateString(),
                        'notes' => 'Archived during platform billing reseed.',
                    ]);
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, PlatformSubscriptionPlan>  $plans
     */
    private function planForGym(Gym $gym, $plans): PlatformSubscriptionPlan
    {
        if ($gym->is_promoted) {
            return $plans->get('scale');
        }

        if ($gym->is_featured || ($gym->branches()->count() > 1)) {
            return $plans->get('growth');
        }

        return $plans->get('starter');
    }

    private function resolveRenewalDate(string $startsAt, PlatformSubscriptionPlan $plan): CarbonImmutable
    {
        $start = CarbonImmutable::parse($startsAt);

        return match ($plan->billing_period) {
            'day' => $start->addDays($plan->billing_interval_count),
            'week' => $start->addWeeks($plan->billing_interval_count),
            'month' => $start->addMonths($plan->billing_interval_count),
            'quarter' => $start->addMonths($plan->billing_interval_count * 3),
            'year' => $start->addYears($plan->billing_interval_count),
            default => $start->addMonth(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function planSnapshot(PlatformSubscriptionPlan $plan): array
    {
        return [
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'slug' => Str::slug($plan->slug),
            'cadence_label' => $plan->cadence_label,
            'price_label' => $plan->price_label,
            'trial_days' => $plan->trial_days,
            'included_services' => $plan->included_services ?? [],
            'feature_highlights' => $plan->feature_highlights ?? [],
        ];
    }
}
