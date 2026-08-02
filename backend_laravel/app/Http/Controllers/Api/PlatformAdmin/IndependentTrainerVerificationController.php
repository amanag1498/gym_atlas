<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\ReviewIndependentTrainerRequest;
use App\Http\Resources\PlatformAdmin\IndependentTrainerVerificationResource;
use App\Models\TrainerProfile;
use App\Services\Platform\IndependentTrainerVerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class IndependentTrainerVerificationController extends Controller
{
    public function __construct(private readonly IndependentTrainerVerificationService $verificationService) {}

    public function index(Request $request)
    {
        $paginator = $this->query($request)
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return $this->paginated(
            $paginator,
            IndependentTrainerVerificationResource::collection($paginator->getCollection()),
            'Independent trainer verification submissions loaded successfully.',
        );
    }

    public function show(TrainerProfile $trainerProfile)
    {
        return $this->success(
            IndependentTrainerVerificationResource::make($trainerProfile->load(['user', 'gym', 'branch', 'verificationReviewer'])),
            'Trainer verification submission loaded successfully.',
        );
    }

    public function update(ReviewIndependentTrainerRequest $request, TrainerProfile $trainerProfile)
    {
        $reviewedProfile = $this->verificationService->review($request, $trainerProfile, $request->validated());

        return $this->success(
            IndependentTrainerVerificationResource::make($reviewedProfile),
            'Independent trainer verification status updated successfully.',
        );
    }

    private function query(Request $request): Builder
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

        return $query;
    }
}
