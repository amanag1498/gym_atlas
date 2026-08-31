<?php

namespace App\Console\Commands;

use App\Services\Workout\ExerciseCatalogImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportExerciseCatalog extends Command
{
    protected $signature = 'exercise-catalog:import
        {path : Path to exercises.json}
        {--source-key=hasaneyldrm_exercises_dataset}
        {--source-url=https://github.com/hasaneyldrm/exercises-dataset}
        {--source-commit=}
        {--license=MIT}
        {--expected-count= : Expected source-row count}
        {--strict-count : Fail when the source count differs from expected-count}
        {--apply : Persist the import; without this option the command is a dry run}
        {--publish : Publish imported rows immediately; requires --apply}';

    protected $description = 'Validate and import licensed exercise metadata while explicitly ignoring all source media fields.';

    public function handle(ExerciseCatalogImporter $importer): int
    {
        if ($this->option('publish') && ! $this->option('apply')) {
            $this->error('--publish requires --apply.');

            return self::INVALID;
        }

        $metadata = [
            'source_key' => $this->option('source-key'),
            'source_url' => $this->option('source-url'),
            'source_commit' => $this->option('source-commit'),
            'license_code' => $this->option('license'),
        ];
        $expected = $this->option('expected-count');
        $strictMismatch = false;

        try {
            if ($expected !== null && $this->option('strict-count')) {
                $report = $importer->importFile((string) $this->argument('path'), $metadata);
                $strictMismatch = (int) $expected !== $report['source_rows'];
            }

            if (! $strictMismatch) {
                $report = $importer->importFile(
                    (string) $this->argument('path'),
                    $metadata,
                    (bool) $this->option('apply'),
                    (bool) $this->option('publish'),
                );
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Value'], [
            ['Mode', $report['applied'] ? 'APPLY' : 'DRY RUN'],
            ['Importer version', $report['importer_version']],
            ['Source rows', $report['source_rows']],
            ['Accepted rows', $report['accepted_rows']],
            ['Rejected rows', $report['rejected_rows']],
            ['New exercises', $report['new_exercises']],
            ['Updated exercises', $report['updated_exercises']],
            ['Unchanged exercises', $report['unchanged_exercises']],
            ['Matched existing by name', $report['matched_existing_by_name']],
            ['Ambiguous existing names', count($report['ambiguous_existing_name_matches'])],
            ['Translations upserted', $report['translations_upserted']],
            ['Duplicate source IDs', count($report['duplicate_source_ids'])],
            ['Duplicate normalized names', count($report['duplicate_normalized_names'])],
            ['Rows missing required fields', count($report['missing_required_fields'])],
            ['Media references ignored', $report['media_references_ignored']],
            ['Source SHA-256', $report['source_checksum']],
        ]);

        if ($expected !== null && (int) $expected !== $report['source_rows']) {
            $message = "Expected {$expected} source rows, found {$report['source_rows']}.";
            if ($this->option('strict-count')) {
                $this->error($message);

                return self::FAILURE;
            }
            $this->warn($message);
        }

        if ($report['rejected_rows'] > 0) {
            $this->warn('Some rows were rejected. Review the import report before publishing.');
        }

        $this->info($report['applied']
            ? 'Exercise metadata import completed. Source media was not imported.'
            : 'Dry run completed. No database rows were changed.');

        return self::SUCCESS;
    }
}
