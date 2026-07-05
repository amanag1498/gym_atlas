<?php

namespace App\Services\Gym;

use App\Models\Gym;
use App\Services\Media\GymImageService;
use App\Support\Scheduling\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GymProfileManagementService
{
    public function __construct(
        private readonly GymImageService $gymImageService,
    ) {
    }

    public function updateProfile(Request $request, Gym $gym, array $data): Gym
    {
        $gym->loadMissing('gymPhotos');
        $facilityIds = Arr::get($data, 'facility_ids');
        $payload = $this->buildProfilePayload($request, $gym, $data);

        $gym->update($payload);
        $this->gymImageService->syncGymMediaRecords($gym);

        if (is_array($facilityIds)) {
            $gym->facilities()->sync($facilityIds);
        }

        return $gym->fresh(['facilities', 'owner', 'branches.facilities', 'cityRecord', 'gymPhotos']);
    }

    /**
     * @return array{gym: Gym, forced_private: bool}
     */
    public function updatePublicListingSettings(Gym $gym, array $data): array
    {
        $showPricing = Arr::exists($data, 'show_pricing')
            ? (bool) $data['show_pricing']
            : (Arr::exists($data, 'pricing_visible') ? (bool) $data['pricing_visible'] : (bool) $gym->show_pricing);

        $payload = [
            'show_pricing' => $showPricing,
            'pricing_visible' => $showPricing,
            'trial_available' => Arr::exists($data, 'trial_available') ? (bool) $data['trial_available'] : (bool) $gym->trial_available,
            'contact_visible' => Arr::exists($data, 'contact_visible') ? (bool) $data['contact_visible'] : (bool) $gym->contact_visible,
        ];

        $requestedPublic = Arr::exists($data, 'public_listing_enabled')
            ? (bool) $data['public_listing_enabled']
            : (bool) $gym->public_listing_enabled;

        $forcedPrivate = $requestedPublic && ! $this->canBePubliclyListed($gym);
        $payload['public_listing_enabled'] = $forcedPrivate ? false : $requestedPublic;

        $gym->update($payload);

        return [
            'gym' => $gym->fresh(['facilities', 'owner', 'branches.facilities', 'cityRecord']),
            'forced_private' => $forcedPrivate,
        ];
    }

    public function canBePubliclyListed(Gym $gym): bool
    {
        return $gym->is_active
            && $gym->status === 'active'
            && ($gym->approval_status === 'approved' || $gym->approval_status === null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildProfilePayload(Request $request, Gym $gym, array $data): array
    {
        $removeLogo = (bool) Arr::get($data, 'remove_logo', false);
        $removeCover = (bool) Arr::get($data, 'remove_cover_image', false);

        if ($removeLogo && ! $request->hasFile('logo')) {
            $this->gymImageService->deleteManagedImage($gym->logo);
            $logoMedia = [
                'path' => null,
                'url' => null,
            ];
        } else {
            $logoMedia = $this->gymImageService->storeSingle($request->file('logo'), 'gyms/logos', $gym->logo, $gym->logo_url, [
                'max_width' => 720,
                'max_height' => 720,
                'thumb_width' => 240,
                'thumb_height' => 240,
                'thumb_mode' => 'fit',
            ]);
        }

        if ($removeCover && ! $request->hasFile('cover_image')) {
            $this->gymImageService->deleteManagedImage($gym->cover_image);
            $coverMedia = [
                'path' => null,
                'url' => null,
            ];
        } else {
            $coverMedia = $this->gymImageService->storeSingle($request->file('cover_image'), 'gyms/covers', $gym->cover_image, $gym->cover_image_url, [
                'max_width' => 1800,
                'max_height' => 1200,
                'thumb_width' => 640,
                'thumb_height' => 360,
                'thumb_mode' => 'crop',
            ]);
        }

        $galleryUrls = $this->gymImageService->storeGallery($request->file('gallery_images', []), 'gyms/gallery', [
            'max_width' => 1600,
            'max_height' => 1600,
            'thumb_width' => 640,
            'thumb_height' => 480,
            'thumb_mode' => 'crop',
        ])->pluck('url');
        $removeGalleryPhotoIds = collect(Arr::get($data, 'remove_gallery_photo_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $existingGalleryUrls = $gym->gymPhotos
            ->whereNull('branch_id')
            ->where('type', 'gallery')
            ->reject(fn ($photo) => $removeGalleryPhotoIds->contains((int) $photo->id))
            ->sortBy('sort_order')
            ->pluck('image_url');

        if ($existingGalleryUrls->isEmpty() && ! Arr::exists($data, 'photo_urls')) {
            $existingGalleryUrls = collect($gym->photo_urls ?? []);
        }

        $name = trim((string) Arr::get($data, 'name', $gym->name));
        $showPricing = Arr::exists($data, 'show_pricing')
            ? (bool) $data['show_pricing']
            : (Arr::exists($data, 'pricing_visible') ? (bool) $data['pricing_visible'] : (bool) $gym->show_pricing);

        $address = Arr::get($data, 'address', Arr::get($data, 'address_line', $gym->address));
        $addressLine = Arr::get($data, 'address_line', Arr::get($data, 'address', $gym->address_line));
        $openingTime = Arr::get($data, 'opening_time', $gym->opening_time);
        $closingTime = Arr::get($data, 'closing_time', $gym->closing_time);
        $timings = Arr::get($data, 'timings');
        $photoUrls = collect(Arr::exists($data, 'photo_urls') ? Arr::get($data, 'photo_urls', []) : $existingGalleryUrls)
            ->concat($galleryUrls)
            ->filter(fn (mixed $url): bool => filled($url))
            ->map(fn (mixed $url): string => trim((string) $url))
            ->unique()
            ->take(10)
            ->values()
            ->all();

        $hoursPayload = $this->buildHoursPayload(
            is_array($timings) ? $timings : ($gym->timings ?? []),
            Arr::get($data, 'weekly_off', $gym->weekly_off ?? []),
            $openingTime,
            $closingTime,
        );

        $payload = [
            'name' => $name,
            'slug' => $this->resolveSlug($gym, $name),
            'description' => Arr::get($data, 'description', $gym->description),
            'logo' => $logoMedia['path'],
            'logo_url' => $removeLogo && ! $request->hasFile('logo')
                ? null
                : ($request->hasFile('logo')
                ? $logoMedia['url']
                : (filled(Arr::get($data, 'logo_url')) ? Arr::get($data, 'logo_url') : $logoMedia['url'])),
            'cover_image' => $coverMedia['path'],
            'cover_image_url' => $removeCover && ! $request->hasFile('cover_image')
                ? null
                : ($request->hasFile('cover_image')
                ? $coverMedia['url']
                : (filled(Arr::get($data, 'cover_image_url')) ? Arr::get($data, 'cover_image_url') : $coverMedia['url'])),
            'photo_urls' => $photoUrls,
            'address' => $address,
            'address_line' => $addressLine,
            'contact_number' => Arr::get($data, 'contact_number', $gym->contact_number),
            'instagram_profile' => Arr::get($data, 'instagram_profile', $gym->instagram_profile),
            'city_id' => Arr::get($data, 'city_id', $gym->city_id),
            'city' => Arr::get($data, 'city', $gym->city),
            'state' => Arr::get($data, 'state', $gym->state),
            'country' => Arr::get($data, 'country', $gym->country),
            'pincode' => Arr::get($data, 'pincode', $gym->pincode),
            'latitude' => Arr::get($data, 'latitude', $gym->latitude),
            'longitude' => Arr::get($data, 'longitude', $gym->longitude),
            'timezone' => Arr::get($data, 'timezone', $gym->timezone ?: config('app.timezone')),
            ...$hoursPayload,
            'show_pricing' => $showPricing,
            'pricing_visible' => $showPricing,
            'trial_available' => Arr::exists($data, 'trial_available') ? (bool) $data['trial_available'] : (bool) $gym->trial_available,
            'contact_visible' => Arr::exists($data, 'contact_visible') ? (bool) $data['contact_visible'] : (bool) $gym->contact_visible,
        ];

        if (Arr::exists($data, 'public_listing_enabled')) {
            $requestedPublic = (bool) $data['public_listing_enabled'];
            $payload['public_listing_enabled'] = $requestedPublic && $this->canBePubliclyListed($gym);
        }

        return $payload;
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

    private function resolveSlug(Gym $gym, string $name): string
    {
        if ($gym->name === $name && $gym->slug) {
            return $gym->slug;
        }

        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'gym';
        $slug = $base;
        $suffix = 2;

        while (Gym::query()
            ->where('id', '!=', $gym->id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
