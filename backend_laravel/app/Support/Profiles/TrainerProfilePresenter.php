<?php

namespace App\Support\Profiles;

use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Str;

class TrainerProfilePresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function present(?TrainerProfile $profile, ?User $user = null, array $options = []): ?array
    {
        if (! $profile) {
            return null;
        }

        $user ??= $profile->relationLoaded('user') ? $profile->user : null;
        $specializations = self::normalizedList($profile->specializations ?? []);
        $certifications = self::normalizedList($profile->certifications ?? []);
        $languages = self::normalizedList($profile->languages ?? []);
        $availabilitySlots = self::availabilitySlots($profile->availability_notes);
        $programsOffered = self::programsOffered($specializations);
        $clientCount = self::clientCount($profile, (bool) ($options['include_client_count'] ?? true));

        return [
            'id' => $profile->user_id,
            'trainer_profile_id' => $profile->id,
            'name' => $user?->name,
            'email' => $options['public'] ?? false ? null : $user?->email,
            'photo' => $user?->avatar,
            'profile_photo_url' => $profile->profile_photo_url ?: $user?->avatar,
            'bio' => $profile->bio,
            'primary_specialization' => $specializations[0] ?? null,
            'specializations' => $specializations,
            'experience_years' => (int) ($profile->experience_years ?? 0),
            'experience_label' => (int) ($profile->experience_years ?? 0).' yrs experience',
            'certifications' => $certifications,
            'languages' => $languages,
            'availability_notes' => $profile->availability_notes,
            'availability_slots' => $availabilitySlots,
            'assigned_gym' => $profile->gym ? [
                'id' => $profile->gym->id,
                'name' => $profile->gym->name,
                'slug' => $profile->gym->slug,
            ] : null,
            'assigned_branch' => $profile->branch ? [
                'id' => $profile->branch->id,
                'name' => $profile->branch->name,
                'slug' => $profile->branch->slug,
                'city' => $profile->branch->city,
            ] : null,
            'client_count' => $clientCount,
            'client_count_placeholder' => [
                'value' => $clientCount,
                'label' => $clientCount === null ? 'Client count sync pending' : $clientCount.' active members',
                'is_placeholder' => $clientCount === null,
            ],
            'rating_placeholder' => [
                'value' => null,
                'label' => 'Rating coming soon',
                'is_placeholder' => true,
            ],
            'transformation_photos_placeholder' => [
                'enabled' => false,
                'count' => 0,
                'message' => 'Transformation highlights will be published in a later phase.',
            ],
            'programs_offered_placeholder' => [
                'items' => $programsOffered,
                'message' => $programsOffered === []
                    ? 'Programs offered will be published once this trainer profile is fully configured.'
                    : 'Programs are derived from current specialization tags for now.',
            ],
            'profile_completion_percentage' => self::completionPercentage($profile, $specializations, $certifications, $languages, $availabilitySlots),
            'verification_status' => $profile->verification_status,
            'is_active' => (bool) $profile->is_active,
            'contact_action' => [
                'mode' => (string) ($options['contact_mode'] ?? 'message'),
                'enabled' => (bool) ($options['contact_enabled'] ?? false),
                'label' => (string) ($options['contact_label'] ?? 'Message Trainer'),
            ],
            'request_session_action' => [
                'mode' => (string) ($options['request_mode'] ?? 'session_request_placeholder'),
                'enabled' => (bool) ($options['request_enabled'] ?? false),
                'label' => (string) ($options['request_label'] ?? 'Request Session'),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function normalizedList(array $items): array
    {
        return collect($items)
            ->map(function ($item): string {
                if (is_array($item)) {
                    return trim((string) ($item['name'] ?? $item['title'] ?? ''));
                }

                return trim((string) $item);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function availabilitySlots(?string $availabilityNotes): array
    {
        if (! $availabilityNotes) {
            return [];
        }

        return collect(preg_split('/[\r\n,]+/', $availabilityNotes) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $specializations
     * @return list<string>
     */
    private static function programsOffered(array $specializations): array
    {
        return collect($specializations)
            ->take(4)
            ->map(function (string $specialization): string {
                $normalized = Str::of($specialization)->replace(['_', '-'], ' ')->trim()->title()->toString();

                return str_contains(strtolower($normalized), 'program')
                    ? $normalized
                    : $normalized.' Program';
            })
            ->values()
            ->all();
    }

    private static function completionPercentage(
        TrainerProfile $profile,
        array $specializations,
        array $certifications,
        array $languages,
        array $availabilitySlots,
    ): int {
        $fields = [
            ! empty($profile->profile_photo_url),
            ! empty($profile->bio),
            $specializations !== [],
            (int) ($profile->experience_years ?? 0) > 0,
            $certifications !== [],
            $languages !== [],
            $availabilitySlots !== [],
            $profile->branch_id !== null,
        ];

        return (int) round((collect($fields)->filter()->count() / count($fields)) * 100);
    }

    private static function clientCount(TrainerProfile $profile, bool $includeClientCount): ?int
    {
        if (! $includeClientCount) {
            return null;
        }

        if (array_key_exists('assigned_members_count', $profile->getAttributes())) {
            return (int) $profile->getAttribute('assigned_members_count');
        }

        if ($profile->relationLoaded('assignedMembers')) {
            return $profile->assignedMembers->count();
        }

        if (($profile->relationLoaded('user') && $profile->user?->relationLoaded('assignedMembers'))
            || ($profile->user && $profile->user->relationLoaded('assignedMembers'))) {
            return $profile->user->assignedMembers->count();
        }

        return null;
    }
}
