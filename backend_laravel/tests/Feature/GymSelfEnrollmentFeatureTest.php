<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\FitnessGoal;
use App\Models\Gym;
use App\Models\GymSelfEnrollmentLink;
use App\Models\MemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GymSelfEnrollmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_new_visitor_creates_reusable_account_and_active_gym_profile_without_invitation(): void
    {
        [$gym, $branch, $link] = $this->gymFixture('new');
        $gym->update(['logo_url' => 'https://cdn.example.com/new-gym-logo.png']);
        $goal = FitnessGoal::query()->create([
            'name' => 'Build Strength',
            'slug' => 'build-strength',
            'status' => 'active',
            'is_active' => true,
        ]);

        $page = $this->get(route('public.self-enrollment.show', $link->token));
        $page
            ->assertOk()
            ->assertSee('Choose how to continue')
            ->assertSee('Atlas account')
            ->assertSee('Contact details')
            ->assertSee($branch->name)
            ->assertSee('new-gym-logo.png', false)
            ->assertSee('data-enrollment-shell', false)
            ->assertSee('data-initial-step="1"', false)
            ->assertDontSee('existing-member-card', false)
            ->assertDontSee('public-header', false)
            ->assertDontSee('public-footer', false);

        $html = $page->getContent();
        $this->assertMatchesRegularExpression('/<button id="enroll-next"[^>]*disabled[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<button id="enroll-submit"[^>]*hidden[^>]*disabled[^>]*>/', $html);
        $this->assertStringContainsString('submit.hidden = current !== totalSteps', $html);
        $this->assertStringContainsString('for (let stepNumber = 1; stepNumber <= totalSteps; stepNumber++)', $html);

        $response = $this->post(route('public.self-enrollment.store', $link->token), [
            'name' => 'New Atlas Member',
            'email' => 'new-atlas@example.com',
            'phone' => '+91 90000 00000',
            'branch_id' => $branch->id,
            'fitness_goal_ids' => [$goal->id],
            'experience_level' => 'beginner',
            'height_cm' => 172,
            'weight_kg' => 68.5,
            'injury_notes' => 'Old ankle strain',
            'medical_notes' => 'No medication',
            'consent' => '1',
            'whatsapp_marketing_consent' => '1',
        ]);

        $member = User::query()->where('email', 'new-atlas@example.com')->firstOrFail();
        $response->assertRedirect(route('public.self-enrollment.success', [
            'token' => $link->token,
            'submission' => $link->submissions()->value('id'),
        ]));
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Welcome to '.$gym->name)
            ->assertSee('Powered by Gym Atlas')
            ->assertDontSee('public-footer', false);
        $this->assertSame('self_enrollment', $member->auth_provider);
        $this->assertTrue($member->hasRole(RoleName::Member->value));
        $this->assertTrue($member->member_onboarding_completed);
        $this->assertSame(8, $member->member_onboarding_step);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
            'fitness_goal' => 'Build Strength',
            'experience_level' => 'beginner',
        ]);
        $this->assertDatabaseHas('gym_self_enrollment_submissions', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'outcome' => 'enrolled',
            'source' => 'web',
        ]);
        $this->assertDatabaseCount('member_gym_invitations', 0);
        $this->assertDatabaseCount('member_email_invitations', 0);
        $this->assertDatabaseCount('member_memberships', 0);
        $this->assertDatabaseHas('whatsapp_consents', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'purpose' => 'utility',
            'status' => 'granted',
            'source' => 'gym_self_enrollment',
        ]);
        $this->assertDatabaseHas('whatsapp_consents', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'purpose' => 'marketing',
            'status' => 'granted',
        ]);

        $this->post(route('public.self-enrollment.store', $link->token), [
            'name' => 'New Atlas Member',
            'email' => 'new-atlas@example.com',
            'phone' => '+91 90000 00000',
            'branch_id' => $branch->id,
            'fitness_goal_ids' => [$goal->id],
            'experience_level' => 'beginner',
            'height_cm' => 172,
            'weight_kg' => 68.5,
            'consent' => '1',
        ])->assertRedirect();
        $this->assertSame(1, User::query()->where('email', 'new-atlas@example.com')->count());
        $this->assertSame(1, MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->count());
    }

    public function test_existing_member_reuses_profile_and_joins_in_one_authenticated_action(): void
    {
        [$targetGym, $targetBranch, $link] = $this->gymFixture('target');
        [$sourceGym, $sourceBranch] = $this->gymFixture('source');
        $goal = FitnessGoal::query()->create([
            'name' => 'Fat Loss',
            'slug' => 'fat-loss',
            'status' => 'active',
            'is_active' => true,
        ]);
        $member = User::factory()->create([
            'email' => 'existing-atlas@example.com',
            'active_role' => RoleName::Member->value,
            'auth_provider' => 'firebase_google',
            'is_active' => true,
        ]);
        $member->assignRole(RoleName::Member->value);
        $member->gyms()->attach($sourceGym->id, [
            'branch_id' => $sourceBranch->id,
            'role_name' => RoleName::Member->value,
            'status' => 'active',
            'is_primary' => true,
        ]);
        $sourceProfile = MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $sourceGym->id,
            'branch_id' => $sourceBranch->id,
            'fitness_goal' => 'Fat Loss',
            'experience_level' => 'intermediate',
            'height_cm' => 178,
            'weight_kg' => 77,
            'injury_notes' => 'Knee discomfort',
            'medical_notes' => 'Asthma',
            'status' => 'active',
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        $sourceProfile->fitnessGoals()->attach($goal->id);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/self-enrollment/'.$link->token.'/preview', [
                'X-Gym-Id' => (string) $sourceGym->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.profile.name', $member->name)
            ->assertJsonPath('data.profile.fitness_goals.0.name', 'Fat Loss');

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/self-enrollment/'.$link->token, [
                'branch_id' => $targetBranch->id,
                'reuse_profile' => true,
                'consent' => true,
            ], ['X-Gym-Id' => (string) $sourceGym->id])
            ->assertOk()
            ->assertJsonPath('data.outcome', 'enrolled')
            ->assertJsonPath('data.gym_id', $targetGym->id);

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $targetGym->id,
            'branch_id' => $targetBranch->id,
            'fitness_goal' => 'Fat Loss',
            'experience_level' => 'intermediate',
            'height_cm' => 178,
            'weight_kg' => 77,
            'injury_notes' => 'Knee discomfort',
            'medical_notes' => 'Asthma',
        ]);
        $this->assertSame('firebase_google', $member->fresh()->auth_provider);
        $this->assertDatabaseCount('member_gym_invitations', 0);
    }

    public function test_public_form_redirects_existing_account_to_account_reuse_lane(): void
    {
        [, $branch, $link] = $this->gymFixture('existing-email');
        $goal = FitnessGoal::query()->create([
            'name' => 'Mobility',
            'slug' => 'mobility',
            'status' => 'active',
            'is_active' => true,
        ]);
        User::factory()->create(['email' => 'member@example.com']);

        $this->from(route('public.self-enrollment.show', $link->token))
            ->post(route('public.self-enrollment.store', $link->token), [
                'name' => 'Existing Member',
                'email' => 'MEMBER@example.com',
                'phone' => '9999999999',
                'branch_id' => $branch->id,
                'fitness_goal_ids' => [$goal->id],
                'experience_level' => 'advanced',
                'height_cm' => 180,
                'weight_kg' => 80,
                'consent' => '1',
            ])
            ->assertRedirect(route('public.self-enrollment.show', $link->token))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('gym_self_enrollment_submissions', 0);
        $this->assertDatabaseCount('member_profiles', 0);
    }

    public function test_server_validation_returns_user_to_the_step_that_needs_attention(): void
    {
        [, $branch, $link] = $this->gymFixture('validation-step');
        $goal = FitnessGoal::query()->create([
            'name' => 'Conditioning',
            'slug' => 'conditioning',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->followingRedirects()
            ->from(route('public.self-enrollment.show', $link->token))
            ->post(route('public.self-enrollment.store', $link->token), [
                'name' => 'Validation Member',
                'email' => 'validation-member@example.com',
                'phone' => '9999999999',
                'branch_id' => $branch->id,
                'fitness_goal_ids' => [$goal->id],
                'experience_level' => 'beginner',
                'height_cm' => 170,
                'weight_kg' => 70,
            ]);

        $response
            ->assertOk()
            ->assertSee('data-initial-step="5"', false)
            ->assertSee('Check the highlighted step.')
            ->assertSee('The consent field must be accepted.');
    }

    public function test_repeat_authenticated_submission_is_idempotent(): void
    {
        [$gym, $branch, $link] = $this->gymFixture('repeat');
        $member = User::factory()->create([
            'active_role' => RoleName::Member->value,
            'is_active' => true,
        ]);
        $member->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/self-enrollment/'.$link->token, [
                'consent' => true,
                'reuse_profile' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.outcome', 'already_enrolled');

        $this->assertSame(1, MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->count());
    }

    /** @return array{Gym, Branch, GymSelfEnrollmentLink} */
    private function gymFixture(string $slug): array
    {
        $gym = Gym::query()->create([
            'name' => Str::headline($slug).' Gym',
            'slug' => $slug.'-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'operational_access_enabled' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => Str::headline($slug).' Branch',
            'slug' => $slug.'-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $link = GymSelfEnrollmentLink::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'token' => (string) Str::uuid(),
            'name' => $branch->name.' reception',
            'is_active' => true,
        ]);

        return [$gym, $branch, $link];
    }
}
