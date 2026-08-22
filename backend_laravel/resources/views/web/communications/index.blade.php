@extends('layouts.panel')

@section('content')
    @php
        $connected = $account?->status === 'connected';
        $healthy = $connected && $account?->health_status === 'healthy' && (! $account?->token_expires_at || $account->token_expires_at->isFuture());
        $routeParams = $isPlatform ? [] : request()->only(['gym', 'branch']);
    @endphp

    <div class="space-y-6">
        <section class="panel-hero overflow-hidden">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[.18em] text-emerald-700"><i class="ti ti-brand-whatsapp"></i>Unified communications</span>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $isPlatform ? 'Platform communications' : 'Member communications' }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Connect WhatsApp Business, choose In-App, WhatsApp, or both for every message, and manage campaigns and replies from this panel.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="#campaigns" class="panel-btn-secondary">Campaigns</a>
                    <a href="#automations" class="panel-btn-secondary">Automations</a>
                    <a href="#inbox" class="panel-btn-secondary">Inbox</a>
                </div>
            </div>
        </section>

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
                <strong>Review the highlighted information.</strong>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
            <x-premium-card class="p-6">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $healthy ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}"><i class="ti ti-brand-whatsapp text-2xl"></i></div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2"><h2 class="panel-section-title">WhatsApp Business</h2><x-status-badge :label="$healthy ? 'Ready' : ($connected ? ucfirst($account->health_status) : 'Not connected')" :tone="$healthy ? 'success' : ($connected ? 'warning' : 'neutral')" /></div>
                            @if($connected)
                                <p class="mt-2 text-sm text-slate-500">{{ $account->business_name ?: 'Connected business' }} · {{ $account->phoneNumbers->where('is_active', true)->count() }} active number(s)</p>
                                <p class="mt-1 text-xs text-slate-400">Last synchronized {{ $account->last_synced_at?->diffForHumans() ?? 'not yet' }}. Credentials remain encrypted on the server.</p>
                            @else
                                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">Use Meta Embedded Signup to choose a business account and phone number. No API key is entered or exposed in the browser.</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if($connected)
                            @if($canManageTemplates)<form method="POST" action="{{ route($routePrefix.'.whatsapp.sync', $routeParams) }}">@csrf<button class="panel-btn-primary"><i class="ti ti-refresh"></i> Sync</button></form>@endif
                            @if($canConnect)<form method="POST" action="{{ route($routePrefix.'.whatsapp.disconnect', $routeParams) }}" data-confirm-submit data-confirm-title="Disconnect WhatsApp?" data-confirm-message="Campaign and automation delivery through this sender will stop." data-confirm-button="Disconnect">@csrf @method('DELETE')<button class="panel-btn-secondary text-rose-700">Disconnect</button></form>@endif
                        @elseif(!$connected && $canConnect)
                            <form method="POST" action="{{ route($routePrefix.'.whatsapp.onboarding', $routeParams) }}">@csrf<button class="panel-btn-primary" @disabled(!($configuration['ready'] ?? false))><i class="ti ti-link"></i> Connect with Meta</button></form>
                        @endif
                    </div>
                </div>
                @if(!($configuration['ready'] ?? false))
                    <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Meta Embedded Signup is not configured on this server. Add the Meta environment values before connecting.</div>
                @elseif($account?->last_error)
                    <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $account->last_error }}</div>
                @endif
            </x-premium-card>

            <x-premium-card class="p-6">
                <h2 class="panel-section-title">Sender health</h2>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="panel-card-muted p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Templates</div><div class="mt-1 text-2xl font-semibold">{{ $account?->templates->count() ?? 0 }}</div></div>
                    <div class="panel-card-muted p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Approved</div><div class="mt-1 text-2xl font-semibold">{{ $approvedTemplates->count() }}</div></div>
                    <div class="panel-card-muted p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Campaigns</div><div class="mt-1 text-2xl font-semibold">{{ $campaigns->total() }}</div></div>
                    <div class="panel-card-muted p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Active rules</div><div class="mt-1 text-2xl font-semibold">{{ $automations->where('is_enabled', true)->count() }}</div></div>
                </div>
            </x-premium-card>
        </section>

        <section id="templates" class="grid scroll-mt-6 gap-5 xl:grid-cols-[.9fr_1.1fr]">
            <x-premium-card class="p-6">
                <h2 class="panel-section-title">Create message template</h2>
                <p class="panel-section-copy">Templates are submitted to Meta and can be used after approval.</p>
                <form method="POST" action="{{ route($routePrefix.'.templates.store', $routeParams) }}" class="mt-5 space-y-4">
                    @csrf
                    <fieldset class="space-y-4" @disabled(!$canManageTemplates)>
                    <div><label class="panel-label">Template name</label><input name="name" value="{{ old('name') }}" class="panel-input" placeholder="membership_due_reminder" required pattern="[a-z0-9_]+" @disabled(!$healthy)></div>
                    <div class="grid gap-4 sm:grid-cols-2"><div><label class="panel-label">Language</label><input name="language" value="{{ old('language', 'en') }}" class="panel-input" required @disabled(!$healthy)></div><div><label class="panel-label">Category</label><select name="category" class="panel-select" @disabled(!$healthy)><option value="utility">Utility</option><option value="marketing">Marketing</option></select></div></div>
                    <div><label class="panel-label">Message</label><textarea name="body" rows="5" class="panel-textarea" placeholder="Hi &#123;&#123;1&#125;&#125;, your membership is due on &#123;&#123;2&#125;&#125;." required data-template-body @disabled(!$healthy)>{{ old('body') }}</textarea><p class="mt-1 text-xs text-slate-400" data-template-body-count>Use sequential variables such as &#123;&#123;1&#125;&#125; and &#123;&#123;2&#125;&#125;.</p></div>
                    <div><label class="panel-label">Sample values <span class="font-normal text-slate-400">one per line</span></label><textarea name="sample_values_text" rows="3" class="panel-textarea" placeholder="Aman&#10;31 August" @disabled(!$healthy)>{{ old('sample_values_text') }}</textarea><p class="mt-1 text-xs text-slate-400">Provide one example for every variable so Meta can review the template.</p></div>
                    <button class="panel-btn-primary w-full justify-center" @disabled(!$healthy)>Submit to Meta</button>
                    </fieldset>
                </form>
            </x-premium-card>

            <x-premium-card class="overflow-hidden p-0">
                <div class="border-b border-slate-200 p-6 dark:border-slate-800"><h2 class="panel-section-title">Message templates</h2><p class="panel-section-copy">Approval and quality status come directly from Meta.</p></div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($account?->templates ?? [] as $template)
                        @php($bodyComponent = collect($template->components ?? [])->first(fn($component) => strtoupper((string)($component['type'] ?? '')) === 'BODY'))
                        <details class="group p-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4"><div><div class="font-semibold text-slate-950 dark:text-white">{{ $template->name }}</div><div class="mt-1 text-xs text-slate-500">{{ strtoupper($template->category) }} · {{ $template->language }}</div></div><div class="flex items-center gap-2"><x-status-badge :label="ucfirst($template->status)" :tone="$template->status === 'approved' ? 'success' : ($template->status === 'rejected' ? 'danger' : 'warning')" /><i class="ti ti-chevron-down transition group-open:rotate-180"></i></div></summary>
                            @if($canManageTemplates)<form method="POST" action="{{ route($routePrefix.'.templates.update', ['template' => $template->id] + $routeParams) }}" class="mt-5 space-y-4 border-t border-slate-200 pt-5 dark:border-slate-800">@csrf @method('PUT')<div><label class="panel-label">Category</label><select name="category" class="panel-select"><option value="utility" @selected($template->category === 'utility')>Utility</option><option value="marketing" @selected($template->category === 'marketing')>Marketing</option></select></div><div><label class="panel-label">Message</label><textarea name="body" rows="4" class="panel-textarea" required data-template-body>{{ $bodyComponent['text'] ?? '' }}</textarea><p class="mt-1 text-xs text-slate-400" data-template-body-count>{{ $templateVariableCounts->get($template->id, 0) }} variable value(s) required.</p></div><div><label class="panel-label">Sample values <span class="font-normal text-slate-400">one per line</span></label><textarea name="sample_values_text" rows="2" class="panel-textarea"></textarea></div><button class="panel-btn-secondary">Submit changes to Meta</button></form>@endif
                        </details>
                    @empty
                        <div class="p-6"><x-empty-state title="No templates" message="Connect WhatsApp, then create or synchronize templates." /></div>
                    @endforelse
                </div>
            </x-premium-card>
        </section>

        <section id="campaigns" class="scroll-mt-6 space-y-5">
            <x-premium-card class="p-6">
                <div><h2 class="panel-section-title">Create campaign</h2><p class="panel-section-copy">Build one audience and independently enable In-App, WhatsApp, or both.</p></div>
                <form method="POST" action="{{ route($routePrefix.'.campaigns.store', $routeParams) }}" class="mt-6 grid gap-5 xl:grid-cols-2" data-channel-form>
                    @csrf
                    <fieldset class="contents" @disabled(!$canSendCampaigns)>
                    <div class="space-y-4">
                        <div><label class="panel-label">Campaign name</label><input name="name" class="panel-input" required placeholder="August renewal reminder"></div>
                        <div><label class="panel-label">Audience</label><select name="audience_type" class="panel-select" data-audience-select required>@if($isPlatform)<option value="all_members">All Atlas members</option>@else<option value="gym">All gym members</option><option value="branch">One branch</option>@endif<option value="selected_members">Selected members</option></select></div>
                        @if(!$isPlatform)<div data-branch-field hidden><label class="panel-label">Branch</label><select name="branch_id" class="panel-select"><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>@endif
                        <div data-members-field hidden><label class="panel-label">Members</label><select name="member_ids[]" class="panel-select min-h-40" multiple>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }} · {{ $member->email ?: $member->phone }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-400">Use Ctrl/Cmd to select multiple members.</p></div>
                        <div><label class="panel-label">Schedule <span class="font-normal text-slate-400">optional</span></label><input name="scheduled_for" type="datetime-local" class="panel-input"></div>
                    </div>
                    <div class="space-y-4">
                        <label class="block rounded-2xl border border-slate-200 p-4 dark:border-slate-800"><span class="flex items-center gap-3 font-semibold"><input type="checkbox" name="in_app_enabled" value="1" checked data-channel-toggle="in-app">In-App notification</span><span class="mt-3 block space-y-3" data-channel-panel="in-app"><input name="in_app_title" class="panel-input" placeholder="Notification title"><textarea name="in_app_body" rows="3" class="panel-textarea" placeholder="Message shown in the member app"></textarea></span></label>
                        <label class="block rounded-2xl border border-slate-200 p-4 dark:border-slate-800"><span class="flex items-center gap-3 font-semibold"><input type="checkbox" name="whatsapp_enabled" value="1" data-channel-toggle="whatsapp" @disabled($approvedTemplates->isEmpty())>WhatsApp</span><span class="mt-3 hidden space-y-3" data-channel-panel="whatsapp"><select name="whatsapp_template_id" class="panel-select" data-template-select><option value="">Choose approved template</option>@foreach($approvedTemplates as $template)<option value="{{ $template->id }}">{{ $template->name }} · {{ $template->language }}</option>@endforeach</select><textarea name="template_parameters_text" rows="3" class="panel-textarea" placeholder="Template values, one per line" data-template-values></textarea><small class="block leading-5 text-slate-400" data-template-help>Select a template to see its required values. Sender availability is checked again before delivery.</small></span></label>
                        <button class="panel-btn-primary w-full justify-center">Create draft and preview audience</button>
                    </div>
                    </fieldset>
                </form>
            </x-premium-card>

            <x-premium-card class="overflow-hidden p-0">
                <div class="border-b border-slate-200 p-6 dark:border-slate-800"><h2 class="panel-section-title">Campaign history</h2><p class="panel-section-copy">Review eligibility before sending. Recipient lists are frozen when delivery starts.</p></div>
                <x-table-wrapper><table class="panel-table"><thead><tr><th>Campaign</th><th>Channels</th><th>Audience preview</th><th>Status</th><th>Action</th></tr></thead><tbody>
                    @forelse($campaigns as $campaign)
                        @php($preview = $campaignPreviews->get($campaign->id))
                        <tr>
                            <td><div class="font-semibold">{{ $campaign->name }}</div><div class="text-xs text-slate-500">{{ str($campaign->audience_type)->replace('_', ' ')->title() }} · {{ $campaign->scheduled_for?->format('d M Y, h:i A') ?? $campaign->created_at?->format('d M Y') }}</div></td>
                            <td><div class="flex flex-wrap gap-1">@foreach($campaign->channels as $channel)<x-status-badge :label="$channel->channel === 'in_app' ? 'In-App' : 'WhatsApp'" tone="info" />@endforeach</div></td>
                            <td>
                                @if($preview)
                                    <details>
                                        <summary class="cursor-pointer font-semibold text-brand-600">{{ $preview['eligible'] }} eligible</summary>
                                        <div class="mt-2 min-w-52 space-y-2 text-xs text-slate-500">
                                            <div>{{ $preview['audience'] }} audience · {{ $preview['excluded'] }} excluded</div>
                                            @foreach($preview['by_channel'] as $channel => $channelPreview)
                                                <div class="rounded-lg bg-slate-50 p-2 dark:bg-white/5">
                                                    <strong>{{ $channel === 'in_app' ? 'In-App' : 'WhatsApp' }}:</strong> {{ $channelPreview['eligible'] }} eligible
                                                    @if($channelPreview['exclusion_reasons'])
                                                        <div class="mt-1">{{ collect($channelPreview['exclusion_reasons'])->map(fn($count, $reason) => str($reason)->replace('_',' ')->title().': '.$count)->join(' · ') }}</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @else
                                    <div class="font-semibold">{{ $campaign->recipients_count }} deliveries</div>
                                @endif
                            </td>
                            <td><x-status-badge :label="ucfirst($campaign->status)" :tone="in_array($campaign->status, ['completed','running']) ? 'success' : ($campaign->status === 'failed' ? 'danger' : 'warning')" /></td>
                            <td><div class="flex flex-wrap gap-2">@if($canSendCampaigns && in_array($campaign->status, ['draft','scheduled']))<form method="POST" action="{{ route($routePrefix.'.campaigns.send', ['campaign' => $campaign->id] + $routeParams) }}" data-confirm-submit data-confirm-title="Send {{ $campaign->name }}?" data-confirm-message="{{ $preview ? $preview['eligible'].' delivery target(s) are eligible and '.$preview['excluded'].' will be excluded by consent, sender, or template checks.' : 'The eligible audience will be frozen and queued for delivery.' }}" data-confirm-button="Send campaign">@csrf<button class="panel-btn-primary !px-3 !py-2">{{ $campaign->scheduled_for?->isFuture() ? 'Confirm schedule' : 'Send now' }}</button></form><form method="POST" action="{{ route($routePrefix.'.campaigns.cancel', ['campaign' => $campaign->id] + $routeParams) }}">@csrf<button class="panel-btn-secondary !px-3 !py-2">Cancel</button></form>@else<span class="text-xs text-slate-400">{{ $campaign->completed_at?->diffForHumans() }}</span>@endif</div></td>
                        </tr>
                    @empty<tr><td colspan="5"><x-empty-state title="No campaigns" message="Create the first draft above." /></td></tr>@endforelse
                </tbody></table></x-table-wrapper>
                <div class="p-5">{{ $campaigns->links() }}</div>
            </x-premium-card>
        </section>

        <section id="automations" class="grid scroll-mt-6 gap-5 xl:grid-cols-[.85fr_1.15fr]">
            <x-premium-card class="p-6">
                <h2 class="panel-section-title">Notification routing</h2><p class="panel-section-copy">Choose delivery channels independently for each automated event.</p>
                <form method="POST" action="{{ route($routePrefix.'.automations.store', $routeParams) }}" class="mt-5 space-y-4" data-channel-form>@csrf @method('PUT')
                    <fieldset class="space-y-4" @disabled(!$canManageAutomations)>
                    <div><label class="panel-label">Notification</label><select name="notification_type" class="panel-select" required>@foreach($notificationTypes as $type)<option value="{{ $type['value'] }}">{{ $type['label'] }}</option>@endforeach</select></div>
                    @if(!$isPlatform)<div><label class="panel-label">Branch <span class="font-normal text-slate-400">optional</span></label><select name="branch_id" class="panel-select"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>@endif
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 p-4 dark:border-slate-800"><span><strong class="block">In-App</strong><small class="text-slate-500">Notification centre and Firebase push</small></span><input type="checkbox" name="in_app_enabled" value="1" checked></label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 p-4 dark:border-slate-800"><span><strong class="block">WhatsApp</strong><small class="text-slate-500">Approved utility template</small></span><input type="checkbox" name="whatsapp_enabled" value="1" data-channel-toggle="automation-whatsapp" @checked($utilityTemplates->isNotEmpty()) @disabled($utilityTemplates->isEmpty())></label>
                    <div class="hidden space-y-3" data-channel-panel="automation-whatsapp"><select name="whatsapp_template_id" class="panel-select" data-template-select>@if($utilityTemplates->isEmpty())<option value="">No approved utility templates</option>@endif @foreach($utilityTemplates as $template)<option value="{{ $template->id }}">{{ $template->name }} · {{ $template->language }}</option>@endforeach</select><textarea name="template_parameters_text" class="panel-textarea" rows="3" placeholder="Template values, one per line" data-template-values></textarea><small class="block leading-5 text-slate-400" data-template-help>Select a template to see its required values.</small></div>
                    <label class="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" name="is_enabled" value="1" checked>Rule active</label>
                    <button class="panel-btn-primary w-full justify-center">Save channel rule</button>
                    </fieldset>
                </form>
            </x-premium-card>
            <x-premium-card class="overflow-hidden p-0">
                <div class="border-b border-slate-200 p-6 dark:border-slate-800"><h2 class="panel-section-title">Configured rules</h2><p class="panel-section-copy">Open any rule to edit its active state, channels, template, or runtime values.</p></div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($automations as $rule)
                        <details class="group p-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                                <div><div class="font-semibold">{{ str($rule->notification_type)->replace('_',' ')->title() }}</div><div class="mt-2 flex flex-wrap gap-2">@if($rule->in_app_enabled)<x-status-badge label="In-App" tone="info" />@endif @if($rule->whatsapp_enabled)<x-status-badge label="WhatsApp" tone="success" />@endif @if($rule->branch_id)<x-status-badge :label="$branches->firstWhere('id', $rule->branch_id)?->name ?? 'Branch scoped'" tone="neutral" />@endif</div></div>
                                <div class="flex items-center gap-2"><x-status-badge :label="$rule->is_enabled ? 'Active' : 'Paused'" :tone="$rule->is_enabled ? 'success' : 'neutral'" /><i class="ti ti-chevron-down transition group-open:rotate-180"></i></div>
                            </summary>
                            @if($canManageAutomations)
                                <form method="POST" action="{{ route($routePrefix.'.automations.store', $routeParams) }}" class="mt-5 space-y-4 border-t border-slate-200 pt-5 dark:border-slate-800">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="notification_type" value="{{ $rule->notification_type }}">
                                    @if(!$isPlatform)<input type="hidden" name="branch_id" value="{{ $rule->branch_id }}">@endif
                                    <label class="flex items-center justify-between rounded-xl border border-slate-200 p-4 dark:border-slate-800"><span><strong class="block">Rule active</strong><small class="text-slate-500">Pause without deleting the setup.</small></span><input type="checkbox" name="is_enabled" value="1" @checked($rule->is_enabled)></label>
                                    <label class="flex items-center justify-between rounded-xl border border-slate-200 p-4 dark:border-slate-800"><strong>In-App notification</strong><input type="checkbox" name="in_app_enabled" value="1" @checked($rule->in_app_enabled)></label>
                                    <label class="flex items-center justify-between rounded-xl border border-slate-200 p-4 dark:border-slate-800"><strong>WhatsApp utility template</strong><input type="checkbox" name="whatsapp_enabled" value="1" data-channel-toggle="automation-whatsapp-{{ $rule->id }}" @checked($rule->whatsapp_enabled) @disabled($utilityTemplates->isEmpty())></label>
                                    <div class="hidden space-y-3" data-channel-panel="automation-whatsapp-{{ $rule->id }}"><select name="whatsapp_template_id" class="panel-select" data-template-select>@foreach($utilityTemplates as $template)<option value="{{ $template->id }}" @selected($rule->whatsapp_template_id === $template->id)>{{ $template->name }} · {{ $template->language }}</option>@endforeach</select><textarea name="template_parameters_text" class="panel-textarea" rows="3" data-template-values>{{ collect($rule->configuration['template_parameter_values'] ?? [])->join("\n") }}</textarea><small class="block leading-5 text-slate-400" data-template-help></small></div>
                                    <button class="panel-btn-primary">Save changes</button>
                                </form>
                            @endif
                        </details>
                    @empty
                        <div class="p-6"><x-empty-state title="No automation rules" message="Add a routing rule for the first lifecycle event." /></div>
                    @endforelse
                </div>
            </x-premium-card>
        </section>

        <section id="inbox" class="grid scroll-mt-6 gap-5 xl:grid-cols-[.8fr_1.2fr]">
            <x-premium-card class="overflow-hidden p-0"><div class="border-b border-slate-200 p-6 dark:border-slate-800"><h2 class="panel-section-title">WhatsApp inbox</h2><p class="panel-section-copy">Member replies received by the connected sender.</p></div><div class="max-h-[38rem] divide-y divide-slate-200 overflow-y-auto dark:divide-slate-800">@forelse($conversations ?? [] as $conversation)<a href="{{ route($routePrefix.'.index', ['conversation' => $conversation->id] + $routeParams) }}#inbox" class="block p-5 transition hover:bg-slate-50 dark:hover:bg-white/5"><div class="flex items-center justify-between gap-3"><strong>{{ $conversation->contact_name ?: $conversation->contact_wa_id }}</strong><x-status-badge :label="$conversation->service_window_expires_at?->isFuture() ? 'Reply open' : 'Template only'" :tone="$conversation->service_window_expires_at?->isFuture() ? 'success' : 'warning'" /></div><div class="mt-1 text-xs text-slate-500">{{ $conversation->messages_count }} messages · {{ $conversation->last_message_at?->diffForHumans() }}</div></a>@empty<div class="p-6"><x-empty-state title="No conversations" message="Inbound WhatsApp replies will appear here." /></div>@endforelse</div>@if($conversations)<div class="p-5">{{ $conversations->links() }}</div>@endif</x-premium-card>
            <x-premium-card class="p-6">@if($selectedConversation)<div class="flex items-start justify-between gap-4"><div><h2 class="panel-section-title">{{ $selectedConversation->contact_name ?: 'WhatsApp contact' }}</h2><p class="panel-section-copy">{{ $selectedConversation->contact_wa_id }}</p></div><x-status-badge :label="$selectedConversation->service_window_expires_at?->isFuture() ? '24-hour window open' : 'Template required'" :tone="$selectedConversation->service_window_expires_at?->isFuture() ? 'success' : 'warning'" /></div><div class="mt-5 max-h-80 space-y-3 overflow-y-auto rounded-2xl bg-slate-50 p-4 dark:bg-white/5">@foreach($messages as $message)<div class="flex {{ $message->direction === 'outbound' ? 'justify-end' : 'justify-start' }}"><div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm {{ $message->direction === 'outbound' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-white' }}"><div>{{ $message->body ?: 'Template message' }}</div><div class="mt-1 text-[10px] opacity-70">{{ ucfirst($message->status) }} · {{ $message->created_at?->format('d M, h:i A') }}</div></div></div>@endforeach</div>@if($canReply)<form method="POST" action="{{ route($routePrefix.'.inbox.reply', ['conversation' => $selectedConversation->id] + $routeParams) }}" class="mt-5 space-y-3">@csrf @if($selectedConversation->service_window_expires_at?->isFuture())<textarea name="body" class="panel-textarea" rows="3" placeholder="Reply inside the 24-hour service window"></textarea><div class="text-center text-xs text-slate-400">or use an approved template</div>@endif<select name="whatsapp_template_id" class="panel-select" data-template-select><option value="">{{ $selectedConversation->service_window_expires_at?->isFuture() ? 'Free-form reply' : 'Choose approved template' }}</option>@foreach($approvedTemplates as $template)<option value="{{ $template->id }}">{{ $template->name }} · {{ $template->language }}</option>@endforeach</select><textarea name="template_parameters_text" class="panel-textarea" rows="2" placeholder="Template values, one per line" data-template-values></textarea><small class="block leading-5 text-slate-400" data-template-help>Select a template to see its required values.</small><button class="panel-btn-primary w-full justify-center">Send reply</button></form>@endif @else<x-empty-state title="Select a conversation" message="Choose a WhatsApp conversation to inspect messages and reply." />@endif</x-premium-card>
        </section>
    </div>

    <script>
        const templateVariableCounts = @json($templateVariableCounts);
        const templatePlaceholders = @json($templatePlaceholders);
        document.querySelectorAll('[data-channel-toggle]').forEach(toggle => {
            const panel = document.querySelector(`[data-channel-panel="${toggle.dataset.channelToggle}"]`);
            const update = () => panel?.classList.toggle('hidden', !toggle.checked);
            toggle.addEventListener('change', update); update();
        });
        document.querySelectorAll('[data-audience-select]').forEach(select => {
            const form = select.closest('form'); const branch = form.querySelector('[data-branch-field]'); const members = form.querySelector('[data-members-field]');
            const update = () => { if (branch) branch.hidden = select.value !== 'branch'; if (members) members.hidden = select.value !== 'selected_members'; };
            select.addEventListener('change', update); update();
        });
        document.querySelectorAll('[data-template-select]').forEach(select => {
            const form = select.closest('form'); const values = form?.querySelector('[data-template-values]'); const help = form?.querySelector('[data-template-help]');
            const update = () => {
                const count = Number(templateVariableCounts[select.value] || 0);
                if (values) { values.hidden = !select.value || count === 0; values.required = Boolean(select.value) && count > 0; }
                if (help) help.textContent = !select.value ? 'Select a template to see its required values.' : count > 0 ? `${count} value(s) required, one per line. Available fields: ${templatePlaceholders.join(', ')}. Static text is also allowed.` : 'This template has no variables.';
            };
            select.addEventListener('change', update); update();
        });
        document.querySelectorAll('[data-template-body]').forEach(body => {
            const help = body.parentElement?.querySelector('[data-template-body-count]');
            const update = () => { const indexes = [...body.value.matchAll(/\{\{(\d+)\}\}/g)].map(match => match[1]); const count = new Set(indexes).size; if (help) help.textContent = count ? `${count} sample value(s) required.` : 'Use sequential variables such as \u007b\u007b1\u007d\u007d and \u007b\u007b2\u007d\u007d.'; };
            body.addEventListener('input', update); update();
        });
    </script>
@endsection
