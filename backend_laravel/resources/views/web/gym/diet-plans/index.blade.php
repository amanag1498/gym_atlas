@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <div class="grid gap-3 lg:grid-cols-4">
            <x-stat-card label="Visible plans" :value="$plans->total()" hint="Current gym and branch scope" tone="sky" />
            <x-stat-card label="Active" :value="$plans->getCollection()->where('status', 'active')->count()" hint="Members can log these plans" tone="emerald" />
            <x-stat-card label="Paused" :value="$plans->getCollection()->where('status', 'inactive')->count()" hint="Hidden from member diet view" tone="amber" />
            <x-stat-card label="Members" :value="$members->count()" hint="Eligible to receive a plan" tone="violet" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
            <x-premium-card class="p-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:text-sky-300">Nutrition workspace</p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Assign diet plan</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Create a practical daily plan. Members can tick off meals in the member app.</p>
                </div>
                @if ($canManageDietPlans)
                    <form method="POST" action="{{ route('web.gym.diet-plans.store', request()->only(['gym', 'branch'])) }}" class="mt-6 space-y-4">
                        @csrf
                        <x-form-select name="diet_template_id" label="Start from global template (optional)"><option value="">Build a custom plan</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected((int) old('diet_template_id') === $template->id)>{{ $template->name }}</option>@endforeach</x-form-select>
                        <x-form-select name="member_id" label="Member" required>
                            <option value="">Select member</option>
                            @foreach ($members as $profile)
                                <option value="{{ $profile->user_id }}" @selected((int) old('member_id') === $profile->user_id)>{{ $profile->user?->name ?? 'Member #'.$profile->user_id }}</option>
                            @endforeach
                        </x-form-select>
                        <x-form-input name="name" label="Plan name" :value="old('name')" placeholder="Optional override when using a template" />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-form-input name="starts_on" type="date" label="Starts on" :value="old('starts_on')" />
                            <x-form-input name="ends_on" type="date" label="Ends on" :value="old('ends_on')" />
                        </div>
                        <p class="hidden rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200" data-template-copy-note>
                            The template’s targets, guidance, meals and products will be copied. Only the member, optional name and schedule above can override it.
                        </p>
                        <div class="space-y-4" data-custom-diet-fields>
                            <x-form-input name="goal" label="Goal" :value="old('goal')" placeholder="Fat loss, strength, general wellness..." />
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-form-input name="daily_calorie_target" type="number" min="0" label="Daily calories" :value="old('daily_calorie_target')" />
                                <x-form-input name="protein_target_g" type="number" min="0" step="0.1" label="Protein (g)" :value="old('protein_target_g')" />
                                <x-form-input name="carbs_target_g" type="number" min="0" step="0.1" label="Carbs (g)" :value="old('carbs_target_g')" />
                                <x-form-input name="fats_target_g" type="number" min="0" step="0.1" label="Fats (g)" :value="old('fats_target_g')" />
                            </div>
                            <textarea name="dietary_preferences" class="panel-textarea" placeholder="Dietary preferences (vegetarian, halal, etc.)">{{ old('dietary_preferences') }}</textarea>
                            <textarea name="allergies_and_restrictions" class="panel-textarea" placeholder="Allergies and restrictions">{{ old('allergies_and_restrictions') }}</textarea>
                            <textarea name="notes" class="panel-textarea" placeholder="Coach notes for the member">{{ old('notes') }}</textarea>
                            <x-diet-builder :meals="old('meals', [])" />
                        </div>
                        <x-action-button type="submit" class="w-full justify-center">Assign Diet Plan</x-action-button>
                    </form>
                @else
                    <x-empty-state title="Diet plan management unavailable" message="Your role can view current diet plans but cannot create or pause them." />
                @endif
            </x-premium-card>

            <x-table-wrapper>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 class="panel-section-title">Assigned diet plans</h3><p class="panel-section-copy">Active plans appear in the member app only during their scheduled dates.</p></div>
                    <form method="GET" class="flex gap-2"><input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}"><select name="status" class="panel-select"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Paused</option></select><x-action-button type="submit" variant="secondary">Filter</x-action-button></form>
                </div>
                <div class="mt-6 overflow-x-auto"><table class="panel-table"><thead><tr><th>Plan</th><th>Member</th><th>Targets</th><th>Schedule</th><th>Status</th><th></th></tr></thead><tbody>
                    @forelse ($plans as $plan)
                        <tr><td><p class="font-medium text-slate-950 dark:text-white">{{ $plan->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $plan->meals->count() }} meal slots · {{ $plan->goal ?: 'No goal set' }}</p></td><td>{{ $plan->member?->name ?? 'Member' }}</td><td>{{ $plan->daily_calorie_target ?? '—' }} kcal<br><span class="text-xs text-slate-500">P {{ $plan->protein_target_g ?? '—' }} · C {{ $plan->carbs_target_g ?? '—' }} · F {{ $plan->fats_target_g ?? '—' }}</span></td><td class="text-sm">{{ $plan->starts_on?->format('d M Y') ?? 'Now' }}<br>{{ $plan->ends_on?->format('d M Y') ?? 'Ongoing' }}</td><td><x-status-badge :label="ucfirst($plan->status)" :tone="$plan->status === 'active' ? 'success' : 'warning'" />@if($canManageDietPlans)<form class="mt-2" method="POST" action="{{ route('web.gym.diet-plans.status', ['dietPlan' => $plan->id] + request()->only(['gym', 'branch'])) }}">@csrf<input type="hidden" name="status" value="{{ $plan->status === 'active' ? 'inactive' : 'active' }}"><x-action-button type="submit" variant="secondary">{{ $plan->status === 'active' ? 'Pause' : 'Resume' }}</x-action-button></form>@endif</td><td>@if($canManageDietPlans)<x-action-button as="a" variant="secondary" href="{{ route('web.gym.diet-plans.edit', ['dietPlan' => $plan->id] + request()->only(['gym', 'branch'])) }}">Edit</x-action-button>@endif</td></tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="No diet plans yet" message="Assign a member's first nutrition plan from this workspace." /></td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="mt-5">{{ $plans->links() }}</div>
            </x-table-wrapper>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const template = document.getElementById('diet_template_id');
            const customFields = document.querySelector('[data-custom-diet-fields]');
            const copyNote = document.querySelector('[data-template-copy-note]');
            const planName = document.getElementById('name');
            if (!template || !customFields || !copyNote || !planName) return;

            const syncTemplateMode = () => {
                const usingTemplate = template.value !== '';
                customFields.classList.toggle('hidden', usingTemplate);
                copyNote.classList.toggle('hidden', !usingTemplate);
                planName.required = !usingTemplate;
            };

            template.addEventListener('change', syncTemplateMode);
            syncTemplateMode();
        });
    </script>
@endpush
