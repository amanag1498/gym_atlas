<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionScopedRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_and_trainer_tokens_keep_independent_roles_for_the_same_user(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $user->assignRole([RoleName::Member->value, RoleName::Trainer->value]);

        TrainerProfile::query()->create([
            'user_id' => $user->id,
            'gym_id' => null,
            'branch_id' => null,
            'status' => 'active',
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $memberToken = $user->createToken('member-app', ['role:member'])->plainTextToken;
        $trainerToken = $user->createToken('trainer-app', ['role:trainer'])->plainTextToken;

        $this->withToken($memberToken)
            ->getJson('/api/public/me')
            ->assertOk()
            ->assertJsonPath('data.active_role', RoleName::Member->value);
        Auth::forgetGuards();

        $this->withToken($trainerToken)
            ->getJson('/api/public/me')
            ->assertOk()
            ->assertJsonPath('data.active_role', RoleName::Trainer->value);
        Auth::forgetGuards();

        $this->withToken($memberToken)
            ->getJson('/api/member/context')
            ->assertOk()
            ->assertJsonPath('data.user.active_role', RoleName::Member->value);
        Auth::forgetGuards();

        $this->withToken($trainerToken)
            ->getJson('/api/trainer/context')
            ->assertOk()
            ->assertJsonPath('data.user.active_role', RoleName::Trainer->value);

        $this->assertSame(
            RoleName::Trainer->value,
            DB::table('users')->where('id', $user->id)->value('active_role'),
        );
    }

    public function test_legacy_unscoped_tokens_continue_using_the_saved_active_role(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Member->value,
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Member->value);

        $token = $user->createToken('legacy-app')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/public/me')
            ->assertOk()
            ->assertJsonPath('data.active_role', RoleName::Member->value);
    }

    public function test_existing_mobile_tokens_infer_their_role_from_the_stable_device_name(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $user->assignRole([RoleName::Member->value, RoleName::Trainer->value]);

        $memberToken = $user->createToken('flutter_member_app')->plainTextToken;
        $trainerToken = $user->createToken('flutter_trainer_app')->plainTextToken;

        $this->withToken($memberToken)
            ->getJson('/api/public/me')
            ->assertOk()
            ->assertJsonPath('data.active_role', RoleName::Member->value);
        Auth::forgetGuards();

        $this->withToken($trainerToken)
            ->getJson('/api/public/me')
            ->assertOk()
            ->assertJsonPath('data.active_role', RoleName::Trainer->value);

        $this->assertSame(
            RoleName::Trainer->value,
            DB::table('users')->where('id', $user->id)->value('active_role'),
        );
    }

    public function test_scoped_token_is_rejected_after_its_role_is_revoked(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $user->assignRole([RoleName::Member->value, RoleName::Trainer->value]);
        $token = $user->createToken('member-app', ['role:member'])->plainTextToken;
        $user->removeRole(RoleName::Member->value);

        $this->withToken($token)
            ->getJson('/api/public/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'This session role is no longer available for this account. Please sign in again.');
    }
}
