<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrialRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'request_type',
        'source',
        'name',
        'phone',
        'email',
        'preferred_date',
        'preferred_time',
        'status',
        'assigned_trainer_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
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

    public function assignedTrainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_trainer_id');
    }

    public function linkedMemberHasGymProfile(): bool
    {
        if (! $this->member_id) {
            return false;
        }

        $member = $this->relationLoaded('member') ? $this->member : null;
        if ($member?->relationLoaded('memberProfiles')) {
            return $member->memberProfiles->contains(
                fn (MemberProfile $profile) => (int) $profile->gym_id === (int) $this->gym_id
            );
        }

        return MemberProfile::query()
            ->where('user_id', $this->member_id)
            ->where('gym_id', $this->gym_id)
            ->exists();
    }

    public function canConvert(): bool
    {
        return in_array($this->status, ['accepted', 'completed'], true)
            && ! $this->linkedMemberHasGymProfile();
    }
}
