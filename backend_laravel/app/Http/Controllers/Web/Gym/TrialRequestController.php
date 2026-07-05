<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trial\UpdateTrialRequestRequest;
use App\Models\User;
use App\Models\TrialRequest;
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
    ) {
    }

    public function index(Request $request): View|StreamedResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrialRequestsView->value, $gym);
        $query = $this->trialRequestService->queryForActor($request->user(), $request);

        if ($request->filled('assigned_trainer_id')) {
            $query->where('assigned_trainer_id', $request->integer('assigned_trainer_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search)
                ->orWhere('phone', 'like', $search));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('preferred_date', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('preferred_date', '<=', $request->date('end_date'));
        }

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
            'trainers' => User::query()
                ->whereHas('managedTrainerProfile', fn ($builder) => $builder
                    ->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $this->gymWebPanelService->accessibleBranchIds($request, $gym)))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateTrialRequestRequest $request, TrialRequest $trialRequest): RedirectResponse
    {
        $this->trialRequestService->updateForActor($request->user(), $trialRequest, $request->validated(), $request);

        return back()->with('status', 'Trial request updated successfully.');
    }

    public function show(Request $request, TrialRequest $trial): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrialRequestsView->value, $gym);
        $trial = $this->trialRequestService->resolveForActor($request->user(), $trial);

        return view('web.gym.trial-requests.show', [
            'pageTitle' => $trial->name,
            'breadcrumbs' => ['Gym', 'Trial Requests', $trial->name],
            'gym' => $gym,
            'trial' => $trial,
            'trainers' => User::query()
                ->whereHas('managedTrainerProfile', fn ($builder) => $builder
                    ->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $this->gymWebPanelService->accessibleBranchIds($request, $gym)))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function accept(Request $request, TrialRequest $trial): RedirectResponse
    {
        $this->trialRequestService->accept($request->user(), $trial, $request->string('notes')->toString() ?: null, $request);

        return back()->with('status', 'Trial request accepted successfully.');
    }

    public function reject(Request $request, TrialRequest $trial): RedirectResponse
    {
        $this->trialRequestService->reject($request->user(), $trial, $request->string('notes')->toString() ?: null, $request);

        return back()->with('status', 'Trial request rejected successfully.');
    }

    public function complete(Request $request, TrialRequest $trial): RedirectResponse
    {
        $this->trialRequestService->complete($request->user(), $trial, $request->string('notes')->toString() ?: null, $request);

        return back()->with('status', 'Trial request marked completed successfully.');
    }

    public function assignTrainer(Request $request, TrialRequest $trial): RedirectResponse
    {
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
}
