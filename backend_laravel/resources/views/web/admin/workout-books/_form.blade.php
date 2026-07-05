@php
    $isEdit = $workoutBook->exists;
    $difficultyOptions = [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
    ];
    $statusOptions = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];
    $programTypeOptions = [
        'full_body' => 'Full Body',
        'upper_lower' => 'Upper / Lower',
        'push_pull_legs' => 'Push Pull Legs',
        'home_training' => 'Home Training',
        'conditioning' => 'Conditioning',
        'hypertrophy' => 'Hypertrophy',
        'strength' => 'Strength',
    ];
    $equipmentOptions = [
        'mixed_gym' => 'Mixed Gym',
        'bodyweight' => 'Bodyweight',
        'home_setup' => 'Home Setup',
        'dumbbells' => 'Dumbbells',
        'barbell' => 'Barbell',
        'machines' => 'Machines',
    ];
    $weekdayOptions = [
        'monday' => 'Mon',
        'tuesday' => 'Tue',
        'wednesday' => 'Wed',
        'thursday' => 'Thu',
        'friday' => 'Fri',
        'saturday' => 'Sat',
        'sunday' => 'Sun',
    ];
    $initialPlansJson = old('plans_json', $plansJson);
@endphp

<style>
    .wb-builder-shell {
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.03) 0%, rgba(15, 23, 42, 0.01) 100%);
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1.5rem;
        padding: 1.5rem;
    }

    .wb-builder-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .wb-builder-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }

    .wb-stat-card,
    .wb-helper-card,
    .wb-plan-card,
    .wb-day-card,
    .wb-exercise-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1.25rem;
        background: #fff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
    }

    .wb-stat-card {
        padding: 0.9rem 1rem;
    }

    .wb-stat-label {
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 0.35rem;
    }

    .wb-stat-value {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0f172a;
    }

    .wb-helper-card {
        padding: 1rem 1.1rem;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(14, 165, 233, 0.04) 100%);
    }

    .wb-section-title {
        font-size: 0.78rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #475569;
        margin-bottom: 0.35rem;
    }

    .wb-plan-stack,
    .wb-day-stack,
    .wb-exercise-stack {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .wb-plan-card {
        padding: 1.25rem;
    }

    .wb-day-card {
        padding: 1rem;
        background: #f8fafc;
    }

    .wb-exercise-card {
        padding: 1rem;
        background: #fff;
    }

    .wb-card-header,
    .wb-subcard-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .wb-card-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.15rem;
    }

    .wb-card-subtitle {
        color: #64748b;
        font-size: 0.88rem;
        margin: 0;
    }

    .wb-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin-top: 0.5rem;
    }

    .wb-meta-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.3rem 0.65rem;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.06);
        color: #334155;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .wb-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        background: rgba(14, 165, 233, 0.1);
        color: #0369a1;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .wb-grid-2,
    .wb-grid-3,
    .wb-grid-4,
    .wb-grid-6 {
        display: grid;
        gap: 0.85rem;
    }

    .wb-grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .wb-grid-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .wb-grid-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .wb-grid-6 {
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .wb-field {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }

    .wb-field label {
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #475569;
    }

    .wb-field input,
    .wb-field select,
    .wb-field textarea {
        width: 100%;
        border: 1px solid rgba(15, 23, 42, 0.12);
        border-radius: 0.85rem;
        padding: 0.75rem 0.85rem;
        background: #fff;
        color: #0f172a;
        font-size: 0.95rem;
    }

    .wb-field textarea {
        min-height: 86px;
        resize: vertical;
    }

    .wb-checkbox-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.65rem;
    }

    .wb-checkbox-pill {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 0.85rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 0.95rem;
        background: rgba(255, 255, 255, 0.92);
        font-size: 0.86rem;
        color: #334155;
    }

    .wb-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 1rem;
    }

    .wb-toolbar--top {
        margin-top: 0;
        margin-bottom: 1rem;
    }

    .wb-btn {
        appearance: none;
        border: 1px solid rgba(15, 23, 42, 0.1);
        background: #fff;
        color: #0f172a;
        padding: 0.7rem 1rem;
        border-radius: 999px;
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
    }

    .wb-btn--primary {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: #fff;
        border-color: transparent;
    }

    .wb-btn--soft {
        background: rgba(59, 130, 246, 0.08);
        color: #1d4ed8;
    }

    .wb-btn--danger {
        background: rgba(239, 68, 68, 0.08);
        color: #b91c1c;
    }

    .wb-empty {
        border: 1px dashed rgba(15, 23, 42, 0.18);
        border-radius: 1.25rem;
        padding: 1.25rem;
        text-align: center;
        background: rgba(255, 255, 255, 0.6);
        color: #64748b;
    }

    .wb-error {
        display: none;
        border-radius: 1rem;
        padding: 0.9rem 1rem;
        background: rgba(239, 68, 68, 0.08);
        color: #991b1b;
        font-size: 0.92rem;
        margin-bottom: 1rem;
    }

    .wb-hidden-input {
        display: none;
    }

    .dark .wb-builder-shell {
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.8) 0%, rgba(15, 23, 42, 0.55) 100%);
        border-color: rgba(148, 163, 184, 0.18);
    }

    .dark .wb-stat-card,
    .dark .wb-helper-card,
    .dark .wb-plan-card,
    .dark .wb-day-card,
    .dark .wb-exercise-card {
        border-color: rgba(148, 163, 184, 0.14);
        background: rgba(15, 23, 42, 0.92);
        box-shadow: none;
    }

    .dark .wb-stat-label,
    .dark .wb-section-title,
    .dark .wb-card-subtitle,
    .dark .wb-field label {
        color: #94a3b8;
    }

    .dark .wb-stat-value,
    .dark .wb-card-title {
        color: #f8fafc;
    }

    .dark .wb-helper-card {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.18) 0%, rgba(14, 165, 233, 0.08) 100%);
    }

    .dark .wb-day-card {
        background: rgba(30, 41, 59, 0.85);
    }

    .dark .wb-meta-badge {
        background: rgba(148, 163, 184, 0.14);
        color: #cbd5e1;
    }

    .dark .wb-chip {
        background: rgba(59, 130, 246, 0.18);
        color: #bfdbfe;
    }

    .dark .wb-field input,
    .dark .wb-field select,
    .dark .wb-field textarea {
        border-color: rgba(148, 163, 184, 0.16);
        background: rgba(15, 23, 42, 0.92);
        color: #f8fafc;
    }

    .dark .wb-field input::placeholder,
    .dark .wb-field textarea::placeholder {
        color: #64748b;
    }

    .dark .wb-checkbox-pill {
        border-color: rgba(148, 163, 184, 0.16);
        background: rgba(15, 23, 42, 0.92);
        color: #cbd5e1;
    }

    .dark .wb-btn {
        border-color: rgba(148, 163, 184, 0.14);
        background: rgba(30, 41, 59, 0.95);
        color: #e2e8f0;
    }

    .dark .wb-btn--primary {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #eff6ff;
    }

    .dark .wb-btn--soft {
        background: rgba(59, 130, 246, 0.14);
        color: #bfdbfe;
    }

    .dark .wb-btn--danger {
        background: rgba(239, 68, 68, 0.14);
        color: #fecaca;
    }

    .dark .wb-empty {
        border-color: rgba(148, 163, 184, 0.18);
        background: rgba(15, 23, 42, 0.74);
        color: #94a3b8;
    }

    .dark .wb-error {
        background: rgba(127, 29, 29, 0.35);
        color: #fecaca;
    }

    @media (max-width: 1199.98px) {
        .wb-grid-4,
        .wb-grid-6 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .wb-checkbox-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .wb-grid-2,
        .wb-grid-3,
        .wb-grid-4,
        .wb-grid-6,
        .wb-checkbox-grid {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .wb-builder-header,
        .wb-card-header,
        .wb-subcard-header {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<div class="space-y-6">
    <section class="panel-hero">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <div class="panel-toolbar-chip">{{ $isEdit ? 'Catalog Update' : 'Workout Catalog' }}</div>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $isEdit ? 'Edit Workout Book' : 'Create Workout Book' }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $isEdit ? 'Refine platform workout books with structured plans, coaching rules, and clean exercise mapping.' : 'Create a reusable training book with production-ready plans, schedule structure, and real exercise assignments.' }}</p>
            </div>
            <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                <div class="panel-card-muted px-4 py-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Plans</div>
                    <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $workoutBook->templates_count ?? ($isEdit ? $workoutBook->templates()->count() : 0) }}</div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">nested templates in this book</div>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Publishing</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <x-status-badge :label="$workoutBook->status === 'active' ? 'Active' : 'Inactive'" :tone="$workoutBook->status === 'active' ? 'success' : 'danger'" />
                        @if ($workoutBook->is_featured)
                            <x-status-badge label="Featured" tone="info" />
                        @endif
                    </div>
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $workoutBook->published_at ? 'Published '.$workoutBook->published_at->format('d M Y') : 'Not published yet' }}</div>
                </div>
            </div>
        </div>
    </section>

    <x-premium-card class="p-5">
        @if ($isEdit)
            <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="panel-card-muted px-4 py-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Slug</div>
                    <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $workoutBook->slug }}</div>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Created By</div>
                    <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $workoutBook->creator?->name ?? 'System' }}</div>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Published</div>
                    <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $workoutBook->published_at?->format('d M Y, h:i A') ?: 'Draft' }}</div>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Session Length</div>
                    <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $workoutBook->estimated_session_minutes ?: '--' }} minutes</div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ $isEdit ? route('web.admin.workout-books.update', $workoutBook) : route('web.admin.workout-books.store') }}" id="workout-book-form">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                <div class="space-y-5">
                    <div class="grid gap-5">
                        <div>
                            <x-form-input name="name" label="Book Name" :value="old('name', $workoutBook->name)" placeholder="Starter Strength Book" required />
                        </div>
                        <div>
                            <x-form-input name="audience" label="Audience" :value="old('audience', $workoutBook->audience)" placeholder="New members, busy professionals" />
                        </div>
                        <div>
                            <x-form-input name="goal" label="Primary Goal" :value="old('goal', $workoutBook->goal)" placeholder="Lose Fat, Build Muscle, General Fitness" />
                        </div>
                        <div>
                            <x-form-select
                                name="program_type"
                                label="Program Type"
                                :selected="old('program_type', $workoutBook->program_type ?: 'full_body')"
                                :options="$programTypeOptions"
                            />
                        </div>
                        <div>
                            <x-form-select
                                name="difficulty"
                                label="Difficulty"
                                :selected="old('difficulty', $workoutBook->difficulty)"
                                :options="$difficultyOptions"
                            />
                        </div>
                        <div>
                            <x-form-select
                                name="equipment_profile"
                                label="Equipment Profile"
                                :selected="old('equipment_profile', $workoutBook->equipment_profile ?: 'mixed_gym')"
                                :options="$equipmentOptions"
                            />
                        </div>
                        <div>
                            <x-form-select
                                name="status"
                                label="Status"
                                :selected="old('status', $workoutBook->status ?: 'active')"
                                :options="$statusOptions"
                            />
                        </div>
                        <div>
                            <x-form-input name="days_per_week" label="Days Per Week" type="number" min="1" max="7" :value="old('days_per_week', $workoutBook->days_per_week)" />
                        </div>
                        <div>
                            <x-form-input name="duration_weeks" label="Duration Weeks" type="number" min="1" max="52" :value="old('duration_weeks', $workoutBook->duration_weeks)" />
                        </div>
                        <div>
                            <x-form-input name="estimated_session_minutes" label="Minutes Per Session" type="number" min="10" max="240" :value="old('estimated_session_minutes', $workoutBook->estimated_session_minutes)" />
                        </div>
                        <div>
                            <label for="description" class="panel-label">Description</label>
                            <textarea id="description" name="description" rows="4" class="panel-textarea" placeholder="Explain the training intent and the type of member this book is designed for.">{{ old('description', $workoutBook->description) }}</textarea>
                        </div>
                        <div>
                            <label for="coach_notes" class="panel-label">Coach Notes</label>
                            <textarea id="coach_notes" name="coach_notes" rows="4" class="panel-textarea" placeholder="Internal progression notes, coaching reminders, or safety guidance.">{{ old('coach_notes', $workoutBook->coach_notes) }}</textarea>
                        </div>
                        <div>
                            <label class="panel-card-muted flex items-start gap-3 rounded-2xl px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $workoutBook->is_featured))>
                                <span>
                                    <span class="block font-semibold text-slate-950 dark:text-white">Feature in member catalog</span>
                                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Use this for books that should be recommended more prominently in discovery flows.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="wb-builder-shell">
                        <div class="wb-builder-header">
                            <div>
                                <div class="wb-chip">Workout Plan Builder</div>
                                <h4 class="mt-3 mb-2 text-xl font-semibold text-slate-950 dark:text-white">Build plans visually</h4>
                                <p class="mb-0 text-sm text-slate-500 dark:text-slate-400">Define plans, days, and exercise prescriptions with real catalog exercises. The backend still receives the exact nested payload it expects.</p>
                            </div>
                            <div class="wb-toolbar wb-toolbar--top">
                                <button type="button" class="wb-btn wb-btn--soft" id="wb-load-sample">Load Sample</button>
                                <button type="button" class="wb-btn wb-btn--primary" id="wb-add-plan">Add Plan</button>
                            </div>
                        </div>

                        <div class="wb-builder-grid" id="wb-builder-stats"></div>

                        <div class="wb-helper-card mb-3">
                            <div class="wb-section-title">Admin Notes</div>
                            <div class="text-sm text-slate-600 dark:text-slate-300">
                                Each book needs at least one plan. Every plan needs at least one day, and every day needs at least one exercise. Exercises come directly from the real catalog, so admins no longer need to manage raw IDs by hand.
                            </div>
                        </div>

                        <div class="wb-helper-card mb-3">
                            <div class="wb-section-title">Exercise Book</div>
                            <div class="mb-3 text-sm text-slate-600 dark:text-slate-300">
                                The exercise catalog is organized by body part. Build plans by choosing a body part first, then selecting the exact movement, then setting sets and rep ranges.
                            </div>
                            <div class="wb-meta-row">
                                @foreach ($exerciseBook as $group)
                                    <span class="wb-meta-badge">{{ $group['label'] }} · {{ $group['count'] }}</span>
                                @endforeach
                            </div>
                            <div class="mt-3">
                                <a href="{{ route('web.admin.exercises.index') }}" class="wb-btn wb-btn--soft">Open Exercise Book</a>
                            </div>
                        </div>

                        <div id="wb-builder-error" class="wb-error"></div>

                        <div id="wb-plan-builder" class="wb-plan-stack"></div>

                        <textarea id="plans_json" name="plans_json" class="wb-hidden-input">{{ $initialPlansJson }}</textarea>

                        @error('plans_json')
                            <div class="mt-3 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>
                        @enderror
                        @error('plans')
                            <div class="mt-3 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <x-action-button type="submit">{{ $isEdit ? 'Update Workout Book' : 'Create Workout Book' }}</x-action-button>
                <x-action-button as="a" href="{{ route('web.admin.workout-books.index') }}" variant="secondary">Back to Workout Books</x-action-button>
            </div>
        </form>

        @if ($isEdit)
            <form
                action="{{ route('web.admin.workout-books.destroy', $workoutBook) }}"
                method="POST"
                class="mt-2"
                data-confirm-submit
                data-confirm-title="Delete workout book?"
                data-confirm-message="This will permanently delete {{ $workoutBook->name }} and all nested plans."
                data-confirm-button="Delete"
            >
                @csrf
                @method('DELETE')
                <x-action-button type="submit" variant="danger">Delete Workout Book</x-action-button>
            </form>
        @endif
    </x-premium-card>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const exerciseCatalog = @json($exerciseCatalog);
        const exerciseBook = @json($exerciseBook);
        const weekdayOptions = @json($weekdayOptions);
        const difficultyOptions = @json($difficultyOptions);
        const statusOptions = @json($statusOptions);
        const programTypeOptions = @json($programTypeOptions);
        const equipmentOptions = @json($equipmentOptions);
        const samplePlansJson = @json($samplePlansJson);

        const form = document.getElementById('workout-book-form');
        const plansInput = document.getElementById('plans_json');
        const builder = document.getElementById('wb-plan-builder');
        const statsContainer = document.getElementById('wb-builder-stats');
        const errorBox = document.getElementById('wb-builder-error');
        const addPlanButton = document.getElementById('wb-add-plan');
        const loadSampleButton = document.getElementById('wb-load-sample');

        const defaultBodyPart = exerciseBook[0]?.body_part ?? '';
        const defaultExerciseId = exerciseBook[0]?.exercises?.[0]?.id ?? exerciseCatalog[0]?.id ?? '';
        const exerciseCatalogById = new Map(exerciseCatalog.map((exercise) => [Number(exercise.id), exercise]));
        const exerciseBookByPart = new Map(exerciseBook.map((group) => [group.body_part, group]));
        const repRangeOptions = {
            '5-6': '5-6',
            '6-8': '6-8',
            '8-10': '8-10',
            '8-12': '8-12',
            '10-12': '10-12',
            '12-15': '12-15',
            '15-20': '15-20',
            'custom': 'Custom',
        };

        const buildEmptyExercise = () => ({
            body_part: defaultBodyPart,
            exercise_id: defaultExerciseId,
            sort_order: 1,
            sets: 3,
            reps: '8-12',
            target_weight: null,
            rest_seconds: 60,
            notes: '',
        });

        const buildEmptyDay = (dayNumber = 1) => ({
            day_number: dayNumber,
            label: `Day ${dayNumber}`,
            focus: '',
            notes: '',
            exercises: [buildEmptyExercise()],
        });

        const buildEmptyPlan = () => ({
            name: '',
            goal: '',
            difficulty: 'beginner',
            program_type: 'full_body',
            equipment_profile: 'mixed_gym',
            duration_weeks: 4,
            estimated_session_minutes: 45,
            weekly_schedule: ['monday', 'wednesday', 'friday'],
            notes: '',
            status: 'active',
            days: [buildEmptyDay(1)],
        });

        const parsePlans = (payload) => {
            if (typeof payload !== 'string' || payload.trim() === '') {
                return [buildEmptyPlan()];
            }

            try {
                const decoded = JSON.parse(payload);

                if (Array.isArray(decoded) && decoded.length > 0) {
                    return decoded;
                }

                return [buildEmptyPlan()];
            } catch (error) {
                errorBox.textContent = 'Saved plan payload could not be parsed. A clean builder state has been loaded. If needed, reload the page after correcting the stored JSON.';
                errorBox.style.display = 'block';

                return [buildEmptyPlan()];
            }
        };

        let plans = parsePlans(plansInput.value);

        const summarize = () => {
            const dayCount = plans.reduce((total, plan) => total + ((plan.days || []).length), 0);
            const exerciseCount = plans.reduce(
                (total, plan) => total + (plan.days || []).reduce(
                    (dayTotal, day) => dayTotal + ((day.exercises || []).length),
                    0,
                ),
                0,
            );

            statsContainer.innerHTML = '';

            [
                { label: 'Plans', value: plans.length },
                { label: 'Training Days', value: dayCount },
                { label: 'Exercises', value: exerciseCount },
                { label: 'Catalog Choices', value: exerciseCatalog.length },
            ].forEach((stat) => {
                const card = document.createElement('div');
                card.className = 'wb-stat-card';
                card.innerHTML = `
                    <div class="wb-stat-label">${stat.label}</div>
                    <div class="wb-stat-value">${stat.value}</div>
                `;
                statsContainer.appendChild(card);
            });
        };

        const syncPayload = () => {
            plansInput.value = JSON.stringify(plans, null, 4);
        };

        const createField = ({ label, type = 'text', value = '', options = null, onChange, min = null, max = null, step = null, placeholder = '', rows = 3 }) => {
            const field = document.createElement('div');
            field.className = 'wb-field';

            const labelNode = document.createElement('label');
            labelNode.textContent = label;
            field.appendChild(labelNode);

            let input;

            if (type === 'select') {
                input = document.createElement('select');
                Object.entries(options || {}).forEach(([optionValue, optionLabel]) => {
                    const option = document.createElement('option');
                    option.value = optionValue;
                    option.textContent = optionLabel;
                    option.selected = String(optionValue) === String(value ?? '');
                    input.appendChild(option);
                });
            } else if (type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = rows;
                input.value = value ?? '';
                input.placeholder = placeholder;
            } else {
                input = document.createElement('input');
                input.type = type;
                input.value = value ?? '';
                input.placeholder = placeholder;
                if (min !== null) input.min = String(min);
                if (max !== null) input.max = String(max);
                if (step !== null) input.step = String(step);
            }

            input.addEventListener('input', () => onChange(input.value));
            input.addEventListener('change', () => onChange(input.value));
            field.appendChild(input);

            return field;
        };

        const createBodyPartSelectField = (bodyPart, onChange) => {
            const field = document.createElement('div');
            field.className = 'wb-field';

            const labelNode = document.createElement('label');
            labelNode.textContent = 'Body Part';
            field.appendChild(labelNode);

            const select = document.createElement('select');
            exerciseBook.forEach((group) => {
                const option = document.createElement('option');
                option.value = group.body_part;
                option.textContent = `${group.label} · ${group.count}`;
                option.selected = String(group.body_part) === String(bodyPart ?? defaultBodyPart);
                select.appendChild(option);
            });

            select.addEventListener('change', () => onChange(select.value));
            field.appendChild(select);

            return field;
        };

        const createExerciseSelectField = (bodyPart, exerciseId, onChange) => {
            const field = document.createElement('div');
            field.className = 'wb-field';

            const labelNode = document.createElement('label');
            labelNode.textContent = 'Exercise';
            field.appendChild(labelNode);

            const select = document.createElement('select');
            const exercises = exerciseBookByPart.get(bodyPart)?.exercises ?? exerciseCatalog;
            exercises.forEach((exercise) => {
                const option = document.createElement('option');
                option.value = exercise.id;
                option.textContent = `${exercise.name}${exercise.muscle_group ? ` · ${exercise.muscle_group}` : ''}${exercise.equipment ? ` · ${exercise.equipment}` : ''}`;
                option.selected = Number(exerciseId) === Number(exercise.id);
                select.appendChild(option);
            });

            select.addEventListener('change', () => onChange(select.value));
            field.appendChild(select);

            return field;
        };

        const getExerciseMeta = (exerciseId) => {
            return exerciseCatalogById.get(Number(exerciseId)) || null;
        };

        const detectRepPreset = (value) => Object.prototype.hasOwnProperty.call(repRangeOptions, value) ? value : 'custom';

        const createWeekdaySelector = (selectedDays, onToggle) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'wb-field';

            const labelNode = document.createElement('label');
            labelNode.textContent = 'Weekly Schedule';
            wrapper.appendChild(labelNode);

            const grid = document.createElement('div');
            grid.className = 'wb-checkbox-grid';

            Object.entries(weekdayOptions).forEach(([value, label]) => {
                const pill = document.createElement('label');
                pill.className = 'wb-checkbox-pill';

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.checked = Array.isArray(selectedDays) && selectedDays.includes(value);
                input.addEventListener('change', () => onToggle(value, input.checked));

                const text = document.createElement('span');
                text.textContent = label;

                pill.appendChild(input);
                pill.appendChild(text);
                grid.appendChild(pill);
            });

            wrapper.appendChild(grid);

            return wrapper;
        };

        const normalizePlan = (plan) => ({
            name: plan?.name ?? '',
            goal: plan?.goal ?? '',
            difficulty: plan?.difficulty ?? 'beginner',
            program_type: plan?.program_type ?? 'full_body',
            equipment_profile: plan?.equipment_profile ?? 'mixed_gym',
            duration_weeks: Number(plan?.duration_weeks ?? 4),
            estimated_session_minutes: Number(plan?.estimated_session_minutes ?? 45),
            weekly_schedule: Array.isArray(plan?.weekly_schedule) ? plan.weekly_schedule : [],
            notes: plan?.notes ?? '',
            status: plan?.status ?? 'active',
            days: Array.isArray(plan?.days) && plan.days.length > 0
                ? plan.days.map((day, dayIndex) => ({
                    day_number: Number(day?.day_number ?? dayIndex + 1),
                    label: day?.label ?? `Day ${dayIndex + 1}`,
                    focus: day?.focus ?? '',
                    notes: day?.notes ?? '',
                    exercises: Array.isArray(day?.exercises) && day.exercises.length > 0
                        ? day.exercises.map((exercise, exerciseIndex) => ({
                            body_part: exercise?.body_part ?? getExerciseMeta(exercise?.exercise_id)?.body_part ?? defaultBodyPart,
                            exercise_id: Number(exercise?.exercise_id ?? defaultExerciseId),
                            sort_order: Number(exercise?.sort_order ?? exerciseIndex + 1),
                            sets: Number(exercise?.sets ?? 3),
                            reps: exercise?.reps ?? '8-12',
                            target_weight: exercise?.target_weight ?? null,
                            rest_seconds: exercise?.rest_seconds ?? 60,
                            notes: exercise?.notes ?? '',
                        }))
                        : [buildEmptyExercise()],
                }))
                : [buildEmptyDay(1)],
        });

        plans = plans.map(normalizePlan);

        const renderExercise = (exercise, planIndex, dayIndex, exerciseIndex) => {
            const card = document.createElement('div');
            card.className = 'wb-exercise-card';

            const header = document.createElement('div');
            header.className = 'wb-subcard-header';
            const exerciseMeta = getExerciseMeta(exercise.exercise_id);
            header.innerHTML = `
                <div>
                    <div class="wb-card-title">Exercise ${exerciseIndex + 1}</div>
                    <p class="wb-card-subtitle">Prescription and recovery settings</p>
                    <div class="wb-meta-row">
                        ${exerciseMeta?.body_part_label ? `<span class="wb-meta-badge">${exerciseMeta.body_part_label}</span>` : ''}
                        ${exerciseMeta?.muscle_group ? `<span class="wb-meta-badge">${exerciseMeta.muscle_group}</span>` : ''}
                        ${exerciseMeta?.equipment ? `<span class="wb-meta-badge">${exerciseMeta.equipment}</span>` : ''}
                        ${exerciseMeta?.name ? `<span class="wb-meta-badge">${exerciseMeta.name}</span>` : ''}
                    </div>
                </div>
            `;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'wb-btn wb-btn--danger';
            removeButton.textContent = 'Remove Exercise';
            removeButton.addEventListener('click', () => {
                plans[planIndex].days[dayIndex].exercises.splice(exerciseIndex, 1);
                if (plans[planIndex].days[dayIndex].exercises.length === 0) {
                    plans[planIndex].days[dayIndex].exercises.push(buildEmptyExercise());
                }
                renderBuilder();
            });
            header.appendChild(removeButton);

            const grid = document.createElement('div');
            grid.className = 'wb-grid-6';

            grid.appendChild(createBodyPartSelectField(exercise.body_part, (value) => {
                const nextExercises = exerciseBookByPart.get(value)?.exercises ?? [];
                plans[planIndex].days[dayIndex].exercises[exerciseIndex].body_part = value;
                plans[planIndex].days[dayIndex].exercises[exerciseIndex].exercise_id = nextExercises[0]?.id ?? defaultExerciseId;
                syncPayload();
                renderBuilder();
            }));

            grid.appendChild(createExerciseSelectField(exercise.body_part, exercise.exercise_id, (value) => {
                const nextMeta = getExerciseMeta(value);
                plans[planIndex].days[dayIndex].exercises[exerciseIndex].exercise_id = Number(value);
                plans[planIndex].days[dayIndex].exercises[exerciseIndex].body_part = nextMeta?.body_part ?? exercise.body_part ?? defaultBodyPart;
                syncPayload();
                renderBuilder();
            }));

            grid.appendChild(createField({
                label: 'Sort Order',
                type: 'number',
                value: exercise.sort_order,
                min: 1,
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].exercises[exerciseIndex].sort_order = Number(value || 1);
                    syncPayload();
                },
            }));

            grid.appendChild(createField({
                label: 'Sets',
                type: 'number',
                value: exercise.sets,
                min: 1,
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].exercises[exerciseIndex].sets = Number(value || 1);
                    syncPayload();
                },
            }));

            grid.appendChild(createField({
                label: 'Rep Range',
                type: 'select',
                value: detectRepPreset(exercise.reps),
                options: repRangeOptions,
                onChange: (value) => {
                    if (value !== 'custom') {
                        plans[planIndex].days[dayIndex].exercises[exerciseIndex].reps = value;
                    }
                    syncPayload();
                    renderBuilder();
                },
            }));

            grid.appendChild(createField({
                label: 'Reps',
                value: exercise.reps,
                placeholder: '8-12',
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].exercises[exerciseIndex].reps = value;
                    syncPayload();
                },
            }));

            grid.appendChild(createField({
                label: 'Target Weight',
                type: 'number',
                value: exercise.target_weight ?? '',
                min: 0,
                step: '0.01',
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].exercises[exerciseIndex].target_weight = value === '' ? null : Number(value);
                    syncPayload();
                },
            }));

            grid.appendChild(createField({
                label: 'Rest Seconds',
                type: 'number',
                value: exercise.rest_seconds ?? '',
                min: 0,
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].exercises[exerciseIndex].rest_seconds = value === '' ? null : Number(value);
                    syncPayload();
                },
            }));

            const notesField = createField({
                label: 'Exercise Notes',
                type: 'textarea',
                value: exercise.notes,
                rows: 2,
                placeholder: 'Cueing, tempo, RIR, or progression notes',
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].exercises[exerciseIndex].notes = value;
                    syncPayload();
                },
            });

            card.appendChild(header);
            card.appendChild(grid);
            card.appendChild(notesField);

            return card;
        };

        const renderDay = (day, planIndex, dayIndex) => {
            const card = document.createElement('div');
            card.className = 'wb-day-card';

            const header = document.createElement('div');
            header.className = 'wb-subcard-header';
            header.innerHTML = `
                <div>
                    <div class="wb-card-title">Training Day ${dayIndex + 1}</div>
                    <p class="wb-card-subtitle">Daily focus and exercise sequence</p>
                </div>
            `;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'wb-btn wb-btn--danger';
            removeButton.textContent = 'Remove Day';
            removeButton.addEventListener('click', () => {
                plans[planIndex].days.splice(dayIndex, 1);
                if (plans[planIndex].days.length === 0) {
                    plans[planIndex].days.push(buildEmptyDay(1));
                }
                renderBuilder();
            });
            header.appendChild(removeButton);

            const grid = document.createElement('div');
            grid.className = 'wb-grid-4';

            grid.appendChild(createField({
                label: 'Day Number',
                type: 'number',
                value: day.day_number,
                min: 1,
                max: 7,
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].day_number = Number(value || 1);
                    syncPayload();
                },
            }));

            grid.appendChild(createField({
                label: 'Label',
                value: day.label,
                placeholder: 'Upper A',
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].label = value;
                    syncPayload();
                },
            }));

            grid.appendChild(createField({
                label: 'Focus',
                value: day.focus,
                placeholder: 'Squat and push',
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].focus = value;
                    syncPayload();
                },
            }));

            grid.appendChild(createField({
                label: 'Notes',
                type: 'textarea',
                value: day.notes,
                rows: 2,
                placeholder: 'Warm-up, pacing, recovery notes',
                onChange: (value) => {
                    plans[planIndex].days[dayIndex].notes = value;
                    syncPayload();
                },
            }));

            const exerciseStack = document.createElement('div');
            exerciseStack.className = 'wb-exercise-stack';
            day.exercises.forEach((exercise, exerciseIndex) => {
                exerciseStack.appendChild(renderExercise(exercise, planIndex, dayIndex, exerciseIndex));
            });

            const toolbar = document.createElement('div');
            toolbar.className = 'wb-toolbar';

            const addExerciseButton = document.createElement('button');
            addExerciseButton.type = 'button';
            addExerciseButton.className = 'wb-btn wb-btn--soft';
            addExerciseButton.textContent = 'Add Exercise';
            addExerciseButton.addEventListener('click', () => {
                plans[planIndex].days[dayIndex].exercises.push({
                    ...buildEmptyExercise(),
                    sort_order: plans[planIndex].days[dayIndex].exercises.length + 1,
                });
                renderBuilder();
            });

            toolbar.appendChild(addExerciseButton);

            card.appendChild(header);
            card.appendChild(grid);
            card.appendChild(document.createElement('hr'));
            card.appendChild(exerciseStack);
            card.appendChild(toolbar);

            return card;
        };

        const renderPlan = (plan, planIndex) => {
            const card = document.createElement('div');
            card.className = 'wb-plan-card';

            const header = document.createElement('div');
            header.className = 'wb-card-header';
            header.innerHTML = `
                <div>
                    <div class="wb-chip">Plan ${planIndex + 1}</div>
                    <div class="wb-card-title mt-2">${plan.name || 'Untitled Plan'}</div>
                    <p class="wb-card-subtitle">Training structure, scheduling, and prescription logic</p>
                    <div class="wb-meta-row">
                        ${plan.goal ? `<span class="wb-meta-badge">${plan.goal}</span>` : ''}
                        ${plan.program_type ? `<span class="wb-meta-badge">${plan.program_type}</span>` : ''}
                        ${plan.difficulty ? `<span class="wb-meta-badge">${plan.difficulty}</span>` : ''}
                        ${plan.equipment_profile ? `<span class="wb-meta-badge">${plan.equipment_profile}</span>` : ''}
                    </div>
                </div>
            `;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'wb-btn wb-btn--danger';
            removeButton.textContent = 'Remove Plan';
            removeButton.addEventListener('click', () => {
                plans.splice(planIndex, 1);
                if (plans.length === 0) {
                    plans.push(buildEmptyPlan());
                }
                renderBuilder();
            });
            header.appendChild(removeButton);

            const topGrid = document.createElement('div');
            topGrid.className = 'wb-grid-3';

            topGrid.appendChild(createField({
                label: 'Plan Name',
                value: plan.name,
                placeholder: 'Upper Lower Base',
                onChange: (value) => {
                    plans[planIndex].name = value;
                    syncPayload();
                },
            }));

            topGrid.appendChild(createField({
                label: 'Goal',
                value: plan.goal,
                placeholder: 'General fitness and consistency',
                onChange: (value) => {
                    plans[planIndex].goal = value;
                    syncPayload();
                },
            }));

            topGrid.appendChild(createField({
                label: 'Difficulty',
                type: 'select',
                value: plan.difficulty,
                options: difficultyOptions,
                onChange: (value) => {
                    plans[planIndex].difficulty = value;
                    syncPayload();
                },
            }));

            const middleGrid = document.createElement('div');
            middleGrid.className = 'wb-grid-4';

            middleGrid.appendChild(createField({
                label: 'Program Type',
                type: 'select',
                value: plan.program_type,
                options: programTypeOptions,
                onChange: (value) => {
                    plans[planIndex].program_type = value;
                    syncPayload();
                },
            }));

            middleGrid.appendChild(createField({
                label: 'Equipment',
                type: 'select',
                value: plan.equipment_profile,
                options: equipmentOptions,
                onChange: (value) => {
                    plans[planIndex].equipment_profile = value;
                    syncPayload();
                },
            }));

            middleGrid.appendChild(createField({
                label: 'Duration Weeks',
                type: 'number',
                value: plan.duration_weeks,
                min: 1,
                max: 52,
                onChange: (value) => {
                    plans[planIndex].duration_weeks = Number(value || 1);
                    syncPayload();
                },
            }));

            middleGrid.appendChild(createField({
                label: 'Session Minutes',
                type: 'number',
                value: plan.estimated_session_minutes,
                min: 10,
                max: 240,
                onChange: (value) => {
                    plans[planIndex].estimated_session_minutes = Number(value || 10);
                    syncPayload();
                },
            }));

            const bottomGrid = document.createElement('div');
            bottomGrid.className = 'wb-grid-2';

            bottomGrid.appendChild(createField({
                label: 'Plan Status',
                type: 'select',
                value: plan.status,
                options: statusOptions,
                onChange: (value) => {
                    plans[planIndex].status = value;
                    syncPayload();
                },
            }));

            bottomGrid.appendChild(createField({
                label: 'Plan Notes',
                type: 'textarea',
                value: plan.notes,
                rows: 2,
                placeholder: 'Progression, deload, recovery, coaching notes',
                onChange: (value) => {
                    plans[planIndex].notes = value;
                    syncPayload();
                },
            }));

            const weeklyScheduleField = createWeekdaySelector(plan.weekly_schedule, (dayValue, checked) => {
                const schedule = new Set(plans[planIndex].weekly_schedule || []);
                if (checked) {
                    schedule.add(dayValue);
                } else {
                    schedule.delete(dayValue);
                }
                plans[planIndex].weekly_schedule = Object.keys(weekdayOptions).filter((day) => schedule.has(day));
                syncPayload();
            });

            const dayStack = document.createElement('div');
            dayStack.className = 'wb-day-stack';
            plan.days.forEach((day, dayIndex) => {
                dayStack.appendChild(renderDay(day, planIndex, dayIndex));
            });

            const toolbar = document.createElement('div');
            toolbar.className = 'wb-toolbar';

            const addDayButton = document.createElement('button');
            addDayButton.type = 'button';
            addDayButton.className = 'wb-btn wb-btn--soft';
            addDayButton.textContent = 'Add Day';
            addDayButton.addEventListener('click', () => {
                plans[planIndex].days.push(buildEmptyDay(plans[planIndex].days.length + 1));
                renderBuilder();
            });

            toolbar.appendChild(addDayButton);

            card.appendChild(header);
            card.appendChild(topGrid);
            card.appendChild(document.createElement('div')).className = 'mt-3';
            card.appendChild(middleGrid);
            card.appendChild(document.createElement('div')).className = 'mt-3';
            card.appendChild(bottomGrid);
            card.appendChild(document.createElement('div')).className = 'mt-3';
            card.appendChild(weeklyScheduleField);
            card.appendChild(document.createElement('hr'));
            card.appendChild(dayStack);
            card.appendChild(toolbar);

            return card;
        };

        function renderBuilder() {
            builder.innerHTML = '';
            errorBox.style.display = 'none';

            if (!Array.isArray(plans) || plans.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'wb-empty';
                empty.textContent = 'No plans added yet.';
                builder.appendChild(empty);
            } else {
                plans = plans.map(normalizePlan);
                plans.forEach((plan, planIndex) => {
                    builder.appendChild(renderPlan(plan, planIndex));
                });
            }

            if (exerciseCatalog.length === 0) {
                errorBox.textContent = 'No active exercises are available in the catalog yet. Create exercises first, then assign them inside workout plans.';
                errorBox.style.display = 'block';
            }

            summarize();
            syncPayload();
        }

        addPlanButton.addEventListener('click', () => {
            plans.push(buildEmptyPlan());
            renderBuilder();
        });

        loadSampleButton.addEventListener('click', () => {
            plans = parsePlans(samplePlansJson).map(normalizePlan);
            renderBuilder();
        });

        form.addEventListener('submit', () => {
            syncPayload();
        });

        renderBuilder();
    });
</script>
