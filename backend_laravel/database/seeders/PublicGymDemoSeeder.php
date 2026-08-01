<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\City;
use App\Models\Facility;
use App\Models\Gym;
use App\Models\GymPhoto;
use App\Models\MembershipPlan;
use App\Support\Scheduling\OperatingHours;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use LogicException;

class PublicGymDemoSeeder extends Seeder
{
    /** @var array<string, string> */
    private const IMAGE_SOURCES = [
        'strength' => 'images/public-site/gyms/atlas-forge-strength.webp',
        'wellness' => 'images/public-site/gyms/atlas-arc-wellness.webp',
        'performance' => 'images/public-site/gyms/atlas-performance-club.webp',
        'operations' => 'images/public-site/editorial/gym-operations-team.webp',
        'coaching' => 'images/public-site/editorial/trainer-member-coaching.webp',
        'ecosystem' => 'images/public-site/editorial/atlas-ecosystem-network.webp',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('PublicGymDemoSeeder is restricted to local and testing environments.');
        }

        $city = City::query()->updateOrCreate(
            ['name' => 'Bengaluru', 'state' => 'Karnataka', 'country' => 'India'],
            ['is_active' => true],
        );

        $facilities = collect([
            ['slug' => 'atlas-demo-strength', 'name' => 'Strength Floor', 'description' => 'Racks, platforms, and free weights.'],
            ['slug' => 'atlas-demo-cardio', 'name' => 'Cardio Studio', 'description' => 'Dedicated endurance and conditioning equipment.'],
            ['slug' => 'atlas-demo-functional', 'name' => 'Functional Zone', 'description' => 'Open training turf and functional equipment.'],
            ['slug' => 'atlas-demo-classes', 'name' => 'Group Classes', 'description' => 'Coach-led sessions in a shared studio.'],
            ['slug' => 'atlas-demo-recovery', 'name' => 'Recovery Space', 'description' => 'Mobility and post-training recovery area.'],
            ['slug' => 'atlas-demo-wellness', 'name' => 'Wellness Studio', 'description' => 'Yoga, mobility, and low-impact training.'],
        ])->mapWithKeys(function (array $data): array {
            $facility = Facility::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data + ['is_active' => true, 'status' => 'active'],
            );

            return [$data['slug'] => $facility];
        });

        $gyms = [
            ['atlas-demo-ember-athletic-club', 'Ember Athletic Club', 'Indiranagar', 'A fictional design-led training club with strength, conditioning, and recovery spaces for focused everyday training.', 12.9719, 77.6412, 'strength', ['atlas-demo-strength', 'atlas-demo-functional', 'atlas-demo-recovery'], 3200, true, true],
            ['atlas-demo-northline-strength', 'Northline Strength House', 'Koramangala', 'A fictional lifting-focused studio built around structured programming, open platforms, and attentive floor coaching.', 12.9352, 77.6245, 'performance', ['atlas-demo-strength', 'atlas-demo-functional', 'atlas-demo-classes'], 2800, true, false],
            ['atlas-demo-arc-wellness-studio', 'Arc Wellness Studio', 'HSR Layout', 'A fictional calm, light-filled space combining strength fundamentals, mobility, yoga, and guided recovery.', 12.9116, 77.6476, 'wellness', ['atlas-demo-wellness', 'atlas-demo-recovery', 'atlas-demo-classes'], 3000, true, true],
            ['atlas-demo-forge-performance-lab', 'Forge Performance Lab', 'Whitefield', 'A fictional performance gym with a large functional floor, progressive strength equipment, and coach-led sessions.', 12.9698, 77.7500, 'performance', ['atlas-demo-strength', 'atlas-demo-cardio', 'atlas-demo-functional'], 3500, false, true],
            ['atlas-demo-canvas-fitness-club', 'Canvas Fitness Club', 'JP Nagar', 'A fictional neighbourhood fitness club with clear membership options, approachable coaching, and versatile training zones.', 12.9063, 77.5857, 'coaching', ['atlas-demo-cardio', 'atlas-demo-functional', 'atlas-demo-classes'], 2400, false, false],
            ['atlas-demo-stillpoint-training', 'Stillpoint Training Studio', 'Marathahalli', 'A fictional boutique studio balancing personal guidance, small-group training, mobility, and mindful recovery.', 12.9569, 77.7011, 'ecosystem', ['atlas-demo-strength', 'atlas-demo-wellness', 'atlas-demo-recovery'], 2900, true, false],
        ];
        $timings = OperatingHours::buildFromFlat('06:00', '22:30');

        foreach ($gyms as $index => [$slug, $name, $area, $description, $latitude, $longitude, $heroKey, $facilitySlugs, $price, $featured, $promoted]) {
            $paths = $this->installImages($slug, $heroKey);
            $gym = Gym::query()->updateOrCreate(['slug' => $slug], [
                'owner_user_id' => null,
                'city_id' => $city->id,
                'name' => $name,
                'description' => $description,
                'logo' => $paths['logo'],
                'logo_url' => null,
                'cover_image' => $paths['cover'],
                'cover_image_url' => null,
                'photo_urls' => array_values($paths['gallery']),
                'timezone' => 'Asia/Kolkata',
                'address' => $area.', Demo District',
                'address_line' => $area.', Demo District',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'country' => 'India',
                'pincode' => '5600'.str_pad((string) ($index + 31), 2, '0', STR_PAD_LEFT),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'opening_time' => '06:00',
                'closing_time' => '22:30',
                'timings' => $timings,
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
                'is_featured' => $featured,
                'is_promoted' => $promoted,
                'featured_sort_order' => $index + 1,
                'pricing_visible' => true,
                'trial_available' => true,
                'contact_visible' => false,
                'gym_onboarding_completed' => true,
                'women_friendly' => true,
                'women_only' => false,
            ]);

            $branch = Branch::query()->updateOrCreate(['slug' => $slug.'-main'], [
                'gym_id' => $gym->id,
                'city_id' => $city->id,
                'name' => $area.' Studio',
                'timezone' => 'Asia/Kolkata',
                'address' => $area.', Demo District',
                'address_line' => $area.', Demo District',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'country' => 'India',
                'pincode' => $gym->pincode,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'opening_time' => '06:00',
                'closing_time' => '22:30',
                'timings' => $gym->timings,
                'weekly_off' => [],
                'photo_urls' => array_values($paths['gallery']),
                'status' => 'active',
                'is_active' => true,
            ]);

            $facilityIds = $facilities->only($facilitySlugs)->pluck('id')->all();
            $gym->facilities()->sync($facilityIds);
            $branch->facilities()->sync($facilityIds);

            foreach ($paths['gallery'] as $order => $path) {
                GymPhoto::query()->updateOrCreate(
                    ['gym_id' => $gym->id, 'branch_id' => $branch->id, 'image_path' => $path, 'type' => 'gallery'],
                    ['sort_order' => $order + 1],
                );
            }

            foreach ([
                ['Studio Access', $price, false, 'Flexible access to the gym floor and scheduled group sessions.'],
                ['Guided Training', $price + 2400, true, 'Gym access with guided personal training support.'],
            ] as [$planName, $planPrice, $includesPt, $planDescription]) {
                MembershipPlan::query()->updateOrCreate(
                    ['gym_id' => $gym->id, 'branch_id' => $branch->id, 'name' => $planName],
                    ['billing_type' => 'paid', 'billing_period' => 'month', 'billing_interval_count' => 1, 'duration_days' => 30, 'plan_price' => $planPrice, 'joining_fee' => 0, 'pt_included' => $includesPt, 'description' => $planDescription, 'status' => 'active', 'created_by_user_id' => null],
                );
            }
        }
    }

    /** @return array{logo:string, cover:string, gallery:array<int, string>} */
    private function installImages(string $slug, string $heroKey): array
    {
        $keys = array_keys(self::IMAGE_SOURCES);
        $orderedKeys = array_values(array_unique(array_merge([$heroKey], $keys)));
        $installed = [];

        foreach ($orderedKeys as $key) {
            $source = public_path(self::IMAGE_SOURCES[$key]);
            $target = 'demo/public-gyms/'.$slug.'/'.$key.'.webp';

            if (! is_file($source)) {
                throw new LogicException('Missing public demo source image: '.$source);
            }

            Storage::disk('public')->put($target, file_get_contents($source));
            $installed[$key] = $target;
        }

        return [
            'logo' => $installed[$heroKey],
            'cover' => $installed[$heroKey],
            'gallery' => array_values($installed),
        ];
    }
}
