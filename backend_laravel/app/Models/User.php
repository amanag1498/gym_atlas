<?php

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'google_id',
        'firebase_uid',
        'avatar',
        'auth_provider',
        'is_active',
        'member_onboarding_completed',
        'member_onboarding_step',
        'trainer_onboarding_completed',
        'trainer_onboarding_step',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected string $guard_name = 'sanctum';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'member_onboarding_completed' => 'boolean',
            'member_onboarding_step' => 'integer',
            'trainer_onboarding_completed' => 'boolean',
            'trainer_onboarding_step' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    public function gyms(): BelongsToMany
    {
        return $this->belongsToMany(Gym::class)
            ->withPivot(['branch_id', 'role_name', 'custom_permissions', 'permissions', 'status', 'is_primary'])
            ->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot(['custom_permissions', 'is_primary'])
            ->withTimestamps();
    }

    public function ownedGyms(): HasMany
    {
        return $this->hasMany(Gym::class, 'owner_user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_user_id');
    }

    public function managedTrainerProfile(): HasOne
    {
        return $this->hasOne(TrainerProfile::class);
    }

    public function trainerProfile(): HasOne
    {
        return $this->managedTrainerProfile();
    }

    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function memberProfiles(): HasMany
    {
        return $this->hasMany(MemberProfile::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(GymStaff::class);
    }

    public function assignedMembers(): HasMany
    {
        return $this->hasMany(MemberProfile::class, 'assigned_trainer_user_id');
    }

    public function trainerMemberNotes(): HasMany
    {
        return $this->hasMany(TrainerMemberNote::class, 'trainer_id');
    }

    public function membershipPlansCreated(): HasMany
    {
        return $this->hasMany(MembershipPlan::class, 'created_by_user_id');
    }

    public function memberMemberships(): HasMany
    {
        return $this->hasMany(MemberMembership::class, 'member_id');
    }

    public function approvedMemberships(): HasMany
    {
        return $this->hasMany(MemberMembership::class, 'approved_by_admin_id');
    }

    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by_user_id');
    }

    public function ledgerEntriesCreated(): HasMany
    {
        return $this->hasMany(GymLedgerEntry::class, 'created_by_user_id');
    }

    public function customFeeAuditLogs(): HasMany
    {
        return $this->hasMany(CustomFeeAuditLog::class, 'changed_by');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'member_id');
    }

    public function attendanceCheckInsRecorded(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'checked_in_by');
    }

    public function announcementsCreated(): HasMany
    {
        return $this->hasMany(Announcement::class, 'created_by_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationsCreated(): HasMany
    {
        return $this->hasMany(Notification::class, 'created_by_user_id');
    }

    public function exercisesCreated(): HasMany
    {
        return $this->hasMany(Exercise::class, 'created_by_user_id');
    }

    public function workoutPlansAsMember(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class, 'member_id');
    }

    public function workoutPlansAsTrainer(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class, 'trainer_id');
    }

    public function workoutSessionsAsMember(): HasMany
    {
        return $this->hasMany(WorkoutSession::class, 'member_id');
    }

    public function workoutSessionsAsTrainer(): HasMany
    {
        return $this->hasMany(WorkoutSession::class, 'trainer_id');
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(WeightLog::class, 'member_id');
    }

    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class, 'member_id');
    }

    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class, 'member_id');
    }

    public function dailySteps(): HasMany
    {
        return $this->hasMany(MemberDailyStep::class, 'user_id');
    }

    public function personalRecords(): HasMany
    {
        return $this->hasMany(PersonalRecord::class, 'member_id');
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(UserFcmToken::class);
    }

    public function scheduledReminders(): HasMany
    {
        return $this->hasMany(ScheduledReminder::class);
    }

    public function trialRequests(): HasMany
    {
        return $this->hasMany(TrialRequest::class, 'member_id');
    }

    public function favoriteGyms(): BelongsToMany
    {
        return $this->belongsToMany(Gym::class, 'saved_gyms')
            ->withTimestamps();
    }

    public function assignedTrialRequests(): HasMany
    {
        return $this->hasMany(TrialRequest::class, 'assigned_trainer_id');
    }

    public function canUseRole(string|RoleName $role): bool
    {
        $roleName = $role instanceof RoleName ? $role->value : $role;

        return $this->hasRole($roleName);
    }

    public function hasAnySystemRole(array $roles): bool
    {
        return $this->hasAnyRole(array_map(
            fn (string|RoleName $role) => $role instanceof RoleName ? $role->value : $role,
            $roles,
        ));
    }
}
