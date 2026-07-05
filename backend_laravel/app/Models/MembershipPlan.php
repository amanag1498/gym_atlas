<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    use HasFactory;

    public const BILLING_TYPES = ['free', 'paid'];

    public const BILLING_PERIODS = ['day', 'week', 'month', 'quarter', 'year', 'custom'];

    protected $fillable = [
        'gym_id',
        'branch_id',
        'name',
        'billing_type',
        'billing_period',
        'billing_interval_count',
        'duration_days',
        'plan_price',
        'joining_fee',
        'pt_included',
        'description',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'plan_price' => 'decimal:2',
            'joining_fee' => 'decimal:2',
            'pt_included' => 'boolean',
            'billing_interval_count' => 'integer',
        ];
    }

    protected $appends = [
        'duration_label',
        'cadence_label',
        'price_label',
    ];

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

    public function memberMemberships(): HasMany
    {
        return $this->hasMany(MemberMembership::class);
    }

    public function getDurationLabelAttribute(): string
    {
        return match ($this->billing_period) {
            'day' => $this->billing_interval_count.' day'.($this->billing_interval_count > 1 ? 's' : ''),
            'week' => $this->billing_interval_count.' week'.($this->billing_interval_count > 1 ? 's' : ''),
            'month' => $this->billing_interval_count.' month'.($this->billing_interval_count > 1 ? 's' : ''),
            'quarter' => $this->billing_interval_count.' quarter'.($this->billing_interval_count > 1 ? 's' : ''),
            'year' => $this->billing_interval_count.' year'.($this->billing_interval_count > 1 ? 's' : ''),
            default => $this->duration_days.' days',
        };
    }

    public function getCadenceLabelAttribute(): string
    {
        if ($this->billing_type === 'free') {
            return 'Free plan';
        }

        return match ($this->billing_period) {
            'month' => $this->billing_interval_count === 1 ? 'Monthly' : 'Every '.$this->billing_interval_count.' months',
            'quarter' => $this->billing_interval_count === 1 ? 'Quarterly' : 'Every '.$this->billing_interval_count.' quarters',
            'year' => $this->billing_interval_count === 1 ? 'Yearly' : 'Every '.$this->billing_interval_count.' years',
            'week' => $this->billing_interval_count === 1 ? 'Weekly' : 'Every '.$this->billing_interval_count.' weeks',
            'day' => $this->billing_interval_count === 1 ? 'Daily' : 'Every '.$this->billing_interval_count.' days',
            default => $this->duration_days.' day custom',
        };
    }

    public function getPriceLabelAttribute(): string
    {
        if ($this->billing_type === 'free' || (float) $this->plan_price <= 0) {
            return 'Free';
        }

        $suffix = match ($this->billing_period) {
            'month' => '/ month',
            'quarter' => '/ quarter',
            'year' => '/ year',
            'week' => '/ week',
            'day' => '/ day',
            default => '',
        };

        return '₹'.number_format((float) $this->plan_price, 0).$suffix;
    }
}
