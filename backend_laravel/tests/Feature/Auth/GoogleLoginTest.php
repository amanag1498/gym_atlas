<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\GoogleTokenVerifier;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_in_a_user_with_a_verified_google_token_and_issues_sanctum_token(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->mock(GoogleTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'google-user-001',
                    'email' => 'member-login@example.com',
                    'name' => 'Member Login',
                    'picture' => 'https://example.com/member.png',
                    'email_verified' => true,
                    'aud' => 'test-client-id',
                    'iss' => 'https://accounts.google.com',
                    'exp' => now()->addHour()->timestamp,
                ]);
        });

        config()->set('services.google.client_ids', ['test-client-id']);

        $response = $this->postJson('/api/public/auth/google/login', [
            'id_token' => 'fake.jwt.token',
            'device_name' => 'flutter-member-app',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'member-login@example.com')
            ->assertJsonPath('data.user.active_role', 'member');

        $user = User::query()->firstWhere('email', 'member-login@example.com');

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('member'));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_it_logs_out_and_revokes_only_the_current_sanctum_token(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => 'member',
        ]);
        $user->assignRole('member');

        $firstToken = $user->createToken('device-one');
        $secondToken = $user->createToken('device-two');

        $this->withToken($firstToken->plainTextToken)
            ->postJson('/api/public/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $firstToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $secondToken->accessToken->id,
        ]);
    }
}
