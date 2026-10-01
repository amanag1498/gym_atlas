<?php

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\CommunicationCampaign;
use App\Models\DietPlan;
use App\Models\Event;
use App\Models\Gym;
use App\Models\GymLedgerEntry;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\SmartAttendanceHub;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\GymOneUiReviewSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymOneUiReviewSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_broad_idempotent_gym_admin_review_dataset(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);
        $this->seed(GymOneUiReviewSeeder::class);

        $gym = Gym::query()->findOrFail(1);
        $counts = $this->counts($gym->id);

        $this->seed(GymOneUiReviewSeeder::class);

        $this->assertSame($counts, $this->counts($gym->id));
        $this->assertGreaterThanOrEqual(7, $counts['member_profiles']);
        $this->assertGreaterThanOrEqual(8, $counts['memberships']);
        $this->assertSame(3, $counts['diet_plans']);
        $this->assertSame(3, $counts['events']);
        $this->assertSame(2, $counts['biometric_devices']);
        $this->assertSame(3, $counts['smart_hubs']);
        $this->assertSame(3, $counts['campaigns']);
        $this->assertGreaterThanOrEqual(4, $counts['ledger_entries']);
    }

    /** @return array<string, int> */
    private function counts(int $gymId): array
    {
        return [
            'member_profiles' => MemberProfile::query()->where('gym_id', $gymId)->count(),
            'memberships' => MemberMembership::query()->where('gym_id', $gymId)->count(),
            'diet_plans' => DietPlan::query()->where('gym_id', $gymId)->count(),
            'events' => Event::query()->where('gym_id', $gymId)->count(),
            'biometric_devices' => BiometricDevice::query()->where('gym_id', $gymId)->count(),
            'smart_hubs' => SmartAttendanceHub::query()->where('gym_id', $gymId)->count(),
            'campaigns' => CommunicationCampaign::query()->where('gym_id', $gymId)->count(),
            'ledger_entries' => GymLedgerEntry::query()->where('gym_id', $gymId)->count(),
        ];
    }
}
