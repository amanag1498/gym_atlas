<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppBusinessAccount;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\MetaWhatsAppClient;
use Illuminate\Http\Request;

class WhatsAppInboxController extends Controller
{
    public function __construct(private readonly MetaWhatsAppClient $meta) {}

    public function index()
    {
        $account = $this->account();
        $paginator = WhatsAppConversation::query()
            ->where('whatsapp_business_account_id', $account->id)
            ->with('phoneNumber')->withCount('messages')->latest('last_message_at')->paginate(25);

        return $this->paginated($paginator, $paginator->getCollection(), 'Platform WhatsApp conversations fetched successfully.');
    }

    public function show(WhatsAppConversation $conversation)
    {
        $conversation = $this->conversation($conversation);

        return $this->success([
            'conversation' => $conversation,
            'messages' => $conversation->messages()->latest('id')->paginate(50),
        ], 'Platform WhatsApp conversation fetched successfully.');
    }

    public function reply(Request $request, WhatsAppConversation $conversation)
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:4096', 'required_without:whatsapp_template_id'],
            'whatsapp_template_id' => ['nullable', 'integer', 'required_without:body'],
            'template_parameters' => ['nullable', 'array'],
        ]);
        $conversation = $this->conversation($conversation)->load('phoneNumber');
        $account = $this->account();
        if (! empty($validated['body'])) {
            abort_unless($conversation->service_window_expires_at?->isFuture() === true, 422, 'The 24-hour service window is closed. Use an approved template.');
            $messageId = $this->meta->sendText(
                $conversation->phoneNumber->phone_number_id,
                (string) $account->access_token,
                $conversation->contact_wa_id,
                $validated['body'],
            );
            $type = 'text';
            $payload = ['body' => $validated['body']];
        } else {
            $template = WhatsAppTemplate::query()
                ->where('whatsapp_business_account_id', $account->id)
                ->whereKey($validated['whatsapp_template_id'])
                ->where('status', 'approved')->firstOrFail();
            $messageId = $this->meta->sendTemplate(
                $conversation->phoneNumber->phone_number_id,
                (string) $account->access_token,
                $conversation->contact_wa_id,
                $template->name,
                $template->language,
                $validated['template_parameters'] ?? [],
            );
            $type = 'template';
            $payload = ['template_id' => $template->id, 'components' => $validated['template_parameters'] ?? []];
        }
        $message = WhatsAppMessage::query()->create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => $messageId,
            'direction' => 'outbound',
            'message_type' => $type,
            'body' => $validated['body'] ?? null,
            'payload' => $payload,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        return $this->success($message, 'Platform WhatsApp reply sent successfully.', 201);
    }

    private function account(): WhatsAppBusinessAccount
    {
        return WhatsAppBusinessAccount::query()
            ->whereNull('gym_id')
            ->where('status', 'connected')
            ->where('health_status', 'healthy')
            ->where(fn ($query) => $query->whereNull('token_expires_at')->orWhere('token_expires_at', '>', now()))
            ->firstOrFail();
    }

    private function conversation(WhatsAppConversation $conversation): WhatsAppConversation
    {
        return WhatsAppConversation::query()
            ->where('whatsapp_business_account_id', $this->account()->id)
            ->findOrFail($conversation->id);
    }
}
