<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Services\Auth\FirebaseTokenVerifier;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelFirebaseLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_sign_into_admin_panel_with_firebase_google(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin-web@example.com',
            'active_role' => 'platform_admin',
            'is_active' => true,
        ]);
        $user->assignRole('platform_admin');

        $this->mock(FirebaseTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'firebase-admin-web',
                    'email' => 'admin-web@example.com',
                    'name' => 'Platform Admin',
                    'picture' => 'https://example.com/admin.png',
                    'email_verified' => true,
                    'aud' => 'gym-atlas-test',
                    'iss' => 'https://securetoken.google.com/gym-atlas-test',
                    'exp' => now()->addHour()->timestamp,
                ]);
        });

        config()->set('services.firebase.project_id', 'gym-atlas-test');

        $response = $this->post('/admin/login/firebase', [
            'id_token' => 'fake.firebase.jwt',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('web.admin.dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('firebase-admin-web', $user->fresh()->firebase_uid);
    }

    public function test_gym_owner_can_sign_into_gym_panel_with_firebase_google(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'name' => 'Gym Owner',
            'email' => 'owner-web@example.com',
            'active_role' => 'gym_owner',
            'is_active' => true,
        ]);
        $user->assignRole('gym_owner');

        $this->mock(FirebaseTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'firebase-owner-web',
                    'email' => 'owner-web@example.com',
                    'name' => 'Gym Owner',
                    'picture' => 'https://example.com/owner.png',
                    'email_verified' => true,
                    'aud' => 'gym-atlas-test',
                    'iss' => 'https://securetoken.google.com/gym-atlas-test',
                    'exp' => now()->addHour()->timestamp,
                ]);
        });

        config()->set('services.firebase.project_id', 'gym-atlas-test');

        $response = $this->post('/gym/login/firebase', [
            'id_token' => 'fake.firebase.jwt',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('web.gym.dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('firebase-owner-web', $user->fresh()->firebase_uid);
    }

    public function test_unknown_google_user_cannot_auto_provision_into_gym_web_panel(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->mock(FirebaseTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'firebase-unknown-web',
                    'email' => 'unknown-gym-web@example.com',
                    'name' => 'Unknown Gym User',
                    'picture' => 'https://example.com/unknown.png',
                    'email_verified' => true,
                    'aud' => 'gym-atlas-test',
                    'iss' => 'https://securetoken.google.com/gym-atlas-test',
                    'exp' => now()->addHour()->timestamp,
                ]);
        });

        config()->set('services.firebase.project_id', 'gym-atlas-test');

        $response = $this->from('/gym/login')->post('/gym/login/firebase', [
            'id_token' => 'fake.firebase.jwt',
            'remember' => '1',
        ]);

        $response
            ->assertRedirect('/gym/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
        $this->assertDatabaseMissing('users', [
            'email' => 'unknown-gym-web@example.com',
        ]);
    }
}
