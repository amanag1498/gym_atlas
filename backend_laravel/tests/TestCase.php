<?php

namespace Tests;

use App\Models\User;
use App\Services\Privacy\ConsentService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    protected bool $seedPrivacyConsentsForFixtureUsers = true;

    protected function setUp(): void
    {
        parent::setUp();

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
