<?php

namespace App\Models;

use App\Support\Media\StoredImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'image_path',
        'type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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

    public function getImageUrlAttribute(): ?string
    {
        return StoredImage::publicUrl($this->image_path, $this->image_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return StoredImage::thumbnailUrl($this->image_path, $this->image_path);
    }
}
