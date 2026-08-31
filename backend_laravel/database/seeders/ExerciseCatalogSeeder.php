<?php

namespace Database\Seeders;

use App\Services\Workout\ExerciseCatalogImporter;
use Illuminate\Database\Seeder;
use RuntimeException;

class ExerciseCatalogSeeder extends Seeder
{
    public function run(ExerciseCatalogImporter $importer): void
    {
        $path = (string) config('exercise_catalog.dataset_path');
        $metadata = [
            'source_key' => config('exercise_catalog.source_key'),
            'source_url' => config('exercise_catalog.source_url'),
            'source_commit' => config('exercise_catalog.source_commit'),
            'license_code' => config('exercise_catalog.license_code'),
        ];
        $expected = (int) config('exercise_catalog.expected_count');
        $validation = $importer->importFile($path, $metadata);

        if ($expected > 0 && $validation['source_rows'] !== $expected) {
            throw new RuntimeException("Expected {$expected} exercise source rows, found {$validation['source_rows']}. Database was not changed.");
        }
        if ($validation['rejected_rows'] > 0) {
            throw new RuntimeException("Exercise dataset contains {$validation['rejected_rows']} rejected rows. Database was not changed.");
        }

        $report = $importer->importFile(
            $path,
            $metadata,
            apply: true,
            publish: (bool) config('exercise_catalog.publish'),
        );

        $this->command?->info(sprintf(
            'Exercise catalog seeded: %d accepted, %d new, %d updated, %d matched by name, %d translations; publish=%s.',
            $report['accepted_rows'],
            $report['new_exercises'],
            $report['updated_exercises'],
            $report['matched_existing_by_name'],
            $report['translations_upserted'],
            $report['published'] ? 'yes' : 'no',
        ));
    }
}
