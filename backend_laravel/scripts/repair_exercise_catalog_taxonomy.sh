#!/usr/bin/env bash

set -euo pipefail

if [[ $# -lt 1 || $# -gt 2 ]]; then
    echo "Usage: $0 /absolute/path/to/exercises.json [--apply]" >&2
    exit 64
fi

dataset_path="$1"
mode="${2:-}"

if [[ "$mode" != "" && "$mode" != "--apply" ]]; then
    echo "Second argument must be --apply when database writes are intended." >&2
    exit 64
fi

if [[ ! -r "$dataset_path" ]]; then
    echo "Exercise dataset is not readable: $dataset_path" >&2
    exit 66
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
backend_dir="$(cd "$script_dir/.." && pwd)"
dataset_dir="$(cd "$(dirname "$dataset_path")" && pwd)"
dataset_path="$dataset_dir/$(basename "$dataset_path")"

cd "$backend_dir"

import_args=(
    exercise-catalog:import
    "$dataset_path"
    --expected-count=1324
    --strict-count
)

echo "Running taxonomy repair dry run..."
php artisan "${import_args[@]}"

if [[ "$mode" != "--apply" ]]; then
    echo "Dry run only. Re-run with --apply after reviewing the report."
    exit 0
fi

echo "Applying taxonomy repair without changing existing publication state..."
php artisan "${import_args[@]}" --apply

echo "Verifying idempotency..."
php artisan "${import_args[@]}"
