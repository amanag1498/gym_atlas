<?php

namespace App\Services\Privacy;

use App\Models\ConsentRecord;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ConsentService
{
    public const POLICY_VERSION = '2026-09-21';

    /** @var array<string, array{required: bool, title: string, description: string}> */
    public const PURPOSES = [
        'core_account' => [
            'required' => true,
            'title' => 'Account and service data',
            'description' => 'Use your account, profile, and training information to provide Gym Atlas.',
        ],
        'health_and_fitness_data' => [
            'required' => false,
            'title' => 'Health and fitness data',
            'description' => 'Use steps, measurements, progress, workouts, and diet information to personalise your training experience.',
        ],
        'trainer_member_sharing' => [
            'required' => false,
            'title' => 'Trainer and member sharing',
            'description' => 'Allow the relevant trainer or member to view information needed for coaching.',
        ],
        'biometric_attendance' => [
            'required' => false,
            'title' => 'Biometric attendance',
            'description' => 'Use biometric identifiers for gym attendance when your gym offers this feature.',
        ],
        'location_data' => [
            'required' => false,
            'title' => 'Location',
            'description' => 'Use your location to show nearby gyms and relevant local options.',
        ],
        'photos' => [
            'required' => false,
            'title' => 'Photos',
            'description' => 'Store profile and progress photos that you choose to upload.',
        ],
        'notifications' => [
            'required' => false,
            'title' => 'Notifications',
            'description' => 'Send reminders, updates, and account notifications to your device.',
        ],
        'whatsapp' => [
            'required' => false,
            'title' => 'WhatsApp service reminders',
            'description' => 'Send membership, payment, schedule, and account reminders to your phone on WhatsApp. Marketing is not included.',
        ],
    ];

    public function state(User $user): array
    {
        $records = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('policy_version', self::POLICY_VERSION)
            ->latest('id')
            ->get()
            ->unique('purpose')
            ->keyBy('purpose');

        $items = [];
        foreach (self::PURPOSES as $purpose => $definition) {
            $record = $records->get($purpose);
            $granted = $record !== null && $record->consented_at !== null && $record->withdrawn_at === null;
            $items[] = [
                'purpose' => $purpose,
                'required' => $definition['required'],
                'title' => $definition['title'],
                'description' => $definition['description'],
                'version' => self::POLICY_VERSION,
                'granted' => $granted,
                'consented_at' => $granted ? $record->consented_at?->toIso8601String() : null,
            ];
        }

        return [
            'policy_version' => self::POLICY_VERSION,
            'items' => $items,
            'required_purposes' => collect($items)->where('required', true)->pluck('purpose')->values()->all(),
            'missing_required' => collect($items)->where('required', true)->where('granted', false)->pluck('purpose')->values()->all(),
        ];
    }

    public function record(User $user, string $purpose, Request $request): ConsentRecord
    {
        abort_if(
            $user->date_of_birth !== null && $user->date_of_birth->greaterThan(now()->subYears(18)),
            403,
            'A parent or guardian must complete consent for users under 18.',
        );

        $notice = self::PURPOSES[$purpose]['title'].' — '.self::PURPOSES[$purpose]['description'];
        if ($purpose === 'core_account') {
            $notice .= ' By continuing, you agree to our Terms of Service and Privacy Policy. Terms: '.route('public.terms').' Privacy: '.route('public.privacy-policy');
        }

        return ConsentRecord::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'policy_version' => self::POLICY_VERSION,
            'source' => $user->active_role ? $user->active_role.'_app' : 'app',
            'consented_at' => Carbon::now(),
            'withdrawn_at' => null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 65535),
            'notice_snapshot' => $notice,
            'notice_hash' => hash('sha256', $notice),
            'notice_url' => route('public.privacy-policy'),
        ]);
    }

    public function recordLoginAcceptance(
        User $user,
        Request $request,
        bool $enableOptionalFeatures = true,
    ): void {
        if (! $this->granted($user, 'core_account')) {
            $this->record($user, 'core_account', $request);
        }

        if (! $enableOptionalFeatures) {
            return;
        }

        foreach (array_keys(self::PURPOSES) as $purpose) {
            if ($purpose === 'core_account' || $this->hasDecision($user, $purpose)) {
                continue;
            }

            $this->record($user, $purpose, $request);
        }
    }

    public function withdraw(User $user, string $purpose, Request $request): void
    {
        $notice = self::PURPOSES[$purpose]['title'].' — '.self::PURPOSES[$purpose]['description'];
        ConsentRecord::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'policy_version' => self::POLICY_VERSION,
            'source' => $user->active_role ? $user->active_role.'_app' : 'app',
            'consented_at' => null,
            'withdrawn_at' => Carbon::now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 65535),
            'notice_snapshot' => $notice,
            'notice_hash' => hash('sha256', $notice),
            'notice_url' => route('public.privacy-policy'),
        ]);
        if ($purpose === 'notifications' || $purpose === 'core_account') {
            UserFcmToken::query()->where('user_id', $user->id)->delete();
        }
    }

    public function granted(User $user, string $purpose): bool
    {
        if (! isset(self::PURPOSES[$purpose])) {
            return false;
        }

        $latest = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('policy_version', self::POLICY_VERSION)
            ->latest('id')
            ->first();

        return $latest !== null && $latest->consented_at !== null && $latest->withdrawn_at === null;
    }

    private function hasDecision(User $user, string $purpose): bool
    {
        return ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('policy_version', self::POLICY_VERSION)
            ->exists();
    }

    /** @param array<int,int> $userIds
     * @return array<int,int>
     */
    public function grantedUserIds(array $userIds, string $purpose): array
    {
        if ($userIds === [] || ! isset(self::PURPOSES[$purpose])) {
            return [];
        }

        return ConsentRecord::query()
            ->whereIn('user_id', $userIds)
            ->where('purpose', $purpose)
            ->where('policy_version', self::POLICY_VERSION)
            ->latest('id')
            ->get()
            ->unique('user_id')
            ->filter(fn (ConsentRecord $record): bool => $record->consented_at !== null && $record->withdrawn_at === null)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function scopeGrantedUsers(Builder $query, string $userIdColumn, string $purpose): Builder
    {
        return $query->whereExists(function ($subquery) use ($userIdColumn, $purpose): void {
            $subquery->selectRaw('1')
                ->from('consent_records as latest_consent')
                ->whereColumn('latest_consent.user_id', $userIdColumn)
                ->where('latest_consent.purpose', $purpose)
                ->where('latest_consent.policy_version', self::POLICY_VERSION)
                ->whereNotNull('latest_consent.consented_at')
                ->whereNull('latest_consent.withdrawn_at')
                ->whereRaw(
                    'latest_consent.id = (select max(id) from consent_records where user_id = latest_consent.user_id and purpose = ? and policy_version = ?)',
                    [$purpose, self::POLICY_VERSION],
                );
        });
    }

    public function assertGranted(User $user, string $purpose): void
    {
        abort_unless(
            $this->granted($user, $purpose),
            403,
            'Consent is required for this feature.',
        );
    }
}
