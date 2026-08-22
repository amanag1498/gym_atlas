<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationCampaign;
use App\Services\Communication\CommunicationCampaignService;
use Illuminate\Http\Request;

class CommunicationCampaignController extends Controller
{
    public function __construct(private readonly CommunicationCampaignService $campaigns) {}

    public function index()
    {
        $paginator = CommunicationCampaign::query()->whereNull('gym_id')
            ->with('channels.whatsappTemplate')->withCount('recipients')->latest('id')->paginate(20);

        return $this->paginated($paginator, $paginator->getCollection(), 'Platform campaigns fetched successfully.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audience_type' => ['required', 'in:all_members,selected_members'],
            'member_ids' => ['nullable', 'array', 'max:5000'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'scheduled_for' => ['nullable', 'date'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.in_app' => ['nullable', 'array'],
            'channels.in_app.title' => ['required_with:channels.in_app', 'string', 'max:255'],
            'channels.in_app.body' => ['required_with:channels.in_app', 'string', 'max:4000'],
            'channels.in_app.notification_type' => ['nullable', 'string', 'max:100'],
            'channels.whatsapp' => ['nullable', 'array'],
            'channels.whatsapp.whatsapp_template_id' => ['required_with:channels.whatsapp', 'integer'],
            'channels.whatsapp.template_parameters' => ['nullable', 'array'],
            'channels.whatsapp.template_parameters.*' => ['string', 'max:250'],
        ]);

        return $this->success($this->campaigns->create(null, $request->user(), $data), 'Platform campaign draft created.', 201);
    }

    public function preview(CommunicationCampaign $campaign)
    {
        return $this->success($this->campaigns->preview($this->resolve($campaign)), 'Platform campaign preview generated.');
    }

    public function send(Request $request, CommunicationCampaign $campaign)
    {
        $validated = $request->validate(['scheduled_for' => ['nullable', 'date']]);

        return $this->success(
            $this->campaigns->schedule($this->resolve($campaign), $validated['scheduled_for'] ?? null),
            'Platform campaign scheduled successfully.',
        );
    }

    public function cancel(CommunicationCampaign $campaign)
    {
        $campaign = $this->resolve($campaign);
        abort_unless(in_array($campaign->status, ['draft', 'scheduled'], true), 422, 'A running campaign cannot be cancelled.');
        $campaign->forceFill(['status' => 'cancelled'])->save();

        return $this->success($campaign, 'Platform campaign cancelled successfully.');
    }

    private function resolve(CommunicationCampaign $campaign): CommunicationCampaign
    {
        return CommunicationCampaign::query()->whereNull('gym_id')->findOrFail($campaign->id);
    }
}
