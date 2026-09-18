<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\WhatsApp\WhatsAppConsentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_role_aware_notification_preferences_catalog(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $user->assignRole(RoleName::Member->value);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/public/notification-preferences')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'notification_type' => NotificationType::WorkoutReminder->value,
                'label' => 'Workout reminders',
            ])
            ->assertJsonMissing([
                'notification_type' => NotificationType::GymApprovalAlert->value,
            ]);
    }

    public function test_member_notification_and_whatsapp_channels_default_to_enabled(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Member->value,
            'phone' => '9876543210',
        ]);
        $user->assignRole(RoleName::Member->value);

        $preferences = $this->actingAs($user, 'sanctum')
            ->getJson('/api/public/notification-preferences')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($preferences);
        foreach ($preferences as $preference) {
            $this->assertTrue($preference['is_enabled']);
            $this->assertTrue($preference['channels']['in_app']);
            $this->assertTrue($preference['channels']['whatsapp']);
        }

        $whatsApp = app(WhatsAppConsentService::class);
        foreach (['utility', 'marketing'] as $purpose) {
            $eligibility = $whatsApp->deliveryEligibility($user, null, $purpose);
            $this->assertSame('+919876543210', $eligibility['phone']);
            $this->assertNull($eligibility['exclusion_reason']);
        }
    }

    public function test_global_preference_disables_non_critical_scoped_notifications(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $user->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'name' => 'Preference Gym',
            'slug' => 'preference-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Preference Branch',
            'slug' => 'preference-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'notification_type' => NotificationType::GymAnnouncement->value,
            'is_enabled' => false,
        ]);

        $notification = app(NotificationService::class)->create(
            user: $user,
            type: NotificationType::GymAnnouncement->value,
            title: 'Gym announcement',
            body: 'Hidden by preference.',
            gymId: $gym->id,
            branchId: $branch->id,
        );

        $this->assertNull($notification);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_critical_billing_notifications_still_create_even_when_disabled(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'active_role' => RoleName::Member->value,
        ]);
        $user->assignRole(RoleName::Member->value);

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'notification_type' => NotificationType::PaymentDue->value,
            'is_enabled' => false,
        ]);

        $notification = app(NotificationService::class)->create(
            user: $user,
            type: NotificationType::PaymentDue->value,
            title: 'Payment due',
            body: 'Critical billing reminder.',
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::PaymentDue->value,
        ]);
    }
}
