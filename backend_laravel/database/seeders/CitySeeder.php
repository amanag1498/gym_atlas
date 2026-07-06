<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'Bengaluru', 'state' => 'Karnataka', 'country' => 'India'],
            ['name' => 'Mumbai', 'state' => 'Maharashtra', 'country' => 'India'],
            ['name' => 'Delhi', 'state' => 'Delhi', 'country' => 'India'],
            ['name' => 'Gurugram', 'state' => 'Haryana', 'country' => 'India'],
            ['name' => 'Noida', 'state' => 'Uttar Pradesh', 'country' => 'India'],
            ['name' => 'Hyderabad', 'state' => 'Telangana', 'country' => 'India'],
            ['name' => 'Chennai', 'state' => 'Tamil Nadu', 'country' => 'India'],
            ['name' => 'Pune', 'state' => 'Maharashtra', 'country' => 'India'],
            ['name' => 'Jaipur', 'state' => 'Rajasthan', 'country' => 'India'],
            ['name' => 'Ahmedabad', 'state' => 'Gujarat', 'country' => 'India'],
        ];

        foreach ($cities as $city) {
            City::query()->updateOrCreate(
                [
                    'name' => $city['name'],
                    'state' => $city['state'],
                    'country' => $city['country'],
                ],
                ['is_active' => true],
            );
        }
    }
}
