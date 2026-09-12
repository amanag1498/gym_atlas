<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Workout\WorkoutPlanResource;
use App\Models\WorkoutHistoryImportBatch;
use App\Models\WorkoutPlan;
use App\Models\WorkoutPlanShare;
use App\Services\Audit\AuditLogService;
use App\Services\Workout\WorkoutAccessService;
use App\Services\Workout\WorkoutPlanPdfService;
use App\Services\Workout\WorkoutPortabilityService;
use Illuminate\Http\Request;

class WorkoutPortabilityController extends Controller
{
    public function __construct(
        private readonly WorkoutAccessService $workoutAccessService,
        private readonly WorkoutPlanPdfService $pdfService,
        private readonly WorkoutPortabilityService $portabilityService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function planPdf(Request $request, WorkoutPlan $workoutPlan)
    {
        $this->workoutAccessService->assertPlanAccess($request->user(), $workoutPlan);
        $pdf = $this->pdfService->generate($workoutPlan);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
        ]);
    }

    public function createShare(Request $request, WorkoutPlan $workoutPlan)
    {
        $this->workoutAccessService->assertPlanAccess($request->user(), $workoutPlan);
        $validated = $request->validate([
            'recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $created = $this->portabilityService->createShare($request->user(), $workoutPlan, $validated);

        return $this->success([
            'id' => $created['share']->id,
            'token' => $created['token'],
            'expires_at' => $created['share']->expires_at?->toIso8601String(),
            'share_url' => url('/api/member/workout-plan-shares/'.$created['token']),
        ], 'Workout plan share created successfully.', 201);
    }

    public function showShare(Request $request, string $token)
    {
        $share = $this->portabilityService->resolveShare($token, $request->user());

        return $this->success([
            'id' => $share->id,
            'shared_by' => $share->sharedBy?->only(['id', 'name', 'email']),
            'expires_at' => $share->expires_at?->toIso8601String(),
            'snapshot' => $share->snapshot,
        ], 'Workout plan share fetched successfully.');
    }

    public function revokeShare(Request $request, WorkoutPlanShare $share)
    {
        abort_unless((int) $share->shared_by_user_id === (int) $request->user()->id, 404);
        $share->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        return $this->success([
            'id' => $share->id,
            'status' => $share->status,
            'revoked_at' => $share->revoked_at?->toIso8601String(),
        ], 'Workout plan share revoked successfully.');
    }

    public function adoptShare(Request $request, string $token)
    {
        $validated = $request->validate(['name' => ['nullable', 'string', 'max:255']]);
        $plan = $this->portabilityService->adoptShare($request->user(), $token, $validated['name'] ?? null);

        $this->auditLogService->log(
            event: 'member.workout_plan.share_adopted',
            action: 'create',
            request: $request,
            subject: $plan,
            newValues: $plan->toArray(),
        );

        return $this->success(WorkoutPlanResource::make($plan->load('days.exercises.exercise')), 'Shared workout plan adopted successfully.', 201);
    }

    public function previewImport(Request $request)
    {
        $validated = $request->validate([
            'source_format' => ['nullable', 'string', 'max:40'],
            'source_filename' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone'],
            'csv_text' => ['nullable', 'string'],
            'rows' => ['nullable', 'array'],
            'rows.*' => ['array'],
        ]);
        $batch = $this->portabilityService->previewHistoryImport($request->user(), $request->user(), $validated);

        return $this->success($this->batchPayload($batch), 'Workout history import preview created successfully.', 201);
    }

    public function confirmImport(Request $request, WorkoutHistoryImportBatch $batch)
    {
        $confirmed = $this->portabilityService->confirmHistoryImport($request->user(), $batch);

        return $this->success($this->batchPayload($confirmed), 'Workout history import confirmed successfully.');
    }

    public function exportData(Request $request)
    {
        return response()->json($this->portabilityService->exportMemberData($request->user()), 200, [
            'Content-Disposition' => 'attachment; filename="gym-atlas-member-workout-export-'.$request->user()->id.'.json"',
        ]);
    }

    private function batchPayload(WorkoutHistoryImportBatch $batch): array
    {
        $batch->loadMissing('rows.matchedExercise');

        return [
            'id' => $batch->id,
            'source_format' => $batch->source_format,
            'source_filename' => $batch->source_filename,
            'status' => $batch->status,
            'timezone' => $batch->timezone,
            'summary' => $batch->summary,
            'confirmed_at' => $batch->confirmed_at?->toIso8601String(),
            'rows' => $batch->rows->map(fn ($row) => [
                'id' => $row->id,
                'row_number' => $row->row_number,
                'status' => $row->status,
                'matched_exercise_id' => $row->matched_exercise_id,
                'matched_exercise_name' => $row->matchedExercise?->name,
                'workout_session_id' => $row->workout_session_id,
                'normalized_payload' => $row->normalized_payload,
                'message' => $row->message,
            ])->values(),
        ];
    }
}
