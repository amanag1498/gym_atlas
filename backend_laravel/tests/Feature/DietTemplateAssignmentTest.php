<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\DietMealLog;
use App\Models\DietPlan;
use App\Models\DietPlanTemplate;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DietTemplateAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainer_diet_builder_accepts_custom_meals_and_ignores_blank_food_rows(): void
    {
        $this->seed(PermissionSeeder::class);
        [, , $trainer] = $this->context();

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/diet-templates', [
                'name' => 'Flexible meal plan',
                'meals' => [
                    [
                        'name' => 'Pre-workout fuel',
                        'items' => [
                            ['name' => 'Banana', 'quantity' => '1'],
                            ['name' => '', 'quantity' => ''],
                        ],
                    ],
                    [
                        'name' => '',
                        'items' => [],
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.meals.0.name', 'Pre-workout fuel')
            ->assertJsonPath('data.meals.0.meal_type', 'pre_workout_fuel')
            ->assertJsonCount(1, 'data.meals.0.items')
            ->assertJsonPath('data.meals.1.name', 'Meal 2')
            ->assertJsonPath('data.meals.1.meal_type', 'meal_2');
    }

    public function test_diet_permission_backfill_repairs_an_existing_trainer_role(): void
    {
        $this->seed(PermissionSeeder::class);
        $trainerRole = Role::findByName(RoleName::Trainer->value, 'sanctum');
        $trainerRole->revokePermissionTo([
            PermissionName::DietPlansView->value,
            PermissionName::DietPlansManage->value,
        ]);

        $this->assertFalse(
            $trainerRole->fresh()->hasPermissionTo(
                PermissionName::DietPlansView->value,
            ),
        );

        $migration = require database_path(
            'migrations/2026_07_29_120000_grant_diet_plan_permissions_to_trainers.php',
        );
        $migration->up();

        $repairedRole = $trainerRole->fresh();
        $this->assertTrue(
            $repairedRole->hasPermissionTo(
                PermissionName::DietPlansView->value,
            ),
        );
        $this->assertTrue(
            $repairedRole->hasPermissionTo(
                PermissionName::DietPlansManage->value,
            ),
        );
    }

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

    public function test_platform_admin_app_api_can_create_and_edit_global_diet_templates(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/platform-admin/diet-templates', [
                'name' => 'Admin App Nutrition',
                'goal' => 'Wellness',
                'status' => 'active',
                'meals' => [[
                    'name' => 'Breakfast',
                    'items' => [[
                        'name' => 'Idli',
                        'quantity' => '3 pieces',
                        'calories' => 180,
                    ]],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.meals.0.items.0.name', 'Idli')
            ->assertJsonPath('data.status', 'active');

        $templateId = $response->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/platform-admin/diet-templates/{$templateId}", [
                'name' => 'Admin App Nutrition Updated',
                'goal' => 'Wellness',
                'status' => 'inactive',
                'meals' => [[
                    'name' => 'Breakfast',
                    'items' => [[
                        'name' => 'Idli and sambar',
                        'quantity' => '1 plate',
                        'calories' => 260,
                    ]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Admin App Nutrition Updated')
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_gym_admin_app_api_can_assign_a_global_template_in_scope(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch, , $member, $owner] = $this->context();
        $template = $this->template();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/gym/diet-templates?gym_id={$gym->id}&branch_id={$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $template->id)
            ->assertJsonPath('meta.pagination.current_page', 1);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/gym/diet-templates/{$template->id}/assign", [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'member_ids' => [$member->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.gym_id', $gym->id)
            ->assertJsonPath('data.0.branch_id', $branch->id)
            ->assertJsonCount(2, 'data.0.meals.0.items');
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

        $this->actingAs($trainer, 'sanctum')
            ->getJson("/api/trainer/diet-plans?member_id={$member->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.member_id', $member->id)
            ->assertJsonPath('data.0.meals.0.items.0.name', 'Oats');

        $outsider = User::factory()->create(['active_role' => RoleName::Member->value]);
        $outsider->assignRole(RoleName::Member->value);
        $this->actingAs($trainer, 'sanctum')
            ->getJson("/api/trainer/diet-plans?member_id={$outsider->id}")
            ->assertUnprocessable();

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

    public function test_trainer_can_manage_only_their_own_diet_library_templates(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch, $trainer] = $this->context();
        $globalTemplate = $this->template();

        $created = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/diet-templates', [
                'name' => 'Trainer Recovery Plan',
                'goal' => 'Recovery',
                'daily_calorie_target' => 2200,
                'status' => 'active',
                'meals' => [[
                    'name' => 'Breakfast',
                    'meal_type' => 'breakfast',
                    'items' => [[
                        'name' => 'Eggs',
                        'quantity' => '4',
                        'protein_g' => 24,
                    ]],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_owned', true)
            ->assertJsonPath('data.source', 'trainer');

        $templateId = $created->json('data.id');

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/diet-templates')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 20)
            ->assertJsonFragment([
                'id' => $globalTemplate->id,
                'source' => 'atlas',
            ])
            ->assertJsonFragment([
                'id' => $templateId,
                'is_owned' => true,
            ]);

        $this->actingAs($trainer, 'sanctum')
            ->putJson("/api/trainer/diet-templates/{$templateId}", [
                'name' => 'Trainer Recovery Plan Updated',
                'goal' => 'Recovery',
                'status' => 'active',
                'meals' => [[
                    'name' => 'Breakfast',
                    'items' => [['name' => 'Eggs and toast']],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Trainer Recovery Plan Updated');

        $otherTrainer = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
        ]);
        $otherTrainer->assignRole(RoleName::Trainer->value);
        $otherTrainer->gyms()->attach($gym);
        $otherTrainer->branches()->attach($branch);
        TrainerProfile::query()->create([
            'user_id' => $otherTrainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specializations' => [],
            'certifications' => [],
            'languages' => [],
            'is_active' => true,
        ]);

        $this->actingAs($otherTrainer, 'sanctum')
            ->deleteJson("/api/trainer/diet-templates/{$templateId}")
            ->assertUnprocessable();

        $this->actingAs($trainer, 'sanctum')
            ->deleteJson("/api/trainer/diet-templates/{$templateId}")
            ->assertOk();

        $this->assertDatabaseMissing('diet_plan_templates', [
            'id' => $templateId,
        ]);
    }

    public function test_trainer_can_build_edit_review_and_delete_an_assigned_members_diet_plan(): void
    {
        $this->seed(PermissionSeeder::class);
        [, , $trainer, $member] = $this->context();

        $response = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/diet-plans', [
                'member_ids' => [$member->id],
                'name' => 'Member Fat Loss Plan',
                'goal' => 'Fat loss',
                'daily_calorie_target' => 1900,
                'protein_target_g' => 140,
                'starts_on' => '2026-08-01',
                'ends_on' => '2026-08-31',
                'meals' => [[
                    'name' => 'Breakfast',
                    'meal_type' => 'breakfast',
                    'scheduled_time' => '08:30',
                    'items' => [[
                        'name' => 'Paneer bhurji',
                        'quantity' => '200g',
                        'calories' => 420,
                        'protein_g' => 36,
                    ]],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.member_id', $member->id)
            ->assertJsonPath('data.0.trainer_id', $trainer->id)
            ->assertJsonPath('data.0.meals.0.items.0.name', 'Paneer bhurji');

        $planId = $response->json('data.0.id');
        $mealId = $response->json('data.0.meals.0.id');
        $itemId = $response->json('data.0.meals.0.items.0.id');

        $this->actingAs($trainer, 'sanctum')
            ->putJson("/api/trainer/diet-plans/{$planId}", [
                'name' => 'Member Fat Loss Plan Updated',
                'goal' => 'Fat loss and recovery',
                'daily_calorie_target' => 2000,
                'protein_target_g' => 150,
                'status' => 'active',
                'starts_on' => '2026-08-01',
                'ends_on' => '2026-09-15',
                'meals' => [[
                    'id' => $mealId,
                    'name' => 'Breakfast',
                    'meal_type' => 'breakfast',
                    'scheduled_time' => '09:00',
                    'items' => [[
                        'id' => $itemId,
                        'name' => 'Paneer and vegetables',
                        'quantity' => '1 plate',
                        'calories' => 460,
                        'protein_g' => 40,
                    ]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Member Fat Loss Plan Updated')
            ->assertJsonPath('data.daily_calorie_target', 2000)
            ->assertJsonPath('data.meals.0.scheduled_time', '09:00')
            ->assertJsonPath('data.meals.0.items.0.name', 'Paneer and vegetables');

        $this->actingAs($trainer, 'sanctum')
            ->getJson("/api/trainer/diet-plans?member_id={$member->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $planId)
            ->assertJsonPath('data.0.meals.0.items.0.protein_g', 40);

        $this->actingAs($trainer, 'sanctum')
            ->deleteJson("/api/trainer/diet-plans/{$planId}")
            ->assertOk();

        $this->assertDatabaseMissing('diet_plans', ['id' => $planId]);
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

    public function test_member_catalog_hides_trainer_templates_until_they_are_assigned(): void
    {
        $this->seed(PermissionSeeder::class);
        [$gym, $branch, $trainer, $assignedMember] = $this->context();
        $globalTemplate = $this->template();
        $trainerTemplate = DietPlanTemplate::query()->create([
            'created_by_user_id' => $trainer->id,
            'name' => 'Trainer Private Nutrition',
            'goal' => 'Private coaching',
            'status' => 'active',
            'meals' => [[
                'name' => 'Coach meal',
                'meal_type' => 'coach_meal',
                'items' => [['name' => 'Paneer', 'quantity' => '150g']],
            ]],
        ]);
        $otherMember = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $otherMember->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $otherMember->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($assignedMember, 'sanctum')
            ->getJson('/api/member/diet-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $globalTemplate->id)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonMissing(['id' => $trainerTemplate->id]);

        $this->actingAs($assignedMember, 'sanctum')
            ->postJson("/api/member/diet-templates/{$trainerTemplate->id}/adopt")
            ->assertUnprocessable();

        $this->actingAs($trainer, 'sanctum')
            ->postJson("/api/trainer/diet-templates/{$trainerTemplate->id}/assign", [
                'member_ids' => [$assignedMember->id],
            ])
            ->assertCreated();

        $this->actingAs($assignedMember, 'sanctum')
            ->getJson('/api/member/diet-plans')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 15)
            ->assertJsonFragment(['name' => 'Trainer Private Nutrition']);

        $this->actingAs($otherMember, 'sanctum')
            ->getJson('/api/member/diet-plans')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Trainer Private Nutrition']);
    }

    public function test_member_created_products_persist_and_plan_edits_keep_meal_progress(): void
    {
        $this->seed(PermissionSeeder::class);
        [, , , $member] = $this->context();

        $response = $this->actingAs($member, 'sanctum')
            ->postJson('/api/member/diet-plans', [
                'name' => 'Member Complete Plan',
                'goal' => 'Energy',
                'meals' => [[
                    'name' => 'Early shift meal',
                    'items' => [
                        [
                            'name' => 'Poha',
                            'quantity' => '1 bowl',
                            'calories' => 280,
                            'protein_g' => 7,
                            'carbs_g' => 52,
                            'fats_g' => 6,
                        ],
                        [
                            'name' => '',
                            'quantity' => '',
                        ],
                    ],
                ], [
                    'name' => 'Dinner',
                    'items' => [[
                        'name' => 'Khichdi',
                        'quantity' => '1 bowl',
                        'calories' => 360,
                    ]],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.protein_target_g', null)
            ->assertJsonPath('data.meals.0.meal_type', 'early_shift_meal')
            ->assertJsonCount(1, 'data.meals.0.items')
            ->assertJsonPath('data.meals.0.items.0.name', 'Poha');

        $planId = $response->json('data.id');
        $mealId = $response->json('data.meals.0.id');
        $dinnerMealId = $response->json('data.meals.1.id');
        $pohaItemId = $response->json('data.meals.0.items.0.id');
        $khichdiItemId = $response->json('data.meals.1.items.0.id');

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/diet-plans/{$planId}/meals/{$mealId}/log")
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->putJson("/api/member/diet-plans/{$planId}", [
                'name' => 'Member Complete Plan Updated',
                'goal' => 'Energy',
                'status' => 'active',
                'meals' => [[
                    'id' => $dinnerMealId,
                    'name' => 'Dinner',
                    'meal_type' => 'dinner',
                    'items' => [[
                        'id' => $khichdiItemId,
                        'name' => 'Khichdi',
                        'quantity' => '1 bowl',
                        'calories' => 360,
                    ]],
                ], [
                    'id' => $mealId,
                    'name' => 'Breakfast',
                    'meal_type' => 'breakfast',
                    'items' => [
                        [
                            'id' => $pohaItemId,
                            'name' => 'Poha',
                            'quantity' => '1 large bowl',
                            'calories' => 340,
                        ],
                        [
                            'name' => 'Curd',
                            'quantity' => '100g',
                            'calories' => 80,
                        ],
                    ],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.meals.0.id', $dinnerMealId)
            ->assertJsonPath('data.meals.1.id', $mealId)
            ->assertJsonCount(2, 'data.meals.1.items');

        $this->assertDatabaseHas('diet_meal_logs', [
            'diet_plan_meal_id' => $mealId,
            'member_id' => $member->id,
        ]);
        $this->assertSame(
            1,
            DietMealLog::query()
                ->where('diet_plan_meal_id', $mealId)
                ->count(),
        );
    }

    public function test_gym_admin_can_edit_all_assigned_plan_fields_and_products(): void
    {
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        [$gym, $branch, , $member, $owner] = $this->context();
        $plan = DietPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'created_by_user_id' => $owner->id,
            'name' => 'Gym Plan',
            'status' => 'active',
        ]);
        $meal = $plan->meals()->create([
            'name' => 'Lunch',
            'meal_type' => 'lunch',
            'sort_order' => 0,
        ]);
        $meal->items()->create([
            'name' => 'Rice',
            'quantity' => '1 cup',
            'sort_order' => 0,
        ]);

        $routeParameters = [
            'dietPlan' => $plan,
            'gym' => $gym->id,
            'branch' => $branch->id,
        ];

        $this->actingAs($owner)
            ->get(route('web.gym.diet-plans.edit', $routeParameters))
            ->assertOk()
            ->assertSee('Add food/product')
            ->assertSee('Rice');

        $this->actingAs($owner)
            ->put(route('web.gym.diet-plans.update', $routeParameters), [
                'name' => 'Gym Plan Updated',
                'goal' => 'Lean muscle',
                'daily_calorie_target' => 2500,
                'protein_target_g' => 170,
                'status' => 'active',
                'meals' => [[
                    'name' => 'Lunch',
                    'meal_type' => 'lunch',
                    'items' => [[
                        'name' => 'Brown rice',
                        'quantity' => '1.5 cups',
                        'calories' => 320,
                    ]],
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('diet_plans', [
            'id' => $plan->id,
            'name' => 'Gym Plan Updated',
            'goal' => 'Lean muscle',
            'daily_calorie_target' => 2500,
        ]);
        $this->assertDatabaseHas('diet_plan_meal_items', [
            'diet_plan_meal_id' => $meal->id,
            'name' => 'Brown rice',
            'quantity' => '1.5 cups',
        ]);
    }

    private function context(): array
    {
        $owner = User::factory()->create([
            'active_role' => RoleName::GymOwner->value,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);
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

        return [$gym, $branch, $trainer, $member, $owner];
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
