<?php

use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\GymPlatformSubscriptionController as AdminGymPlatformSubscriptionController;
use App\Http\Controllers\Web\Admin\GymController as AdminGymController;
use App\Http\Controllers\Web\Admin\GymOwnerController as AdminGymOwnerController;
use App\Http\Controllers\Web\Admin\ListingController as AdminListingController;
use App\Http\Controllers\Web\Admin\PlatformSubscriptionPlanController as AdminPlatformSubscriptionPlanController;
use App\Http\Controllers\Web\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Web\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Web\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Web\Admin\UserController as AdminUserController;
use App\Http\Controllers\Web\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Web\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Web\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Web\Admin\WorkoutBookController as AdminWorkoutBookController;
use App\Http\Controllers\Web\Auth\PanelAuthController;
use App\Http\Controllers\Web\Gym\AnnouncementController as WebGymAnnouncementController;
use App\Http\Controllers\Web\Gym\AttendanceController as WebGymAttendanceController;
use App\Http\Controllers\Web\Gym\AuditLogController as WebGymAuditLogController;
use App\Http\Controllers\Web\Gym\BranchController as WebGymBranchController;
use App\Http\Controllers\Web\Gym\DashboardController as WebGymDashboardController;
use App\Http\Controllers\Web\Gym\GymProfileController as WebGymProfileController;
use App\Http\Controllers\Web\Gym\MemberController as WebGymMemberController;
use App\Http\Controllers\Web\Gym\MemberMembershipController as WebGymMemberMembershipController;
use App\Http\Controllers\Web\Gym\MembershipPlanController as WebGymMembershipPlanController;
use App\Http\Controllers\Web\Gym\PaymentController as WebGymPaymentController;
use App\Http\Controllers\Web\Gym\PublicListingController as WebGymPublicListingController;
use App\Http\Controllers\Web\Gym\ReminderController as WebGymReminderController;
use App\Http\Controllers\Web\Gym\ReportController as WebGymReportController;
use App\Http\Controllers\Web\Gym\SettingController as WebGymSettingController;
use App\Http\Controllers\Web\Gym\StaffController as WebGymStaffController;
use App\Http\Controllers\Web\Gym\TrainerController as WebGymTrainerController;
use App\Http\Controllers\Web\Gym\TrialRequestController as WebGymTrialRequestController;
use App\Http\Requests\Web\Public\StoreContactSubmissionRequest;
use App\Http\Requests\Web\Public\StoreGymTrialRequest;
use App\Models\ContactSubmission;
use App\Models\Gym;
use App\Models\Facility;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use App\Services\Discovery\GymDiscoveryService;
use App\Services\Platform\PlatformSettingService;
use App\Services\Trials\TrialRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

Route::get('/', function (GymDiscoveryService $discoveryService) {
    if (! Schema::hasTable('gyms')) {
        return view('public.home', [
            'stats' => [
                'active_gyms' => 0,
                'trainers' => 0,
                'members' => 0,
                'trial_requests' => 0,
            ],
            'featuredGyms' => collect(),
        ]);
    }

    $stats = [
        'active_gyms' => Gym::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->count(),
        'trainers' => TrainerProfile::query()
            ->where('is_active', true)
            ->count(),
        'members' => MemberProfile::query()->count(),
        'trial_requests' => TrialRequest::query()->count(),
    ];

    $featuredGyms = Gym::query()
        ->with(['facilities', 'membershipPlans' => fn ($query) => $query->where('status', 'active')->orderBy('plan_price')])
        ->where('public_listing_enabled', true)
        ->where('public_listing_approval_status', 'approved')
        ->where(function ($query): void {
            $query->where('approval_status', 'approved')
                ->orWhereNull('approval_status');
        })
        ->where('is_active', true)
        ->where('status', 'active')
        ->where('is_featured', true)
        ->orderByDesc('is_promoted')
        ->orderByDesc('is_featured')
        ->orderBy('name')
        ->limit(6)
        ->get()
        ->map(fn (Gym $gym) => $discoveryService->publicGymBySlug($gym->slug));

    return view('public.home', [
        'stats' => $stats,
        'featuredGyms' => $featuredGyms,
    ]);
})->name('public.home');

