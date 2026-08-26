<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Biometric\EnrollMemberBiometricRequest;
use App\Models\BiometricDevice;
use App\Models\BiometricMemberLink;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Biometric\BiometricEnrollmentService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberBiometricController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $panel,
        private readonly BiometricEnrollmentService $enrollments,
        private readonly AuditLogService $audit,
    ) {}

    public function show(Request $request, User $member): View
    {
        [$gym, $profile] = $this->profile($request, $member);
        $devices = BiometricDevice::query()->with('branch')->where('gym_id', $gym->id)
            ->whereIn('branch_id', $this->panel->accessibleBranchIds($request, $gym))
            ->where('is_active', true)
            ->when($profile->branch_id, fn ($query) => $query->where('branch_id', $profile->branch_id))
            ->orderBy('name')->get();
        $links = $profile->biometricMemberLinks()->with('device.branch')->latest()->get();

        return view('web.gym.members.biometrics', [
            'pageTitle' => 'Member Biometric Setup',
            'breadcrumbs' => ['Gym', 'Members', $member->name, 'Biometrics'],
            'gym' => $gym,
            'member' => $member,
            'memberProfile' => $profile,
            'devices' => $devices,
            'links' => $links,
            'instructions' => $links->mapWithKeys(fn ($link) => [$link->id => $this->enrollments->instructions($link->device)]),
        ]);
    }

    public function store(EnrollMemberBiometricRequest $request, User $member): RedirectResponse
    {
        [$gym, $profile] = $this->profile($request, $member);
        $devices = BiometricDevice::query()->where('gym_id', $gym->id)
            ->whereIn('branch_id', $this->panel->accessibleBranchIds($request, $gym))
            ->whereIn('id', $request->validated('device_ids'))->get()->all();
        abort_unless(count($devices) === count($request->validated('device_ids')), 404);
        $links = $this->enrollments->enroll($profile->loadMissing('user'), $devices, $request->validated('modalities'), $request->user());
        $this->audit->log('web.gym.member_biometric.requested', 'create', $request, $profile, $gym, $profile->branch, context: ['link_ids' => collect($links)->pluck('id')->all()]);

        return back()->with('status', 'Biometric setup prepared. Follow the capture step shown for each device.');
    }

    public function confirm(Request $request, BiometricMemberLink $link): RedirectResponse
    {
        [$gym] = $this->authorizeLink($request, $link);
        $this->enrollments->confirm($link);
        $this->audit->log('web.gym.member_biometric.confirmed', 'update', $request, $link, $gym, $link->branch);

        return back()->with('status', 'Biometric enrollment confirmed.');
    }

    public function revoke(Request $request, BiometricMemberLink $link): RedirectResponse
    {
        [$gym] = $this->authorizeLink($request, $link);
        $this->enrollments->revoke($link->loadMissing(['device', 'memberProfile']));
        $this->audit->log('web.gym.member_biometric.revoked', 'update', $request, $link, $gym, $link->branch);

        return back()->with('status', 'Biometric access revoked for this device.');
    }

    private function profile(Request $request, User $member): array
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertAnyPermission($request, [PermissionName::MembersManage->value, PermissionName::AttendanceManage->value], $gym);
        $profile = MemberProfile::query()->with('branch')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        if ($profile->branch_id) {
            $this->panel->assertBranchAccessible($profile->branch, $request, $gym);
        }

        return [$gym, $profile];
    }

    private function authorizeLink(Request $request, BiometricMemberLink $link): array
    {
        $link->loadMissing(['branch', 'memberProfile.user', 'device']);
        [$gym, $profile] = $this->profile($request, $link->memberProfile->user);
        abort_unless((int) $link->gym_id === (int) $gym->id && (int) $link->member_profile_id === (int) $profile->id, 404);

        return [$gym, $profile];
    }
}
