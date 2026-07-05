<?php

use App\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $memberships = DB::table('member_memberships')->orderBy('id')->get();

            foreach ($memberships as $membership) {
                $updates = [];
                $dueDate = $membership->due_date ? Carbon::parse($membership->due_date) : null;
                $startDate = Carbon::parse($membership->start_date);
                $expiryDate = Carbon::parse($membership->expiry_date);

                if ($dueDate === null || ($dueDate->equalTo($startDate) && $expiryDate->gt($startDate))) {
                    $dueDate = $expiryDate->copy();
                    $updates['due_date'] = $dueDate->toDateString();
                }

                $hasPriorMembership = DB::table('member_memberships')
                    ->where('gym_id', $membership->gym_id)
                    ->where('member_id', $membership->member_id)
                    ->where('id', '<', $membership->id)
                    ->exists();

                if (
                    $hasPriorMembership
                    && ! (bool) $membership->joining_fee_waived
                    && (float) $membership->default_joining_fee > 0
                    && round((float) $membership->custom_joining_fee, 2) === round((float) $membership->default_joining_fee, 2)
                ) {
                    $finalPayable = round((float) $membership->final_payable_amount - (float) $membership->custom_joining_fee, 2);
                    $amountPaid = round((float) $membership->amount_paid, 2);
                    $dueAmount = round($finalPayable - $amountPaid, 2);

                    $paymentStatus = match (true) {
                        $dueAmount < 0 => PaymentStatus::Overpaid->value,
                        $dueAmount == 0.0 && $amountPaid > 0 => PaymentStatus::Paid->value,
                        $amountPaid <= 0 && $dueDate?->isPast() => PaymentStatus::Overdue->value,
                        $amountPaid > 0 => PaymentStatus::Partial->value,
                        default => PaymentStatus::Unpaid->value,
                    };

                    $updates['custom_joining_fee'] = 0;
                    $updates['joining_fee_waived'] = 1;
                    $updates['final_payable_amount'] = $finalPayable;
                    $updates['due_amount'] = $dueAmount;
                    $updates['payment_status'] = $paymentStatus;
                }

                if ($updates !== []) {
                    $updates['updated_at'] = now();

                    DB::table('member_memberships')
                        ->where('id', $membership->id)
                        ->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
    }
};
