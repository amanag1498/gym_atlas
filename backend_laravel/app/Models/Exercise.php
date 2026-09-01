<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'created_by_user_id',
        'name',
        'body_part',
        'muscle_group',
        'target_muscle',
        'secondary_muscles',
        'equipment',
        'difficulty',
        'movement_pattern',
        'default_tracking_mode',
        'is_bodyweight',
        'supports_external_load',
        'is_per_side',
        'instructions',
        'image_url',
        'video_url',
        'is_global',
        'status',
        'review_status',
        'reviewed_by_user_id',
        'reviewed_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'secondary_muscles' => 'array',
            'is_global' => 'boolean',
            'is_active' => 'boolean',
            'is_bodyweight' => 'boolean',
            'supports_external_load' => 'boolean',
            'is_per_side' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ExerciseSource::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ExerciseTranslation::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ExerciseAlias::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ExerciseMedia::class);
    }

    public function previewMedia(): HasOne
    {
        return $this->hasOne(ExerciseMedia::class)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeSearchCatalog(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        $like = '%'.$search.'%';

        return $query->where(function (Builder $builder) use ($like): void {
            $builder->where('name', 'like', $like)
                ->orWhere('body_part', 'like', $like)
                ->orWhere('muscle_group', 'like', $like)
                ->orWhere('target_muscle', 'like', $like)
                ->orWhere('equipment', 'like', $like)
                ->orWhereHas('aliases', fn (Builder $aliases) => $aliases
                    ->where('review_status', 'approved')
                    ->where('alias', 'like', $like))
                ->orWhereHas('translations', fn (Builder $translations) => $translations
                    ->where('review_status', 'approved')
                    ->where('name', 'like', $like));
        });
    }

    public function scopeApplyCatalogFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['equipment'] ?? null, fn (Builder $builder, string $equipment) => $builder->where('equipment', $equipment))
            ->when($filters['target_muscle'] ?? null, fn (Builder $builder, string $muscle) => $builder->where('target_muscle', $muscle))
            ->when($filters['secondary_muscle'] ?? null, fn (Builder $builder, string $muscle) => $builder->whereJsonContains('secondary_muscles', $muscle))
            ->when($filters['difficulty'] ?? null, fn (Builder $builder, string $difficulty) => $builder->where('difficulty', $difficulty))
            ->when($filters['movement_pattern'] ?? null, fn (Builder $builder, string $pattern) => $builder->where('movement_pattern', $pattern))
            ->when($filters['tracking_mode'] ?? null, fn (Builder $builder, string $mode) => $builder->where('default_tracking_mode', $mode))
            ->when(array_key_exists('is_bodyweight', $filters), fn (Builder $builder) => $builder->where('is_bodyweight', (bool) $filters['is_bodyweight']));
    }

    public function favoriteMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'member_favorite_exercises')->withTimestamps();
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(ExerciseSubstitution::class);
    }

    public function templateExercises(): HasMany
    {
        return $this->hasMany(WorkoutTemplateExercise::class);
    }

    public function planExercises(): HasMany
    {
        return $this->hasMany(WorkoutPlanExercise::class);
    }

    public function sessionExercises(): HasMany
    {
        return $this->hasMany(WorkoutSessionExercise::class);
    }

    public function personalRecords(): HasMany
    {
        return $this->hasMany(PersonalRecord::class);
    }
}
