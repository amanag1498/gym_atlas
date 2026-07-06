<?php

namespace Database\Seeders;

use App\Models\PlatformBanner;
use Illuminate\Database\Seeder;

class PlatformBannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            [
                'title' => 'Atlas Starter Trial',
                'image_url' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=1600&q=80',
                'link_url' => '/gyms?trial_available=1',
                'sort_order' => 10,
            ],
            [
                'title' => 'Strength Training Week',
                'image_url' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1600&q=80',
                'link_url' => '/gyms?verified_only=1',
                'sort_order' => 20,
            ],
            [
                'title' => 'Premium Coaching Access',
                'image_url' => 'https://images.unsplash.com/photo-1540497077202-7c8a3999166f?auto=format&fit=crop&w=1600&q=80',
                'link_url' => '/for-gyms',
                'sort_order' => 30,
            ],
        ];

        foreach ($banners as $banner) {
            PlatformBanner::query()->updateOrCreate(
                ['title' => $banner['title']],
                [
                    'image_url' => $banner['image_url'],
                    'link_url' => $banner['link_url'],
                    'is_active' => true,
                    'sort_order' => $banner['sort_order'],
                ],
            );
        }
    }
}
