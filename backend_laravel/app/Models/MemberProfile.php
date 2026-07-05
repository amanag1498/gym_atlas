<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gym_id',
        'branch_id',
        'assigned_trainer_user_id',
        'assigned_trainer_id',
        'fitness_goal',
        'gender',
        'height_cm',
        'weight_kg',
        'experience_level',
        'medical_notes',
        'injury_notes',
        'emergency_contact_name',
        'emergency_contact_phone',
        'biometric_identifier',
        'biometric_enabled',
        'status',
        'membership_status',
        'membership_expires_on',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'membership_expires_on' => 'date',
            'status' => 'string',
            'is_active' => 'boolean',
            'biometric_enabled' => 'boolean',
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

    public function assignedTrainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_trainer_user_id');
    }

    public function fitnessGoals(): BelongsToMany
    {
        return $this->belongsToMany(FitnessGoal::class, 'fitness_goal_member_profile')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(MemberMembership::class, 'member_id', 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'member_id', 'user_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'member_id', 'user_id');
    }

    public function trainerNotes(): HasMany
    {
        return $this->hasMany(TrainerMemberNote::class, 'member_id', 'user_id');
    }

    public function workoutPlans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class, 'member_id', 'user_id');
    }

    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class, 'member_id', 'user_id');
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(WeightLog::class, 'member_id', 'user_id');
    }

    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class, 'member_id', 'user_id');
    }

    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class, 'member_id', 'user_id');
    }

    public function personalRecords(): HasMany
    {
        return $this->hasMany(PersonalRecord::class, 'member_id', 'user_id');
    }
}
