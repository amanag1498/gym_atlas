<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\GymSelfEnrollmentLink;
use App\Models\GymSelfEnrollmentSubmission;
use App\Services\Audit\AuditLogService;
use App\Services\Members\GymSelfEnrollmentService;
use App\Services\Web\GymWebPanelService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SelfEnrollmentController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly GymSelfEnrollmentService $service,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembersManage->value, $gym);
        $links = $this->service->ensureLinks($gym, $request->user());

        return view('web.gym.self-enrollment.index', [
            'pageTitle' => 'Self-enrollment QR',
            'breadcrumbs' => ['Gym', 'Members', 'Self-enrollment QR'],
            'gym' => $gym,
            'links' => $links,
            'recentSubmissions' => GymSelfEnrollmentSubmission::query()
                ->with(['user', 'branch', 'link'])
                ->where('gym_id', $gym->id)
                ->latest('id')
                ->take(30)
                ->get(),
        ]);
    }

    public function toggle(Request $request, GymSelfEnrollmentLink $link): RedirectResponse
    {
        $gym = $this->authorizeLink($request, $link);
        $old = $link->is_active;
        $link->update(['is_active' => ! $old]);
        $this->auditLogService->log('web.gym.self_enrollment.toggled', 'update', $request, $link, $gym, $link->branch, ['is_active' => $old], ['is_active' => $link->is_active]);

        return back()->with('status', 'Self-enrollment link '.($link->is_active ? 'enabled' : 'disabled').'.');
    }

    public function rotate(Request $request, GymSelfEnrollmentLink $link): RedirectResponse
    {
        $gym = $this->authorizeLink($request, $link);
        $this->service->rotate($link, $request->user());
        $this->auditLogService->log('web.gym.self_enrollment.rotated', 'update', $request, $link, $gym, $link->branch, context: ['link_id' => $link->id]);

        return back()->with('status', 'A new QR link was generated. The previous QR no longer works.');
    }

    public function qr(Request $request, GymSelfEnrollmentLink $link): Response
    {
        $this->authorizeLink($request, $link);
        $url = route('public.self-enrollment.show', $link->token);
        $result = (new Builder(
            writer: new SvgWriter,
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 720,
            margin: 30,
            foregroundColor: new Color(15, 118, 110),
        ))->build();

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="gym-atlas-enrollment-'.$link->id.'.svg"',
        ]);
    }

    private function authorizeLink(Request $request, GymSelfEnrollmentLink $link)
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembersManage->value, $gym, $link->branch_id);
        abort_unless((int) $link->gym_id === (int) $gym->id, 404);
        $link->loadMissing('branch');

        return $gym;
    }
}
