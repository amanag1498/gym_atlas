<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\ReviewIndependentTrainerRequest;
use App\Models\ActivityLog;
use App\Models\TrainerProfile;
use App\Services\Platform\IndependentTrainerVerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndependentTrainerVerificationController extends Controller
{
    public function __construct(private readonly IndependentTrainerVerificationService $verificationService) {}

    public function index(Request $request): View
    {
        $query = TrainerProfile::query()
            ->whereNull('gym_id')
            ->whereNull('branch_id')
            ->with(['user', 'verificationReviewer'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('verification_status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('specialization', 'like', $search)
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery
                        ->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search));
            });
        }

        $counts = TrainerProfile::query()
            ->whereNull('gym_id')
            ->whereNull('branch_id')
            ->selectRaw('verification_status, COUNT(*) as aggregate')
            ->groupBy('verification_status')
            ->pluck('aggregate', 'verification_status');

        return view('web.admin.trainer-verifications.index', [
            'pageTitle' => 'Independent Trainer Verification',
            'breadcrumbs' => ['Platform', 'Independent Trainer Verification'],
            'submissions' => $query->paginate(15)->withQueryString(),
            'counts' => $counts,
        ]);
    }

    public function show(TrainerProfile $trainerProfile): View
    {
        $this->ensureIndependent($trainerProfile);
        $trainerProfile->load(['user', 'verificationReviewer']);

        $auditLogs = ActivityLog::query()
            ->with('actor:id,name,email')
            ->where('subject_type', $trainerProfile->getMorphClass())
            ->where('subject_id', $trainerProfile->id)
            ->where('event', 'like', 'platform.independent_trainer.verification_%')
            ->latest('occurred_at')
            ->get();

        return view('web.admin.trainer-verifications.show', [
            'pageTitle' => $trainerProfile->user?->name ?? 'Trainer Verification',
            'breadcrumbs' => ['Platform', 'Independent Trainer Verification', $trainerProfile->user?->name ?? 'Submission'],
            'trainerProfile' => $trainerProfile,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function update(ReviewIndependentTrainerRequest $request, TrainerProfile $trainerProfile): RedirectResponse
    {
        $reviewedProfile = $this->verificationService->review($request, $trainerProfile, $request->validated());

        return redirect()
            ->route('web.admin.trainer-verifications.show', $reviewedProfile)
            ->with('status', 'Trainer verification updated to '.str($reviewedProfile->verification_status)->title().'.');
    }

    private function ensureIndependent(TrainerProfile $trainerProfile): void
    {
        abort_if($trainerProfile->gym_id !== null || $trainerProfile->branch_id !== null, 404);
    }
}
