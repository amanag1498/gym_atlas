<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Serviceable Indian urban centres, based on the Government of India's
     * Smart Cities Mission city catalog, with additional major NCR markets.
     *
     * Source: https://smartcities.data.gov.in/cities
     * License: Government Open Data License - India (GODL-India)
     */
    public function run(): void
    {
        foreach ($this->citiesByState() as $state => $cities) {
            foreach ($cities as $name) {
                City::query()->updateOrCreate(
                    ['name' => $name, 'state' => $state, 'country' => 'India'],
                    ['is_active' => true],
                );
            }
        }
    }

    /** @return array<string, array<int, string>> */
    private function citiesByState(): array
    {
        return [
            'Andaman and Nicobar Islands' => ['Port Blair'],
            'Andhra Pradesh' => ['Amaravati', 'Kakinada', 'Tirupati', 'Visakhapatnam'],
            'Arunachal Pradesh' => ['Pasighat'],
            'Assam' => ['Guwahati'],
            'Bihar' => ['Bhagalpur', 'Bihar Sharif', 'Muzaffarpur', 'Patna'],
            'Chandigarh' => ['Chandigarh'],
            'Chhattisgarh' => ['Bilaspur', 'Nava Raipur', 'Raipur'],
            'Dadra and Nagar Haveli and Daman and Diu' => ['Diu', 'Silvassa'],
            'Delhi' => ['Delhi', 'New Delhi'],
            'Goa' => ['Panaji'],
            'Gujarat' => ['Ahmedabad', 'Dahod', 'Gandhinagar', 'Rajkot', 'Surat', 'Vadodara'],
            'Haryana' => ['Faridabad', 'Gurugram', 'Karnal'],
            'Himachal Pradesh' => ['Dharamshala', 'Shimla'],
            'Jammu and Kashmir' => ['Jammu', 'Srinagar'],
            'Jharkhand' => ['Ranchi'],
            'Karnataka' => ['Belagavi', 'Bengaluru', 'Davanagere', 'Hubballi-Dharwad', 'Mangaluru', 'Shivamogga', 'Tumakuru'],
            'Kerala' => ['Kochi', 'Thiruvananthapuram'],
            'Lakshadweep' => ['Kavaratti'],
            'Ladakh' => ['Kargil', 'Leh'],
            'Madhya Pradesh' => ['Bhopal', 'Gwalior', 'Indore', 'Jabalpur', 'Sagar', 'Satna', 'Ujjain'],
            'Maharashtra' => ['Chhatrapati Sambhajinagar', 'Kalyan-Dombivli', 'Mumbai', 'Nagpur', 'Nashik', 'Navi Mumbai', 'Pimpri-Chinchwad', 'Pune', 'Solapur', 'Thane'],
            'Manipur' => ['Imphal'],
            'Meghalaya' => ['Shillong'],
            'Mizoram' => ['Aizawl'],
            'Nagaland' => ['Kohima'],
            'Odisha' => ['Bhubaneswar', 'Rourkela'],
            'Puducherry' => ['Puducherry'],
            'Punjab' => ['Amritsar', 'Jalandhar', 'Ludhiana'],
            'Rajasthan' => ['Ajmer', 'Jaipur', 'Kota', 'Udaipur'],
            'Sikkim' => ['Gangtok', 'Namchi'],
            'Tamil Nadu' => ['Chennai', 'Coimbatore', 'Erode', 'Madurai', 'Salem', 'Thanjavur', 'Thoothukudi', 'Tiruchirappalli', 'Tirunelveli', 'Tiruppur', 'Vellore'],
            'Telangana' => ['Hyderabad', 'Karimnagar', 'Warangal'],
            'Tripura' => ['Agartala'],
            'Uttar Pradesh' => ['Agra', 'Aligarh', 'Bareilly', 'Ghaziabad', 'Greater Noida', 'Jhansi', 'Kanpur', 'Lucknow', 'Moradabad', 'Noida', 'Prayagraj', 'Saharanpur', 'Varanasi'],
            'Uttarakhand' => ['Dehradun'],
            'West Bengal' => ['Bidhannagar', 'Durgapur', 'Haldia', 'Kolkata', 'New Town Kolkata'],
        ];
    }
}
