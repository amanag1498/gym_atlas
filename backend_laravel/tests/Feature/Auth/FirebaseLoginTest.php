<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Auth\FirebaseTokenVerifier;
use App\Services\Privacy\ConsentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirebaseLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_app_login_accepts_an_existing_gym_owner_and_rejects_new_accounts(): void
    {
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->create([
            'email' => 'owner@example.com',
            'active_role' => RoleName::GymOwner->value,
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);
        $member = User::factory()->create([
            'email' => 'member@example.com',
            'active_role' => RoleName::Member->value,
            'is_active' => true,
        ]);
        $member->assignRole(RoleName::Member->value);
        $this->mock(FirebaseTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')->times(3)->andReturnUsing(function (string $token): array {
                $email = match ($token) {
                    'known.token' => 'owner@example.com',
                    'member.token' => 'member@example.com',
                    default => 'unknown@example.com',
                };

                return [
                    'sub' => 'firebase-'.$email,
                    'email' => $email,
                    'name' => 'Gym Owner',
                    'email_verified' => true,
                    'firebase' => ['sign_in_provider' => 'google.com'],
                ];
            });
        });

        $this->postJson('/api/public/auth/firebase/login', [
            'id_token' => 'known.token',
            'app_type' => 'admin',
            'device_name' => 'flutter_admin_app',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $owner->id)
            ->assertJsonPath('data.user.active_role', RoleName::GymOwner->value);

        $this->postJson('/api/public/auth/firebase/login', [
            'id_token' => 'unknown.token',
            'app_type' => 'admin',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);

        $this->postJson('/api/public/auth/firebase/login', [
            'id_token' => 'member.token',
            'app_type' => 'admin',
        ])->assertUnprocessable();
        $this->assertFalse($member->fresh()->hasRole(RoleName::GymOwner->value));
    }

    public function test_it_logs_in_a_user_with_a_verified_firebase_token_and_issues_sanctum_token(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->mock(FirebaseTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'firebase-user-001',
                    'email' => 'firebase-member@example.com',
                    'name' => 'Firebase Member',
                    'picture' => 'https://example.com/firebase-member.png',
                    'email_verified' => true,
                    'firebase' => [
                        'sign_in_provider' => 'google.com',
                    ],
                    'aud' => 'gym-atlas-test',
                    'iss' => 'https://securetoken.google.com/gym-atlas-test',
                    'exp' => now()->addHour()->timestamp,
                ]);
        });

        config()->set('services.firebase.project_id', 'gym-atlas-test');

        $response = $this->postJson('/api/public/auth/firebase/login', [
            'id_token' => 'fake.firebase.jwt',
            'device_name' => 'flutter-member-app',
            'app_type' => 'member',
            'accepted_terms' => true,
            'enable_optional_features' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'firebase-member@example.com')
            ->assertJsonPath('data.user.active_role', 'member')
            ->assertJsonPath('data.user.auth_provider', 'firebase_google');

        $user = User::query()->firstWhere('email', 'firebase-member@example.com');

        $this->assertNotNull($user);
        $this->assertSame('firebase-user-001', $user->firebase_uid);
        $this->assertTrue($user->hasRole('member'));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertCount(
            count(ConsentService::PURPOSES),
            $user->consentRecords()->where('policy_version', ConsentService::POLICY_VERSION)->get(),
        );
        $this->assertTrue(
            $user->consentRecords()
                ->where('policy_version', ConsentService::POLICY_VERSION)
                ->get()
                ->every(fn ($record): bool => $record->consented_at !== null && $record->withdrawn_at === null),
        );
    }

    public function test_trainer_app_login_provisions_an_independent_trainer_account(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->mock(FirebaseTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'firebase-trainer-001',
                    'email' => 'firebase-trainer@example.com',
                    'name' => 'Firebase Trainer',
                    'picture' => 'https://example.com/firebase-trainer.png',
                    'email_verified' => true,
                    'firebase' => [
                        'sign_in_provider' => 'google.com',
                    ],
                    'aud' => 'gym-atlas-test',
                    'iss' => 'https://securetoken.google.com/gym-atlas-test',
                    'exp' => now()->addHour()->timestamp,
                ]);
        });

        config()->set('services.firebase.project_id', 'gym-atlas-test');

        $this->postJson('/api/public/auth/firebase/login', [
            'id_token' => 'fake.firebase.trainer.jwt',
            'device_name' => 'flutter_trainer_app',
            'app_type' => 'trainer',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'firebase-trainer@example.com')
            ->assertJsonPath('data.user.active_role', RoleName::Trainer->value)
            ->assertJsonPath('data.user.roles.0', RoleName::Trainer->value);

        $trainer = User::query()
            ->where('email', 'firebase-trainer@example.com')
            ->firstOrFail();

        $this->assertTrue($trainer->hasRole(RoleName::Trainer->value));
        $this->assertFalse($trainer->hasRole(RoleName::Member->value));
        $this->assertDatabaseHas('trainer_profiles', [
            'user_id' => $trainer->id,
            'gym_id' => null,
            'branch_id' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $profile = TrainerProfile::query()
            ->where('user_id', $trainer->id)
            ->firstOrFail();

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/context')
            ->assertOk()
            ->assertJsonPath('data.trainer_profile.id', $profile->id)
            ->assertJsonPath('data.trainer_profile.gym_id', null)
            ->assertJsonPath('data.assigned_gym', null);
    }

    public function test_member_app_login_adds_member_access_to_an_existing_trainer_account(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'email' => 'dual-role@example.com',
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Trainer->value);

        $this->mock(FirebaseTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'firebase-dual-role-001',
                    'email' => 'dual-role@example.com',
                    'name' => 'Dual Role Reviewer',
                    'email_verified' => true,
                    'firebase' => [
                        'sign_in_provider' => 'google.com',
                    ],
                    'aud' => 'gym-atlas-test',
                    'iss' => 'https://securetoken.google.com/gym-atlas-test',
                    'exp' => now()->addHour()->timestamp,
                ]);
        });

        config()->set('services.firebase.project_id', 'gym-atlas-test');

        $response = $this->postJson('/api/public/auth/firebase/login', [
            'id_token' => 'fake.firebase.dual-role.jwt',
            'device_name' => 'flutter_member_app',
            'app_type' => RoleName::Member->value,
        ])->assertOk();

        $user->refresh();
        $this->assertTrue($user->hasRole(RoleName::Member->value));
        $this->assertTrue($user->hasRole(RoleName::Trainer->value));

        $token = $response->json('data.token');

        $this->withToken($token)
            ->getJson('/api/public/me')
            ->assertOk()
            ->assertJsonPath('data.active_role', RoleName::Member->value)
            ->assertJsonPath(
                'data.roles',
                fn (array $roles): bool => in_array(RoleName::Member->value, $roles, true)
                    && in_array(RoleName::Trainer->value, $roles, true),
            );

        $this->assertSame(['role:member'], $user->tokens()->latest('id')->firstOrFail()->abilities);
        $this->assertSame(RoleName::Trainer->value, $user->fresh()->active_role);
    }

    public function test_apple_login_records_the_firebase_apple_provider(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->mock(FirebaseTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'firebase-apple-member-001',
                    'email' => 'member@privaterelay.appleid.com',
                    'email_verified' => true,
                    'firebase' => [
                        'sign_in_provider' => 'apple.com',
                    ],
                    'aud' => 'gym-atlas-test',
                    'iss' => 'https://securetoken.google.com/gym-atlas-test',
                    'exp' => now()->addHour()->timestamp,
                ]);
        });

        config()->set('services.firebase.project_id', 'gym-atlas-test');

        $this->postJson('/api/public/auth/firebase/login', [
            'id_token' => 'fake.firebase.apple.jwt',
            'device_name' => 'flutter_member_app_ios',
            'app_type' => 'member',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'member@privaterelay.appleid.com')
            ->assertJsonPath('data.user.auth_provider', 'firebase_apple')
            ->assertJsonPath('data.user.active_role', RoleName::Member->value);

        $this->assertDatabaseHas('users', [
            'firebase_uid' => 'firebase-apple-member-001',
            'email' => 'member@privaterelay.appleid.com',
            'auth_provider' => 'firebase_apple',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'auth.firebase.login',
            'context->auth_provider' => 'firebase_apple',
            'context->app_type' => 'member',
        ]);
    }
}
