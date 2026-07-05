<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Builder;

class MemberMembership extends Model
{
    use HasFactory;

    public const CURRENT_STATUS_PRIORITY_SQL = "
        case
            when status = 'active' then 1
            when status = 'frozen' then 2
            when status = 'expired' then 3
            when status = 'cancelled' then 4
            else 5
        end
    ";

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'membership_plan_id',
        'start_date',
        'expiry_date',
        'status',
        'default_plan_price',
        'default_joining_fee',
        'custom_fee_enabled',
        'custom_fee_amount',
        'discount_type',
        'discount_amount',
        'custom_joining_fee',
        'joining_fee_waived',
        'partial_month_fee',
        'pt_custom_fee',
        'final_payable_amount',
        'amount_paid',
        'due_amount',
        'due_date',
        'payment_status',
        'custom_fee_reason',
        'approved_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expiry_date' => 'date',
            'default_plan_price' => 'decimal:2',
            'default_joining_fee' => 'decimal:2',
            'custom_fee_enabled' => 'boolean',
            'custom_fee_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'custom_joining_fee' => 'decimal:2',
            'joining_fee_waived' => 'boolean',
            'partial_month_fee' => 'decimal:2',
            'pt_custom_fee' => 'decimal:2',
            'final_payable_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'due_date' => 'date',
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function memberProfile(): BelongsTo
    {
        return $this->belongsTo(MemberProfile::class, 'member_id', 'user_id');
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function plan(): BelongsTo
    {
        return $this->membershipPlan();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentReceipts(): HasManyThrough
    {
        return $this->hasManyThrough(PaymentReceipt::class, Payment::class, 'member_membership_id', 'payment_id');
    }

    public function customFeeAuditLogs(): HasMany
    {
        return $this->hasMany(CustomFeeAuditLog::class);
    }

    public function latestCustomFeeAuditLog(): HasOne
    {
        return $this->hasOne(CustomFeeAuditLog::class)->latestOfMany('changed_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'member_membership_id');
    }

    public function scheduledReminders(): HasMany
    {
        return $this->hasMany(ScheduledReminder::class, 'member_membership_id');
    }

    public function workoutPlans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class, 'member_id', 'member_id');
    }

    public function scopeCurrentFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw(self::CURRENT_STATUS_PRIORITY_SQL)
            ->orderByDesc('expiry_date')
            ->orderByDesc('id');
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'frozen']);
    }

    public function scopeOverlappingCycle(Builder $query, string $startDate, string $expiryDate): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $expiryDate)
            ->whereDate('expiry_date', '>=', $startDate);
    }
}
