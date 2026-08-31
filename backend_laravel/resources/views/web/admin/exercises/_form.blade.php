@php
    $isEdit = $exercise->exists;
    $secondaryMuscles = old('secondary_muscles', $exercise->secondary_muscles ?? []);
    $secondaryMuscles = is_array($secondaryMuscles) ? array_values($secondaryMuscles) : [];
@endphp

<x-premium-card class="overflow-hidden">
    <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="max-w-3xl">
                <div class="panel-toolbar-chip">Global Exercise Library</div>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $isEdit ? 'Edit Exercise' : 'Create Exercise' }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Maintain the global movement library used by workout books, trainer templates, member plans, and logged personal records.</p>
            </div>
            @if ($isEdit)
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Builder Usage</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ ($exercise->plan_exercises_count ?? 0) + ($exercise->session_exercises_count ?? 0) }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">plans and session entries using this movement</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Performance Data</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $exercise->personal_records_count ?? 0 }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">personal records linked to this exercise</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('web.admin.exercises.update', $exercise) : route('web.admin.exercises.store') }}" class="p-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
            <div class="space-y-6">
                <div class="admin-detail-grid">
                    <x-form-input name="name" label="Exercise Name" :value="old('name', $exercise->name)" placeholder="Barbell Back Squat" required />
                    <x-form-select name="body_part" label="Body Part" :selected="old('body_part', $exercise->body_part)" :options="['' => 'Select Body Part'] + collect(\App\Support\Workout\ExerciseBookCatalog::BODY_PART_ORDER)->mapWithKeys(fn ($part) => [$part => \App\Support\Workout\ExerciseBookCatalog::bodyPartLabel($part)])->all()" />
                    <x-form-input name="muscle_group" label="Muscle Group" :value="old('muscle_group', $exercise->muscle_group)" placeholder="legs" required />
                    <x-form-input name="target_muscle" label="Target Muscle" :value="old('target_muscle', $exercise->target_muscle)" placeholder="quadriceps" />
                    <x-form-input name="equipment" label="Equipment" :value="old('equipment', $exercise->equipment)" placeholder="barbell" />
                    <x-form-input name="difficulty" label="Difficulty" :value="old('difficulty', $exercise->difficulty)" placeholder="intermediate" />
                    <x-form-input name="movement_pattern" label="Movement Pattern" :value="old('movement_pattern', $exercise->movement_pattern)" placeholder="squat" />
                    <x-form-select name="default_tracking_mode" label="Tracking" :selected="old('default_tracking_mode', $exercise->default_tracking_mode ?: 'reps')" :options="['reps' => 'Reps', 'timed' => 'Timed', 'cardio' => 'Cardio', 'distance' => 'Distance']" />
                    <x-form-select name="status" label="Status" :selected="old('status', $exercise->status)" :options="$statusOptions" />
                    <x-form-select name="review_status" label="Review Status" :selected="old('review_status', $exercise->review_status ?: 'approved')" :options="['imported' => 'Imported', 'in_review' => 'In Review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'archived' => 'Archived']" />
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Activation</div>
                        <label class="mt-3 flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="mt-1" name="is_active" value="1" @checked(old('is_active', $exercise->is_active ?? true))>
                            <span>Keep this exercise available in new plan builders.</span>
                        </label>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ([
                        'is_bodyweight' => ['Bodyweight', 'Exercise can be performed using body weight.'],
                        'supports_external_load' => ['External Load', 'Allow weight/load tracking.'],
                        'is_per_side' => ['Per Side', 'Track left and right sides separately.'],
                    ] as $field => [$label, $help])
                        <div class="panel-card-muted px-4 py-4">
                            <label class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input type="checkbox" class="mt-1" name="{{ $field }}" value="1" @checked(old($field, $exercise->{$field} ?? ($field === 'supports_external_load')))>
                                <span><span class="font-semibold text-slate-950 dark:text-white">{{ $label }}</span><br>{{ $help }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="admin-detail-grid">
                    <x-form-input name="image_url" label="Legacy Image URL (optional)" :value="old('image_url', $exercise->image_url)" placeholder="https://..." />
                    <x-form-input name="video_url" label="Legacy Video URL (optional)" :value="old('video_url', $exercise->video_url)" placeholder="https://..." />
                </div>

                <div>
                    <label class="panel-label">Secondary Muscles</label>
                    <div class="admin-detail-grid-compact">
                        @for ($index = 0; $index < 4; $index++)
                            <input name="secondary_muscles[]" value="{{ $secondaryMuscles[$index] ?? '' }}" class="panel-input" placeholder="core">
                        @endfor
                    </div>
                    @error('secondary_muscles') <div class="mt-2 text-sm text-rose-500">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="panel-label" for="instructions">Instructions</label>
                    <textarea id="instructions" name="instructions" class="panel-textarea" rows="6" placeholder="Coaching cues, setup, tempo, and safety notes...">{{ old('instructions', $exercise->instructions) }}</textarea>
                    @error('instructions') <div class="mt-2 text-sm text-rose-500">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="space-y-4">
                <div class="panel-card-muted px-4 py-4">
                    <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Library Rules</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Keep names searchable, muscle groups consistent, and instructions trainer-friendly. Global exercises should be clean enough to support templates, sessions, and analytics.</p>
                </div>

                <div class="panel-card-muted px-4 py-4">
                    <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Preview</h3>
                    <div class="mt-3 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/80">
                        <div class="flex items-start justify-between gap-3">
                            <div class="font-semibold text-slate-950 dark:text-white">{{ old('name', $exercise->name ?: 'Exercise Name') }}</div>
                            <x-status-badge :label="str(old('status', $exercise->status ?: 'approved'))->title()" tone="info" />
                        </div>
                        <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ old('muscle_group', $exercise->muscle_group ?: 'muscle group') }} • {{ old('equipment', $exercise->equipment ?: 'equipment') }} • {{ old('difficulty', $exercise->difficulty ?: 'difficulty') }}</div>
                        <div class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ old('instructions', $exercise->instructions ?: 'Instructions will appear here to show how operators and coaches will read this movement.') }}</div>
                    </div>
                </div>

                @if ($isEdit)
                    <div class="panel-card-muted px-4 py-4">
                        <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Source & Preview</h3>
                        <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                            <div><span class="font-semibold text-slate-950 dark:text-white">Translations:</span> {{ $exercise->translations_count ?? 0 }}</div>
                            <div><span class="font-semibold text-slate-950 dark:text-white">Media records:</span> {{ $exercise->media_count ?? 0 }}</div>
                            @forelse ($exercise->sources as $source)
                                <div class="rounded-xl border border-slate-200/80 p-3 dark:border-slate-800">
                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $source->source_key }} · {{ $source->source_external_id }}</div>
                                    <div class="mt-1">License {{ $source->license_code }} · synced {{ $source->last_synced_at?->diffForHumans() }}</div>
                                </div>
                            @empty
                                <div>Manual/internal exercise with no external source record.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Operational Context</h3>
                        <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                            <div><span class="font-semibold text-slate-950 dark:text-white">Creator:</span> {{ $exercise->creator?->name ?? 'System' }}</div>
                            <div><span class="font-semibold text-slate-950 dark:text-white">Catalog Scope:</span> {{ $exercise->is_global ? 'Global library' : 'Local' }}</div>
                            <div><span class="font-semibold text-slate-950 dark:text-white">Session Usage:</span> {{ $exercise->session_exercises_count ?? 0 }}</div>
                            <div><span class="font-semibold text-slate-950 dark:text-white">Template Usage:</span> {{ $exercise->template_exercises_count ?? 0 }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <x-action-button type="submit">{{ $isEdit ? 'Update Exercise' : 'Create Exercise' }}</x-action-button>
            <x-action-button as="a" href="{{ route('web.admin.exercises.index') }}" variant="secondary">Back to Exercise Book</x-action-button>
        </div>
    </form>
</x-premium-card>
