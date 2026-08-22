<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymSelfEnrollmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_self_enrollment_link_id',
        'gym_id',
        'branch_id',
        'user_id',
        'submitted_name',
        'submitted_email',
        'submitted_phone',
        'outcome',
        'source',
        'payload',
        'request_fingerprint',
        'consented_at',
        'consent_version',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'consented_at' => 'datetime',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(GymSelfEnrollmentLink::class, 'gym_self_enrollment_link_id');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
