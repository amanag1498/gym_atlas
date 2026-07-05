<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymPlatformSubscriptionInvoice extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'due', 'paid', 'overdue', 'void'];

    protected $fillable = [
        'gym_platform_subscription_id',
        'gym_id',
        'platform_subscription_plan_id',
        'generated_by_user_id',
        'paid_by_user_id',
        'invoice_number',
        'status',
        'currency',
        'period_starts_at',
        'period_ends_at',
        'issued_at',
        'due_at',
        'paid_at',
        'subtotal_amount',
        'setup_fee_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'payment_reference',
        'payment_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_starts_at' => 'date',
            'period_ends_at' => 'date',
            'issued_at' => 'datetime',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'subtotal_amount' => 'decimal:2',
            'setup_fee_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(GymPlatformSubscription::class, 'gym_platform_subscription_id');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscriptionPlan::class, 'platform_subscription_plan_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
