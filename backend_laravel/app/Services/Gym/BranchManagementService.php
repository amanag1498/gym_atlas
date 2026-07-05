<?php

namespace App\Services\Gym;

use App\Models\Branch;
use App\Models\Gym;
use App\Support\Scheduling\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BranchManagementService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Request $request, Gym $gym, array $data): Branch
    {
        $payload = $this->buildPayload($gym, $data);
        $facilityIds = Arr::get($data, 'facility_ids', []);

        $branch = Branch::query()->create($payload);
        $branch->facilities()->sync($facilityIds);

        return $branch->fresh(['facilities', 'cityRecord'])
            ->loadCount([
                'memberProfiles',
                'trainerProfiles',
                'attendanceLogs as today_check_ins_count' => fn ($query) => $query->whereDate('checked_in_at', today()),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data): Branch
    {
        $payload = $this->buildPayload($branch->gym, $data, $branch);
        $facilityIds = Arr::get($data, 'facility_ids');

        $branch->update($payload);

        if (is_array($facilityIds)) {
            $branch->facilities()->sync($facilityIds);
        }

        return $branch->fresh(['facilities', 'cityRecord'])
            ->loadCount([
                'memberProfiles',
                'trainerProfiles',
                'attendanceLogs as today_check_ins_count' => fn ($query) => $query->whereDate('checked_in_at', today()),
            ]);
    }

    public function toggleStatus(Branch $branch): Branch
    {
        $isActive = ! $branch->is_active;

        $branch->update([
            'is_active' => $isActive,
            'status' => $isActive ? 'active' : 'inactive',
        ]);

        return $branch->fresh(['facilities', 'cityRecord'])
            ->loadCount([
                'memberProfiles',
                'trainerProfiles',
                'attendanceLogs as today_check_ins_count' => fn ($query) => $query->whereDate('checked_in_at', today()),
            ]);
    }

    public function canDeleteSafely(Branch $branch): bool
    {
        return ! $branch->memberProfiles()
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhere('membership_status', 'active');
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPayload(Gym $gym, array $data, ?Branch $branch = null): array
    {
        $name = trim((string) Arr::get($data, 'name', $branch?->name));
        $openingTime = Arr::get($data, 'opening_time', $branch?->opening_time);
        $closingTime = Arr::get($data, 'closing_time', $branch?->closing_time);
        $timings = Arr::get($data, 'timings');
        $isActive = Arr::exists($data, 'is_active') ? (bool) $data['is_active'] : (bool) ($branch?->is_active ?? true);
        $slug = Arr::get($data, 'slug');
        $hoursPayload = $this->buildHoursPayload(
            is_array($timings) ? $timings : ($branch?->timings ?? []),
            Arr::get($data, 'weekly_off', $branch?->weekly_off ?? []),
            $openingTime,
            $closingTime,
        );

        return [
            'gym_id' => $gym->id,
            'city_id' => Arr::get($data, 'city_id', $branch?->city_id),
            'name' => $name,
            'slug' => filled($slug) ? (string) $slug : $this->resolveSlug($name, $branch?->id),
            'timezone' => Arr::get($data, 'timezone', $branch?->timezone ?: $gym->timezone ?: config('app.timezone')),
            'address' => Arr::get($data, 'address', Arr::get($data, 'address_line', $branch?->address)),
            'address_line' => Arr::get($data, 'address', Arr::get($data, 'address_line', $branch?->address_line)),
            'city' => Arr::get($data, 'city', $branch?->city),
            'state' => Arr::get($data, 'state', $branch?->state),
            'country' => Arr::get($data, 'country', $branch?->country ?: $gym->country ?: 'India'),
            'pincode' => Arr::get($data, 'pincode', $branch?->pincode),
            'latitude' => Arr::get($data, 'latitude', $branch?->latitude),
            'longitude' => Arr::get($data, 'longitude', $branch?->longitude),
            ...$hoursPayload,
            'photo_urls' => Arr::get($data, 'photo_urls', $branch?->photo_urls ?? []),
            'is_active' => $isActive,
            'status' => $isActive ? 'active' : 'inactive',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $timings
     * @return array{opening_time: string|null, closing_time: string|null, timings: array<string, list<array{open: string, close: string}>>, weekly_off: list<string>}
     */
    private function buildHoursPayload(?array $timings, array $weeklyOff = [], ?string $openingTime = null, ?string $closingTime = null): array
    {
        $schedule = OperatingHours::normalize($timings, $weeklyOff);

        if (collect($schedule)->flatten(1)->isEmpty()) {
            $schedule = OperatingHours::buildFromFlat($openingTime, $closingTime, $weeklyOff);
        }

        $summary = OperatingHours::summarize($schedule);

        return [
            'opening_time' => $summary['opening_time'],
            'closing_time' => $summary['closing_time'],
            'timings' => $schedule,
            'weekly_off' => OperatingHours::weeklyOffFromTimings($schedule),
        ];
    }

    private function resolveSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'branch';
        $slug = $base;
        $suffix = 2;

        while (Branch::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
