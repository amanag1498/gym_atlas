<?php

namespace App\Http\Controllers\Web;

use App\Enums\NotificationType;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\CommunicationAutomationRule;
use App\Models\CommunicationCampaign;
use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppBusinessAccount;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\Communication\CommunicationCampaignService;
use App\Services\Web\GymWebPanelService;
use App\Services\WhatsApp\MetaWhatsAppClient;
use App\Services\WhatsApp\WhatsAppConnectionService;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use App\Services\WhatsApp\WhatsAppTemplateParameterService;
use App\Support\CommunicationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymPanel,
        private readonly WhatsAppConnectionService $connections,
        private readonly WhatsAppOnboardingService $onboarding,
        private readonly CommunicationCampaignService $campaigns,
        private readonly WhatsAppTemplateParameterService $templateParameters,
        private readonly MetaWhatsAppClient $meta,
    ) {}

    public function index(Request $request): View
    {
        $gym = $this->scope($request, PermissionName::CommunicationsView);
        $account = $this->connections->accountFor($gym);
        $campaigns = CommunicationCampaign::query()
            ->where('gym_id', $gym?->id)
            ->with('channels.whatsappTemplate')
            ->withCount('recipients')
            ->latest('id')
            ->paginate(12, ['*'], 'campaigns_page')
            ->withQueryString();
        $automations = CommunicationAutomationRule::query()
            ->where('gym_id', $gym?->id)
            ->with('whatsappTemplate')
            ->orderBy('notification_type')
            ->get();
        $campaignPreviews = $campaigns->getCollection()->mapWithKeys(function (CommunicationCampaign $campaign): array {
            if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
                return [];
            }

            return [$campaign->id => $this->campaigns->preview($campaign)];
        });
        $conversations = $account && $account->status === 'connected'
            ? WhatsAppConversation::query()
                ->where('whatsapp_business_account_id', $account->id)
                ->with('phoneNumber')
                ->withCount('messages')
                ->latest('last_message_at')
                ->paginate(15, ['*'], 'inbox_page')
                ->withQueryString()
            : null;
        $selectedConversation = null;
        $messages = collect();

        if ($request->filled('conversation') && $account) {
            $selectedConversation = WhatsAppConversation::query()
                ->where('whatsapp_business_account_id', $account->id)
                ->findOrFail($request->integer('conversation'));
            $messages = $selectedConversation->messages()->latest('id')->limit(100)->get()->reverse()->values();
        }

        return view('web.communications.index', [
            'pageTitle' => $gym ? 'Communications' : 'Platform Communications',
            'breadcrumbs' => [$gym ? 'Gym' : 'Platform', 'Communications'],
            'gym' => $gym,
            'isPlatform' => $gym === null,
            'routePrefix' => $this->routePrefix($request),
            'account' => $account,
            'configuration' => $this->connections->configuration(),
            'campaigns' => $campaigns,
            'campaignPreviews' => $campaignPreviews,
            'automations' => $automations,
            'notificationTypes' => NotificationType::catalog(),
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'branches' => $gym ? $this->gymPanel->accessibleBranches($request, $gym) : collect(),
            'members' => $this->members($gym),
            'approvedTemplates' => $account?->templates->where('status', 'approved')->values() ?? collect(),
            'utilityTemplates' => $account?->templates->where('status', 'approved')->filter(
                fn (WhatsAppTemplate $template): bool => strtolower((string) $template->category) === 'utility'
            )->values() ?? collect(),
            'templateVariableCounts' => $account?->templates->mapWithKeys(
                fn (WhatsAppTemplate $template): array => [$template->id => $this->templateParameters->variableCount($template)]
            ) ?? collect(),
            'templatePlaceholders' => WhatsAppTemplateParameterService::ALLOWED_PLACEHOLDERS,
            'canConnect' => $this->can($request, $gym, PermissionName::WhatsAppConnect),
            'canManageTemplates' => $this->can($request, $gym, PermissionName::WhatsAppTemplatesManage),
            'canSendCampaigns' => $this->can($request, $gym, PermissionName::CampaignsSend),
            'canManageAutomations' => $this->can($request, $gym, PermissionName::CommunicationsManage),
            'canReply' => $this->can($request, $gym, PermissionName::WhatsAppInboxReply),
        ]);
    }

    public function startOnboarding(Request $request): RedirectResponse
    {
        $gym = $this->scope($request, PermissionName::WhatsAppConnect);
        abort_unless($this->connections->configuration()['ready'] ?? false, 422, 'Meta Embedded Signup is not configured on this server.');
        $session = $this->onboarding->start($gym, $request->user());

        return redirect()->away($session['url']);
    }

    public function syncTemplates(Request $request): RedirectResponse
    {
        $account = $this->account($request, PermissionName::WhatsAppTemplatesManage, requireHealthy: false);
        $count = $this->connections->syncTemplates($account);

        return back()->with('status', $count.' WhatsApp template(s) synchronized.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $account = $this->account($request, PermissionName::WhatsAppConnect, requireHealthy: false);
        $this->connections->disconnect($account);

        return back()->with('status', 'WhatsApp Business disconnected from this scope.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $account = $this->account($request, PermissionName::WhatsAppTemplatesManage);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:20'],
            'category' => ['required', Rule::in(['utility', 'marketing'])],
            'body' => ['required', 'string', 'max:1024'],
            'sample_values_text' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['sample_values'] = $this->lines($data['sample_values_text'] ?? null);
        $this->connections->createTemplate($account, Arr::except($data, 'sample_values_text'));

        return back()->with('status', 'Template submitted to Meta for approval.');
    }

    public function updateTemplate(Request $request, WhatsAppTemplate $template): RedirectResponse
    {
        $account = $this->account($request, PermissionName::WhatsAppTemplatesManage);
        abort_unless((int) $template->whatsapp_business_account_id === (int) $account->id, 404);
        $data = $request->validate([
            'category' => ['required', Rule::in(['utility', 'marketing'])],
            'body' => ['required', 'string', 'max:1024'],
            'sample_values_text' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['sample_values'] = $this->lines($data['sample_values_text'] ?? null);
        $this->connections->updateTemplate($account, $template, Arr::except($data, 'sample_values_text'));

        return back()->with('status', 'Template changes submitted to Meta.');
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $gym = $this->scope($request, PermissionName::CampaignsSend);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audience_type' => ['required', Rule::in($gym ? ['gym', 'branch', 'selected_members'] : ['all_members', 'selected_members'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'member_ids' => ['nullable', 'array', 'max:5000'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'scheduled_for' => ['nullable', 'date'],
            'in_app_enabled' => ['nullable', 'boolean'],
            'in_app_title' => ['nullable', 'string', 'max:255'],
            'in_app_body' => ['nullable', 'string', 'max:4000'],
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_template_id' => ['nullable', 'integer'],
            'template_parameters_text' => ['nullable', 'string', 'max:4000'],
        ]);
        abort_if(! $request->boolean('in_app_enabled') && ! $request->boolean('whatsapp_enabled'), 422, 'Select In-App, WhatsApp, or both.');
        if ($gym && ! empty($data['branch_id'])) {
            abort_unless($gym->branches()->whereKey($data['branch_id'])->exists(), 422, 'The selected branch does not belong to this gym.');
        }
        $channels = [];
        if ($request->boolean('in_app_enabled')) {
            $request->validate(['in_app_title' => ['required', 'string', 'max:255'], 'in_app_body' => ['required', 'string', 'max:4000']]);
            $channels['in_app'] = ['title' => $data['in_app_title'], 'body' => $data['in_app_body'], 'notification_type' => 'manual_campaign'];
        }
        if ($request->boolean('whatsapp_enabled')) {
            $request->validate(['whatsapp_template_id' => ['required', 'integer']]);
            $channels['whatsapp'] = [
                'whatsapp_template_id' => $data['whatsapp_template_id'],
                'template_parameters' => $this->lines($data['template_parameters_text'] ?? null),
            ];
        }
        $campaign = $this->campaigns->create($gym, $request->user(), [
            ...Arr::only($data, ['name', 'audience_type', 'branch_id', 'member_ids', 'scheduled_for']),
            'channels' => $channels,
        ]);

        return back()->with('status', 'Campaign draft created. Review its audience before sending.')->with('preview_campaign', $campaign->id);
    }

    public function sendCampaign(Request $request, CommunicationCampaign $campaign): RedirectResponse
    {
        $campaign = $this->campaign($request, $campaign, PermissionName::CampaignsSend);
        $data = $request->validate(['scheduled_for' => ['nullable', 'date']]);
        $this->campaigns->schedule($campaign, $data['scheduled_for'] ?? null);

        return back()->with('status', $data['scheduled_for'] ?? null ? 'Campaign scheduled.' : 'Campaign queued for delivery.');
    }

    public function cancelCampaign(Request $request, CommunicationCampaign $campaign): RedirectResponse
    {
        $campaign = $this->campaign($request, $campaign, PermissionName::CampaignsSend);
        abort_unless(in_array($campaign->status, ['draft', 'scheduled'], true), 422, 'Only draft or scheduled campaigns can be cancelled.');
        $campaign->update(['status' => 'cancelled']);

        return back()->with('status', 'Campaign cancelled.');
    }

    public function storeAutomation(Request $request): RedirectResponse
    {
        $gym = $this->scope($request, PermissionName::CommunicationsManage);
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'notification_type' => ['required', Rule::in(NotificationType::values())],
            'in_app_enabled' => ['nullable', 'boolean'],
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_template_id' => ['nullable', 'integer'],
            'is_enabled' => ['nullable', 'boolean'],
            'template_parameters_text' => ['nullable', 'string', 'max:4000'],
        ]);
        abort_if(! $request->boolean('in_app_enabled') && ! $request->boolean('whatsapp_enabled'), 422, 'Enable In-App, WhatsApp, or both.');
        if ($gym && ! empty($data['branch_id'])) {
            abort_unless($gym->branches()->whereKey($data['branch_id'])->exists(), 422, 'The selected branch does not belong to this gym.');
        }
        $template = null;
        $parameters = [];
        if ($request->boolean('whatsapp_enabled')) {
            $template = WhatsAppTemplate::query()
                ->whereKey($data['whatsapp_template_id'] ?? 0)
                ->where('status', 'approved')
                ->whereRaw('LOWER(category) = ?', ['utility'])
                ->whereHas('account', fn (Builder $query) => $query->where('gym_id', $gym?->id))
                ->first();
            abort_unless($template, 422, 'Select an approved utility template for this sender.');
            $parameters = $this->templateParameters->validate($template, $this->lines($data['template_parameters_text'] ?? null));
        }
        CommunicationAutomationRule::query()->updateOrCreate([
            'gym_id' => $gym?->id,
            'branch_id' => $gym ? ($data['branch_id'] ?? null) : null,
            'scope_key' => CommunicationScope::key($gym?->id, $gym ? ($data['branch_id'] ?? null) : null),
            'notification_type' => $data['notification_type'],
            'recipient_role' => 'member',
        ], [
            'in_app_enabled' => $request->boolean('in_app_enabled'),
            'whatsapp_enabled' => $request->boolean('whatsapp_enabled'),
            'whatsapp_template_id' => $template?->id,
            'is_enabled' => $request->boolean('is_enabled'),
            'configuration' => ['template_parameter_values' => $parameters],
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Notification channel rule saved.');
    }

    public function reply(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        $account = $this->account($request, PermissionName::WhatsAppInboxReply);
        $conversation = WhatsAppConversation::query()->where('whatsapp_business_account_id', $account->id)->with('phoneNumber')->findOrFail($conversation->id);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4096', 'required_without:whatsapp_template_id'],
            'whatsapp_template_id' => ['nullable', 'integer', 'required_without:body'],
            'template_parameters_text' => ['nullable', 'string', 'max:4000'],
        ]);
        if (empty($data['whatsapp_template_id']) && ! empty($data['body'])) {
            abort_unless($conversation->service_window_expires_at?->isFuture() === true, 422, 'The 24-hour service window is closed. Use an approved template.');
            $messageId = $this->meta->sendText($conversation->phoneNumber->phone_number_id, (string) $account->access_token, $conversation->contact_wa_id, $data['body']);
            $type = 'text';
            $payload = ['body' => $data['body']];
        } else {
            $template = WhatsAppTemplate::query()->where('whatsapp_business_account_id', $account->id)->whereKey($data['whatsapp_template_id'])->where('status', 'approved')->firstOrFail();
            $values = $this->templateParameters->validate($template, $this->lines($data['template_parameters_text'] ?? null));
            $memberName = $conversation->contact_name ?: User::query()->whereKey($conversation->user_id)->value('name') ?: 'Member';
            $parameters = $this->templateParameters->componentsFromReplacements($values, [
                '{member_name}' => $memberName,
                '{notification_title}' => '',
                '{notification_message}' => '',
                '{gym_name}' => $account->gym?->name ?? 'Gym Atlas',
                '{branch_name}' => 'your branch',
            ]);
            $messageId = $this->meta->sendTemplate($conversation->phoneNumber->phone_number_id, (string) $account->access_token, $conversation->contact_wa_id, $template->name, $template->language, $parameters);
            $type = 'template';
            $payload = ['template_id' => $template->id, 'components' => $parameters, 'parameter_values' => $values];
        }
        WhatsAppMessage::query()->create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => $messageId,
            'direction' => 'outbound',
            'message_type' => $type,
            'body' => $data['body'] ?? null,
            'payload' => $payload,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $conversation->update(['last_message_at' => now()]);

        return back()->with('status', 'WhatsApp reply sent.');
    }

    private function scope(Request $request, PermissionName $permission): ?Gym
    {
        if ($this->isPlatform($request)) {
            return null;
        }
        $gym = $this->gymPanel->resolveGym($request);
        $this->gymPanel->assertPermission($request, $permission->value, $gym);

        return $gym;
    }

    private function account(Request $request, PermissionName $permission, bool $requireHealthy = true): WhatsAppBusinessAccount
    {
        $gym = $this->scope($request, $permission);
        $account = $this->connections->accountFor($gym);
        abort_unless($account && $account->status === 'connected', 422, 'Connect WhatsApp Business first.');
        if ($requireHealthy) {
            abort_unless($account->health_status === 'healthy' && (! $account->token_expires_at || $account->token_expires_at->isFuture()), 422, 'Reconnect or synchronize the WhatsApp sender before continuing.');
        }

        return $account;
    }

    private function campaign(Request $request, CommunicationCampaign $campaign, PermissionName $permission): CommunicationCampaign
    {
        $gym = $this->scope($request, $permission);

        return CommunicationCampaign::query()->where('gym_id', $gym?->id)->findOrFail($campaign->id);
    }

    private function members(?Gym $gym)
    {
        return User::query()
            ->where('is_active', true)
            ->when(
                $gym,
                fn (Builder $query) => $query->whereHas('memberProfiles', fn (Builder $profile) => $profile->where('gym_id', $gym->id)->where('is_active', true)),
                fn (Builder $query) => $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'member')),
            )
            ->orderBy('name')
            ->limit(5000)
            ->get(['id', 'name', 'email', 'phone']);
    }

    private function lines(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function isPlatform(Request $request): bool
    {
        return str_starts_with((string) $request->route()?->getName(), 'web.admin.');
    }

    private function routePrefix(Request $request): string
    {
        return $this->isPlatform($request) ? 'web.admin.communications' : 'web.gym.communications';
    }

    private function can(Request $request, ?Gym $gym, PermissionName $permission): bool
    {
        return $gym === null || $this->gymPanel->canPermission($request, $permission->value, $gym);
    }
}
