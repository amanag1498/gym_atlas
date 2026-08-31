<?php

return [
    'dataset_path' => env('EXERCISE_CATALOG_DATASET_PATH', database_path('data/exercises.json')),
    'source_key' => env('EXERCISE_CATALOG_SOURCE_KEY', 'hasaneyldrm_exercises_dataset'),
    'source_url' => env('EXERCISE_CATALOG_SOURCE_URL', 'https://github.com/hasaneyldrm/exercises-dataset'),
    'source_commit' => env('EXERCISE_CATALOG_SOURCE_COMMIT'),
    'license_code' => env('EXERCISE_CATALOG_LICENSE', 'MIT'),
    'expected_count' => (int) env('EXERCISE_CATALOG_EXPECTED_COUNT', 1324),
    'publish' => (bool) env('EXERCISE_CATALOG_PUBLISH', false),
];
