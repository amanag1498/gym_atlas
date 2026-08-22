<?php

namespace App\Http\Controllers\Api\Gym\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationCampaign;
use App\Services\Authorization\ScopeResolver;
use App\Services\Communication\CommunicationCampaignService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly CommunicationCampaignService $campaigns,
    ) {}

    public function index(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);
        $paginator = CommunicationCampaign::query()->where('gym_id', $gym->id)
            ->with('channels.whatsappTemplate')->withCount('recipients')->latest('id')->paginate(20);

        return $this->paginated($paginator, $paginator->getCollection(), 'Campaigns fetched successfully.');
    }

    public function store(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request);
        $data = $this->validated($request);
        if (! empty($data['branch_id'])) {
            $branch = $this->scopeResolver->resolveBranch($request->merge(['branch_id' => $data['branch_id']]), true);
            abort_unless((int) $branch->gym_id === (int) $gym->id, 422, 'The branch does not belong to this gym.');
        }

        return $this->success($this->campaigns->create($gym, $request->user(), $data), 'Campaign draft created.', 201);
    }

    public function show(Request $request, CommunicationCampaign $campaign)
    {
        $campaign = $this->resolve($request, $campaign);

        return $this->success($campaign->load(['channels.whatsappTemplate'])->loadCount([
            'recipients as sent_count' => fn ($query) => $query->where('status', 'sent'),
            'recipients as skipped_count' => fn ($query) => $query->where('status', 'skipped'),
            'recipients as failed_count' => fn ($query) => $query->where('status', 'failed'),
        ]), 'Campaign fetched successfully.');
    }

    public function preview(Request $request, CommunicationCampaign $campaign)
    {
        return $this->success($this->campaigns->preview($this->resolve($request, $campaign)), 'Campaign preview generated.');
    }

    public function send(Request $request, CommunicationCampaign $campaign)
    {
        $validated = $request->validate(['scheduled_for' => ['nullable', 'date']]);

        return $this->success(
            $this->campaigns->schedule($this->resolve($request, $campaign), $validated['scheduled_for'] ?? null),
            'Campaign scheduled successfully.',
        );
    }

    public function cancel(Request $request, CommunicationCampaign $campaign)
    {
        $campaign = $this->resolve($request, $campaign);
        abort_unless(in_array($campaign->status, ['draft', 'scheduled'], true), 422, 'A running campaign cannot be cancelled.');
        $campaign->forceFill(['status' => 'cancelled'])->save();

        return $this->success($campaign, 'Campaign cancelled successfully.');
    }

    private function resolve(Request $request, CommunicationCampaign $campaign): CommunicationCampaign
    {
        $gym = $this->scopeResolver->resolveGym($request);

        return CommunicationCampaign::query()->where('gym_id', $gym->id)->findOrFail($campaign->id);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audience_type' => ['required', 'in:gym,branch,selected_members'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
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
    }
}
