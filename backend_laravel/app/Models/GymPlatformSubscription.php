<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GymPlatformSubscription extends Model
{
    use HasFactory;

    public const STATUSES = ['trialing', 'active', 'past_due', 'cancelled', 'expired'];

    protected $fillable = [
        'gym_id',
        'platform_subscription_plan_id',
        'assigned_by_user_id',
        'status',
        'starts_at',
        'renews_at',
        'ends_at',
        'trial_ends_at',
        'billing_amount',
        'setup_fee_amount',
        'auto_renew',
        'included_services',
        'plan_snapshot',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'renews_at' => 'date',
            'ends_at' => 'date',
            'trial_ends_at' => 'date',
            'billing_amount' => 'decimal:2',
            'setup_fee_amount' => 'decimal:2',
            'auto_renew' => 'boolean',
            'included_services' => 'array',
            'plan_snapshot' => 'array',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscriptionPlan::class, 'platform_subscription_plan_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(GymPlatformSubscriptionInvoice::class);
    }

    public function latestInvoice(): HasOne
    {
        return $this->hasOne(GymPlatformSubscriptionInvoice::class)->latestOfMany();
    }
}
