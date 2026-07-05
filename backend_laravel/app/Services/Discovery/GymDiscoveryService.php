<?php

namespace App\Services\Discovery;

use App\Models\Gym;
use App\Support\Scheduling\OperatingHours;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GymDiscoveryService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $latitude = $this->nullableFloat($filters['latitude'] ?? null);
        $longitude = $this->nullableFloat($filters['longitude'] ?? null);

        $gyms = Gym::query()
            ->with([
                'facilities',
                'gymPhotos',
                'branches.facilities',
                'branches.gymPhotos',
                'branches.trainerProfiles.user',
                'trainerProfiles.user',
                'membershipPlans' => fn ($query) => $query->where('status', 'active')->orderBy('plan_price'),
            ])
            ->where('public_listing_enabled', true)
            ->where('public_listing_approval_status', 'approved')
            ->where(function ($query): void {
                $query->where('approval_status', 'approved')
                    ->orWhereNull('approval_status');
            })
            ->where('is_active', true)
            ->where('status', 'active')
            ->get()
            ->filter(fn (Gym $gym) => $this->matchesFilters($gym, $filters, $latitude, $longitude))
            ->values()
            ->map(function (Gym $gym) use ($latitude, $longitude): Gym {
                return $this->decorateGym($gym, $latitude, $longitude);
            });

        if ($latitude !== null && $longitude !== null) {
            $gyms = $gyms->sortBy(fn (Gym $gym) => $gym->getAttribute('distance_km') ?? INF)->values();
        }

        return $this->paginateCollection(
            $gyms,
            (int) ($filters['per_page'] ?? 15),
            (int) ($filters['page'] ?? 1),
        );
    }

    public function publicGymBySlug(string $slug, ?float $latitude = null, ?float $longitude = null): Gym
    {
        $gym = Gym::query()
            ->with([
                'facilities',
                'gymPhotos',
                'branches.facilities',
                'branches.gymPhotos',
                'branches.trainerProfiles.user',
                'trainerProfiles.user',
                'membershipPlans' => fn ($query) => $query->where('status', 'active')->orderBy('plan_price'),
            ])
            ->where('slug', $slug)
            ->where('public_listing_enabled', true)
            ->where('public_listing_approval_status', 'approved')
            ->where(function ($query): void {
                $query->where('approval_status', 'approved')
                    ->orWhereNull('approval_status');
            })
            ->where('is_active', true)
            ->where('status', 'active')
            ->firstOrFail();

        return $this->decorateGym($gym, $latitude, $longitude);
    }

    public function isOpenNow(?array $timings, ?string $timezone, array $weeklyOff = []): bool
    {
        $schedule = OperatingHours::normalize($timings, $weeklyOff);

        return OperatingHours::isOpenNow($schedule, $timezone);
    }

    private function decorateGym(Gym $gym, ?float $latitude = null, ?float $longitude = null): Gym
    {
        $plans = $gym->membershipPlans
            ->where('status', 'active')
            ->sortBy('plan_price')
            ->values();

        $pricingVisible = (bool) ($gym->show_pricing ?? $gym->pricing_visible);

        $feeSummary = $pricingVisible && $plans->isNotEmpty() ? [
            'min_price' => (float) $plans->min('plan_price'),
            'max_price' => (float) $plans->max('plan_price'),
            'plans_count' => $plans->count(),
        ] : null;

        $gym->setAttribute('is_open_now', $this->isOpenNow($gym->timings, $gym->timezone, $gym->weekly_off ?? []));
        $gym->setAttribute('personal_training_available', $plans->contains(fn ($plan) => (bool) $plan->pt_included)
            || $gym->trainerProfiles->where('is_active', true)->isNotEmpty());
        $gym->setAttribute('fee_summary', $feeSummary);

        if ($latitude !== null && $longitude !== null && $gym->latitude !== null && $gym->longitude !== null) {
            $gym->setAttribute('distance_km', round($this->distanceKm($latitude, $longitude, (float) $gym->latitude, (float) $gym->longitude), 2));
        }

        return $gym;
    }

    private function matchesFilters(Gym $gym, array $filters, ?float $latitude, ?float $longitude): bool
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $haystacks = [
                strtolower((string) $gym->name),
                strtolower((string) $gym->city),
                strtolower((string) ($gym->address ?: $gym->address_line)),
            ];

            $needle = strtolower($search);
            $matched = collect($haystacks)->contains(fn (string $value): bool => str_contains($value, $needle));

            if (! $matched) {
                return false;
            }
        }

        if (! empty($filters['city']) && strcasecmp((string) $gym->city, (string) $filters['city']) !== 0) {
            return false;
        }

        if (isset($filters['verified_only']) && filter_var($filters['verified_only'], FILTER_VALIDATE_BOOLEAN) && ! $gym->is_verified) {
            return false;
        }

        if (isset($filters['featured_only']) && filter_var($filters['featured_only'], FILTER_VALIDATE_BOOLEAN) && ! $gym->is_featured) {
            return false;
        }

        if (isset($filters['women_friendly']) && filter_var($filters['women_friendly'], FILTER_VALIDATE_BOOLEAN) && ! $gym->women_friendly) {
            return false;
        }

        if (isset($filters['women_only']) && filter_var($filters['women_only'], FILTER_VALIDATE_BOOLEAN) && ! $gym->women_only) {
            return false;
        }

        if (isset($filters['trial_available']) && filter_var($filters['trial_available'], FILTER_VALIDATE_BOOLEAN) && ! $gym->trial_available) {
            return false;
        }

        if (isset($filters['personal_training_available']) && filter_var($filters['personal_training_available'], FILTER_VALIDATE_BOOLEAN)) {
            $hasPt = $gym->membershipPlans->contains(fn ($plan) => $plan->status === 'active' && $plan->pt_included)
                || $gym->trainerProfiles->where('is_active', true)->isNotEmpty();

            if (! $hasPt) {
                return false;
            }
        }

        if (isset($filters['open_now']) && filter_var($filters['open_now'], FILTER_VALIDATE_BOOLEAN)) {
            $gymOpen = $this->isOpenNow($gym->timings, $gym->timezone, $gym->weekly_off ?? []);
            $branchOpen = $gym->branches->contains(fn ($branch) => $branch->is_active
                && $this->isOpenNow($branch->timings, $branch->timezone, $branch->weekly_off ?? []));

            if (! $gymOpen && ! $branchOpen) {
                return false;
            }
        }

        if (! empty($filters['facilities'])) {
            $required = collect($filters['facilities'])->map(fn ($value) => strtolower((string) $value))->values();
            $available = $gym->facilities->pluck('slug')
                ->merge($gym->branches->flatMap(fn ($branch) => $branch->facilities->pluck('slug')))
                ->map(fn ($value) => strtolower((string) $value))
                ->unique()
                ->values();

            if ($required->diff($available)->isNotEmpty()) {
                return false;
            }
        }

        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            if (! ($gym->show_pricing ?? $gym->pricing_visible)) {
                return false;
            }

            $minPrice = $this->nullableFloat($filters['min_price'] ?? null);
            $maxPrice = $this->nullableFloat($filters['max_price'] ?? null);
            $matchingPlan = $gym->membershipPlans->first(function ($plan) use ($minPrice, $maxPrice): bool {
                if ($plan->status !== 'active') {
                    return false;
                }

                $price = (float) $plan->plan_price;

                return ($minPrice === null || $price >= $minPrice)
                    && ($maxPrice === null || $price <= $maxPrice);
            });

            if (! $matchingPlan) {
                return false;
            }
        }

        if ($latitude !== null && $longitude !== null && isset($filters['distance'])) {
            if ($gym->latitude === null || $gym->longitude === null) {
                return false;
            }

            $distance = $this->distanceKm($latitude, $longitude, (float) $gym->latitude, (float) $gym->longitude);

            if ($distance > (float) $filters['distance']) {
                return false;
            }
        }

        return true;
    }

    private function paginateCollection(Collection $items, int $perPage, int $page): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
