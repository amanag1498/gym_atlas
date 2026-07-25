<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\DietPlanTemplate;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DietTemplateAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_build_a_global_template_with_repeatable_food_products(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $this->actingAs($admin)
            ->get(route('web.admin.diet-templates.create'))
            ->assertOk()
            ->assertSee('Add food/product');

        $this->actingAs($admin)
            ->post(route('web.admin.diet-templates.store'), [
                'name' => 'Platform Complete Nutrition',
                'goal' => 'Recovery',
                'status' => 'active',
                'meals' => [[
                    'name' => 'Post workout',
                    'meal_type' => 'snack',
                    'protein_g' => 40,
                    'items' => [
                        [
                            'name' => 'Whey protein',
                            'quantity' => '1 scoop',
                            'calories' => 120,
                            'protein_g' => 24,
                        ],
                        [
                            'name' => 'Banana',
                            'quantity' => '1 medium',
                            'calories' => 105,
                            'carbs_g' => 27,
                        ],
                    ],
                ]],
            ])
            ->assertRedirect(route('web.admin.diet-templates.index'));

        $template = DietPlanTemplate::query()
            ->where('name', 'Platform Complete Nutrition')
            ->firstOrFail();

        $this->assertCount(2, $template->meals[0]['items']);
        $this->assertSame(
            'Whey protein',
            $template->meals[0]['items'][0]['name'],
        );
        $this->assertSame(
            'Banana',
            $template->meals[0]['items'][1]['name'],
        );
    }

    public function test_trainer_clones_global_template_only_to_assigned_member(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch, $trainer, $member] = $this->context();
        $template = $this->template();

        $this->actingAs($trainer, 'sanctum')
            ->postJson("/api/trainer/diet-templates/{$template->id}/assign", [
                'member_ids' => [$member->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.member_id', $member->id)
            ->assertJsonPath('data.0.gym_id', $gym->id)
            ->assertJsonCount(2, 'data.0.meals.0.items');

        $this->assertDatabaseHas('diet_plans', [
            'member_id' => $member->id,
            'trainer_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => $template->name,
        ]);

        $outsider = User::factory()->create(['active_role' => RoleName::Member->value]);
        $outsider->assignRole(RoleName::Member->value);
        $this->actingAs($trainer, 'sanctum')
            ->postJson("/api/trainer/diet-templates/{$template->id}/assign", [
                'member_ids' => [$outsider->id],
            ])
            ->assertUnprocessable();

        $template->update(['status' => 'inactive']);
        $this->actingAs($trainer, 'sanctum')
            ->postJson("/api/trainer/diet-templates/{$template->id}/assign", [
                'member_ids' => [$member->id],
            ])
            ->assertUnprocessable();
    }

    public function test_member_adopts_template_as_owner_scoped_personal_plan(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch, , $member] = $this->context();
        $template = $this->template();

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/diet-templates/{$template->id}/adopt")
            ->assertCreated()
            ->assertJsonPath('data.member_id', $member->id)
            ->assertJsonPath('data.trainer_id', null)
            ->assertJsonPath('data.is_member_owned', true)
            ->assertJsonCount(2, 'data.meals.0.items');

        $this->assertDatabaseHas('diet_plans', [
            'member_id' => $member->id,
            'trainer_id' => null,
            'created_by_user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
        ]);

        $template->update(['status' => 'inactive']);
        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/diet-templates/{$template->id}/adopt")
            ->assertUnprocessable();
    }

    private function context(): array
    {
        $owner = User::factory()->create();
        $gym = Gym::query()->create(['owner_user_id' => $owner->id, 'name' => 'Diet Scope Gym', 'slug' => 'diet-scope-gym', 'timezone' => 'Asia/Kolkata', 'status' => 'active', 'is_active' => true]);
        $branch = Branch::query()->create(['gym_id' => $gym->id, 'name' => 'Main', 'slug' => 'main', 'timezone' => 'Asia/Kolkata', 'status' => 'active', 'is_active' => true]);
        $trainer = User::factory()->create(['active_role' => RoleName::Trainer->value]);
        $trainer->assignRole(RoleName::Trainer->value);
        $trainer->gyms()->attach($gym);
        $trainer->branches()->attach($branch);
        TrainerProfile::query()->create(['user_id' => $trainer->id, 'gym_id' => $gym->id, 'branch_id' => $branch->id, 'specializations' => [], 'certifications' => [], 'languages' => [], 'is_active' => true]);
        $member = User::factory()->create(['active_role' => RoleName::Member->value]);
        $member->assignRole(RoleName::Member->value);
        MemberProfile::query()->create(['user_id' => $member->id, 'gym_id' => $gym->id, 'branch_id' => $branch->id, 'assigned_trainer_user_id' => $trainer->id, 'membership_status' => 'active', 'is_active' => true]);

        return [$gym, $branch, $trainer, $member];
    }

    private function template(): DietPlanTemplate
    {
        return DietPlanTemplate::query()->create([
            'name' => 'Global Strength Nutrition',
            'goal' => 'Strength',
            'daily_calorie_target' => 2400,
            'protein_target_g' => 160,
            'carbs_target_g' => 280,
            'fats_target_g' => 70,
            'status' => 'active',
            'meals' => [[
                'name' => 'Breakfast',
                'meal_type' => 'breakfast',
                'items' => [
                    ['name' => 'Oats', 'quantity' => '80g', 'calories' => 300],
                    ['name' => 'Milk', 'quantity' => '250ml', 'calories' => 150],
                ],
            ]],
        ]);
    }
}
