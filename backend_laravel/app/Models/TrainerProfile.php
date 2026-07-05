<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gym_id',
        'branch_id',
        'profile_photo_url',
        'bio',
        'specialization',
        'specializations',
        'experience_years',
        'certifications',
        'status',
        'languages',
        'availability_notes',
        'is_active',
        'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'profile_photo_url' => 'string',
            'specializations' => 'array',
            'certifications' => 'array',
            'languages' => 'array',
            'availability_notes' => 'string',
            'status' => 'string',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedMembers(): HasMany
    {
        return $this->hasMany(MemberProfile::class, 'assigned_trainer_user_id', 'user_id');
    }

    public function members(): HasMany
    {
        return $this->assignedMembers();
    }

    public function memberNotes(): HasMany
    {
        return $this->hasMany(TrainerMemberNote::class, 'trainer_id', 'user_id');
    }

    public function assignedTrialRequests(): HasMany
    {
        return $this->hasMany(TrialRequest::class, 'assigned_trainer_id', 'user_id');
    }

    public function workoutPlans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class, 'trainer_id', 'user_id');
    }

    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class, 'trainer_id', 'user_id');
    }
}
