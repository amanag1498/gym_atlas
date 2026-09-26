<x-public.layouts.enrollment
    :page-title="'Join '.$gym->name"
    :page-description="'Complete your member enrollment for '.$gym->name.'.'"
    :social-image="$gym->logo_url ?: asset('images/public-site/brand/atlas-mark-512.png')"
>
    @php
        $gymHasLogo = filled($gym->logo_url);
        $gymLogoUrl = $gymHasLogo
            ? $gym->logo_url
            : asset('images/public-site/brand/atlas-mark-512.png');
        $gymLogoAlt = $gymHasLogo ? $gym->name.' logo' : 'Gym Atlas logo';
        $errorFields = array_keys($errors->toArray());
        $initialStep = 1;
        $stepFields = [
            1 => ['name', 'email', 'phone', 'branch_id', 'website'],
            2 => ['fitness_goal_ids', 'fitness_goal_ids.*'],
            3 => ['gender', 'experience_level', 'height_cm', 'weight_kg'],
            4 => ['injury_notes', 'medical_notes', 'emergency_contact_name', 'emergency_contact_phone'],
            5 => ['consent', 'whatsapp_marketing_consent'],
        ];
        foreach ($stepFields as $stepNumber => $fields) {
            $hasStepError = collect($errorFields)->contains(fn (string $errorField): bool => collect($fields)->contains(
                fn (string $field): bool => $field === $errorField || (str_ends_with($field, '.*') && str_starts_with($errorField, substr($field, 0, -1))),
            ));
            if ($hasStepError) {
                $initialStep = $stepNumber;
                break;
            }
        }
        $branchLabel = $link->branch?->name ?? ($branches->count() > 1 ? 'Multiple branches' : ($branches->first()?->name ?? 'Gym membership'));
    @endphp

    <div class="atlas-enrollment-page min-h-screen bg-slate-100 lg:grid lg:grid-cols-[minmax(20rem,.72fr)_minmax(0,1.28fr)]">
        <aside class="relative hidden min-h-screen overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            @if($gym->cover_image_url)<div class="absolute inset-0 bg-cover bg-center opacity-25" style="background-image: url('{{ $gym->cover_image_url }}')"></div>@endif
            <div class="absolute inset-0 bg-gradient-to-b from-slate-950/30 via-slate-950/75 to-slate-950"></div>
            <div class="absolute -right-24 top-10 h-72 w-72 rounded-full bg-teal-400/20 blur-3xl"></div>
            <div class="relative"><span class="inline-flex rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[.18em] text-teal-200">Member enrollment</span></div>
            <div class="relative py-14">
                <img src="{{ $gymLogoUrl }}" alt="{{ $gymLogoAlt }}" class="h-28 w-28 rounded-[2rem] border-4 border-white/15 bg-white shadow-2xl {{ $gymHasLogo ? 'object-cover' : 'object-contain p-3' }}">
                <h1 class="mt-7 max-w-md text-4xl font-bold tracking-[-.04em]">{{ $gym->name }}</h1>
                <p class="mt-3 flex items-center gap-2 text-sm text-slate-300"><i class="ti ti-map-pin text-teal-300"></i>{{ $branchLabel }}</p>
                <div class="mt-8 flex flex-wrap gap-2 text-xs font-medium text-slate-300">@foreach(['Contact','Goals','Profile','Review'] as $label)<span class="rounded-full border border-white/10 bg-white/5 px-3 py-2">{{ $label }}</span>@endforeach</div>
            </div>
            <div class="relative flex items-center gap-2 text-xs text-slate-400"><img src="{{ asset('images/public-site/brand/atlas-mark-64.png') }}" alt="" class="h-5 w-5 rounded-md"><span>Powered by Gym Atlas</span></div>
        </aside>

        <main class="min-w-0">
            <header class="relative overflow-hidden bg-slate-950 px-5 pb-12 pt-7 text-white lg:hidden">
                @if($gym->cover_image_url)<div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image: url('{{ $gym->cover_image_url }}')"></div>@endif
                <div class="absolute inset-0 bg-gradient-to-b from-slate-950/40 to-slate-950"></div>
                <div class="relative flex items-center gap-4">
                    <img src="{{ $gymLogoUrl }}" alt="{{ $gymLogoAlt }}" class="h-16 w-16 rounded-2xl border-2 border-white/15 bg-white shadow-lg {{ $gymHasLogo ? 'object-cover' : 'object-contain p-2' }}">
                    <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[.18em] text-teal-300">Member enrollment</p><h1 class="mt-1 truncate text-2xl font-bold">{{ $gym->name }}</h1><p class="mt-1 truncate text-xs text-slate-300">{{ $branchLabel }}</p></div>
                </div>
            </header>

            <div class="mx-auto max-w-3xl px-4 pb-8 sm:px-8 lg:flex lg:min-h-screen lg:items-center lg:px-12 lg:py-12">
                <div class="-mt-7 w-full lg:mt-0">
                    @if($errors->any())<div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm" role="alert"><div class="flex gap-3"><i class="ti ti-alert-circle mt-0.5 text-lg" aria-hidden="true"></i><div><strong>Check the highlighted details.</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div></div>@endif

                    <section class="atlas-enrollment-card overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_24px_80px_rgba(15,23,42,.10)]">
                        <div class="border-b border-slate-200/80 bg-white p-5 sm:p-7">
                            <div class="flex items-start justify-between gap-4"><div><p class="text-[11px] font-bold uppercase tracking-[.2em] text-teal-700">Secure enrollment</p><h2 class="mt-1 text-2xl font-bold tracking-[-.035em] text-slate-950">How would you like to join?</h2><p class="mt-2 text-sm leading-6 text-slate-500">Create a profile, or use your existing Gym Atlas account.</p></div><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-xl text-teal-700"><i class="ti ti-shield-check" aria-hidden="true"></i></div></div>
                            <div class="mt-5 grid grid-cols-2 rounded-2xl border border-slate-200 bg-slate-100/80 p-1.5" role="tablist" aria-label="Enrollment method">
                                <button id="new-member-tab" type="button" role="tab" aria-selected="true" aria-controls="new-member-lane" class="flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white shadow-sm"><i class="ti ti-user-plus" aria-hidden="true"></i>New member</button>
                                <button id="existing-member-tab" type="button" role="tab" aria-selected="false" aria-controls="existing-member-lane" class="flex min-h-11 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600"><i class="ti ti-login-2" aria-hidden="true"></i>Atlas account</button>
                            </div>
                        </div>

                        <div id="new-member-lane" role="tabpanel" aria-labelledby="new-member-tab" class="p-5 sm:p-7">
                            <div class="mb-3 flex items-center justify-between text-xs font-semibold"><span id="enroll-step-label" class="rounded-full bg-teal-50 px-3 py-1.5 text-teal-800">Step 1 of 5</span><span id="enroll-step-name" class="text-slate-500">Contact</span></div>
                            <div class="mb-7 grid grid-cols-5 gap-1.5" aria-label="Enrollment progress">@foreach(['Contact','Goals','Profile','Health','Review'] as $step)<div class="enroll-progress h-1.5 rounded-full bg-slate-200 transition-colors duration-300" data-progress="{{ $loop->iteration }}" role="progressbar" aria-valuemin="1" aria-valuemax="5" aria-label="{{ $step }}"></div>@endforeach</div>

                            <form id="new-enrollment-form" method="POST" action="{{ route('public.self-enrollment.store', $link->token) }}" data-initial-step="{{ $initialStep }}" novalidate>
                                @csrf
                                <input name="website" value="" tabindex="-1" autocomplete="off" class="absolute -left-[10000px]" aria-hidden="true">

                                <div class="enroll-step space-y-5" data-step="1">
                                    <div class="enroll-step-heading"><span class="enroll-step-icon"><i class="ti ti-user" aria-hidden="true"></i></span><div><h3>Let’s start with you</h3><p>We’ll use these details for your Gym Atlas membership.</p></div></div>
                                    <div class="enroll-field"><label for="enroll-name">Full name <span>Required</span></label><input id="enroll-name" name="name" value="{{ old('name') }}" class="form-control" autocomplete="name" placeholder="e.g. Aman Agarwal" required>@error('name')<p class="enroll-field-error">{{ $message }}</p>@enderror</div>
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        <div class="enroll-field"><label for="enroll-email">Email address <span>Required</span></label><input id="enroll-email" name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" inputmode="email" placeholder="you@example.com" required>@error('email')<p class="enroll-field-error">{{ $message }}</p>@enderror</div>
                                        <div class="enroll-field"><label for="enroll-phone">Mobile number <span>Required</span></label><input id="enroll-phone" name="phone" type="tel" value="{{ old('phone') }}" class="form-control" autocomplete="tel" inputmode="tel" placeholder="Your 10-digit number" required>@error('phone')<p class="enroll-field-error">{{ $message }}</p>@enderror</div>
                                    </div>
                                    @if($link->branch_id === null && $branches->count() > 0)<div class="enroll-field"><label for="enroll-branch">Preferred branch <span>Required</span></label><select id="enroll-branch" name="branch_id" class="form-control" required><option value="">Select a branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select>@error('branch_id')<p class="enroll-field-error">{{ $message }}</p>@enderror</div>@else<input type="hidden" name="branch_id" value="{{ $link->branch_id }}">@endif
                                </div>

                                <div class="enroll-step space-y-5" data-step="2" hidden>
                                    <div class="enroll-step-heading"><span class="enroll-step-icon"><i class="ti ti-target" aria-hidden="true"></i></span><div><h3>What are you working toward?</h3><p>Choose every goal that matters to you.</p></div></div>
                                    <div class="grid gap-3 sm:grid-cols-2">@foreach($fitnessGoals as $goal)<label class="enroll-choice-card"><input type="checkbox" name="fitness_goal_ids[]" value="{{ $goal->id }}" @checked(in_array($goal->id, old('fitness_goal_ids', [])))><span class="enroll-choice-check"><i class="ti ti-check" aria-hidden="true"></i></span><span class="min-w-0"><strong>{{ $goal->name }}</strong>@if($goal->description)<small>{{ $goal->description }}</small>@endif</span></label>@endforeach</div>
                                    <p id="fitness-goal-error" class="enroll-field-error rounded-xl bg-rose-50 px-3 py-2" role="alert" hidden>Select at least one goal to continue.</p>
                                </div>

                                <div class="enroll-step space-y-5" data-step="3" hidden>
                                    <div class="enroll-step-heading"><span class="enroll-step-icon"><i class="ti ti-activity" aria-hidden="true"></i></span><div><h3>Your current profile</h3><p>This helps your gym tailor plans and recommendations.</p></div></div>
                                    <div class="grid gap-5 sm:grid-cols-2">
                                        <div class="enroll-field"><label for="enroll-dob">Date of birth <span>Optional</span></label><input id="enroll-dob" name="date_of_birth" type="date" max="{{ now()->toDateString() }}" value="{{ old('date_of_birth') }}" class="form-control" autocomplete="bday"></div>
                                        <div class="enroll-field"><label for="enroll-gender">Gender <span>Optional</span></label><select id="enroll-gender" name="gender" class="form-control"><option value="">Prefer not to select</option><option value="female" @selected(old('gender') === 'female')>Female</option><option value="male" @selected(old('gender') === 'male')>Male</option><option value="non_binary" @selected(old('gender') === 'non_binary')>Non-binary</option><option value="prefer_not_to_say" @selected(old('gender') === 'prefer_not_to_say')>Prefer not to say</option></select></div>
                                    </div>
                                    <fieldset><legend class="enroll-label">Training experience <span>Required</span></legend><div class="mt-2 grid grid-cols-3 gap-2">@foreach(['beginner','intermediate','advanced'] as $level)<label class="enroll-segment"><input type="radio" name="experience_level" value="{{ $level }}" class="sr-only" @checked(old('experience_level') === $level) required><span>{{ $level }}</span></label>@endforeach</div></fieldset>
                                    <div class="grid grid-cols-2 gap-4"><div class="enroll-field"><label for="enroll-height">Height <span>cm</span></label><input id="enroll-height" name="height_cm" type="number" min="120" max="230" value="{{ old('height_cm', 173) }}" class="form-control" inputmode="decimal" required></div><div class="enroll-field"><label for="enroll-weight">Weight <span>kg</span></label><input id="enroll-weight" name="weight_kg" type="number" min="30" max="180" step="0.5" value="{{ old('weight_kg', 80) }}" class="form-control" inputmode="decimal" required></div></div>
                                </div>

                                <div class="enroll-step space-y-5" data-step="4" hidden>
                                    <div class="enroll-step-heading"><span class="enroll-step-icon"><i class="ti ti-heart" aria-hidden="true"></i></span><div><h3>Anything your gym should know?</h3><p>All fields on this step are optional and kept with your profile.</p></div></div>
                                    <div class="enroll-field"><label for="enroll-injuries">Injuries or movement limitations <span>Optional</span></label><textarea id="enroll-injuries" name="injury_notes" rows="3" class="form-control" placeholder="Share only what affects your training">{{ old('injury_notes') }}</textarea></div>
                                    <div class="enroll-field"><label for="enroll-medical">Medical notes <span>Optional</span></label><textarea id="enroll-medical" name="medical_notes" rows="3" class="form-control" placeholder="Medication, allergies, or other relevant notes">{{ old('medical_notes') }}</textarea></div>
                                    <div class="grid gap-5 sm:grid-cols-2"><div class="enroll-field"><label for="enroll-emergency-name">Emergency contact <span>Optional</span></label><input id="enroll-emergency-name" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="form-control" autocomplete="name" placeholder="Contact name"></div><div class="enroll-field"><label for="enroll-emergency-phone">Contact number <span>Optional</span></label><input id="enroll-emergency-phone" name="emergency_contact_phone" type="tel" value="{{ old('emergency_contact_phone') }}" class="form-control" autocomplete="tel" inputmode="tel" placeholder="Mobile number"></div></div>
                                </div>

                                <div class="enroll-step space-y-5" data-step="5" hidden>
                                    <div class="enroll-step-heading"><span class="enroll-step-icon"><i class="ti ti-circle-check" aria-hidden="true"></i></span><div><h3>Review and join</h3><p>Confirm your details before creating the membership.</p></div></div>
                                    <div id="new-review" class="enroll-review-grid grid gap-3 text-sm sm:grid-cols-2"></div>
                                    <label class="enroll-consent-card"><input name="consent" type="checkbox" value="1" required><span><strong>Membership consent</strong><small>Enroll me at {{ $gym->name }} and send essential membership and service updates on WhatsApp.</small></span></label>
                                    <label class="enroll-consent-card enroll-consent-secondary"><input name="whatsapp_marketing_consent" type="checkbox" value="1"><span><strong>Offers on WhatsApp</strong><small>Optional updates about gym offers and promotions.</small></span></label>
                                </div>

                                <div class="enroll-actions mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-between"><button id="enroll-back" type="button" class="public-button justify-center border border-slate-300 bg-white text-slate-900" hidden><i class="ti ti-arrow-left" aria-hidden="true"></i>Back</button><button id="enroll-next" type="button" class="public-button public-button-primary justify-center sm:ml-auto" disabled>Continue<i class="ti ti-arrow-right" aria-hidden="true"></i></button><button id="enroll-submit" type="submit" class="public-button public-button-primary justify-center sm:ml-auto" hidden disabled>Join {{ $gym->name }}<i class="ti ti-check" aria-hidden="true"></i></button></div>
                            </form>
                        </div>

                        <div id="existing-member-lane" role="tabpanel" aria-labelledby="existing-member-tab" class="p-5 sm:p-7" hidden>
                            <div class="mx-auto max-w-lg py-3 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-2xl text-teal-700"><i class="ti ti-user-check"></i></div>
                                <h3 class="mt-4 text-xl font-bold text-slate-950">Use your saved profile</h3>
                                <p class="mt-1 text-sm text-slate-500">Sign in and confirm. No form required.</p>
                                <div class="mt-5 grid gap-2 sm:grid-cols-2">@if($firebaseConfig)<button id="existing-google" type="button" class="public-button public-button-primary justify-center"><i class="ti ti-brand-google"></i> Google</button><button id="existing-apple" type="button" class="public-button justify-center border border-slate-300 bg-white text-slate-900"><i class="ti ti-brand-apple"></i> Apple</button>@else<a href="gymatlasmember:///join/{{ $link->token }}" class="public-button public-button-primary justify-center sm:col-span-2">Open Member App</a>@endif</div>
                            </div>
                            <p id="existing-status" class="mt-4 rounded-xl border px-4 py-3 text-sm" role="status" hidden></p>
                            <div id="existing-preview" class="mt-5 border-t border-slate-200 pt-5" hidden>
                                <div><h3 id="existing-name" class="text-lg font-semibold text-slate-950"></h3><p id="existing-email" class="mt-1 text-sm text-slate-500"></p><div id="existing-summary" class="mt-3 flex flex-wrap gap-2"></div></div>
                                <div class="mt-5 space-y-3">@if($link->branch_id === null && $branches->count() > 0)<select id="existing-branch" class="form-control"><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>@endif<label class="flex items-start gap-3 text-sm text-slate-600"><input id="reuse-profile" type="checkbox" checked class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600"><span>Reuse my saved profile details.</span></label><label class="flex items-start gap-3 text-sm text-slate-600"><input id="existing-marketing" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600"><span>Gym offers on WhatsApp (optional).</span></label><button id="existing-join" type="button" class="public-button public-button-primary w-full justify-center">Join {{ $gym->name }}</button></div>
                            </div>
                        </div>
                    </section>
                    <div class="mt-5 flex items-center justify-center gap-2 text-xs text-slate-500 lg:hidden"><img src="{{ asset('images/public-site/brand/atlas-mark-64.png') }}" alt="" class="h-5 w-5 rounded-md"><span>Powered by Gym Atlas</span></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        (() => {
            const newTab = document.getElementById('new-member-tab');
            const existingTab = document.getElementById('existing-member-tab');
            const newLane = document.getElementById('new-member-lane');
            const existingLane = document.getElementById('existing-member-lane');
            const selectLane = lane => {
                const showNew = lane === 'new';
                newLane.hidden = !showNew; existingLane.hidden = showNew;
                newTab.setAttribute('aria-selected', String(showNew)); existingTab.setAttribute('aria-selected', String(!showNew));
                [newTab, existingTab].forEach(tab => tab.classList.remove('bg-slate-950', 'text-white', 'shadow-sm', 'text-slate-600'));
                newTab.classList.add(...(showNew ? ['bg-slate-950', 'text-white', 'shadow-sm'] : ['text-slate-600']));
                existingTab.classList.add(...(!showNew ? ['bg-slate-950', 'text-white', 'shadow-sm'] : ['text-slate-600']));
            };
            newTab.addEventListener('click', () => selectLane('new')); existingTab.addEventListener('click', () => selectLane('existing'));

            const form = document.getElementById('new-enrollment-form');
            const steps = [...form.querySelectorAll('.enroll-step')];
            const progress = [...document.querySelectorAll('.enroll-progress')];
            const back = document.getElementById('enroll-back'); const next = document.getElementById('enroll-next'); const submit = document.getElementById('enroll-submit');
            const stepLabel = document.getElementById('enroll-step-label'); const stepName = document.getElementById('enroll-step-name'); const goalError = document.getElementById('fitness-goal-error');
            const stepNames = ['Contact', 'Goals', 'Profile', 'Health', 'Review']; const totalSteps = steps.length;
            let current = Math.min(totalSteps, Math.max(1, Number(form.dataset.initialStep) || 1)); let submitting = false;
            const stepComplete = stepNumber => {
                if (stepNumber === 2 && !form.querySelector('input[name="fitness_goal_ids[]"]:checked')) return false;
                return [...steps[stepNumber - 1].querySelectorAll('input,select,textarea')].every(field => field.checkValidity());
            };
            const reportStepErrors = stepNumber => {
                if (stepNumber === 2 && !form.querySelector('input[name="fitness_goal_ids[]"]:checked')) { goalError.hidden = false; return false; }
                goalError.hidden = true;
                const invalidField = [...steps[stepNumber - 1].querySelectorAll('input,select,textarea')].find(field => !field.checkValidity());
                if (!invalidField) return true;
                invalidField.classList.add('enroll-control-invalid'); invalidField.setAttribute('aria-invalid', 'true'); invalidField.reportValidity(); invalidField.focus({preventScroll: true}); return false;
            };
            const updateActions = () => { back.hidden = current === 1; next.hidden = current === totalSteps; submit.hidden = current !== totalSteps; next.disabled = !stepComplete(current); submit.disabled = submitting || !stepComplete(totalSteps); next.innerHTML = (current === totalSteps - 1 ? 'Review details' : 'Continue') + '<i class="ti ti-arrow-right" aria-hidden="true"></i>'; };
            const buildReview = () => {
                const data = new FormData(form); const goals = [...form.querySelectorAll('input[name="fitness_goal_ids[]"]:checked')].map(el => el.closest('label').querySelector('strong').textContent).join(', '); const branch = form.querySelector('[name="branch_id"] option:checked')?.textContent || @json($link->branch?->name ?? 'Gym branch');
                const genderLabels = {female: 'Female', male: 'Male', non_binary: 'Non-binary', prefer_not_to_say: 'Prefer not to say'};
                const identity = [data.get('date_of_birth'), genderLabels[data.get('gender')] || null].filter(Boolean).join(' · ') || 'Optional details skipped';
                const entries = [['Name', data.get('name')], ['Branch', branch], ['Goals', goals], ['Identity', identity], ['Profile', String(data.get('experience_level')) + ' · ' + data.get('height_cm') + ' cm · ' + data.get('weight_kg') + ' kg']];
                const review = document.getElementById('new-review'); review.innerHTML = '';
                entries.forEach(entry => { const item = document.createElement('div'); const label = document.createElement('span'); const value = document.createElement('strong'); item.className = 'enroll-review-item'; label.className = 'text-slate-500'; label.textContent = entry[0]; value.className = 'mt-1 block text-slate-950'; value.textContent = entry[1]; item.append(label, value); review.appendChild(item); });
            };
            const render = () => { steps.forEach(step => { step.hidden = Number(step.dataset.step) !== current; }); progress.forEach(item => { const active = Number(item.dataset.progress) <= current; item.classList.toggle('bg-teal-600', active); item.classList.toggle('bg-slate-200', !active); item.setAttribute('aria-valuenow', String(current)); }); stepLabel.textContent = 'Step ' + current + ' of ' + totalSteps; stepName.textContent = stepNames[current - 1]; if (current !== 2) goalError.hidden = true; if (current === totalSteps) buildReview(); updateActions(); };
            next.addEventListener('click', () => { if (!reportStepErrors(current)) return; current++; render(); form.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block:'start'}); });
            back.addEventListener('click', () => { current--; render(); }); form.addEventListener('input', event => { event.target.classList.remove('enroll-control-invalid'); event.target.removeAttribute('aria-invalid'); updateActions(); }); form.addEventListener('change', () => { if (current === 2) goalError.hidden = stepComplete(2); updateActions(); });
            form.addEventListener('submit', event => { for (let stepNumber = 1; stepNumber <= totalSteps; stepNumber++) { if (!stepComplete(stepNumber)) { event.preventDefault(); current = stepNumber; render(); reportStepErrors(stepNumber); return; } } if (submitting) { event.preventDefault(); return; } submitting = true; submit.disabled = true; submit.textContent = 'Enrolling…'; });
            selectLane('new'); render();
        })();
    </script>

    @if($firebaseConfig)
        <script type="module">
            import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
            import { getAuth, GoogleAuthProvider, OAuthProvider, signInWithPopup } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';
            const app = initializeApp(@json($firebaseConfig)); const auth = getAuth(app); let atlasToken = null;
            const status = document.getElementById('existing-status'); const preview = document.getElementById('existing-preview'); const googleButton = document.getElementById('existing-google'); const appleButton = document.getElementById('existing-apple'); const joinButton = document.getElementById('existing-join');
            const showStatus = (message, error = false) => { status.hidden = false; status.textContent = message; status.classList.remove('border-rose-200','bg-rose-50','text-rose-800','border-emerald-200','bg-emerald-50','text-emerald-800'); status.classList.add(...(error ? ['border-rose-200','bg-rose-50','text-rose-800'] : ['border-emerald-200','bg-emerald-50','text-emerald-800'])); };
            const api = async (path, options = {}) => { const response = await fetch(path, {headers:{'Accept':'application/json','Content-Type':'application/json', ...(atlasToken ? {'Authorization':'Bearer ' + atlasToken} : {})}, ...options}); const body = await response.json(); if (!response.ok) throw new Error(body.message || Object.values(body.errors || {}).flat()[0] || 'Request failed.'); return body; };
            const signIn = async provider => { googleButton.disabled = true; appleButton.disabled = true; try { showStatus('Signing in…'); const credential = await signInWithPopup(auth, provider); const idToken = await credential.user.getIdToken(true); const login = await api('/api/public/auth/firebase/login', {method:'POST', body:JSON.stringify({id_token:idToken, device_name:'gym-enrollment-web', app_type:'member'})}); atlasToken = login.data.token; if (login.data.user?.active_role !== 'member') await api('/api/public/auth/active-role', {method:'POST',body:JSON.stringify({active_role:'member'})}); const result = await api('/api/member/self-enrollment/{{ $link->token }}/preview'); const data = result.data; document.getElementById('existing-name').textContent = data.profile.name; document.getElementById('existing-email').textContent = data.profile.email; const labels = [(data.profile.fitness_goals || []).map(item => item.name).join(', '), data.profile.experience_level].filter(Boolean); const summary = document.getElementById('existing-summary'); summary.innerHTML=''; labels.forEach(text => { const chip=document.createElement('span'); chip.className='rounded-full bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-800'; chip.textContent=text; summary.appendChild(chip); }); preview.hidden = false; showStatus(data.already_enrolled ? 'Already enrolled at this gym.' : data.requires_gym_assistance ? 'Ask the gym desk to reactivate your membership.' : 'Profile found. Confirm to join.'); joinButton.disabled = data.already_enrolled || data.requires_gym_assistance; } catch (error) { showStatus(error.message, true); } finally { googleButton.disabled = false; appleButton.disabled = false; } };
            googleButton.addEventListener('click', () => signIn(new GoogleAuthProvider())); const apple = new OAuthProvider('apple.com'); apple.addScope('email'); apple.addScope('name'); appleButton.addEventListener('click', () => signIn(apple));
            joinButton.addEventListener('click', async () => { joinButton.disabled = true; try { const branch = document.getElementById('existing-branch'); const body = {consent:true,whatsapp_marketing_consent:document.getElementById('existing-marketing').checked,reuse_profile:document.getElementById('reuse-profile').checked,branch_id:branch ? Number(branch.value) || null : {{ $link->branch_id ?? 'null' }}}; if (branch && !body.branch_id) throw new Error('Choose a branch.'); const result = await api('/api/member/self-enrollment/{{ $link->token }}', {method:'POST',body:JSON.stringify(body)}); showStatus(result.message); preview.hidden = true; } catch(error) { showStatus(error.message,true); joinButton.disabled = false; } });
        </script>
    @endif
</x-public.layouts.enrollment>
