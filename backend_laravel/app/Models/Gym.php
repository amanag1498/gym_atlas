<?php

namespace App\Models;

use App\Support\Media\StoredImage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Gym extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'city_id',
        'name',
        'description',
        'logo_url',
        'logo',
        'cover_image_url',
        'cover_image',
        'photo_urls',
        'slug',
        'timezone',
        'address_line',
        'address',
        'contact_number',
        'instagram_profile',
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
        'status',
        'is_active',
        'approval_status',
        'approval_notes',
        'rejected_reason',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'is_verified',
        'verified_by_user_id',
        'verified_at',
        'public_listing_enabled',
        'show_pricing',
        'public_listing_approval_status',
        'public_listing_approved_by_user_id',
        'public_listing_approved_at',
        'is_featured',
        'is_promoted',
        'featured_sort_order',
        'pricing_visible',
        'trial_available',
        'contact_visible',
        'gym_onboarding_completed',
        'prevent_duplicate_same_day_checkins',
        'women_friendly',
        'women_only',
    ];

    protected function casts(): array
    {
        return [
            'photo_urls' => 'array',
            'opening_time' => 'string',
            'closing_time' => 'string',
            'timings' => 'array',
            'weekly_off' => 'array',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'public_listing_enabled' => 'boolean',
            'show_pricing' => 'boolean',
            'public_listing_approved_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_promoted' => 'boolean',
            'featured_sort_order' => 'integer',
            'pricing_visible' => 'boolean',
            'trial_available' => 'boolean',
            'contact_visible' => 'boolean',
            'gym_onboarding_completed' => 'boolean',
            'prevent_duplicate_same_day_checkins' => 'boolean',
            'women_friendly' => 'boolean',
            'women_only' => 'boolean',
        ];
    }

    protected function contactNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? trim($value) : null,
        );
    }

    protected function instagramProfile(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $this->normalizeInstagramProfile($value),
        );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function cityRecord(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function gymPhotos(): HasMany
    {
        return $this->hasMany(GymPhoto::class);
    }

    public function getLogoThumbnailUrlAttribute(): ?string
    {
        return StoredImage::thumbnailUrl($this->logo, $this->logo_url);
    }

    public function getLogoUrlAttribute(?string $value): ?string
    {
        return StoredImage::publicUrl($this->logo, $value);
    }

    public function getCoverImageThumbnailUrlAttribute(): ?string
    {
        return StoredImage::thumbnailUrl($this->cover_image, $this->cover_image_url);
    }

    public function getCoverImageUrlAttribute(?string $value): ?string
    {
        return StoredImage::publicUrl($this->cover_image, $value);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['branch_id', 'role_name', 'custom_permissions', 'permissions', 'status', 'is_primary'])
            ->withTimestamps();
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_gyms')
            ->withTimestamps();
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_gym')
            ->withTimestamps();
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

    public function platformSubscriptions(): HasMany
    {
        return $this->hasMany(GymPlatformSubscription::class);
    }

    public function currentPlatformSubscription(): HasOne
    {
        return $this->hasOne(GymPlatformSubscription::class)->latestOfMany();
    }

    public function platformSubscriptionInvoices(): HasMany
    {
        return $this->hasMany(GymPlatformSubscriptionInvoice::class);
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

    private function normalizeInstagramProfile(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '@')) {
            $value = substr($value, 1);
        }

        if (preg_match('#^(?:https?://)?(?:www\.)?instagram\.com/([^/?#]+)#i', $value, $matches) === 1) {
            return 'https://instagram.com/'.trim($matches[1], '/');
        }

        if (preg_match('/^[A-Za-z0-9._]+$/', $value) === 1) {
            return 'https://instagram.com/'.$value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }
}
