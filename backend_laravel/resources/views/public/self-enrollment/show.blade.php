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
        $branchLabel = $link->branch?->name ?? ($branches->count() > 1 ? 'Multiple branches' : ($branches->first()?->name ?? 'Gym membership'));
    @endphp

    <div class="min-h-screen bg-slate-100 lg:grid lg:grid-cols-[minmax(20rem,.72fr)_minmax(0,1.28fr)]">
        <aside class="relative hidden min-h-screen overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            @if($gym->cover_image_url)<div class="absolute inset-0 bg-cover bg-center opacity-25" style="background-image: url('{{ $gym->cover_image_url }}')"></div>@endif
            <div class="absolute inset-0 bg-gradient-to-b from-slate-950/30 via-slate-950/75 to-slate-950"></div>
            <div class="absolute -right-24 top-10 h-72 w-72 rounded-full bg-teal-400/20 blur-3xl"></div>
            <div class="relative"><span class="inline-flex rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[.18em] text-teal-200">Member enrollment</span></div>
            <div class="relative py-14">
                @if($gym->logo_url)<img src="{{ $gym->logo_url }}" alt="{{ $gym->name }} logo" class="h-28 w-28 rounded-[2rem] border-4 border-white/15 bg-white object-cover shadow-2xl">@else<div class="flex h-28 w-28 items-center justify-center rounded-[2rem] border border-white/15 bg-white/10 text-4xl text-teal-300 shadow-2xl"><i class="ti ti-building-store"></i></div>@endif
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
                    @if($gym->logo_url)<img src="{{ $gym->logo_url }}" alt="{{ $gym->name }} logo" class="h-16 w-16 rounded-2xl border-2 border-white/15 bg-white object-cover shadow-lg">@else<div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-2xl text-teal-300"><i class="ti ti-building-store"></i></div>@endif
                    <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[.18em] text-teal-300">Member enrollment</p><h1 class="mt-1 truncate text-2xl font-bold">{{ $gym->name }}</h1><p class="mt-1 truncate text-xs text-slate-300">{{ $branchLabel }}</p></div>
                </div>
            </header>

            <div class="mx-auto max-w-3xl px-4 pb-8 sm:px-8 lg:flex lg:min-h-screen lg:items-center lg:px-12 lg:py-12">
                <div class="-mt-7 w-full lg:mt-0">
                    @if($errors->any())<div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm"><strong>Check the highlighted step.</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

                    <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_24px_80px_rgba(15,23,42,.10)]">
                        <div class="border-b border-slate-200 p-5 sm:p-7">
                            <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-teal-700">Join the gym</p><h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Choose how to continue</h2></div><i class="ti ti-shield-check mt-1 text-2xl text-teal-600" aria-hidden="true"></i></div>
                            <div class="mt-5 grid grid-cols-2 rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Enrollment method">
                                <button id="new-member-tab" type="button" role="tab" aria-selected="true" aria-controls="new-member-lane" class="rounded-lg bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white shadow-sm">New member</button>
                                <button id="existing-member-tab" type="button" role="tab" aria-selected="false" aria-controls="existing-member-lane" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-600">Atlas account</button>
                            </div>
                        </div>

                        <div id="new-member-lane" role="tabpanel" aria-labelledby="new-member-tab" class="p-5 sm:p-7">
                            <div class="mb-3 flex items-center justify-between text-xs font-semibold text-slate-500"><span id="enroll-step-label">Step 1 of 5</span><span id="enroll-step-name">Contact</span></div>
                            <div class="mb-7 grid grid-cols-5 gap-2" aria-label="Enrollment progress">@foreach(['Contact','Goals','Profile','Health','Review'] as $step)<div class="enroll-progress h-1.5 rounded-full bg-slate-200" data-progress="{{ $loop->iteration }}" role="progressbar" aria-valuemin="1" aria-valuemax="5" aria-label="{{ $step }}"></div>@endforeach</div>

                            <form id="new-enrollment-form" method="POST" action="{{ route('public.self-enrollment.store', $link->token) }}" data-initial-step="{{ $initialStep }}" novalidate>
                                @csrf
                                <input name="website" value="" tabindex="-1" autocomplete="off" class="absolute -left-[10000px]" aria-hidden="true">

                                <div class="enroll-step space-y-5" data-step="1">
                                    <h3 class="text-xl font-semibold text-slate-950">Contact details</h3>
                                    <div><label class="mb-2 block text-sm font-semibold">Full name</label><input name="name" value="{{ old('name') }}" class="form-control" autocomplete="name" placeholder="Your full name" required></div>
                                    <div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Email</label><input name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" placeholder="you@example.com" required></div><div><label class="mb-2 block text-sm font-semibold">Mobile number</label><input name="phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" inputmode="tel" placeholder="Your mobile number" required></div></div>
                                    @if($link->branch_id === null && $branches->count() > 0)<div><label class="mb-2 block text-sm font-semibold">Branch</label><select name="branch_id" class="form-control" required><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@else<input type="hidden" name="branch_id" value="{{ $link->branch_id }}">@endif
                                </div>

                                <div class="enroll-step space-y-5" data-step="2" hidden>
                                    <div><h3 class="text-xl font-semibold text-slate-950">Fitness goals</h3><p class="mt-1 text-sm text-slate-500">Pick all that apply.</p></div>
                                    <div class="grid gap-3 sm:grid-cols-2">@foreach($fitnessGoals as $goal)<label class="cursor-pointer rounded-2xl border border-slate-200 p-4 transition has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50"><div class="flex items-start gap-3"><input type="checkbox" name="fitness_goal_ids[]" value="{{ $goal->id }}" class="mt-1 text-teal-600" @checked(in_array($goal->id, old('fitness_goal_ids', [])))><span><strong class="block text-sm text-slate-900">{{ $goal->name }}</strong>@if($goal->description)<small class="mt-1 block leading-5 text-slate-500">{{ $goal->description }}</small>@endif</span></div></label>@endforeach</div>
                                    <p id="fitness-goal-error" class="text-sm font-medium text-rose-700" hidden>Select at least one goal.</p>
                                </div>

                                <div class="enroll-step space-y-5" data-step="3" hidden>
                                    <h3 class="text-xl font-semibold text-slate-950">Current profile</h3>
                                    <div><label class="mb-2 block text-sm font-semibold">Experience</label><div class="grid grid-cols-3 gap-2">@foreach(['beginner','intermediate','advanced'] as $level)<label class="cursor-pointer rounded-xl border border-slate-200 px-2 py-3 text-center text-sm capitalize has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50"><input type="radio" name="experience_level" value="{{ $level }}" class="sr-only" @checked(old('experience_level') === $level) required><span>{{ $level }}</span></label>@endforeach</div></div>
                                    <div class="grid grid-cols-2 gap-4"><div><label class="mb-2 block text-sm font-semibold">Height <span class="font-normal text-slate-400">cm</span></label><input name="height_cm" type="number" min="120" max="230" value="{{ old('height_cm', 173) }}" class="form-control" inputmode="decimal" required></div><div><label class="mb-2 block text-sm font-semibold">Weight <span class="font-normal text-slate-400">kg</span></label><input name="weight_kg" type="number" min="30" max="180" step="0.5" value="{{ old('weight_kg', 80) }}" class="form-control" inputmode="decimal" required></div></div>
                                </div>

                                <div class="enroll-step space-y-5" data-step="4" hidden>
                                    <div><h3 class="text-xl font-semibold text-slate-950">Health details <span class="text-sm font-normal text-slate-400">optional</span></h3><p class="mt-1 text-sm text-slate-500">Share only what your gym should know.</p></div>
                                    <div><label class="mb-2 block text-sm font-semibold">Injuries or limitations</label><textarea name="injury_notes" rows="3" class="form-control" placeholder="Optional">{{ old('injury_notes') }}</textarea></div>
                                    <div><label class="mb-2 block text-sm font-semibold">Medical notes</label><textarea name="medical_notes" rows="3" class="form-control" placeholder="Optional">{{ old('medical_notes') }}</textarea></div>
                                    <div class="grid gap-4 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Emergency contact</label><input name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="form-control" placeholder="Name"></div><div><label class="mb-2 block text-sm font-semibold">Contact number</label><input name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" class="form-control" inputmode="tel" placeholder="Mobile number"></div></div>
                                </div>

                                <div class="enroll-step space-y-5" data-step="5" hidden>
                                    <h3 class="text-xl font-semibold text-slate-950">Review & join</h3>
                                    <div id="new-review" class="grid gap-3 rounded-2xl bg-slate-50 p-4 text-sm sm:grid-cols-2"></div>
                                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm leading-6 text-slate-600"><input name="consent" type="checkbox" value="1" class="mt-1 h-4 w-4 shrink-0 rounded text-teal-600" required><span>Enroll me at {{ $gym->name }} and send essential membership and service updates on WhatsApp.</span></label>
                                    <label class="flex items-start gap-3 px-1 text-sm leading-6 text-slate-500"><input name="whatsapp_marketing_consent" type="checkbox" value="1" class="mt-1 h-4 w-4 shrink-0 rounded text-teal-600"><span>Gym offers on WhatsApp (optional).</span></label>
                                </div>

                                <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-between"><button id="enroll-back" type="button" class="public-button justify-center border border-slate-300 bg-white text-slate-900" hidden>Back</button><button id="enroll-next" type="button" class="public-button public-button-primary justify-center sm:ml-auto" disabled>Continue</button><button id="enroll-submit" type="submit" class="public-button public-button-primary justify-center sm:ml-auto" hidden disabled>Enroll now</button></div>
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
                invalidField.reportValidity(); invalidField.focus({preventScroll: true}); return false;
            };
            const updateActions = () => { back.hidden = current === 1; next.hidden = current === totalSteps; submit.hidden = current !== totalSteps; next.disabled = !stepComplete(current); submit.disabled = submitting || !stepComplete(totalSteps); next.textContent = current === totalSteps - 1 ? 'Review details' : 'Continue'; };
            const buildReview = () => {
                const data = new FormData(form); const goals = [...form.querySelectorAll('input[name="fitness_goal_ids[]"]:checked')].map(el => el.closest('label').querySelector('strong').textContent).join(', '); const branch = form.querySelector('[name="branch_id"] option:checked')?.textContent || @json($link->branch?->name ?? 'Gym branch');
                const entries = [['Name', data.get('name')], ['Branch', branch], ['Goals', goals], ['Profile', String(data.get('experience_level')) + ' · ' + data.get('height_cm') + ' cm · ' + data.get('weight_kg') + ' kg']];
                const review = document.getElementById('new-review'); review.innerHTML = '';
                entries.forEach(entry => { const item = document.createElement('div'); const label = document.createElement('span'); const value = document.createElement('strong'); label.className = 'text-slate-400'; label.textContent = entry[0]; value.className = 'block'; value.textContent = entry[1]; item.append(label, value); review.appendChild(item); });
            };
            const render = () => { steps.forEach(step => { step.hidden = Number(step.dataset.step) !== current; }); progress.forEach(item => { const active = Number(item.dataset.progress) <= current; item.classList.toggle('bg-teal-600', active); item.classList.toggle('bg-slate-200', !active); item.setAttribute('aria-valuenow', String(current)); }); stepLabel.textContent = 'Step ' + current + ' of ' + totalSteps; stepName.textContent = stepNames[current - 1]; goalError.hidden = current !== 2 || stepComplete(2); if (current === totalSteps) buildReview(); updateActions(); };
            next.addEventListener('click', () => { if (!reportStepErrors(current)) return; current++; render(); form.scrollIntoView({behavior:'smooth', block:'center'}); });
            back.addEventListener('click', () => { current--; render(); }); form.addEventListener('input', updateActions); form.addEventListener('change', () => { if (current === 2) goalError.hidden = stepComplete(2); updateActions(); });
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
