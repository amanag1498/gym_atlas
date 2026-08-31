<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Exercise;
use App\Models\ExerciseMedia;
use App\Models\ExerciseSource;
use App\Models\ExerciseTranslation;
use App\Models\User;
use App\Services\Workout\ExerciseCatalogImporter;
use App\Support\Workout\ExerciseBookCatalog;
use Database\Seeders\ExerciseCatalogSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExerciseCatalogImportFeatureTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_import_is_dry_run_by_default_and_strict_count_prevents_writes(): void
    {
        $path = $this->dataset();

        $this->artisan('exercise-catalog:import', ['path' => $path])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('No database rows were changed')
            ->assertSuccessful();

        $this->assertDatabaseCount('exercises', 0);
        $this->assertDatabaseCount('exercise_import_batches', 0);

        $this->artisan('exercise-catalog:import', [
            'path' => $path,
            '--apply' => true,
            '--expected-count' => 1326,
            '--strict-count' => true,
        ])->expectsOutputToContain('Expected 1326 source rows, found 2.')
            ->assertFailed();

        $this->assertDatabaseCount('exercises', 0);
        $this->assertDatabaseCount('exercise_import_batches', 0);
    }

    public function test_import_is_idempotent_localized_and_never_persists_source_media(): void
    {
        $path = $this->dataset();

        $this->artisan('exercise-catalog:import', [
            'path' => $path,
            '--apply' => true,
            '--publish' => true,
            '--source-commit' => 'dataset-test-commit',
        ])->expectsOutputToContain('Source media was not imported')
            ->assertSuccessful();

        $this->assertDatabaseCount('exercises', 2);
        $this->assertDatabaseCount('exercise_sources', 2);
        $this->assertDatabaseCount('exercise_translations', 4);
        $this->assertDatabaseCount('exercise_media', 0);
        $this->assertDatabaseCount('exercise_import_batches', 1);

        $exercise = Exercise::query()->where('name', 'Test Sit-up')->firstOrFail();
        $exerciseId = $exercise->id;
        $this->assertSame('core', $exercise->body_part);
        $this->assertSame('core', $exercise->muscle_group);
        $this->assertSame('abs', $exercise->target_muscle);
        $this->assertTrue($exercise->is_bodyweight);
        $this->assertTrue($exercise->is_active);
        $this->assertSame('approved', $exercise->review_status);
        $this->assertNull($exercise->image_url);
        $this->assertNull($exercise->video_url);
        $cardio = Exercise::query()->where('name', 'Test Treadmill')->firstOrFail();
        $this->assertSame('conditioning', $cardio->body_part);
        $this->assertSame('cardio', $cardio->default_tracking_mode);

        $source = ExerciseSource::query()->where('exercise_id', $exerciseId)->firstOrFail();
        $this->assertSame('0001', $source->source_external_id);
        $this->assertSame('MIT', $source->license_code);
        $this->assertSame('dataset-test-commit', $source->source_commit);

        $this->writeDataset($path, [
            ...$this->records(),
        ], 'Test Sit-up Updated');

        $this->artisan('exercise-catalog:import', [
            'path' => $path,
            '--apply' => true,
            '--publish' => true,
            '--source-commit' => 'dataset-test-commit-2',
        ])->assertSuccessful();

        $this->assertDatabaseCount('exercises', 2);
        $this->assertDatabaseCount('exercise_sources', 2);
        $this->assertDatabaseCount('exercise_translations', 4);
        $this->assertDatabaseCount('exercise_media', 0);
        $this->assertDatabaseCount('exercise_import_batches', 2);
        $this->assertSame($exerciseId, Exercise::query()->where('name', 'Test Sit-up Updated')->value('id'));

        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);
        Sanctum::actingAs($admin);

        $this->getJson('/api/platform-admin/exercises?equipment=body%20weight&target_muscle=abs&is_bodyweight=1&locale=es')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $exerciseId)
            ->assertJsonPath('data.0.localized_name', 'Test Sit-up Updated')
            ->assertJsonPath('data.0.instructions', 'Instrucciones en español.')
            ->assertJsonPath('data.0.instruction_steps.0', 'Paso uno.')
            ->assertJsonPath('data.0.content_locale', 'es')
            ->assertJsonPath('data.0.preview_media', null)
            ->assertJsonPath('data.0.default_tracking_mode', 'reps')
            ->assertJsonPath('data.0.is_bodyweight', true);
    }

    public function test_preview_media_contract_supports_licensed_remote_and_local_records(): void
    {
        $path = $this->dataset();
        $this->artisan('exercise-catalog:import', [
            'path' => $path,
            '--apply' => true,
            '--publish' => true,
        ])->assertSuccessful();

        $exercise = Exercise::query()->where('name', 'Test Sit-up')->firstOrFail();
        ExerciseMedia::query()->create([
            'exercise_id' => $exercise->id,
            'kind' => 'gif',
            'source_type' => 'remote_url',
            'remote_url' => 'https://media.example.test/owned/test-sit-up.gif',
            'mime_type' => 'image/gif',
            'license_code' => 'TEST-OWNED',
            'license_evidence_reference' => 'test-fixture-only',
            'status' => 'active',
        ]);

        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);
        Sanctum::actingAs($admin);

        $this->getJson('/api/platform-admin/exercises?search=Test%20Sit-up')
            ->assertOk()
            ->assertJsonPath('data.0.preview_media.kind', 'gif')
            ->assertJsonPath('data.0.preview_media.source_type', 'remote_url')
            ->assertJsonPath('data.0.preview_media.url', 'https://media.example.test/owned/test-sit-up.gif');
    }

    public function test_reviewed_staged_rows_can_be_published_without_source_content_changing(): void
    {
        $path = $this->dataset();

        $this->artisan('exercise-catalog:import', [
            'path' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(0, Exercise::query()->where('is_active', true)->count());
        $this->assertSame(2, ExerciseTranslation::query()->where('review_status', 'imported')->where('locale', 'en')->count());

        $this->artisan('exercise-catalog:import', [
            'path' => $path,
            '--apply' => true,
            '--publish' => true,
        ])->assertSuccessful();

        $this->assertSame(2, Exercise::query()->where('is_active', true)->where('review_status', 'approved')->count());
        $this->assertSame(4, ExerciseTranslation::query()->where('review_status', 'approved')->count());
    }

    public function test_import_updates_an_existing_global_exercise_with_the_same_normalized_name(): void
    {
        $existing = Exercise::query()->create([
            'name' => '  TEST SIT-UP  ',
            'muscle_group' => 'legacy core',
            'equipment' => 'mat',
            'instructions' => 'Legacy instructions.',
            'image_url' => 'https://owned.example.test/sit-up.jpg',
            'is_global' => true,
            'status' => 'approved',
            'review_status' => 'approved',
            'is_active' => true,
        ]);

        $report = app(ExerciseCatalogImporter::class)->importFile(
            $this->dataset(),
            ['source_key' => 'hasaneyldrm_exercises_dataset', 'license_code' => 'MIT'],
            apply: true,
            publish: true,
        );
        $this->assertSame(1, $report['matched_existing_by_name']);

        $this->assertDatabaseCount('exercises', 2);
        $this->assertDatabaseHas('exercise_sources', [
            'exercise_id' => $existing->id,
            'source_external_id' => '0001',
        ]);
        $existing->refresh();
        $this->assertSame('Test Sit-up', $existing->name);
        $this->assertSame('core', $existing->muscle_group);
        $this->assertSame('abs', $existing->target_muscle);
        $this->assertSame('https://owned.example.test/sit-up.jpg', $existing->image_url);
    }

    public function test_body_part_filter_uses_canonical_body_part_instead_of_upstream_supporting_muscle(): void
    {
        $chest = Exercise::query()->create([
            'name' => 'Canonical Chest Exercise',
            'body_part' => 'chest',
            'muscle_group' => 'shoulders',
            'target_muscle' => 'pectorals',
            'is_global' => true,
            'status' => 'approved',
            'review_status' => 'approved',
            'is_active' => true,
        ]);
        Exercise::query()->create([
            'name' => 'Triceps Exercise With Chest Support',
            'body_part' => 'arms',
            'muscle_group' => 'chest',
            'target_muscle' => 'triceps',
            'is_global' => true,
            'status' => 'approved',
            'review_status' => 'approved',
            'is_active' => true,
        ]);

        $query = Exercise::query()->where('is_global', true);
        ExerciseBookCatalog::applyBodyPartFilter($query, 'chest');

        $this->assertSame([$chest->id], $query->pluck('id')->all());
    }

    public function test_taxonomy_repair_preserves_existing_publication_and_translation_review_state(): void
    {
        $path = $this->dataset();
        $importer = app(ExerciseCatalogImporter::class);
        $metadata = ['source_key' => 'hasaneyldrm_exercises_dataset', 'license_code' => 'MIT'];
        $importer->importFile($path, $metadata, apply: true, publish: true);

        $exercise = Exercise::query()->where('name', 'Test Sit-up')->firstOrFail();
        $exercise->update(['muscle_group' => 'chest']);
        ExerciseSource::query()->where('exercise_id', $exercise->id)->update([
            'content_checksum' => str_repeat('0', 64),
        ]);

        $importer->importFile($path, $metadata, apply: true, publish: false);

        $exercise->refresh();
        $this->assertSame('core', $exercise->muscle_group);
        $this->assertTrue($exercise->is_active);
        $this->assertSame('approved', $exercise->status);
        $this->assertSame('approved', $exercise->review_status);
        $this->assertDatabaseHas('exercise_translations', [
            'exercise_id' => $exercise->id,
            'locale' => 'en',
            'review_status' => 'approved',
        ]);
    }

    public function test_catalog_seeder_validates_count_and_runs_the_idempotent_import(): void
    {
        $path = $this->dataset();
        config()->set('exercise_catalog.dataset_path', $path);
        config()->set('exercise_catalog.expected_count', 2);
        config()->set('exercise_catalog.publish', true);

        $this->seed(ExerciseCatalogSeeder::class);
        $this->seed(ExerciseCatalogSeeder::class);

        $this->assertDatabaseCount('exercises', 2);
        $this->assertDatabaseCount('exercise_sources', 2);
        $this->assertSame(2, Exercise::query()->where('is_active', true)->count());
    }

    private function dataset(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gym-atlas-exercises-');
        $this->temporaryFiles[] = $path;
        $this->writeDataset($path, $this->records());

        return $path;
    }

    private function writeDataset(string $path, array $records, ?string $firstName = null): void
    {
        if ($firstName !== null) {
            $records[0]['name'] = $firstName;
        }

        file_put_contents($path, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function records(): array
    {
        return [
            [
                'id' => '0001',
                'name' => 'Test Sit-up',
                'category' => 'waist',
                'body_part' => 'waist',
                'equipment' => 'body weight',
                'instructions' => [
                    'en' => 'English instructions.',
                    'es' => 'Instrucciones en español.',
                ],
                'instruction_steps' => [
                    'en' => ['Step one.'],
                    'es' => ['Paso uno.'],
                ],
                'muscle_group' => 'hip flexors',
                'secondary_muscles' => ['hip flexors', 'lower back'],
                'target' => 'abs',
                'media_id' => 'not-licensed',
                'image' => 'images/not-licensed.jpg',
                'gif_url' => 'videos/not-licensed.gif',
                'attribution' => 'Third-party media',
            ],
            [
                'id' => '0002',
                'name' => 'Test Treadmill',
                'category' => 'cardio',
                'body_part' => 'cardio',
                'equipment' => 'treadmill',
                'instructions' => [
                    'en' => 'Walk safely.',
                    'hi' => 'सुरक्षित रूप से चलें।',
                ],
                'instruction_steps' => [
                    'en' => ['Start slowly.'],
                    'hi' => ['धीरे शुरू करें।'],
                ],
                'muscle_group' => 'cardiovascular system',
                'secondary_muscles' => ['calves'],
                'target' => 'cardiovascular system',
                'image' => 'images/not-licensed-2.jpg',
                'gif_url' => 'videos/not-licensed-2.gif',
            ],
        ];
    }
}
