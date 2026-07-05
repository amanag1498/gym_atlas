<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\CustomFeeAuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AuditTimelineService
{
    /**
     * @param  iterable<ActivityLog>  $logs
     * @return list<array<string, mixed>>
     */
    public function forActivityLogs(iterable $logs): array
    {
        $items = [];

        foreach ($logs as $log) {
            $items[] = [
                'title' => $this->titleForActivityLog($log),
                'change_summary' => $this->changeSummaryForActivityLog($log),
                'changed_by' => $log->actor?->name ?? 'System',
                'changed_by_role' => $this->formatRole($log->actor_role),
                'reason' => $this->reasonForActivityLog($log),
                'date' => $log->occurred_at?->format('j M Y, g:i A'),
                'occurred_at' => $log->occurred_at?->toIso8601String(),
                'event' => $log->event,
                'action' => $log->action,
                'icon' => $this->iconForActivityLog($log),
                'tone' => $this->toneForActivityLog($log),
                'amount_label' => $this->amountLabelForActivityLog($log),
                'amount_value' => $this->amountValueForActivityLog($log),
                'old_values' => $log->old_values ?? [],
                'new_values' => $log->new_values ?? [],
            ];
        }

        return $items;
    }

    /**
     * @param  iterable<CustomFeeAuditLog>  $logs
     * @return list<array<string, mixed>>
     */
    public function forCustomFeeAudits(iterable $logs): array
    {
        $items = [];

        foreach ($logs as $log) {
            $old = $log->old_values ?? [];
            $new = $log->new_values ?? [];

            $items[] = [
                'title' => empty($old) ? 'Custom fee applied' : 'Custom fee updated',
                'change_summary' => $this->moneyTransition(
                    $new['final_payable_amount'] ?? $new['custom_fee_amount'] ?? null,
                    $old['final_payable_amount'] ?? $old['custom_fee_amount'] ?? null,
                ),
                'changed_by' => $log->changer?->name ?? 'System',
                'changed_by_role' => $this->formatRole($log->changer?->active_role),
                'reason' => $log->reason,
                'date' => $log->changed_at?->format('j M Y, g:i A'),
                'occurred_at' => $log->changed_at?->toIso8601String(),
                'event' => 'membership.custom_fee.audit',
                'action' => 'update',
                'icon' => 'custom_fee',
                'tone' => 'accent',
                'amount_label' => 'Final payable',
                'amount_value' => $this->formatMoney($new['final_payable_amount'] ?? $new['custom_fee_amount'] ?? null),
                'old_values' => $old,
                'new_values' => $new,
            ];
        }

        return $items;
    }

    /**
     * @param  iterable<ActivityLog>  $activityLogs
     * @param  iterable<CustomFeeAuditLog>  $customFeeAudits
     * @return list<array<string, mixed>>
     */
    public function forMemberTimeline(iterable $activityLogs, iterable $customFeeAudits = []): array
    {
        $items = collect($this->forActivityLogs($activityLogs))
            ->merge($this->forCustomFeeAudits($customFeeAudits))
            ->sortByDesc('occurred_at')
            ->values()
            ->all();

        return $items;
    }

    public function formatRole(?string $role): string
    {
        return $role ? Str::of($role)->replace('_', ' ')->title()->toString() : 'System';
    }

    private function titleForActivityLog(ActivityLog $log): string
    {
        return match ($log->event) {
            'web.gym.membership.custom_fee.updated', 'membership.custom_fee.updated' => "Member fee changed",
            'web.gym.payment.recorded', 'payment.recorded' => 'Payment collected',
            'web.gym.payment.marked_paid', 'payment.marked_paid' => 'Membership marked paid',
            'web.gym.payment.marked_unpaid', 'payment.marked_unpaid' => 'Membership marked unpaid',
            'web.gym.payment.reversed', 'payment.reversed' => 'Payment reversed',
            'membership.created' => 'Membership assigned',
            'membership.renewed' => 'Membership renewed',
            'membership.frozen', 'web.gym.membership.frozen' => 'Membership frozen',
            'membership.reactivated', 'web.gym.membership.reactivated' => 'Membership reactivated',
            'membership.extended', 'web.gym.membership.extended' => 'Membership extended',
            'membership.cancelled', 'web.gym.membership.cancelled' => 'Membership cancelled',
            'web.gym.staff.created' => 'Staff member added',
            'web.gym.staff.updated' => 'Staff access updated',
            'web.gym.staff.status.updated' => 'Staff account status changed',
            'web.gym.staff.removed' => 'Staff member removed',
            'web.gym.member.created', 'gym.member.created' => 'Member created',
            'web.gym.member.updated', 'gym.member.updated' => $this->memberUpdateTitle($log),
            'web.gym.ledger.entry.created' => 'Ledger entry recorded',
            'web.gym.ledger.entry.reversed' => 'Ledger entry reversed',
            'web.gym.attendance.manual.created', 'attendance.manual.created' => 'Attendance marked',
            'attendance.biometric.created', 'web.gym.attendance.biometric.created' => 'Biometric attendance marked',
            'web.gym.attendance.correction.requested' => 'Attendance correction requested',
            'web.gym.attendance.correction.approved' => 'Attendance correction approved',
            'web.gym.attendance.correction.rejected' => 'Attendance correction rejected',
            'workout_plan.created' => 'Workout plan assigned',
            'progress.photo.created' => 'Progress photo uploaded',
            default => Str::of($log->event)->replace('.', ' ')->replace('_', ' ')->title()->toString(),
        };
    }

    private function memberUpdateTitle(ActivityLog $log): string
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        $oldProfile = is_array($old['member_profile'] ?? null) ? $old['member_profile'] : [];
        $newProfile = is_array($new['member_profile'] ?? null) ? $new['member_profile'] : [];

        if (($oldProfile['membership_status'] ?? null) !== ($newProfile['membership_status'] ?? null)) {
            return match (strtolower((string) ($newProfile['membership_status'] ?? ''))) {
                'frozen' => 'Membership frozen',
                'expired' => 'Membership expired',
                'cancelled' => 'Membership cancelled',
                default => 'Member status changed',
            };
        }

        if (($oldProfile['assigned_trainer_user_id'] ?? null) !== ($newProfile['assigned_trainer_user_id'] ?? null)) {
            return ($newProfile['assigned_trainer_user_id'] ?? null) ? 'Trainer assigned' : 'Trainer unassigned';
        }

        return 'Member profile updated';
    }

    private function changeSummaryForActivityLog(ActivityLog $log): string
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        return match ($log->event) {
            'web.gym.membership.custom_fee.updated', 'membership.custom_fee.updated' => $this->moneyTransition(
                $new['final_payable_amount'] ?? null,
                $old['final_payable_amount'] ?? null,
            ),
            'web.gym.payment.recorded', 'payment.recorded' => $this->formatMoney($new['amount'] ?? 0).' received via '.strtoupper((string) ($new['payment_mode'] ?? 'cash')),
            'web.gym.payment.marked_paid', 'payment.marked_paid' => 'Payment status moved to paid',
            'web.gym.payment.marked_unpaid', 'payment.marked_unpaid' => 'Payment status moved to unpaid',
            'web.gym.payment.reversed', 'payment.reversed' => 'Recorded payment was reversed and membership balance was recalculated',
            'membership.created' => $this->membershipSummary($new),
            'membership.renewed' => $this->membershipSummary($new),
            'membership.frozen', 'web.gym.membership.frozen' => 'Membership status moved to frozen',
            'membership.reactivated', 'web.gym.membership.reactivated' => 'Membership status moved back to active',
            'membership.extended', 'web.gym.membership.extended' => 'Expiry updated to '.($new['expiry_date'] ?? 'new date'),
            'membership.cancelled', 'web.gym.membership.cancelled' => 'Membership status moved to cancelled',
            'web.gym.staff.updated' => $this->staffChangeSummary($old, $new),
            'web.gym.staff.status.updated' => (($old['is_active'] ?? false) ? 'Active' : 'Inactive').' -> '.(($new['is_active'] ?? false) ? 'Active' : 'Inactive'),
            'web.gym.staff.removed' => 'Staff role, branch scope, and gym access were removed from this workspace',
            'web.gym.member.updated', 'gym.member.updated' => $this->memberChangeSummary($old, $new),
            'web.gym.ledger.entry.created' => ($new['title'] ?? 'Ledger entry').' for '.$this->formatMoney($new['amount'] ?? 0),
            'web.gym.ledger.entry.reversed' => ($new['title'] ?? 'Ledger entry').' was reversed',
            'web.gym.attendance.manual.created', 'attendance.manual.created' => 'Attendance was recorded manually by gym staff',
            'attendance.biometric.created', 'web.gym.attendance.biometric.created' => 'Attendance was recorded from the biometric desk scanner',
            'web.gym.attendance.correction.requested' => 'A check-in correction request was submitted for review',
            'web.gym.attendance.correction.approved' => 'A correction request was approved and the attendance ledger was updated',
            'web.gym.attendance.correction.rejected' => 'A correction request was rejected after review',
            'workout_plan.created' => $this->workoutPlanSummary($new),
            'progress.photo.created' => 'Member uploaded a new progress photo',
            default => 'Updated by backend audit trail',
        };
    }

    private function reasonForActivityLog(ActivityLog $log): string
    {
        return (string) ($log->context['reason']
            ?? $log->new_values['notes']
            ?? $log->old_values['notes']
            ?? 'No additional reason provided.');
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function memberChangeSummary(array $old, array $new): string
    {
        $oldProfile = is_array($old['member_profile'] ?? null) ? $old['member_profile'] : [];
        $newProfile = is_array($new['member_profile'] ?? null) ? $new['member_profile'] : [];

        if (($oldProfile['membership_status'] ?? null) !== ($newProfile['membership_status'] ?? null)) {
            return Str::title((string) ($oldProfile['membership_status'] ?? 'unknown')).' -> '.Str::title((string) ($newProfile['membership_status'] ?? 'unknown'));
        }

        if (($oldProfile['assigned_trainer_user_id'] ?? null) !== ($newProfile['assigned_trainer_user_id'] ?? null)) {
            return ($newProfile['assigned_trainer_user_id'] ?? null)
                ? 'Assigned trainer updated for this member'
                : 'Trainer assignment removed from this member';
        }

        return 'Member profile fields were updated';
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function staffChangeSummary(array $old, array $new): string
    {
        $oldRoles = collect($old['roles'] ?? [])->pluck('name')->filter()->values()->all();
        $newRoles = collect($new['roles'] ?? [])->pluck('name')->filter()->values()->all();

        if ($oldRoles !== $newRoles) {
            return $this->formatRole((string) ($oldRoles[0] ?? 'gym_staff')).' -> '.$this->formatRole((string) ($newRoles[0] ?? 'gym_staff'));
        }

        return 'Roles, branches, or custom permissions were updated';
    }

    private function moneyTransition(mixed $newValue, mixed $oldValue): string
    {
        return $this->formatMoney($oldValue).' -> '.$this->formatMoney($newValue);
    }

    /**
     * @param  array<string, mixed>  $membership
     */
    private function membershipSummary(array $membership): string
    {
        $plan = (string) ($membership['membership_plan']['name'] ?? $membership['membership_plan_name'] ?? 'Membership plan');
        $expiry = $membership['expiry_date'] ?? null;

        if ($expiry) {
            return $plan.' until '.date('j M Y', strtotime((string) $expiry));
        }

        return $plan.' assigned to the member';
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function workoutPlanSummary(array $values): string
    {
        $name = (string) ($values['name'] ?? 'Workout plan');
        $goal = (string) ($values['goal'] ?? '');

        return trim($name.($goal !== '' ? ' • '.$goal : ''));
    }

    private function iconForActivityLog(ActivityLog $log): string
    {
        return match ($log->event) {
            'web.gym.member.created', 'gym.member.created' => 'member_created',
            'membership.created' => 'membership_assigned',
            'membership.renewed' => 'membership_renewed',
            'web.gym.member.updated', 'gym.member.updated' => $this->memberUpdateIcon($log),
            'web.gym.payment.recorded', 'payment.recorded', 'web.gym.payment.marked_paid', 'payment.marked_paid', 'web.gym.payment.marked_unpaid', 'payment.marked_unpaid' => 'payment',
            'web.gym.attendance.manual.created', 'attendance.manual.created', 'attendance.biometric.created', 'web.gym.attendance.biometric.created', 'web.gym.attendance.correction.requested', 'web.gym.attendance.correction.approved', 'web.gym.attendance.correction.rejected' => 'attendance',
            'workout_plan.created' => 'workout_plan',
            'progress.photo.created' => 'progress_photo',
            default => 'activity',
        };
    }

    private function toneForActivityLog(ActivityLog $log): string
    {
        return match ($log->event) {
            'web.gym.payment.recorded', 'payment.recorded', 'web.gym.payment.marked_paid', 'payment.marked_paid' => 'success',
            'web.gym.membership.custom_fee.updated', 'membership.custom_fee.updated' => 'accent',
            'membership.renewed', 'membership.created', 'workout_plan.created', 'progress.photo.created' => 'info',
            'web.gym.member.updated', 'gym.member.updated' => $this->memberStatusTone($log),
            'web.gym.attendance.correction.approved' => 'success',
            'web.gym.attendance.correction.rejected' => 'danger',
            'web.gym.attendance.correction.requested' => 'warning',
            default => 'neutral',
        };
    }

    private function amountLabelForActivityLog(ActivityLog $log): ?string
    {
        return match ($log->event) {
            'web.gym.payment.recorded', 'payment.recorded', 'web.gym.payment.marked_paid', 'payment.marked_paid' => 'Amount',
            'web.gym.membership.custom_fee.updated', 'membership.custom_fee.updated', 'membership.created', 'membership.renewed' => 'Payable',
            default => null,
        };
    }

    private function amountValueForActivityLog(ActivityLog $log): ?string
    {
        $new = $log->new_values ?? [];

        return match ($log->event) {
            'web.gym.payment.recorded', 'payment.recorded', 'web.gym.payment.marked_paid', 'payment.marked_paid' => $this->formatMoney($new['amount'] ?? null),
            'web.gym.membership.custom_fee.updated', 'membership.custom_fee.updated', 'membership.created', 'membership.renewed' => $this->formatMoney($new['final_payable_amount'] ?? null),
            default => null,
        };
    }

    private function memberUpdateIcon(ActivityLog $log): string
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        $oldProfile = is_array($old['member_profile'] ?? null) ? $old['member_profile'] : [];
        $newProfile = is_array($new['member_profile'] ?? null) ? $new['member_profile'] : [];

        if (($oldProfile['membership_status'] ?? null) !== ($newProfile['membership_status'] ?? null)) {
            return 'membership_status';
        }

        if (($oldProfile['assigned_trainer_user_id'] ?? null) !== ($newProfile['assigned_trainer_user_id'] ?? null)) {
            return 'trainer_assigned';
        }

        return 'member_update';
    }

    private function memberStatusTone(ActivityLog $log): string
    {
        $new = $log->new_values ?? [];
        $newProfile = is_array($new['member_profile'] ?? null) ? $new['member_profile'] : [];
        $status = strtolower((string) ($newProfile['membership_status'] ?? ''));

        return match ($status) {
            'expired', 'cancelled' => 'danger',
            'frozen' => 'warning',
            default => 'info',
        };
    }

    private function formatMoney(mixed $value): string
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;

        return 'Rs '.number_format($amount, 2);
    }
}