Route::get('/gyms', function (Request $request, GymDiscoveryService $discoveryService) {
    $filters = array_filter([
        'search' => trim((string) $request->string('search')),
        'city' => trim((string) $request->string('city')),
        'facilities' => array_values(array_filter((array) $request->input('facilities', []))),
        'trial_available' => $request->boolean('trial_available') ? true : null,
        'verified_only' => $request->boolean('verified_only') ? true : null,
        'featured_only' => $request->boolean('featured_only') ? true : null,
        'women_friendly' => $request->boolean('women_friendly') ? true : null,
        'women_only' => $request->boolean('women_only') ? true : null,
        'personal_training_available' => $request->boolean('personal_training_available') ? true : null,
        'open_now' => $request->boolean('open_now') ? true : null,
        'min_price' => $request->filled('min_price') ? $request->input('min_price') : null,
        'max_price' => $request->filled('max_price') ? $request->input('max_price') : null,
        'latitude' => $request->filled('latitude') ? $request->input('latitude') : null,
        'longitude' => $request->filled('longitude') ? $request->input('longitude') : null,
        'distance' => $request->filled('distance') ? $request->input('distance') : null,
        'per_page' => 12,
        'page' => $request->integer('page', 1),
    ], static fn ($value) => $value !== null && $value !== '' && $value !== []);

    $gyms = $discoveryService->list($filters);

    $publicGymQuery = Gym::query()
        ->where('public_listing_enabled', true)
        ->where('public_listing_approval_status', 'approved')
        ->where(function ($query): void {
            $query->where('approval_status', 'approved')
                ->orWhereNull('approval_status');
        })
        ->where('is_active', true)
        ->where('status', 'active');

    $cities = (clone $publicGymQuery)
        ->whereNotNull('city')
        ->where('city', '!=', '')
        ->orderBy('city')
        ->pluck('city')
        ->unique()
        ->values();

    $facilities = Facility::query()
        ->where('status', 'active')
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    return view('public.gyms.index', [
        'gyms' => $gyms,
        'cities' => $cities,
        'facilities' => $facilities,
    ]);
})->name('public.gyms.index');

Route::post('/gyms/{slug}/trial-request', function (
    StoreGymTrialRequest $request,
    string $slug,
    GymDiscoveryService $discoveryService,
    TrialRequestService $trialRequestService,
) {
    $gym = $discoveryService->publicGymBySlug($slug);

    $payload = array_merge($request->validated(), [
        'gym_id' => $gym->id,
    ]);

    $trialRequestService->createPublic(
        $payload,
        $request->user(),
        $request,
    );

    return redirect()
        ->route('public.gyms.show', $gym->slug)
        ->with('success', ($payload['request_type'] ?? 'trial') === 'contact'
            ? 'Your enquiry has been sent to the gym successfully.'
            : 'Trial request submitted successfully.')
        ->withFragment('request-trial');
})->name('public.gyms.trial-request');

Route::get('/gyms/{slug}', function (string $slug, GymDiscoveryService $discoveryService) {
    return view('public.gyms.show', [
        'gym' => $discoveryService->publicGymBySlug($slug),
    ]);
})->name('public.gyms.show');

Route::view('/for-gyms', 'public.pages.for-gyms')->name('public.for-gyms');
Route::view('/for-trainers', 'public.pages.for-trainers')->name('public.for-trainers');
Route::view('/pricing', 'public.pages.pricing')->name('public.pricing');
Route::view('/about', 'public.pages.about')->name('public.about');
Route::get('/contact', function (Request $request, PlatformSettingService $platformSettingService) {
    return view('public.pages.contact', [
        'inquiryType' => (string) $request->query('inquiry_type', 'user'),
        'settings' => $platformSettingService->all(),
    ]);
})->name('public.contact');
Route::post('/contact', function (StoreContactSubmissionRequest $request) {
    $validated = $request->validated();

    ContactSubmission::query()->create(collect($validated)
        ->except('redirect_to')
        ->all());

    $redirectTo = (string) ($validated['redirect_to'] ?? '');
    $allowedRedirects = [
        route('public.contact', ['inquiry_type' => $validated['inquiry_type']]),
        route('public.for-gyms').'#lead-form',
        route('public.for-trainers').'#trainer-access',
    ];

    if (in_array($redirectTo, $allowedRedirects, true)) {
        return redirect($redirectTo)
            ->with('success', 'Your message has been submitted successfully.');
    }

    return redirect()
        ->route('public.contact', ['inquiry_type' => $request->validated('inquiry_type')])
        ->with('success', 'Your message has been submitted successfully.');
})->name('public.contact.store');
Route::get('/privacy-policy', function (PlatformSettingService $platformSettingService) {
    return view('public.pages.privacy-policy', [
        'settings' => $platformSettingService->all(),
    ]);
})->name('public.privacy-policy');

