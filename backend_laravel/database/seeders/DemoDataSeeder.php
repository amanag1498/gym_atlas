<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Branch;
use App\Models\City;
use App\Models\Facility;
use App\Models\Gym;
use App\Models\GymPhoto;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Payment;
use App\Models\PlatformBanner;
use App\Models\ScheduledReminder;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use App\Models\User;
use App\Models\Exercise;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Models\WeightLog;
use App\Models\BodyMeasurement;
use App\Models\ProgressPhoto;
use App\Models\PersonalRecord;
use App\Services\Authorization\ActiveRoleManager;
use App\Services\Billing\MembershipPricingService;
use App\Services\Billing\PaymentService;
use App\Enums\AttendanceCheckInMethod;
use App\Enums\NotificationType;
use App\Services\Notification\ReminderService;
use App\Models\AttendanceLog;
use App\Services\Workout\WorkoutPlanService;
use App\Services\Workout\WorkoutSessionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $activeRoleManager = app(ActiveRoleManager::class);
        $pricingService = app(MembershipPricingService::class);
        $paymentService = app(PaymentService::class);
        $reminderService = app(ReminderService::class);
        $workoutPlanService = app(WorkoutPlanService::class);
        $workoutSessionService = app(WorkoutSessionService::class);

        $owner = $this->makeUser('Owner One', (string) config('gym.demo_gym_owner_email'), 'seed-owner-1', [RoleName::GymOwner], $activeRoleManager);
        $manager = $this->makeUser('Branch Manager', 'manager1@example.com', 'seed-manager-1', [RoleName::BranchManager], $activeRoleManager);
        $staff = $this->makeUser('Staff User', 'staff1@example.com', 'seed-staff-1', [RoleName::GymStaff], $activeRoleManager);
        $trainer = $this->makeUser('Trainer User', 'trainer1@example.com', 'seed-trainer-1', [RoleName::Trainer], $activeRoleManager);
        $member = $this->makeUser('Member User', 'member1@example.com', 'seed-member-1', [RoleName::Member], $activeRoleManager);
        $ownerTrainer = $this->makeUser('Hybrid Coach', 'hybrid1@example.com', 'seed-hybrid-1', [RoleName::GymOwner, RoleName::Trainer], $activeRoleManager);

        $bengaluru = City::query()->updateOrCreate(
            ['name' => 'Bengaluru', 'state' => 'Karnataka', 'country' => 'India'],
            ['is_active' => true],
        );

        $hsrFacility = Facility::query()->updateOrCreate(
            ['slug' => 'strength-zone'],
            ['name' => 'Strength Zone', 'icon' => 'dumbbell', 'description' => 'Free weights and racks', 'status' => 'active', 'is_active' => true],
        );
        $cardioFacility = Facility::query()->updateOrCreate(
            ['slug' => 'cardio-deck'],
            ['name' => 'Cardio Deck', 'icon' => 'activity', 'description' => 'Treadmills and bikes', 'status' => 'active', 'is_active' => true],
        );
        $steamFacility = Facility::query()->updateOrCreate(
            ['slug' => 'steam-room'],
            ['name' => 'Steam Room', 'icon' => 'flame', 'description' => 'Recovery and sauna area', 'status' => 'active', 'is_active' => true],
        );
        $functionalFacility = Facility::query()->updateOrCreate(
            ['slug' => 'functional-training'],
            ['name' => 'Functional Training', 'icon' => 'activity', 'description' => 'Turf, sleds, battle ropes, and conditioning rigs', 'status' => 'active', 'is_active' => true],
        );
        $olympicFacility = Facility::query()->updateOrCreate(
            ['slug' => 'olympic-lifting'],
            ['name' => 'Olympic Lifting', 'icon' => 'barbell', 'description' => 'Platforms, bumper plates, and competition bars', 'status' => 'active', 'is_active' => true],
        );
        $yogaFacility = Facility::query()->updateOrCreate(
            ['slug' => 'yoga-studio'],
            ['name' => 'Yoga Studio', 'icon' => 'spa', 'description' => 'Mobility, yoga, and breathwork studio', 'status' => 'active', 'is_active' => true],
        );
        $recoveryFacility = Facility::query()->updateOrCreate(
            ['slug' => 'recovery-lounge'],
            ['name' => 'Recovery Lounge', 'icon' => 'heart', 'description' => 'Stretching, compression, and recovery space', 'status' => 'active', 'is_active' => true],
        );
        $groupClassFacility = Facility::query()->updateOrCreate(
            ['slug' => 'group-classes'],
            ['name' => 'Group Classes', 'icon' => 'users', 'description' => 'Coach-led strength, HIIT, and conditioning classes', 'status' => 'active', 'is_active' => true],
        );

        $gym = Gym::query()->updateOrCreate(
            ['slug' => 'iron-core-fitness'],
            [
                'owner_user_id' => $owner->id,
                'city_id' => $bengaluru->id,
                'name' => 'Iron Core Fitness',
                'description' => 'Flagship performance gym with strength and conditioning focus.',
                'logo' => 'demo/gyms/iron-core/logo.png',
                'logo_url' => 'demo/gyms/iron-core/logo.png',
                'cover_image' => 'demo/gyms/iron-core/cover.png',
                'cover_image_url' => 'demo/gyms/iron-core/cover.png',
                'timezone' => 'Asia/Kolkata',
                'address' => '27, 14th Main Road',
                'address_line' => '27, 14th Main Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'country' => 'India',
                'pincode' => '560102',
                'latitude' => 12.9116221,
                'longitude' => 77.6476210,
                'opening_time' => '05:30',
                'closing_time' => '23:00',
                'timings' => [
                    'monday_to_saturday' => ['open' => '05:30', 'close' => '23:00'],
                    'sunday' => ['open' => '07:00', 'close' => '20:00'],
                ],
                'weekly_off' => [],
                'status' => 'active',
                'is_active' => true,
                'approval_status' => 'approved',
                'approved_at' => now(),
                'is_verified' => true,
                'verified_at' => now(),
                'public_listing_enabled' => true,
                'show_pricing' => true,
                'public_listing_approval_status' => 'approved',
                'public_listing_approved_at' => now(),
                'pricing_visible' => true,
                'trial_available' => true,
                'contact_visible' => true,
                'women_friendly' => true,
                'women_only' => false,
            ],
        );

        $branchA = Branch::query()->updateOrCreate(
            ['slug' => 'iron-core-hsr'],
            [
                'gym_id' => $gym->id,
                'city_id' => $bengaluru->id,
                'name' => 'HSR Branch',
                'timezone' => 'Asia/Kolkata',
                'address' => 'HSR Layout Sector 2',
                'address_line' => 'HSR Layout Sector 2',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'country' => 'India',
                'pincode' => '560102',
                'opening_time' => '05:30',
                'closing_time' => '23:00',
                'timings' => [
                    'weekdays' => ['open' => '05:30', 'close' => '23:00'],
                    'weekend' => ['open' => '07:00', 'close' => '20:00'],
                ],
                'weekly_off' => [],
                'is_active' => true,
                'status' => 'active',
            ],
        );

        $branchB = Branch::query()->updateOrCreate(
            ['slug' => 'iron-core-indiranagar'],
            [
                'gym_id' => $gym->id,
                'city_id' => $bengaluru->id,
                'name' => 'Indiranagar Branch',
                'timezone' => 'Asia/Kolkata',
                'address' => '100 Feet Road',
                'address_line' => '100 Feet Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'country' => 'India',
                'pincode' => '560038',
                'opening_time' => '06:00',
                'closing_time' => '22:00',
                'timings' => [
                    'weekdays' => ['open' => '06:00', 'close' => '22:00'],
                    'weekend' => ['open' => '07:00', 'close' => '19:00'],
                ],
                'weekly_off' => ['monday'],
                'is_active' => true,
                'status' => 'active',
            ],
        );

        $gym->facilities()->sync([$hsrFacility->id, $cardioFacility->id, $steamFacility->id]);
        $branchA->facilities()->sync([$hsrFacility->id, $cardioFacility->id]);
        $branchB->facilities()->sync([$cardioFacility->id, $steamFacility->id]);

        $gym->users()->syncWithoutDetaching([
            $owner->id => ['branch_id' => $branchA->id, 'role_name' => RoleName::GymOwner->value, 'status' => 'active', 'is_primary' => true],
            $manager->id => ['branch_id' => $branchA->id, 'role_name' => RoleName::BranchManager->value, 'status' => 'active', 'is_primary' => false],
            $staff->id => ['branch_id' => $branchA->id, 'role_name' => RoleName::GymStaff->value, 'permissions' => json_encode(['view_billing', 'manage_attendance']), 'status' => 'active', 'is_primary' => false],
            $trainer->id => ['branch_id' => $branchA->id, 'role_name' => RoleName::Trainer->value, 'status' => 'active', 'is_primary' => false],
            $member->id => ['branch_id' => $branchA->id, 'role_name' => RoleName::Member->value, 'status' => 'active', 'is_primary' => false],
            $ownerTrainer->id => ['branch_id' => $branchB->id, 'role_name' => RoleName::GymOwner->value, 'status' => 'active', 'is_primary' => false],
        ]);

        $branchA->users()->syncWithoutDetaching([
            $manager->id => ['is_primary' => true],
            $staff->id => ['is_primary' => false],
            $trainer->id => ['is_primary' => false],
            $member->id => ['is_primary' => false],
            $ownerTrainer->id => ['is_primary' => false],
        ]);

        $branchB->users()->syncWithoutDetaching([
            $owner->id => ['is_primary' => false],
            $trainer->id => ['is_primary' => false],
        ]);

        TrainerProfile::query()->updateOrCreate(
            ['user_id' => $trainer->id],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'profile_photo_url' => $trainer->avatar,
                'bio' => 'Strength and weight loss coach for beginner and intermediate members.',
                'specialization' => 'weight loss',
                'specializations' => ['weight loss', 'strength'],
                'experience_years' => 5,
                'certifications' => ['ACE CPT'],
                'status' => 'active',
                'languages' => ['English', 'Hindi'],
                'is_active' => true,
                'verification_status' => 'pending',
            ],
        );

        TrainerProfile::query()->updateOrCreate(
            ['user_id' => $ownerTrainer->id],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchB->id,
                'profile_photo_url' => $ownerTrainer->avatar,
                'bio' => 'Hybrid owner-trainer focused on functional conditioning.',
                'specialization' => 'cross training',
                'specializations' => ['cross training'],
                'experience_years' => 7,
                'certifications' => ['NSCA CSCS'],
                'status' => 'active',
                'languages' => ['English', 'Kannada'],
                'is_active' => true,
                'verification_status' => 'pending',
            ],
        );

        MemberProfile::query()->updateOrCreate(
            ['user_id' => $member->id],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'assigned_trainer_user_id' => $trainer->id,
                'assigned_trainer_id' => $trainer->id,
                'fitness_goal' => 'Fat loss',
                'height_cm' => 172,
                'weight_kg' => 81,
                'experience_level' => 'beginner',
                'medical_notes' => 'Mild knee pain history',
                'emergency_contact_name' => 'Parent Contact',
                'emergency_contact_phone' => '+91-9999999999',
                'status' => 'active',
                'membership_status' => 'active',
                'membership_expires_on' => now()->addMonths(2)->toDateString(),
                'is_active' => true,
            ],
        );

        foreach ([
            ['gym_id' => $gym->id, 'branch_id' => null, 'image_path' => 'demo/gyms/iron-core/logo.png', 'type' => 'logo', 'sort_order' => 0],
            ['gym_id' => $gym->id, 'branch_id' => null, 'image_path' => 'demo/gyms/iron-core/cover.png', 'type' => 'cover', 'sort_order' => 0],
            ['gym_id' => $gym->id, 'branch_id' => $branchA->id, 'image_path' => 'demo/gyms/iron-core/hsr-1.png', 'type' => 'gallery', 'sort_order' => 1],
            ['gym_id' => $gym->id, 'branch_id' => $branchB->id, 'image_path' => 'demo/gyms/iron-core/indiranagar-1.png', 'type' => 'gallery', 'sort_order' => 1],
        ] as $photo) {
            GymPhoto::query()->updateOrCreate(
                [
                    'gym_id' => $photo['gym_id'],
                    'branch_id' => $photo['branch_id'],
                    'image_path' => $photo['image_path'],
                    'type' => $photo['type'],
                ],
                ['sort_order' => $photo['sort_order']],
            );
        }

        $standardPlan = MembershipPlan::query()->updateOrCreate(
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'name' => 'Standard Monthly',
            ],
            [
                'duration_days' => 30,
                'plan_price' => 2500,
                'joining_fee' => 500,
                'pt_included' => false,
                'description' => 'Monthly access membership',
                'status' => 'active',
                'created_by_user_id' => $owner->id,
            ],
        );

        $coachPlan = MembershipPlan::query()->updateOrCreate(
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'name' => 'Coach Assisted',
            ],
            [
                'duration_days' => 30,
                'plan_price' => 4000,
                'joining_fee' => 500,
                'pt_included' => true,
                'description' => 'Includes PT support',
                'status' => 'active',
                'created_by_user_id' => $owner->id,
            ],
        );

        $this->seedDiscoveryGyms($owner, $bengaluru, [
            'strength' => $hsrFacility,
            'cardio' => $cardioFacility,
            'steam' => $steamFacility,
            'functional' => $functionalFacility,
            'olympic' => $olympicFacility,
            'yoga' => $yogaFacility,
            'recovery' => $recoveryFacility,
            'group' => $groupClassFacility,
        ]);

        $pricing = $pricingService->buildMembershipPayload($coachPlan, [
            'custom_fee_enabled' => true,
            'custom_fee_amount' => 3500,
            'discount_type' => 'fixed',
            'discount_amount' => 250,
            'custom_joining_fee' => 300,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 500,
            'amount_paid' => 0,
            'custom_fee_reason' => 'Launch offer for demo member',
        ]);

        $membership = MemberMembership::query()->updateOrCreate(
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'member_id' => $member->id,
                'membership_plan_id' => $coachPlan->id,
                'start_date' => now()->subDays(5)->toDateString(),
            ],
            [
                'expiry_date' => now()->addDays(25)->toDateString(),
                'status' => 'active',
                'due_date' => now()->addDays(3)->toDateString(),
                'approved_by_admin_id' => $manager->id,
                ...$pricing,
            ],
        );

        MemberMembership::query()->updateOrCreate(
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'member_id' => $member->id,
                'membership_plan_id' => $standardPlan->id,
                'start_date' => now()->subDays(45)->toDateString(),
            ],
            [
                'expiry_date' => now()->subDays(15)->toDateString(),
                'status' => 'expired',
                'default_plan_price' => 2500,
                'default_joining_fee' => 500,
                'custom_fee_enabled' => false,
                'custom_fee_amount' => 2500,
                'discount_type' => 'none',
                'discount_amount' => 0,
                'custom_joining_fee' => 500,
                'joining_fee_waived' => false,
                'partial_month_fee' => 0,
                'pt_custom_fee' => 0,
                'final_payable_amount' => 3000,
                'amount_paid' => 3000,
                'due_amount' => 0,
                'due_date' => now()->subDays(30)->toDateString(),
                'payment_status' => 'paid',
                'approved_by_admin_id' => $owner->id,
            ],
        );

        if (! Payment::query()->where('member_membership_id', $membership->id)->exists()) {
            $paymentService->recordPayment($membership->fresh(['payments', 'membershipPlan']), $manager, [
                'amount' => 1500,
                'payment_mode' => 'upi',
                'notes' => 'Initial partial payment',
                'paid_at' => now()->subDays(2),
            ]);
        }

        AttendanceLog::query()->updateOrCreate(
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'member_id' => $member->id,
                'checked_in_at' => now()->subDay()->startOfDay()->addHours(7),
            ],
            [
                'checked_in_by' => $staff->id,
                'check_in_method' => AttendanceCheckInMethod::Manual->value,
                'notes' => 'Morning training session',
            ],
        );

        $announcement = Announcement::query()->updateOrCreate(
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'title' => 'Summer Challenge Kickoff',
            ],
            [
                'created_by_user_id' => $manager->id,
                'created_by' => $manager->id,
                'audience_type' => 'gym_wide',
                'message' => 'Join the summer challenge and win free PT sessions.',
                'status' => 'sent',
                'is_platform_wide' => false,
                'send_at' => now(),
                'metadata' => ['offer_placeholder' => true],
            ],
        );

        $memberAnnouncementNotification = Notification::query()->updateOrCreate(
            [
                'user_id' => $member->id,
                'type' => NotificationType::GymAnnouncement->value,
                'title' => 'Summer Challenge Kickoff',
            ],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'announcement_id' => $announcement->id,
                'member_membership_id' => $membership->id,
                'message' => 'Join the summer challenge and win free PT sessions.',
                'body' => 'Join the summer challenge and win free PT sessions.',
                'data' => ['member_id' => $member->id],
                'created_by_user_id' => $manager->id,
                'scheduled_for' => now(),
            ],
        );

        AnnouncementRecipient::query()->updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $member->id,
            ],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'notification_id' => $memberAnnouncementNotification->id,
            ],
        );

        Notification::query()->updateOrCreate(
            [
                'user_id' => $trainer->id,
                'type' => NotificationType::TrainerAssignment->value,
                'title' => 'New member assigned',
            ],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'member_membership_id' => $membership->id,
                'message' => 'A new member has been assigned to your training roster.',
                'body' => 'A new member has been assigned to your training roster.',
                'data' => ['member_id' => $member->id],
                'created_by_user_id' => $manager->id,
            ],
        );

        NotificationPreference::query()->updateOrCreate(
            [
                'user_id' => $member->id,
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'notification_type' => NotificationType::AttendanceInactivity->value,
            ],
            [
                'is_enabled' => true,
            ],
        );

        TrialRequest::query()->updateOrCreate(
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'email' => 'trial.lead@example.com',
            ],
            [
                'member_id' => $member->id,
                'name' => 'Trial Lead',
                'phone' => '+91-8888888888',
                'preferred_date' => now()->addDays(2)->toDateString(),
                'preferred_time' => '18:00:00',
                'status' => 'pending',
                'assigned_trainer_id' => $trainer->id,
                'notes' => 'Interested in strength onboarding.',
            ],
        );

        $reminderService->syncMembershipReminders($membership->fresh('membershipPlan'));

        ScheduledReminder::query()->firstOrCreate(
            [
                'user_id' => $member->id,
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'member_membership_id' => $membership->id,
                'type' => 'workout_reminder',
            ],
            [
                'title' => 'Workout Reminder',
                'body' => 'Placeholder workout reminder.',
                'payload' => ['placeholder' => true],
                'scheduled_for' => now()->addDay(),
                'status' => 'pending',
            ],
        );

        $squat = Exercise::query()->updateOrCreate(
            ['name' => 'Barbell Back Squat', 'is_global' => true],
            [
                'muscle_group' => 'legs',
                'secondary_muscles' => ['glutes', 'core'],
                'equipment' => 'barbell',
                'difficulty' => 'intermediate',
                'instructions' => 'Brace core and drive through full foot.',
                'status' => 'approved',
                'is_active' => true,
                'created_by_user_id' => $owner->id,
            ],
        );

        $row = Exercise::query()->updateOrCreate(
            ['name' => 'Seated Cable Row', 'gym_id' => $gym->id],
            [
                'branch_id' => $branchA->id,
                'created_by_user_id' => $trainer->id,
                'muscle_group' => 'back',
                'secondary_muscles' => ['biceps'],
                'equipment' => 'cable machine',
                'difficulty' => 'beginner',
                'instructions' => 'Pull elbows back without shrugging shoulders.',
                'is_global' => false,
                'status' => 'pending',
                'is_active' => true,
            ],
        );

        $plans = $workoutPlanService->createPlans($trainer, [
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'member_ids' => [$member->id],
            'name' => 'Strength Base Phase',
            'goal' => 'Strength and muscle gain',
            'difficulty' => 'beginner',
            'duration_weeks' => 4,
            'weekly_schedule' => ['monday', 'wednesday', 'friday'],
            'notes' => 'Demo starter plan.',
            'status' => 'active',
            'starts_on' => now()->subDays(3)->toDateString(),
            'ends_on' => now()->addWeeks(4)->toDateString(),
            'days' => [
                [
                    'day_number' => 1,
                    'label' => 'Day 1',
                    'focus' => 'Lower body',
                    'exercises' => [
                        [
                            'exercise_id' => $squat->id,
                            'sort_order' => 1,
                            'sets' => 4,
                            'reps' => '5',
                            'target_weight' => 60,
                            'rest_seconds' => 120,
                        ],
                    ],
                ],
                [
                    'day_number' => 2,
                    'label' => 'Day 2',
                    'focus' => 'Upper pull',
                    'exercises' => [
                        [
                            'exercise_id' => $row->id,
                            'sort_order' => 1,
                            'sets' => 3,
                            'reps' => '10',
                            'target_weight' => 35,
                            'rest_seconds' => 90,
                        ],
                    ],
                ],
            ],
        ]);

        $workoutPlan = $plans->first();

        $session = $workoutSessionService->startSession($member, [
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'workout_plan_id' => $workoutPlan->id,
            'session_date' => now()->subDay()->toDateString(),
            'allow_duplicate_active_session' => true,
            'notes' => 'Demo completed workout.',
        ]);

        $workoutSessionService->completeSession($session, [
            'notes' => 'Solid effort on all sets.',
            'exercises' => $session->fresh('exercises')->exercises->map(fn ($exercise) => [
                'id' => $exercise->id,
                'exercise_id' => $exercise->exercise_id,
                'sets' => [
                    ['set_number' => 1, 'reps' => 5, 'weight' => 60],
                    ['set_number' => 2, 'reps' => 5, 'weight' => 60],
                    ['set_number' => 3, 'reps' => 5, 'weight' => 62.5],
                ],
            ])->values()->all(),
        ]);

        WeightLog::query()->updateOrCreate(
            [
                'member_id' => $member->id,
                'log_date' => now()->subDays(2)->toDateString(),
            ],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'logged_by_user_id' => $member->id,
                'weight_kg' => 80.4,
                'notes' => 'Morning fasted weight',
            ],
        );

        BodyMeasurement::query()->updateOrCreate(
            [
                'member_id' => $member->id,
                'measured_on' => now()->subDays(2)->toDateString(),
            ],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'logged_by_user_id' => $member->id,
                'chest_cm' => 101,
                'waist_cm' => 88,
                'hips_cm' => 96,
                'body_fat_percentage' => 22.5,
                'notes' => 'Initial progress baseline',
            ],
        );

        ProgressPhoto::query()->updateOrCreate(
            [
                'member_id' => $member->id,
                'photo_url' => 'https://example.com/progress/member-front.jpg',
            ],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'uploaded_by_user_id' => $member->id,
                'photo_type' => 'front',
                'album_key' => 'week-1',
                'captured_on' => now()->subDays(2)->toDateString(),
                'notes' => 'Week 1 front photo',
            ],
        );

        PersonalRecord::query()->firstOrCreate(
            [
                'member_id' => $member->id,
                'exercise_id' => $squat->id,
            ],
            [
                'gym_id' => $gym->id,
                'branch_id' => $branchA->id,
                'workout_session_id' => WorkoutSession::query()->where('member_id', $member->id)->latest('id')->value('id'),
                'best_weight' => 62.5,
                'best_reps' => 5,
                'best_volume' => 912.5,
                'achieved_at' => now()->subDay(),
            ],
        );

        PlatformBanner::query()->updateOrCreate(
            ['title' => 'Summer Trial Pass'],
            [
                'image_url' => 'https://example.com/banners/summer-trial.png',
                'link_url' => 'https://example.com/gyms/iron-core-fitness',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }

    /**
     * @param  list<RoleName>  $roles
     */
    private function makeUser(
        string $name,
        string $email,
        string $googleId,
        array $roles,
        ActiveRoleManager $activeRoleManager,
    ): User {
        $user = User::query()
            ->where('email', $email)
            ->orWhere('google_id', $googleId)
            ->first();

        if (! $user) {
            $user = new User();
        }

        $user->fill([
            'email' => $email,
            'name' => $name,
            'google_id' => $googleId,
            'avatar' => 'https://example.com/avatar.png',
            'auth_provider' => 'google',
            'email_verified_at' => now(),
            'last_login_at' => now(),
            'password' => Hash::make((string) config('gym.demo_user_password')),
        ]);

        $user->save();

        $user->syncRoles(array_map(
            static fn (RoleName $role): string => $role->value,
            $roles,
        ));
        $activeRoleManager->ensureValidActiveRole($user);

        return $user;
    }

    /**
     * @param  array<string, Facility>  $facilities
     */
    private function seedDiscoveryGyms(User $owner, City $city, array $facilities): void
    {
        $gyms = [
            [
                'slug' => 'pulse-lab-koramangala',
                'branch_slug' => 'pulse-lab-koramangala-main',
                'name' => 'Pulse Lab Koramangala',
                'description' => 'High-energy training club near Koramangala with strength zones, turf work, and coach-led conditioning.',
                'area' => 'Koramangala 5th Block',
                'pincode' => '560095',
                'latitude' => 12.9352,
                'longitude' => 77.6245,
                'open' => '05:30',
                'close' => '23:00',
                'featured' => true,
                'promoted' => true,
                'women_friendly' => true,
                'facilities' => ['strength', 'cardio', 'functional', 'group'],
                'plans' => [
                    ['name' => 'Monthly Access', 'price' => 2800, 'joining_fee' => 500, 'duration' => 30, 'pt' => false],
                    ['name' => 'Coach Plus', 'price' => 5200, 'joining_fee' => 750, 'duration' => 30, 'pt' => true],
                ],
            ],
            [
                'slug' => 'northstar-strength-indiranagar',
                'branch_slug' => 'northstar-strength-indiranagar-main',
                'name' => 'Northstar Strength Indiranagar',
                'description' => 'Premium strength gym on the Indiranagar side with platforms, racks, recovery, and structured coaching.',
                'area' => 'Indiranagar 100 Feet Road',
                'pincode' => '560038',
                'latitude' => 12.9719,
                'longitude' => 77.6412,
                'open' => '06:00',
                'close' => '22:30',
                'featured' => true,
                'promoted' => false,
                'women_friendly' => true,
                'facilities' => ['strength', 'olympic', 'recovery', 'steam'],
                'plans' => [
                    ['name' => 'Strength Monthly', 'price' => 3500, 'joining_fee' => 750, 'duration' => 30, 'pt' => false],
                    ['name' => 'Performance Coaching', 'price' => 6500, 'joining_fee' => 1000, 'duration' => 30, 'pt' => true],
                ],
            ],
            [
                'slug' => 'fit-yard-whitefield',
                'branch_slug' => 'fit-yard-whitefield-main',
                'name' => 'Fit Yard Whitefield',
                'description' => 'Neighbourhood fitness floor in Whitefield with clear pricing, wide cardio lanes, and beginner-friendly programs.',
                'area' => 'Whitefield Main Road',
                'pincode' => '560066',
                'latitude' => 12.9698,
                'longitude' => 77.7500,
                'open' => '05:00',
                'close' => '22:00',
                'featured' => false,
                'promoted' => true,
                'women_friendly' => true,
                'facilities' => ['strength', 'cardio', 'group'],
                'plans' => [
                    ['name' => 'Smart Monthly', 'price' => 2400, 'joining_fee' => 400, 'duration' => 30, 'pt' => false],
                    ['name' => 'Transformation Pack', 'price' => 4500, 'joining_fee' => 600, 'duration' => 30, 'pt' => true],
                ],
            ],
            [
                'slug' => 'lift-house-jp-nagar',
                'branch_slug' => 'lift-house-jp-nagar-main',
                'name' => 'Lift House JP Nagar',
                'description' => 'Compact lifting-first club in JP Nagar with smart programming, mobility sessions, and friendly floor support.',
                'area' => 'JP Nagar 7th Phase',
                'pincode' => '560078',
                'latitude' => 12.9063,
                'longitude' => 77.5857,
                'open' => '05:30',
                'close' => '22:00',
                'featured' => false,
                'promoted' => false,
                'women_friendly' => true,
                'facilities' => ['strength', 'cardio', 'yoga'],
                'plans' => [
                    ['name' => 'Lift Monthly', 'price' => 2600, 'joining_fee' => 500, 'duration' => 30, 'pt' => false],
                    ['name' => 'Mobility Plus', 'price' => 5000, 'joining_fee' => 750, 'duration' => 30, 'pt' => true],
                ],
            ],
            [
                'slug' => 'aura-wellness-studio-marathahalli',
                'branch_slug' => 'aura-wellness-studio-marathahalli-main',
                'name' => 'Aura Wellness Studio Marathahalli',
                'description' => 'Wellness-forward fitness studio with strength basics, yoga, recovery support, and calm light-filled spaces.',
                'area' => 'Marathahalli Bridge',
                'pincode' => '560037',
                'latitude' => 12.9569,
                'longitude' => 77.7011,
                'open' => '06:00',
                'close' => '21:30',
                'featured' => true,
                'promoted' => false,
                'women_friendly' => true,
                'facilities' => ['yoga', 'recovery', 'cardio', 'group'],
                'plans' => [
                    ['name' => 'Wellness Monthly', 'price' => 3000, 'joining_fee' => 500, 'duration' => 30, 'pt' => false],
                    ['name' => 'Personal Wellness', 'price' => 5800, 'joining_fee' => 800, 'duration' => 30, 'pt' => true],
                ],
            ],
            [
                'slug' => 'arena-performance-electronic-city',
                'branch_slug' => 'arena-performance-electronic-city-main',
                'name' => 'Arena Performance Electronic City',
                'description' => 'Functional training gym for Electronic City with group circuits, strength foundations, and accessible pricing.',
                'area' => 'Electronic City Phase 1',
                'pincode' => '560100',
                'latitude' => 12.8452,
                'longitude' => 77.6602,
                'open' => '05:30',
                'close' => '22:30',
                'featured' => false,
                'promoted' => true,
                'women_friendly' => true,
                'facilities' => ['strength', 'functional', 'group', 'cardio'],
                'plans' => [
                    ['name' => 'Arena Monthly', 'price' => 2200, 'joining_fee' => 400, 'duration' => 30, 'pt' => false],
                    ['name' => 'Athlete Track', 'price' => 4200, 'joining_fee' => 600, 'duration' => 30, 'pt' => true],
                ],
            ],
        ];

        foreach ($gyms as $index => $data) {
            $gym = Gym::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'owner_user_id' => $owner->id,
                    'city_id' => $city->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'logo' => 'demo/gyms/'.$data['slug'].'/logo.png',
                    'logo_url' => 'demo/gyms/'.$data['slug'].'/logo.png',
                    'cover_image' => 'demo/gyms/'.$data['slug'].'/cover.png',
                    'cover_image_url' => 'demo/gyms/'.$data['slug'].'/cover.png',
                    'photo_urls' => [
                        'demo/gyms/'.$data['slug'].'/floor-1.png',
                        'demo/gyms/'.$data['slug'].'/floor-2.png',
                    ],
                    'timezone' => 'Asia/Kolkata',
                    'address' => $data['area'],
                    'address_line' => $data['area'],
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'country' => 'India',
                    'pincode' => $data['pincode'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'opening_time' => $data['open'],
                    'closing_time' => $data['close'],
                    'timings' => [
                        'monday_to_saturday' => ['open' => $data['open'], 'close' => $data['close']],
                        'sunday' => ['open' => '07:00', 'close' => '20:00'],
                    ],
                    'weekly_off' => [],
                    'status' => 'active',
                    'is_active' => true,
                    'approval_status' => 'approved',
                    'approved_at' => now(),
                    'is_verified' => true,
                    'verified_at' => now(),
                    'public_listing_enabled' => true,
                    'show_pricing' => true,
                    'public_listing_approval_status' => 'approved',
                    'public_listing_approved_at' => now(),
                    'is_featured' => $data['featured'],
                    'is_promoted' => $data['promoted'],
                    'featured_sort_order' => $index + 2,
                    'pricing_visible' => true,
                    'trial_available' => true,
                    'contact_visible' => true,
                    'gym_onboarding_completed' => true,
                    'women_friendly' => $data['women_friendly'],
                    'women_only' => false,
                ],
            );

            $branch = Branch::query()->updateOrCreate(
                ['slug' => $data['branch_slug']],
                [
                    'gym_id' => $gym->id,
                    'city_id' => $city->id,
                    'name' => $data['area'],
                    'timezone' => 'Asia/Kolkata',
                    'address' => $data['area'],
                    'address_line' => $data['area'],
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'country' => 'India',
                    'pincode' => $data['pincode'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'opening_time' => $data['open'],
                    'closing_time' => $data['close'],
                    'timings' => [
                        'weekdays' => ['open' => $data['open'], 'close' => $data['close']],
                        'weekend' => ['open' => '07:00', 'close' => '20:00'],
                    ],
                    'weekly_off' => [],
                    'photo_urls' => ['demo/gyms/'.$data['slug'].'/branch.png'],
                    'is_active' => true,
                    'status' => 'active',
                ],
            );

            $facilityIds = collect($data['facilities'])
                ->map(fn (string $key): ?int => $facilities[$key]->id ?? null)
                ->filter()
                ->values()
                ->all();
            $gym->facilities()->sync($facilityIds);
            $branch->facilities()->sync($facilityIds);

            foreach ([
                ['image_path' => 'demo/gyms/'.$data['slug'].'/logo.png', 'type' => 'logo', 'sort_order' => 0],
                ['image_path' => 'demo/gyms/'.$data['slug'].'/cover.png', 'type' => 'cover', 'sort_order' => 0],
                ['image_path' => 'demo/gyms/'.$data['slug'].'/floor-1.png', 'type' => 'gallery', 'sort_order' => 1],
                ['image_path' => 'demo/gyms/'.$data['slug'].'/floor-2.png', 'type' => 'gallery', 'sort_order' => 2],
            ] as $photo) {
                GymPhoto::query()->updateOrCreate(
                    [
                        'gym_id' => $gym->id,
                        'branch_id' => $photo['type'] === 'gallery' ? $branch->id : null,
                        'image_path' => $photo['image_path'],
                        'type' => $photo['type'],
                    ],
                    ['sort_order' => $photo['sort_order']],
                );
            }

            foreach ($data['plans'] as $plan) {
                MembershipPlan::query()->updateOrCreate(
                    [
                        'gym_id' => $gym->id,
                        'branch_id' => $branch->id,
                        'name' => $plan['name'],
                    ],
                    [
                        'duration_days' => $plan['duration'],
                        'plan_price' => $plan['price'],
                        'joining_fee' => $plan['joining_fee'],
                        'pt_included' => $plan['pt'],
                        'description' => $plan['pt']
                            ? 'Includes access plus guided personal training support.'
                            : 'Includes full gym floor access during open hours.',
                        'status' => 'active',
                        'created_by_user_id' => $owner->id,
                    ],
                );
            }
        }
    }
}
