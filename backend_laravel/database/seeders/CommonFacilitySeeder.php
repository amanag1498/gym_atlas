<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;

class CommonFacilitySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->facilities() as $facility) {
            Facility::query()->updateOrCreate(
                ['slug' => $facility['slug']],
                [
                    'name' => $facility['name'],
                    'icon' => $facility['icon'],
                    'description' => $facility['description'],
                    'status' => 'active',
                    'is_active' => true,
                ],
            );
        }
    }

    /** @return array<int, array{slug: string, name: string, icon: string, description: string}> */
    private function facilities(): array
    {
        return [
            ['slug' => 'ac', 'name' => 'Air Conditioning', 'icon' => 'snowflake', 'description' => 'Air-conditioned workout areas.'],
            ['slug' => 'cardio', 'name' => 'Cardio Equipment', 'icon' => 'activity', 'description' => 'Cardio machines such as treadmills, cycles, rowers, or ellipticals.'],
            ['slug' => 'weight-training', 'name' => 'Strength Machines', 'icon' => 'barbell', 'description' => 'Selectorized or plate-loaded strength equipment.'],
            ['slug' => 'free-weights', 'name' => 'Free Weights', 'icon' => 'barbell', 'description' => 'Dumbbells, barbells, benches, racks, and lifting platforms.'],
            ['slug' => 'functional-training', 'name' => 'Functional Training Zone', 'icon' => 'stretching', 'description' => 'Open training space with functional equipment.'],
            ['slug' => 'crossfit', 'name' => 'Cross-training Zone', 'icon' => 'barbell', 'description' => 'Space and equipment for high-intensity cross-training.'],
            ['slug' => 'hiit-zone', 'name' => 'HIIT Zone', 'icon' => 'bolt', 'description' => 'Dedicated space for interval and circuit training.'],
            ['slug' => 'group-exercise-studio', 'name' => 'Group Exercise Studio', 'icon' => 'users-group', 'description' => 'Studio space for instructor-led group classes.'],
            ['slug' => 'zumba', 'name' => 'Dance Fitness', 'icon' => 'music', 'description' => 'Instructor-led dance fitness classes.'],
            ['slug' => 'yoga-studio', 'name' => 'Yoga Studio', 'icon' => 'yoga', 'description' => 'Dedicated yoga and mind-body practice space.'],
            ['slug' => 'pilates-studio', 'name' => 'Pilates Studio', 'icon' => 'stretching-2', 'description' => 'Dedicated mat or equipment-based Pilates space.'],
            ['slug' => 'indoor-cycling', 'name' => 'Indoor Cycling Studio', 'icon' => 'bike', 'description' => 'Studio with stationary cycles for coached sessions.'],
            ['slug' => 'boxing-combat-area', 'name' => 'Boxing / Combat Area', 'icon' => 'boxing', 'description' => 'Bags, mats, or ring space for combat conditioning.'],
            ['slug' => 'personal-training', 'name' => 'Personal Training', 'icon' => 'user-star', 'description' => 'One-to-one or small-group coaching is available.'],
            ['slug' => 'fitness-assessment', 'name' => 'Fitness Assessment', 'icon' => 'clipboard-heart', 'description' => 'Non-diagnostic fitness and movement assessments.'],
            ['slug' => 'nutrition-coaching', 'name' => 'Nutrition Coaching', 'icon' => 'apple', 'description' => 'General nutrition and habit coaching within practitioner scope.'],
            ['slug' => 'women-only', 'name' => 'Women-only Area', 'icon' => 'user-heart', 'description' => 'A workout area reserved for women.'],
            ['slug' => 'accessible-entry', 'name' => 'Accessible Entrance', 'icon' => 'wheelchair', 'description' => 'Step-free or wheelchair-accessible entry.'],
            ['slug' => 'accessible-restroom', 'name' => 'Accessible Restroom', 'icon' => 'accessible', 'description' => 'Restroom designed for accessible use.'],
            ['slug' => 'changing-room', 'name' => 'Changing Rooms', 'icon' => 'shirt', 'description' => 'Dedicated member changing areas.'],
            ['slug' => 'locker', 'name' => 'Lockers', 'icon' => 'lock', 'description' => 'Secure or member-use storage lockers.'],
            ['slug' => 'shower', 'name' => 'Showers', 'icon' => 'droplet', 'description' => 'Member shower facilities.'],
            ['slug' => 'steam', 'name' => 'Steam Room', 'icon' => 'flame', 'description' => 'Member steam room.'],
            ['slug' => 'sauna', 'name' => 'Sauna', 'icon' => 'temperature', 'description' => 'Member sauna facility.'],
            ['slug' => 'swimming-pool', 'name' => 'Swimming Pool', 'icon' => 'pool', 'description' => 'On-site lap or recreational pool.'],
            ['slug' => 'drinking-water', 'name' => 'Drinking Water', 'icon' => 'bottle', 'description' => 'Filtered water station or drinking fountain.'],
            ['slug' => 'parking', 'name' => 'Parking', 'icon' => 'car', 'description' => 'On-site or validated member parking.'],
            ['slug' => 'bicycle-parking', 'name' => 'Bicycle Parking', 'icon' => 'bike', 'description' => 'Dedicated bicycle stands or secure parking.'],
            ['slug' => 'wifi', 'name' => 'Wi-Fi', 'icon' => 'wifi', 'description' => 'Member Wi-Fi access.'],
            ['slug' => 'towel-service', 'name' => 'Towel Service', 'icon' => 'wash', 'description' => 'Workout or shower towels are available.'],
            ['slug' => 'cafe-protein-bar', 'name' => 'Cafe / Protein Bar', 'icon' => 'cup', 'description' => 'On-site beverages, snacks, or protein refreshments.'],
            ['slug' => 'childcare', 'name' => 'Childcare', 'icon' => 'baby-carriage', 'description' => 'Supervised childcare during eligible workout hours.'],
            ['slug' => '24-hour-access', 'name' => '24-hour Access', 'icon' => 'clock-24', 'description' => 'Member entry is available around the clock.'],
            ['slug' => 'first-aid', 'name' => 'First Aid', 'icon' => 'first-aid-kit', 'description' => 'Clearly identified first-aid supplies are available.'],
            ['slug' => 'aed', 'name' => 'AED', 'icon' => 'heart-rate-monitor', 'description' => 'An automated external defibrillator is available on site.'],
            ['slug' => 'security-cctv', 'name' => 'Security / CCTV', 'icon' => 'shield-check', 'description' => 'On-site security controls or monitored common areas.'],
        ];
    }
}
