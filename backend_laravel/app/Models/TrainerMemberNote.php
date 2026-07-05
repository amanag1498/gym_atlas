<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerMemberNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'member_id',
        'note',
        'visibility',
        'follow_up_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
