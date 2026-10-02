<?php

namespace App\Services\Gym;

use App\Models\Branch;
use App\Models\Gym;
use App\Services\Media\GymImageService;
use App\Support\Scheduling\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchManagementService
{
    public function __construct(private readonly GymImageService $gymImageService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Request $request, Gym $gym, array $data): Branch
    {
        $data['photo_urls'] = $this->photoUrls($request, $data);
        $payload = $this->buildPayload($gym, $data);
        $facilityIds = Arr::get($data, 'facility_ids', []);

        $branch = Branch::query()->create($payload);
        $branch->facilities()->sync($facilityIds);
        $this->gymImageService->syncBranchMediaRecords($branch);

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
    public function update(Branch $branch, array $data, ?Request $request = null): Branch
    {
        if ($request) {
            $data['photo_urls'] = $this->photoUrls($request, $data, $branch);
        }
        $payload = $this->buildPayload($branch->gym, $data, $branch);
        $facilityIds = Arr::get($data, 'facility_ids');

        $branch->update($payload);

        if (is_array($facilityIds)) {
            $branch->facilities()->sync($facilityIds);
        }

        $this->gymImageService->syncBranchMediaRecords($branch);

        return $branch->fresh(['facilities', 'cityRecord'])
            ->loadCount([
                'memberProfiles',
                'trainerProfiles',
                'attendanceLogs as today_check_ins_count' => fn ($query) => $query->whereDate('checked_in_at', today()),
            ]);
    }

    /** @param array<string, mixed> $data
     * @return list<string>
     */
    private function photoUrls(Request $request, array $data, ?Branch $branch = null): array
    {
        $existing = collect(Arr::get($data, 'photo_urls', $branch?->photo_urls ?? []));
        $removedIndexes = collect(Arr::get($data, 'remove_photo_indexes', []))->map(fn ($index): int => (int) $index);
        $kept = $existing->reject(fn ($url, $index): bool => $removedIndexes->contains($index))->values();
        $files = collect($request->file('gallery_images', []));

        if ($kept->count() + $files->count() > 10) {
            throw ValidationException::withMessages(['gallery_images' => 'A branch can have up to 10 photos. Remove existing photos before adding more.']);
        }

        return $kept->concat($this->gymImageService->storeGallery($files, 'branches/gallery', [
            'max_width' => 1600,
            'max_height' => 1600,
            'thumb_width' => 640,
            'thumb_height' => 480,
            'thumb_mode' => 'crop',
        ])->pluck('url'))->unique()->values()->all();
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
