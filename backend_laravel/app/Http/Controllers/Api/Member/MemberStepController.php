<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\SyncMemberDailyStepsRequest;
use App\Http\Resources\Workout\MemberDailyStepResource;
use App\Models\MemberDailyStep;
use App\Models\MemberProfile;
use App\Services\Member\MemberAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberStepController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
    ) {
    }

    public function sync(SyncMemberDailyStepsRequest $request)
    {
        $memberProfile = $this->resolveMemberProfile($request);

        $user = $request->user();
        $timezone = $this->memberAppService->stepTimezoneFor($user, $memberProfile);
        $stepDate = Carbon::parse($request->validated('date'), $timezone)->startOfDay();
        $validated = $request->validated();
        $todayDate = now($timezone)->toDateString();

        $record = DB::transaction(function () use ($user, $memberProfile, $stepDate, $validated, $todayDate) {
            $existing = MemberDailyStep::query()
                ->where('user_id', $user->id)
                ->whereDate('step_date', $stepDate->toDateString())
                ->lockForUpdate()
                ->first();

            $steps = (int) $validated['steps'];
            if ($existing !== null && $stepDate->toDateString() === $todayDate) {
                $steps = max((int) $existing->steps, $steps);
            }

            if ($existing === null) {
                return MemberDailyStep::query()->create([
                    'user_id' => $user->id,
                    'gym_id' => $memberProfile?->gym_id,
                    'step_date' => $stepDate->toDateString(),
                    'steps' => $steps,
                    'goal_steps' => (int) $validated['goalSteps'],
                    'distance_meters' => (int) ($validated['distanceMeters'] ?? 0),
                    'calories_estimated' => (int) ($validated['caloriesEstimated'] ?? 0),
                    'source' => $validated['source'],
                    'synced_at' => now(),
                ]);
            }

            $existing->fill([
                'gym_id' => $memberProfile?->gym_id,
                'steps' => $steps,
                'goal_steps' => (int) $validated['goalSteps'],
                'distance_meters' => (int) ($validated['distanceMeters'] ?? 0),
                'calories_estimated' => (int) ($validated['caloriesEstimated'] ?? 0),
                'source' => $validated['source'],
                'synced_at' => now(),
            ]);
            $existing->save();

            return $existing->refresh();
        });

        $this->memberAppService->forgetStepSummaryCacheFor($user, $memberProfile);

        return $this->success(
            MemberDailyStepResource::make($record),
            'Member daily steps synced successfully.'
        );
    }

    public function today(Request $request)
    {
        $memberProfile = $this->resolveMemberProfile($request);
        $today = now($this->memberAppService->stepTimezoneFor($request->user(), $memberProfile))->toDateString();

        $record = MemberDailyStep::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('step_date', $today)
            ->first();

        if ($record !== null) {
            return $this->success(MemberDailyStepResource::make($record));
        }

        return $this->success([
            'date' => $today,
            'steps' => 0,
            'goalSteps' => 10000,
            'distanceMeters' => 0,
            'caloriesEstimated' => 0,
            'source' => null,
            'lastSyncedAt' => null,
        ]);
    }

    public function summary(Request $request)
    {
        $memberProfile = $this->resolveMemberProfile($request);
        $timezone = $this->memberAppService->stepTimezoneFor($request->user(), $memberProfile);
        $range = $request->query('range', '7d');
        if (! in_array($range, ['7d', '30d'], true)) {
            throw ValidationException::withMessages([
                'range' => ['The selected range is invalid.'],
            ]);
        }

        $days = $range === '30d' ? 30 : 7;
        $end = now($timezone)->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        $rows = MemberDailyStep::query()
            ->where('user_id', $request->user()->id)
            ->where('step_date', '>=', $start->copy()->startOfDay()->toDateTimeString())
            ->where('step_date', '<=', $end->copy()->endOfDay()->toDateTimeString())
            ->orderBy('step_date')
            ->get()
            ->keyBy(fn (MemberDailyStep $step) => $step->step_date->toDateString());

        $summary = Collection::times($days, function (int $offset) use ($start, $rows) {
            $date = $start->copy()->addDays($offset - 1)->toDateString();
            $record = $rows->get($date);

            if ($record !== null) {
                return MemberDailyStepResource::make($record)->resolve();
            }

            return [
                'date' => $date,
                'steps' => 0,
                'goalSteps' => 10000,
                'progressPercent' => 0,
            ];
        })->values();

        return $this->success($summary->all());
    }

    private function resolveMemberProfile(Request $request): ?MemberProfile
    {
        return $request->user()->loadMissing('memberProfile')->memberProfile;
    }
}
