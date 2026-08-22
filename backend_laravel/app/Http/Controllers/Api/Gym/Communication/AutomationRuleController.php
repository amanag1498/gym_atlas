<?php

namespace App\Http\Controllers\Api\Gym\Communication;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\CommunicationAutomationRule;
use App\Models\WhatsAppTemplate;
use App\Services\Authorization\ScopeResolver;
use App\Services\WhatsApp\WhatsAppTemplateParameterService;
use App\Support\CommunicationScope;
use Illuminate\Http\Request;

class AutomationRuleController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly WhatsAppTemplateParameterService $templateParameters,
    ) {}

    public function index(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);

        return $this->success(CommunicationAutomationRule::query()
            ->where('gym_id', $gym->id)->with('whatsappTemplate')->orderBy('notification_type')->get(),
            'Communication automation rules fetched successfully.');
    }

    public function types()
    {
        return $this->success(NotificationType::catalog(), 'Communication notification types fetched successfully.');
    }

    public function store(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'notification_type' => ['required', 'in:'.implode(',', NotificationType::values())],
            'recipient_role' => ['nullable', 'in:member'],
            'in_app_enabled' => ['required', 'boolean'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'whatsapp_template_id' => ['nullable', 'integer'],
            'is_enabled' => ['required', 'boolean'],
            'configuration' => ['nullable', 'array'],
            'configuration.template_parameter_values' => ['nullable', 'array'],
            'configuration.template_parameter_values.*' => ['string', 'max:250'],
        ]);
        abort_if(! $data['in_app_enabled'] && ! $data['whatsapp_enabled'], 422, 'Enable In-App, WhatsApp, or both.');
        if ($data['whatsapp_enabled']) {
            $template = WhatsAppTemplate::query()->whereKey($data['whatsapp_template_id'] ?? 0)
                ->where('status', 'approved')
                ->whereRaw('LOWER(category) = ?', ['utility'])
                ->whereHas('account', fn ($query) => $query->where('gym_id', $gym->id))->first();
            abort_unless($template, 422, 'Select an approved utility template from this gym connection.');
            $data['configuration']['template_parameter_values'] = $this->templateParameters->validate(
                $template,
                $data['configuration']['template_parameter_values'] ?? [],
            );
        }
        $rule = CommunicationAutomationRule::query()->updateOrCreate([
            'gym_id' => $gym->id,
            'branch_id' => $data['branch_id'] ?? null,
            'scope_key' => CommunicationScope::key($gym->id, $data['branch_id'] ?? null),
            'notification_type' => $data['notification_type'],
            'recipient_role' => $data['recipient_role'] ?? 'member',
        ], [
            ...$data,
            'created_by_user_id' => $request->user()->id,
        ]);

        return $this->success($rule->load('whatsappTemplate'), 'Communication automation rule saved successfully.');
    }
}
