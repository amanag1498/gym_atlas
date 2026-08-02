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
            ->with(['user', 'gym', 'branch', 'verificationReviewer'])
            ->latest('id');

        if ($request->string('status')->toString() === 'not_submitted') {
            $query->where('verification_status', 'pending')->whereNull('verification_submitted_at');
        } elseif ($request->filled('status')) {
            $query->where('verification_status', $request->string('status')->toString());
            if ($request->string('status')->toString() === 'pending') {
                $query->whereNotNull('verification_submitted_at');
            }
        } else {
            $query->where(fn (Builder $scope) => $scope
                ->whereNotNull('verification_submitted_at')
                ->orWhere('verification_status', '!=', 'pending'));
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
            ->selectRaw('verification_status, COUNT(*) as aggregate')
            ->groupBy('verification_status')
            ->pluck('aggregate', 'verification_status');

        return view('web.admin.trainer-verifications.index', [
            'pageTitle' => 'Trainer Verification',
            'breadcrumbs' => ['Platform', 'Trainer Verification'],
            'submissions' => $query->paginate(15)->withQueryString(),
            'counts' => $counts,
            'notSubmittedCount' => TrainerProfile::query()
                ->where('verification_status', 'pending')
                ->whereNull('verification_submitted_at')
                ->count(),
        ]);
    }

    public function show(TrainerProfile $trainerProfile): View
    {
        $trainerProfile->load(['user', 'gym', 'branch', 'verificationReviewer']);

        $auditLogs = ActivityLog::query()
            ->with('actor:id,name,email')
            ->where('subject_type', $trainerProfile->getMorphClass())
            ->where('subject_id', $trainerProfile->id)
            ->where('event', 'like', 'platform.independent_trainer.verification_%')
            ->latest('occurred_at')
            ->get();

        return view('web.admin.trainer-verifications.show', [
            'pageTitle' => $trainerProfile->user?->name ?? 'Trainer Verification',
            'breadcrumbs' => ['Platform', 'Trainer Verification', $trainerProfile->user?->name ?? 'Submission'],
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
}
