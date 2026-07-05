<?php

namespace Tests\Feature\Trainer;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrainerScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainer_only_sees_assigned_members(): void
    {
        $this->seed(PermissionSeeder::class);

        [$gym, $branch] = $this->makeGymContext();

        $trainer = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);
        $trainer->gyms()->attach($gym->id);
        $trainer->branches()->attach($branch->id);

        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specializations' => ['strength'],
            'experience_years' => 4,
            'certifications' => ['ACE'],
            'languages' => ['English'],
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $assignedMember = User::factory()->create();
        $assignedMember->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $assignedMember->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'fitness_goal' => 'Strength',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $otherTrainer = User::factory()->create();
        $otherTrainer->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $otherTrainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specializations' => ['fat loss'],
            'experience_years' => 2,
            'certifications' => ['NASM'],
            'languages' => ['English'],
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $unassignedMember = User::factory()->create();
        $unassignedMember->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $unassignedMember->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $otherTrainer->id,
            'fitness_goal' => 'Fat loss',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/assigned-members')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.member_id', $assignedMember->id);

        $this->actingAs($trainer, 'sanctum')
            ->getJson("/api/trainer/assigned-members/{$unassignedMember->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.member_id.0', 'You do not have access to this member.');
    }

    public function test_branch_manager_can_inspect_only_branch_trainers(): void
    {
        $this->seed(PermissionSeeder::class);

        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trainer Inspect Gym',
            'slug' => 'trainer-inspect-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_approval_status' => 'approved',
        ]);

        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Inspect A',
            'slug' => 'inspect-a',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Inspect B',
            'slug' => 'inspect-b',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $manager = User::factory()->create([
            'active_role' => RoleName::BranchManager->value,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);
        $manager->gyms()->attach($gym->id);
        $manager->branches()->attach($branchA->id);

        $trainerA = User::factory()->create();
        $trainerA->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $trainerA->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'specializations' => ['strength'],
            'experience_years' => 5,
            'certifications' => ['ACE'],
            'languages' => ['English'],
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $trainerB = User::factory()->create();
        $trainerB->assignRole(RoleName::Trainer->value);
        TrainerProfile::query()->create([
            'user_id' => $trainerB->id,
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'specializations' => ['mobility'],
            'experience_years' => 3,
            'certifications' => ['NSCA'],
            'languages' => ['English'],
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/trainer/profile?trainer_user_id={$trainerA->id}")
            ->assertOk()
            ->assertJsonPath('data.trainer_profile.branch_id', $branchA->id);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/trainer/profile?trainer_user_id={$trainerB->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.trainer_user_id.0', 'You do not have access to this trainer profile.');
    }

    public function test_trainer_can_upload_profile_photo(): void
    {
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');

        [$gym, $branch] = $this->makeGymContext();

        $trainer = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);
        $trainer->gyms()->attach($gym->id);
        $trainer->branches()->attach($branch->id);

        $profile = TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specializations' => ['strength'],
            'experience_years' => 4,
            'certifications' => ['ACE'],
            'languages' => ['English'],
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $response = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/profile/photo', [
                'photo' => UploadedFile::fake()->image('coach-profile.jpg', 720, 720)->size(400),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Trainer profile photo uploaded successfully.');

        $photoUrl = $response->json('data.profile_photo_url');

        $this->assertIsString($photoUrl);
        $this->assertStringStartsWith('http://localhost/storage/trainer-profile-photos/', $photoUrl);

        $storedPath = str_replace('http://localhost/storage/', '', $photoUrl);
        Storage::disk('public')->assertExists($storedPath);

        $this->assertDatabaseHas('trainer_profiles', [
            'id' => $profile->id,
            'profile_photo_url' => $photoUrl,
        ]);
    }

    public function test_trainer_can_upload_and_save_certification_proof(): void
    {
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');

        [$gym, $branch] = $this->makeGymContext();

        $trainer = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);
        $trainer->gyms()->attach($gym->id);
        $trainer->branches()->attach($branch->id);

        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specializations' => ['Strength'],
            'experience_years' => 4,
            'certifications' => [],
            'languages' => ['English'],
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $uploadResponse = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/trainer/profile/certifications/upload', [
                'certificate' => UploadedFile::fake()->image('ace-cpt.jpg', 1200, 900)->size(600),
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Certification proof uploaded successfully.')
            ->assertJsonPath('data.file_name', 'ace-cpt.jpg')
            ->assertJsonPath('data.file_type', 'image');

        $fileUrl = $uploadResponse->json('data.certification_file_url');
        $this->assertIsString($fileUrl);
        $this->assertStringStartsWith('http://localhost/storage/trainer-certifications/', $fileUrl);

        $storedPath = str_replace('http://localhost/storage/', '', $fileUrl);
        Storage::disk('public')->assertExists($storedPath);

        $this->actingAs($trainer, 'sanctum')
            ->putJson('/api/trainer/profile', [
                'experience_years' => 5,
                'certifications' => [
                    [
                        'name' => 'ACE CPT',
                        'issuer' => 'ACE',
                        'issued_year' => 2025,
                        'file_url' => $fileUrl,
                        'file_name' => 'ace-cpt.jpg',
                        'mime_type' => 'image/jpeg',
                        'file_type' => 'image',
                    ],
                ],
                'trainer_onboarding_step' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.trainer_profile.certifications.0.name', 'ACE CPT')
            ->assertJsonPath('data.trainer_profile.certifications.0.file_name', 'ace-cpt.jpg');
    }

    /**
     * @return array{0: Gym, 1: Branch}
     */
    private function makeGymContext(): array
    {
        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trainer Scope Gym',
            'slug' => 'trainer-scope-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_approval_status' => 'approved',
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Trainer Scope Branch',
            'slug' => 'trainer-scope-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$gym, $branch];
    }
}
