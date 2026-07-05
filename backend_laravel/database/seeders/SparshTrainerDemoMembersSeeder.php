<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SparshTrainerDemoMembersSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now();
        $today = $now->toDateString();
        $gymId = 1;
        $branchId = 1;
        $trainerId = (int) DB::table('users')
            ->where('email', 'sparshagarwal141998@gmail.com')
            ->value('id');

        if ($trainerId === 0) {
            $this->command?->warn('Sparsh trainer user was not found.');

            return;
        }

        $planId = (int) DB::table('membership_plans')
            ->where('gym_id', $gymId)
            ->where('name', 'Coach Assisted')
            ->value('id');

        if ($planId === 0) {
            $planId = (int) DB::table('membership_plans')
                ->where('gym_id', $gymId)
                ->orderBy('id')
                ->value('id');
        }

        if ($planId === 0) {
            $this->command?->warn('No membership plan found for gym 1.');

            return;
        }

        $plan = DB::table('membership_plans')->where('id', $planId)->first();
        $members = [
            [
                'name' => 'Riya Sharma',
                'email' => 'riya.sharma.demo@gymatlas.local',
                'goal' => 'Fat loss and conditioning',
                'gender' => 'female',
                'height' => 164,
                'weight' => 68.4,
                'experience' => 'Beginner',
                'status' => 'active',
                'payment_status' => 'paid',
                'paid' => 4000,
                'due' => 0,
                'note' => 'Review squat depth and keep cardio finisher low impact this week.',
                'follow_up' => $now->addDays(2)->toDateString(),
                'attendance_days_ago' => 0,
                'body_fat' => 29.5,
            ],
            [
                'name' => 'Kabir Mehta',
                'email' => 'kabir.mehta.demo@gymatlas.local',
                'goal' => 'Muscle gain',
                'gender' => 'male',
                'height' => 178,
                'weight' => 76.2,
                'experience' => 'Intermediate',
                'status' => 'active',
                'payment_status' => 'partial',
                'paid' => 2500,
                'due' => 1500,
                'note' => 'Needs new upper/lower split after current plan review.',
                'follow_up' => $now->addDay()->toDateString(),
                'attendance_days_ago' => 1,
                'body_fat' => 18.2,
            ],
            [
                'name' => 'Meera Iyer',
                'email' => 'meera.iyer.demo@gymatlas.local',
                'goal' => 'Strength and mobility',
                'gender' => 'female',
                'height' => 160,
                'weight' => 59.8,
                'experience' => 'Intermediate',
                'status' => 'active',
                'payment_status' => 'paid',
                'paid' => 4000,
                'due' => 0,
                'note' => 'Shoulder mobility is improving; progress overhead work carefully.',
                'follow_up' => $now->addDays(3)->toDateString(),
                'attendance_days_ago' => 2,
                'body_fat' => 24.7,
            ],
            [
                'name' => 'Arjun Rao',
                'email' => 'arjun.rao.demo@gymatlas.local',
                'goal' => 'Sports conditioning',
                'gender' => 'male',
                'height' => 182,
                'weight' => 83.1,
                'experience' => 'Advanced',
                'status' => 'active',
                'payment_status' => 'unpaid',
                'paid' => 0,
                'due' => 4000,
                'note' => 'Missed two conditioning sessions; check recovery and schedule.',
                'follow_up' => $today,
                'attendance_days_ago' => 5,
                'body_fat' => 16.8,
            ],
        ];

        DB::transaction(function () use ($members, $trainerId, $gymId, $branchId, $planId, $plan, $now, $today): void {
            foreach ($members as $index => $member) {
                $memberId = DB::table('users')->where('email', $member['email'])->value('id');

                if (! $memberId) {
                    $memberId = DB::table('users')->insertGetId([
                        'name' => $member['name'],
                        'email' => $member['email'],
                        'auth_provider' => 'gym_invite',
                        'active_role' => 'member',
                        'is_active' => 1,
                        'member_onboarding_completed' => 1,
                        'member_onboarding_step' => 8,
                        'trainer_onboarding_completed' => 0,
                        'trainer_onboarding_step' => 1,
                        'email_verified_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('users')->where('id', $memberId)->update([
                        'name' => $member['name'],
                        'active_role' => 'member',
                        'is_active' => 1,
                        'member_onboarding_completed' => 1,
                        'member_onboarding_step' => 8,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => 6,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $memberId,
                ], []);

                DB::table('gym_user')->updateOrInsert([
                    'gym_id' => $gymId,
                    'user_id' => $memberId,
                ], [
                    'branch_id' => $branchId,
                    'role_name' => 'member',
                    'status' => 'active',
                    'is_primary' => 0,
                    'permissions' => null,
                    'custom_permissions' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('member_profiles')->updateOrInsert([
                    'user_id' => $memberId,
                    'gym_id' => $gymId,
                ], [
                    'branch_id' => $branchId,
                    'assigned_trainer_user_id' => $trainerId,
                    'assigned_trainer_id' => $trainerId,
                    'fitness_goal' => $member['goal'],
                    'height_cm' => $member['height'],
                    'weight_kg' => $member['weight'],
                    'experience_level' => $member['experience'],
                    'gender' => $member['gender'],
                    'medical_notes' => 'No major medical restrictions reported.',
                    'injury_notes' => $index === 2 ? 'Mild shoulder tightness.' : null,
                    'membership_status' => $member['status'],
                    'status' => $member['status'],
                    'membership_expires_on' => $now->addDays(28 + ($index * 4))->toDateString(),
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $membershipId = DB::table('member_memberships')
                    ->where('gym_id', $gymId)
                    ->where('member_id', $memberId)
                    ->value('id');

                $membershipPayload = [
                    'gym_id' => $gymId,
                    'branch_id' => $branchId,
                    'member_id' => $memberId,
                    'membership_plan_id' => $planId,
                    'start_date' => $now->subDays(8 + $index)->toDateString(),
                    'expiry_date' => $now->addDays(22 + ($index * 3))->toDateString(),
                    'status' => 'active',
                    'default_plan_price' => $plan->plan_price,
                    'default_joining_fee' => $plan->joining_fee,
                    'final_payable_amount' => $plan->plan_price,
                    'amount_paid' => $member['paid'],
                    'due_amount' => $member['due'],
                    'due_date' => $member['due'] > 0 ? $now->addDays(3)->toDateString() : null,
                    'payment_status' => $member['payment_status'],
                    'updated_at' => $now,
                ];

                if ($membershipId) {
                    DB::table('member_memberships')->where('id', $membershipId)->update($membershipPayload);
                } else {
                    DB::table('member_memberships')->insert($membershipPayload + ['created_at' => $now]);
                }

                DB::table('trainer_member_notes')->updateOrInsert([
                    'trainer_id' => $trainerId,
                    'member_id' => $memberId,
                    'note' => $member['note'],
                ], [
                    'visibility' => 'private_to_trainer',
                    'follow_up_date' => $member['follow_up'],
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('weight_logs')->updateOrInsert([
                    'member_id' => $memberId,
                    'log_date' => $today,
                ], [
                    'gym_id' => $gymId,
                    'branch_id' => $branchId,
                    'logged_by_user_id' => $trainerId,
                    'weight_kg' => $member['weight'],
                    'notes' => 'Trainer dashboard demo check-in.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('body_measurements')->updateOrInsert([
                    'member_id' => $memberId,
                    'measured_on' => $today,
                ], [
                    'gym_id' => $gymId,
                    'branch_id' => $branchId,
                    'logged_by_user_id' => $trainerId,
                    'chest_cm' => 88 + ($index * 3),
                    'waist_cm' => 74 + ($index * 2),
                    'hips_cm' => 92 + ($index * 2),
                    'arm_cm' => 29 + $index,
                    'thigh_cm' => 51 + $index,
                    'calf_cm' => 35 + $index,
                    'body_fat_percentage' => $member['body_fat'],
                    'notes' => 'Demo baseline measurement for trainer review.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('attendance_logs')->updateOrInsert([
                    'gym_id' => $gymId,
                    'branch_id' => $branchId,
                    'member_id' => $memberId,
                    'checked_in_at' => $now->subDays($member['attendance_days_ago'])->setTime(7 + $index, 30)->toDateTimeString(),
                ], [
                    'checked_in_by' => $trainerId,
                    'check_in_method' => 'manual',
                    'source_device' => 'demo-seeder',
                    'notes' => 'Demo trainer dashboard attendance.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('notifications')->updateOrInsert([
                'user_id' => $trainerId,
                'type' => 'new_member_assigned',
                'title' => 'New members assigned',
            ], [
                'gym_id' => $gymId,
                'branch_id' => $branchId,
                'body' => 'Four demo members have been assigned to your trainer roster.',
                'message' => 'Four demo members have been assigned to your trainer roster.',
                'data' => json_encode(['member_count' => count($members), 'gym_id' => $gymId]),
                'read_at' => null,
                'created_by_user_id' => null,
                'scheduled_for' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $this->command?->info('Assigned demo members to Sparsh Agarwal for gym 1.');
    }
}
