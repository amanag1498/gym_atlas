<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_web_payments_tab_supports_partial_and_full_payment_and_member_history(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'payments-web-member@example.com');
        [$gym, $branch, $membership] = $this->makeScopedMembership($owner, $member, [
            'custom_fee_enabled' => true,
            'custom_fee_amount' => 1800,
            'custom_fee_reason' => 'Retention pricing',
            'final_payable_amount' => 1900,
            'amount_paid' => 0,
            'due_amount' => 1900,
        ]);

        $this->attachToGym($owner, $gym, [$branch], []);
        $this->attachToGym($member, $gym, [$branch], []);
        $this->loginGymUser($owner);

        $this->get(route('web.gym.payments.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Payments');

        $this->post(route('web.gym.payments.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'member_membership_id' => $membership->id,
            'amount' => 500,
            'payment_mode' => 'cash',
            'payment_date' => now()->toDateTimeString(),
        ])->assertRedirect(route('web.gym.payments.index'));

        $membership->refresh();
        $this->assertSame('partial', $membership->payment_status);
        $this->assertSame(500.0, (float) $membership->amount_paid);
        $this->assertSame(1400.0, (float) $membership->due_amount);

        $this->post(route('web.gym.payments.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'member_membership_id' => $membership->id,
            'amount' => 1400,
            'payment_mode' => 'upi',
            'payment_date' => now()->toDateTimeString(),
        ])->assertRedirect(route('web.gym.payments.index'));

        $membership->refresh();
        $payment = Payment::query()->latest('id')->firstOrFail();

        $this->assertSame('paid', $membership->payment_status);
        $this->assertSame(1900.0, (float) $membership->amount_paid);
        $this->assertSame(0.0, (float) $membership->due_amount);

        $this->get(route('web.gym.payments.show', ['gym' => $gym->id, 'branch' => $branch->id, 'payment' => $payment->id]))
            ->assertOk()
            ->assertSee($member->name);

        $this->get(route('web.gym.members.payments', ['gym' => $gym->id, 'branch' => $branch->id, 'member' => $member->id]))
            ->assertOk()
            ->assertSee($member->name);
    }

    public function test_api_top_level_payment_endpoints_work_and_custom_fee_is_respected(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'payments-api-member@example.com');
        [$gym, $branch, $membership] = $this->makeScopedMembership($owner, $member, [
            'custom_fee_enabled' => true,
            'custom_fee_amount' => 1800,
            'custom_fee_reason' => 'API pricing',
            'final_payable_amount' => 1900,
            'amount_paid' => 200,
            'due_amount' => 1700,
            'payment_status' => 'partial',
        ]);

        $this->attachToGym($owner, $gym, [$branch], []);
        $this->attachToGym($member, $gym, [$branch], []);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $store = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/payments', [
                'member_membership_id' => $membership->id,
                'amount' => 700,
                'payment_mode' => 'card',
                'payment_date' => now()->toDateTimeString(),
            ], $headers);

        $store->assertCreated()
            ->assertJsonPath('data.amount', 700);

        $membership->refresh();
        $payment = Payment::query()->latest('id')->firstOrFail();

        $this->assertSame(1200.0, (float) $membership->due_amount);
        $this->assertSame('partial', $membership->payment_status);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/payments?gym_id='.$gym->id.'&branch_id='.$branch->id, $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/payments/'.$payment->id, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/dues?gym_id='.$gym->id.'&branch_id='.$branch->id, $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/members/'.$member->id.'/payments?gym_id='.$gym->id.'&branch_id='.$branch->id, $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/payments/reports?gym_id='.$gym->id.'&branch_id='.$branch->id, $headers)
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_staff_permissions_control_billing_visibility_and_collection(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $staff = $this->makeRoleUser(RoleName::GymStaff->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'payments-staff-member@example.com');
        [$gym, $branch, $membership] = $this->makeScopedMembership($owner, $member);

        $this->attachToGym($owner, $gym, [$branch], []);
        $this->attachToGym($staff, $gym, [$branch], []);
        $this->attachToGym($member, $gym, [$branch], []);

        $this->actingAs($staff);
        $this->get(route('web.gym.payments.index', ['gym' => $gym->id, 'branch' => $branch->id]))->assertForbidden();

        $this->attachToGym($staff, $gym, [$branch], ['view_billing']);
        $this->actingAs($staff->fresh());
        $this->get(route('web.gym.payments.index', ['gym' => $gym->id, 'branch' => $branch->id]))->assertOk();
        $this->get(route('web.gym.payments.create', ['gym' => $gym->id, 'branch' => $branch->id]))->assertForbidden();

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($staff->fresh(), 'sanctum')
            ->postJson('/api/gym/payments', [
                'member_membership_id' => $membership->id,
                'amount' => 300,
                'payment_mode' => 'cash',
            ], $headers)
            ->assertForbidden();

        $this->attachToGym($staff, $gym, [$branch], ['view_billing', 'collect_payment']);
        $this->actingAs($staff->fresh(), 'sanctum')
            ->postJson('/api/gym/payments', [
                'member_membership_id' => $membership->id,
                'amount' => 300,
                'payment_mode' => 'cash',
            ], $headers)
            ->assertCreated();
    }

    public function test_cancelled_membership_cannot_receive_payment_via_top_level_route(): void
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value, 'secret123', 'payments-cancelled-member@example.com');
        [$gym, $branch, $membership] = $this->makeScopedMembership($owner, $member, [
            'status' => 'cancelled',
        ]);

        $this->attachToGym($owner, $gym, [$branch], []);
        $this->attachToGym($member, $gym, [$branch], []);

        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/payments', [
                'member_membership_id' => $membership->id,
                'amount' => 500,
                'payment_mode' => 'cash',
            ], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.membership.0', 'Payments cannot be recorded for a cancelled membership.');
    }

    private function makeScopedMembership(User $owner, User $member, array $membershipOverrides = []): array
    {
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Payments Gym',
            'slug' => 'payments-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Payments Branch',
            'slug' => 'payments-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 100,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $membership = MemberMembership::query()->create(array_merge([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 2000,
            'default_joining_fee' => 100,
            'custom_fee_enabled' => false,
            'custom_fee_amount' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_joining_fee' => 100,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2100,
            'amount_paid' => 0,
            'due_amount' => 2100,
            'due_date' => now()->addDays(5)->toDateString(),
            'payment_status' => 'unpaid',
            'approved_by_admin_id' => $owner->id,
        ], $membershipOverrides));

        return [$gym, $branch, $membership];
    }

    private function attachToGym(User $user, Gym $gym, array $branches = [], array $customPermissions = []): void
    {
        $payload = [
            'custom_permissions' => json_encode($customPermissions),
            'is_primary' => true,
        ];

        if ($user->gyms()->where('gyms.id', $gym->id)->exists()) {
            $user->gyms()->updateExistingPivot($gym->id, $payload);
        } else {
            $user->gyms()->attach($gym->id, $payload);
        }

        foreach ($branches as $branch) {
            $branchPayload = [
                'custom_permissions' => json_encode($customPermissions),
                'is_primary' => false,
            ];

            if ($user->branches()->where('branches.id', $branch->id)->exists()) {
                $user->branches()->updateExistingPivot($branch->id, $branchPayload);
            } else {
                $user->branches()->attach($branch->id, $branchPayload);
            }
        }
    }

    private function loginGymUser(User $user, string $password = 'secret123'): void
    {
        $this->post('/gym/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('web.gym.dashboard'));
    }

    private function makeRoleUser(string $role, string $password = 'secret123', ?string $email = null): User
    {
        $user = User::factory()->create([
            'password' => $password,
            'email' => $email ?? fake()->unique()->safeEmail(),
            'is_active' => true,
            'active_role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
