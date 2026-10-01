<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Announcement;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\CommissionEarning;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignChannel;
use App\Models\CommunicationRecipient;
use App\Models\CompensationProfile;
use App\Models\DietPlan;
use App\Models\DietPlanMeal;
use App\Models\DietPlanMealItem;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\FoodCatalogItem;
use App\Models\Gym;
use App\Models\GymLedgerEntry;
use App\Models\GymSelfEnrollmentLink;
use App\Models\GymSelfEnrollmentSubmission;
use App\Models\GymSetting;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipCommissionAllocation;
use App\Models\MembershipPlan;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PayrollStatement;
use App\Models\ScheduledReminder;
use App\Models\SmartAttendanceHub;
use App\Models\TrialRequest;
use App\Models\User;
use App\Models\UserAppPresence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class GymOneUiReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('GymOneUiReviewSeeder is restricted to local and testing environments.');
        }

        $gym = Gym::query()->with('branches')->findOrFail(1);
        $gym->update(['operational_access_enabled' => true]);

        $branch = $gym->branches->firstOrFail();
        $secondBranch = $gym->branches->skip(1)->first() ?? $branch;
        $owner = User::query()->findOrFail($gym->owner_user_id);
        $trainer = User::query()->where('email', 'trainer1@example.com')->firstOrFail();
        $staff = User::query()->where('email', 'staff1@example.com')->firstOrFail();
        $plan = MembershipPlan::query()->where('gym_id', $gym->id)->where('status', 'active')->firstOrFail();
        $ptPlan = MembershipPlan::query()->where('gym_id', $gym->id)->where('pt_included', true)->first() ?? $plan;

        $members = collect([
            ['name' => 'Aarav Active', 'email' => 'ui.active@gymatlas.test', 'membership' => 'active', 'payment' => 'paid', 'paid' => 3000, 'due' => 0],
            ['name' => 'Meera Partial', 'email' => 'ui.partial@gymatlas.test', 'membership' => 'active', 'payment' => 'partial', 'paid' => 1500, 'due' => 2000],
            ['name' => 'Kabir Overdue', 'email' => 'ui.overdue@gymatlas.test', 'membership' => 'active', 'payment' => 'overdue', 'paid' => 0, 'due' => 3000],
            ['name' => 'Isha Frozen', 'email' => 'ui.frozen@gymatlas.test', 'membership' => 'frozen', 'payment' => 'partial', 'paid' => 1000, 'due' => 2000],
            ['name' => 'Rohan Expired', 'email' => 'ui.expired@gymatlas.test', 'membership' => 'expired', 'payment' => 'paid', 'paid' => 3000, 'due' => 0],
            ['name' => 'Tara Former Member', 'email' => 'ui.left@gymatlas.test', 'membership' => 'left_gym', 'payment' => 'paid', 'paid' => 3000, 'due' => 0],
        ])->map(function (array $data, int $index) use ($gym, $branch, $secondBranch, $trainer, $owner, $plan, $ptPlan): array {
            $user = $this->user($data['name'], $data['email'], RoleName::Member);
            $memberBranch = $index % 2 === 0 ? $branch : $secondBranch;
            $isFormer = $data['membership'] === 'left_gym';
            $profileStatus = $isFormer ? 'left_gym' : $data['membership'];

            $gym->users()->syncWithoutDetaching([$user->id => [
                'branch_id' => $memberBranch->id,
                'role_name' => RoleName::Member->value,
                'status' => $isFormer ? 'inactive' : 'active',
                'is_primary' => false,
            ]]);
            $memberBranch->users()->syncWithoutDetaching([$user->id => ['is_primary' => true]]);

            $profile = MemberProfile::query()->updateOrCreate(
                ['user_id' => $user->id, 'gym_id' => $gym->id],
                [
                    'branch_id' => $memberBranch->id,
                    'assigned_trainer_user_id' => $isFormer ? null : $trainer->id,
                    'assigned_trainer_id' => $isFormer ? null : $trainer->id,
                    'fitness_goal' => ['Strength', 'Fat loss', 'Mobility'][$index % 3],
                    'gender' => $index % 2 === 0 ? 'male' : 'female',
                    'height_cm' => 165 + $index,
                    'weight_kg' => 62 + ($index * 3),
                    'experience_level' => ['beginner', 'intermediate', 'advanced'][$index % 3],
                    'emergency_contact_name' => 'Demo Contact',
                    'emergency_contact_phone' => '+9199000000'.($index + 10),
                    'biometric_identifier' => 'UI-MEMBER-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'biometric_enabled' => $index < 3,
                    'status' => $isFormer ? 'inactive' : 'active',
                    'membership_status' => $profileStatus,
                    'membership_expires_on' => match ($data['membership']) {
                        'expired', 'left_gym' => today()->subDays(10),
                        default => today()->addDays(20 + $index),
                    },
                    'is_active' => ! $isFormer,
                ],
            );

            $membershipStatus = $isFormer ? 'cancelled' : $data['membership'];
            $membershipPlan = $index === 1 ? $ptPlan : $plan;
            $startDate = in_array($membershipStatus, ['expired', 'cancelled'], true) ? today()->subDays(45) : today()->subDays(10 + $index);
            $expiryDate = in_array($membershipStatus, ['expired', 'cancelled'], true) ? today()->subDays(10) : today()->addDays(20 + $index);
            $membership = MemberMembership::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'member_id' => $user->id, 'membership_plan_id' => $membershipPlan->id],
                [
                    'branch_id' => $memberBranch->id,
                    'start_date' => $startDate,
                    'expiry_date' => $expiryDate,
                    'status' => $membershipStatus,
                    'paused_at' => $membershipStatus === 'frozen' ? today()->subDays(4) : null,
                    'default_plan_price' => 2500,
                    'default_joining_fee' => 500,
                    'custom_fee_enabled' => $index === 1,
                    'custom_fee_amount' => $index === 1 ? 4500 : 2500,
                    'discount_type' => $index === 1 ? 'fixed' : 'none',
                    'discount_amount' => $index === 1 ? 500 : 0,
                    'custom_joining_fee' => 500,
                    'joining_fee_waived' => false,
                    'partial_month_fee' => 0,
                    'pt_custom_fee' => $index === 1 ? 1500 : 0,
                    'final_payable_amount' => $data['paid'] + $data['due'],
                    'amount_paid' => $data['paid'],
                    'due_amount' => $data['due'],
                    'due_date' => $data['payment'] === 'overdue' ? today()->subDays(5) : $expiryDate,
                    'payment_status' => $data['payment'],
                    'custom_fee_reason' => $index === 1 ? 'PT package with retention discount' : null,
                    'approved_by_admin_id' => $owner->id,
                ],
            );

            $payment = null;
            if ($data['paid'] > 0) {
                $payment = Payment::query()->updateOrCreate(
                    ['gym_id' => $gym->id, 'member_membership_id' => $membership->id, 'external_reference' => 'UI-PAY-'.($index + 1)],
                    [
                        'branch_id' => $memberBranch->id,
                        'member_id' => $user->id,
                        'received_by_user_id' => $owner->id,
                        'collected_by' => $owner->id,
                        'amount' => $data['paid'],
                        'payment_mode' => ['upi', 'cash', 'card'][$index % 3],
                        'status' => 'recorded',
                        'payment_status' => $data['payment'],
                        'receipt_number' => 'UI-RCT-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        'notes' => 'Local UI review payment',
                        'paid_at' => now()->subDays($index + 1),
                        'payment_date' => now()->subDays($index + 1),
                    ],
                );
            }

            UserAppPresence::query()->updateOrCreate(
                ['user_id' => $user->id, 'app_role' => 'member', 'device_key' => 'ui-device-'.($index + 1)],
                [
                    'platform' => $index % 2 === 0 ? 'android' : 'ios',
                    'device_name' => $index % 2 === 0 ? 'Pixel Demo' : 'iPhone Demo',
                    'app_version' => '2.4.0',
                    'bluetooth_permission_status' => $index < 4 ? 'granted' : 'denied',
                    'smart_attendance_scanning' => $index < 4,
                    'smart_attendance_mode' => 'ble',
                    'first_seen_at' => now()->subDays(20),
                    'last_seen_at' => now()->subMinutes($index * 15),
                ],
            );

            return compact('user', 'profile', 'membership', 'payment');
        });

        $this->seedAttendance($gym, $branch, $staff, $members);
        $this->seedTrials($gym, $branch, $trainer);
        $this->seedDietPlans($gym, $branch, $trainer, $members);
        $this->seedEvents($gym, $branch, $owner, $trainer, $members);
        $this->seedEnrollment($gym, $branch, $owner, $members);
        $this->seedDevices($gym, $branch, $owner);
        $this->seedCompensation($gym, $branch, $owner, $trainer, $staff, $members);
        $this->seedCommunications($gym, $branch, $owner, $members);
        $this->seedFinanceAndSettings($gym, $branch, $owner);
    }

    private function seedAttendance(Gym $gym, $branch, User $staff, $members): void
    {
        foreach ($members->take(4)->values() as $index => $data) {
            $checkedInAt = today()->addHours(6 + $index);
            $log = AttendanceLog::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'member_id' => $data['user']->id, 'checked_in_at' => $checkedInAt],
                [
                    'branch_id' => $branch->id,
                    'checked_in_by' => $staff->id,
                    'check_in_method' => $index === 1 ? 'smart_attendance' : 'manual',
                    'last_presence_at' => $checkedInAt->copy()->addMinutes(75),
                    'checked_out_at' => $index === 0 ? null : $checkedInAt->copy()->addHours(2),
                    'attendance_window_ends_at' => $checkedInAt->copy()->addHours(4),
                    'notes' => $index === 0 ? 'Currently inside gym' : 'UI review attendance',
                    'smart_attendance_detection' => $index === 1 ? ['source' => 'ble', 'rssi' => -58] : null,
                    'smart_attendance_exit_managed' => $index === 1,
                ],
            );

            if ($index === 2) {
                AttendanceCorrectionRequest::query()->updateOrCreate(
                    ['attendance_log_id' => $log->id, 'requested_by' => $data['user']->id],
                    [
                        'gym_id' => $gym->id,
                        'branch_id' => $branch->id,
                        'member_id' => $data['user']->id,
                        'status' => 'pending',
                        'reason' => 'Check-in should be fifteen minutes earlier.',
                        'requested_check_in_at' => $checkedInAt->copy()->subMinutes(15),
                    ],
                );
            }
        }
    }

    private function seedTrials(Gym $gym, $branch, User $trainer): void
    {
        foreach (['pending', 'contacted', 'completed', 'cancelled'] as $index => $status) {
            TrialRequest::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'email' => "ui.trial.{$status}@gymatlas.test"],
                [
                    'branch_id' => $branch->id,
                    'request_type' => 'trial',
                    'source' => ['website', 'walk_in', 'referral', 'phone'][$index],
                    'name' => ucfirst($status).' Trial Lead',
                    'phone' => '+9198111100'.($index + 10),
                    'preferred_date' => today()->addDays($index + 1),
                    'preferred_time' => '18:00:00',
                    'status' => $status,
                    'assigned_trainer_id' => $trainer->id,
                    'notes' => 'Local UI review lead',
                ],
            );
        }
    }

    private function seedDietPlans(Gym $gym, $branch, User $trainer, $members): void
    {
        $food = FoodCatalogItem::query()->first();
        foreach (['active', 'draft', 'completed'] as $index => $status) {
            $member = $members[$index]['user'];
            $plan = DietPlan::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'member_id' => $member->id, 'name' => ucfirst($status).' Nutrition Plan'],
                [
                    'branch_id' => $branch->id,
                    'trainer_id' => $trainer->id,
                    'created_by_user_id' => $trainer->id,
                    'goal' => ['Fat loss', 'Muscle gain', 'Maintenance'][$index],
                    'daily_calorie_target' => 1800 + ($index * 300),
                    'protein_target_g' => 120 + ($index * 15),
                    'carbs_target_g' => 190 + ($index * 20),
                    'fats_target_g' => 55 + ($index * 5),
                    'dietary_preferences' => $index === 1 ? 'Vegetarian' : 'High protein',
                    'allergies_and_restrictions' => $index === 2 ? 'Lactose sensitive' : null,
                    'notes' => 'Seeded for gym admin UI review.',
                    'status' => $status,
                    'assigned_at' => now()->subDays(5),
                    'starts_on' => today()->subDays(5),
                    'ends_on' => today()->addDays(25),
                ],
            );
            $meal = DietPlanMeal::query()->updateOrCreate(
                ['diet_plan_id' => $plan->id, 'meal_type' => 'breakfast'],
                ['name' => 'Protein Breakfast', 'scheduled_time' => '08:00', 'sort_order' => 1, 'calories' => 480, 'protein_g' => 32, 'carbs_g' => 48, 'fats_g' => 14],
            );
            DietPlanMealItem::query()->updateOrCreate(
                ['diet_plan_meal_id' => $meal->id, 'name' => 'Oats and fruit bowl'],
                ['food_catalog_item_id' => $food?->id, 'quantity' => '1 bowl', 'sort_order' => 1, 'calories' => 480, 'protein_g' => 32, 'carbs_g' => 48, 'fats_g' => 14, 'fiber_g' => 8],
            );
        }
    }

    private function seedEvents(Gym $gym, $branch, User $owner, User $trainer, $members): void
    {
        foreach (['published', 'draft', 'cancelled'] as $index => $status) {
            $event = Event::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'title' => ['Saturday Strength Clinic', 'Mobility Workshop Draft', 'Cancelled Outdoor Bootcamp'][$index]],
                [
                    'scope' => 'gym',
                    'branch_id' => $branch->id,
                    'created_by_user_id' => $owner->id,
                    'host_user_id' => $trainer->id,
                    'category' => ['workshop', 'mobility', 'bootcamp'][$index],
                    'description' => 'Representative event for the gym admin redesign.',
                    'starts_at' => now()->addDays(5 + $index)->setTime(7, 0),
                    'ends_at' => now()->addDays(5 + $index)->setTime(9, 0),
                    'timezone' => 'Asia/Kolkata',
                    'booking_opens_at' => now()->subDay(),
                    'booking_closes_at' => now()->addDays(4 + $index),
                    'capacity' => 24,
                    'waitlist_enabled' => true,
                    'pricing_type' => $index === 0 ? 'paid' : 'free',
                    'price_amount' => $index === 0 ? 499 : null,
                    'currency' => 'INR',
                    'location_name' => $branch->name,
                    'status' => $status,
                    'published_at' => $status === 'published' ? now()->subDay() : null,
                    'cancelled_at' => $status === 'cancelled' ? now()->subHours(2) : null,
                    'cancellation_reason' => $status === 'cancelled' ? 'Weather warning' : null,
                ],
            );

            if ($index === 0) {
                foreach (['reserved', 'checked_in', 'waitlisted'] as $bookingIndex => $bookingStatus) {
                    EventBooking::query()->updateOrCreate(
                        ['event_id' => $event->id, 'user_id' => $members[$bookingIndex]['user']->id],
                        [
                            'status' => $bookingStatus,
                            'booked_at' => now()->subDays(2),
                            'checked_in_at' => $bookingStatus === 'checked_in' ? now() : null,
                            'checked_in_by_user_id' => $bookingStatus === 'checked_in' ? $owner->id : null,
                            'price_amount_snapshot' => 499,
                            'currency_snapshot' => 'INR',
                        ],
                    );
                }
            }
        }
    }

    private function seedEnrollment(Gym $gym, $branch, User $owner, $members): void
    {
        $link = GymSelfEnrollmentLink::query()->updateOrCreate(
            ['gym_id' => $gym->id, 'branch_id' => $branch->id],
            ['created_by_user_id' => $owner->id, 'token' => '11111111-1111-4111-8111-111111111111', 'name' => 'HSR Front Desk QR', 'is_active' => true],
        );
        foreach (['member_created', 'invitation_sent', 'already_member'] as $index => $outcome) {
            GymSelfEnrollmentSubmission::query()->updateOrCreate(
                ['gym_self_enrollment_link_id' => $link->id, 'submitted_email' => "ui.enrollment.{$index}@gymatlas.test"],
                [
                    'gym_id' => $gym->id,
                    'branch_id' => $branch->id,
                    'user_id' => $index === 2 ? $members[0]['user']->id : null,
                    'submitted_name' => 'Enrollment Lead '.($index + 1),
                    'submitted_phone' => '+9198222200'.($index + 10),
                    'outcome' => $outcome,
                    'source' => $index === 0 ? 'app' : 'web',
                    'payload' => ['fitness_goal' => 'General fitness'],
                    'request_fingerprint' => hash('sha256', 'ui-enrollment-'.$index),
                    'consented_at' => now()->subHours($index + 1),
                    'consent_version' => '2026-01',
                ],
            );
        }
    }

    private function seedDevices(Gym $gym, $branch, User $owner): void
    {
        BiometricDevice::query()->updateOrCreate(
            ['gym_id' => $gym->id, 'serial_number' => 'UI-BIO-001'],
            [
                'branch_id' => $branch->id,
                'created_by_user_id' => $owner->id,
                'uuid' => '22222222-2222-4222-8222-222222222222',
                'name' => 'Main Entrance Face Terminal',
                'vendor' => 'eSSL',
                'model' => 'SpeedFace Demo',
                'firmware_version' => '1.8.4',
                'connector_version' => '2.1.0',
                'adapter_key' => 'essl_ebioserver',
                'connection_method' => 'cloud_push',
                'modalities' => ['face', 'fingerprint'],
                'capabilities' => ['attendance', 'enrollment'],
                'configuration' => ['demo' => true],
                'status' => 'connected',
                'is_active' => true,
                'last_seen_at' => now()->subMinute(),
                'last_event_at' => now()->subMinutes(3),
                'clock_skew_seconds' => 2,
            ],
        );
        BiometricDevice::query()->updateOrCreate(
            ['gym_id' => $gym->id, 'serial_number' => 'UI-BIO-002'],
            [
                'branch_id' => $branch->id,
                'created_by_user_id' => $owner->id,
                'uuid' => '33333333-3333-4333-8333-333333333333',
                'name' => 'Studio Fingerprint Terminal',
                'vendor' => 'Generic',
                'adapter_key' => 'generic_webhook',
                'connection_method' => 'webhook',
                'modalities' => ['fingerprint'],
                'status' => 'error',
                'is_active' => true,
                'last_error' => 'Demo device is waiting for credentials.',
            ],
        );
        foreach ([
            ['44444444-4444-4444-8444-444444444444', 'ATLAS-UI-ONLINE', 'Reception BLE Hub', 'online', now()->subMinute()],
            ['55555555-5555-4555-8555-555555555555', 'ATLAS-UI-OFFLINE', 'Weight Floor Hub', 'online', now()->subMinutes(30)],
            ['66666666-6666-4666-8666-666666666666', 'ATLAS-UI-PENDING', 'New Unpaired Hub', 'pending', null],
        ] as [$uuid, $publicId, $name, $status, $lastSeen]) {
            SmartAttendanceHub::query()->updateOrCreate(
                ['public_id' => $publicId],
                ['uuid' => $uuid, 'gym_id' => $gym->id, 'branch_id' => $branch->id, 'created_by_user_id' => $owner->id, 'name' => $name, 'platform' => 'esp32', 'status' => $status, 'is_active' => true, 'last_seen_at' => $lastSeen, 'firmware_version' => '1.2.0', 'metadata' => ['service_uuid' => '9f100001-demo']],
            );
        }
    }

    private function seedCompensation(Gym $gym, $branch, User $owner, User $trainer, User $staff, $members): void
    {
        foreach ([[$trainer, 'trainer', 22000, 5], [$staff, 'staff', 18000, 7]] as [$user, $type, $salary, $payoutDay]) {
            CompensationProfile::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'user_id' => $user->id],
                ['branch_id' => $branch->id, 'worker_type' => $type, 'monthly_salary' => $salary, 'payout_day' => $payoutDay, 'effective_from' => today()->startOfYear(), 'is_active' => true],
            );
            PayrollStatement::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'user_id' => $user->id, 'period_start' => today()->startOfMonth()],
                [
                    'branch_id' => $branch->id,
                    'period_end' => today()->endOfMonth(),
                    'salary_amount' => $salary,
                    'commission_amount' => $type === 'trainer' ? 750 : 250,
                    'adjustment_amount' => 500,
                    'deduction_amount' => 250,
                    'net_payable_amount' => $salary + ($type === 'trainer' ? 1000 : 500),
                    'paid_amount' => $type === 'staff' ? $salary : 0,
                    'status' => $type === 'staff' ? 'paid' : 'approved',
                    'notes' => 'Local UI review payroll statement',
                    'generated_at' => now(),
                    'approved_by_user_id' => $owner->id,
                    'approved_at' => now(),
                ],
            );
        }

        $membership = $members[1]['membership'];
        $payment = $members[1]['payment'];
        if ($payment) {
            $allocation = MembershipCommissionAllocation::query()->updateOrCreate(
                ['member_membership_id' => $membership->id, 'recipient_user_id' => $trainer->id],
                ['gym_id' => $gym->id, 'branch_id' => $branch->id, 'recipient_type' => 'trainer', 'category' => 'pt', 'calculation_type' => 'percentage', 'value' => 40, 'recurrence' => 'recurring', 'commissionable_extra_amount' => 1500, 'expected_commission_amount' => 600, 'status' => 'active'],
            );
            CommissionEarning::query()->updateOrCreate(
                ['membership_commission_allocation_id' => $allocation->id, 'payment_id' => $payment->id],
                ['gym_id' => $gym->id, 'branch_id' => $branch->id, 'recipient_user_id' => $trainer->id, 'commissionable_collected_amount' => 750, 'amount' => 300, 'status' => 'earned', 'earned_at' => now()->subDay()],
            );
        }
    }

    private function seedCommunications(Gym $gym, $branch, User $owner, $members): void
    {
        foreach (['draft', 'scheduled', 'completed'] as $index => $status) {
            $campaign = CommunicationCampaign::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'name' => ucfirst($status).' Member Campaign'],
                ['branch_id' => $index === 0 ? $branch->id : null, 'audience_type' => $index === 1 ? 'due_members' : 'all_members', 'audience_filters' => ['membership_status' => 'active'], 'status' => $status, 'scheduled_for' => $status === 'scheduled' ? now()->addDay() : null, 'started_at' => $status === 'completed' ? now()->subHours(2) : null, 'completed_at' => $status === 'completed' ? now()->subHour() : null, 'created_by_user_id' => $owner->id],
            );
            $channel = CommunicationCampaignChannel::query()->updateOrCreate(
                ['communication_campaign_id' => $campaign->id, 'channel' => 'push'],
                ['notification_type' => 'gym_update', 'title' => 'Gym Atlas update', 'body' => 'Representative campaign for UI review.'],
            );
            foreach ($members->take(3) as $memberIndex => $data) {
                CommunicationRecipient::query()->updateOrCreate(
                    ['communication_campaign_id' => $campaign->id, 'communication_campaign_channel_id' => $channel->id, 'user_id' => $data['user']->id],
                    ['channel' => 'push', 'destination' => 'device-token-demo', 'status' => $status === 'completed' ? ['sent', 'delivered', 'read'][$memberIndex] : 'pending', 'recipient_snapshot' => ['name' => $data['user']->name], 'attempt_count' => $status === 'completed' ? 1 : 0, 'sent_at' => $status === 'completed' ? now()->subHour() : null, 'delivered_at' => $status === 'completed' && $memberIndex > 0 ? now()->subMinutes(50) : null, 'read_at' => $status === 'completed' && $memberIndex === 2 ? now()->subMinutes(40) : null],
                );
            }
        }

        foreach (['draft', 'scheduled'] as $index => $status) {
            Announcement::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'title' => ucfirst($status).' Gym Announcement'],
                ['branch_id' => $index === 0 ? $branch->id : null, 'created_by_user_id' => $owner->id, 'created_by' => $owner->id, 'audience_type' => 'gym_wide', 'message' => 'Representative announcement content for UI review.', 'status' => $status, 'is_platform_wide' => false, 'send_at' => $status === 'scheduled' ? now()->addHours(6) : null, 'metadata' => ['ui_review' => true]],
            );
        }

        foreach ($members->take(3) as $index => $data) {
            $notification = Notification::query()->updateOrCreate(
                ['user_id' => $data['user']->id, 'deduplication_key' => 'ui-review-notification-'.$index],
                ['gym_id' => $gym->id, 'branch_id' => $branch->id, 'member_membership_id' => $data['membership']->id, 'type' => 'gym_update', 'title' => ['Payment reminder', 'New workout assigned', 'Gym maintenance notice'][$index], 'message' => 'Representative notification for UI review.', 'body' => 'Representative notification for UI review.', 'data' => ['screen' => 'notifications'], 'read_at' => $index === 2 ? now() : null, 'created_by_user_id' => $owner->id, 'scheduled_for' => now()->subMinutes(20), 'in_app_visible' => true],
            );
            ScheduledReminder::query()->updateOrCreate(
                ['user_id' => $data['user']->id, 'gym_id' => $gym->id, 'type' => 'payment_due', 'member_membership_id' => $data['membership']->id],
                ['branch_id' => $branch->id, 'title' => 'Membership payment reminder', 'body' => 'Your membership payment checkpoint is approaching.', 'payload' => ['ui_review' => true, 'notification_id' => $notification->id], 'scheduled_for' => now()->addDays($index + 1), 'status' => ['pending', 'sent', 'cancelled'][$index]],
            );
        }
    }

    private function seedFinanceAndSettings(Gym $gym, $branch, User $owner): void
    {
        foreach ([
            ['UI-EXP-RENT', 'expense', 'outflow', 'rent', 'Monthly facility rent', 85000, 'bank'],
            ['UI-EXP-UTIL', 'expense', 'outflow', 'utilities', 'Electricity and utilities', 18500, 'upi'],
            ['UI-INC-PT', 'other_income', 'inflow', 'personal_training', 'PT package collection', 12500, 'card'],
        ] as $index => [$reference, $entryType, $direction, $category, $title, $amount, $mode]) {
            GymLedgerEntry::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'reference' => $reference],
                ['branch_id' => $branch->id, 'created_by_user_id' => $owner->id, 'entry_type' => $entryType, 'direction' => $direction, 'category' => $category, 'title' => $title, 'description' => 'Seeded for local gym admin review.', 'payment_mode' => $mode, 'amount' => $amount, 'status' => 'posted', 'occurred_at' => now()->subDays($index + 2), 'metadata' => ['ui_review' => true]],
            );
        }

        foreach ([
            'attendance_duplicate_window_minutes' => ['value' => 240],
            'attendance_auto_checkout_hours' => ['value' => 2],
            'membership_expiry_reminder_days' => ['value' => [7, 3, 1]],
            'allow_member_self_enrollment' => ['value' => true],
        ] as $key => $value) {
            GymSetting::query()->updateOrCreate(['gym_id' => $gym->id, 'key' => $key], ['value' => $value]);
        }
    }

    private function user(string $name, string $email, RoleName $role): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make((string) config('gym.demo_user_password')), 'email_verified_at' => now(), 'active_role' => $role->value, 'is_active' => true],
        );
        $user->syncRoles([$role->value]);

        return $user;
    }
}
