<x-public.layouts.enrollment
    :page-title="'Join '.$gym->name"
    :page-description="'Complete your member enrollment for '.$gym->name.'.'"
    :social-image="$gym->logo_url"
>
    @php
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
    @endphp

    <div class="relative overflow-hidden bg-slate-950 text-white">
        @if($gym->cover_image_url)
            <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image: url('{{ $gym->cover_image_url }}')"></div>
        @endif
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(20,184,166,.24),transparent_42%)]"></div>
        <div class="relative mx-auto flex max-w-4xl flex-col items-center px-5 pb-16 pt-10 text-center sm:px-8 sm:pb-20 sm:pt-14">
            @if($gym->logo_url)
                <img src="{{ $gym->logo_url }}" alt="{{ $gym->name }} logo" class="h-24 w-24 rounded-[1.75rem] border-4 border-white/15 bg-white object-cover shadow-2xl sm:h-28 sm:w-28">
            @else
                <div class="flex h-24 w-24 items-center justify-center rounded-[1.75rem] border border-white/15 bg-white/10 text-4xl text-teal-300 shadow-2xl sm:h-28 sm:w-28"><i class="ti ti-building-store"></i></div>
            @endif
            <p class="mt-6 text-xs font-bold uppercase tracking-[.24em] text-teal-300">Member enrollment</p>
            <h1 class="mt-3 text-3xl font-bold tracking-[-.035em] sm:text-5xl">Join {{ $gym->name }}</h1>
            <p class="mt-3 flex items-center gap-2 text-sm text-slate-300"><i class="ti ti-map-pin"></i>{{ $link->branch?->name ?? ($branches->count() > 1 ? 'Choose your branch during enrollment' : ($branches->first()?->name ?? 'Gym membership')) }}</p>
            <p class="mt-5 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">Complete your details securely. Your member profile will be ready for this gym and the member app.</p>
        </div>
    </div>

    <div class="mx-auto -mt-7 max-w-4xl px-4 pb-8 sm:px-6 sm:pb-12">
        @if($errors->any())
            <div class="relative mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800 shadow-sm"><strong>Please review these details.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section id="existing-member-card" class="relative mb-5 overflow-hidden rounded-3xl border border-teal-100 bg-white p-5 shadow-[0_18px_55px_rgba(15,23,42,.10)] sm:p-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-xl">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-[.18em] text-teal-700"><i class="ti ti-user-check text-lg"></i>Already a member?</div>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">Reuse your Atlas profile</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Sign in once, confirm your saved details, and join without filling the form again.</p>
                </div>
                <div class="flex shrink-0 flex-col gap-2 sm:min-w-52">
                    @if($firebaseConfig)
                        <button id="existing-google" type="button" class="public-button public-button-primary justify-center"><i class="ti ti-brand-google"></i> Continue with Google</button>
                        <button id="existing-apple" type="button" class="public-button justify-center border border-slate-300 bg-white text-slate-900"><i class="ti ti-brand-apple"></i> Continue with Apple</button>
                    @else
                        <a href="gymatlasmember:///join/{{ $link->token }}" class="public-button public-button-primary justify-center">Open Member App</a>
                    @endif
                </div>
            </div>
            <p id="existing-status" class="mt-4 hidden rounded-xl border px-4 py-3 text-sm" role="status"></p>
            <div id="existing-preview" class="mt-6 hidden border-t border-slate-200 pt-6">
                <div class="grid gap-5 lg:grid-cols-[1fr_20rem] lg:items-end">
                    <div><h3 id="existing-name" class="text-xl font-semibold text-slate-950"></h3><p id="existing-email" class="mt-1 text-sm text-slate-500"></p><div id="existing-summary" class="mt-4 flex flex-wrap gap-2"></div></div>
                    <div class="space-y-3">
                        @if($link->branch_id === null && $branches->count() > 0)
                            <select id="existing-branch" class="form-control"><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                        @endif
                        <label class="flex items-start gap-3 text-sm text-slate-600"><input id="reuse-profile" type="checkbox" checked class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600"><span>Use my current profile details for this gym.</span></label>
                        <label class="flex items-start gap-3 text-sm text-slate-600"><input id="existing-marketing" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600"><span>Send gym offers on WhatsApp (optional).</span></label>
                        <p class="text-xs leading-5 text-slate-500">By joining, you request membership and service updates from {{ $gym->name }} on WhatsApp. Reply STOP or change this later in the Member App.</p>
                        <button id="existing-join" type="button" class="public-button public-button-primary w-full justify-center">Join {{ $gym->name }}</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden rounded-3xl bg-white shadow-[0_18px_55px_rgba(15,23,42,.10)]">
            <div class="border-b border-slate-200 px-5 py-6 sm:px-8">
                <p class="text-xs font-bold uppercase tracking-[.18em] text-teal-700">New member</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Create your member profile</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">One profile for {{ $gym->name }} and future access to the member app.</p>
            </div>

            <div class="px-5 py-6 sm:px-8 sm:py-8">
                <div class="mb-3 flex items-center justify-between gap-4 text-xs font-semibold text-slate-500"><span id="enroll-step-label">Step 1 of 5</span><span id="enroll-step-name">Details</span></div>
                <div class="mb-8 flex items-center gap-2" aria-label="Enrollment progress">
                    @foreach(['Details','Goals','Profile','Health','Review'] as $step)
                        <div class="min-w-0 flex-1"><div class="enroll-progress h-1.5 rounded-full bg-slate-200" data-progress="{{ $loop->iteration }}" role="progressbar" aria-valuemin="1" aria-valuemax="5" aria-label="{{ $step }}"></div><span class="mt-2 hidden text-center text-[10px] font-bold uppercase tracking-wide text-slate-400 sm:block">{{ $step }}</span></div>
                    @endforeach
                </div>

                <form id="new-enrollment-form" method="POST" action="{{ route('public.self-enrollment.store', $link->token) }}" data-initial-step="{{ $initialStep }}" novalidate>
                    @csrf
                    <input name="website" value="" tabindex="-1" autocomplete="off" class="absolute -left-[10000px]" aria-hidden="true">

                    <div class="enroll-step space-y-5" data-step="1">
                        <div><h3 class="text-xl font-semibold text-slate-950">Your details</h3><p class="mt-1 text-sm text-slate-500">Use the email you will use in the Member App.</p></div>
                        <div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Full name</label><input name="name" value="{{ old('name') }}" class="form-control" autocomplete="name" required></div><div><label class="mb-2 block text-sm font-semibold">Email</label><input name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" required></div></div>
                        <div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Mobile number</label><input name="phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" required></div>@if($link->branch_id === null && $branches->count() > 0)<div><label class="mb-2 block text-sm font-semibold">Branch</label><select name="branch_id" class="form-control" required><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@else<input type="hidden" name="branch_id" value="{{ $link->branch_id }}">@endif</div>
                    </div>

                    <div class="enroll-step space-y-5" data-step="2" hidden><div><h3 class="text-xl font-semibold text-slate-950">What is your goal?</h3><p class="mt-1 text-sm text-slate-500">Choose one or more.</p></div><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($fitnessGoals as $goal)<label class="cursor-pointer rounded-2xl border border-slate-200 p-4 transition has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50"><input type="checkbox" name="fitness_goal_ids[]" value="{{ $goal->id }}" class="mr-2 text-teal-600" @checked(in_array($goal->id, old('fitness_goal_ids', [])))><strong>{{ $goal->name }}</strong><span class="mt-1 block text-xs leading-5 text-slate-500">{{ $goal->description }}</span></label>@endforeach</div><p id="fitness-goal-error" class="text-sm font-medium text-rose-700" hidden>Select at least one fitness goal to continue.</p></div>

                    <div class="enroll-step space-y-5" data-step="3" hidden><div><h3 class="text-xl font-semibold text-slate-950">Your current baseline</h3><p class="mt-1 text-sm text-slate-500">These details help personalize your member profile.</p></div><div><label class="mb-2 block text-sm font-semibold">Experience level</label><div class="grid gap-3 sm:grid-cols-3">@foreach(['beginner','intermediate','advanced'] as $level)<label class="cursor-pointer rounded-2xl border border-slate-200 p-4 text-center capitalize has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50"><input type="radio" name="experience_level" value="{{ $level }}" class="mr-2" @checked(old('experience_level') === $level) required>{{ $level }}</label>@endforeach</div></div><div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Height (cm)</label><input name="height_cm" type="number" min="120" max="230" value="{{ old('height_cm', 173) }}" class="form-control" required></div><div><label class="mb-2 block text-sm font-semibold">Weight (kg)</label><input name="weight_kg" type="number" min="30" max="180" step="0.5" value="{{ old('weight_kg', 80) }}" class="form-control" required></div></div></div>

                    <div class="enroll-step space-y-5" data-step="4" hidden><div><h3 class="text-xl font-semibold text-slate-950">Health context <span class="text-sm font-normal text-slate-400">(optional)</span></h3><p class="mt-1 text-sm text-slate-500">Add anything your future training should respect, or continue without it.</p></div><div><label class="mb-2 block text-sm font-semibold">Injuries or limitations</label><textarea name="injury_notes" rows="4" class="form-control" placeholder="Shoulder restriction, lower back pain, knee discomfort...">{{ old('injury_notes') }}</textarea></div><div><label class="mb-2 block text-sm font-semibold">Medical notes</label><textarea name="medical_notes" rows="4" class="form-control" placeholder="Anything a coach or program should keep in mind.">{{ old('medical_notes') }}</textarea></div><div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Emergency contact name</label><input name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="form-control"></div><div><label class="mb-2 block text-sm font-semibold">Emergency contact phone</label><input name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" class="form-control"></div></div></div>

                    <div class="enroll-step space-y-5" data-step="5" hidden><div><h3 class="text-xl font-semibold text-slate-950">Ready to join {{ $gym->name }}</h3><p class="mt-1 text-sm text-slate-500">Review and confirm your enrollment.</p></div><div id="new-review" class="grid gap-3 rounded-2xl bg-slate-50 p-5 text-sm sm:grid-cols-2"></div><label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm leading-6 text-slate-600"><input name="consent" type="checkbox" value="1" class="mt-1 h-4 w-4 rounded text-teal-600" required><span>I confirm these details, want to enroll at {{ $gym->name }}, and request membership, payment, booking, and other service updates on WhatsApp. I can reply STOP or change this later in the Member App.</span></label><label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm leading-6 text-slate-600"><input name="whatsapp_marketing_consent" type="checkbox" value="1" class="mt-1 h-4 w-4 rounded text-teal-600"><span>Also send me gym offers and campaigns on WhatsApp (optional).</span></label></div>

                    <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-between"><button id="enroll-back" type="button" class="public-button justify-center border border-slate-300 bg-white text-slate-900" hidden>Back</button><button id="enroll-next" type="button" class="public-button public-button-primary justify-center sm:ml-auto" disabled>Continue</button><button id="enroll-submit" type="submit" class="public-button public-button-primary justify-center sm:ml-auto" hidden disabled>Enroll now</button></div>
                </form>
            </div>
        </section>

        <div class="flex items-center justify-center gap-2 py-7 text-xs text-slate-500">
            <img src="{{ asset('images/public-site/brand/atlas-mark-64.png') }}" alt="" class="h-5 w-5 rounded-md">
            <span>Secure enrollment powered by <strong class="font-semibold text-slate-700">Gym Atlas</strong></span>
        </div>
    </div>

    <script>
        (() => {
            const form = document.getElementById('new-enrollment-form');
            const steps = [...form.querySelectorAll('.enroll-step')];
            const progress = [...document.querySelectorAll('.enroll-progress')];
            const back = document.getElementById('enroll-back');
            const next = document.getElementById('enroll-next');
            const submit = document.getElementById('enroll-submit');
            const stepLabel = document.getElementById('enroll-step-label');
            const stepName = document.getElementById('enroll-step-name');
            const goalError = document.getElementById('fitness-goal-error');
            const stepNames = ['Details', 'Goals', 'Profile', 'Health', 'Review'];
            const totalSteps = steps.length;
            let current = Math.min(totalSteps, Math.max(1, Number(form.dataset.initialStep) || 1));
            let submitting = false;

            const stepComplete = stepNumber => {
                if (stepNumber === 2 && !form.querySelector('input[name="fitness_goal_ids[]"]:checked')) return false;
                return [...steps[stepNumber - 1].querySelectorAll('input,select,textarea')].every(field => field.checkValidity());
            };

            const reportStepErrors = stepNumber => {
                if (stepNumber === 2 && !form.querySelector('input[name="fitness_goal_ids[]"]:checked')) {
                    goalError.hidden = false;
                    return false;
                }
                goalError.hidden = true;
                const invalidField = [...steps[stepNumber - 1].querySelectorAll('input,select,textarea')].find(field => !field.checkValidity());
                if (invalidField) {
                    invalidField.reportValidity();
                    invalidField.focus({preventScroll: true});
                    return false;
                }
                return true;
            };

            const updateActions = () => {
                back.hidden = current === 1;
                next.hidden = current === totalSteps;
                submit.hidden = current !== totalSteps;
                next.disabled = !stepComplete(current);
                submit.disabled = submitting || !stepComplete(totalSteps);
                next.textContent = current === totalSteps - 1 ? 'Review details' : 'Continue';
            };

            const render = () => {
                steps.forEach(step => { step.hidden = Number(step.dataset.step) !== current; });
                progress.forEach(item => {
                    const progressStep = Number(item.dataset.progress);
                    const active = progressStep <= current;
                    item.classList.toggle('bg-teal-600', active);
                    item.classList.toggle('bg-slate-200', !active);
                    item.setAttribute('aria-valuenow', String(current));
                    item.setAttribute('aria-current', progressStep === current ? 'step' : 'false');
                });
                stepLabel.textContent = `Step ${current} of ${totalSteps}`;
                stepName.textContent = stepNames[current - 1];
                goalError.hidden = current !== 2 || stepComplete(2);
                if (current === totalSteps) buildReview();
                updateActions();
            };

            const buildReview = () => { const data = new FormData(form); const goals = [...form.querySelectorAll('input[name="fitness_goal_ids[]"]:checked')].map(el => el.closest('label').querySelector('strong').textContent).join(', '); const branch = form.querySelector('[name="branch_id"] option:checked')?.textContent || @json($link->branch?->name ?? 'Gym branch'); document.getElementById('new-review').innerHTML = `<div><strong>Name</strong><br>${escapeHtml(data.get('name'))}</div><div><strong>Email</strong><br>${escapeHtml(data.get('email'))}</div><div><strong>Branch</strong><br>${escapeHtml(branch)}</div><div><strong>Goals</strong><br>${escapeHtml(goals)}</div><div><strong>Experience</strong><br>${escapeHtml(data.get('experience_level'))}</div><div><strong>Baseline</strong><br>${escapeHtml(data.get('height_cm'))} cm · ${escapeHtml(data.get('weight_kg'))} kg</div>`; };
            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
            next.addEventListener('click', () => {
                if (!reportStepErrors(current)) return;
                current++;
                render();
                form.scrollIntoView({behavior:'smooth', block:'start'});
            });
            back.addEventListener('click', () => { current--; render(); });
            form.addEventListener('input', updateActions);
            form.addEventListener('change', () => {
                if (current === 2) goalError.hidden = stepComplete(2);
                updateActions();
            });
            form.addEventListener('submit', event => {
                for (let stepNumber = 1; stepNumber <= totalSteps; stepNumber++) {
                    if (!stepComplete(stepNumber)) {
                        event.preventDefault();
                        current = stepNumber;
                        render();
                        reportStepErrors(stepNumber);
                        return;
                    }
                }
                if (submitting) {
                    event.preventDefault();
                    return;
                }
                submitting = true;
                submit.disabled = true;
                submit.textContent = 'Enrolling…';
            });
            render();
        })();
    </script>

    @if($firebaseConfig)
        <script type="module">
            import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
            import { getAuth, GoogleAuthProvider, OAuthProvider, signInWithPopup } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';
            const app = initializeApp(@json($firebaseConfig)); const auth = getAuth(app); let atlasToken = null;
            const status = document.getElementById('existing-status'); const preview = document.getElementById('existing-preview');
            const showStatus = (message, error = false) => { status.textContent = message; status.classList.remove('hidden','border-rose-200','bg-rose-50','text-rose-800','border-emerald-200','bg-emerald-50','text-emerald-800'); status.classList.add(...(error ? ['border-rose-200','bg-rose-50','text-rose-800'] : ['border-emerald-200','bg-emerald-50','text-emerald-800'])); };
            const api = async (path, options = {}) => { const response = await fetch(path, {headers:{'Accept':'application/json','Content-Type':'application/json', ...(atlasToken ? {'Authorization':`Bearer ${atlasToken}`} : {})}, ...options}); const body = await response.json(); if (!response.ok) throw new Error(body.message || Object.values(body.errors || {}).flat()[0] || 'Request failed.'); return body; };
            const signIn = async provider => { try { showStatus('Opening secure sign-in…'); const credential = await signInWithPopup(auth, provider); const idToken = await credential.user.getIdToken(true); const login = await api('/api/public/auth/firebase/login', {method:'POST', body:JSON.stringify({id_token:idToken, device_name:'gym-enrollment-web', app_type:'member'})}); atlasToken = login.data.token; if (login.data.user?.active_role !== 'member') await api('/api/public/auth/active-role', {method:'POST',body:JSON.stringify({active_role:'member'})}); const result = await api('/api/member/self-enrollment/{{ $link->token }}/preview'); const data = result.data; document.getElementById('existing-name').textContent = data.profile.name; document.getElementById('existing-email').textContent = data.profile.email; const labels = [(data.profile.fitness_goals || []).map(item => item.name).join(', '), data.profile.experience_level, data.profile.height_cm ? `${data.profile.height_cm} cm` : null, data.profile.weight_kg ? `${data.profile.weight_kg} kg` : null].filter(Boolean); const summary = document.getElementById('existing-summary'); summary.innerHTML=''; labels.forEach(label => { const chip=document.createElement('span'); chip.className='rounded-full bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-800'; chip.textContent=label; summary.appendChild(chip); }); preview.classList.remove('hidden'); showStatus(data.already_enrolled ? 'You already belong to this gym.' : data.requires_gym_assistance ? 'Please ask the gym desk to reactivate this relationship.' : 'Profile found. Confirm once to join.'); document.getElementById('existing-join').disabled = data.already_enrolled || data.requires_gym_assistance; } catch (error) { showStatus(error.message, true); } };
            document.getElementById('existing-google').addEventListener('click', () => signIn(new GoogleAuthProvider())); const apple = new OAuthProvider('apple.com'); apple.addScope('email'); apple.addScope('name'); document.getElementById('existing-apple').addEventListener('click', () => signIn(apple));
            document.getElementById('existing-join').addEventListener('click', async () => { try { const branch = document.getElementById('existing-branch'); const body = {consent:true,whatsapp_marketing_consent:document.getElementById('existing-marketing').checked,reuse_profile:document.getElementById('reuse-profile').checked,branch_id:branch ? Number(branch.value) || null : {{ $link->branch_id ?? 'null' }}}; if (branch && !body.branch_id) throw new Error('Choose a branch.'); const result = await api('/api/member/self-enrollment/{{ $link->token }}', {method:'POST',body:JSON.stringify(body)}); showStatus(result.message); preview.classList.add('hidden'); } catch(error) { showStatus(error.message,true); } });
        </script>
    @endif
</x-public.layouts.enrollment>
