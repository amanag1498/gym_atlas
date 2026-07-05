<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'city_id',
        'name',
        'slug',
        'timezone',
        'address_line',
        'address',
        'city',
        'state',
        'country',
        'pincode',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time',
        'timings',
        'weekly_off',
        'photo_urls',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'timings' => 'array',
            'opening_time' => 'string',
            'closing_time' => 'string',
            'weekly_off' => 'array',
            'photo_urls' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function cityRecord(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['custom_permissions', 'is_primary'])
            ->withTimestamps();
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'branch_facility')
            ->withTimestamps();
    }

    public function gymPhotos(): HasMany
    {
        return $this->hasMany(GymPhoto::class);
    }

    public function trainerProfiles(): HasMany
    {
        return $this->hasMany(TrainerProfile::class);
    }

    public function trainers(): HasMany
    {
        return $this->trainerProfiles();
    }

    public function memberProfiles(): HasMany
    {
        return $this->hasMany(MemberProfile::class);
    }

    public function members(): HasMany
    {
        return $this->memberProfiles();
    }

    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function memberMemberships(): HasMany
    {
        return $this->hasMany(MemberMembership::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(GymLedgerEntry::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    public function workoutTemplates(): HasMany
    {
        return $this->hasMany(WorkoutTemplate::class);
    }

    public function workoutPlans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class);
    }

    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(WeightLog::class);
    }

    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class);
    }

    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }

    public function personalRecords(): HasMany
    {
        return $this->hasMany(PersonalRecord::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function trialRequests(): HasMany
    {
        return $this->hasMany(TrialRequest::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(GymStaff::class);
    }
}
