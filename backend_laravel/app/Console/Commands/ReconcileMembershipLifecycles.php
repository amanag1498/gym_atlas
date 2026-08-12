<?php

namespace App\Console\Commands;

use App\Services\Billing\MembershipLifecycleReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class ReconcileMembershipLifecycles extends Command
{
    protected $signature = 'memberships:reconcile-lifecycle {--date= : Reconcile through this YYYY-MM-DD date}';

    protected $description = 'Expire elapsed memberships and synchronize member gym access safely.';

    public function handle(MembershipLifecycleReconciliationService $service): int
    {
        try {
            $dateOption = $this->option('date');
            $asOf = $dateOption
                ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $dateOption)
                : today()->toImmutable();
        } catch (Throwable) {
            $this->error('The --date option must use YYYY-MM-DD.');

            return self::FAILURE;
        }

        if (! $asOf || ($dateOption && $asOf->format('Y-m-d') !== $dateOption)) {
            $this->error('The --date option must use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $result = $service->reconcile($asOf);

        $this->info(sprintf(
            'Membership lifecycle reconciled: %d expired, %d profile activations, %d terminal access revocations.',
            $result['expired'],
            $result['profiles_activated'],
            $result['terminal_access_revocations'],
        ));

        return self::SUCCESS;
    }
}
