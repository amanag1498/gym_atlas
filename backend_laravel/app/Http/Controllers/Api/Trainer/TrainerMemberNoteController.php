<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreTrainerMemberNoteRequest;
use App\Http\Requests\Trainer\UpdateTrainerMemberNoteRequest;
use App\Http\Resources\Trainer\TrainerMemberNoteResource;
use App\Models\TrainerMemberNote;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;

class TrainerMemberNoteController extends Controller
{
    public function __construct(
        private readonly TrainerScopeService $trainerScopeService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request, User $member)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $this->trainerScopeService->resolveAssignedMember($trainerProfile, $member);

        $paginator = TrainerMemberNote::query()
            ->with(['member', 'trainer'])
            ->where('trainer_id', $trainerProfile->user_id)
            ->where('member_id', $member->id)
            ->latest('created_at')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, TrainerMemberNoteResource::collection($paginator->getCollection()), 'Trainer notes fetched successfully.');
    }

    public function store(StoreTrainerMemberNoteRequest $request, User $member)
    {
        abort_unless($request->user()->active_role === RoleName::Trainer->value, 403);

        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $memberProfile = $this->trainerScopeService->resolveAssignedMember($trainerProfile, $member);

        $note = TrainerMemberNote::query()->create([
            'trainer_id' => $trainerProfile->user_id,
            'member_id' => $member->id,
            ...$request->validated(),
        ]);

        $this->auditLogService->log(
            event: 'trainer.note.created',
            action: 'create',
            request: $request,
            subject: $note,
            gym: $trainerProfile->gym,
            branch: $memberProfile->branch,
            newValues: $note->toArray(),
        );

        return $this->success(TrainerMemberNoteResource::make($note->load(['member', 'trainer'])), 'Trainer note created successfully.', 201);
    }

    public function update(UpdateTrainerMemberNoteRequest $request, TrainerMemberNote $trainerMemberNote)
    {
        abort_unless($request->user()->active_role === RoleName::Trainer->value, 403);

        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $note = $this->trainerScopeService->resolveTrainerNote($trainerProfile, $trainerMemberNote);
        $oldValues = $note->toArray();
        $note->update($request->validated());

        $this->auditLogService->log(
            event: 'trainer.note.updated',
            action: 'update',
            request: $request,
            subject: $note,
            gym: $trainerProfile->gym,
            branch: $trainerProfile->branch,
            oldValues: $oldValues,
            newValues: $note->fresh()->toArray(),
        );

        return $this->success(TrainerMemberNoteResource::make($note->fresh(['member', 'trainer'])), 'Trainer note updated successfully.');
    }

    public function complete(Request $request, TrainerMemberNote $trainerMemberNote)
    {
        abort_unless($request->user()->active_role === RoleName::Trainer->value, 403);

        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $note = $this->trainerScopeService->resolveTrainerNote($trainerProfile, $trainerMemberNote);
        $oldValues = $note->toArray();
        $note->update(['completed_at' => now()]);

        $this->auditLogService->log(
            event: 'trainer.note.completed',
            action: 'update',
            request: $request,
            subject: $note,
            gym: $trainerProfile->gym,
            branch: $trainerProfile->branch,
            oldValues: $oldValues,
            newValues: $note->fresh()->toArray(),
        );

        return $this->success(TrainerMemberNoteResource::make($note->fresh(['member', 'trainer'])), 'Trainer note marked completed successfully.');
    }
}
