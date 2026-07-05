<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSubscriptionPlan extends Model
{
    use HasFactory;

    public const BILLING_PERIODS = ['day', 'week', 'month', 'quarter', 'year'];

    public const STATUSES = ['draft', 'active', 'inactive'];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'billing_period',
        'billing_interval_count',
        'price',
        'setup_fee',
        'trial_days',
        'is_default',
        'sort_order',
        'included_services',
        'feature_highlights',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'trial_days' => 'integer',
            'billing_interval_count' => 'integer',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'included_services' => 'array',
            'feature_highlights' => 'array',
        ];
    }

    protected $appends = [
        'cadence_label',
        'price_label',
    ];

    public function gymSubscriptions(): HasMany
    {
        return $this->hasMany(GymPlatformSubscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(GymPlatformSubscriptionInvoice::class, 'platform_subscription_plan_id');
    }

    public function getCadenceLabelAttribute(): string
    {
        return match ($this->billing_period) {
            'day' => $this->billing_interval_count === 1 ? 'Daily' : 'Every '.$this->billing_interval_count.' days',
            'week' => $this->billing_interval_count === 1 ? 'Weekly' : 'Every '.$this->billing_interval_count.' weeks',
            'month' => $this->billing_interval_count === 1 ? 'Monthly' : 'Every '.$this->billing_interval_count.' months',
            'quarter' => $this->billing_interval_count === 1 ? 'Quarterly' : 'Every '.$this->billing_interval_count.' quarters',
            'year' => $this->billing_interval_count === 1 ? 'Yearly' : 'Every '.$this->billing_interval_count.' years',
            default => 'Custom',
        };
    }

    public function getPriceLabelAttribute(): string
    {
        if ((float) $this->price <= 0) {
            return 'Free';
        }

        $suffix = match ($this->billing_period) {
            'day' => '/ day',
            'week' => '/ week',
            'month' => '/ month',
            'quarter' => '/ quarter',
            'year' => '/ year',
            default => '',
        };

        return '₹'.number_format((float) $this->price, 0).$suffix;
    }
}
