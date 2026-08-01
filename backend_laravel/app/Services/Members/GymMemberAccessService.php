<?php

namespace App\Services\Members;

use App\Models\MemberMembership;
use App\Models\MemberProfile;
use Illuminate\Database\Eloquent\Builder;

class GymMemberAccessService
{
    public function scopeAccessibleProfiles(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('gym', fn (Builder $gym) => $gym
                ->where('is_active', true)
                ->where('status', 'active')
                ->where('operational_access_enabled', true))
            ->where(fn (Builder $status) => $status->where('status', 'active')->orWhereNull('status'))
            ->whereIn('membership_status', ['active', 'frozen'])
            ->where(function (Builder $expiry): void {
                $expiry->where('membership_status', 'frozen')
                    ->orWhereNull('membership_expires_on')
                    ->orWhereDate('membership_expires_on', '>=', now()->toDateString());
            })
            ->where(function (Builder $membershipGate): void {
                $membershipGate
                    ->whereDoesntHave('memberships', fn (Builder $membership) => $membership
                        ->whereColumn('member_memberships.gym_id', 'member_profiles.gym_id'))
                    ->orWhereHas('memberships', function (Builder $membership): void {
                        $membership->whereColumn('member_memberships.gym_id', 'member_profiles.gym_id')
                            ->whereDate('start_date', '<=', now()->toDateString());
                        $membership->where(function (Builder $operational): void {
                            $operational->where('status', 'frozen')
                                ->orWhere(function (Builder $active): void {
                                    $active->where('status', 'active')
                                        ->whereDate('expiry_date', '>=', now()->toDateString());
                                });
                        });
                    });
            });
    }

    public function isAccessible(MemberProfile $profile): bool
    {
        return $this->scopeAccessibleProfiles(MemberProfile::query()->whereKey($profile->id))->exists();
    }

    public function assertAccessible(MemberProfile $profile): void
    {
        abort_unless($this->isAccessible($profile), 404);
    }

    public function assertMembershipAccessible(MemberMembership $membership): void
    {
        $profile = MemberProfile::query()
            ->where('user_id', $membership->member_id)
            ->where('gym_id', $membership->gym_id)
            ->first();

        abort_unless($profile && $this->isAccessible($profile), 404);
    }
}
