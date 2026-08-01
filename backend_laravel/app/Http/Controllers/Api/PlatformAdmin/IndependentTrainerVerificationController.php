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
        $this->ensureIndependent($trainerProfile);

        return $this->success(
            IndependentTrainerVerificationResource::make($trainerProfile->load(['user', 'verificationReviewer'])),
            'Independent trainer verification submission loaded successfully.',
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

        return $query;
    }

    private function ensureIndependent(TrainerProfile $trainerProfile): void
    {
        abort_if($trainerProfile->gym_id !== null || $trainerProfile->branch_id !== null, 404);
    }
}
