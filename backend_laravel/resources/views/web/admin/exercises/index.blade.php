@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.exercises.create') }}">Add Exercise</x-action-button>
        <x-action-button as="a" href="{{ route('web.admin.workout-books.create') }}" variant="secondary">Create Workout Book</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Movement Library</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Exercise Book</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">A global movement catalog organized by body part so workout books, custom plans, sessions, and member progress records all point to the same backend-driven library.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Video Ready</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $videoReadyExercises }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">exercises with linked demo video</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Image Ready</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $imageReadyExercises }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">exercises with visual media</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Exercises" :value="$totalExercises" hint="Seeded catalog size" tone="sky" />
            <x-stat-card label="Body Parts" :value="count($groupedExercises)" hint="Grouped selection buckets" tone="emerald" />
            <x-stat-card label="Active" :value="$activeExercises" hint="Available in builders" tone="violet" />
            <x-stat-card label="Media Ready" :value="$videoReadyExercises + $imageReadyExercises" hint="Video or image attached" tone="amber" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1.8fr)_minmax(220px,1fr)_auto]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Search by exercise, muscle group, or equipment" />
                <x-form-select
                    name="body_part"
                    label="Body Part"
                    :selected="request('body_part')"
                    :options="['' => 'All Body Parts'] + $bodyPartOptions"
                />
                <div class="flex items-end gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.exercises.index') }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        @if (count($groupedExercises) > 0)
            <x-premium-card class="p-5">
                <div class="flex flex-col gap-3">
                    <div>
                        <h3 class="panel-section-title">Body-Part Navigation</h3>
                        <p class="panel-section-copy">Jump into the movement families most commonly used while building workout books and session plans.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($groupedExercises as $group)
                            <a href="#group-{{ $group['body_part'] }}" class="panel-toolbar-chip no-underline">{{ $group['label'] }} · {{ $group['count'] }}</a>
                        @endforeach
                    </div>
                </div>
            </x-premium-card>

            <div class="space-y-6">
                @foreach ($groupedExercises as $group)
                    <x-premium-card class="overflow-hidden" id="group-{{ $group['body_part'] }}">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h3 class="panel-section-title">{{ $group['label'] }}</h3>
                                    <p class="panel-section-copy">{{ $group['count'] }} exercises available for workout-book and member-plan selection.</p>
                                </div>
                                <x-status-badge :label="$group['count'].' exercises'" tone="neutral" />
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="panel-table min-w-[1320px]">
                                <thead>
                                    <tr>
                                        <th>Exercise</th>
                                        <th>Movement</th>
                                        <th>Media</th>
                                        <th>Usage</th>
                                        <th>Creator</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['exercises'] as $exercise)
                                        @php($usageTotal = ($exercise['plan_exercises_count'] ?? 0) + ($exercise['session_exercises_count'] ?? 0) + ($exercise['personal_records_count'] ?? 0))
                                        <tr>
                                            <td>
                                                <div class="font-semibold text-slate-950 dark:text-white">{{ $exercise['name'] }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ str($exercise['status'])->title() }} • {{ !empty($exercise['is_active']) ? 'Active' : 'Inactive' }}</div>
                                                @if (!empty($exercise['instructions']))
                                                    <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($exercise['instructions'], 110) }}</div>
                                                @endif
                                            </td>
                                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                                <div>{{ str($exercise['muscle_group'])->replace('_', ' ')->title() }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $exercise['equipment'] ?: 'No equipment set' }} • {{ $exercise['difficulty'] ?: 'No difficulty' }}</div>
                                                @if (!empty($exercise['secondary_muscles']))
                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        @foreach (collect($exercise['secondary_muscles'])->take(3) as $secondary)
                                                            <x-status-badge :label="str($secondary)->title()" tone="neutral" />
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                                <div>{{ !empty($exercise['video_url']) ? 'Video linked' : 'No video' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ !empty($exercise['image_url']) ? 'Image linked' : 'No image' }}</div>
                                            </td>
                                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                                <div>Plans {{ $exercise['plan_exercises_count'] ?? 0 }}</div>
                                                <div>Sessions {{ $exercise['session_exercises_count'] ?? 0 }}</div>
                                                <div>PRs {{ $exercise['personal_records_count'] ?? 0 }}</div>
                                                <div class="mt-1 font-semibold text-slate-900 dark:text-slate-100">Total {{ $usageTotal }}</div>
                                            </td>
                                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                                <div>{{ data_get($exercise, 'creator.name', 'System') }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ !empty($exercise['is_global']) ? 'Global library' : 'Local' }}</div>
                                            </td>
                                            <td>
                                                <div class="flex justify-end gap-2">
                                                    <x-action-button as="a" href="{{ route('web.admin.exercises.edit', $exercise['id']) }}" variant="secondary">Edit</x-action-button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-premium-card>
                @endforeach
            </div>
        @else
            <x-premium-card class="p-5">
                <x-empty-state title="No exercises found" message="Adjust the filters or create the first global exercise for the platform library." action-label="Add Exercise" :action-href="route('web.admin.exercises.create')" />
            </x-premium-card>
        @endif
    </div>
@endsection
