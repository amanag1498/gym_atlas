<?php

namespace App\Http\Resources\Workout;

use App\Support\Workout\ExerciseBookCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bodyPart = $this->body_part ?: ExerciseBookCatalog::bodyPartForMuscleGroup($this->muscle_group);
        $locale = $this->requestedLocale($request);
        $translations = $this->relationLoaded('translations') ? $this->translations : collect();
        $translation = $translations
            ->first(fn ($item) => $item->locale === $locale && $item->review_status === 'approved');
        $english = $translations
            ->first(fn ($item) => $item->locale === 'en' && $item->review_status === 'approved');
        $localized = $translation ?: $english;

        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'localized_name' => $localized?->name ?: $this->name,
            'body_part' => $bodyPart,
            'body_part_label' => ExerciseBookCatalog::bodyPartLabel($bodyPart),
            'muscle_group' => $this->muscle_group,
            'target_muscle' => $this->target_muscle,
            'secondary_muscles' => $this->secondary_muscles ?? [],
            'equipment' => $this->equipment,
            'difficulty' => $this->difficulty,
            'instructions' => $localized?->instructions ?: $this->instructions,
            'instruction_steps' => $localized?->instruction_steps ?? [],
            'content_locale' => $localized?->locale ?? 'en',
            'movement_pattern' => $this->movement_pattern,
            'default_tracking_mode' => $this->default_tracking_mode,
            'is_bodyweight' => $this->is_bodyweight,
            'supports_external_load' => $this->supports_external_load,
            'is_per_side' => $this->is_per_side,
            'preview_media' => $this->previewMedia($this->relationLoaded('previewMedia') ? $this->previewMedia : null),
            'image_url' => $this->image_url,
            'video_url' => $this->video_url,
            'is_global' => $this->is_global,
            'status' => $this->status,
            'review_status' => $this->review_status,
            'translations_count' => $this->whenCounted('translations'),
            'media_count' => $this->whenCounted('media'),
            'sources' => $this->when($this->relationLoaded('sources'), fn () => $this->sources->map(fn ($source) => [
                'source_key' => $source->source_key,
                'source_external_id' => $source->source_external_id,
                'source_url' => $source->source_url,
                'source_commit' => $source->source_commit,
                'license_code' => $source->license_code,
                'last_synced_at' => $source->last_synced_at?->toIso8601String(),
            ])->values()),
            'is_active' => $this->is_active,
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function requestedLocale(Request $request): string
    {
        $locale = $request->string('locale')->trim()->lower()->toString();
        if ($locale === '') {
            $locale = strtolower(explode(',', $request->header('Accept-Language', 'en'))[0]);
        }

        return explode('-', str_replace('_', '-', $locale))[0];
    }

    private function previewMedia(mixed $media): ?array
    {
        if (! $media || $media->status !== 'active') {
            return null;
        }

        $url = $media->source_type === 'remote_url'
            ? $media->remote_url
            : ($media->storage_disk && $media->storage_path
                ? Storage::disk($media->storage_disk)->url($media->storage_path)
                : null);

        if (! $url) {
            return null;
        }

        return [
            'kind' => $media->kind,
            'source_type' => $media->source_type,
            'url' => $url,
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
            'duration_ms' => $media->duration_ms,
            'attribution_text' => $media->attribution_text,
        ];
    }
}
