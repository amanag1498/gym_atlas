<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\FirebaseTokenVerifier;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirebaseLoginTest extends TestCase
{
    use RefreshDatabase;

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
    }
}
