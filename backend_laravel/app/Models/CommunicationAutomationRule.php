<?php

namespace App\Models;

use App\Support\CommunicationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationAutomationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id', 'branch_id', 'scope_key', 'notification_type', 'recipient_role', 'in_app_enabled',
        'whatsapp_enabled', 'whatsapp_template_id', 'is_enabled', 'configuration',
        'created_by_user_id', 'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean', 'whatsapp_enabled' => 'boolean',
            'is_enabled' => 'boolean', 'configuration' => 'array', 'last_triggered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $rule): void {
            $rule->scope_key ??= CommunicationScope::key($rule->gym_id, $rule->branch_id);
        });
    }

    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class);
    }
}