Route::get('/terms', function (PlatformSettingService $platformSettingService) {
    return view('public.pages.terms', [
        'settings' => $platformSettingService->all(),
    ]);
})->name('public.terms');

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [PanelAuthController::class, 'showAdminLogin'])->name('web.admin.login');
    Route::post('/admin/login', [PanelAuthController::class, 'loginAdmin'])->name('web.admin.login.store');
    Route::post('/admin/login/firebase', [PanelAuthController::class, 'loginAdminWithFirebase'])->name('web.admin.login.firebase');
    Route::get('/gym/login', [PanelAuthController::class, 'showGymLogin'])->name('web.gym.login');
    Route::post('/gym/login', [PanelAuthController::class, 'loginGym'])->name('web.gym.login.store');
    Route::post('/gym/login/firebase', [PanelAuthController::class, 'loginGymWithFirebase'])->name('web.gym.login.firebase');
});

Route::post('/logout', [PanelAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('web.logout');

Route::post('/admin/impersonation/stop', [AdminGymOwnerController::class, 'stopMockDashboard'])
    ->middleware('auth')
    ->name('web.admin.impersonation.stop');

Route::prefix('admin')
    ->name('web.admin.')
    ->middleware(['auth', 'web_platform_admin'])
    ->group(function (): void {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        Route::get('/gyms', [AdminGymController::class, 'index'])->name('gyms.index');
        Route::get('/gyms/create', [AdminGymController::class, 'create'])->name('gyms.create');
        Route::post('/gyms', [AdminGymController::class, 'store'])->name('gyms.store');
        Route::get('/gyms/{gym}', [AdminGymController::class, 'show'])->name('gyms.show');
        Route::get('/gyms/{gym}/edit', [AdminGymController::class, 'edit'])->name('gyms.edit');
        Route::put('/gyms/{gym}', [AdminGymController::class, 'update'])->name('gyms.update');
        Route::post('/gyms/{gym}/approve', [AdminGymController::class, 'approve'])->name('gyms.approve');
        Route::post('/gyms/{gym}/reject', [AdminGymController::class, 'reject'])->name('gyms.reject');
        Route::post('/gyms/{gym}/activate', [AdminGymController::class, 'activate'])->name('gyms.activate');
        Route::post('/gyms/{gym}/deactivate', [AdminGymController::class, 'deactivate'])->name('gyms.deactivate');
        Route::post('/gyms/{gym}/verify', [AdminGymController::class, 'verify'])->name('gyms.verify');
        Route::post('/gyms/{gym}/feature', [AdminGymController::class, 'feature'])->name('gyms.feature');
        Route::post('/gyms/{gym}/promote', [AdminGymController::class, 'promote'])->name('gyms.promote');
        Route::post('/gyms/{gym}/hide-listing', [AdminGymController::class, 'hideListing'])->name('gyms.hide-listing');
        Route::post('/gyms/{gym}/show-listing', [AdminGymController::class, 'showListing'])->name('gyms.show-listing');
        Route::post('/gyms/{gym}/listing', [AdminGymController::class, 'updateListingStatus'])->name('gyms.listing');

        Route::get('/gym-owners', [AdminGymOwnerController::class, 'index'])->name('gym-owners.index');
        Route::get('/gym-owners/create', [AdminGymOwnerController::class, 'create'])->name('gym-owners.create');
        Route::post('/gym-owners', [AdminGymOwnerController::class, 'store'])->name('gym-owners.store');
        Route::get('/gym-owners/{user}/dashboard', [AdminGymOwnerController::class, 'mockDashboard'])->name('gym-owners.dashboard');
        Route::get('/gym-owners/{user}/gyms/{gym}/dashboard', [AdminGymOwnerController::class, 'mockDashboard'])->name('gym-owners.gyms.dashboard');
        Route::get('/gym-owners/{user}/activity', [AdminGymOwnerController::class, 'activity'])->name('gym-owners.activity');
        Route::get('/gym-owners/{user}', [AdminGymOwnerController::class, 'show'])->name('gym-owners.show');
        Route::get('/gym-owners/{user}/edit', [AdminGymOwnerController::class, 'edit'])->name('gym-owners.edit');
        Route::put('/gym-owners/{user}', [AdminGymOwnerController::class, 'update'])->name('gym-owners.update');
        Route::post('/gym-owners/{user}/activate', [AdminGymOwnerController::class, 'activate'])->name('gym-owners.activate');
        Route::post('/gym-owners/{user}/deactivate', [AdminGymOwnerController::class, 'deactivate'])->name('gym-owners.deactivate');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/trainers', [AdminUserController::class, 'trainers'])->name('trainers.index');
        Route::get('/members', [AdminUserController::class, 'members'])->name('members.index');
        Route::get('/users/trainers', [AdminUserController::class, 'trainers'])->name('users.trainers');
        Route::get('/users/members', [AdminUserController::class, 'members'])->name('users.members');
        Route::get('/users/{user}/activity', [AdminUserController::class, 'activity'])->name('users.activity');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
        Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/toggle-active', [AdminUserController::class, 'toggleActive'])->name('users.toggle-active');

        Route::get('/facilities', [AdminCatalogController::class, 'facilities'])->name('facilities.index');
        Route::get('/exercise-book', [AdminCatalogController::class, 'exercises'])->name('exercises.index');
        Route::get('/exercise-book/create', [AdminCatalogController::class, 'createExercise'])->name('exercises.create');
        Route::post('/exercise-book', [AdminCatalogController::class, 'storeExercise'])->name('exercises.store');
        Route::get('/exercise-book/{exercise}/edit', [AdminCatalogController::class, 'editExercise'])->name('exercises.edit');
        Route::put('/exercise-book/{exercise}', [AdminCatalogController::class, 'updateExercise'])->name('exercises.update');
        Route::get('/facilities/create', [AdminCatalogController::class, 'createFacility'])->name('facilities.create');
        Route::post('/facilities', [AdminCatalogController::class, 'storeFacility'])->name('facilities.store');
        Route::get('/facilities/{facility}/edit', [AdminCatalogController::class, 'editFacility'])->name('facilities.edit');
        Route::put('/facilities/{facility}', [AdminCatalogController::class, 'updateFacility'])->name('facilities.update');
        Route::delete('/facilities/{facility}', [AdminCatalogController::class, 'destroyFacility'])->name('facilities.destroy');
        Route::post('/facilities/{facility}/toggle-status', [AdminCatalogController::class, 'toggleFacilityStatus'])->name('facilities.toggle-status');

        Route::get('/workout-books', [AdminWorkoutBookController::class, 'index'])->name('workout-books.index');
        Route::get('/workout-books/create', [AdminWorkoutBookController::class, 'create'])->name('workout-books.create');
        Route::post('/workout-books', [AdminWorkoutBookController::class, 'store'])->name('workout-books.store');
        Route::get('/workout-books/{workoutBook}/edit', [AdminWorkoutBookController::class, 'edit'])->name('workout-books.edit');
        Route::put('/workout-books/{workoutBook}', [AdminWorkoutBookController::class, 'update'])->name('workout-books.update');
        Route::delete('/workout-books/{workoutBook}', [AdminWorkoutBookController::class, 'destroy'])->name('workout-books.destroy');

        Route::get('/fitness-goals', [AdminCatalogController::class, 'fitnessGoals'])->name('fitness-goals.index');
        Route::get('/fitness-goals/create', [AdminCatalogController::class, 'createFitnessGoal'])->name('fitness-goals.create');
        Route::post('/fitness-goals', [AdminCatalogController::class, 'storeFitnessGoal'])->name('fitness-goals.store');
        Route::get('/fitness-goals/{fitnessGoal}/edit', [AdminCatalogController::class, 'editFitnessGoal'])->name('fitness-goals.edit');
        Route::put('/fitness-goals/{fitnessGoal}', [AdminCatalogController::class, 'updateFitnessGoal'])->name('fitness-goals.update');
        Route::delete('/fitness-goals/{fitnessGoal}', [AdminCatalogController::class, 'destroyFitnessGoal'])->name('fitness-goals.destroy');
        Route::post('/fitness-goals/{fitnessGoal}/toggle-status', [AdminCatalogController::class, 'toggleFitnessGoalStatus'])->name('fitness-goals.toggle-status');

        Route::get('/trainer-specializations', [AdminCatalogController::class, 'trainerSpecializations'])->name('trainer-specializations.index');
        Route::get('/trainer-specializations/create', [AdminCatalogController::class, 'createTrainerSpecialization'])->name('trainer-specializations.create');
        Route::post('/trainer-specializations', [AdminCatalogController::class, 'storeTrainerSpecialization'])->name('trainer-specializations.store');
        Route::get('/trainer-specializations/{trainerSpecialization}/edit', [AdminCatalogController::class, 'editTrainerSpecialization'])->name('trainer-specializations.edit');
        Route::put('/trainer-specializations/{trainerSpecialization}', [AdminCatalogController::class, 'updateTrainerSpecialization'])->name('trainer-specializations.update');
        Route::delete('/trainer-specializations/{trainerSpecialization}', [AdminCatalogController::class, 'destroyTrainerSpecialization'])->name('trainer-specializations.destroy');
        Route::post('/trainer-specializations/{trainerSpecialization}/toggle-status', [AdminCatalogController::class, 'toggleTrainerSpecializationStatus'])->name('trainer-specializations.toggle-status');

        Route::get('/banners', [AdminCatalogController::class, 'banners'])->name('banners.index');
        Route::get('/banners/create', [AdminCatalogController::class, 'createBanner'])->name('banners.create');
        Route::post('/banners', [AdminCatalogController::class, 'storeBanner'])->name('banners.store');
        Route::get('/banners/{banner}/edit', [AdminCatalogController::class, 'editBanner'])->name('banners.edit');
        Route::put('/banners/{banner}', [AdminCatalogController::class, 'updateBanner'])->name('banners.update');
        Route::delete('/banners/{banner}', [AdminCatalogController::class, 'destroyBanner'])->name('banners.destroy');

        Route::get('/cities', [AdminCatalogController::class, 'cities'])->name('cities.index');
        Route::post('/cities', [AdminCatalogController::class, 'storeCity'])->name('cities.store');
        Route::put('/cities/{city}', [AdminCatalogController::class, 'updateCity'])->name('cities.update');
        Route::delete('/cities/{city}', [AdminCatalogController::class, 'destroyCity'])->name('cities.destroy');

        Route::get('/listings', [AdminListingController::class, 'index'])->name('listings.index');
        Route::get('/featured-gyms', [AdminListingController::class, 'featured'])->name('featured-gyms.index');
        Route::get('/promoted-gyms', [AdminListingController::class, 'promoted'])->name('promoted-gyms.index');
        Route::get('/platform-subscription-plans', [AdminPlatformSubscriptionPlanController::class, 'index'])->name('platform-subscription-plans.index');
        Route::get('/platform-subscription-plans/create', [AdminPlatformSubscriptionPlanController::class, 'create'])->name('platform-subscription-plans.create');
        Route::post('/platform-subscription-plans', [AdminPlatformSubscriptionPlanController::class, 'store'])->name('platform-subscription-plans.store');
        Route::get('/platform-subscription-plans/{platformSubscriptionPlan}/edit', [AdminPlatformSubscriptionPlanController::class, 'edit'])->name('platform-subscription-plans.edit');
        Route::put('/platform-subscription-plans/{platformSubscriptionPlan}', [AdminPlatformSubscriptionPlanController::class, 'update'])->name('platform-subscription-plans.update');
        Route::get('/gym-platform-subscriptions', [AdminGymPlatformSubscriptionController::class, 'index'])->name('gym-platform-subscriptions.index');
        Route::get('/gym-platform-subscriptions/create', [AdminGymPlatformSubscriptionController::class, 'create'])->name('gym-platform-subscriptions.create');
        Route::post('/gym-platform-subscriptions', [AdminGymPlatformSubscriptionController::class, 'store'])->name('gym-platform-subscriptions.store');
        Route::get('/gym-platform-subscriptions/{gymPlatformSubscription}/edit', [AdminGymPlatformSubscriptionController::class, 'edit'])->name('gym-platform-subscriptions.edit');
        Route::get('/gym-platform-subscriptions/{gymPlatformSubscription}/ledger', [AdminGymPlatformSubscriptionController::class, 'ledger'])->name('gym-platform-subscriptions.ledger');
        Route::put('/gym-platform-subscriptions/{gymPlatformSubscription}', [AdminGymPlatformSubscriptionController::class, 'update'])->name('gym-platform-subscriptions.update');
        Route::post('/gym-platform-subscriptions/{gymPlatformSubscription}/renew', [AdminGymPlatformSubscriptionController::class, 'renew'])->name('gym-platform-subscriptions.renew');
        Route::post('/gym-platform-subscription-invoices/{gymPlatformSubscriptionInvoice}/mark-paid', [AdminGymPlatformSubscriptionController::class, 'markInvoicePaid'])->name('gym-platform-subscription-invoices.mark-paid');
        Route::get('/enquiries', [AdminEnquiryController::class, 'index'])->name('enquiries.index');
        Route::post('/enquiries/{enquiry}/status', [AdminEnquiryController::class, 'updateStatus'])->name('enquiries.update-status');

        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/gyms', [AdminReportController::class, 'gyms'])->name('reports.gyms');
        Route::get('/reports/users', [AdminReportController::class, 'users'])->name('reports.users');
        Route::get('/reports/payments', [AdminReportController::class, 'payments'])->name('reports.payments');
        Route::get('/reports/platform-billing', [AdminReportController::class, 'platformBilling'])->name('reports.platform-billing');
        Route::get('/reports/attendance', [AdminReportController::class, 'attendance'])->name('reports.attendance');
        Route::get('/reports/custom-fees', [AdminReportController::class, 'customFees'])->name('reports.custom-fees');
        Route::get('/reports/export/{type}', [AdminReportController::class, 'export'])->name('reports.export');
        Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/create', [AdminAnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}', [AdminAnnouncementController::class, 'show'])->name('announcements.show');
        Route::get('/notifications', [AdminAnnouncementController::class, 'notifications'])->name('notifications.index');
        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
    });

Route::prefix('gym')
    ->name('web.gym.')
    ->middleware(['auth', 'web_gym_panel'])
    ->group(function (): void {
        Route::get('/dashboard', WebGymDashboardController::class)->name('dashboard');

        Route::get('/profile', [WebGymProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [WebGymProfileController::class, 'update'])->name('profile.update');

        Route::get('/branches', [WebGymBranchController::class, 'index'])->name('branches.index');
        Route::get('/branches/create', [WebGymBranchController::class, 'create'])->name('branches.create');
        Route::post('/branches', [WebGymBranchController::class, 'store'])->name('branches.store');
        Route::get('/branches/{branch}', [WebGymBranchController::class, 'show'])->name('branches.show');
        Route::get('/branches/{branch}/edit', [WebGymBranchController::class, 'edit'])->name('branches.edit');
        Route::put('/branches/{branch}', [WebGymBranchController::class, 'update'])->name('branches.update');
        Route::delete('/branches/{branch}', [WebGymBranchController::class, 'destroy'])->name('branches.destroy');
        Route::post('/branches/{branch}/toggle-status', [WebGymBranchController::class, 'toggleStatus'])->name('branches.toggle-status');

        Route::get('/trainers', [WebGymTrainerController::class, 'index'])->name('trainers.index');
        Route::get('/trainers/create', [WebGymTrainerController::class, 'create'])->name('trainers.create');
        Route::post('/trainers', [WebGymTrainerController::class, 'store'])->name('trainers.store');
        Route::get('/trainers/{trainer}', [WebGymTrainerController::class, 'show'])->name('trainers.show');
        Route::get('/trainers/{trainer}/edit', [WebGymTrainerController::class, 'edit'])->name('trainers.edit');
        Route::put('/trainers/{trainer}', [WebGymTrainerController::class, 'update'])->name('trainers.update');
        Route::post('/trainers/{trainer}/activate', [WebGymTrainerController::class, 'activate'])->name('trainers.activate');
        Route::post('/trainers/{trainer}/deactivate', [WebGymTrainerController::class, 'deactivate'])->name('trainers.deactivate');
        Route::post('/trainers/{trainer}/assign-members', [WebGymTrainerController::class, 'assignMembers'])->name('trainers.assign-members');
        Route::post('/trainers/{trainer}/toggle-active', [WebGymTrainerController::class, 'toggleActive'])->name('trainers.toggle-active');

        Route::get('/staff', [WebGymStaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [WebGymStaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [WebGymStaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}', [WebGymStaffController::class, 'show'])->name('staff.show');
        Route::get('/staff/{staff}/edit', [WebGymStaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [WebGymStaffController::class, 'update'])->name('staff.update');
        Route::post('/staff/{staff}/activate', [WebGymStaffController::class, 'activate'])->name('staff.activate');
        Route::post('/staff/{staff}/deactivate', [WebGymStaffController::class, 'deactivate'])->name('staff.deactivate');
        Route::post('/staff/{staff}/toggle-active', [WebGymStaffController::class, 'toggleActive'])->name('staff.toggle-active');
        Route::delete('/staff/{staff}', [WebGymStaffController::class, 'destroy'])->name('staff.destroy');

        Route::get('/members', [WebGymMemberController::class, 'index'])->name('members.index');
        Route::get('/members/create', [WebGymMemberController::class, 'create'])->name('members.create');
        Route::post('/members', [WebGymMemberController::class, 'store'])->name('members.store');
        Route::post('/members/import/preview', [WebGymMemberController::class, 'previewImport'])->name('members.import.preview');
        Route::post('/members/import', [WebGymMemberController::class, 'import'])->name('members.import.store');
        Route::get('/members/{member}', [WebGymMemberController::class, 'show'])->name('members.show');
        Route::get('/members/{member}/edit', [WebGymMemberController::class, 'edit'])->name('members.edit');
        Route::put('/members/{member}', [WebGymMemberController::class, 'update'])->name('members.update');
        Route::post('/members/{member}/activate', [WebGymMemberController::class, 'activate'])->name('members.activate');
        Route::post('/members/{member}/deactivate', [WebGymMemberController::class, 'deactivate'])->name('members.deactivate');
        Route::post('/members/{member}/remove-from-gym', [WebGymMemberController::class, 'removeFromGym'])->name('members.remove-from-gym');
        Route::post('/members/{member}/assign-trainer', [WebGymMemberController::class, 'assignTrainer'])->name('members.assign-trainer');
        Route::get('/members/{member}/assign-membership', [WebGymMemberMembershipController::class, 'assignForm'])->name('members.assign-membership');
        Route::post('/members/{member}/assign-membership', [WebGymMemberMembershipController::class, 'assign'])->name('members.assign-membership.store');
        Route::get('/memberships', [WebGymMemberMembershipController::class, 'index'])->name('memberships.index');
        Route::get('/memberships/active', [WebGymMemberMembershipController::class, 'active'])->name('memberships.active');
        Route::get('/memberships/expired', [WebGymMemberMembershipController::class, 'expired'])->name('memberships.expired');
        Route::get('/memberships/expiring-soon', [WebGymMemberMembershipController::class, 'expiringSoon'])->name('memberships.expiring-soon');
        Route::get('/memberships/{membership}', [WebGymMemberMembershipController::class, 'show'])->name('memberships.show');
        Route::get('/custom-fees', [WebGymMemberMembershipController::class, 'customFeesIndex'])->name('custom-fees.index');
        Route::get('/custom-fees/audit-logs', [WebGymMemberMembershipController::class, 'customFeeAuditLogs'])->name('custom-fees.audit-logs');
        Route::post('/memberships/{membership}/renew', [WebGymMemberMembershipController::class, 'renew'])->name('memberships.renew');
        Route::post('/memberships/{membership}/freeze', [WebGymMemberMembershipController::class, 'freeze'])->name('memberships.freeze');
        Route::post('/memberships/{membership}/reactivate', [WebGymMemberMembershipController::class, 'reactivate'])->name('memberships.reactivate');
        Route::post('/memberships/{membership}/extend', [WebGymMemberMembershipController::class, 'extend'])->name('memberships.extend');
        Route::post('/memberships/{membership}/cancel', [WebGymMemberMembershipController::class, 'cancel'])->name('memberships.cancel');
        Route::get('/members/{member}/custom-fee', [WebGymMemberMembershipController::class, 'customFeeForm'])->name('members.custom-fee');
        Route::post('/members/{member}/custom-fee', [WebGymMemberMembershipController::class, 'updateMemberCustomFee'])->name('members.custom-fee.update');
        Route::post('/memberships/{memberMembership}/custom-fee', [WebGymMemberMembershipController::class, 'updateCustomFee'])->name('memberships.custom-fee.update');

        Route::get('/membership-plans', [WebGymMembershipPlanController::class, 'index'])->name('membership-plans.index');
        Route::get('/membership-plans/create', [WebGymMembershipPlanController::class, 'create'])->name('membership-plans.create');
        Route::post('/membership-plans', [WebGymMembershipPlanController::class, 'store'])->name('membership-plans.store');
        Route::get('/membership-plans/{plan}', [WebGymMembershipPlanController::class, 'show'])->name('membership-plans.show');
        Route::get('/membership-plans/{plan}/edit', [WebGymMembershipPlanController::class, 'edit'])->name('membership-plans.edit');
        Route::put('/membership-plans/{plan}', [WebGymMembershipPlanController::class, 'update'])->name('membership-plans.update');
        Route::post('/membership-plans/{plan}/activate', [WebGymMembershipPlanController::class, 'activate'])->name('membership-plans.activate');
        Route::post('/membership-plans/{plan}/deactivate', [WebGymMembershipPlanController::class, 'deactivate'])->name('membership-plans.deactivate');

        Route::get('/payments', [WebGymPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/create', [WebGymPaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [WebGymPaymentController::class, 'store'])->name('payments.store');
        Route::post('/payments/ledger-entries', [WebGymPaymentController::class, 'storeLedgerEntry'])->name('payments.ledger-entries.store');
        Route::post('/payments/ledger-entries/{ledgerEntry}/reverse', [WebGymPaymentController::class, 'reverseLedgerEntry'])->name('payments.ledger-entries.reverse');
        Route::get('/payments/{payment}', [WebGymPaymentController::class, 'show'])->name('payments.show');
        Route::get('/payments/{payment}/invoice', [WebGymPaymentController::class, 'invoice'])->name('payments.invoice');
        Route::get('/dues', [WebGymPaymentController::class, 'dues'])->name('dues.index');
        Route::get('/members/{member}/payments', [WebGymPaymentController::class, 'memberPayments'])->name('members.payments');
        Route::get('/reports/payments', [WebGymPaymentController::class, 'reports'])->name('reports.payments');
        Route::post('/payments/{memberMembership}/mark-paid', [WebGymPaymentController::class, 'markPaid'])->name('payments.mark-paid');
        Route::post('/payments/{memberMembership}/mark-unpaid', [WebGymPaymentController::class, 'markUnpaid'])->name('payments.mark-unpaid');
        Route::post('/payments/{payment}/reverse', [WebGymPaymentController::class, 'reverse'])->name('payments.reverse');

        Route::get('/attendance', [WebGymAttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/manual', [WebGymAttendanceController::class, 'manualForm'])->name('attendance.manual');
        Route::post('/attendance/manual', [WebGymAttendanceController::class, 'storeManual'])->name('attendance.manual.store');
        Route::post('/attendance/biometric-scan', [WebGymAttendanceController::class, 'biometricScan'])->name('attendance.biometric-scan');
        Route::post('/attendance/corrections', [WebGymAttendanceController::class, 'storeCorrection'])->name('attendance.corrections.store');
        Route::post('/attendance/corrections/{correction}/approve', [WebGymAttendanceController::class, 'approveCorrection'])->name('attendance.corrections.approve');
        Route::post('/attendance/corrections/{correction}/reject', [WebGymAttendanceController::class, 'rejectCorrection'])->name('attendance.corrections.reject');
        Route::get('/attendance/today', [WebGymAttendanceController::class, 'today'])->name('attendance.today');
        Route::get('/members/{member}/attendance', [WebGymAttendanceController::class, 'memberHistory'])->name('members.attendance');

        Route::get('/announcements', [WebGymAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/create', [WebGymAnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [WebGymAnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}', [WebGymAnnouncementController::class, 'show'])->name('announcements.show');
        Route::delete('/announcements/{announcement}', [WebGymAnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::get('/notifications', [WebGymAnnouncementController::class, 'notifications'])->name('notifications.index');
        Route::get('/scheduled-reminders', [WebGymReminderController::class, 'index'])->name('reminders.index');
        Route::post('/scheduled-reminders/run-due', [WebGymReminderController::class, 'runDue'])->name('reminders.run-due');

        Route::get('/leads', [WebGymTrialRequestController::class, 'index'])->name('leads.index');
        Route::get('/trial-requests', [WebGymTrialRequestController::class, 'index'])->name('trial-requests.index');
        Route::get('/trial-requests/{trial}', [WebGymTrialRequestController::class, 'show'])->name('trial-requests.show');
        Route::put('/trial-requests/{trialRequest}', [WebGymTrialRequestController::class, 'update'])->name('trial-requests.update');
        Route::post('/trial-requests/{trial}/accept', [WebGymTrialRequestController::class, 'accept'])->name('trial-requests.accept');
        Route::post('/trial-requests/{trial}/reject', [WebGymTrialRequestController::class, 'reject'])->name('trial-requests.reject');
        Route::post('/trial-requests/{trial}/complete', [WebGymTrialRequestController::class, 'complete'])->name('trial-requests.complete');
        Route::post('/trial-requests/{trial}/convert', [WebGymTrialRequestController::class, 'convert'])->name('trial-requests.convert');
        Route::post('/trial-requests/{trial}/assign-trainer', [WebGymTrialRequestController::class, 'assignTrainer'])->name('trial-requests.assign-trainer');

        Route::get('/reports', [WebGymReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/revenue', [WebGymReportController::class, 'revenue'])->name('reports.revenue');
        Route::get('/reports/dues', [WebGymReportController::class, 'dues'])->name('reports.dues');
        Route::get('/reports/memberships', [WebGymReportController::class, 'memberships'])->name('reports.memberships');
        Route::get('/reports/attendance', [WebGymReportController::class, 'attendance'])->name('reports.attendance');
        Route::get('/reports/trainers', [WebGymReportController::class, 'trainers'])->name('reports.trainers');
        Route::get('/reports/custom-fees', [WebGymReportController::class, 'customFees'])->name('reports.custom-fees');
        Route::get('/reports/leads', [WebGymReportController::class, 'leads'])->name('reports.leads');
        Route::get('/reports/export/{type}', [WebGymReportController::class, 'export'])->name('reports.export');
        Route::get('/settings', [WebGymSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [WebGymSettingController::class, 'update'])->name('settings.update');
        Route::get('/audit-logs', [WebGymAuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/public-listing-settings', [WebGymPublicListingController::class, 'edit'])->name('public-listing.edit');
        Route::put('/public-listing-settings', [WebGymPublicListingController::class, 'update'])->name('public-listing.update');
    });
