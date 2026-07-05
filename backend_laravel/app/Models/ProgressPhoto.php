<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'uploaded_by_user_id',
        'photo_url',
        'photo_type',
        'album_key',
        'captured_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
