<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\CommunicationAutomationRule;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppTemplateParameterService;
use App\Support\CommunicationScope;
use Illuminate\Http\Request;

class CommunicationAutomationRuleController extends Controller
{
    public function __construct(private readonly WhatsAppTemplateParameterService $templateParameters) {}

    public function index()
    {
        return $this->success(CommunicationAutomationRule::query()->whereNull('gym_id')
            ->with('whatsappTemplate')->orderBy('notification_type')->get(),
            'Platform communication automation rules fetched successfully.');
    }

    public function types()
    {
        return $this->success(NotificationType::catalog(), 'Platform communication notification types fetched successfully.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
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
                ->whereHas('account', fn ($query) => $query->whereNull('gym_id'))->first();
            abort_unless($template, 422, 'Select an approved platform utility template.');
            $data['configuration']['template_parameter_values'] = $this->templateParameters->validate(
                $template,
                $data['configuration']['template_parameter_values'] ?? [],
            );
        }
        $rule = CommunicationAutomationRule::query()->updateOrCreate([
            'gym_id' => null,
            'branch_id' => null,
            'scope_key' => CommunicationScope::key(null),
            'notification_type' => $data['notification_type'],
            'recipient_role' => $data['recipient_role'] ?? 'member',
        ], [
            ...$data,
            'created_by_user_id' => $request->user()->id,
        ]);

        return $this->success($rule->load('whatsappTemplate'), 'Platform communication automation rule saved successfully.');
    }
}
