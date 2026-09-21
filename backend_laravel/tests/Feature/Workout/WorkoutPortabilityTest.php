<?php

namespace Tests\Feature\Workout;

use App\Enums\RoleName;
use App\Enums\WorkoutSessionStatus;
use App\Models\BodyMeasurement;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WeightLog;
use App\Models\WorkoutHistoryImportBatch;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Services\Workout\WorkoutPlanService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutPortabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_member_exports_plan_pdf_and_data_without_private_gym_scopes(): void
    {
        [$member, $plan] = $this->memberPlan();
        WeightLog::query()->create([
            'member_id' => $member->id,
            'logged_by_user_id' => $member->id,
            'log_date' => '2026-09-01',
            'weight_kg' => 82.5,
            'notes' => 'Morning',
        ]);
        BodyMeasurement::query()->create([
            'member_id' => $member->id,
            'logged_by_user_id' => $member->id,
            'measured_on' => '2026-09-01',
            'waist_cm' => 84,
            'notes' => 'Monthly check',
        ]);

        $this->actingAs($member, 'sanctum')
            ->get('/api/member/workout-plans/'.$plan->id.'/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $export = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-data/export')
            ->assertOk()
            ->assertJsonPath('schema_version', 'gym-atlas.workout-export.v1')
            ->json();

        $this->assertSame($plan->id, $export['plans'][0]['id']);
        $this->assertArrayNotHasKey('payments', $export);
        $this->assertArrayNotHasKey('memberships', $export);
        $this->assertSame(['log_date', 'weight_kg', 'notes'], array_keys($export['progress']['weight_logs'][0]));
        $this->assertArrayNotHasKey('gym_id', $export['progress']['body_measurements'][0]);
        $this->assertArrayNotHasKey('branch_id', $export['progress']['body_measurements'][0]);
        $this->assertArrayNotHasKey('logged_by_user_id', $export['progress']['body_measurements'][0]);
    }

    public function test_shared_plan_adoption_creates_new_member_owned_plan_without_overwriting_source(): void
    {
        [$owner, $plan] = $this->memberPlan();
        $recipient = $this->member('recipient@example.com');

        $share = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/member/workout-plans/'.$plan->id.'/shares', [
                'recipient_user_id' => $recipient->id,
                'expires_in_days' => 7,
            ])
            ->assertCreated()
            ->json('data');

        $adopted = $this->actingAs($recipient, 'sanctum')
            ->postJson('/api/member/workout-plan-shares/'.$share['token'].'/adopt', [
                'name' => 'Adopted strength plan',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Adopted strength plan')
            ->assertJsonPath('data.plan_origin', 'shared_adopted')
            ->json('data');

        $this->assertNotSame($plan->id, $adopted['id']);
        $this->assertDatabaseHas('workout_plans', [
            'id' => $plan->id,
            'member_id' => $owner->id,
            'name' => $plan->name,
        ]);
        $this->assertDatabaseHas('workout_plans', [
            'id' => $adopted['id'],
            'member_id' => $recipient->id,
            'source_shared_workout_plan_id' => $plan->id,
            'source_shared_by_user_id' => $owner->id,
        ]);
    }

    public function test_member_creates_a_public_link_that_previews_the_plan(): void
    {
        [$owner, $plan] = $this->memberPlan();

        $share = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/member/workout-plans/'.$plan->id.'/shares', [
                'expires_in_days' => 7,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame(
            route('public.workout-plans.shared', $share['token']),
            $share['share_url'],
        );

        $this->get($share['share_url'])
            ->assertOk()
            ->assertSee('Portable Strength')
            ->assertSee('Save your own copy')
            ->assertSee('Open in Gym Atlas')
            ->assertSee('/workouts/shared/'.$share['token'], false)
            ->assertSee('Bench Press');
    }

    public function test_recipient_scoped_share_does_not_expose_a_public_preview(): void
    {
        [$owner, $plan] = $this->memberPlan();
        $recipient = $this->member('private-recipient@example.com');

        $share = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/member/workout-plans/'.$plan->id.'/shares', [
                'recipient_user_id' => $recipient->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->get(route('public.workout-plans.shared', $share['token']))
            ->assertNotFound();
    }

    public function test_shared_plan_recipient_scope_is_enforced(): void
    {
        [$owner, $plan] = $this->memberPlan();
        $recipient = $this->member('recipient@example.com');
        $otherMember = $this->member('other-recipient@example.com');

        $share = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/member/workout-plans/'.$plan->id.'/shares', [
                'recipient_user_id' => $recipient->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($otherMember, 'sanctum')
            ->postJson('/api/member/workout-plan-shares/'.$share['token'].'/adopt')
            ->assertUnprocessable()
            ->assertJsonPath('errors.share.0', 'This workout plan share was sent to another member.');
    }

    public function test_member_can_revoke_owned_share_before_adoption(): void
    {
        [$owner, $plan] = $this->memberPlan();
        $recipient = $this->member('recipient@example.com');
        $otherMember = $this->member('other-recipient@example.com');

        $share = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/member/workout-plans/'.$plan->id.'/shares', [
                'recipient_user_id' => $recipient->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($otherMember, 'sanctum')
            ->deleteJson('/api/member/workout-plan-shares/'.$share['id'])
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/member/workout-plan-shares/'.$share['id'])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->actingAs($recipient, 'sanctum')
            ->postJson('/api/member/workout-plan-shares/'.$share['token'].'/adopt')
            ->assertUnprocessable()
            ->assertJsonPath('errors.share.0', 'This workout plan share is no longer available.');
    }

    public function test_history_import_preview_confirm_is_idempotent(): void
    {
        [$member] = $this->memberPlan();
        $exercise = Exercise::query()->firstOrFail();
        $csv = "Date,Exercise,Set,Reps,Weight,Unit\n2026-09-10,".$exercise->name.',1,8,100,kg';

        $preview = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-history/imports/preview', [
                'source_format' => 'strong_csv',
                'timezone' => 'Asia/Kolkata',
                'csv_text' => $csv,
            ])
            ->assertCreated()
            ->assertJsonPath('data.summary.matched', 1)
            ->json('data');

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-history/imports/'.$preview['id'].'/confirm')
            ->assertOk()
            ->assertJsonPath('data.summary.created_sessions', 1);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-history/imports/'.$preview['id'].'/confirm')
            ->assertOk()
            ->assertJsonPath('data.summary.created_sessions', 1);

        $this->assertDatabaseCount(WorkoutHistoryImportBatch::class, 1);
        $this->assertSame(1, WorkoutSession::query()
            ->where('member_id', $member->id)
            ->where('status', WorkoutSessionStatus::Completed->value)
            ->count());
    }

    public function test_history_import_accepts_body_weight_rows_without_exercise_name(): void
    {
        [$member] = $this->memberPlan();

        $preview = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-history/imports/preview', [
                'source_format' => 'health_body_weight_csv',
                'timezone' => 'Asia/Kolkata',
                'csv_text' => "Date,Body Weight,Unit\n2026-09-11,180,lb",
            ])
            ->assertCreated()
            ->assertJsonPath('data.summary.body_weight', 1)
            ->json('data');

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/workout-history/imports/'.$preview['id'].'/confirm')
            ->assertOk();

        $this->assertDatabaseHas('weight_logs', [
            'member_id' => $member->id,
            'log_date' => '2026-09-11 00:00:00',
            'notes' => 'Imported body weight from health_body_weight_csv',
        ]);
        $this->assertSame(81.65, (float) WeightLog::query()->where('member_id', $member->id)->firstOrFail()->weight_kg);
    }

    /** @return array{User, WorkoutPlan} */
    private function memberPlan(): array
    {
        $member = $this->member();
        $exercise = Exercise::query()->create([
            'name' => 'Bench Press',
            'body_part' => 'chest',
            'muscle_group' => 'chest',
            'target_muscle' => 'pectorals',
            'equipment' => 'barbell',
            'is_global' => true,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $plan = app(WorkoutPlanService::class)->createMemberPlan($member, [
            'name' => 'Portable Strength',
            'goal' => 'Strength',
            'duration_weeks' => 4,
            'weekly_schedule' => ['monday'],
            'days' => [[
                'day_number' => 1,
                'label' => 'Push',
                'exercises' => [[
                    'exercise_id' => $exercise->id,
                    'sets' => 3,
                    'reps' => '8-10',
                    'target_weight' => 60,
                ]],
            ]],
        ]);

        return [$member, $plan];
    }

    private function member(string $email = 'member@example.com'): User
    {
        $member = User::factory()->create([
            'email' => $email,
            'active_role' => RoleName::Member->value,
            'is_active' => true,
        ]);
        $member->assignRole(RoleName::Member->value);

        return $member;
    }
}
