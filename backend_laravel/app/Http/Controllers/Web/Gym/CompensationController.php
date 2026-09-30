<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\CompensationProfile;
use App\Models\MemberMembership;
use App\Models\PayrollStatement;
use App\Services\Audit\AuditLogService;
use App\Services\Billing\CommissionService;
use App\Services\Billing\CompensationTeamService;
use App\Services\Billing\PayrollService;
use App\Services\Web\GymWebPanelService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompensationController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly CommissionService $commissionService,
        private readonly CompensationTeamService $compensationTeamService,
        private readonly PayrollService $payrollService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsView->value, $gym);
        $monthInput = $request->string('month')->toString();
        abort_if($monthInput !== '' && ! Carbon::canBeCreatedFromFormat($monthInput, 'Y-m'), 422, 'Choose a valid statement month.');
        $month = $monthInput !== '' ? Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth() : now()->startOfMonth();
        $branchId = $request->integer('branch') ?: null;
        if ($branchId) {
            abort_unless(in_array($branchId, $this->gymWebPanelService->accessibleBranchIds($request, $gym), true), 403);
        }
        $scopeBranch = fn ($query) => $query->when($branchId, fn ($scoped) => $scoped
            ->where(fn ($branch) => $branch->whereNull('branch_id')->orWhere('branch_id', $branchId)));
        $profiles = CompensationProfile::query()->with(['user', 'branch'])->where('gym_id', $gym->id)
            ->tap($scopeBranch)->orderBy('worker_type')->get();
        $statements = PayrollStatement::query()->with(['user', 'branch', 'payments'])->where('gym_id', $gym->id)
            ->tap($scopeBranch)->whereDate('period_start', $month)->orderByDesc('net_payable_amount')->get();
        $earnings = CommissionEarning::query()->with(['recipient', 'allocation.membership.member', 'payment'])
            ->where('gym_id', $gym->id)->tap($scopeBranch)->where('status', 'earned')->whereBetween('earned_at', [$month, $month->copy()->endOfMonth()])
            ->latest('earned_at')->limit(100)->get();
        $editingProfile = $request->integer('edit_profile')
            ? $profiles->firstWhere('id', $request->integer('edit_profile'))
            : null;
        $activeProfiles = $profiles->filter(fn (CompensationProfile $profile): bool => $profile->is_active
            && (! $profile->effective_from || $profile->effective_from->lte($month->copy()->endOfMonth()))
            && (! $profile->effective_until || $profile->effective_until->gte($month)));
        $statementCommission = (float) $statements->sum('commission_amount');
        $earnedCommission = (float) CommissionEarning::query()->where('gym_id', $gym->id)->where('status', 'earned')
            ->tap($scopeBranch)->whereBetween('earned_at', [$month, $month->copy()->endOfMonth()])->sum('amount');

        return view('web.gym.compensation.index', [
            'pageTitle' => 'Salary & Commission',
            'breadcrumbs' => ['Gym', 'Finance', 'Salary & Commission'],
            'gym' => $gym,
            'month' => $month,
            'profiles' => $profiles,
            'statements' => $statements,
            'earnings' => $earnings,
            'teamMembers' => $this->compensationTeamService->eligibleRecipients($gym->id, $branchId),
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'canManage' => $this->gymWebPanelService->canPermission($request, PermissionName::PaymentsManage->value, $gym),
            'editingProfile' => $editingProfile,
            'overview' => [
                'active_profiles' => $activeProfiles->count(),
                'salary_commitment' => (float) $activeProfiles->sum('monthly_salary'),
                'earned_commission' => $earnedCommission,
                'unprocessed_commission' => max(0, $earnedCommission - $statementCommission),
                'net_payable' => (float) $statements->sum('net_payable_amount'),
                'paid' => (float) $statements->sum('paid_amount'),
                'remaining' => max(0, (float) $statements->sum('net_payable_amount') - (float) $statements->sum('paid_amount')),
                'attention_count' => $statements->whereIn('status', ['draft', 'partially_paid'])->count(),
            ],
        ]);
    }

    public function showStatement(Request $request, PayrollStatement $statement): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($statement->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsView->value, $gym, $statement->branch_id);
        $statement->load(['user', 'branch', 'payments' => fn ($query) => $query->with(['paidBy', 'ledgerEntry'])->latest('paid_at')]);
        $earnings = CommissionEarning::query()
            ->with(['allocation.membership.member', 'payment'])
            ->where('gym_id', $gym->id)
            ->where('recipient_user_id', $statement->user_id)
            ->where('status', 'earned')
            ->whereBetween('earned_at', [$statement->period_start->copy()->startOfDay(), $statement->period_end->copy()->endOfDay()])
            ->latest('earned_at')
            ->get();

        return view('web.gym.compensation.show', [
            'pageTitle' => 'Payout Statement',
            'breadcrumbs' => ['Gym', 'Finance', 'Salary & Commission', $statement->user?->name ?? 'Statement'],
            'gym' => $gym,
            'statement' => $statement,
            'earnings' => $earnings,
            'canManage' => $this->gymWebPanelService->canPermission($request, PermissionName::PaymentsManage->value, $gym, $statement->branch_id),
        ]);
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym, $request->integer('branch_id') ?: null);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'worker_type' => ['nullable', 'in:trainer,staff'],
            'monthly_salary' => ['required', 'numeric', 'min:0'],
            'payout_day' => ['required', 'integer', 'min:1', 'max:28'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['branch_id'])) {
            abort_unless(in_array((int) $data['branch_id'], $this->gymWebPanelService->selectedBranchIds($request, $gym), true), 403);
        }
        $selectedTeamMember = $this->compensationTeamService
            ->eligibleRecipients($gym->id, $data['branch_id'] ?? null)
            ->firstWhere('id', (int) $data['user_id']);
        abort_unless($selectedTeamMember, 422, 'Select an active trainer or staff member from this gym and branch.');
        $profile = CompensationProfile::query()->updateOrCreate(['gym_id' => $gym->id, 'user_id' => $data['user_id']], [
            ...$data,
            'gym_id' => $gym->id,
            'worker_type' => $selectedTeamMember->getAttribute('compensation_role'),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $this->auditLogService->log('web.gym.compensation.profile.saved', 'update', $request, $profile, $gym, $profile->branch, [], $profile->toArray());

        return back()->with('status', 'Compensation profile saved.');
    }

    public function updateMembership(Request $request, MemberMembership $membership): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($membership->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym, $membership->branch_id);
        $data = $request->validate([
            'commissions' => ['nullable', 'array', 'max:10'],
            'commissions.*.recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'commissions.*.recipient_type' => ['nullable', 'in:trainer,staff'],
            'commissions.*.category' => ['required_with:commissions.*.recipient_user_id', 'in:pt,sales'],
            'commissions.*.calculation_type' => ['required_with:commissions.*.recipient_user_id', 'in:percentage,fixed'],
            'commissions.*.value' => ['nullable', 'numeric', 'min:0'],
            'commissions.*.recurrence' => ['required_with:commissions.*.recipient_user_id', 'in:one_time,recurring'],
        ]);
        $this->commissionService->configure($membership, $data['commissions'] ?? []);
        $this->auditLogService->log('web.gym.membership.commissions.updated', 'update', $request, $membership, $gym, $membership->branch, [], ['commissions' => $data['commissions'] ?? []]);

        return back()->with('status', 'Membership commission split updated.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $count = $this->payrollService->generate($gym, Carbon::createFromFormat('Y-m', $data['month']));

        return back()->with('status', "Generated or refreshed {$count} payout statements.");
    }

    public function pay(Request $request, PayrollStatement $statement): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($statement->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym, $statement->branch_id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'in:cash,upi,card,bank'],
            'reference' => ['nullable', 'string', 'max:160'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $payment = $this->payrollService->pay($statement, $request->user(), $data);
        $this->auditLogService->log('web.gym.payroll.paid', 'create', $request, $payment, $gym, $statement->branch, [], $payment->toArray());

        return back()->with('status', 'Payout recorded and posted to the finance ledger.');
    }
}
