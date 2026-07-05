<?php

namespace App\Services\Billing;

use App\Models\CustomFeeAuditLog;
use App\Models\MemberMembership;
use App\Models\User;

class CustomFeeAuditService
{
    public function log(MemberMembership $membership, User $actor, array $oldValues, array $newValues, string $reason): void
    {
        CustomFeeAuditLog::query()->create([
            'gym_id' => $membership->gym_id,
            'member_id' => $membership->member_id,
            'member_membership_id' => $membership->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_by' => $actor->id,
            'reason' => $reason,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
