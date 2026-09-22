<?php

namespace Tests;

use App\Models\Gym;
use App\Models\GymPlatformSubscription;
use App\Models\User;
use App\Services\Privacy\ConsentService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    protected bool $seedPrivacyConsentsForFixtureUsers = true;

    protected bool $seedPlatformSubscriptionsForFixtureGyms = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->seedPlatformSubscriptionsForFixtureGyms) {
            // Existing feature fixtures represent operating gyms. Billing
            // access tests disable this to cover unassigned and expired gyms.
            Gym::created(static function (Gym $gym): void {
                GymPlatformSubscription::query()->create([
                    'gym_id' => $gym->id,
                    'status' => 'active',
                    'starts_at' => now()->toDateString(),
                    'renews_at' => now()->addYear()->toDateString(),
                    'ends_at' => now()->addYear()->toDateString(),
                    'billing_amount' => 0,
                    'setup_fee_amount' => 0,
                    'auto_renew' => false,
                ]);
            });
        }

        if (! $this->seedPrivacyConsentsForFixtureUsers) {
            return;
        }

        // Older feature tests create synthetic users to exercise unrelated
        // workflows. Give those fixtures the choices needed to reach routes;
        // privacy tests turn this off to verify real opt-in and withdrawal.
        User::created(static function (User $user): void {
            $consents = app(ConsentService::class);
            $request = Request::create('/test-fixture', 'POST');
            foreach (['core_account', 'health_and_fitness_data', 'trainer_member_sharing', 'biometric_attendance', 'photos', 'notifications'] as $purpose) {
                $consents->record($user, $purpose, $request);
            }
        });
    }
}
