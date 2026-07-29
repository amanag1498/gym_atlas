<?php

use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('member_memberships')
            ->where('amount_paid', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($memberships): void {
                foreach ($memberships as $membership) {
                    $recordedAmount = (float) DB::table('payments')
                        ->where('member_membership_id', $membership->id)
                        ->where('status', 'recorded')
                        ->sum('amount');
                    $missingAmount = round((float) $membership->amount_paid - $recordedAmount, 2);

                    if ($missingAmount <= 0) {
                        continue;
                    }

                    $paidAt = $membership->created_at ?: now();
                    $paymentId = DB::table('payments')->insertGetId([
                        'gym_id' => $membership->gym_id,
                        'branch_id' => $membership->branch_id,
                        'member_membership_id' => $membership->id,
                        'member_id' => $membership->member_id,
                        'received_by_user_id' => $membership->approved_by_admin_id,
                        'collected_by' => $membership->approved_by_admin_id,
                        'amount' => $missingAmount,
                        'payment_mode' => 'cash',
                        'status' => 'recorded',
                        'payment_status' => 'paid',
                        'external_reference' => 'ENROLLMENT-BACKFILL-'.$membership->id,
                        'notes' => 'Backfilled from the paid amount captured during enrollment.',
                        'paid_at' => $paidAt,
                        'payment_date' => $paidAt,
                        'created_at' => $paidAt,
                        'updated_at' => now(),
                    ]);

                    $receiptNumber = sprintf('RCT-%06d', $paymentId);
                    DB::table('payments')->where('id', $paymentId)->update([
                        'receipt_number' => $receiptNumber,
                    ]);
                    DB::table('payment_receipts')->insert([
                        'payment_id' => $paymentId,
                        'receipt_number' => $receiptNumber,
                        'status' => 'pending_generation',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('gym_ledger_entries')->updateOrInsert(
                        [
                            'source_type' => Payment::class,
                            'source_id' => $paymentId,
                        ],
                        [
                            'gym_id' => $membership->gym_id,
                            'branch_id' => $membership->branch_id,
                            'created_by_user_id' => $membership->approved_by_admin_id,
                            'entry_type' => 'membership_collection',
                            'direction' => 'inflow',
                            'category' => 'member_payment',
                            'title' => 'Membership payment',
                            'description' => 'Backfilled from the paid amount captured during enrollment.',
                            'reference' => $receiptNumber,
                            'payment_mode' => 'cash',
                            'amount' => $missingAmount,
                            'status' => 'posted',
                            'occurred_at' => $paidAt,
                            'metadata' => json_encode([
                                'payment_status' => 'paid',
                                'member_id' => $membership->member_id,
                                'membership_id' => $membership->id,
                                'entry_origin' => 'enrollment_payment_backfill',
                            ], JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }

    public function down(): void
    {
        $paymentIds = DB::table('payments')
            ->where('external_reference', 'like', 'ENROLLMENT-BACKFILL-%')
            ->pluck('id');

        DB::table('gym_ledger_entries')
            ->where('source_type', Payment::class)
            ->whereIn('source_id', $paymentIds)
            ->delete();
        DB::table('payments')->whereIn('id', $paymentIds)->delete();
    }
};
