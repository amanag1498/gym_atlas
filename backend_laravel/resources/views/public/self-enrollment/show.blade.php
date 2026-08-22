<x-public.layouts.app
    :page-title="'Join '.$gym->name"
    :page-description="'Complete your member profile and join '.$gym->name.' through Gym Atlas.'"
    robots="noindex, nofollow"
>
    <section class="bg-slate-950 pb-16 pt-32 text-white sm:pt-40">
        <div class="public-container grid items-center gap-8 lg:grid-cols-[1fr_.6fr]">
            <div>
                <p class="public-eyebrow">Gym desk enrollment</p>
                <h1 class="mt-5 text-4xl font-semibold tracking-[-.05em] sm:text-6xl">Join {{ $gym->name }}.</h1>
                <p class="mt-5 max-w-2xl text-base leading-8 text-slate-300">Use your existing Atlas profile in one tap, or complete the same focused setup used by the Member App.</p>
            </div>
            <div class="rounded-[2rem] border border-white/10 bg-white/[.07] p-6 backdrop-blur-xl">
                <div class="flex items-center gap-4">
                    @if($gym->logo_url)<img src="{{ $gym->logo_url }}" alt="" class="h-16 w-16 rounded-2xl object-cover">@else<div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-teal-500/20 text-2xl"><i class="ti ti-building-store"></i></div>@endif
                    <div><div class="text-xl font-semibold">{{ $gym->name }}</div><div class="mt-1 text-sm text-slate-300">{{ $link->branch?->name ?? 'Choose your branch below' }}</div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="public-section bg-[#f3f6fa]">
        <div class="public-container-wide">
            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800"><strong>Please review these details.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div id="existing-member-card" class="mb-8 rounded-[2rem] border border-teal-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div><p class="public-kicker">Already have the Member App?</p><h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Reuse your Atlas profile.</h2><p class="mt-2 max-w-2xl text-sm leading-7 text-slate-600">No new account and no repeated onboarding. Sign in once, review your saved profile, and join this gym.</p></div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        @if($firebaseConfig)
                            <button id="existing-google" type="button" class="public-button public-button-primary"><i class="ti ti-brand-google"></i> Continue with Google</button>
                            <button id="existing-apple" type="button" class="public-button border border-slate-300 bg-white text-slate-900"><i class="ti ti-brand-apple"></i> Continue with Apple</button>
                        @else
                            <a href="gymatlasmember:///join/{{ $link->token }}" class="public-button public-button-primary">Open Member App</a>
                        @endif
                    </div>
                </div>
                <p id="existing-status" class="mt-4 hidden rounded-xl border px-4 py-3 text-sm" role="status"></p>
                <div id="existing-preview" class="mt-6 hidden border-t border-slate-200 pt-6">
                    <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div><h3 id="existing-name" class="text-xl font-semibold text-slate-950"></h3><p id="existing-email" class="mt-1 text-sm text-slate-500"></p><div id="existing-summary" class="mt-4 flex flex-wrap gap-2"></div></div>
                        <div class="space-y-3">
                            @if($link->branch_id === null && $branches->count() > 0)
                                <select id="existing-branch" class="form-control min-w-56"><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                            @endif
                            <label class="flex items-start gap-3 text-sm text-slate-600"><input id="reuse-profile" type="checkbox" checked class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600"><span>Use my current Atlas profile, including health context, for this gym.</span></label>
                            <label class="flex items-start gap-3 text-sm text-slate-600"><input id="existing-marketing" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600"><span>Also send me gym offers and campaigns on WhatsApp (optional).</span></label>
                            <p class="text-xs leading-5 text-slate-500">By joining, you ask {{ $gym->name }} to send membership, payment, booking, and service updates to your mobile number on WhatsApp. Reply STOP or change this later in the Member App.</p>
                            <button id="existing-join" type="button" class="public-button public-button-primary w-full justify-center">Join {{ $gym->name }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-[2rem] bg-white p-6 shadow-[0_24px_70px_rgba(15,23,42,.09)] sm:p-10">
                <div class="border-b border-slate-200 pb-6"><p class="public-kicker">New to Gym Atlas</p><h2 class="mt-3 text-3xl font-semibold tracking-[-.04em] text-slate-950">Create your reusable member profile.</h2><p class="mt-3 text-sm leading-7 text-slate-600">This profile enrolls you now and will be waiting when you later sign in to the Member App with the same email.</p></div>

                <div class="mt-7 grid grid-cols-5 gap-2" aria-label="Enrollment progress">@foreach(['Details','Goals','Profile','Health','Review'] as $step)<div class="enroll-progress rounded-full bg-slate-200 px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500" data-progress="{{ $loop->iteration }}">{{ $step }}</div>@endforeach</div>

                <form id="new-enrollment-form" method="POST" action="{{ route('public.self-enrollment.store', $link->token) }}" class="mt-8">
                    @csrf
                    <input name="website" value="" tabindex="-1" autocomplete="off" class="absolute -left-[10000px]" aria-hidden="true">

                    <div class="enroll-step space-y-5" data-step="1">
                        <div><h3 class="text-2xl font-semibold text-slate-950">Your details</h3><p class="mt-2 text-sm text-slate-500">Use the email you will use in the Member App.</p></div>
                        <div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Full name</label><input name="name" value="{{ old('name') }}" class="form-control" autocomplete="name" required></div><div><label class="mb-2 block text-sm font-semibold">Email</label><input name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" required></div></div>
                        <div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Mobile number</label><input name="phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" required></div>@if($link->branch_id === null && $branches->count() > 0)<div><label class="mb-2 block text-sm font-semibold">Branch</label><select name="branch_id" class="form-control" required><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@else<input type="hidden" name="branch_id" value="{{ $link->branch_id }}">@endif</div>
                    </div>

                    <div class="enroll-step hidden space-y-5" data-step="2"><div><h3 class="text-2xl font-semibold text-slate-950">What do you want to optimize for?</h3><p class="mt-2 text-sm text-slate-500">Choose one or more fitness goals.</p></div><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($fitnessGoals as $goal)<label class="cursor-pointer rounded-2xl border border-slate-200 p-4 transition has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50"><input type="checkbox" name="fitness_goal_ids[]" value="{{ $goal->id }}" class="mr-2 text-teal-600" @checked(in_array($goal->id, old('fitness_goal_ids', [])))><strong>{{ $goal->name }}</strong><span class="mt-1 block text-xs leading-5 text-slate-500">{{ $goal->description }}</span></label>@endforeach</div></div>

                    <div class="enroll-step hidden space-y-5" data-step="3"><div><h3 class="text-2xl font-semibold text-slate-950">Set your current baseline</h3><p class="mt-2 text-sm text-slate-500">The same profile details used by the Member App.</p></div><div><label class="mb-2 block text-sm font-semibold">Experience level</label><div class="grid gap-3 sm:grid-cols-3">@foreach(['beginner','intermediate','advanced'] as $level)<label class="cursor-pointer rounded-2xl border border-slate-200 p-4 text-center capitalize has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50"><input type="radio" name="experience_level" value="{{ $level }}" class="mr-2" @checked(old('experience_level') === $level) required>{{ $level }}</label>@endforeach</div></div><div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Height (cm)</label><input name="height_cm" type="number" min="120" max="230" value="{{ old('height_cm', 173) }}" class="form-control" required></div><div><label class="mb-2 block text-sm font-semibold">Weight (kg)</label><input name="weight_kg" type="number" min="30" max="180" step="0.5" value="{{ old('weight_kg', 80) }}" class="form-control" required></div></div></div>

                    <div class="enroll-step hidden space-y-5" data-step="4"><div><h3 class="text-2xl font-semibold text-slate-950">Health context</h3><p class="mt-2 text-sm text-slate-500">Optional. Add anything future training should respect.</p></div><div><label class="mb-2 block text-sm font-semibold">Injuries or limitations</label><textarea name="injury_notes" rows="4" class="form-control" placeholder="Shoulder restriction, lower back pain, knee discomfort...">{{ old('injury_notes') }}</textarea></div><div><label class="mb-2 block text-sm font-semibold">Medical notes</label><textarea name="medical_notes" rows="4" class="form-control" placeholder="Anything a coach or program should keep in mind.">{{ old('medical_notes') }}</textarea></div><div class="grid gap-5 sm:grid-cols-2"><div><label class="mb-2 block text-sm font-semibold">Emergency contact name</label><input name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="form-control"></div><div><label class="mb-2 block text-sm font-semibold">Emergency contact phone</label><input name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" class="form-control"></div></div></div>

                    <div class="enroll-step hidden space-y-5" data-step="5"><div><h3 class="text-2xl font-semibold text-slate-950">Ready to join {{ $gym->name }}</h3><p class="mt-2 text-sm text-slate-500">Your Atlas member profile and gym relationship will be created immediately.</p></div><div id="new-review" class="grid gap-3 rounded-2xl bg-slate-50 p-5 text-sm sm:grid-cols-2"></div><label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm leading-6 text-slate-600"><input name="consent" type="checkbox" value="1" class="mt-1 h-4 w-4 rounded text-teal-600" required><span>I confirm these details, want to enroll at {{ $gym->name }}, and ask the gym to send membership, payment, booking, and other service updates to my mobile number on WhatsApp. I can reply STOP or change this later in the Member App.</span></label><label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm leading-6 text-slate-600"><input name="whatsapp_marketing_consent" type="checkbox" value="1" class="mt-1 h-4 w-4 rounded text-teal-600"><span>Also send me gym offers and campaigns on WhatsApp (optional).</span></label></div>

                    <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-between"><button id="enroll-back" type="button" class="public-button border border-slate-300 bg-white text-slate-900 hidden">Back</button><button id="enroll-next" type="button" class="public-button public-button-primary sm:ml-auto">Continue</button><button id="enroll-submit" type="submit" class="public-button public-button-primary hidden sm:ml-auto">Enroll now</button></div>
                </form>
            </div>
        </div>
    </section>

    <script>
        (() => {
            const form = document.getElementById('new-enrollment-form');
            const steps = [...form.querySelectorAll('.enroll-step')];
            const progress = [...document.querySelectorAll('.enroll-progress')];
            const back = document.getElementById('enroll-back');
            const next = document.getElementById('enroll-next');
            const submit = document.getElementById('enroll-submit');
            let current = {{ $errors->any() ? 1 : 1 }};
            const render = () => { steps.forEach(el => el.classList.toggle('hidden', Number(el.dataset.step) !== current)); progress.forEach(el => { const active = Number(el.dataset.progress) <= current; el.classList.toggle('bg-teal-600', active); el.classList.toggle('text-white', active); el.classList.toggle('bg-slate-200', !active); }); back.classList.toggle('hidden', current === 1); next.classList.toggle('hidden', current === 5); submit.classList.toggle('hidden', current !== 5); if (current === 5) buildReview(); };
            const currentFieldsValid = () => { const fields = [...steps[current - 1].querySelectorAll('input,select,textarea')]; for (const field of fields) { if (!field.checkValidity()) { field.reportValidity(); return false; } } if (current === 2 && !form.querySelector('input[name="fitness_goal_ids[]"]:checked')) { alert('Select at least one fitness goal.'); return false; } return true; };
            const buildReview = () => { const data = new FormData(form); const goals = [...form.querySelectorAll('input[name="fitness_goal_ids[]"]:checked')].map(el => el.closest('label').querySelector('strong').textContent).join(', '); const branch = form.querySelector('[name="branch_id"] option:checked')?.textContent || @json($link->branch?->name ?? 'Gym branch'); document.getElementById('new-review').innerHTML = `<div><strong>Name</strong><br>${escapeHtml(data.get('name'))}</div><div><strong>Email</strong><br>${escapeHtml(data.get('email'))}</div><div><strong>Branch</strong><br>${escapeHtml(branch)}</div><div><strong>Goals</strong><br>${escapeHtml(goals)}</div><div><strong>Experience</strong><br>${escapeHtml(data.get('experience_level'))}</div><div><strong>Baseline</strong><br>${escapeHtml(data.get('height_cm'))} cm · ${escapeHtml(data.get('weight_kg'))} kg</div>`; };
            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
            next.addEventListener('click', () => { if (currentFieldsValid()) { current++; render(); window.scrollTo({top: form.offsetTop - 120, behavior:'smooth'}); } }); back.addEventListener('click', () => { current--; render(); }); render();
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
            const signIn = async provider => { try { showStatus('Opening secure Atlas sign-in…'); const credential = await signInWithPopup(auth, provider); const idToken = await credential.user.getIdToken(true); const login = await api('/api/public/auth/firebase/login', {method:'POST', body:JSON.stringify({id_token:idToken, device_name:'gym-enrollment-web', app_type:'member'})}); atlasToken = login.data.token; if (login.data.user?.active_role !== 'member') await api('/api/public/auth/active-role', {method:'POST',body:JSON.stringify({active_role:'member'})}); const result = await api('/api/member/self-enrollment/{{ $link->token }}/preview'); const data = result.data; document.getElementById('existing-name').textContent = data.profile.name; document.getElementById('existing-email').textContent = data.profile.email; const labels = [(data.profile.fitness_goals || []).map(item => item.name).join(', '), data.profile.experience_level, data.profile.height_cm ? `${data.profile.height_cm} cm` : null, data.profile.weight_kg ? `${data.profile.weight_kg} kg` : null].filter(Boolean); const summary = document.getElementById('existing-summary'); summary.innerHTML=''; labels.forEach(label => { const chip=document.createElement('span'); chip.className='rounded-full bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-800'; chip.textContent=label; summary.appendChild(chip); }); preview.classList.remove('hidden'); showStatus(data.already_enrolled ? 'You already belong to this gym.' : data.requires_gym_assistance ? 'Please ask the gym desk to reactivate this relationship.' : 'Profile found. Confirm once to join.'); document.getElementById('existing-join').disabled = data.already_enrolled || data.requires_gym_assistance; } catch (error) { showStatus(error.message, true); } };
            document.getElementById('existing-google').addEventListener('click', () => signIn(new GoogleAuthProvider())); const apple = new OAuthProvider('apple.com'); apple.addScope('email'); apple.addScope('name'); document.getElementById('existing-apple').addEventListener('click', () => signIn(apple));
            document.getElementById('existing-join').addEventListener('click', async () => { try { const branch = document.getElementById('existing-branch'); const body = {consent:true,whatsapp_marketing_consent:document.getElementById('existing-marketing').checked,reuse_profile:document.getElementById('reuse-profile').checked,branch_id:branch ? Number(branch.value) || null : {{ $link->branch_id ?? 'null' }}}; if (branch && !body.branch_id) throw new Error('Choose a branch.'); const result = await api('/api/member/self-enrollment/{{ $link->token }}', {method:'POST',body:JSON.stringify(body)}); showStatus(result.message); preview.classList.add('hidden'); } catch(error) { showStatus(error.message,true); } });
        </script>
    @endif
</x-public.layouts.app>
