@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.workout-books.create') }}">Create Workout Book</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Training Catalog</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Workout Book Catalog</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Publish reusable training books, manage catalog visibility, and route each book into the nested visual plan builder used by the platform.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Published</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $publishedCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">books with publish timestamps</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Featured</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $featuredCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">recommended catalog entries</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Books" :value="$workoutBooks->total()" hint="Catalog programs available" tone="sky" />
            <x-stat-card label="Active" :value="$activeCount" hint="Member-visible catalog books" tone="emerald" />
            <x-stat-card label="Featured" :value="$featuredCount" hint="Recommended in discovery" tone="violet" />
            <x-stat-card label="Inactive" :value="$inactiveCount" hint="Draft or hidden books" tone="amber" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1.8fr)_minmax(220px,1fr)_minmax(240px,1fr)_auto]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Search by name, goal, audience, or description" />
                <x-form-select
                    name="status"
                    label="Status"
                    :selected="request('status')"
                    :options="['' => 'All Statuses', 'active' => 'Active', 'inactive' => 'Inactive']"
                />
                <div>
                    <label class="panel-label">Featured Only</label>
                    <label class="panel-card-muted flex items-center gap-3 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="featured_only" value="1" @checked(request()->boolean('featured_only'))>
                        <span>Show featured books only</span>
                    </label>
                </div>
                <div class="flex items-end gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.workout-books.index') }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Workout Books</h3>
                    <p class="panel-section-copy">Control top-level catalog books and route each one into its nested plan editor with template, duration, and publish-state context.</p>
                </div>
                <x-status-badge :label="$workoutBooks->total().' total'" tone="neutral" />
            </div>

            @if ($workoutBooks->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1320px]">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Audience</th>
                                <th>Program Specs</th>
                                <th>Publishing</th>
                                <th>Creator</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($workoutBooks as $book)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $book->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $book->goal ?: 'No goal defined' }}</div>
                                        <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($book->description, 120) ?: 'No description added yet.' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ $book->audience ?: 'General audience' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $book->difficulty ?: 'Mixed difficulty' }} • {{ str($book->program_type ?: 'custom')->replace('_', ' ')->title() }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>Templates {{ $book->templates_count }}</div>
                                        <div>Days/week {{ $book->days_per_week ?: '--' }}</div>
                                        <div>Duration {{ $book->duration_weeks ?: '--' }} weeks</div>
                                        <div>Session {{ $book->estimated_session_minutes ?: '--' }} min</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :label="str($book->status)->title()" :tone="$book->status === 'active' ? 'success' : 'danger'" />
                                            @if ($book->is_featured)
                                                <x-status-badge label="Featured" tone="info" />
                                            @endif
                                        </div>
                                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $book->published_at ? 'Published '.$book->published_at->format('d M Y') : 'Not published yet' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ $book->creator?->name ?: 'System' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $book->slug }}</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" href="{{ route('web.admin.workout-books.edit', $book) }}" variant="secondary">Edit</x-action-button>
                                            <form
                                                action="{{ route('web.admin.workout-books.destroy', $book) }}"
                                                method="POST"
                                                data-confirm-submit
                                                data-confirm-title="Delete workout book?"
                                                data-confirm-message="This will delete {{ $book->name }} and every nested workout template inside it."
                                                data-confirm-button="Delete"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <x-action-button type="submit" variant="danger">Delete</x-action-button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5">
                    <x-empty-state title="No workout books found" message="Create the first platform workout book to make structured plans available to members." action-label="Create Workout Book" :action-href="route('web.admin.workout-books.create')" />
                </div>
            @endif

            @if ($workoutBooks->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    {{ $workoutBooks->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
