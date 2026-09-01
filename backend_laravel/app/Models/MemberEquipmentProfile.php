<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberEquipmentProfile extends Model
{
    protected $fillable = ['user_id', 'name', 'preset_key', 'equipment', 'is_default'];

    protected function casts(): array
    {
        return ['equipment' => 'array', 'is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
