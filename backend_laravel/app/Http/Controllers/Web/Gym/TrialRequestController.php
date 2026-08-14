<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trial\UpdateTrialRequestRequest;
use App\Models\TrialRequest;
use App\Models\User;
use App\Services\Trials\TrialRequestService;
use App\Services\Web\CsvStreamService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialRequestController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly TrialRequestService $trialRequestService,
        private readonly CsvStreamService $csvStreamService,
    ) {}

    public function index(Request $request): View|StreamedResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrialRequestsView->value, $gym);
        $branchIds = $this->gymWebPanelService->selectedBranchIds($request, $gym);
        $query = $this->trialRequestService->queryForActor($request->user(), $request)
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $branchIds);

        $summaryQuery = TrialRequest::query()
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $branchIds);

        if ($request->string('export')->toString() === 'csv') {
            return $this->csvStreamService->download(
                'gym-trial-leads-'.$gym->id.'-'.now()->format('Ymd-His').'.csv',
                ['Name', 'Type', 'Phone', 'Email', 'Branch', 'Preferred Date', 'Preferred Time', 'Status', 'Assigned Trainer', 'Notes'],
                $query->with(['branch', 'assignedTrainer'])->get()->map(fn (TrialRequest $trialRequest) => [
                    $trialRequest->name,
                    $trialRequest->request_type,
                    $trialRequest->phone ?? '',
                    $trialRequest->email ?? '',
                    $trialRequest->branch?->name ?? '',
                    optional($trialRequest->preferred_date)->format('Y-m-d') ?? '',
                    $trialRequest->preferred_time ?? '',
                    $trialRequest->status,
                    $trialRequest->assignedTrainer?->name ?? '',
                    $trialRequest->notes ?? '',
                ]),
            );
        }

        $paginator = $query->paginate(15)->withQueryString();

        return view('web.gym.trial-requests.index', [
            'pageTitle' => 'Trial Requests',
            'breadcrumbs' => ['Gym', 'Trial Requests'],
            'gym' => $gym,
            'trialRequests' => $paginator,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'unassigned' => (clone $summaryQuery)->whereNull('assigned_trainer_id')->whereNotIn('status', ['rejected', 'converted'])->count(),
                'pending' => (clone $summaryQuery)->where('status', 'pending')->count(),
                'accepted' => (clone $summaryQuery)->where('status', 'accepted')->count(),
                'completed' => (clone $summaryQuery)->where('status', 'completed')->count(),
                'converted' => (clone $summaryQuery)->where('status', 'converted')->count(),
            ],
            'canManage' => $this->gymWebPanelService->canPermission($request, PermissionName::TrialRequestsManage->value, $gym),
            'trainers' => $this->activeTrainers($request, $gym->id),
        ]);
    }

    public function update(UpdateTrialRequestRequest $request, TrialRequest $trialRequest): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAccess($request, $gym->id, $trialRequest);
        $this->trialRequestService->updateForActor($request->user(), $trialRequest, $request->validated(), $request);

        return back()->with('status', 'Trial request updated successfully.');
    }

    public function show(Request $request, TrialRequest $trial): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrialRequestsView->value, $gym);
        abort_unless((int) $trial->gym_id === (int) $gym->id, 404);
        abort_unless(in_array((int) $trial->branch_id, $this->gymWebPanelService->accessibleBranchIds($request, $gym), true), 404);
        $trial = $this->trialRequestService->resolveForActor($request->user(), $trial);

        return view('web.gym.trial-requests.show', [
            'pageTitle' => $trial->name,
            'breadcrumbs' => ['Gym', 'Trial Requests', $trial->name],
            'gym' => $gym,
            'trial' => $trial,
            'canManage' => $this->gymWebPanelService->canPermission($request, PermissionName::TrialRequestsManage->value, $gym, $trial->branch_id),
            'trainers' => $this->activeTrainers($request, $gym->id, $trial->branch_id),
        ]);
    }

    public function accept(Request $request, TrialRequest $trial): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAccess($request, $gym->id, $trial);
        $this->trialRequestService->accept($request->user(), $trial, $request->string('notes')->toString() ?: null, $request);

        return back()->with('status', 'Trial request accepted successfully.');
    }

    public function reject(Request $request, TrialRequest $trial): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAccess($request, $gym->id, $trial);
        $this->trialRequestService->reject($request->user(), $trial, $request->string('notes')->toString() ?: null, $request);

        return back()->with('status', 'Trial request rejected successfully.');
    }

    public function complete(Request $request, TrialRequest $trial): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAccess($request, $gym->id, $trial);
        $this->trialRequestService->complete($request->user(), $trial, $request->string('notes')->toString() ?: null, $request);

        return back()->with('status', 'Trial request marked completed successfully.');
    }

    public function assignTrainer(Request $request, TrialRequest $trial): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAccess($request, $gym->id, $trial);
        $validated = $request->validate([
            'assigned_trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->trialRequestService->assignTrainer(
            $request->user(),
            $trial,
            $validated['assigned_trainer_id'] ?? null,
            $validated['notes'] ?? null,
            $request,
        );

        return back()->with('status', 'Trainer assignment updated successfully.');
    }

    public function convert(Request $request, TrialRequest $trial): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAccess($request, $gym->id, $trial);
        $validated = $request->validate([
            'existing_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'assigned_trainer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->trialRequestService->convert($request->user(), $trial, $validated, $request);

        return redirect()
            ->route('web.gym.members.show', array_merge(
                request()->only(['gym', 'branch']),
                ['member' => $result['member']->id]
            ))
            ->with('status', 'Trial request converted into member successfully.');
    }

    private function assertManageAccess(Request $request, int $gymId, TrialRequest $trial): void
    {
        abort_unless((int) $trial->gym_id === $gymId, 404);
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless(in_array((int) $trial->branch_id, $this->gymWebPanelService->accessibleBranchIds($request, $gym), true), 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrialRequestsManage->value, $gym, $trial->branch_id);
    }

    private function activeTrainers(Request $request, int $gymId, ?int $branchId = null)
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branchIds = $branchId ? [$branchId] : $this->gymWebPanelService->accessibleBranchIds($request, $gym);

        return User::query()
            ->with('managedTrainerProfile:id,user_id,gym_id,branch_id')
            ->where('is_active', true)
            ->whereHas('managedTrainerProfile', fn ($builder) => $builder
                ->where('gym_id', $gymId)
                ->where('is_active', true)
                ->where('status', 'active')
                ->where(fn ($scope) => $scope->whereNull('branch_id')->orWhereIn('branch_id', $branchIds)))
            ->orderBy('name')
            ->get();
    }
}
