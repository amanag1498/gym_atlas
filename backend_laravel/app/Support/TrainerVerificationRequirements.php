<?php

namespace App\Support;

use App\Models\TrainerProfile;

final class TrainerVerificationRequirements
{
    /**
     * @return array<string, string>
     */
    public static function missing(TrainerProfile $profile): array
    {
        $missing = [];

        if (blank($profile->bio)) {
            $missing['bio'] = 'Add a professional bio before verification can be approved.';
        }
        if (collect($profile->specializations ?? [])->filter(fn ($value) => filled($value))->isEmpty()) {
            $missing['specializations'] = 'Add at least one specialization before verification can be approved.';
        }
        if ($profile->experience_years === null) {
            $missing['experience_years'] = 'Add your years of experience before verification can be approved.';
        }
        if (collect($profile->certifications ?? [])->filter(function ($certificate): bool {
            return is_array($certificate)
                ? filled($certificate['name'] ?? null) || filled($certificate['file_url'] ?? null)
                : filled($certificate);
        })->isEmpty()) {
            $missing['certifications'] = 'Add at least one certification or qualification before verification can be approved.';
        }

        return $missing;
    }
}
