<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EraseUserData extends Command
{
    protected $signature = 'users:erase
        {email : The exact Atlas account email}
        {--execute : Perform the irreversible deletion}
        {--yes : Skip the interactive confirmation (requires --execute)}';

    protected $description = 'Report or permanently erase one user and rows directly linked by foreign keys.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error("No user found for {$email}.");

            return self::FAILURE;
        }

        if ($user->hasAnySystemRole(['platform_admin'])) {
            $this->error('Refusing to erase a platform administrator.');

            return self::FAILURE;
        }

        $references = $this->userReferences();
        $this->info("User #{$user->id}: {$user->email}");

        $total = 0;
        foreach ($references as $reference) {
            $count = DB::table($reference->TABLE_NAME)
                ->where($reference->COLUMN_NAME, $user->id)
                ->count();

            if ($count > 0) {
                $total += $count;
                $this->line("{$reference->TABLE_NAME}.{$reference->COLUMN_NAME}: {$count}");
            }
        }

        $this->warn("Rows linked directly to this account: {$total}");

        if (! $this->option('execute')) {
            $this->comment('Dry run only. Re-run with --execute --yes after taking a verified backup.');

            return self::SUCCESS;
        }

        if (! $this->option('yes')) {
            $this->error('Deletion requires both --execute and --yes.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($references, $user): void {
            // Several tables are nested behind memberships/plans. Retry failed
            // deletes so children removed in an earlier pass unblock parents.
            for ($pass = 1; $pass <= 5; $pass++) {
                $deletedThisPass = 0;

                foreach ($references as $reference) {
                    try {
                        $deletedThisPass += DB::table($reference->TABLE_NAME)
                            ->where($reference->COLUMN_NAME, $user->id)
                            ->delete();
                    } catch (QueryException) {
                        // Try this table again after its dependent rows are removed.
                    }
                }

                if ($deletedThisPass === 0) {
                    break;
                }
            }

            $user->refresh();
            $user->forceDelete();
        });

        $this->info("Erasure completed for {$email}.");

        return self::SUCCESS;
    }

    /** @return array<int, object{TABLE_NAME:string, COLUMN_NAME:string}> */
    private function userReferences(): array
    {
        $database = DB::connection()->getDatabaseName();

        return DB::select(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = ?
               AND REFERENCED_TABLE_NAME = 'users'
               AND REFERENCED_COLUMN_NAME = 'id'
             ORDER BY TABLE_NAME",
            [$database],
        );
    }
}
