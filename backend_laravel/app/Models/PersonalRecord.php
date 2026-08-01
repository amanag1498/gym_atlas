<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'coaching_scope_key',
        'member_id',
        'exercise_id',
        'workout_session_id',
        'best_weight',
        'best_reps',
        'best_volume',
        'achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'best_weight' => 'decimal:2',
            'best_volume' => 'decimal:2',
            'achieved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PersonalRecord $record): void {
            $independentRelationshipId = null;
            if ($record->gym_id === null
                && preg_match('/^independent:(\d+)$/', (string) $record->coaching_scope_key, $matches) === 1) {
                $independentRelationshipId = (int) $matches[1];
            }

            $record->coaching_scope_key = self::coachingScopeKey(
                $record->gym_id !== null ? (int) $record->gym_id : null,
                $record->branch_id !== null ? (int) $record->branch_id : null,
                $independentRelationshipId,
            );
        });
    }

    public static function coachingScopeKey(?int $gymId, ?int $branchId, ?int $independentRelationshipId = null): string
    {
        return $gymId === null
            ? ($independentRelationshipId !== null ? 'independent:'.$independentRelationshipId : 'independent:self')
            : 'gym:'.$gymId.':branch:'.($branchId ?? 0);
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

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }
}
