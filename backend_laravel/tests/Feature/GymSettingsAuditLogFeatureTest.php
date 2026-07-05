<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\GymSetting;
use App\Models\NotificationPreference;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymSettingsAuditLogFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_owner_can_save_settings_from_web_and_api(): void
    {
        [$owner, $gym, $branch] = $this->makeGymScope();

        $this->post('/gym/login', [
            'email' => $owner->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));

        $this->put(route('web.gym.settings.update', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'attendance_duplicate_checkin_rule' => 0,
            'billing_settings_placeholder' => 'Collect cash only till 9 PM.',
            'staff_permission_defaults' => ['manage_attendance', 'view_reports'],
            'notification_preferences' => [
                [
                    'notification_type' => NotificationType::MembershipExpiry->value,
                    'is_enabled' => false,
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('gym_settings', [
            'gym_id' => $gym->id,
            'key' => 'billing_settings_placeholder',
        ]);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $owner->id,
            'notification_type' => NotificationType::MembershipExpiry->value,
            'is_enabled' => false,
        ]);
        $this->assertFalse($gym->fresh()->prevent_duplicate_same_day_checkins);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'web.gym.settings.updated',
            'actor_user_id' => $owner->id,
            'gym_id' => $gym->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/settings?gym_id='.$gym->id.'&branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonPath('data.billing_settings_placeholder', 'Collect cash only till 9 PM.');

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/gym/settings', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'attendance_duplicate_checkin_rule' => true,
                'billing_settings_placeholder' => 'UPI close-out at 10 PM.',
                'staff_permission_defaults' => ['view_billing'],
                'notification_preferences' => [
                    [
                        'notification_type' => NotificationType::PaymentDue->value,
                        'is_enabled' => false,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.billing_settings_placeholder', 'UPI close-out at 10 PM.')
            ->assertJsonPath('data.attendance_duplicate_checkin_rule', true);

        $this->assertTrue($gym->fresh()->prevent_duplicate_same_day_checkins);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'gym.settings.updated',
            'actor_user_id' => $owner->id,
            'gym_id' => $gym->id,
        ]);
    }

    public function test_gym_audit_logs_show_and_branch_scope_correctly(): void
    {
        [$owner, $gym, $branchA] = $this->makeGymScope();
        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch B',
            'slug' => 'branch-b-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        $actor = User::factory()->create(['is_active' => true]);

        ActivityLog::query()->create([
            'actor_user_id' => $owner->id,
            'user_id' => $owner->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'event' => 'payment.recorded',
            'action' => 'create',
            'actor_role' => RoleName::GymOwner->value,
            'subject_type' => Gym::class,
            'subject_id' => $gym->id,
            'old_values' => ['password' => 'secret'],
            'new_values' => ['status' => 'paid'],
            'occurred_at' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        ActivityLog::query()->create([
            'actor_user_id' => $actor->id,
            'user_id' => $actor->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'event' => 'web.gym.public_listing.updated',
            'action' => 'update',
            'actor_role' => RoleName::GymStaff->value,
            'subject_type' => Branch::class,
            'subject_id' => $branchB->id,
            'old_values' => ['contact_visible' => false],
            'new_values' => ['token' => 'abc123', 'contact_visible' => true],
            'occurred_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->post('/gym/login', [
            'email' => $owner->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));

        $this->get(route('web.gym.audit-logs.index', [
            'gym' => $gym->id,
            'actor' => $owner->name,
            'branch_id' => $branchA->id,
        ]))
            ->assertOk()
            ->assertSee('Audit Logs')
            ->assertSee('payment.recorded')
            ->assertSee('[redacted]')
            ->assertDontSee('abc123');

        $manager = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::BranchManager->value,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);
        $this->attachToGymAndBranches($manager, $gym, [$branchA], []);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/gym/audit-logs?gym_id='.$gym->id.'&branch_id='.$branchA->id)
            ->assertOk()
            ->assertJsonPath('data.0.branch.name', $branchA->name)
            ->assertJsonMissing(['Branch B']);
    }

    public function test_gym_staff_needs_view_reports_for_settings_and_audit_logs(): void
    {
        [$owner, $gym, $branch] = $this->makeGymScope();
        $staff = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::GymStaff->value,
        ]);
        $staff->assignRole(RoleName::GymStaff->value);
        $this->attachToGymAndBranches($staff, $gym, [$branch], []);

        $this->post('/gym/login', [
            'email' => $staff->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));

        $this->get(route('web.gym.settings.index', ['gym' => $gym->id, 'branch' => $branch->id]))->assertForbidden();
        $this->get(route('web.gym.audit-logs.index', ['gym' => $gym->id, 'branch' => $branch->id]))->assertForbidden();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/gym/settings?gym_id='.$gym->id.'&branch_id='.$branch->id)
            ->assertForbidden();

        $this->attachToGymAndBranches($staff, $gym, [$branch], ['view_reports']);
        $staff = $staff->fresh();

        $this->actingAs($staff)->get(route('web.gym.settings.index', ['gym' => $gym->id, 'branch' => $branch->id]))->assertOk();
        $this->actingAs($staff)->get(route('web.gym.audit-logs.index', ['gym' => $gym->id, 'branch' => $branch->id]))->assertOk();
        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/gym/audit-logs?gym_id='.$gym->id.'&branch_id='.$branch->id)
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: Gym, 2: Branch}
     */
    private function makeGymScope(): array
    {
        $owner = User::factory()->create([
            'email' => 'gym-settings-owner@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Settings Gym',
            'slug' => 'settings-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'prevent_duplicate_same_day_checkins' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch], []);

        return [$owner, $gym, $branch];
    }

    /**
     * @param  list<Branch>  $branches
     * @param  list<string>  $customPermissions
     */
    private function attachToGymAndBranches(User $user, Gym $gym, array $branches, array $customPermissions): void
    {
        $encoded = json_encode($customPermissions);
        $user->gyms()->syncWithoutDetaching([
            $gym->id => ['custom_permissions' => $encoded, 'is_primary' => true],
        ]);

        foreach ($branches as $branch) {
            $user->branches()->syncWithoutDetaching([
                $branch->id => ['custom_permissions' => $encoded, 'is_primary' => true],
            ]);
        }
    }
}
