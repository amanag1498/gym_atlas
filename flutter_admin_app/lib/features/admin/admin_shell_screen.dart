import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/models/session_models.dart';
import '../../core/widgets/common_widgets.dart';
import '../auth/session_controller.dart';
import 'admin_repository.dart';
import 'notification_preferences_sheet.dart';
import 'platform_workout_books_screen.dart';

Map<String, dynamic> _recordMap(Object? value) {
  return Map<String, dynamic>.from(value as Map? ?? const {});
}

bool _isPermissionError(Object? error) {
  final message = error?.toString().toLowerCase() ?? '';
  return message.contains('403') ||
      message.contains('permission') ||
      message.contains('forbidden') ||
      message.contains('not authorized');
}

bool _hasAnyAdminPermission(AppUser appUser, List<String> permissions) {
  if (appUser.activeRole == 'platform_admin' ||
      appUser.activeRole == 'gym_owner') {
    return true;
  }
  if (appUser.activeRole == 'branch_manager') {
    return true;
  }

  return appUser.hasAnyPermission(permissions);
}

class AdminShellScreen extends StatefulWidget {
  const AdminShellScreen({super.key});

  @override
  State<AdminShellScreen> createState() => _AdminShellScreenState();
}

class _AdminShellScreenState extends State<AdminShellScreen> {
  late AdminRepository _repository;
  int _selectedIndex = 0;
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _dashboard = const {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _repository = AdminRepository(
      context.read<SessionController>().authenticatedClient,
    );
    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    final session = context.read<SessionController>();
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _dashboard = await _repository.fetchDashboard(
        session.user?.activeRole ?? '',
      );
    } catch (exception) {
      _error = exception.toString();
    }
    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _openNotificationPreferences() async {
    final role = context.read<SessionController>().user?.activeRole ?? '';
    final changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => AdminNotificationPreferencesSheet(
        title: role == 'platform_admin'
            ? 'Platform alert preferences'
            : 'Gym operations alert preferences',
        subtitle: role == 'platform_admin'
            ? 'Control which platform approvals, support, and moderation alerts reach your operations desk.'
            : 'Control renewal, dues, lead, and retention alerts for your current operating role.',
        onLoad: _repository.fetchNotificationPreferences,
        onSave: _repository.updateNotificationPreferences,
      ),
    );

    if (changed == true && mounted) {
      await _loadDashboard();
    }
  }

  List<_AdminDestination> _destinations(String role) {
    if (role == 'platform_admin') {
      return const [
        _AdminDestination('Dashboard', Icons.space_dashboard_rounded),
        _AdminDestination(
          'Gyms',
          Icons.approval_rounded,
          endpoint: '/platform-admin/gyms',
          formType: _AdminFormType.platformGym,
        ),
        _AdminDestination(
          'Users',
          Icons.groups_rounded,
          endpoint: '/platform-admin/users',
        ),
        _AdminDestination(
          'Gym Owners',
          Icons.badge_rounded,
          endpoint: '/platform-admin/gym-owners',
          formType: _AdminFormType.platformGymOwner,
        ),
        _AdminDestination(
          'Trainers',
          Icons.fitness_center_rounded,
          endpoint: '/platform-admin/trainers',
        ),
        _AdminDestination(
          'Members',
          Icons.directions_run_rounded,
          endpoint: '/platform-admin/members',
        ),
        _AdminDestination(
          'Facilities',
          Icons.spa_rounded,
          endpoint: '/platform-admin/facilities',
          formType: _AdminFormType.platformFacility,
        ),
        _AdminDestination(
          'Cities',
          Icons.location_city_rounded,
          endpoint: '/platform-admin/cities',
        ),
        _AdminDestination(
          'Fitness Goals',
          Icons.flag_circle_rounded,
          endpoint: '/platform-admin/fitness-goals',
        ),
        _AdminDestination(
          'Trainer Specializations',
          Icons.military_tech_rounded,
          endpoint: '/platform-admin/trainer-specializations',
        ),
        _AdminDestination(
          'Banners',
          Icons.view_day_rounded,
          endpoint: '/platform-admin/banners',
        ),
        _AdminDestination(
          'Exercises',
          Icons.fitness_center_rounded,
          endpoint: '/platform-admin/exercises',
        ),
        _AdminDestination(
          'Workout Books',
          Icons.menu_book_rounded,
          endpoint: '/platform-admin/workout-books',
        ),
        _AdminDestination(
          'Listings',
          Icons.view_carousel_rounded,
          endpoint: '/platform-admin/listings',
        ),
        _AdminDestination(
          'Reports',
          Icons.analytics_rounded,
          formType: _AdminFormType.platformReports,
        ),
        _AdminDestination(
          'Announcements',
          Icons.campaign_rounded,
          endpoint: '/platform-admin/announcements',
        ),
        _AdminDestination(
          'Notifications',
          Icons.notifications_active_rounded,
          endpoint: '/notifications',
        ),
        _AdminDestination('Settings', Icons.settings_rounded),
        _AdminDestination(
          'Audit Logs',
          Icons.history_rounded,
          endpoint: '/platform-admin/audit-logs',
        ),
      ];
    }

    return const [
      _AdminDestination('Dashboard', Icons.space_dashboard_rounded),
      _AdminDestination(
        'Gym Profile',
        Icons.store_mall_directory_rounded,
        formType: _AdminFormType.gymProfile,
      ),
      _AdminDestination(
        'Branches',
        Icons.location_city_rounded,
        endpoint: '/gym/branches',
        formType: _AdminFormType.branch,
      ),
      _AdminDestination(
        'Staff',
        Icons.badge_rounded,
        endpoint: '/gym/staff',
        formType: _AdminFormType.staff,
      ),
      _AdminDestination(
        'Trainers',
        Icons.fitness_center_rounded,
        endpoint: '/gym/trainers',
        formType: _AdminFormType.trainer,
      ),
      _AdminDestination(
        'Members',
        Icons.groups_rounded,
        endpoint: '/gym/members',
        formType: _AdminFormType.member,
      ),
      _AdminDestination(
        'Membership Plans',
        Icons.workspace_premium_rounded,
        endpoint: '/gym/membership-plans',
        formType: _AdminFormType.plan,
      ),
      _AdminDestination(
        'Memberships',
        Icons.receipt_long_rounded,
        endpoint: '/gym/memberships',
        formType: _AdminFormType.membershipAssign,
      ),
      _AdminDestination(
        'Custom Fees',
        Icons.tune_rounded,
        endpoint: '/gym/custom-fees',
      ),
      _AdminDestination(
        'Payments',
        Icons.payments_rounded,
        endpoint: '/gym/payments',
        formType: _AdminFormType.payment,
      ),
      _AdminDestination(
        'Dues',
        Icons.warning_amber_rounded,
        endpoint: '/gym/dues',
        formType: _AdminFormType.payment,
      ),
      _AdminDestination(
        'Attendance',
        Icons.qr_code_scanner_rounded,
        formType: _AdminFormType.attendance,
      ),
      _AdminDestination(
        'Trial Requests',
        Icons.flag_rounded,
        endpoint: '/gym/trial-requests',
      ),
      _AdminDestination(
        'Announcements',
        Icons.campaign_rounded,
        endpoint: '/gym/announcements',
        formType: _AdminFormType.announcement,
      ),
      _AdminDestination(
        'Notifications',
        Icons.notifications_active_rounded,
        endpoint: '/notifications',
      ),
      _AdminDestination(
        'Scheduled Reminders',
        Icons.alarm_rounded,
        endpoint: '/gym/scheduled-reminders',
      ),
      _AdminDestination('Reports', Icons.analytics_rounded),
      _AdminDestination('Settings', Icons.settings_rounded),
      _AdminDestination('Audit Logs', Icons.history_rounded),
      _AdminDestination(
        'Public Listing',
        Icons.travel_explore_rounded,
        formType: _AdminFormType.publicListing,
      ),
    ];
  }

  bool _isDestinationAllowed(AppUser user, _AdminDestination destination) {
    if (user.activeRole == 'platform_admin') {
      return true;
    }

    if (user.activeRole == 'gym_owner') {
      return true;
    }

    if (user.activeRole == 'branch_manager') {
      if (destination.title == 'Staff') {
        return user.hasAnyPermission(['manage_staff']);
      }
      return !const {
        'Gym Profile',
        'Public Listing',
      }.contains(destination.title);
    }

    if (user.activeRole != 'gym_staff') {
      return false;
    }

    switch (destination.title) {
      case 'Dashboard':
        return true;
      case 'Staff':
        return user.hasAnyPermission(['manage_staff']);
      case 'Trainers':
        return user.hasAnyPermission(['trainer.manage', 'manage_trainers']);
      case 'Members':
        return user.hasAnyPermission(['member.manage', 'manage_members']);
      case 'Membership Plans':
      case 'Memberships':
        return user.hasAnyPermission(['membership.manage']);
      case 'Custom Fees':
        return user.hasAnyPermission([
          'payment.view',
          'payment.manage',
          'view_billing',
          'edit_custom_fee',
        ]);
      case 'Payments':
        return user.hasAnyPermission(['view_billing']);
      case 'Attendance':
        return user.hasAnyPermission([
          'attendance.manage',
          'manage_attendance',
        ]);
      case 'Trial Requests':
        return user.hasAnyPermission([
          'trial_request.view',
          'trial_request.manage',
        ]);
      case 'Announcements':
        return user.hasAnyPermission([
          'announcement.view',
          'announcement.manage',
          'notification.manage',
          'send_announcements',
        ]);
      case 'Notifications':
      case 'Scheduled Reminders':
        return user.hasAnyPermission([
          'notification.manage',
          'announcement.view',
          'announcement.manage',
          'send_announcements',
        ]);
      case 'Reports':
      case 'Settings':
      case 'Audit Logs':
        return user.hasAnyPermission(['view_reports']);
      default:
        return false;
    }
  }

  List<_AdminDestination> _visibleDestinations(AppUser user) {
    return _destinations(
      user.activeRole,
    ).where((destination) => _isDestinationAllowed(user, destination)).toList();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionController>();
    final user = session.user;
    if (user == null) {
      return const SizedBox.shrink();
    }

    final destinations = _visibleDestinations(user);
    final safeSelectedIndex = destinations.isEmpty
        ? 0
        : _selectedIndex.clamp(0, destinations.length - 1);
    final selected = destinations.isEmpty
        ? const _AdminDestination('Dashboard', Icons.space_dashboard_rounded)
        : destinations[safeSelectedIndex];

    return AppGradientScaffold(
      child: Column(
        children: [
          _AdminFitPageHeader(
            title: selected.title,
            subtitle: user.activeRole.replaceAll('_', ' ').toUpperCase(),
            icon: selected.icon,
            onRefresh: _loadDashboard,
            onPreferences: _openNotificationPreferences,
            onRoles: session.hasMultipleRoles
                ? () => context.go('/roles')
                : null,
            onLogout: () => context.read<SessionController>().logout(),
          ),
          if (destinations.isNotEmpty)
            _AdminFitSectionNav(
              destinations: destinations,
              selectedIndex: safeSelectedIndex,
              onSelected: (value) {
                final destination = destinations[value];
                if (destination.title == 'Workout Books' &&
                    user.activeRole == 'platform_admin') {
                  context.go('/platform-admin/workout-books');
                  return;
                }
                setState(() => _selectedIndex = value);
              },
            ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
              child: destinations.isEmpty
                  ? const EmptyState(
                      title: 'No admin workspace available',
                      message:
                          'This account does not have access to any admin sections for the current role.',
                      icon: Icons.lock_outline_rounded,
                    )
                  : selected.title == 'Payments'
                  ? _PaymentsAndDuesSection(
                      key: ValueKey(selected.title),
                      appUser: user,
                      repository: _repository,
                      onOpenForm: (type, {prefill}) =>
                          _showFormSheet(type, prefill: prefill),
                      onOpenMemberDetail: _openMemberDetail,
                    )
                  : selected.title == 'Attendance'
                  ? _AttendanceWorkspaceSection(
                      key: ValueKey(selected.title),
                      appUser: user,
                      repository: _repository,
                      onOpenForm: (type, {prefill}) =>
                          _showFormSheet(type, prefill: prefill),
                      onOpenMemberDetail: _openMemberDetail,
                    )
                  : selected.title == 'Trial Requests'
                  ? _TrialRequestsWorkspaceSection(
                      key: ValueKey(selected.title),
                      appUser: user,
                      repository: _repository,
                      onOpenMemberDetail: _openMemberDetail,
                    )
                  : selected.title == 'Announcements' &&
                        user.activeRole != 'platform_admin'
                  ? _AnnouncementsWorkspaceSection(
                      key: ValueKey(selected.title),
                      appUser: user,
                      repository: _repository,
                    )
                  : selected.title == 'Workout Books' &&
                        user.activeRole == 'platform_admin'
                  ? PlatformWorkoutBooksWorkspace(
                      key: ValueKey(selected.title),
                      appUser: user,
                      repository: _repository,
                    )
                  : selected.title == 'Notifications' &&
                        user.activeRole != 'platform_admin'
                  ? _NotificationsWorkspaceSection(
                      key: ValueKey(selected.title),
                      appUser: user,
                      repository: _repository,
                    )
                  : selected.title == 'Reports' &&
                        user.activeRole != 'platform_admin'
                  ? _GymReportsWorkspaceSection(
                      key: ValueKey(selected.title),
                      appUser: user,
                      repository: _repository,
                    )
                  : selected.title == 'Settings' &&
                        user.activeRole == 'platform_admin'
                  ? _PlatformSettingsWorkspaceSection(
                      key: ValueKey(selected.title),
                      repository: _repository,
                      onOpenNotificationPreferences:
                          _openNotificationPreferences,
                      onOpenSection: _openDashboardSection,
                    )
                  : selected.title == 'Settings' &&
                        user.activeRole != 'platform_admin'
                  ? _GymSettingsWorkspaceSection(
                      key: ValueKey(selected.title),
                      appUser: user,
                      repository: _repository,
                      onOpenNotificationPreferences:
                          _openNotificationPreferences,
                      onOpenSection: _openDashboardSection,
                    )
                  : selected.title == 'Audit Logs' &&
                        user.activeRole != 'platform_admin'
                  ? _GymAuditLogsWorkspaceSection(
                      key: ValueKey(selected.title),
                      appUser: user,
                      repository: _repository,
                    )
                  : selected.endpoint == null
                  ? _buildDashboardOrForm(selected, user.activeRole)
                  : _CollectionSection(
                      key: ValueKey(selected.title),
                      appUser: user,
                      destination: selected,
                      repository: _repository,
                      onOpenForm: (type, {prefill}) => _showFormSheet(
                        type ?? selected.formType,
                        prefill: prefill,
                      ),
                      onOpenMemberDetail: _openMemberDetail,
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDashboardOrForm(_AdminDestination destination, String role) {
    if (destination.title == 'Dashboard') {
      if (_isPermissionError(_error)) {
        return const EmptyState(
          title: 'Permission denied',
          message:
              'You do not have access to this dashboard workspace for the current admin role.',
          icon: Icons.lock_outline_rounded,
        );
      }
      return AsyncStateView(
        isLoading: _loading,
        error: _error,
        onRetry: _loadDashboard,
        loadingChild: const _AdminDashboardSkeleton(),
        child: _DashboardWorkspace(
          appUser: context.read<SessionController>().user!,
          role: role,
          dashboard: _dashboard,
          onRefresh: _loadDashboard,
          onOpenOnboardingStep: _openOnboardingStep,
          onOpenSection: _openDashboardSection,
        ),
      );
    }

    return _buildFormWorkspace(destination.formType);
  }

  Widget _buildFormWorkspace(_AdminFormType? type) {
    switch (type) {
      case _AdminFormType.gymProfile:
        return _GymProfileWorkspace(
          repository: _repository,
          onEdit: (profile) => _showFormSheet(type, prefill: profile),
        );
      case _AdminFormType.attendance:
        return Column(
          children: [
            _SingleActionCard(
              title: 'Attendance QR Scanner',
              description: 'Open scanner mode for live member check-ins.',
              actionLabel: 'Launch QR Scanner',
              onTap: () => _showFormSheet(type),
            ),
            const SizedBox(height: 16),
            _SingleActionCard(
              title: 'Manual Attendance',
              description:
                  'Handle manual check-ins when QR flow is unavailable.',
              actionLabel: 'Record Manual Check-in',
              onTap: () => _showFormSheet(_AdminFormType.manualAttendance),
            ),
          ],
        );
      case _AdminFormType.publicListing:
        return _GymPublicListingWorkspace(
          repository: _repository,
          onEdit: (settings) => _showFormSheet(type, prefill: settings),
        );
      case _AdminFormType.platformFacility:
        return _SingleActionCard(
          title: 'Facilities Catalog',
          description:
              'Create and refine platform-wide facility metadata used across gyms and branches.',
          actionLabel: 'Create Facility',
          onTap: () => _showFormSheet(type),
        );
      case _AdminFormType.platformReports:
        return _PlatformReportsWorkspace(repository: _repository);
      default:
        return const SizedBox.shrink();
    }
  }

  void _openOnboardingStep(String stepKey) {
    const mapping = <String, int>{
      'gym_profile': 1,
      'first_branch': 2,
      'membership_plans': 6,
      'trainers': 4,
      'first_member': 5,
      'public_listing': 12,
      'dashboard_ready': 0,
    };
    setState(() => _selectedIndex = mapping[stepKey] ?? 0);
  }

  void _openDashboardSection(String title) {
    final user = context.read<SessionController>().user;
    if (user == null) {
      return;
    }
    final destinations = _visibleDestinations(user);
    final index = destinations.indexWhere(
      (destination) => destination.title == title,
    );
    setState(() => _selectedIndex = index < 0 ? 0 : index);
  }

  Future<void> _openMemberDetail(Map<String, dynamic> item) async {
    final memberId =
        (item['id'] as num?)?.toInt() ?? (item['member_id'] as num?)?.toInt();
    if (memberId == null) {
      return;
    }
    Map<String, dynamic> detail;
    try {
      detail = await _repository.fetchMemberDetail(memberId);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      showModalBottomSheet<void>(
        context: context,
        builder: (_) => SizedBox(
          height: 280,
          child: _isPermissionError(exception)
              ? const EmptyState(
                  title: 'Permission denied',
                  message:
                      'You do not have access to this member detail for the current role.',
                  icon: Icons.lock_outline_rounded,
                )
              : ErrorState(
                  message: exception.toString(),
                  onRetry: () {
                    Navigator.of(context).pop();
                    _openMemberDetail(item);
                  },
                ),
        ),
      );
      return;
    }
    if (!mounted) {
      return;
    }
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => DraggableScrollableSheet(
        expand: false,
        builder: (context, controller) => _MemberDetailSheet(
          appUser: context.read<SessionController>().user!,
          detail: detail,
          repository: _repository,
          onOpenForm: _showFormSheet,
        ),
      ),
    );
  }

  Future<void> _showFormSheet(
    _AdminFormType? type, {
    Map<String, dynamic>? prefill,
  }) async {
    if (type == null) {
      return;
    }
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => _AdminFormSheet(
        type: type,
        repository: _repository,
        appUser: context.read<SessionController>().user!,
        userRole: context.read<SessionController>().user?.activeRole ?? '',
        prefill: prefill ?? const {},
        parentContext: this.context,
      ),
    );
  }
}

class _AdminFitPageHeader extends StatelessWidget {
  const _AdminFitPageHeader({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onRefresh,
    required this.onPreferences,
    required this.onLogout,
    this.onRoles,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onRefresh;
  final VoidCallback onPreferences;
  final VoidCallback onLogout;
  final VoidCallback? onRoles;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 10),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(30),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF92A3FD).withValues(alpha: 0.28),
              blurRadius: 28,
              offset: const Offset(0, 16),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 58,
              height: 58,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.22),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Icon(icon, color: Colors.white, size: 28),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.white.withValues(alpha: 0.82),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            _AdminFitHeaderButton(
              icon: Icons.tune_rounded,
              onTap: onPreferences,
            ),
            const SizedBox(width: 8),
            _AdminFitHeaderButton(
              icon: Icons.refresh_rounded,
              onTap: onRefresh,
            ),
            if (onRoles != null) ...[
              const SizedBox(width: 8),
              _AdminFitHeaderButton(
                icon: Icons.swap_horiz_rounded,
                onTap: onRoles!,
              ),
            ],
            const SizedBox(width: 8),
            _AdminFitHeaderButton(icon: Icons.logout_rounded, onTap: onLogout),
          ],
        ),
      ),
    );
  }
}

class _AdminFitHeaderButton extends StatelessWidget {
  const _AdminFitHeaderButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.18),
          borderRadius: BorderRadius.circular(18),
        ),
        child: Icon(icon, color: Colors.white, size: 20),
      ),
    );
  }
}

class _AdminFitSectionNav extends StatelessWidget {
  const _AdminFitSectionNav({
    required this.destinations,
    required this.selectedIndex,
    required this.onSelected,
  });

  final List<_AdminDestination> destinations;
  final int selectedIndex;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 58,
      child: ListView.separated(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        scrollDirection: Axis.horizontal,
        itemCount: destinations.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final destination = destinations[index];
          final selected = index == selectedIndex;
          return InkWell(
            onTap: () => onSelected(index),
            borderRadius: BorderRadius.circular(22),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                gradient: selected
                    ? const LinearGradient(
                        colors: [Color(0xFFEEA4CE), Color(0xFFC58BF2)],
                      )
                    : null,
                color: selected ? null : AppColors.surface,
                borderRadius: BorderRadius.circular(22),
                border: Border.all(
                  color: selected ? Colors.transparent : AppColors.strokeStrong,
                ),
                boxShadow: [
                  BoxShadow(
                    color: selected
                        ? const Color(0xFFC58BF2).withValues(alpha: 0.22)
                        : AppColors.shadow,
                    blurRadius: selected ? 18 : 12,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    destination.icon,
                    size: 18,
                    color: selected ? Colors.white : AppColors.primary,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    destination.title,
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: selected ? Colors.white : AppColors.textPrimary,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _CollectionSection extends StatefulWidget {
  const _CollectionSection({
    super.key,
    required this.appUser,
    required this.destination,
    required this.repository,
    required this.onOpenForm,
    required this.onOpenMemberDetail,
  });

  final AppUser appUser;
  final _AdminDestination destination;
  final AdminRepository repository;
  final Future<void> Function(
    _AdminFormType? type, {
    Map<String, dynamic>? prefill,
  })
  onOpenForm;
  final Future<void> Function(Map<String, dynamic>) onOpenMemberDetail;

  @override
  State<_CollectionSection> createState() => __CollectionSectionState();
}

class __CollectionSectionState extends State<_CollectionSection> {
  late _CollectionState _state;
  final TextEditingController _searchController = TextEditingController();
  String? _quickFilter;
  bool _memberFilterLoading = false;
  List<Map<String, dynamic>> _memberBranches = const [];
  List<Map<String, dynamic>> _memberPlans = const [];
  List<Map<String, dynamic>> _memberTrainers = const [];
  int? _selectedMemberBranchId;
  int? _selectedMemberPlanId;
  int? _selectedMemberTrainerId;

  @override
  void initState() {
    super.initState();
    _state = _CollectionState();
    if (widget.destination.title == 'Members') {
      _loadMemberFilterOptions();
    }
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadMemberFilterOptions() async {
    setState(() => _memberFilterLoading = true);
    try {
      final results = await Future.wait([
        widget.repository.fetchCollection('/gym/branches', perPage: 100),
        widget.repository.fetchCollection(
          '/gym/membership-plans',
          perPage: 100,
        ),
        widget.repository.fetchCollection('/gym/trainers', perPage: 100),
      ]);
      if (!mounted) {
        return;
      }
      setState(() {
        _memberBranches = results[0].items;
        _memberPlans = results[1].items;
        _memberTrainers = results[2].items;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }
    } finally {
      if (mounted) {
        setState(() => _memberFilterLoading = false);
      }
    }
  }

  Future<void> _load({bool reset = false}) async {
    setState(() {
      _state.loading = true;
      _state.error = null;
      if (reset) {
        _state.page = 1;
        _state.items = [];
      }
    });

    try {
      final response = await widget.repository.fetchCollection(
        widget.destination.endpoint!,
        page: _state.page,
        queryParameters: _queryParameters(),
      );
      setState(() {
        if (_state.page == 1) {
          _state.items = response.items;
        } else {
          _state.items.addAll(response.items);
        }
        _state.page = response.currentPage;
        _state.lastPage = response.lastPage;
      });
    } catch (exception) {
      setState(() => _state.error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _state.loading = false);
      }
    }
  }

  Future<void> _showAssignTrainerSheet(Map<String, dynamic> item) async {
    final memberId = (item['id'] as num?)?.toInt();
    if (memberId == null) {
      return;
    }

    final memberProfile = _recordMap(item['member_profile']);
    final branchId = (memberProfile['branch_id'] as num?)?.toInt();
    final currentTrainerId = (memberProfile['assigned_trainer_user_id'] as num?)
        ?.toInt();
    List<Map<String, dynamic>> trainers = const [];
    String? loadError;
    int? selectedTrainerId = currentTrainerId;
    var busy = false;

    try {
      final response = await widget.repository.fetchCollection(
        '/gym/trainers',
        perPage: 100,
        queryParameters: {
          if (branchId != null) 'branch_id': branchId,
          'status': 'active',
        },
      );
      trainers = response.items;
    } catch (exception) {
      loadError = exception.toString();
    }

    if (!mounted) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => Padding(
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Assign trainer',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              if (loadError != null)
                ErrorState(
                  message: loadError,
                  onRetry: () {
                    Navigator.of(context).pop();
                    _showAssignTrainerSheet(item);
                  },
                )
              else if (trainers.isEmpty)
                const EmptyState(
                  title: 'No trainers available',
                  message:
                      'No active trainers are available in this member scope.',
                  icon: Icons.fitness_center_outlined,
                )
              else
                DropdownButtonFormField<int?>(
                  initialValue: selectedTrainerId,
                  decoration: const InputDecoration(
                    labelText: 'Assigned trainer',
                  ),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('Unassigned'),
                    ),
                    ...trainers.map(
                      (trainer) => DropdownMenuItem<int?>(
                        value: (trainer['id'] as num?)?.toInt(),
                        child: Text(trainer['name']?.toString() ?? 'Trainer'),
                      ),
                    ),
                  ],
                  onChanged: (value) =>
                      setModalState(() => selectedTrainerId = value),
                ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: busy
                          ? null
                          : () => Navigator.of(context).pop(),
                      child: const Text('Cancel'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: GradientButton(
                      label: 'Assign trainer',
                      loading: busy,
                      onPressed: () async {
                        final navigator = Navigator.of(context);
                        final messenger = ScaffoldMessenger.of(context);
                        setModalState(() => busy = true);
                        try {
                          await widget.repository.assignMemberTrainer(
                            memberId,
                            assignedTrainerUserId: selectedTrainerId,
                          );
                          if (!mounted) {
                            return;
                          }
                          navigator.pop();
                          await _load(reset: true);
                          messenger.showSnackBar(
                            const SnackBar(
                              content: Text('Trainer assignment updated.'),
                            ),
                          );
                        } catch (exception) {
                          if (mounted) {
                            messenger.showSnackBar(
                              SnackBar(content: Text(exception.toString())),
                            );
                            setModalState(() => busy = false);
                          }
                        }
                      },
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMemberFilters(BuildContext context) {
    final disabled = _memberFilterLoading;

    return Wrap(
      spacing: 12,
      runSpacing: 12,
      children: [
        SizedBox(
          width: 220,
          child: DropdownButtonFormField<int?>(
            initialValue: _selectedMemberBranchId,
            decoration: const InputDecoration(labelText: 'Branch'),
            items: [
              const DropdownMenuItem<int?>(
                value: null,
                child: Text('All branches'),
              ),
              ..._memberBranches.map(
                (branch) => DropdownMenuItem<int?>(
                  value: (branch['id'] as num?)?.toInt(),
                  child: Text(branch['name']?.toString() ?? 'Branch'),
                ),
              ),
            ],
            onChanged: disabled
                ? null
                : (value) {
                    setState(() => _selectedMemberBranchId = value);
                    _load(reset: true);
                  },
          ),
        ),
        SizedBox(
          width: 220,
          child: DropdownButtonFormField<int?>(
            initialValue: _selectedMemberTrainerId,
            decoration: const InputDecoration(labelText: 'Trainer'),
            items: [
              const DropdownMenuItem<int?>(
                value: null,
                child: Text('All trainers'),
              ),
              ..._memberTrainers.map(
                (trainer) => DropdownMenuItem<int?>(
                  value: (trainer['id'] as num?)?.toInt(),
                  child: Text(trainer['name']?.toString() ?? 'Trainer'),
                ),
              ),
            ],
            onChanged: disabled
                ? null
                : (value) {
                    setState(() => _selectedMemberTrainerId = value);
                    _load(reset: true);
                  },
          ),
        ),
        SizedBox(
          width: 220,
          child: DropdownButtonFormField<int?>(
            initialValue: _selectedMemberPlanId,
            decoration: const InputDecoration(labelText: 'Plan'),
            items: [
              const DropdownMenuItem<int?>(
                value: null,
                child: Text('All plans'),
              ),
              ..._memberPlans.map(
                (plan) => DropdownMenuItem<int?>(
                  value: (plan['id'] as num?)?.toInt(),
                  child: Text(plan['name']?.toString() ?? 'Plan'),
                ),
              ),
            ],
            onChanged: disabled
                ? null
                : (value) {
                    setState(() => _selectedMemberPlanId = value);
                    _load(reset: true);
                  },
          ),
        ),
      ],
    );
  }

  Future<void> _showAssignMemberToTrainerSheet(
    Map<String, dynamic> item,
  ) async {
    final trainerId = (item['id'] as num?)?.toInt();
    if (trainerId == null) {
      return;
    }

    List<Map<String, dynamic>> members = const [];
    final selectedMemberIds = <int>{};
    var busy = false;
    String? loadError;

    try {
      final trainer = await widget.repository.fetchTrainerDetail(trainerId);
      final assignedMembers =
          (trainer['assignedMembers'] as List<dynamic>? ??
                  trainer['assigned_members'] as List<dynamic>? ??
                  const [])
              .map((entry) => _recordMap(entry))
              .toList();
      selectedMemberIds.addAll(
        assignedMembers
            .map((member) => (member['id'] as num?)?.toInt())
            .whereType<int>(),
      );
      final response = await widget.repository.fetchCollection(
        '/gym/members',
        perPage: 100,
      );
      members = response.items;
    } catch (exception) {
      loadError = exception.toString();
    }

    if (!mounted) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => Padding(
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Assign members',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              if (loadError != null)
                ErrorState(
                  message: loadError,
                  onRetry: () {
                    Navigator.of(context).pop();
                    _showAssignMemberToTrainerSheet(item);
                  },
                )
              else if (members.isEmpty)
                const EmptyState(
                  title: 'No members available',
                  message:
                      'There are no visible members in the current scope to assign.',
                  icon: Icons.groups_outlined,
                )
              else
                SizedBox(
                  height: 280,
                  child: SingleChildScrollView(
                    child: Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: members.map((member) {
                        final memberId = (member['id'] as num?)?.toInt();
                        if (memberId == null) {
                          return const SizedBox.shrink();
                        }
                        final selected = selectedMemberIds.contains(memberId);
                        final memberProfile = _recordMap(
                          member['member_profile'],
                        );
                        return FilterChip(
                          selected: selected,
                          label: Text(
                            '${member['name'] ?? 'Member'}'
                            '${memberProfile['fitness_goal'] != null ? ' • ${memberProfile['fitness_goal']}' : ''}',
                          ),
                          onSelected: (_) {
                            setModalState(() {
                              selected
                                  ? selectedMemberIds.remove(memberId)
                                  : selectedMemberIds.add(memberId);
                            });
                          },
                        );
                      }).toList(),
                    ),
                  ),
                ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: busy
                          ? null
                          : () => Navigator.of(context).pop(),
                      child: const Text('Cancel'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: GradientButton(
                      label: 'Assign members',
                      loading: busy,
                      onPressed: () async {
                        final navigator = Navigator.of(context);
                        final messenger = ScaffoldMessenger.of(context);
                        if (selectedMemberIds.isEmpty) {
                          messenger.showSnackBar(
                            const SnackBar(
                              content: Text('Select at least one member.'),
                            ),
                          );
                          return;
                        }

                        setModalState(() => busy = true);
                        try {
                          await widget.repository.assignTrainerMembers(
                            trainerId,
                            selectedMemberIds.toList(),
                          );
                          if (!mounted) {
                            return;
                          }
                          navigator.pop();
                          await _load(reset: true);
                          messenger.showSnackBar(
                            const SnackBar(
                              content: Text('Members assigned to trainer.'),
                            ),
                          );
                        } catch (exception) {
                          if (mounted) {
                            messenger.showSnackBar(
                              SnackBar(content: Text(exception.toString())),
                            );
                            setModalState(() => busy = false);
                          }
                        }
                      },
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showTrainerPerformanceSheet(Map<String, dynamic> item) async {
    final trainerId = (item['id'] as num?)?.toInt();
    if (trainerId == null) {
      return;
    }
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => FutureBuilder<Map<String, dynamic>>(
        future: widget.repository.fetchTrainerDetail(trainerId),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 360,
              child: LoadingState(label: 'Loading trainer detail...'),
            );
          }
          if (snapshot.hasError) {
            return SizedBox(
              height: 360,
              child: ErrorState(
                message: snapshot.error.toString(),
                onRetry: () {
                  Navigator.of(context).pop();
                  _showTrainerPerformanceSheet(item);
                },
              ),
            );
          }

          return _GymTrainerDetailSheet(
            trainer: snapshot.data ?? item,
            onEdit: () async {
              Navigator.of(context).pop();
              await widget.onOpenForm(
                _AdminFormType.trainer,
                prefill: snapshot.data ?? item,
              );
            },
            onAssignMembers: () {
              Navigator.of(context).pop();
              _showAssignMemberToTrainerSheet(item);
            },
            onActivateOrDeactivate: () {
              Navigator.of(context).pop();
              _deactivateTrainer(snapshot.data ?? item);
            },
          );
        },
      ),
    );
  }

  Future<void> _deactivateTrainer(Map<String, dynamic> item) async {
    final trainerId = (item['id'] as num?)?.toInt();
    if (trainerId == null) {
      return;
    }

    final profile = _recordMap(item['managed_trainer_profile']);
    final isActive = item['is_active'] == true || profile['is_active'] == true;
    final actionLabel = isActive ? 'Deactivate' : 'Activate';

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: '$actionLabel Trainer',
        message: '$actionLabel ${item['name'] ?? 'this Trainer'}?',
        confirmLabel: actionLabel,
      ),
    );

    if (confirmed != true) {
      return;
    }

    if (!mounted) {
      return;
    }

    try {
      final messenger = ScaffoldMessenger.of(context);
      if (isActive) {
        await widget.repository.deactivateTrainer(trainerId);
      } else {
        await widget.repository.activateTrainer(trainerId);
      }
      if (!mounted) {
        return;
      }
      await _load(reset: true);
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            '${item['name'] ?? 'Trainer'} ${isActive ? 'deactivated' : 'activated'} successfully.',
          ),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _toggleMembershipPlanStatus(Map<String, dynamic> item) async {
    final planId = (item['id'] as num?)?.toInt();
    if (planId == null) {
      return;
    }
    final isActive = item['status']?.toString() == 'active';
    final actionLabel = isActive ? 'Deactivate' : 'Activate';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: '$actionLabel Plan',
        message: '$actionLabel ${item['name'] ?? 'this membership plan'}?',
        confirmLabel: actionLabel,
      ),
    );
    if (confirmed != true) {
      return;
    }
    if (!mounted) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    try {
      if (isActive) {
        await widget.repository.deactivateMembershipPlan(planId);
      } else {
        await widget.repository.activateMembershipPlan(planId);
      }
      if (!mounted) {
        return;
      }
      await _load(reset: true);
      messenger.showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'Plan'} updated successfully.'),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _showMembershipPlanDetail(Map<String, dynamic> item) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: ListView(
            shrinkWrap: true,
            children: [
              Text(
                item['name']?.toString() ?? 'Membership plan',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 16),
              PremiumCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _InfoRow(
                      label: 'Duration',
                      value: '${item['duration_days'] ?? '--'} days',
                    ),
                    _InfoRow(
                      label: 'Plan price',
                      value: _formatCurrency(item['plan_price']),
                    ),
                    _InfoRow(
                      label: 'Joining fee',
                      value: _formatCurrency(item['joining_fee']),
                    ),
                    _InfoRow(
                      label: 'PT included',
                      value: item['pt_included'] == true ? 'Yes' : 'No',
                    ),
                    _InfoRow(
                      label: 'Status',
                      value: item['status']?.toString() ?? '--',
                    ),
                    _InfoRow(
                      label: 'Members on plan',
                      value: '${item['member_memberships_count'] ?? 0}',
                    ),
                    _InfoRow(
                      label: 'Description',
                      value: item['description']?.toString().isNotEmpty == true
                          ? item['description'].toString()
                          : '--',
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  QuickActionButton(
                    label: 'Edit',
                    icon: Icons.edit_rounded,
                    onPressed: () async {
                      Navigator.of(context).pop();
                      await widget.onOpenForm(
                        _AdminFormType.plan,
                        prefill: item,
                      );
                    },
                  ),
                  QuickActionButton(
                    label: item['status']?.toString() == 'active'
                        ? 'Deactivate'
                        : 'Activate',
                    icon: Icons.power_settings_new_rounded,
                    onPressed: () {
                      Navigator.of(context).pop();
                      _toggleMembershipPlanStatus(item);
                    },
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showMembershipDetail(Map<String, dynamic> item) async {
    final membershipId = (item['id'] as num?)?.toInt();
    if (membershipId == null) {
      return;
    }
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => FutureBuilder<Map<String, dynamic>>(
        future: widget.repository.fetchMembershipDetail(membershipId),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 360,
              child: LoadingState(label: 'Loading membership detail...'),
            );
          }
          if (snapshot.hasError) {
            return SizedBox(
              height: 360,
              child: ErrorState(
                message: snapshot.error.toString(),
                onRetry: () {
                  Navigator.of(context).pop();
                  _showMembershipDetail(item);
                },
              ),
            );
          }
          final payload = Map<String, dynamic>.from(
            (snapshot.data?['member_membership'] as Map?) ??
                snapshot.data ??
                item,
          );
          return _GymMembershipDetailSheet(
            membership: payload,
            onRenew: () {
              Navigator.of(context).pop();
              _showRenewMembershipSheet(payload);
            },
            onFreeze: () {
              Navigator.of(context).pop();
              _runMembershipSimpleAction(payload, 'freeze');
            },
            onExtend: () {
              Navigator.of(context).pop();
              _showExtendMembershipSheet(payload);
            },
            onCancel: () {
              Navigator.of(context).pop();
              _runMembershipSimpleAction(payload, 'cancel');
            },
          );
        },
      ),
    );
  }

  Future<void> _runMembershipSimpleAction(
    Map<String, dynamic> membership,
    String action,
  ) async {
    final membershipId = (membership['id'] as num?)?.toInt();
    if (membershipId == null) {
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: '${_dashboardTitleCase(action)} Membership',
        message: '${_dashboardTitleCase(action)} this membership now?',
        confirmLabel: _dashboardTitleCase(action),
      ),
    );
    if (confirmed != true) {
      return;
    }
    if (!mounted) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    try {
      switch (action) {
        case 'freeze':
          await widget.repository.freezeMembership(membershipId);
          break;
        case 'cancel':
          await widget.repository.cancelMembership(membershipId);
          break;
      }
      if (!mounted) {
        return;
      }
      await _load(reset: true);
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            'Membership ${_dashboardTitleCase(action).toLowerCase()}d successfully.',
          ),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _showRenewMembershipSheet(
    Map<String, dynamic> membership,
  ) async {
    final membershipId = (membership['id'] as num?)?.toInt();
    if (membershipId == null) {
      return;
    }
    final startDate = TextEditingController(
      text: DateFormat('yyyy-MM-dd').format(DateTime.now()),
    );
    final dueDate = TextEditingController(
      text: membership['due_date']?.toString() ?? '',
    );
    final amountPaid = TextEditingController(text: '0');
    final notes = TextEditingController();
    var busy = false;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => Padding(
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Renew membership',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: startDate,
                decoration: const InputDecoration(labelText: 'Start date'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: dueDate,
                decoration: const InputDecoration(labelText: 'Due date'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: amountPaid,
                decoration: const InputDecoration(labelText: 'Amount paid'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: notes,
                decoration: const InputDecoration(labelText: 'Notes'),
              ),
              const SizedBox(height: 16),
              GradientButton(
                label: 'Renew',
                loading: busy,
                onPressed: () async {
                  final navigator = Navigator.of(context);
                  final messenger = ScaffoldMessenger.of(context);
                  setModalState(() => busy = true);
                  try {
                    await widget.repository.renewMembership(membershipId, {
                      'start_date': startDate.text.trim(),
                      'due_date': dueDate.text.trim().isEmpty
                          ? null
                          : dueDate.text.trim(),
                      'amount_paid':
                          double.tryParse(amountPaid.text.trim()) ?? 0,
                      'notes': notes.text.trim(),
                    });
                    if (!mounted) {
                      return;
                    }
                    navigator.pop();
                    await _load(reset: true);
                  } catch (exception) {
                    if (mounted) {
                      messenger.showSnackBar(
                        SnackBar(content: Text(exception.toString())),
                      );
                      setModalState(() => busy = false);
                    }
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showExtendMembershipSheet(
    Map<String, dynamic> membership,
  ) async {
    final membershipId = (membership['id'] as num?)?.toInt();
    if (membershipId == null) {
      return;
    }
    final extraDays = TextEditingController(text: '7');
    final dueDate = TextEditingController(
      text: membership['due_date']?.toString() ?? '',
    );
    final notes = TextEditingController();
    var busy = false;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => Padding(
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Extend membership',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: extraDays,
                decoration: const InputDecoration(labelText: 'Extra days'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: dueDate,
                decoration: const InputDecoration(labelText: 'Due date'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: notes,
                decoration: const InputDecoration(labelText: 'Notes'),
              ),
              const SizedBox(height: 16),
              GradientButton(
                label: 'Extend',
                loading: busy,
                onPressed: () async {
                  final navigator = Navigator.of(context);
                  final messenger = ScaffoldMessenger.of(context);
                  setModalState(() => busy = true);
                  try {
                    await widget.repository.extendMembership(membershipId, {
                      'extra_days': int.tryParse(extraDays.text.trim()) ?? 7,
                      'due_date': dueDate.text.trim().isEmpty
                          ? null
                          : dueDate.text.trim(),
                      'notes': notes.text.trim(),
                    });
                    if (!mounted) {
                      return;
                    }
                    navigator.pop();
                    await _load(reset: true);
                  } catch (exception) {
                    if (mounted) {
                      messenger.showSnackBar(
                        SnackBar(content: Text(exception.toString())),
                      );
                      setModalState(() => busy = false);
                    }
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showPlatformGymDetail(Map<String, dynamic> item) async {
    final gymId = (item['id'] as num?)?.toInt();
    if (gymId == null) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => FutureBuilder<Map<String, dynamic>>(
        future: widget.repository.fetchPlatformGymDetail(gymId),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 360,
              child: LoadingState(label: 'Loading gym detail...'),
            );
          }
          if (snapshot.hasError) {
            return SizedBox(
              height: 360,
              child: ErrorState(
                message: snapshot.error.toString(),
                onRetry: () {
                  Navigator.of(context).pop();
                  _showPlatformGymDetail(item);
                },
              ),
            );
          }

          final gym = snapshot.data ?? const <String, dynamic>{};
          return _PlatformGymDetailSheet(
            gym: gym,
            onApprove: () {
              Navigator.of(context).pop();
              _updatePlatformGym(gym, 'approve');
            },
            onReject: () {
              Navigator.of(context).pop();
              _updatePlatformGym(gym, 'reject');
            },
            onActivateOrDeactivate: () {
              Navigator.of(context).pop();
              _updatePlatformGym(
                gym,
                gym['is_active'] == true ? 'deactivate' : 'activate',
              );
            },
            onVerify: () {
              Navigator.of(context).pop();
              _updatePlatformGym(gym, 'verify');
            },
            onFeature: () {
              Navigator.of(context).pop();
              _updatePlatformGym(gym, 'feature');
            },
            onPromote: () {
              Navigator.of(context).pop();
              _updatePlatformGym(gym, 'promote');
            },
            onEdit: () async {
              Navigator.of(context).pop();
              await widget.onOpenForm(_AdminFormType.platformGym, prefill: gym);
            },
          );
        },
      ),
    );
  }

  Future<void> _updatePlatformGym(
    Map<String, dynamic> item,
    String action,
  ) async {
    final gymId = (item['id'] as num?)?.toInt();
    if (gymId == null) {
      return;
    }

    final descriptor = switch (action) {
      'approve' => (
        title: 'Approve Gym',
        message: 'Approve ${item['name'] ?? 'this gym'} for the platform?',
        confirmLabel: 'Approve',
      ),
      'reject' => (
        title: 'Reject Gym',
        message:
            'Reject ${item['name'] ?? 'this gym'} from the platform approvals queue?',
        confirmLabel: 'Reject',
      ),
      'activate' => (
        title: 'Activate Gym',
        message: 'Activate ${item['name'] ?? 'this gym'}?',
        confirmLabel: 'Activate',
      ),
      'deactivate' => (
        title: 'Deactivate Gym',
        message: 'Deactivate ${item['name'] ?? 'this gym'}?',
        confirmLabel: 'Deactivate',
      ),
      'verify' => (
        title: item['is_verified'] == true
            ? 'Remove Verification'
            : 'Verify Gym',
        message: item['is_verified'] == true
            ? 'Remove the verified status from ${item['name'] ?? 'this gym'}?'
            : 'Verify ${item['name'] ?? 'this gym'}?',
        confirmLabel: item['is_verified'] == true ? 'Remove' : 'Verify',
      ),
      'feature' => (
        title: item['is_featured'] == true ? 'Unfeature Gym' : 'Feature Gym',
        message: item['is_featured'] == true
            ? 'Remove featured visibility from ${item['name'] ?? 'this gym'}?'
            : 'Feature ${item['name'] ?? 'this gym'} on the platform?',
        confirmLabel: item['is_featured'] == true ? 'Unfeature' : 'Feature',
      ),
      'promote' => (
        title: item['is_promoted'] == true ? 'Unpromote Gym' : 'Promote Gym',
        message: item['is_promoted'] == true
            ? 'Remove promoted visibility from ${item['name'] ?? 'this gym'}?'
            : 'Promote ${item['name'] ?? 'this gym'} on the platform?',
        confirmLabel: item['is_promoted'] == true ? 'Unpromote' : 'Promote',
      ),
      _ => (
        title: 'Update Gym',
        message: 'Apply this update to ${item['name'] ?? 'this gym'}?',
        confirmLabel: 'Confirm',
      ),
    };

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: descriptor.title,
        message: descriptor.message,
        confirmLabel: descriptor.confirmLabel,
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      switch (action) {
        case 'approve':
          await widget.repository.approvePlatformGym(gymId);
          break;
        case 'reject':
          await widget.repository.rejectPlatformGym(gymId);
          break;
        case 'activate':
          await widget.repository.activatePlatformGym(gymId);
          break;
        case 'deactivate':
          await widget.repository.deactivatePlatformGym(gymId);
          break;
        case 'verify':
          await widget.repository.verifyPlatformGym(gymId);
          break;
        case 'feature':
          await widget.repository.featurePlatformGym(gymId);
          break;
        case 'promote':
          await widget.repository.promotePlatformGym(gymId);
          break;
      }

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'Gym'} updated successfully.'),
        ),
      );
      await _load(reset: true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _showPlatformUserDetail(Map<String, dynamic> item) async {
    final userId = (item['id'] as num?)?.toInt();
    if (userId == null) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => FutureBuilder<Map<String, dynamic>>(
        future: widget.repository.fetchPlatformUserDetail(userId),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 360,
              child: LoadingState(label: 'Loading user detail...'),
            );
          }
          if (snapshot.hasError) {
            return SizedBox(
              height: 360,
              child: ErrorState(
                message: snapshot.error.toString(),
                onRetry: () {
                  Navigator.of(context).pop();
                  _showPlatformUserDetail(item);
                },
              ),
            );
          }

          return _PlatformUserDetailSheet(
            user: snapshot.data ?? item,
            onActivateOrDeactivate: () {
              Navigator.of(context).pop();
              _updatePlatformUser(item);
            },
          );
        },
      ),
    );
  }

  Future<void> _showPlatformGymOwnerDetail(Map<String, dynamic> item) async {
    final userId = (item['id'] as num?)?.toInt();
    if (userId == null) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => FutureBuilder<Map<String, dynamic>>(
        future: widget.repository.fetchPlatformGymOwnerDetail(userId),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 360,
              child: LoadingState(label: 'Loading gym owner detail...'),
            );
          }
          if (snapshot.hasError) {
            return SizedBox(
              height: 360,
              child: ErrorState(
                message: snapshot.error.toString(),
                onRetry: () {
                  Navigator.of(context).pop();
                  _showPlatformGymOwnerDetail(item);
                },
              ),
            );
          }

          return _PlatformGymOwnerDetailSheet(
            owner: snapshot.data ?? item,
            onActivateOrDeactivate: () {
              Navigator.of(context).pop();
              _updatePlatformGymOwner(item);
            },
          );
        },
      ),
    );
  }

  Future<void> _updatePlatformUser(Map<String, dynamic> item) async {
    final userId = (item['id'] as num?)?.toInt();
    if (userId == null) {
      return;
    }

    final activate = item['is_active'] != true;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: activate ? 'Activate User' : 'Deactivate User',
        message:
            '${activate ? 'Activate' : 'Deactivate'} ${item['name'] ?? 'this user'}?',
        confirmLabel: activate ? 'Activate' : 'Deactivate',
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      if (activate) {
        await widget.repository.activatePlatformUser(userId);
      } else {
        await widget.repository.deactivatePlatformUser(userId);
      }
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'User'} updated successfully.'),
        ),
      );
      await _load(reset: true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _updatePlatformGymOwner(Map<String, dynamic> item) async {
    final userId = (item['id'] as num?)?.toInt();
    if (userId == null) {
      return;
    }

    final activate = item['is_active'] != true;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: activate ? 'Activate Gym Owner' : 'Deactivate Gym Owner',
        message:
            '${activate ? 'Activate' : 'Deactivate'} ${item['name'] ?? 'this gym owner'}?',
        confirmLabel: activate ? 'Activate' : 'Deactivate',
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      if (activate) {
        await widget.repository.activatePlatformGymOwner(userId);
      } else {
        await widget.repository.deactivatePlatformGymOwner(userId);
      }
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'Gym owner'} updated successfully.'),
        ),
      );
      await _load(reset: true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _showPlatformFacilityDetail(Map<String, dynamic> item) async {
    final facilityId = (item['id'] as num?)?.toInt();
    if (facilityId == null) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => FutureBuilder<Map<String, dynamic>>(
        future: widget.repository.fetchPlatformFacilityDetail(facilityId),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 320,
              child: LoadingState(label: 'Loading facility detail...'),
            );
          }
          if (snapshot.hasError) {
            return SizedBox(
              height: 320,
              child: ErrorState(
                message: snapshot.error.toString(),
                onRetry: () {
                  Navigator.of(context).pop();
                  _showPlatformFacilityDetail(item);
                },
              ),
            );
          }

          return _PlatformFacilityDetailSheet(
            facility: snapshot.data ?? item,
            onEdit: () async {
              Navigator.of(context).pop();
              await widget.onOpenForm(
                _AdminFormType.platformFacility,
                prefill: snapshot.data ?? item,
              );
            },
            onToggleStatus: () {
              Navigator.of(context).pop();
              _togglePlatformFacilityStatus(item);
            },
            onDelete: () {
              Navigator.of(context).pop();
              _deletePlatformFacility(item);
            },
          );
        },
      ),
    );
  }

  Future<void> _togglePlatformFacilityStatus(Map<String, dynamic> item) async {
    final facilityId = (item['id'] as num?)?.toInt();
    if (facilityId == null) {
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: item['is_active'] == true
            ? 'Deactivate Facility'
            : 'Activate Facility',
        message:
            '${item['is_active'] == true ? 'Deactivate' : 'Activate'} ${item['name'] ?? 'this facility'}?',
        confirmLabel: item['is_active'] == true ? 'Deactivate' : 'Activate',
      ),
    );
    if (confirmed != true) {
      return;
    }

    try {
      await widget.repository.togglePlatformFacilityStatus(facilityId);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'Facility'} updated successfully.'),
        ),
      );
      await _load(reset: true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _deletePlatformFacility(Map<String, dynamic> item) async {
    final facilityId = (item['id'] as num?)?.toInt();
    if (facilityId == null) {
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: 'Delete Facility',
        message:
            'Delete ${item['name'] ?? 'this facility'}? This is allowed only when the backend confirms it is unused.',
        confirmLabel: 'Delete',
      ),
    );
    if (confirmed != true) {
      return;
    }

    try {
      await widget.repository.deletePlatformFacility(facilityId);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'Facility'} deleted successfully.'),
        ),
      );
      await _load(reset: true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _showBranchDetail(Map<String, dynamic> item) async {
    final branchId = (item['id'] as num?)?.toInt();
    if (branchId == null) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => FutureBuilder<Map<String, dynamic>>(
        future: widget.repository.fetchBranchDetail(branchId),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 320,
              child: LoadingState(label: 'Loading branch detail...'),
            );
          }
          if (snapshot.hasError) {
            return SizedBox(
              height: 320,
              child: ErrorState(
                message: snapshot.error.toString(),
                onRetry: () {
                  Navigator.of(context).pop();
                  _showBranchDetail(item);
                },
              ),
            );
          }

          final branch = snapshot.data ?? item;
          return _GymBranchDetailSheet(
            branch: branch,
            onEdit: () async {
              Navigator.of(context).pop();
              await widget.onOpenForm(_AdminFormType.branch, prefill: branch);
            },
            onToggleStatus: () {
              Navigator.of(context).pop();
              _toggleBranchStatus(branch);
            },
            onDelete: () {
              Navigator.of(context).pop();
              _deleteBranch(branch);
            },
          );
        },
      ),
    );
  }

  Future<void> _toggleBranchStatus(Map<String, dynamic> item) async {
    final branchId = (item['id'] as num?)?.toInt();
    if (branchId == null) {
      return;
    }

    final isActive =
        item['is_active'] == true || item['status']?.toString() == 'active';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: isActive ? 'Deactivate Branch' : 'Activate Branch',
        message:
            '${isActive ? 'Deactivate' : 'Activate'} ${item['name'] ?? 'this branch'}?',
        confirmLabel: isActive ? 'Deactivate' : 'Activate',
      ),
    );
    if (confirmed != true) {
      return;
    }

    try {
      await widget.repository.toggleBranchStatus(branchId);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'Branch'} updated successfully.'),
        ),
      );
      await _load(reset: true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _deleteBranch(Map<String, dynamic> item) async {
    final branchId = (item['id'] as num?)?.toInt();
    if (branchId == null) {
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: 'Delete Branch',
        message:
            'Delete ${item['name'] ?? 'this branch'}? This only succeeds when the backend confirms the branch is safe to remove.',
        confirmLabel: 'Delete',
      ),
    );
    if (confirmed != true) {
      return;
    }

    try {
      await widget.repository.deleteBranch(branchId);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'Branch'} deleted successfully.'),
        ),
      );
      await _load(reset: true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _showStaffDetail(Map<String, dynamic> item) async {
    final staffId = (item['id'] as num?)?.toInt();
    if (staffId == null) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => FutureBuilder<Map<String, dynamic>>(
        future: widget.repository.fetchStaffDetail(staffId),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 360,
              child: LoadingState(label: 'Loading staff detail...'),
            );
          }
          if (snapshot.hasError) {
            return SizedBox(
              height: 360,
              child: ErrorState(
                message: snapshot.error.toString(),
                onRetry: () {
                  Navigator.of(context).pop();
                  _showStaffDetail(item);
                },
              ),
            );
          }

          final staff = snapshot.data ?? item;
          return _GymStaffDetailSheet(
            user: staff,
            onEdit: () async {
              Navigator.of(context).pop();
              await widget.onOpenForm(_AdminFormType.staff, prefill: staff);
            },
            onActivateOrDeactivate: () {
              Navigator.of(context).pop();
              _toggleStaffStatus(staff);
            },
            onDelete: () {
              Navigator.of(context).pop();
              _deleteStaff(staff);
            },
          );
        },
      ),
    );
  }

  Future<void> _toggleStaffStatus(Map<String, dynamic> item) async {
    final staffId = (item['id'] as num?)?.toInt();
    if (staffId == null) {
      return;
    }
    final activate = item['is_active'] != true;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: activate ? 'Activate Staff' : 'Deactivate Staff',
        message:
            '${activate ? 'Activate' : 'Deactivate'} ${item['name'] ?? 'this staff member'}?',
        confirmLabel: activate ? 'Activate' : 'Deactivate',
      ),
    );
    if (confirmed != true) {
      return;
    }

    try {
      if (activate) {
        await widget.repository.activateStaff(staffId);
      } else {
        await widget.repository.deactivateStaff(staffId);
      }
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'Staff'} updated successfully.'),
        ),
      );
      await _load(reset: true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _deleteStaff(Map<String, dynamic> item) async {
    final staffId = (item['id'] as num?)?.toInt();
    if (staffId == null) {
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: 'Delete Staff',
        message:
            'Remove ${item['name'] ?? 'this staff member'} from the gym staff roster?',
        confirmLabel: 'Delete',
      ),
    );
    if (confirmed != true) {
      return;
    }

    try {
      await widget.repository.deleteStaff(staffId);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item['name'] ?? 'Staff'} removed successfully.'),
        ),
      );
      await _load(reset: true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        PremiumCard(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
                      ),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Icon(widget.destination.icon, color: Colors.white),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.destination.title,
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Search, filter, and act on ${widget.destination.title.toLowerCase()} records.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  SizedBox(
                    width: 640,
                    child: TextField(
                      controller: _searchController,
                      decoration: InputDecoration(
                        hintText: _searchHint(),
                        prefixIcon: const Icon(Icons.search_rounded),
                        suffixIcon: _searchController.text.trim().isEmpty
                            ? null
                            : IconButton(
                                onPressed: () {
                                  _searchController.clear();
                                  _load(reset: true);
                                },
                                icon: const Icon(Icons.close_rounded),
                              ),
                      ),
                      onChanged: (_) => setState(() {}),
                      onSubmitted: (_) => _load(reset: true),
                    ),
                  ),
                  if (widget.destination.formType != null)
                    SizedBox(
                      width: 140,
                      child: AppPrimaryButton(
                        onPressed: () =>
                            widget.onOpenForm(widget.destination.formType),
                        icon: Icons.add_rounded,
                        label: 'New',
                      ),
                    ),
                ],
              ),
              if (_filterOptions().isNotEmpty) ...[
                const SizedBox(height: 12),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: _filterOptions().map((option) {
                      final selected = _quickFilter == option.key;

                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: FilterChip(
                          selected: selected,
                          label: Text(option.label),
                          onSelected: (_) {
                            setState(
                              () => _quickFilter = selected ? null : option.key,
                            );
                            _load(reset: true);
                          },
                        ),
                      );
                    }).toList(),
                  ),
                ),
              ],
              if (widget.destination.title == 'Members') ...[
                const SizedBox(height: 12),
                _buildMemberFilters(context),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        Expanded(
          child: _isPermissionError(_state.error)
              ? const EmptyState(
                  title: 'Permission denied',
                  message:
                      'You do not have access to this admin section for the current role.',
                  icon: Icons.lock_outline_rounded,
                )
              : AsyncStateView(
                  isLoading: _state.loading && _state.items.isEmpty,
                  error: _state.error,
                  onRetry: _load,
                  loadingChild: _CollectionLoadingSkeleton(
                    destinationTitle: widget.destination.title,
                  ),
                  isEmpty: _state.items.isEmpty && !_state.loading,
                  emptyTitle: _emptyStateTitle(),
                  emptyMessage: _emptyStateMessage(),
                  emptyIcon: _emptyStateIcon(),
                  emptyAction: _emptyStateAction(context),
                  child: RefreshIndicator(
                    onRefresh: () => _load(reset: true),
                    child: ListView.separated(
                      itemCount: _state.items.length + (_state.hasMore ? 1 : 0),
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, index) {
                        if (index >= _state.items.length) {
                          return Center(
                            child: OutlinedButton(
                              onPressed: () {
                                setState(() => _state.page += 1);
                                _load();
                              },
                              child: const Text('Load more'),
                            ),
                          );
                        }

                        final item = _state.items[index];
                        final title =
                            item['name']?.toString() ??
                            item['title']?.toString() ??
                            item['id']?.toString() ??
                            'Untitled';
                        final subtitle =
                            item['email']?.toString() ??
                            item['status']?.toString() ??
                            item['description']?.toString() ??
                            item['approval_status']?.toString() ??
                            '';

                        return RevealOnBuild(
                          delay: Duration(milliseconds: 40 * (index % 8)),
                          child: _CollectionRecordCard(
                            appUser: widget.appUser,
                            destinationTitle: widget.destination.title,
                            item: item,
                            title: title,
                            subtitle: subtitle,
                            onOpenMemberDetail: () =>
                                widget.onOpenMemberDetail(item),
                            onCollectPayment: () => widget.onOpenForm(
                              _AdminFormType.payment,
                              prefill: {
                                'member_id': (item['id'] as num?)?.toInt(),
                                'member_membership_id':
                                    (item['current_membership_id'] as num?)
                                        ?.toInt(),
                              },
                            ),
                            onSetCustomFee: () => widget.onOpenForm(
                              _AdminFormType.customFee,
                              prefill: {
                                'member_id':
                                    ((item['member_id'] as num?)?.toInt()) ??
                                    ((item['id'] as num?)?.toInt()),
                                'member_membership_id':
                                    ((item['current_membership_id'] as num?)
                                        ?.toInt()) ??
                                    ((item['id'] as num?)?.toInt()),
                              },
                            ),
                            onMarkAttendance: () => widget.onOpenForm(
                              _AdminFormType.attendance,
                              prefill: {
                                'member_id': (item['id'] as num?)?.toInt(),
                              },
                            ),
                            onAssignTrainer: () =>
                                _showAssignTrainerSheet(item),
                            onRenewMembership: () => widget.onOpenForm(
                              _AdminFormType.membershipAssign,
                              prefill: {
                                'member_id': (item['id'] as num?)?.toInt(),
                              },
                            ),
                            onSendReminder: () => widget.onOpenForm(
                              _AdminFormType.announcement,
                              prefill: {
                                'audience_type': 'selected_members',
                                'member_ids':
                                    ((item['id'] as num?)?.toInt() ?? 0)
                                        .toString(),
                                'title': 'Friendly reminder',
                              },
                            ),
                            onAssignMembers: () =>
                                _showAssignMemberToTrainerSheet(item),
                            onViewTrainerPerformance: () =>
                                _showTrainerPerformanceSheet(item),
                            onDeactivateTrainer: () => _deactivateTrainer(item),
                            onPlatformApprove: () =>
                                _updatePlatformGym(item, 'approve'),
                            onPlatformReject: () =>
                                _updatePlatformGym(item, 'reject'),
                            onPlatformVerify: () =>
                                _updatePlatformGym(item, 'verify'),
                            onPlatformFeature: () =>
                                _updatePlatformGym(item, 'feature'),
                            onPlatformPromote: () =>
                                _updatePlatformGym(item, 'promote'),
                            onPlatformDeactivate: () => _updatePlatformGym(
                              item,
                              item['is_active'] == true
                                  ? 'deactivate'
                                  : 'activate',
                            ),
                            onPlatformViewDetails: () =>
                                _showPlatformGymDetail(item),
                            onPlatformUserViewDetails: () =>
                                _showPlatformUserDetail(item),
                            onPlatformUserToggle: () =>
                                _updatePlatformUser(item),
                            onPlatformGymOwnerViewDetails: () =>
                                _showPlatformGymOwnerDetail(item),
                            onPlatformGymOwnerToggle: () =>
                                _updatePlatformGymOwner(item),
                            onPlatformFacilityViewDetails: () =>
                                _showPlatformFacilityDetail(item),
                            onPlatformFacilityToggle: () =>
                                _togglePlatformFacilityStatus(item),
                            onPlatformFacilityDelete: () =>
                                _deletePlatformFacility(item),
                            onPlatformFacilityEdit: () => widget.onOpenForm(
                              _AdminFormType.platformFacility,
                              prefill: item,
                            ),
                            onStaffViewDetails: () => _showStaffDetail(item),
                            onStaffToggle: () => _toggleStaffStatus(item),
                            onStaffDelete: () => _deleteStaff(item),
                            onStaffEdit: () => widget.onOpenForm(
                              _AdminFormType.staff,
                              prefill: item,
                            ),
                            onBranchViewDetails: () => _showBranchDetail(item),
                            onBranchToggle: () => _toggleBranchStatus(item),
                            onBranchDelete: () => _deleteBranch(item),
                            onBranchEdit: () => widget.onOpenForm(
                              _AdminFormType.branch,
                              prefill: item,
                            ),
                            onMembershipPlanViewDetails: () =>
                                _showMembershipPlanDetail(item),
                            onMembershipPlanToggle: () =>
                                _toggleMembershipPlanStatus(item),
                            onMembershipViewDetails: () =>
                                _showMembershipDetail(item),
                            onTap: widget.destination.title == 'Members'
                                ? () => widget.onOpenMemberDetail(item)
                                : widget.destination.title == 'Custom Fees'
                                ? () => widget.onOpenForm(
                                    _AdminFormType.customFee,
                                    prefill: {
                                      'member_id': (item['member_id'] as num?)
                                          ?.toInt(),
                                      'member_membership_id':
                                          (item['id'] as num?)?.toInt(),
                                    },
                                  )
                                : widget.destination.title == 'Membership Plans'
                                ? () => _showMembershipPlanDetail(item)
                                : widget.destination.title == 'Memberships'
                                ? () => _showMembershipDetail(item)
                                : widget.destination.title == 'Gyms'
                                ? () => _showPlatformGymDetail(item)
                                : widget.destination.title == 'Users'
                                ? () => _showPlatformUserDetail(item)
                                : widget.destination.title == 'Gym Owners'
                                ? () => _showPlatformGymOwnerDetail(item)
                                : widget.destination.title == 'Facilities'
                                ? () => _showPlatformFacilityDetail(item)
                                : widget.destination.title == 'Staff'
                                ? () => _showStaffDetail(item)
                                : widget.destination.title == 'Branches'
                                ? () => _showBranchDetail(item)
                                : null,
                          ),
                        );
                      },
                    ),
                  ),
                ),
        ),
      ],
    );
  }

  Map<String, dynamic>? _queryParameters() {
    final params = <String, dynamic>{};
    final search = _searchController.text.trim();
    if (search.isNotEmpty) {
      params['search'] = search;
      params['member_search'] = search;
    }

    if (widget.destination.title == 'Members') {
      if (_selectedMemberBranchId != null) {
        params['branch_id'] = _selectedMemberBranchId;
      }
      if (_selectedMemberTrainerId != null) {
        params['trainer_id'] = _selectedMemberTrainerId;
      }
      if (_selectedMemberPlanId != null) {
        params['plan_id'] = _selectedMemberPlanId;
      }
    }

    switch (_quickFilter) {
      case 'members_active':
        params['status'] = 'active';
        break;
      case 'members_expired':
        params['status'] = 'expired';
        break;
      case 'members_expiring_soon':
        params['status'] = 'expiring_soon';
        break;
      case 'members_due':
        params['status'] = 'due_payment';
        break;
      case 'members_overdue':
        params['status'] = 'overdue';
        break;
      case 'members_no_trainer':
        params['no_trainer_assigned'] = true;
        break;
      case 'members_inactive_7':
        params['inactive_7_days'] = true;
        break;
      case 'payments_paid':
        params['payment_status'] = 'paid';
        break;
      case 'payments_partial':
        params['payment_status'] = 'partial';
        break;
      case 'payments_overdue':
        params['payment_status'] = 'overdue';
        break;
      case 'catalog_active':
        params['status'] = 'active';
        break;
      case 'catalog_inactive':
        params['status'] = 'inactive';
        break;
      case 'exercises_approved':
        params['status'] = 'approved';
        break;
      case 'exercises_pending':
        params['status'] = 'pending';
        break;
      case 'trainers_active':
        params['is_active'] = true;
        break;
      case 'trainers_inactive':
        params['is_active'] = false;
        break;
      case 'gyms_pending':
        params['approval_status'] = 'pending';
        break;
      case 'gyms_active':
        params['status'] = 'active';
        break;
      case 'gyms_inactive':
        params['status'] = 'inactive';
        break;
      case 'gyms_verified':
        params['verified'] = true;
        break;
      case 'gyms_featured':
        params['featured'] = true;
        break;
      case 'gyms_promoted':
        params['promoted'] = true;
        break;
      case 'users_active':
        params['status'] = 'active';
        break;
      case 'users_inactive':
        params['status'] = 'inactive';
        break;
      case 'users_gym_owner':
        params['role'] = 'gym_owner';
        break;
      case 'users_member':
        params['role'] = 'member';
        break;
      case 'users_trainer':
        params['role'] = 'trainer';
        break;
      case 'facilities_active':
        params['status'] = 'active';
        break;
      case 'facilities_inactive':
        params['status'] = 'inactive';
        break;
      case 'owners_active':
        params['status'] = 'active';
        break;
      case 'owners_inactive':
        params['status'] = 'inactive';
        break;
      case 'branches_active':
        params['status'] = 'active';
        break;
      case 'branches_inactive':
        params['status'] = 'inactive';
        break;
    }

    return params.isEmpty ? null : params;
  }

  List<_QuickFilterOption> _filterOptions() {
    switch (widget.destination.title) {
      case 'Members':
        return const [
          _QuickFilterOption('members_active', 'Active'),
          _QuickFilterOption('members_expired', 'Expired'),
          _QuickFilterOption('members_expiring_soon', 'Expiring Soon'),
          _QuickFilterOption('members_due', 'Due'),
          _QuickFilterOption('members_overdue', 'Overdue'),
          _QuickFilterOption('members_no_trainer', 'No Trainer'),
          _QuickFilterOption('members_inactive_7', 'Inactive 7 Days'),
        ];
      case 'Payments':
      case 'Memberships':
      case 'Dues':
        return const [
          _QuickFilterOption('payments_paid', 'Paid'),
          _QuickFilterOption('payments_partial', 'Partial'),
          _QuickFilterOption('payments_overdue', 'Overdue'),
        ];
      case 'Cities':
      case 'Fitness Goals':
      case 'Trainer Specializations':
      case 'Banners':
      case 'Scheduled Reminders':
        return const [
          _QuickFilterOption('catalog_active', 'Active'),
          _QuickFilterOption('catalog_inactive', 'Inactive'),
        ];
      case 'Exercises':
        return const [
          _QuickFilterOption('exercises_approved', 'Approved'),
          _QuickFilterOption('exercises_pending', 'Pending'),
        ];
      case 'Trainers':
        return const [
          _QuickFilterOption('trainers_active', 'Active'),
          _QuickFilterOption('trainers_inactive', 'Inactive'),
        ];
      case 'Gyms':
        return const [
          _QuickFilterOption('gyms_pending', 'Pending'),
          _QuickFilterOption('gyms_active', 'Active'),
          _QuickFilterOption('gyms_inactive', 'Inactive'),
          _QuickFilterOption('gyms_verified', 'Verified'),
          _QuickFilterOption('gyms_featured', 'Featured'),
          _QuickFilterOption('gyms_promoted', 'Promoted'),
        ];
      case 'Users':
        return const [
          _QuickFilterOption('users_active', 'Active'),
          _QuickFilterOption('users_inactive', 'Inactive'),
          _QuickFilterOption('users_gym_owner', 'Gym Owners'),
          _QuickFilterOption('users_member', 'Members'),
          _QuickFilterOption('users_trainer', 'Trainers'),
        ];
      case 'Gym Owners':
        return const [
          _QuickFilterOption('owners_active', 'Active'),
          _QuickFilterOption('owners_inactive', 'Inactive'),
        ];
      case 'Facilities':
        return const [
          _QuickFilterOption('facilities_active', 'Active'),
          _QuickFilterOption('facilities_inactive', 'Inactive'),
        ];
      case 'Branches':
        return const [
          _QuickFilterOption('branches_active', 'Active'),
          _QuickFilterOption('branches_inactive', 'Inactive'),
        ];
      default:
        return const [];
    }
  }

  String _emptyStateTitle() {
    switch (widget.destination.title) {
      case 'Members':
        return 'No members yet';
      case 'Payments':
        return 'No payments recorded';
      case 'Dues':
        return 'No dues found';
      case 'Membership Plans':
        return 'No membership plans yet';
      case 'Trainers':
        return 'No trainers yet';
      case 'Gyms':
        return 'No gyms found';
      case 'Users':
        return 'No users found';
      case 'Gym Owners':
        return 'No gym owners found';
      case 'Facilities':
        return 'No facilities found';
      case 'Cities':
        return 'No cities found';
      case 'Fitness Goals':
        return 'No fitness goals found';
      case 'Trainer Specializations':
        return 'No trainer specializations found';
      case 'Banners':
        return 'No banners found';
      case 'Exercises':
        return 'No exercises found';
      case 'Branches':
        return 'No branches found';
      case 'Staff':
        return 'No staff yet';
      case 'Announcements':
        return 'No announcements yet';
      default:
        return 'Nothing here yet';
    }
  }

  String _emptyStateMessage() {
    switch (widget.destination.title) {
      case 'Members':
        return 'Start by adding your first member or importing your list.';
      case 'Payments':
        return 'Collect the first payment for this member and it will appear here.';
      case 'Dues':
        return 'No due memberships match the selected filters.';
      case 'Membership Plans':
        return 'Create the first plan to start assigning memberships.';
      case 'Trainers':
        return 'Add your first trainer to start coaching and assignment flows.';
      case 'Gyms':
        return 'No platform gyms match the current filters yet.';
      case 'Users':
        return 'No platform users match the current search or role filters.';
      case 'Gym Owners':
        return 'No gym owners match the current filters yet.';
      case 'Facilities':
        return 'No facilities match the current search or status filters.';
      case 'Cities':
        return 'No platform cities match the current filters.';
      case 'Fitness Goals':
        return 'No onboarding fitness goals match the current filters.';
      case 'Trainer Specializations':
        return 'No trainer specialization records match the current filters.';
      case 'Banners':
        return 'No app or discovery banners match the current filters.';
      case 'Exercises':
        return 'No global exercises match the current filters.';
      case 'Branches':
        return 'No branches match the current filters yet.';
      case 'Staff':
        return 'Build the operations team by adding staff and branch managers.';
      case 'Announcements':
        return 'Send your first announcement to keep members informed.';
      default:
        return 'No records match the current view yet.';
    }
  }

  IconData _emptyStateIcon() {
    switch (widget.destination.title) {
      case 'Members':
        return Icons.groups_rounded;
      case 'Payments':
        return Icons.payments_rounded;
      case 'Dues':
        return Icons.warning_amber_rounded;
      case 'Membership Plans':
        return Icons.workspace_premium_rounded;
      case 'Trainers':
        return Icons.fitness_center_rounded;
      case 'Gyms':
        return Icons.store_mall_directory_rounded;
      case 'Users':
        return Icons.groups_rounded;
      case 'Gym Owners':
        return Icons.badge_rounded;
      case 'Facilities':
        return Icons.spa_rounded;
      case 'Cities':
        return Icons.location_city_rounded;
      case 'Fitness Goals':
        return Icons.flag_circle_rounded;
      case 'Trainer Specializations':
        return Icons.military_tech_rounded;
      case 'Banners':
        return Icons.view_day_rounded;
      case 'Exercises':
        return Icons.fitness_center_rounded;
      case 'Branches':
        return Icons.location_city_rounded;
      case 'Staff':
        return Icons.badge_rounded;
      case 'Announcements':
        return Icons.campaign_rounded;
      default:
        return Icons.inbox_rounded;
    }
  }

  Widget? _emptyStateAction(BuildContext context) {
    if (widget.destination.formType == null) {
      return null;
    }

    final label = switch (widget.destination.title) {
      'Gyms' => 'Add Gym',
      'Gym Owners' => 'Add Gym Owner',
      'Facilities' => 'Create Facility',
      'Branches' => 'Create Branch',
      'Members' => 'Add Member',
      'Payments' => 'Collect Payment',
      'Membership Plans' => 'Create Plan',
      'Trainers' => 'Add Trainer',
      'Staff' => 'Add Staff',
      'Announcements' => 'Create Announcement',
      _ => 'Get Started',
    };

    return SizedBox(
      width: 220,
      child: GradientButton(
        label: label,
        icon: Icons.arrow_forward_rounded,
        expanded: true,
        onPressed: () => widget.onOpenForm(widget.destination.formType),
      ),
    );
  }

  String _searchHint() {
    return switch (widget.destination.title) {
      'Users' => 'Search users by name, email, or phone',
      'Gym Owners' => 'Search gym owners by name, email, or phone',
      'Gyms' => 'Search gyms by name, owner, city, or status',
      'Facilities' => 'Search facilities by name or status',
      'Cities' => 'Search cities by name, state, or country',
      'Fitness Goals' => 'Search goals by name or description',
      'Trainer Specializations' =>
        'Search specializations by name or description',
      'Banners' => 'Search banners by title or link',
      'Exercises' => 'Search exercises by name, muscle group, or equipment',
      'Audit Logs' => 'Search audit events, actors, or subjects',
      'Scheduled Reminders' => 'Search reminder jobs or notification type',
      'Branches' => 'Search branches by name, city, or pincode',
      _ => 'Search records in this section',
    };
  }
}

class _AdminFormSheet extends StatefulWidget {
  const _AdminFormSheet({
    required this.type,
    required this.repository,
    required this.appUser,
    required this.userRole,
    required this.prefill,
    required this.parentContext,
  });

  final _AdminFormType type;
  final AdminRepository repository;
  final AppUser appUser;
  final String userRole;
  final Map<String, dynamic> prefill;
  final BuildContext parentContext;

  @override
  State<_AdminFormSheet> createState() => _AdminFormSheetState();
}

class _AdminFormSheetState extends State<_AdminFormSheet> {
  final _formKey = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  bool _busy = false;
  bool _customFeeEnabled = false;
  bool _joiningFeeWaived = false;
  String _discountType = 'none';
  String? _lastScannedQrPayload;
  Map<String, dynamic> _membershipDetail = const {};
  List<Map<String, dynamic>> _paymentHistory = const [];
  List<Map<String, dynamic>> _customFeeAudits = const [];
  List<Map<String, dynamic>> _membershipActivityTimeline = const [];
  List<Map<String, dynamic>> _memberCustomFeeMemberships = const [];
  String? _customFeeMemberName;
  bool _canEditCustomFee = true;
  bool _detailLoading = false;
  String? _submitError;
  bool _optionsLoading = false;
  List<Map<String, dynamic>> _platformOwners = const [];
  List<Map<String, dynamic>> _platformFacilities = const [];
  String _ownerMode = 'existing';
  int? _selectedOwnerId;
  final Set<int> _selectedFacilityIds = <int>{};
  final Set<String> _selectedWeeklyOff = <String>{};
  final Set<String> _selectedBranchWeeklyOff = <String>{};
  bool _createDefaultBranch = true;
  bool _branchSameAsGym = true;
  bool _publicListingEnabled = false;
  bool _showPricing = true;
  bool _trialAvailable = false;
  bool _contactVisible = true;
  List<Map<String, dynamic>> _gymBranches = const [];
  List<Map<String, dynamic>> _gymTrainers = const [];
  List<Map<String, dynamic>> _gymMembers = const [];
  List<Map<String, dynamic>> _gymMemberMemberships = const [];
  List<Map<String, dynamic>> _gymMembershipPlans = const [];
  String _staffUserMode = 'new';
  String _trainerUserMode = 'new';
  String _memberUserMode = 'new';
  final Set<int> _selectedBranchIds = <int>{};
  final Set<String> _selectedStaffPermissions = <String>{};

  static const List<String> _staffPermissionOptions = <String>[
    'view_billing',
    'collect_payment',
    'edit_custom_fee',
    'manage_attendance',
    'manage_members',
    'manage_trainers',
    'send_announcements',
    'view_reports',
    'manage_staff',
  ];

  TextEditingController controllerFor(String key, [String initialValue = '']) {
    return _controllers.putIfAbsent(key, () {
      final controller = TextEditingController(
        text: widget.prefill[key]?.toString() ?? initialValue,
      );

      if (key == 'member_membership_id') {
        controller.addListener(_handleMembershipSelectionChanged);
      }

      if (key == 'membership_plan_id' || key == 'start_date') {
        controller.addListener(() {
          if (mounted) {
            setState(() {});
          }
        });
      }

      return controller;
    });
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    if (widget.type == _AdminFormType.platformGym) {
      _seedPlatformGymState();
      _loadPlatformGymOptions();
    }
    if (widget.type == _AdminFormType.gymProfile ||
        widget.type == _AdminFormType.publicListing ||
        widget.type == _AdminFormType.branch) {
      _seedGymScopedState();
      _loadGymScopedOptions();
    }
    if (widget.type == _AdminFormType.staff) {
      _seedStaffState();
      _loadStaffOptions();
    }
    if (widget.type == _AdminFormType.trainer) {
      _seedTrainerState();
      _loadTrainerOptions();
    }
    if (widget.type == _AdminFormType.member) {
      _seedMemberState();
      _loadMemberOptions();
    }
    if (widget.type == _AdminFormType.customFee) {
      _loadCustomFeeMemberScope();
    }
    if (widget.type == _AdminFormType.plan) {
      _seedPlanState();
      _loadPlanOptions();
    }
    if (widget.type == _AdminFormType.membershipAssign) {
      _seedMembershipAssignState();
      _loadMembershipAssignOptions();
    }
    if (widget.type == _AdminFormType.payment) {
      _seedPaymentState();
      _loadPaymentOptions();
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final membershipId = int.tryParse(
        widget.prefill['member_membership_id']?.toString() ??
            controllerFor('member_membership_id').text,
      );
      if (membershipId != null && membershipId > 0) {
        _loadMembershipSupportData(membershipId);
      }
    });
  }

  void _seedPlatformGymState() {
    _ownerMode =
        (widget.prefill['owner_user_id'] != null &&
            widget.prefill['owner_user_id'].toString().isNotEmpty)
        ? 'existing'
        : 'new';
    _selectedOwnerId =
        (widget.prefill['owner_user_id'] as num?)?.toInt() ??
        (_recordMap(widget.prefill['owner'])['id'] as num?)?.toInt();
    _selectedFacilityIds
      ..clear()
      ..addAll(
        (widget.prefill['facility_ids'] as List<dynamic>? ?? const []).map(
          (entry) => (entry as num).toInt(),
        ),
      )
      ..addAll(
        (widget.prefill['facilities'] as List<dynamic>? ?? const [])
            .map((entry) => _recordMap(entry)['id'])
            .whereType<num>()
            .map((entry) => entry.toInt()),
      );
    _selectedWeeklyOff
      ..clear()
      ..addAll(
        (widget.prefill['weekly_off'] as List<dynamic>? ?? const []).map(
          (entry) => entry.toString(),
        ),
      );
    _selectedBranchWeeklyOff
      ..clear()
      ..addAll(
        (widget.prefill['branch_weekly_off'] as List<dynamic>? ?? const []).map(
          (entry) => entry.toString(),
        ),
      );
    _createDefaultBranch = widget.prefill['create_default_branch'] != false;
    _branchSameAsGym = widget.prefill['branch_same_as_gym'] != false;
    _publicListingEnabled = widget.prefill['public_listing_enabled'] == true;
    _showPricing = widget.prefill['show_pricing'] != false;
    _trialAvailable = widget.prefill['trial_available'] == true;
    _contactVisible = widget.prefill['contact_visible'] != false;
  }

  Future<void> _loadPlatformGymOptions() async {
    setState(() => _optionsLoading = true);
    try {
      final results = await Future.wait([
        widget.repository.fetchPlatformGymOwners(),
        widget.repository.fetchPlatformFacilities(),
      ]);
      if (!mounted) {
        return;
      }
      setState(() {
        _platformOwners = results[0];
        _platformFacilities = results[1];
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _submitError = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _optionsLoading = false);
      }
    }
  }

  void _seedGymScopedState() {
    _selectedFacilityIds
      ..clear()
      ..addAll(
        (widget.prefill['facility_ids'] as List<dynamic>? ?? const []).map(
          (entry) => (entry as num).toInt(),
        ),
      )
      ..addAll(
        (widget.prefill['facilities'] as List<dynamic>? ?? const [])
            .map((entry) => _recordMap(entry)['id'])
            .whereType<num>()
            .map((entry) => entry.toInt()),
      );
    _selectedWeeklyOff
      ..clear()
      ..addAll(
        (widget.prefill['weekly_off'] as List<dynamic>? ?? const []).map(
          (entry) => entry.toString(),
        ),
      );
    _publicListingEnabled = widget.prefill['public_listing_enabled'] == true;
    _showPricing =
        widget.prefill['show_pricing'] == true ||
        widget.prefill['pricing_visible'] == true;
    _trialAvailable = widget.prefill['trial_available'] == true;
    _contactVisible = widget.prefill['contact_visible'] == true;
  }

  Future<void> _loadGymScopedOptions() async {
    setState(() => _optionsLoading = true);
    try {
      final gym = await widget.repository.fetchGymProfile();
      if (!mounted) {
        return;
      }
      _platformFacilities = (gym['facilities'] as List<dynamic>? ?? const [])
          .map((entry) => _recordMap(entry))
          .toList();
      if (widget.prefill.isEmpty) {
        controllerFor('name').text = gym['name']?.toString() ?? '';
        controllerFor('description').text =
            gym['description']?.toString() ?? '';
        controllerFor('logo_url').text = gym['logo_url']?.toString() ?? '';
        controllerFor('cover_image_url').text =
            gym['cover_image_url']?.toString() ?? '';
        controllerFor('address_line').text =
            gym['address_line']?.toString() ?? gym['address']?.toString() ?? '';
        controllerFor('city').text = gym['city']?.toString() ?? '';
        controllerFor('state').text = gym['state']?.toString() ?? '';
        controllerFor('country', 'India').text =
            gym['country']?.toString() ?? 'India';
        controllerFor('pincode').text = gym['pincode']?.toString() ?? '';
        controllerFor('latitude').text = gym['latitude']?.toString() ?? '';
        controllerFor('longitude').text = gym['longitude']?.toString() ?? '';
        controllerFor('timezone', 'Asia/Kolkata').text =
            gym['timezone']?.toString() ?? 'Asia/Kolkata';
        controllerFor('opening_time').text =
            gym['opening_time']?.toString() ?? '';
        controllerFor('closing_time').text =
            gym['closing_time']?.toString() ?? '';
      }
      if (widget.type == _AdminFormType.gymProfile ||
          widget.type == _AdminFormType.publicListing) {
        _selectedFacilityIds
          ..clear()
          ..addAll(
            (gym['facilities'] as List<dynamic>? ?? const [])
                .map((entry) => _recordMap(entry)['id'])
                .whereType<num>()
                .map((entry) => entry.toInt()),
          );
        _selectedWeeklyOff
          ..clear()
          ..addAll(
            (gym['weekly_off'] as List<dynamic>? ?? const []).map(
              (entry) => entry.toString(),
            ),
          );
        _publicListingEnabled = gym['public_listing_enabled'] == true;
        _showPricing =
            gym['show_pricing'] == true || gym['pricing_visible'] == true;
        _trialAvailable = gym['trial_available'] == true;
        _contactVisible = gym['contact_visible'] == true;
      }
      if (mounted) {
        setState(() {});
      }
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _submitError = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _optionsLoading = false);
      }
    }
  }

  void _seedStaffState() {
    final isEditing = (widget.prefill['id'] as num?) != null;
    _staffUserMode = isEditing
        ? 'new'
        : (widget.prefill['existing_user_id'] != null ? 'existing' : 'new');
    controllerFor(
      'role',
      (widget.prefill['roles'] as List<dynamic>? ?? const []).contains(
            'branch_manager',
          )
          ? 'branch_manager'
          : (widget.prefill['role']?.toString() ?? 'gym_staff'),
    );
    controllerFor('existing_user_id', widget.prefill['id']?.toString() ?? '');
    controllerFor('name', widget.prefill['name']?.toString() ?? '');
    controllerFor('email', widget.prefill['email']?.toString() ?? '');
    controllerFor('phone', widget.prefill['phone']?.toString() ?? '');
    final branchIds = <int>{
      ...((widget.prefill['branch_ids'] as List<dynamic>? ?? const [])
          .whereType<num>()
          .map((entry) => entry.toInt())),
      ...((widget.prefill['branches'] as List<dynamic>? ?? const [])
          .map((entry) => _recordMap(entry)['id'])
          .whereType<num>()
          .map((entry) => entry.toInt())),
      ...((widget.prefill['staff_assignments'] as List<dynamic>? ?? const [])
          .map((entry) => _recordMap(entry)['branch_id'])
          .whereType<num>()
          .map((entry) => entry.toInt())),
    };
    _selectedBranchIds
      ..clear()
      ..addAll(branchIds);
    final customPermissions = <String>{
      ...((widget.prefill['custom_permissions'] as List<dynamic>? ?? const [])
          .map((entry) => entry.toString())),
      ...((widget.prefill['staff_assignments'] as List<dynamic>? ?? const [])
          .expand(
            (entry) =>
                (_recordMap(entry)['custom_permissions'] as List<dynamic>? ??
                        const [])
                    .map((permission) => permission.toString()),
          )),
    };
    _selectedStaffPermissions
      ..clear()
      ..addAll(customPermissions);
  }

  Future<void> _loadStaffOptions() async {
    setState(() => _optionsLoading = true);
    try {
      final branches = await widget.repository.fetchCollection(
        '/gym/branches',
        perPage: 100,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _gymBranches = branches.items;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _submitError = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _optionsLoading = false);
      }
    }
  }

  void _seedTrainerState() {
    final isEditing = (widget.prefill['id'] as num?) != null;
    _trainerUserMode = isEditing
        ? 'new'
        : (widget.prefill['existing_user_id'] != null ? 'existing' : 'new');
    controllerFor('existing_user_id', widget.prefill['id']?.toString() ?? '');
    controllerFor('name', widget.prefill['name']?.toString() ?? '');
    controllerFor('email', widget.prefill['email']?.toString() ?? '');
    controllerFor('phone', widget.prefill['phone']?.toString() ?? '');
    controllerFor(
      'branch_id',
      (_recordMap(widget.prefill['managed_trainer_profile'])['branch_id'] ??
                  widget.prefill['branch_id'])
              ?.toString() ??
          '',
    );
    controllerFor(
      'profile_photo_url',
      (_recordMap(
                    widget.prefill['managed_trainer_profile'],
                  )['profile_photo_url'] ??
                  widget.prefill['avatar'] ??
                  widget.prefill['profile_photo_url'])
              ?.toString() ??
          '',
    );
    controllerFor(
      'bio',
      _recordMap(
            widget.prefill['managed_trainer_profile'],
          )['bio']?.toString() ??
          '',
    );
    final profile = _recordMap(widget.prefill['managed_trainer_profile']);
    final specializations =
        (profile['specializations'] as List<dynamic>? ?? const [])
            .map((entry) => entry.toString())
            .where((entry) => entry.isNotEmpty)
            .toList();
    controllerFor(
      'specialization',
      profile['specialization']?.toString() ??
          profile['primary_specialization']?.toString() ??
          '',
    );
    controllerFor('specializations', specializations.join(', '));
    controllerFor(
      'experience_years',
      (profile['experience_years'] ?? 0).toString(),
    );
    final certifications =
        (profile['certifications'] as List<dynamic>? ?? const [])
            .map((entry) => entry.toString())
            .where((entry) => entry.isNotEmpty)
            .toList();
    controllerFor('certifications', certifications.join(', '));
    controllerFor(
      'status',
      profile['status']?.toString() ??
          (profile['is_active'] == false ? 'inactive' : 'active'),
    );
  }

  Future<void> _loadTrainerOptions() async {
    setState(() => _optionsLoading = true);
    try {
      final branches = await widget.repository.fetchCollection(
        '/gym/branches',
        perPage: 100,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _gymBranches = branches.items;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _submitError = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _optionsLoading = false);
      }
    }
  }

  void _seedMemberState() {
    final isEditing = (widget.prefill['id'] as num?) != null;
    final profile = _recordMap(widget.prefill['member_profile']);
    _memberUserMode = isEditing
        ? 'new'
        : (widget.prefill['existing_user_id'] != null ? 'existing' : 'new');
    controllerFor('existing_user_id', widget.prefill['id']?.toString() ?? '');
    controllerFor('name', widget.prefill['name']?.toString() ?? '');
    controllerFor('email', widget.prefill['email']?.toString() ?? '');
    controllerFor('phone', widget.prefill['phone']?.toString() ?? '');
    controllerFor('avatar', widget.prefill['avatar']?.toString() ?? '');
    controllerFor(
      'branch_id',
      (profile['branch_id'] ?? widget.prefill['branch_id'])?.toString() ?? '',
    );
    controllerFor(
      'assigned_trainer_user_id',
      (profile['assigned_trainer_user_id'] ??
                  widget.prefill['assigned_trainer_user_id'])
              ?.toString() ??
          '',
    );
    controllerFor(
      'fitness_goal',
      profile['fitness_goal']?.toString() ??
          widget.prefill['fitness_goal']?.toString() ??
          '',
    );
    controllerFor('gender', profile['gender']?.toString() ?? '');
    controllerFor(
      'height_cm',
      (profile['height_cm'] ?? widget.prefill['height_cm'])?.toString() ?? '',
    );
    controllerFor(
      'weight_kg',
      (profile['weight_kg'] ?? widget.prefill['weight_kg'])?.toString() ?? '',
    );
    controllerFor(
      'experience_level',
      profile['experience_level']?.toString() ?? 'beginner',
    );
    controllerFor('medical_notes', profile['medical_notes']?.toString() ?? '');
    controllerFor('injury_notes', profile['injury_notes']?.toString() ?? '');
    controllerFor(
      'emergency_contact_name',
      profile['emergency_contact_name']?.toString() ?? '',
    );
    controllerFor(
      'emergency_contact_phone',
      profile['emergency_contact_phone']?.toString() ?? '',
    );
    controllerFor(
      'status',
      profile['membership_status']?.toString() ??
          (profile['is_active'] == false ? 'inactive' : 'active'),
    );
  }

  Future<void> _loadMemberOptions() async {
    setState(() => _optionsLoading = true);
    try {
      final results = await Future.wait([
        widget.repository.fetchCollection('/gym/branches', perPage: 100),
        widget.repository.fetchCollection('/gym/trainers', perPage: 100),
      ]);
      if (!mounted) {
        return;
      }
      setState(() {
        _gymBranches = results[0].items;
        _gymTrainers = results[1].items;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _submitError = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _optionsLoading = false);
      }
    }
  }

  void _seedPlanState() {
    controllerFor('branch_id', widget.prefill['branch_id']?.toString() ?? '');
    controllerFor('name', widget.prefill['name']?.toString() ?? '');
    controllerFor(
      'duration_days',
      (widget.prefill['duration_days'] ?? 30).toString(),
    );
    controllerFor('plan_price', (widget.prefill['plan_price'] ?? 0).toString());
    controllerFor(
      'joining_fee',
      (widget.prefill['joining_fee'] ?? 0).toString(),
    );
    controllerFor(
      'pt_included',
      widget.prefill['pt_included'] == true ? 'true' : 'false',
    );
    controllerFor(
      'description',
      widget.prefill['description']?.toString() ?? '',
    );
    controllerFor('status', widget.prefill['status']?.toString() ?? 'active');
  }

  Future<void> _loadPlanOptions() async {
    setState(() => _optionsLoading = true);
    try {
      final results = await Future.wait([
        widget.repository.fetchGymProfile(),
        widget.repository.fetchCollection('/gym/branches', perPage: 100),
      ]);
      if (!mounted) {
        return;
      }
      final gym = results[0] as Map<String, dynamic>;
      final branches = results[1] as PaginatedResponse<Map<String, dynamic>>;
      controllerFor('gym_id').text = gym['id']?.toString() ?? '';
      setState(() {
        _gymBranches = branches.items;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _submitError = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _optionsLoading = false);
      }
    }
  }

  void _seedMembershipAssignState() {
    controllerFor('member_id', widget.prefill['member_id']?.toString() ?? '');
    controllerFor(
      'membership_plan_id',
      widget.prefill['membership_plan_id']?.toString() ?? '',
    );
    controllerFor(
      'start_date',
      widget.prefill['start_date']?.toString() ??
          DateFormat('yyyy-MM-dd').format(DateTime.now()),
    );
    controllerFor('due_date', widget.prefill['due_date']?.toString() ?? '');
    controllerFor(
      'amount_paid',
      (widget.prefill['amount_paid'] ?? 0).toString(),
    );
    controllerFor('status', widget.prefill['status']?.toString() ?? 'active');
  }

  void _seedPaymentState() {
    controllerFor('member_id', widget.prefill['member_id']?.toString() ?? '');
    controllerFor(
      'member_membership_id',
      widget.prefill['member_membership_id']?.toString() ?? '',
    );
    controllerFor('amount', widget.prefill['amount']?.toString() ?? '');
    controllerFor(
      'payment_mode',
      widget.prefill['payment_mode']?.toString() ?? 'cash',
    );
    controllerFor(
      'payment_date',
      widget.prefill['payment_date']?.toString() ??
          DateFormat('yyyy-MM-dd').format(DateTime.now()),
    );
    controllerFor('notes', widget.prefill['notes']?.toString() ?? '');
  }

  Future<void> _loadMembershipAssignOptions() async {
    setState(() => _optionsLoading = true);
    try {
      final results = await Future.wait([
        widget.repository.fetchGymProfile(),
        widget.repository.fetchCollection('/gym/branches', perPage: 100),
        widget.repository.fetchCollection('/gym/members', perPage: 100),
        widget.repository.fetchCollection(
          '/gym/membership-plans',
          perPage: 100,
        ),
      ]);
      if (!mounted) {
        return;
      }
      final gym = results[0] as Map<String, dynamic>;
      final branches = results[1] as PaginatedResponse<Map<String, dynamic>>;
      final members = results[2] as PaginatedResponse<Map<String, dynamic>>;
      final plans = results[3] as PaginatedResponse<Map<String, dynamic>>;
      controllerFor('gym_id').text = gym['id']?.toString() ?? '';
      setState(() {
        _gymBranches = branches.items;
        _gymMembers = members.items;
        _gymMembershipPlans = plans.items;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _submitError = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _optionsLoading = false);
      }
    }
  }

  Future<void> _loadPaymentOptions() async {
    setState(() => _optionsLoading = true);
    try {
      final results = await Future.wait([
        widget.repository.fetchCollection('/gym/members', perPage: 100),
        widget.repository.fetchCollection(
          '/gym/memberships',
          perPage: 100,
          queryParameters: {'status': 'active'},
        ),
      ]);
      if (!mounted) {
        return;
      }
      final members = results[0].items;
      final memberships = results[1].items;
      setState(() {
        _gymMembers = members;
        _gymMemberMemberships = memberships;
      });

      final selectedMemberId = int.tryParse(
        controllerFor('member_id').text.trim(),
      );
      if ((controllerFor('member_membership_id').text.trim()).isEmpty &&
          selectedMemberId != null) {
        final selectedMember = members.cast<Map<String, dynamic>?>().firstWhere(
          (member) => (member?['id'] as num?)?.toInt() == selectedMemberId,
          orElse: () => null,
        );
        final candidateMembershipId =
            (_recordMap(
                      selectedMember?['member_profile'],
                    )['current_membership_id']
                    as num?)
                ?.toInt() ??
            (_recordMap(selectedMember?['current_membership'])['id'] as num?)
                ?.toInt();
        final fallbackMembership = memberships
            .cast<Map<String, dynamic>?>()
            .firstWhere(
              (membership) =>
                  (membership?['member_id'] as num?)?.toInt() ==
                  selectedMemberId,
              orElse: () => null,
            );
        final resolvedMembershipId =
            candidateMembershipId ??
            (fallbackMembership?['id'] as num?)?.toInt();
        if (resolvedMembershipId != null) {
          controllerFor('member_membership_id').text = resolvedMembershipId
              .toString();
          await _loadMembershipSupportData(resolvedMembershipId);
        }
      }
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _submitError = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _optionsLoading = false);
      }
    }
  }

  Future<void> _loadCustomFeeMemberScope() async {
    final memberId = (widget.prefill['member_id'] as num?)?.toInt();
    if (memberId == null) {
      return;
    }
    setState(() => _detailLoading = true);
    try {
      final payload = await widget.repository.fetchMemberCustomFee(
        memberId,
        membershipId: int.tryParse(
          controllerFor('member_membership_id').text.trim(),
        ),
      );
      if (!mounted) {
        return;
      }
      final memberships = (payload['memberships'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      final selectedMembershipId =
          (payload['selected_membership_id'] as num?)?.toInt() ??
          (memberships.isNotEmpty
              ? (memberships.first['id'] as num?)?.toInt()
              : null);
      setState(() {
        _memberCustomFeeMemberships = memberships;
        _customFeeMemberName = _recordMap(
          payload['member'],
        )['name']?.toString();
        _canEditCustomFee = payload['can_edit_custom_fee'] == true;
      });
      if (selectedMembershipId != null) {
        controllerFor('member_membership_id').text = selectedMembershipId
            .toString();
        await _loadMembershipSupportData(selectedMembershipId);
      }
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _submitError = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _detailLoading = false);
      }
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _busy = true;
      _submitError = null;
    });
    try {
      Map<String, dynamic>? savedPlatformGym;
      switch (widget.type) {
        case _AdminFormType.platformGym:
          final gymId = (widget.prefill['id'] as num?)?.toInt();
          final payload = _platformGymPayload();
          savedPlatformGym = gymId == null
              ? await widget.repository.createPlatformGym(payload)
              : await widget.repository.updatePlatformGym(gymId, payload);
          break;
        case _AdminFormType.platformGymOwner:
          await widget.repository.createPlatformGymOwner({
            'name': controllerFor('name').text.trim(),
            'email': controllerFor('email').text.trim(),
            if (controllerFor('phone').text.trim().isNotEmpty)
              'phone': controllerFor('phone').text.trim(),
          });
          break;
        case _AdminFormType.platformFacility:
          final facilityId = (widget.prefill['id'] as num?)?.toInt();
          final payload = {
            'name': controllerFor('name').text.trim(),
            'icon': controllerFor('icon').text.trim(),
            'status': controllerFor(
              'status',
              widget.prefill['status']?.toString() ?? 'active',
            ).text,
          };
          if (facilityId == null) {
            await widget.repository.createPlatformFacility(payload);
          } else {
            await widget.repository.updatePlatformFacility(facilityId, payload);
          }
          break;
        case _AdminFormType.platformReports:
          break;
        case _AdminFormType.gymProfile:
          await widget.repository.updateGymProfile({
            'name': controllerFor('name').text.trim(),
            'description': controllerFor('description').text.trim(),
            'logo_url': controllerFor('logo_url').text.trim(),
            'cover_image_url': controllerFor('cover_image_url').text.trim(),
            'address_line': controllerFor('address_line').text.trim(),
            'city': controllerFor('city').text.trim(),
            'state': controllerFor('state').text.trim(),
            'country': controllerFor('country', 'India').text.trim(),
            'pincode': controllerFor('pincode').text.trim(),
            'latitude': double.tryParse(controllerFor('latitude').text.trim()),
            'longitude': double.tryParse(
              controllerFor('longitude').text.trim(),
            ),
            'timezone': controllerFor('timezone', 'Asia/Kolkata').text.trim(),
            'opening_time': controllerFor('opening_time').text.trim(),
            'closing_time': controllerFor('closing_time').text.trim(),
            'weekly_off': _selectedWeeklyOff.toList(),
            'facility_ids': _selectedFacilityIds.toList(),
          });
          break;
        case _AdminFormType.branch:
          final branchId = (widget.prefill['id'] as num?)?.toInt();
          final branchPayload = {
            'name': controllerFor('name').text.trim(),
            'slug': controllerFor('slug').text.trim(),
            'address_line': controllerFor('address_line').text.trim(),
            'city': controllerFor('city').text.trim(),
            'state': controllerFor('state').text.trim(),
            'country': controllerFor('country', 'India').text.trim(),
            'pincode': controllerFor('pincode').text.trim(),
            'latitude': double.tryParse(controllerFor('latitude').text.trim()),
            'longitude': double.tryParse(
              controllerFor('longitude').text.trim(),
            ),
            'timezone': controllerFor('timezone', 'Asia/Kolkata').text.trim(),
            'opening_time': controllerFor('opening_time').text.trim(),
            'closing_time': controllerFor('closing_time').text.trim(),
            'weekly_off': _selectedWeeklyOff.toList(),
            'facility_ids': _selectedFacilityIds.toList(),
            'is_active':
                controllerFor(
                  'status',
                  widget.prefill['status']?.toString() ?? 'active',
                ).text ==
                'active',
          };
          if (branchId == null) {
            await widget.repository.createBranch(branchPayload);
          } else {
            await widget.repository.updateBranch(branchId, branchPayload);
          }
          break;
        case _AdminFormType.staff:
          final staffId = (widget.prefill['id'] as num?)?.toInt();
          final payload = <String, dynamic>{
            'role': controllerFor('role', 'gym_staff').text,
            'branch_ids': _selectedBranchIds.toList(),
            'custom_permissions': _selectedStaffPermissions.toList(),
          };
          if (_staffUserMode == 'existing') {
            final existingUserId = int.tryParse(
              controllerFor('existing_user_id').text.trim(),
            );
            if (existingUserId != null) {
              payload['existing_user_id'] = existingUserId;
            }
          } else {
            payload['name'] = controllerFor('name').text.trim();
            payload['email'] = controllerFor('email').text.trim();
            if (controllerFor('phone').text.trim().isNotEmpty) {
              payload['phone'] = controllerFor('phone').text.trim();
            }
            if (controllerFor('password').text.trim().isNotEmpty) {
              payload['password'] = controllerFor('password').text.trim();
              payload['password_confirmation'] = controllerFor(
                'password_confirmation',
              ).text.trim();
            }
          }
          if (staffId == null) {
            await widget.repository.createStaff(payload);
          } else {
            await widget.repository.updateStaff(staffId, payload);
          }
          break;
        case _AdminFormType.trainer:
          final trainerId = (widget.prefill['id'] as num?)?.toInt();
          final specializations = controllerFor('specializations').text
              .split(',')
              .map((item) => item.trim())
              .where((item) => item.isNotEmpty)
              .toList();
          final payload = <String, dynamic>{
            'branch_id': int.tryParse(controllerFor('branch_id').text.trim()),
            'profile_photo_url': controllerFor('profile_photo_url').text.trim(),
            'bio': controllerFor('bio').text.trim(),
            'specialization': controllerFor('specialization').text.trim(),
            'specializations': specializations,
            'experience_years':
                int.tryParse(
                  controllerFor('experience_years', '0').text.trim(),
                ) ??
                0,
            'certifications': controllerFor('certifications').text
                .split(',')
                .map((item) => item.trim())
                .where((item) => item.isNotEmpty)
                .toList(),
            'status': controllerFor('status', 'active').text,
          };
          if (_trainerUserMode == 'existing') {
            final existingUserId = int.tryParse(
              controllerFor('existing_user_id').text.trim(),
            );
            if (existingUserId != null) {
              payload['existing_user_id'] = existingUserId;
            }
          } else {
            payload['name'] = controllerFor('name').text.trim();
            payload['email'] = controllerFor('email').text.trim();
            if (controllerFor('phone').text.trim().isNotEmpty) {
              payload['phone'] = controllerFor('phone').text.trim();
            }
            if (controllerFor('password').text.trim().isNotEmpty) {
              payload['password'] = controllerFor('password').text.trim();
              payload['password_confirmation'] = controllerFor(
                'password_confirmation',
              ).text.trim();
            }
          }
          if (trainerId == null) {
            await widget.repository.createTrainer(payload);
          } else {
            await widget.repository.updateTrainer(trainerId, payload);
          }
          break;
        case _AdminFormType.member:
          final memberId = (widget.prefill['id'] as num?)?.toInt();
          final payload = <String, dynamic>{
            'avatar': controllerFor('avatar').text.trim(),
            'branch_id': int.tryParse(controllerFor('branch_id').text.trim()),
            'assigned_trainer_user_id': int.tryParse(
              controllerFor('assigned_trainer_user_id').text.trim(),
            ),
            'fitness_goal': controllerFor('fitness_goal').text.trim(),
            'gender': controllerFor('gender').text.trim(),
            'height_cm': double.tryParse(
              controllerFor('height_cm').text.trim(),
            ),
            'weight_kg': double.tryParse(
              controllerFor('weight_kg').text.trim(),
            ),
            'experience_level': controllerFor(
              'experience_level',
              'beginner',
            ).text.trim(),
            'medical_notes': controllerFor('medical_notes').text.trim(),
            'injury_notes': controllerFor('injury_notes').text.trim(),
            'emergency_contact_name': controllerFor(
              'emergency_contact_name',
            ).text.trim(),
            'emergency_contact_phone': controllerFor(
              'emergency_contact_phone',
            ).text.trim(),
            'status': controllerFor('status', 'active').text.trim(),
          };
          if (_memberUserMode == 'existing' && memberId == null) {
            payload['existing_user_id'] = int.tryParse(
              controllerFor('existing_user_id').text.trim(),
            );
          } else {
            payload['name'] = controllerFor('name').text.trim();
            payload['email'] = controllerFor('email').text.trim();
            if (controllerFor('phone').text.trim().isNotEmpty) {
              payload['phone'] = controllerFor('phone').text.trim();
            }
            if (controllerFor('password').text.trim().isNotEmpty) {
              payload['password'] = controllerFor('password').text.trim();
              payload['password_confirmation'] = controllerFor(
                'password_confirmation',
              ).text.trim();
            }
          }
          if (memberId == null) {
            await widget.repository.createMember(payload);
          } else {
            await widget.repository.updateMember(memberId, payload);
          }
          break;
        case _AdminFormType.plan:
          final planId = (widget.prefill['id'] as num?)?.toInt();
          final payload = {
            'gym_id': int.tryParse(controllerFor('gym_id').text),
            'branch_id': int.tryParse(controllerFor('branch_id').text),
            'name': controllerFor('name').text.trim(),
            'duration_days':
                int.tryParse(controllerFor('duration_days').text.trim()) ?? 30,
            'plan_price':
                double.tryParse(controllerFor('plan_price').text.trim()) ?? 0,
            'joining_fee':
                double.tryParse(controllerFor('joining_fee').text.trim()) ?? 0,
            'pt_included': controllerFor('pt_included', 'false').text == 'true',
            'description': controllerFor('description').text.trim(),
            'status': controllerFor('status', 'active').text,
          };
          if (planId == null) {
            await widget.repository.createMembershipPlan(payload);
          } else {
            await widget.repository.updateMembershipPlan(planId, payload);
          }
          break;
        case _AdminFormType.membershipAssign:
          final selectedMemberId = int.tryParse(
            controllerFor('member_id').text,
          );
          final payload = {
            'gym_id': int.tryParse(controllerFor('gym_id').text),
            'branch_id': int.tryParse(controllerFor('branch_id').text),
            'member_id': selectedMemberId,
            'membership_plan_id': int.tryParse(
              controllerFor('membership_plan_id').text,
            ),
            'start_date': controllerFor(
              'start_date',
              DateFormat('yyyy-MM-dd').format(DateTime.now()),
            ).text,
            'expiry_date': controllerFor('expiry_date').text.trim().isEmpty
                ? null
                : controllerFor('expiry_date').text.trim(),
            'due_date': controllerFor(
              'due_date',
              DateFormat('yyyy-MM-dd').format(DateTime.now()),
            ).text,
            'amount_paid':
                double.tryParse(
                  controllerFor('amount_paid', '0').text.trim(),
                ) ??
                0,
            'status': controllerFor('status', 'active').text.trim(),
            'custom_fee_enabled': _customFeeEnabled,
            'custom_fee_amount':
                double.tryParse(controllerFor('custom_fee_amount', '0').text) ??
                0,
            'discount_type': _discountType,
            'discount_amount':
                double.tryParse(controllerFor('discount_amount', '0').text) ??
                0,
            'custom_joining_fee':
                double.tryParse(
                  controllerFor('custom_joining_fee', '0').text,
                ) ??
                0,
            'joining_fee_waived': _joiningFeeWaived,
            'partial_month_fee':
                double.tryParse(controllerFor('partial_month_fee', '0').text) ??
                0,
            'pt_custom_fee':
                double.tryParse(controllerFor('pt_custom_fee', '0').text) ?? 0,
            'custom_fee_reason': controllerFor('custom_fee_reason').text,
          };
          if (selectedMemberId != null) {
            await widget.repository.assignMembershipForMember(
              selectedMemberId,
              payload,
            );
          } else {
            await widget.repository.assignMembership(payload);
          }
          break;
        case _AdminFormType.customFee:
          final memberId = (widget.prefill['member_id'] as num?)?.toInt();
          final customFeePayload = {
            'member_membership_id': int.tryParse(
              controllerFor('member_membership_id').text,
            ),
            'custom_fee_enabled': _customFeeEnabled,
            'custom_fee_amount':
                double.tryParse(controllerFor('custom_fee_amount', '0').text) ??
                0,
            'discount_type': _discountType,
            'discount_amount':
                double.tryParse(controllerFor('discount_amount', '0').text) ??
                0,
            'custom_joining_fee':
                double.tryParse(
                  controllerFor('custom_joining_fee', '0').text,
                ) ??
                0,
            'joining_fee_waived': _joiningFeeWaived,
            'partial_month_fee':
                double.tryParse(controllerFor('partial_month_fee', '0').text) ??
                0,
            'pt_custom_fee':
                double.tryParse(controllerFor('pt_custom_fee', '0').text) ?? 0,
            'amount_paid':
                double.tryParse(controllerFor('amount_paid', '0').text) ?? 0,
            'due_date': controllerFor('due_date').text,
            'custom_fee_reason': controllerFor('custom_fee_reason').text,
          };
          if (memberId != null) {
            await widget.repository.updateMemberCustomFee(
              memberId,
              customFeePayload,
            );
          } else {
            await widget.repository.updateCustomFee(
              int.tryParse(controllerFor('member_membership_id').text) ?? 0,
              customFeePayload,
            );
          }
          break;
        case _AdminFormType.payment:
          final membershipId = int.tryParse(
            controllerFor('member_membership_id').text,
          );
          final paymentAmount =
              double.tryParse(controllerFor('amount').text.trim()) ?? 0;
          if (membershipId == null || membershipId <= 0) {
            throw Exception(
              'Select an active membership before collecting payment.',
            );
          }
          if (paymentAmount <= 0) {
            throw Exception('Enter a payment amount greater than zero.');
          }
          await widget.repository.collectGymPayment({
            'member_membership_id': membershipId,
            'member_id': int.tryParse(controllerFor('member_id').text.trim()),
            'amount': paymentAmount,
            'payment_mode': controllerFor('payment_mode', 'cash').text,
            'notes': controllerFor('notes').text.trim(),
            'payment_date': controllerFor(
              'payment_date',
              DateFormat('yyyy-MM-dd').format(DateTime.now()),
            ).text,
          });
          break;
        case _AdminFormType.manualAttendance:
          await widget.repository.manualAttendance({
            'gym_id': int.tryParse(controllerFor('gym_id').text),
            'branch_id': int.tryParse(controllerFor('branch_id').text),
            'member_id': int.tryParse(controllerFor('member_id').text),
            'checked_in_at': controllerFor(
              'checked_in_at',
              DateTime.now().toIso8601String(),
            ).text,
            'notes': controllerFor('notes').text,
            'source_device': 'flutter_admin_app',
          });
          break;
        case _AdminFormType.announcement:
          await widget.repository.publishAnnouncement(widget.userRole, {
            'title': controllerFor('title').text,
            'message': controllerFor('message').text,
            'audience_type': controllerFor(
              'audience_type',
              widget.userRole == 'platform_admin'
                  ? 'platform_wide'
                  : 'gym_wide',
            ).text,
            'gym_id': int.tryParse(controllerFor('gym_id').text),
            'branch_id': int.tryParse(controllerFor('branch_id').text),
            'member_ids': controllerFor('member_ids').text
                .split(',')
                .map((item) => int.tryParse(item.trim()))
                .whereType<int>()
                .toList(),
          });
          break;
        case _AdminFormType.attendance:
          await widget.repository.scanAttendance({
            'gym_id': int.tryParse(controllerFor('gym_id').text),
            'branch_id': int.tryParse(controllerFor('branch_id').text),
            'qr_payload': controllerFor('qr_payload').text,
            'notes': controllerFor('notes').text,
            'source_device': 'flutter_admin_app',
          });
          break;
        case _AdminFormType.publicListing:
          await widget.repository.updateGymPublicListingSettings({
            'public_listing_enabled': _publicListingEnabled,
            'show_pricing': _showPricing,
            'pricing_visible': _showPricing,
            'trial_available': _trialAvailable,
            'contact_visible': _contactVisible,
          });
          break;
      }

      if (!mounted) {
        return;
      }
      await _showSuccessCelebration();
      if (!mounted) {
        return;
      }
      Navigator.of(context).pop();
      if (savedPlatformGym != null && savedPlatformGym['id'] != null) {
        if (!widget.parentContext.mounted) {
          return;
        }
        await showModalBottomSheet<void>(
          context: widget.parentContext,
          isScrollControlled: true,
          builder: (context) => FutureBuilder<Map<String, dynamic>>(
            future: widget.repository.fetchPlatformGymDetail(
              (savedPlatformGym!['id'] as num).toInt(),
            ),
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const SafeArea(
                  child: SizedBox(
                    height: 360,
                    child: LoadingState(label: 'Loading gym detail...'),
                  ),
                );
              }
              if (snapshot.hasError) {
                return SafeArea(
                  child: SizedBox(
                    height: 360,
                    child: ErrorState(
                      message: snapshot.error.toString(),
                      onRetry: () => Navigator.of(context).pop(),
                    ),
                  ),
                );
              }
              return _PlatformGymDetailSheet(
                gym: snapshot.data ?? savedPlatformGym!,
              );
            },
          ),
        );
      }
    } catch (exception) {
      if (mounted) {
        setState(() => _submitError = exception.toString());
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Map<String, dynamic> _platformGymPayload() {
    final payload = <String, dynamic>{
      'name': controllerFor('name').text.trim(),
      'description': controllerFor('description').text.trim(),
      'address': controllerFor('address').text.trim(),
      'city': controllerFor('city').text.trim(),
      'state': controllerFor('state').text.trim(),
      'pincode': controllerFor('pincode').text.trim(),
      'latitude': double.tryParse(controllerFor('latitude').text.trim()),
      'longitude': double.tryParse(controllerFor('longitude').text.trim()),
      'opening_time': controllerFor('opening_time').text.trim(),
      'closing_time': controllerFor('closing_time').text.trim(),
      'weekly_off': _selectedWeeklyOff.toList(),
      'facility_ids': _selectedFacilityIds.toList(),
      'public_listing_enabled': _publicListingEnabled,
      'show_pricing': _showPricing,
      'trial_available': _trialAvailable,
      'contact_visible': _contactVisible,
      'status': controllerFor(
        'status',
        widget.prefill['status']?.toString() ?? 'pending',
      ).text,
    };

    if (_ownerMode == 'existing') {
      payload['owner_user_id'] = _selectedOwnerId;
    } else {
      payload['owner_name'] = controllerFor('owner_name').text.trim();
      payload['owner_email'] = controllerFor('owner_email').text.trim();
    }

    if ((widget.prefill['id'] as num?) == null) {
      payload['create_default_branch'] = _createDefaultBranch;
      payload['branch_same_as_gym'] = _branchSameAsGym;
      payload['branch_name'] = controllerFor('branch_name').text.trim();
      payload['branch_address'] = controllerFor('branch_address').text.trim();
      payload['branch_city'] = controllerFor('branch_city').text.trim();
      payload['branch_state'] = controllerFor('branch_state').text.trim();
      payload['branch_pincode'] = controllerFor('branch_pincode').text.trim();
      payload['branch_latitude'] = double.tryParse(
        controllerFor('branch_latitude').text.trim(),
      );
      payload['branch_longitude'] = double.tryParse(
        controllerFor('branch_longitude').text.trim(),
      );
      payload['branch_opening_time'] = controllerFor(
        'branch_opening_time',
      ).text.trim();
      payload['branch_closing_time'] = controllerFor(
        'branch_closing_time',
      ).text.trim();
      payload['branch_weekly_off'] = _selectedBranchWeeklyOff.toList();
    } else if (_selectedOwnerId != null) {
      payload['owner_user_id'] = _selectedOwnerId;
    }

    payload.removeWhere((key, value) => value == null);
    return payload;
  }

  Future<void> _showSuccessCelebration() {
    String title = 'Saved successfully';
    IconData icon = Icons.check_circle_rounded;

    if (widget.type == _AdminFormType.payment) {
      title = 'Payment collected';
      icon = Icons.payments_rounded;
    } else if (widget.type == _AdminFormType.platformGym) {
      title = (widget.prefill['id'] as num?) == null
          ? 'Gym created'
          : 'Gym updated';
      icon = Icons.add_business_rounded;
    } else if (widget.type == _AdminFormType.platformGymOwner) {
      title = 'Gym owner created';
      icon = Icons.badge_rounded;
    } else if (widget.type == _AdminFormType.platformFacility) {
      title = (widget.prefill['id'] as num?) == null
          ? 'Facility created'
          : 'Facility updated';
      icon = Icons.spa_rounded;
    } else if (widget.type == _AdminFormType.membershipAssign) {
      title = 'Membership assigned';
      icon = Icons.workspace_premium_rounded;
    } else if (widget.type == _AdminFormType.member &&
        controllerFor('assigned_trainer_user_id').text.trim().isNotEmpty) {
      title = 'Trainer assigned';
      icon = Icons.person_add_alt_1_rounded;
    } else if (widget.type == _AdminFormType.customFee) {
      title = 'Pricing updated';
      icon = Icons.tune_rounded;
    } else if (widget.type == _AdminFormType.attendance ||
        widget.type == _AdminFormType.manualAttendance) {
      title = 'Attendance marked';
      icon = Icons.qr_code_scanner_rounded;
    }

    Future<void>.delayed(const Duration(milliseconds: 700), () {
      if (mounted && Navigator.of(context, rootNavigator: true).canPop()) {
        Navigator.of(context, rootNavigator: true).pop();
      }
    });

    return showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: title,
      pageBuilder: (context, _, __) => const SizedBox.shrink(),
      transitionDuration: const Duration(milliseconds: 240),
      transitionBuilder: (context, animation, _, __) {
        return FadeTransition(
          opacity: animation,
          child: ScaleTransition(
            scale: CurvedAnimation(
              parent: animation,
              curve: Curves.easeOutBack,
            ),
            child: Center(
              child: PremiumCard(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    PulseGlow(
                      pulseScale: 1.07,
                      glowColor: Theme.of(context).colorScheme.secondary,
                      child: Icon(
                        icon,
                        size: 56,
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      title,
                      style: Theme.of(context).textTheme.headlineSmall,
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final plan = Map<String, dynamic>.from(
      _membershipDetail['membership_plan'] as Map? ?? const {},
    );

    return FitModalSurface(
      title: _titleFor(widget.type),
      subtitle: 'Complete the required fields and save the admin change.',
      icon: Icons.tune_rounded,
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (_submitError != null) ...[
                ErrorState(
                  message: _submitError!,
                  onRetry: widget.type == _AdminFormType.platformGym
                      ? () {
                          _loadPlatformGymOptions();
                        }
                      : () => setState(() => _submitError = null),
                ),
                const SizedBox(height: 16),
              ],
              if (widget.type == _AdminFormType.customFee &&
                  !_canEditCustomFee) ...[
                PremiumCard(
                  child: Row(
                    children: [
                      const Icon(
                        Icons.lock_outline_rounded,
                        color: AppColors.warning,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'You can review member custom pricing, but editing is disabled for the current role.',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],
              if (widget.type == _AdminFormType.customFee ||
                  widget.type == _AdminFormType.membershipAssign ||
                  widget.type == _AdminFormType.payment) ...[
                _buildAnimatedSummaryCard(context),
                const SizedBox(height: 16),
                if (_membershipDetail.isNotEmpty) ...[
                  _MembershipOverviewCard(
                    membershipDetail: _membershipDetail,
                    planName: plan['name']?.toString() ?? 'Membership Plan',
                    loading: _detailLoading,
                  ),
                  const SizedBox(height: 16),
                ],
              ],
              ...(widget.type == _AdminFormType.customFee
                  ? [
                      IgnorePointer(
                        ignoring: !_canEditCustomFee,
                        child: Opacity(
                          opacity: _canEditCustomFee ? 1 : 0.72,
                          child: Column(children: _fieldsFor(widget.type)),
                        ),
                      ),
                    ]
                  : (widget.type == _AdminFormType.platformGym
                        ? _platformGymFields(context)
                        : _fieldsFor(widget.type))),
              if (widget.type == _AdminFormType.customFee &&
                  _customFeeAudits.isNotEmpty) ...[
                const SizedBox(height: 20),
                _SectionHeader(
                  title: 'Custom Fee Audit Timeline',
                  subtitle:
                      'Every pricing change remains visible to operations.',
                ),
                const SizedBox(height: 12),
                _TimelineCard(
                  items: _customFeeAudits.map((item) {
                    final changedBy = Map<String, dynamic>.from(
                      item['changed_by_user'] as Map? ?? const {},
                    );
                    return _TimelineEntry(
                      title: changedBy['name']?.toString() ?? 'Pricing change',
                      subtitle:
                          item['reason']?.toString() ?? 'Reason not provided',
                      valueLabel: 'Changed',
                      value: _formatDateTime(item['changed_at']),
                    );
                  }).toList(),
                ),
              ],
              if ((widget.type == _AdminFormType.customFee ||
                      widget.type == _AdminFormType.payment ||
                      widget.type == _AdminFormType.membershipAssign) &&
                  _membershipActivityTimeline.isNotEmpty) ...[
                const SizedBox(height: 20),
                _SectionHeader(
                  title: 'Trust Timeline',
                  subtitle:
                      'See who changed billing, pricing, or payment state and when.',
                ),
                const SizedBox(height: 12),
                _TimelineCard(
                  items: _membershipActivityTimeline.map((item) {
                    return _TimelineEntry(
                      title: item['title']?.toString() ?? 'Audit event',
                      subtitle:
                          '${item['changed_by'] ?? 'System'} • ${item['reason'] ?? 'No reason provided'}',
                      valueLabel: 'When',
                      value: item['date']?.toString() ?? '--',
                    );
                  }).toList(),
                ),
              ],
              if (widget.type == _AdminFormType.payment &&
                  _paymentHistory.isNotEmpty) ...[
                const SizedBox(height: 20),
                _SectionHeader(
                  title: 'Payment History Timeline',
                  subtitle: 'Track every collection against this membership.',
                ),
                const SizedBox(height: 12),
                _TimelineCard(
                  items: _paymentHistory.map((item) {
                    return _TimelineEntry(
                      title:
                          '${item['payment_mode']?.toString().toUpperCase() ?? 'PAYMENT'} • ${item['status']?.toString().toUpperCase() ?? 'RECORDED'}',
                      subtitle:
                          item['notes']?.toString() ??
                          'No receipt notes added.',
                      valueLabel: 'Amount',
                      value: _formatCurrency(item['amount']),
                    );
                  }).toList(),
                ),
              ],
              const SizedBox(height: 24),
              AppPrimaryButton(
                label: _busy ? 'Saving...' : 'Save',
                loading: _busy,
                onPressed:
                    _busy ||
                        (widget.type == _AdminFormType.customFee &&
                            !_canEditCustomFee)
                    ? null
                    : _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _titleFor(_AdminFormType type) {
    switch (type) {
      case _AdminFormType.platformGym:
        return (widget.prefill['id'] as num?) == null
            ? 'Add Platform Gym'
            : 'Edit Platform Gym';
      case _AdminFormType.platformGymOwner:
        return 'Create Gym Owner';
      case _AdminFormType.platformFacility:
        return (widget.prefill['id'] as num?) == null
            ? 'Create Facility'
            : 'Edit Facility';
      case _AdminFormType.platformReports:
        return 'Platform Reports';
      case _AdminFormType.gymProfile:
        return 'Gym Profile Setup';
      case _AdminFormType.branch:
        return 'Branch Management';
      case _AdminFormType.staff:
        return 'Staff Management';
      case _AdminFormType.trainer:
        return 'Trainer Management';
      case _AdminFormType.member:
        return 'Member Registration';
      case _AdminFormType.plan:
        return 'Membership Plan Management';
      case _AdminFormType.membershipAssign:
        return 'Assign Membership';
      case _AdminFormType.customFee:
        return 'Custom Member Fee';
      case _AdminFormType.payment:
        return 'Payment Collection';
      case _AdminFormType.attendance:
        return 'Attendance QR Scanner';
      case _AdminFormType.manualAttendance:
        return 'Manual Attendance';
      case _AdminFormType.announcement:
        return 'Announcements';
      case _AdminFormType.publicListing:
        return 'Public Listing Settings';
    }
  }

  List<Widget> _fieldsFor(_AdminFormType type) {
    final commonSpacing = <Widget>[const SizedBox(height: 14)];

    List<Widget> textFields(List<Widget> fields) =>
        fields.expand((field) => <Widget>[field, ...commonSpacing]).toList();

    switch (type) {
      case _AdminFormType.platformGym:
        return const [];
      case _AdminFormType.platformGymOwner:
        return textFields([
          _field('Name', 'name'),
          _field('Email', 'email'),
          _field('Phone', 'phone', required: false),
        ]);
      case _AdminFormType.platformFacility:
        return [
          ...textFields([
            _field('Facility name', 'name'),
            _field('Icon', 'icon', required: false),
          ]),
          DropdownButtonFormField<String>(
            initialValue: controllerFor(
              'status',
              widget.prefill['status']?.toString() ?? 'active',
            ).text,
            decoration: const InputDecoration(labelText: 'Status'),
            items: const [
              DropdownMenuItem(value: 'active', child: Text('Active')),
              DropdownMenuItem(value: 'inactive', child: Text('Inactive')),
            ],
            onChanged: (value) {
              if (value != null) {
                controllerFor('status').text = value;
              }
            },
          ),
          const SizedBox(height: 14),
          Text(
            'Delete stays available only when the backend confirms this facility is not used by any gyms or branches.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ];
      case _AdminFormType.platformReports:
        return const [];
      case _AdminFormType.customFee:
        return [
          if (_customFeeMemberName != null)
            _formSection(
              context,
              title: 'Member Pricing Context',
              subtitle:
                  'Review the active membership snapshot before applying a custom fee override.',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _customFeeMemberName!,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (_memberCustomFeeMemberships.isNotEmpty)
                    DropdownButtonFormField<String>(
                      initialValue:
                          controllerFor('member_membership_id').text.isEmpty
                          ? null
                          : controllerFor('member_membership_id').text,
                      decoration: const InputDecoration(
                        labelText: 'Selected membership',
                      ),
                      items: _memberCustomFeeMemberships
                          .map(
                            (membership) => DropdownMenuItem<String>(
                              value: membership['id']?.toString(),
                              child: Text(
                                '${_recordMap(membership['membership_plan'])['name'] ?? 'Plan'} • ${membership['status'] ?? '--'}',
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: (value) async {
                        controllerFor('member_membership_id').text =
                            value ?? '';
                        final membershipId = int.tryParse(value ?? '');
                        if (membershipId != null) {
                          await _loadMembershipSupportData(membershipId);
                        }
                      },
                    )
                  else
                    const EmptyState(
                      title: 'No memberships available',
                      message:
                          'A member needs a membership before custom pricing can be reviewed.',
                      icon: Icons.workspace_premium_outlined,
                    ),
                ],
              ),
            ),
          if (_customFeeMemberName != null) const SizedBox(height: 16),
          _field(
            'Default plan price',
            'default_plan_price',
            required: widget.type != _AdminFormType.membershipAssign,
          ),
          _switchTile(
            'Enable custom fee',
            _customFeeEnabled,
            (value) => setState(() => _customFeeEnabled = value),
          ),
          _field('Custom amount', 'custom_fee_amount', required: false),
          DropdownButtonFormField<String>(
            initialValue: _discountType,
            items: const [
              DropdownMenuItem(value: 'none', child: Text('No discount')),
              DropdownMenuItem(value: 'fixed', child: Text('Fixed discount')),
              DropdownMenuItem(
                value: 'percentage',
                child: Text('Percentage discount'),
              ),
            ],
            onChanged: (value) =>
                setState(() => _discountType = value ?? 'none'),
            decoration: const InputDecoration(labelText: 'Discount type'),
          ),
          const SizedBox(height: 14),
          _field('Discount amount', 'discount_amount', required: false),
          _switchTile(
            'Joining fee waived',
            _joiningFeeWaived,
            (value) => setState(() => _joiningFeeWaived = value),
          ),
          _field('Custom joining fee', 'custom_joining_fee', required: false),
          _field('Partial month fee', 'partial_month_fee', required: false),
          _field('PT custom trainer fee', 'pt_custom_fee', required: false),
          _field(
            'Final payable',
            'final_payable_amount',
            required: widget.type != _AdminFormType.membershipAssign,
          ),
          _field('Paid amount', 'amount_paid', required: false),
          _field(
            'Due amount',
            'due_amount',
            required: widget.type != _AdminFormType.membershipAssign,
          ),
          _field('Due date (YYYY-MM-DD)', 'due_date', required: false),
          _field('Reason', 'custom_fee_reason', required: _customFeeEnabled),
          if (widget.type != _AdminFormType.membershipAssign &&
              _customFeeMemberName == null)
            _field(
              'Audit history reference membership id',
              'member_membership_id',
            ),
        ];
      case _AdminFormType.payment:
        final availableMemberships = _availablePaymentMemberships();
        final canCollectPayment =
            widget.appUser.activeRole == 'gym_owner' ||
            widget.appUser.activeRole == 'branch_manager' ||
            widget.appUser.hasAnyPermission(['collect_payment']);
        return [
          _formSection(
            context,
            title: 'Collect Payment',
            subtitle:
                'Select the member and active membership, then record the incoming amount against the current due.',
            child: Column(
              children: [
                DropdownButtonFormField<String>(
                  initialValue: controllerFor('member_id').text.isEmpty
                      ? null
                      : controllerFor('member_id').text,
                  decoration: const InputDecoration(labelText: 'Member'),
                  items: _gymMembers
                      .map(
                        (member) => DropdownMenuItem<String>(
                          value: member['id']?.toString(),
                          child: Text(member['name']?.toString() ?? 'Member'),
                        ),
                      )
                      .toList(),
                  onChanged: canCollectPayment
                      ? (value) async {
                          controllerFor('member_id').text = value ?? '';
                          controllerFor('member_membership_id').text = '';
                          setState(() {});
                          final selectedMemberId = int.tryParse(value ?? '');
                          if (selectedMemberId == null) {
                            return;
                          }
                          final membership = _availablePaymentMemberships()
                              .cast<Map<String, dynamic>?>()
                              .firstWhere(
                                (record) =>
                                    (record?['member_id'] as num?)?.toInt() ==
                                    selectedMemberId,
                                orElse: () => null,
                              );
                          final membershipId = (membership?['id'] as num?)
                              ?.toInt();
                          if (membershipId != null) {
                            controllerFor('member_membership_id').text =
                                membershipId.toString();
                            await _loadMembershipSupportData(membershipId);
                            if (mounted) {
                              setState(() {});
                            }
                          }
                        }
                      : null,
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue:
                      controllerFor('member_membership_id').text.isEmpty
                      ? null
                      : controllerFor('member_membership_id').text,
                  decoration: const InputDecoration(
                    labelText: 'Active membership',
                  ),
                  items: availableMemberships
                      .map(
                        (membership) => DropdownMenuItem<String>(
                          value: membership['id']?.toString(),
                          child: Text(
                            '${_recordMap(membership['member'])['name'] ?? 'Member'} • ${_recordMap(membership['membership_plan'])['name'] ?? 'Plan'}',
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: canCollectPayment
                      ? (value) async {
                          controllerFor('member_membership_id').text =
                              value ?? '';
                          final selectedMembershipId = int.tryParse(
                            value ?? '',
                          );
                          if (selectedMembershipId == null) {
                            return;
                          }
                          final selectedMembership = availableMemberships
                              .cast<Map<String, dynamic>?>()
                              .firstWhere(
                                (record) =>
                                    (record?['id'] as num?)?.toInt() ==
                                    selectedMembershipId,
                                orElse: () => null,
                              );
                          final memberId =
                              (selectedMembership?['member_id'] as num?)
                                  ?.toInt();
                          if (memberId != null) {
                            controllerFor('member_id').text = memberId
                                .toString();
                          }
                          await _loadMembershipSupportData(
                            selectedMembershipId,
                          );
                          if (mounted) {
                            setState(() {});
                          }
                        }
                      : null,
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(child: _field('Amount', 'amount')),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: controllerFor(
                          'payment_mode',
                          'cash',
                        ).text,
                        decoration: const InputDecoration(
                          labelText: 'Payment mode',
                        ),
                        items: const ['cash', 'upi', 'card', 'bank']
                            .map(
                              (mode) => DropdownMenuItem<String>(
                                value: mode,
                                child: Text(mode.toUpperCase()),
                              ),
                            )
                            .toList(),
                        onChanged: canCollectPayment
                            ? (value) {
                                if (value != null) {
                                  controllerFor('payment_mode').text = value;
                                  setState(() {});
                                }
                              }
                            : null,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _field(
                  'Payment date (YYYY-MM-DD)',
                  'payment_date',
                  initialValue: DateFormat('yyyy-MM-dd').format(DateTime.now()),
                ),
                const SizedBox(height: 14),
                _field('Notes', 'notes', required: false),
                if (!canCollectPayment) ...[
                  const SizedBox(height: 14),
                  const EmptyState(
                    title: 'Collection disabled',
                    message:
                        'You can review billing, but the collect_payment permission is required to record a payment.',
                    icon: Icons.lock_outline_rounded,
                  ),
                ],
              ],
            ),
          ),
        ];
      case _AdminFormType.membershipAssign:
        final selectedPlan = _selectedMembershipPlanRecord();
        final computedExpiryDate = _computedMembershipExpiryDate();
        controllerFor('expiry_date').text = computedExpiryDate == '--'
            ? ''
            : computedExpiryDate;
        if (selectedPlan != null) {
          controllerFor(
            'default_plan_price',
            selectedPlan['plan_price']?.toString() ?? '0',
          ).text = selectedPlan['plan_price']?.toString() ?? '0';
          controllerFor(
            'default_joining_fee',
            selectedPlan['joining_fee']?.toString() ?? '0',
          ).text = selectedPlan['joining_fee']?.toString() ?? '0';
          final basePrice = _toDouble(selectedPlan['plan_price']);
          final joiningFee = _toDouble(selectedPlan['joining_fee']);
          final customAmount =
              double.tryParse(
                controllerFor('custom_fee_amount', '0').text.trim(),
              ) ??
              0;
          final discountAmount =
              double.tryParse(
                controllerFor('discount_amount', '0').text.trim(),
              ) ??
              0;
          final customJoiningFee =
              double.tryParse(
                controllerFor('custom_joining_fee', '0').text.trim(),
              ) ??
              0;
          final partialMonthFee =
              double.tryParse(
                controllerFor('partial_month_fee', '0').text.trim(),
              ) ??
              0;
          final ptCustomFee =
              double.tryParse(
                controllerFor('pt_custom_fee', '0').text.trim(),
              ) ??
              0;
          final paidAmount =
              double.tryParse(controllerFor('amount_paid', '0').text.trim()) ??
              0;
          final effectivePlanPrice = _customFeeEnabled
              ? customAmount
              : basePrice;
          final effectiveJoiningFee = _joiningFeeWaived
              ? 0
              : (customJoiningFee > 0 ? customJoiningFee : joiningFee);
          final finalPayable =
              (effectivePlanPrice +
                      effectiveJoiningFee +
                      partialMonthFee +
                      ptCustomFee -
                      discountAmount)
                  .clamp(0, double.infinity);
          controllerFor(
            'final_payable_amount',
            finalPayable.toStringAsFixed(2),
          ).text = finalPayable.toStringAsFixed(
            2,
          );
          controllerFor(
            'due_amount',
            (finalPayable - paidAmount)
                .clamp(0, double.infinity)
                .toStringAsFixed(2),
          ).text = (finalPayable - paidAmount)
              .clamp(0, double.infinity)
              .toStringAsFixed(2);
        }
        return [
          _formSection(
            context,
            title: 'Membership Assignment',
            subtitle:
                'Select the member, plan, and dates before applying pricing or billing adjustments.',
            child: Column(
              children: [
                DropdownButtonFormField<String>(
                  initialValue: controllerFor('branch_id').text.isEmpty
                      ? null
                      : controllerFor('branch_id').text,
                  decoration: const InputDecoration(labelText: 'Branch'),
                  items: _gymBranches
                      .map(
                        (branch) => DropdownMenuItem<String>(
                          value: branch['id']?.toString(),
                          child: Text(branch['name']?.toString() ?? 'Branch'),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      controllerFor('branch_id').text = value ?? '',
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: controllerFor('member_id').text.isEmpty
                      ? null
                      : controllerFor('member_id').text,
                  decoration: const InputDecoration(labelText: 'Member'),
                  items: _gymMembers
                      .map(
                        (member) => DropdownMenuItem<String>(
                          value: member['id']?.toString(),
                          child: Text(member['name']?.toString() ?? 'Member'),
                        ),
                      )
                      .toList(),
                  onChanged: (value) {
                    controllerFor('member_id').text = value ?? '';
                    Map<String, dynamic>? selectedMember;
                    for (final member in _gymMembers) {
                      if (member['id']?.toString() == value) {
                        selectedMember = member;
                        break;
                      }
                    }
                    final memberProfile = _recordMap(
                      selectedMember?['member_profile'],
                    );
                    if (memberProfile['branch_id'] != null) {
                      controllerFor('branch_id').text =
                          memberProfile['branch_id'].toString();
                    }
                    setState(() {});
                  },
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: controllerFor('membership_plan_id').text.isEmpty
                      ? null
                      : controllerFor('membership_plan_id').text,
                  decoration: const InputDecoration(
                    labelText: 'Membership Plan',
                  ),
                  items: _gymMembershipPlans
                      .map(
                        (plan) => DropdownMenuItem<String>(
                          value: plan['id']?.toString(),
                          child: Text(plan['name']?.toString() ?? 'Plan'),
                        ),
                      )
                      .toList(),
                  onChanged: (value) {
                    controllerFor('membership_plan_id').text = value ?? '';
                    setState(() {});
                  },
                ),
                if (selectedPlan != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: AppColors.surface.withValues(alpha: 0.55),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: AppColors.strokeStrong),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          selectedPlan['name']?.toString() ?? 'Plan preview',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '${selectedPlan['duration_days'] ?? '--'} days • ${_formatCurrency(selectedPlan['plan_price'])} + joining ${_formatCurrency(selectedPlan['joining_fee'])}',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Start date (YYYY-MM-DD)',
                        'start_date',
                        initialValue: DateFormat(
                          'yyyy-MM-dd',
                        ).format(DateTime.now()),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: controllerFor('expiry_date'),
                        enabled: false,
                        decoration: const InputDecoration(
                          labelText: 'Expiry date',
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Paid amount',
                        'amount_paid',
                        required: false,
                        initialValue: '0',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        'Due date (YYYY-MM-DD)',
                        'due_date',
                        required: false,
                        initialValue: DateFormat(
                          'yyyy-MM-dd',
                        ).format(DateTime.now()),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: controllerFor('status', 'active').text,
                  decoration: const InputDecoration(labelText: 'Status'),
                  items: const ['active', 'expired', 'frozen', 'cancelled']
                      .map(
                        (status) => DropdownMenuItem<String>(
                          value: status,
                          child: Text(_dashboardTitleCase(status)),
                        ),
                      )
                      .toList(),
                  onChanged: (value) {
                    if (value != null) {
                      controllerFor('status').text = value;
                    }
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          ..._fieldsFor(_AdminFormType.customFee),
        ];
      case _AdminFormType.plan:
        return [
          _formSection(
            context,
            title: 'Plan Configuration',
            subtitle:
                'Define the membership duration, pricing, onboarding fee, and PT access.',
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(child: _field('Plan name', 'name')),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: controllerFor('branch_id').text.isEmpty
                            ? null
                            : controllerFor('branch_id').text,
                        decoration: const InputDecoration(labelText: 'Branch'),
                        items: [
                          const DropdownMenuItem<String>(
                            value: '',
                            child: Text('Gym-wide plan'),
                          ),
                          ..._gymBranches.map(
                            (branch) => DropdownMenuItem<String>(
                              value: branch['id']?.toString(),
                              child: Text(
                                branch['name']?.toString() ?? 'Branch',
                              ),
                            ),
                          ),
                        ],
                        onChanged: (value) =>
                            controllerFor('branch_id').text = value ?? '',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(child: _field('Duration days', 'duration_days')),
                    const SizedBox(width: 12),
                    Expanded(child: _field('Plan price', 'plan_price')),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(child: _field('Joining fee', 'joining_fee')),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: controllerFor('status', 'active').text,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: const ['active', 'inactive']
                            .map(
                              (status) => DropdownMenuItem<String>(
                                value: status,
                                child: Text(_dashboardTitleCase(status)),
                              ),
                            )
                            .toList(),
                        onChanged: (value) {
                          if (value != null) {
                            controllerFor('status').text = value;
                          }
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _toggleSettingCard(
                  context,
                  title: 'PT included',
                  subtitle:
                      'Include personal training access in this membership plan.',
                  value: controllerFor('pt_included', 'false').text == 'true',
                  onChanged: (value) => setState(
                    () => controllerFor('pt_included').text = value
                        ? 'true'
                        : 'false',
                  ),
                  icon: Icons.fitness_center_rounded,
                ),
                const SizedBox(height: 14),
                _field('Description', 'description', required: false),
              ],
            ),
          ),
        ];
      case _AdminFormType.gymProfile:
        return [
          _formSection(
            context,
            title: 'Brand and Identity',
            subtitle:
                'Update the public brand surface used across the gym workspace and listing.',
            child: Column(
              children: [
                if (controllerFor('cover_image_url').text.trim().isNotEmpty)
                  _imagePreviewCard(
                    label: 'Cover preview',
                    imageUrl: controllerFor('cover_image_url').text.trim(),
                    icon: Icons.image_rounded,
                  ),
                if (controllerFor('cover_image_url').text.trim().isNotEmpty)
                  const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(child: _field('Gym name', 'name')),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        'Timezone',
                        'timezone',
                        required: false,
                        initialValue: 'Asia/Kolkata',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _field('Description', 'description', required: false),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field('Logo URL', 'logo_url', required: false),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        'Cover image URL',
                        'cover_image_url',
                        required: false,
                      ),
                    ),
                  ],
                ),
                if (controllerFor('logo_url').text.trim().isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _imagePreviewCard(
                    label: 'Logo preview',
                    imageUrl: controllerFor('logo_url').text.trim(),
                    icon: Icons.storefront_rounded,
                    compact: true,
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          _formSection(
            context,
            title: 'Location and Timings',
            subtitle:
                'Address, city coverage, and operating hours for discovery and ops surfaces.',
            child: Column(
              children: [
                _field('Address line', 'address_line', required: false),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(child: _field('City', 'city')),
                    const SizedBox(width: 12),
                    Expanded(child: _field('State', 'state', required: false)),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Country',
                        'country',
                        required: false,
                        initialValue: 'India',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field('Pincode', 'pincode', required: false),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field('Latitude', 'latitude', required: false),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field('Longitude', 'longitude', required: false),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Opening time (HH:MM)',
                        'opening_time',
                        required: false,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        'Closing time (HH:MM)',
                        'closing_time',
                        required: false,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _multiChipSelector(
                  context,
                  title: 'Weekly off',
                  values: _selectedWeeklyOff,
                  options: _weekdayOptions,
                  onToggle: (day) {
                    setState(() {
                      _selectedWeeklyOff.contains(day)
                          ? _selectedWeeklyOff.remove(day)
                          : _selectedWeeklyOff.add(day);
                    });
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _formSection(
            context,
            title: 'Facilities',
            subtitle:
                'Choose the facility chips that should appear across gym and branch surfaces.',
            child: _optionsLoading && _platformFacilities.isEmpty
                ? const LoadingState(label: 'Loading facilities...')
                : _multiChipSelector(
                    context,
                    title: 'Available facilities',
                    values: _selectedFacilityIds
                        .map((entry) => entry.toString())
                        .toSet(),
                    options: _platformFacilities
                        .map(
                          (facility) =>
                              '${facility['id']}::${facility['name']}',
                        )
                        .toList(),
                    optionLabel: (raw) => raw.split('::').last,
                    onToggle: (raw) {
                      final id = int.tryParse(raw.split('::').first);
                      if (id == null) {
                        return;
                      }
                      setState(() {
                        _selectedFacilityIds.contains(id)
                            ? _selectedFacilityIds.remove(id)
                            : _selectedFacilityIds.add(id);
                      });
                    },
                  ),
          ),
        ];
      case _AdminFormType.branch:
        return [
          _formSection(
            context,
            title: 'Branch Basics',
            subtitle:
                'Configure the branch identity, address, and timing setup for this location.',
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(child: _field('Branch name', 'name')),
                    const SizedBox(width: 12),
                    Expanded(child: _field('Slug', 'slug', required: false)),
                  ],
                ),
                const SizedBox(height: 14),
                _field('Address line', 'address_line', required: false),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(child: _field('City', 'city', required: false)),
                    const SizedBox(width: 12),
                    Expanded(child: _field('State', 'state', required: false)),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Country',
                        'country',
                        required: false,
                        initialValue: 'India',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field('Pincode', 'pincode', required: false),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field('Latitude', 'latitude', required: false),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field('Longitude', 'longitude', required: false),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Timezone',
                        'timezone',
                        required: false,
                        initialValue: 'Asia/Kolkata',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: controllerFor(
                          'status',
                          widget.prefill['status']?.toString() ?? 'active',
                        ).text,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: const ['active', 'inactive']
                            .map(
                              (status) => DropdownMenuItem<String>(
                                value: status,
                                child: Text(_dashboardTitleCase(status)),
                              ),
                            )
                            .toList(),
                        onChanged: (value) {
                          if (value != null) {
                            controllerFor('status').text = value;
                          }
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Opening time (HH:MM)',
                        'opening_time',
                        required: false,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        'Closing time (HH:MM)',
                        'closing_time',
                        required: false,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _multiChipSelector(
                  context,
                  title: 'Weekly off',
                  values: _selectedWeeklyOff,
                  options: _weekdayOptions,
                  onToggle: (day) {
                    setState(() {
                      _selectedWeeklyOff.contains(day)
                          ? _selectedWeeklyOff.remove(day)
                          : _selectedWeeklyOff.add(day);
                    });
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _formSection(
            context,
            title: 'Facilities',
            subtitle:
                'Attach the branch-level facilities that members should see for this location.',
            child: _optionsLoading && _platformFacilities.isEmpty
                ? const LoadingState(label: 'Loading facilities...')
                : _multiChipSelector(
                    context,
                    title: 'Branch facilities',
                    values: _selectedFacilityIds
                        .map((entry) => entry.toString())
                        .toSet(),
                    options: _platformFacilities
                        .map(
                          (facility) =>
                              '${facility['id']}::${facility['name']}',
                        )
                        .toList(),
                    optionLabel: (raw) => raw.split('::').last,
                    onToggle: (raw) {
                      final id = int.tryParse(raw.split('::').first);
                      if (id == null) {
                        return;
                      }
                      setState(() {
                        _selectedFacilityIds.contains(id)
                            ? _selectedFacilityIds.remove(id)
                            : _selectedFacilityIds.add(id);
                      });
                    },
                  ),
          ),
        ];
      case _AdminFormType.staff:
        final isEditing = (widget.prefill['id'] as num?) != null;
        final availablePermissions = widget.userRole == 'gym_owner'
            ? _staffPermissionOptions
            : _staffPermissionOptions
                  .where(
                    (permission) =>
                        widget.appUser.hasAnyPermission([permission]),
                  )
                  .toList();
        return [
          _formSection(
            context,
            title: 'Identity',
            subtitle:
                'Use an existing user id or create a fresh branch manager or gym staff account.',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'new', label: Text('Create User')),
                    ButtonSegment(
                      value: 'existing',
                      label: Text('Existing User'),
                    ),
                  ],
                  selected: {_staffUserMode},
                  onSelectionChanged: isEditing
                      ? null
                      : (selection) =>
                            setState(() => _staffUserMode = selection.first),
                ),
                const SizedBox(height: 16),
                if (_staffUserMode == 'existing') ...[
                  _field('Existing user id', 'existing_user_id'),
                ] else ...[
                  _field('Name', 'name'),
                  const SizedBox(height: 14),
                  _field('Email', 'email'),
                  const SizedBox(height: 14),
                  _field('Phone', 'phone', required: false),
                  const SizedBox(height: 14),
                  _field('Password', 'password', required: !isEditing),
                  const SizedBox(height: 14),
                  _field(
                    'Confirm password',
                    'password_confirmation',
                    required: !isEditing,
                  ),
                ],
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: controllerFor('role', 'gym_staff').text,
                  decoration: const InputDecoration(labelText: 'Role'),
                  items:
                      (widget.userRole == 'gym_owner'
                              ? const ['branch_manager', 'gym_staff']
                              : const ['gym_staff'])
                          .map(
                            (role) => DropdownMenuItem<String>(
                              value: role,
                              child: Text(_dashboardTitleCase(role)),
                            ),
                          )
                          .toList(),
                  onChanged: (value) {
                    if (value != null) {
                      controllerFor('role').text = value;
                    }
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _formSection(
            context,
            title: 'Branch Scope',
            subtitle:
                'Select one or more branches this staff account should operate inside.',
            child: _optionsLoading && _gymBranches.isEmpty
                ? const LoadingState(label: 'Loading branches...')
                : _multiChipSelector(
                    context,
                    title: 'Accessible branches',
                    values: _selectedBranchIds
                        .map((entry) => entry.toString())
                        .toSet(),
                    options: _gymBranches
                        .map((branch) => '${branch['id']}::${branch['name']}')
                        .toList(),
                    optionLabel: (raw) => raw.split('::').last,
                    onToggle: (raw) {
                      final id = int.tryParse(raw.split('::').first);
                      if (id == null) {
                        return;
                      }
                      setState(() {
                        _selectedBranchIds.contains(id)
                            ? _selectedBranchIds.remove(id)
                            : _selectedBranchIds.add(id);
                      });
                    },
                  ),
          ),
          const SizedBox(height: 16),
          _formSection(
            context,
            title: 'Permissions',
            subtitle:
                'Only grant permissions you already hold. Unauthorized grants stay hidden in the UI and are blocked by the backend.',
            child: availablePermissions.isEmpty
                ? const EmptyState(
                    title: 'No grantable permissions',
                    message:
                        'This account cannot grant any custom staff permissions in the current scope.',
                    icon: Icons.lock_outline_rounded,
                  )
                : Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: availablePermissions.map((permission) {
                      final selected = _selectedStaffPermissions.contains(
                        permission,
                      );
                      return SizedBox(
                        width: 260,
                        child: _permissionToggleCard(
                          context,
                          label: _dashboardTitleCase(permission),
                          value: selected,
                          onChanged: (value) {
                            setState(() {
                              if (value) {
                                _selectedStaffPermissions.add(permission);
                              } else {
                                _selectedStaffPermissions.remove(permission);
                              }
                            });
                          },
                        ),
                      );
                    }).toList(),
                  ),
          ),
        ];
      case _AdminFormType.trainer:
        final isEditing = (widget.prefill['id'] as num?) != null;
        return [
          _formSection(
            context,
            title: 'Identity',
            subtitle:
                'Attach an existing user or create a new trainer account. The backend handles trainer role assignment.',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'new', label: Text('Create User')),
                    ButtonSegment(
                      value: 'existing',
                      label: Text('Existing User'),
                    ),
                  ],
                  selected: {_trainerUserMode},
                  onSelectionChanged: isEditing
                      ? null
                      : (selection) =>
                            setState(() => _trainerUserMode = selection.first),
                ),
                const SizedBox(height: 16),
                if (_trainerUserMode == 'existing') ...[
                  _field('Existing user id', 'existing_user_id'),
                ] else ...[
                  _field('Name', 'name'),
                  const SizedBox(height: 14),
                  _field('Email', 'email'),
                  const SizedBox(height: 14),
                  _field('Phone', 'phone', required: false),
                  const SizedBox(height: 14),
                  _field('Password', 'password', required: !isEditing),
                  const SizedBox(height: 14),
                  _field(
                    'Confirm password',
                    'password_confirmation',
                    required: !isEditing,
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          _formSection(
            context,
            title: 'Trainer Profile',
            subtitle:
                'Core trainer profile information used in operations and public/internal profile surfaces.',
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String?>(
                        initialValue: controllerFor('branch_id').text.isEmpty
                            ? null
                            : controllerFor('branch_id').text,
                        decoration: const InputDecoration(labelText: 'Branch'),
                        items: _gymBranches
                            .map(
                              (branch) => DropdownMenuItem<String?>(
                                value: branch['id']?.toString(),
                                child: Text(
                                  branch['name']?.toString() ?? 'Branch',
                                ),
                              ),
                            )
                            .toList(),
                        onChanged: (value) =>
                            controllerFor('branch_id').text = value ?? '',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: controllerFor('status', 'active').text,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: const ['active', 'inactive']
                            .map(
                              (status) => DropdownMenuItem<String>(
                                value: status,
                                child: Text(_dashboardTitleCase(status)),
                              ),
                            )
                            .toList(),
                        onChanged: (value) {
                          if (value != null) {
                            controllerFor('status').text = value;
                          }
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _field('Photo URL', 'profile_photo_url', required: false),
                const SizedBox(height: 14),
                _field('Bio', 'bio', required: false),
                const SizedBox(height: 14),
                _field('Specialization', 'specialization', required: false),
                const SizedBox(height: 14),
                _field(
                  'Specializations (comma separated)',
                  'specializations',
                  required: false,
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Experience years',
                        'experience_years',
                        required: false,
                        initialValue: '0',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        'Certifications (comma separated)',
                        'certifications',
                        required: false,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ];
      case _AdminFormType.member:
        return [
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Identity', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment<String>(
                      value: 'new',
                      label: Text('Create User'),
                    ),
                    ButtonSegment<String>(
                      value: 'existing',
                      label: Text('Existing User'),
                    ),
                  ],
                  selected: {_memberUserMode},
                  onSelectionChanged: (selection) {
                    setState(() => _memberUserMode = selection.first);
                  },
                ),
                const SizedBox(height: 16),
                if (_memberUserMode == 'existing' &&
                    (widget.prefill['id'] as num?) == null)
                  _field('Existing user id', 'existing_user_id')
                else ...[
                  _field('Name', 'name'),
                  const SizedBox(height: 14),
                  _field('Email', 'email'),
                  const SizedBox(height: 14),
                  _field('Phone', 'phone', required: false),
                  if ((widget.prefill['id'] as num?) == null) ...[
                    const SizedBox(height: 14),
                    _field('Password', 'password'),
                    const SizedBox(height: 14),
                    _field('Confirm password', 'password_confirmation'),
                  ],
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Member Profile',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                _field('Photo URL', 'avatar', required: false),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: controllerFor('branch_id').text.isEmpty
                            ? null
                            : controllerFor('branch_id').text,
                        decoration: const InputDecoration(labelText: 'Branch'),
                        items: _gymBranches
                            .map(
                              (branch) => DropdownMenuItem<String>(
                                value: branch['id']?.toString(),
                                child: Text(
                                  branch['name']?.toString() ?? 'Branch',
                                ),
                              ),
                            )
                            .toList(),
                        onChanged: (value) =>
                            controllerFor('branch_id').text = value ?? '',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: controllerFor('status', 'active').text,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: const ['active', 'inactive', 'expired']
                            .map(
                              (status) => DropdownMenuItem<String>(
                                value: status,
                                child: Text(_dashboardTitleCase(status)),
                              ),
                            )
                            .toList(),
                        onChanged: (value) {
                          if (value != null) {
                            controllerFor('status').text = value;
                          }
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue:
                      controllerFor('assigned_trainer_user_id').text.isEmpty
                      ? null
                      : controllerFor('assigned_trainer_user_id').text,
                  decoration: const InputDecoration(
                    labelText: 'Assigned Trainer',
                  ),
                  items: [
                    const DropdownMenuItem<String>(
                      value: '',
                      child: Text('No trainer assigned'),
                    ),
                    ..._gymTrainers.map(
                      (trainer) => DropdownMenuItem<String>(
                        value: trainer['id']?.toString(),
                        child: Text(trainer['name']?.toString() ?? 'Trainer'),
                      ),
                    ),
                  ],
                  onChanged: (value) =>
                      controllerFor('assigned_trainer_user_id').text =
                          value ?? '',
                ),
                const SizedBox(height: 14),
                _field('Fitness Goal', 'fitness_goal', required: false),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Height (cm)',
                        'height_cm',
                        required: false,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        'Weight (kg)',
                        'weight_kg',
                        required: false,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Experience Level',
                        'experience_level',
                        required: false,
                        initialValue: 'beginner',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field('Gender', 'gender', required: false),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _field('Medical Notes', 'medical_notes', required: false),
                const SizedBox(height: 14),
                _field('Injury Notes', 'injury_notes', required: false),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Emergency Contact Name',
                        'emergency_contact_name',
                        required: false,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        'Emergency Contact Phone',
                        'emergency_contact_phone',
                        required: false,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ];
      case _AdminFormType.manualAttendance:
        return textFields([
          _field('Gym id', 'gym_id'),
          _field('Branch id', 'branch_id'),
          _field('Member id', 'member_id'),
          _field(
            'Check-in at (ISO datetime)',
            'checked_in_at',
            initialValue: DateTime.now().toIso8601String(),
          ),
          _field('Notes', 'notes'),
        ]);
      case _AdminFormType.announcement:
        return textFields([
          _field('Title', 'title'),
          _field('Message', 'message'),
          _field(
            'Audience type',
            'audience_type',
            initialValue: widget.userRole == 'platform_admin'
                ? 'platform_wide'
                : 'gym_wide',
          ),
          _field('Gym id', 'gym_id'),
          _field('Branch id', 'branch_id'),
          _field('Member ids (comma separated)', 'member_ids'),
        ]);
      case _AdminFormType.publicListing:
        return [
          _formSection(
            context,
            title: 'Discovery and Visibility',
            subtitle:
                'Control whether the gym appears publicly and how much pricing/contact information is exposed.',
            child: Column(
              children: [
                _toggleSettingCard(
                  context,
                  title: 'Public listing enabled',
                  subtitle:
                      'Allow the gym to appear in the public discovery experience.',
                  value: _publicListingEnabled,
                  onChanged: (value) =>
                      setState(() => _publicListingEnabled = value),
                  icon: Icons.public_rounded,
                ),
                const SizedBox(height: 12),
                _toggleSettingCard(
                  context,
                  title: 'Show pricing',
                  subtitle:
                      'Display visible membership pricing on public pages and cards.',
                  value: _showPricing,
                  onChanged: (value) => setState(() => _showPricing = value),
                  icon: Icons.sell_rounded,
                ),
                const SizedBox(height: 12),
                _toggleSettingCard(
                  context,
                  title: 'Trial available',
                  subtitle:
                      'Expose trial-request CTA flows to visitors and members.',
                  value: _trialAvailable,
                  onChanged: (value) => setState(() => _trialAvailable = value),
                  icon: Icons.flag_rounded,
                ),
                const SizedBox(height: 12),
                _toggleSettingCard(
                  context,
                  title: 'Contact visible',
                  subtitle:
                      'Allow public visitors to see direct contact visibility cues for the gym.',
                  value: _contactVisible,
                  onChanged: (value) => setState(() => _contactVisible = value),
                  icon: Icons.call_rounded,
                ),
              ],
            ),
          ),
        ];
      case _AdminFormType.attendance:
        return [
          ...textFields([
            _field('Gym id', 'gym_id'),
            _field('Branch id', 'branch_id'),
            _field('QR payload', 'qr_payload'),
            _field('Notes', 'notes', required: false),
          ]),
          Text(
            'Scan a member QR code live or paste the fallback payload manually.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(18),
            child: SizedBox(
              height: 280,
              child: MobileScanner(onDetect: _onQrDetected),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            controllerFor('qr_payload').text.isEmpty
                ? 'No QR captured yet.'
                : 'QR payload captured and ready to submit.',
          ),
        ];
    }
  }

  static const List<String> _weekdayOptions = <String>[
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
  ];

  List<Widget> _platformGymFields(BuildContext context) {
    final isEditing = (widget.prefill['id'] as num?) != null;

    return [
      _formSection(
        context,
        title: 'Owner',
        subtitle:
            'Link an existing gym owner or create a new owner identity. Backend will assign the gym owner role.',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'existing', label: Text('Existing Owner')),
                ButtonSegment(value: 'new', label: Text('Create New Owner')),
              ],
              selected: {_ownerMode},
              onSelectionChanged: (selection) {
                if (isEditing && selection.first == 'new') {
                  return;
                }
                setState(() => _ownerMode = selection.first);
              },
            ),
            const SizedBox(height: 16),
            if (_optionsLoading && _platformOwners.isEmpty)
              const LoadingState(label: 'Loading gym owners...')
            else if (_ownerMode == 'existing')
              DropdownButtonFormField<int>(
                initialValue: _selectedOwnerId,
                decoration: const InputDecoration(labelText: 'Existing owner'),
                items: _platformOwners
                    .map(
                      (owner) => DropdownMenuItem<int>(
                        value: (owner['id'] as num?)?.toInt(),
                        child: Text(
                          '${owner['name'] ?? 'Owner'} • ${owner['email'] ?? '--'}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _selectedOwnerId = value),
                validator: (value) {
                  if (_ownerMode == 'existing' && value == null) {
                    return 'Existing owner is required';
                  }
                  return null;
                },
              )
            else ...[
              _field('Owner name', 'owner_name'),
              const SizedBox(height: 14),
              _field('Owner email', 'owner_email'),
              const SizedBox(height: 10),
              Text(
                'Owner phone is not supported by the current API contract.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ],
        ),
      ),
      const SizedBox(height: 16),
      _formSection(
        context,
        title: 'Gym',
        subtitle:
            'Core gym identity, location, timings, and discovery settings for the platform workspace.',
        child: Column(
          children: [
            _field('Gym name', 'name'),
            const SizedBox(height: 14),
            _field('Description', 'description', required: false),
            const SizedBox(height: 14),
            _field('Address', 'address', required: false),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(child: _field('City', 'city')),
                const SizedBox(width: 12),
                Expanded(child: _field('State', 'state', required: false)),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(child: _field('Pincode', 'pincode', required: false)),
                const SizedBox(width: 12),
                Expanded(
                  child: _field('Latitude', 'latitude', required: false),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _field('Longitude', 'longitude', required: false),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: _field(
                    'Opening time (HH:MM)',
                    'opening_time',
                    required: false,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _field(
                    'Closing time (HH:MM)',
                    'closing_time',
                    required: false,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _multiChipSelector(
              context,
              title: 'Weekly off',
              values: _selectedWeeklyOff,
              options: _weekdayOptions,
              onToggle: (day) {
                setState(() {
                  _selectedWeeklyOff.contains(day)
                      ? _selectedWeeklyOff.remove(day)
                      : _selectedWeeklyOff.add(day);
                });
              },
            ),
            const SizedBox(height: 14),
            _multiChipSelector(
              context,
              title: 'Facilities',
              values: _selectedFacilityIds
                  .map((entry) => entry.toString())
                  .toSet(),
              options: _platformFacilities
                  .map((facility) => '${facility['id']}::${facility['name']}')
                  .toList(),
              optionLabel: (raw) => raw.split('::').last,
              onToggle: (raw) {
                final id = int.tryParse(raw.split('::').first);
                if (id == null) {
                  return;
                }
                setState(() {
                  _selectedFacilityIds.contains(id)
                      ? _selectedFacilityIds.remove(id)
                      : _selectedFacilityIds.add(id);
                });
              },
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: controllerFor(
                'status',
                widget.prefill['status']?.toString() ?? 'pending',
              ).text,
              decoration: const InputDecoration(labelText: 'Status'),
              items:
                  (isEditing
                          ? const [
                              'pending',
                              'active',
                              'rejected',
                              'inactive',
                              'suspended',
                            ]
                          : const ['pending', 'active'])
                      .map(
                        (status) => DropdownMenuItem<String>(
                          value: status,
                          child: Text(_dashboardTitleCase(status)),
                        ),
                      )
                      .toList(),
              onChanged: (value) {
                if (value != null) {
                  controllerFor('status').text = value;
                }
              },
            ),
          ],
        ),
      ),
      const SizedBox(height: 16),
      _formSection(
        context,
        title: 'Visibility',
        subtitle:
            'Public listing, pricing visibility, trial availability, and contact controls.',
        child: Column(
          children: [
            _switchTile(
              'Public listing enabled',
              _publicListingEnabled,
              (value) => setState(() => _publicListingEnabled = value),
            ),
            _switchTile(
              'Show pricing',
              _showPricing,
              (value) => setState(() => _showPricing = value),
            ),
            _switchTile(
              'Trial available',
              _trialAvailable,
              (value) => setState(() => _trialAvailable = value),
            ),
            _switchTile(
              'Contact visible',
              _contactVisible,
              (value) => setState(() => _contactVisible = value),
            ),
            const SizedBox(height: 8),
            Text(
              'Logo and cover uploads are supported by the backend, but this mobile admin flow currently submits structured gym data only.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
      if (!isEditing) ...[
        const SizedBox(height: 16),
        _formSection(
          context,
          title: 'Default Branch',
          subtitle:
              'The backend can create the first branch automatically during gym onboarding.',
          child: Column(
            children: [
              _switchTile(
                'Create default branch',
                _createDefaultBranch,
                (value) => setState(() => _createDefaultBranch = value),
              ),
              if (_createDefaultBranch) ...[
                _switchTile(
                  'Use same address as gym',
                  _branchSameAsGym,
                  (value) => setState(() => _branchSameAsGym = value),
                ),
                const SizedBox(height: 14),
                _field('Branch name', 'branch_name', required: false),
                const SizedBox(height: 14),
                if (!_branchSameAsGym) ...[
                  _field('Branch address', 'branch_address', required: false),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: _field(
                          'Branch city',
                          'branch_city',
                          required: false,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _field(
                          'Branch state',
                          'branch_state',
                          required: false,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: _field(
                          'Branch pincode',
                          'branch_pincode',
                          required: false,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _field(
                          'Branch latitude',
                          'branch_latitude',
                          required: false,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _field(
                          'Branch longitude',
                          'branch_longitude',
                          required: false,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                ],
                Row(
                  children: [
                    Expanded(
                      child: _field(
                        'Branch opening time',
                        'branch_opening_time',
                        required: false,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(
                        'Branch closing time',
                        'branch_closing_time',
                        required: false,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _multiChipSelector(
                  context,
                  title: 'Branch weekly off',
                  values: _selectedBranchWeeklyOff,
                  options: _weekdayOptions,
                  onToggle: (day) {
                    setState(() {
                      _selectedBranchWeeklyOff.contains(day)
                          ? _selectedBranchWeeklyOff.remove(day)
                          : _selectedBranchWeeklyOff.add(day);
                    });
                  },
                ),
              ],
            ],
          ),
        ),
      ],
    ];
  }

  Widget _formSection(
    BuildContext context, {
    required String title,
    required String subtitle,
    required Widget child,
  }) {
    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 6),
          Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }

  Widget _multiChipSelector(
    BuildContext context, {
    required String title,
    required Set<String> values,
    required List<String> options,
    required ValueChanged<String> onToggle,
    String Function(String raw)? optionLabel,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: options.map((raw) {
            final normalizedValue = raw.contains('::')
                ? raw.split('::').first
                : raw;
            final selected =
                values.contains(normalizedValue) || values.contains(raw);
            return FilterChip(
              selected: selected,
              label: Text(optionLabel?.call(raw) ?? _dashboardTitleCase(raw)),
              onSelected: (_) => onToggle(raw),
            );
          }).toList(),
        ),
      ],
    );
  }

  Widget _field(
    String label,
    String key, {
    bool required = true,
    String initialValue = '',
  }) {
    return TextFormField(
      controller: controllerFor(key, initialValue),
      validator: required
          ? (value) {
              if ((value ?? '').trim().isEmpty) {
                return '$label is required';
              }
              return null;
            }
          : null,
      decoration: InputDecoration(labelText: label),
    );
  }

  Widget _switchTile(String title, bool value, ValueChanged<bool> onChanged) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title),
      value: value,
      onChanged: onChanged,
    );
  }

  Widget _toggleSettingCard(
    BuildContext context, {
    required String title,
    required String subtitle,
    required bool value,
    required ValueChanged<bool> onChanged,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: AppColors.primary),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 4),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
          Switch.adaptive(value: value, onChanged: onChanged),
        ],
      ),
    );
  }

  Widget _permissionToggleCard(
    BuildContext context, {
    required String label,
    required bool value,
    required ValueChanged<bool> onChanged,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: value ? AppColors.primaryBright : AppColors.strokeStrong,
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(label, style: Theme.of(context).textTheme.titleMedium),
          ),
          Switch.adaptive(value: value, onChanged: onChanged),
        ],
      ),
    );
  }

  Widget _imagePreviewCard({
    required String label,
    required String imageUrl,
    required IconData icon,
    bool compact = false,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Row(
        children: [
          Container(
            width: compact ? 56 : 88,
            height: compact ? 56 : 88,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(compact ? 18 : 22),
              color: AppColors.surfaceSoft,
              image: DecorationImage(
                image: NetworkImage(imageUrl),
                fit: BoxFit.cover,
                onError: (_, __) {},
              ),
            ),
            child: compact
                ? null
                : Align(
                    alignment: Alignment.topLeft,
                    child: Padding(
                      padding: const EdgeInsets.all(10),
                      child: Icon(
                        icon,
                        color: Colors.white.withValues(alpha: 0.9),
                      ),
                    ),
                  ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 4),
                Text(
                  imageUrl,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _onQrDetected(BarcodeCapture capture) {
    final rawValue = capture.barcodes.firstOrNull?.rawValue?.trim();

    if (rawValue == null ||
        rawValue.isEmpty ||
        rawValue == _lastScannedQrPayload) {
      return;
    }

    setState(() {
      _lastScannedQrPayload = rawValue;
      controllerFor('qr_payload').text = rawValue;
    });
  }

  Widget _buildAnimatedSummaryCard(BuildContext context) {
    final planPrice =
        double.tryParse(controllerFor('default_plan_price', '0').text) ?? 0;
    final joiningFee =
        double.tryParse(controllerFor('default_joining_fee', '0').text) ?? 0;
    final customAmount =
        double.tryParse(controllerFor('custom_fee_amount', '0').text) ?? 0;
    final discountAmount =
        double.tryParse(controllerFor('discount_amount', '0').text) ?? 0;
    final customJoiningFee =
        double.tryParse(controllerFor('custom_joining_fee', '0').text) ?? 0;
    final partialMonthFee =
        double.tryParse(controllerFor('partial_month_fee', '0').text) ?? 0;
    final ptFee =
        double.tryParse(controllerFor('pt_custom_fee', '0').text) ?? 0;
    final paid = double.tryParse(controllerFor('amount_paid', '0').text) ?? 0;
    final paymentAmount =
        double.tryParse(controllerFor('amount', '0').text) ?? 0;

    final basePrice = _customFeeEnabled && customAmount > 0
        ? customAmount
        : planPrice;
    final percentageDiscount = _discountType == 'percentage'
        ? (basePrice * (discountAmount / 100))
        : 0;
    final fixedDiscount = _discountType == 'fixed' ? discountAmount : 0;
    final effectiveJoiningFee = _joiningFeeWaived
        ? 0
        : (customJoiningFee > 0 ? customJoiningFee : joiningFee);
    final finalPayable =
        (basePrice - percentageDiscount - fixedDiscount) +
        effectiveJoiningFee +
        partialMonthFee +
        ptFee;
    final dueAmount = widget.type == _AdminFormType.payment
        ? (finalPayable - (paid + paymentAmount)).clamp(-9999999, 9999999)
        : (finalPayable - paid).clamp(-9999999, 9999999);

    final warning = dueAmount > 0 && widget.type == _AdminFormType.payment;

    final auditCount = _customFeeAudits.length;
    final paymentCount = _paymentHistory.length;

    return RevealOnBuild(
      child: PulseGlow(
        enabled: warning,
        glowColor: Theme.of(context).colorScheme.secondary,
        child: Card(
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 240),
            curve: Curves.easeOutCubic,
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(24),
              gradient: LinearGradient(
                colors: <Color>[
                  (warning
                          ? Theme.of(context).colorScheme.secondary
                          : Theme.of(context).colorScheme.primary)
                      .withValues(alpha: 0.12),
                  Theme.of(context).cardColor,
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  warning ? 'Due warning preview' : 'Live calculation preview',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 180),
                  child: Column(
                    key: ValueKey<String>(
                      '$finalPayable-$dueAmount-$paymentAmount-$_customFeeEnabled-$_discountType',
                    ),
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Plan/base: ${basePrice.toStringAsFixed(2)}'),
                      Text(
                        'Joining fee: ${effectiveJoiningFee.toStringAsFixed(2)}',
                      ),
                      Text(
                        'Partial/PT add-ons: ${(partialMonthFee + ptFee).toStringAsFixed(2)}',
                      ),
                      Text('Final payable: ${finalPayable.toStringAsFixed(2)}'),
                      Text('Paid: ${paid.toStringAsFixed(2)}'),
                      if (widget.type == _AdminFormType.payment)
                        Text(
                          'Incoming payment: ${paymentAmount.toStringAsFixed(2)}',
                        ),
                      const SizedBox(height: 8),
                      Text(
                        'Due: ${dueAmount.toStringAsFixed(2)}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: warning
                                  ? Theme.of(context).colorScheme.secondary
                                  : AppColors.textPrimary,
                            ),
                      ),
                      if (widget.type == _AdminFormType.customFee ||
                          widget.type == _AdminFormType.membershipAssign) ...[
                        const SizedBox(height: 8),
                        Text('Audit entries: $auditCount'),
                      ],
                      if (widget.type == _AdminFormType.payment) ...[
                        const SizedBox(height: 8),
                        Text('Payments logged: $paymentCount'),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _handleMembershipSelectionChanged() {
    final membershipId = int.tryParse(
      _controllers['member_membership_id']?.text.trim() ?? '',
    );

    if (membershipId == null || membershipId <= 0) {
      return;
    }

    _loadMembershipSupportData(membershipId);
  }

  Future<void> _loadMembershipSupportData(int membershipId) async {
    if (_detailLoading) {
      return;
    }

    setState(() => _detailLoading = true);
    try {
      final detail = await widget.repository.fetchMembershipDetail(
        membershipId,
      );
      final payments = await widget.repository.fetchPaymentHistory(
        membershipId,
      );
      final audits = await widget.repository.fetchCustomFeeAudits(membershipId);

      if (!mounted) {
        return;
      }

      final membership = Map<String, dynamic>.from(
        detail['member_membership'] as Map? ?? detail,
      );
      setState(() {
        _membershipDetail = membership;
        _paymentHistory = payments;
        _customFeeAudits = audits;
        _membershipActivityTimeline =
            (detail['activity_timeline'] as List<dynamic>? ?? const [])
                .map((item) => Map<String, dynamic>.from(item as Map))
                .toList();
      });

      controllerFor(
        'default_plan_price',
        membership['default_plan_price']?.toString() ?? '0',
      ).text = membership['default_plan_price']?.toString() ?? '0';
      controllerFor(
        'default_joining_fee',
        membership['default_joining_fee']?.toString() ?? '0',
      ).text = membership['default_joining_fee']?.toString() ?? '0';
      controllerFor(
        'final_payable_amount',
        membership['final_payable_amount']?.toString() ?? '0',
      ).text = membership['final_payable_amount']?.toString() ?? '0';
      controllerFor(
        'due_amount',
        membership['due_amount']?.toString() ?? '0',
      ).text = membership['due_amount']?.toString() ?? '0';
      controllerFor(
        'amount_paid',
        membership['amount_paid']?.toString() ?? '0',
      ).text = membership['amount_paid']?.toString() ?? '0';
      controllerFor('due_date', membership['due_date']?.toString() ?? '').text =
          membership['due_date']?.toString() ?? '';
      controllerFor(
        'custom_fee_reason',
        membership['custom_fee_reason']?.toString() ?? '',
      );

      _customFeeEnabled = membership['custom_fee_enabled'] == true;
      _joiningFeeWaived = membership['joining_fee_waived'] == true;
      _discountType = membership['discount_type']?.toString() ?? 'none';
    } catch (_) {
      if (!mounted) {
        return;
      }
    } finally {
      if (mounted) {
        setState(() => _detailLoading = false);
      }
    }
  }

  Map<String, dynamic>? _selectedMembershipPlanRecord() {
    final planId = int.tryParse(
      controllerFor('membership_plan_id').text.trim(),
    );
    if (planId == null) {
      return null;
    }
    for (final plan in _gymMembershipPlans) {
      if ((plan['id'] as num?)?.toInt() == planId) {
        return plan;
      }
    }
    return null;
  }

  String _computedMembershipExpiryDate() {
    final plan = _selectedMembershipPlanRecord();
    final durationDays = (plan?['duration_days'] as num?)?.toInt();
    final start = DateTime.tryParse(controllerFor('start_date').text.trim());
    if (durationDays == null || start == null) {
      return '--';
    }
    return DateFormat(
      'yyyy-MM-dd',
    ).format(start.add(Duration(days: durationDays)));
  }

  List<Map<String, dynamic>> _availablePaymentMemberships() {
    final selectedMemberId = int.tryParse(
      controllerFor('member_id').text.trim(),
    );
    if (selectedMemberId == null) {
      return _gymMemberMemberships;
    }

    final memberships = _gymMemberMemberships
        .where(
          (membership) =>
              (membership['member_id'] as num?)?.toInt() == selectedMemberId,
        )
        .toList();
    return memberships.isEmpty ? _gymMemberMemberships : memberships;
  }
}

class _DashboardWorkspace extends StatelessWidget {
  const _DashboardWorkspace({
    required this.appUser,
    required this.role,
    required this.dashboard,
    required this.onRefresh,
    required this.onOpenOnboardingStep,
    required this.onOpenSection,
  });

  final AppUser appUser;
  final String role;
  final Map<String, dynamic> dashboard;
  final Future<void> Function() onRefresh;
  final ValueChanged<String> onOpenOnboardingStep;
  final ValueChanged<String> onOpenSection;

  @override
  Widget build(BuildContext context) {
    final onboarding = Map<String, dynamic>.from(
      dashboard['onboarding'] as Map? ?? const {},
    );
    final stats = _map(dashboard['stats']);
    final visibility = _map(dashboard['visibility']);
    final onboardingCompleted = (onboarding['completed'] as bool?) ?? false;
    final metrics = role == 'platform_admin'
        ? _platformMetrics(stats)
        : _gymMetrics(visibility);
    final gymQuickActions = _gymQuickActions();
    final compact = MediaQuery.sizeOf(context).width < 980;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          RevealOnBuild(
            child: PremiumCard(
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          role == 'platform_admin'
                              ? 'Platform operations at a glance'
                              : 'Gym operations control room',
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 10),
                        Text(
                          role == 'platform_admin'
                              ? 'Keep approvals, listings, growth, and report shortcuts moving from one premium command surface.'
                              : 'Track members, dues, attendance, trials, and trainer capacity from one scoped premium command surface.',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        const SizedBox(height: 18),
                        Wrap(
                          spacing: 12,
                          runSpacing: 12,
                          children: role == 'platform_admin'
                              ? [
                                  _MiniHighlight(
                                    label: 'Approvals',
                                    value:
                                        '${stats['pending_gym_approvals'] ?? 0}',
                                  ),
                                  _MiniHighlight(
                                    label: 'Active gyms',
                                    value: '${stats['active_gyms'] ?? 0}',
                                  ),
                                  _MiniHighlight(
                                    label: 'Trials',
                                    value:
                                        '${stats['total_trial_requests'] ?? 0}',
                                  ),
                                ]
                              : [
                                  _MiniHighlight(
                                    label: 'Revenue',
                                    value: _formatCurrency(
                                      dashboard['monthly_collection'] ??
                                          dashboard['revenue'] ??
                                          0,
                                    ),
                                  ),
                                  _MiniHighlight(
                                    label: 'Overdue',
                                    value: _formatCurrency(
                                      dashboard['overdue_dues'] ?? 0,
                                    ),
                                  ),
                                  _MiniHighlight(
                                    label: 'Attendance',
                                    value:
                                        '${dashboard['today_check_ins'] ?? dashboard['today_checkins'] ?? 0} today',
                                  ),
                                ],
                        ),
                        const SizedBox(height: 16),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: role == 'platform_admin'
                              ? [
                                  QuickActionButton(
                                    label: 'Add Gym',
                                    icon: Icons.add_business_rounded,
                                    onPressed: () => onOpenSection('Gyms'),
                                  ),
                                  QuickActionButton(
                                    label: 'Facilities',
                                    icon: Icons.spa_rounded,
                                    onPressed: () =>
                                        onOpenSection('Facilities'),
                                  ),
                                  QuickActionButton(
                                    label: 'Reports',
                                    icon: Icons.analytics_rounded,
                                    onPressed: () => onOpenSection('Reports'),
                                  ),
                                  QuickActionButton(
                                    label: 'Listings',
                                    icon: Icons.view_carousel_rounded,
                                    onPressed: () => onOpenSection('Listings'),
                                  ),
                                ]
                              : gymQuickActions
                                    .map(
                                      (action) => QuickActionButton(
                                        label: action.label,
                                        icon: action.icon,
                                        onPressed: action.onPressed,
                                      ),
                                    )
                                    .toList(),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 18),
          if (role != 'platform_admin' && !onboardingCompleted) ...[
            _GymOnboardingCard(
              onboarding: onboarding,
              onOpenStep: onOpenOnboardingStep,
            ),
            const SizedBox(height: 18),
          ],
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: MediaQuery.sizeOf(context).width > 1200 ? 4 : 2,
              mainAxisSpacing: 18,
              crossAxisSpacing: 18,
              childAspectRatio: 1.12,
            ),
            itemCount: metrics.length,
            itemBuilder: (context, index) => RevealOnBuild(
              delay: Duration(milliseconds: 70 * index),
              child: DashboardStatCard(
                label: metrics[index].label,
                value: metrics[index].value,
                icon: metrics[index].icon,
              ),
            ),
          ),
          const SizedBox(height: 18),
          Wrap(spacing: 18, runSpacing: 18, children: _actionCards(context)),
          const SizedBox(height: 18),
          if (compact)
            Column(children: _sectionPanels(context))
          else
            Wrap(
              spacing: 18,
              runSpacing: 18,
              children: _sectionPanels(context),
            ),
        ],
      ),
    );
  }

  List<_MetricConfig> _gymMetrics(Map<String, dynamic> visibility) {
    final metrics = <_MetricConfig>[];
    final canSeeMembers = _canSeeMemberData(visibility);
    final canSeeBilling = _canSeeBilling(visibility);
    final canSeeAttendance = _canSeeAttendance(visibility);
    final canSeeTrainers = _canSeeTrainers(visibility);
    final canSeeTrials = _canSeeTrials(visibility);

    if (canSeeMembers) {
      metrics.addAll([
        _MetricConfig(
          'Total Members',
          '${dashboard['total_members'] ?? 0}',
          Icons.groups_rounded,
        ),
        _MetricConfig(
          'Active Members',
          '${dashboard['active_members'] ?? 0}',
          Icons.verified_user_rounded,
        ),
        _MetricConfig(
          'Expired Members',
          '${dashboard['expired_members'] ?? 0}',
          Icons.person_off_rounded,
        ),
        _MetricConfig(
          'Expiring Soon',
          '${dashboard['expiring_soon'] ?? dashboard['expiring_memberships'] ?? 0}',
          Icons.hourglass_top_rounded,
        ),
      ]);
    }

    if (canSeeAttendance) {
      metrics.add(
        _MetricConfig(
          'Today Check-ins',
          '${dashboard['today_check_ins'] ?? dashboard['today_checkins'] ?? 0}',
          Icons.qr_code_scanner_rounded,
        ),
      );
    }

    if (canSeeBilling) {
      metrics.addAll([
        _MetricConfig(
          'Pending Dues',
          _formatCurrency(dashboard['pending_dues'] ?? 0),
          Icons.warning_amber_rounded,
        ),
        _MetricConfig(
          'Overdue Dues',
          _formatCurrency(dashboard['overdue_dues'] ?? 0),
          Icons.error_outline_rounded,
        ),
        _MetricConfig(
          'Monthly Collection',
          _formatCurrency(
            dashboard['monthly_collection'] ?? dashboard['revenue'] ?? 0,
          ),
          Icons.currency_rupee_rounded,
        ),
        _MetricConfig(
          'Custom Fee Members',
          '${dashboard['custom_fee_members_count'] ?? 0}',
          Icons.tune_rounded,
        ),
      ]);
    }

    if (canSeeTrainers) {
      metrics.addAll([
        _MetricConfig(
          'Total Trainers',
          '${dashboard['total_trainers'] ?? 0}',
          Icons.fitness_center_rounded,
        ),
        _MetricConfig(
          'Trainer Ratio',
          dashboard['trainer_member_ratio']?.toString() ?? '--',
          Icons.balance_rounded,
        ),
      ]);
    }

    if (canSeeTrials) {
      metrics.add(
        _MetricConfig(
          'Pending Trials',
          '${dashboard['pending_trial_requests'] ?? dashboard['trial_requests_waiting'] ?? 0}',
          Icons.flag_rounded,
        ),
      );
    }

    return metrics;
  }

  List<_MetricConfig> _platformMetrics(Map<String, dynamic> stats) {
    return <_MetricConfig>[
      _MetricConfig(
        'Total Gyms',
        '${stats['total_gyms'] ?? 0}',
        Icons.store_mall_directory_rounded,
      ),
      _MetricConfig(
        'Active Gyms',
        '${stats['active_gyms'] ?? 0}',
        Icons.verified_rounded,
      ),
      _MetricConfig(
        'Pending Approvals',
        '${stats['pending_gym_approvals'] ?? 0}',
        Icons.approval_rounded,
      ),
      _MetricConfig(
        'Total Members',
        '${stats['total_members'] ?? 0}',
        Icons.groups_rounded,
      ),
      _MetricConfig(
        'Total Trainers',
        '${stats['total_trainers'] ?? 0}',
        Icons.fitness_center_rounded,
      ),
      _MetricConfig(
        'Total Branches',
        '${stats['total_branches'] ?? 0}',
        Icons.location_city_rounded,
      ),
      _MetricConfig(
        'Trial Requests',
        '${stats['total_trial_requests'] ?? 0}',
        Icons.flag_rounded,
      ),
      _MetricConfig(
        'Featured Gyms',
        '${stats['featured_gyms'] ?? 0}',
        Icons.workspace_premium_rounded,
      ),
      _MetricConfig(
        'Promoted Gyms',
        '${stats['promoted_gyms'] ?? 0}',
        Icons.trending_up_rounded,
      ),
    ];
  }

  List<Widget> _actionCards(BuildContext context) {
    if (role == 'platform_admin') {
      return [
        _ActionCenterCard(
          title: 'Add Gym',
          value: '${_map(dashboard['stats'])['total_gyms'] ?? 0}',
          description:
              'Jump into the platform gym workspace and onboard the next gym profile.',
          cta: 'Open Gyms',
          onTap: () => onOpenSection('Gyms'),
        ),
        _ActionCenterCard(
          title: 'Manage Facilities',
          value:
              '${_list(_map(dashboard['platform_activity'])['latest_facility_changes']).length}',
          description:
              'Review facility catalog changes and keep public gym metadata clean.',
          cta: 'Open Facilities',
          onTap: () => onOpenSection('Facilities'),
        ),
        _ActionCenterCard(
          title: 'Reports',
          value: '${_map(dashboard['stats'])['total_members'] ?? 0}',
          description:
              'Open platform-level reports for gyms, users, payments, attendance, and custom fees.',
          cta: 'Open Reports',
          onTap: () => onOpenSection('Reports'),
        ),
        _ActionCenterCard(
          title: 'Listings',
          value:
              '${(_map(dashboard['stats'])['featured_gyms'] ?? 0) + (_map(dashboard['stats'])['promoted_gyms'] ?? 0)}',
          description:
              'Control featured and promoted visibility from the listings workspace.',
          cta: 'Open Listings',
          onTap: () => onOpenSection('Listings'),
        ),
      ];
    }

    return [
      if (_canSeeMemberData(_map(dashboard['visibility'])))
        _ActionCenterCard(
          title: 'Memberships expiring this week',
          value:
              '${dashboard['expiring_soon'] ?? dashboard['expiring_memberships'] ?? 0}',
          description: 'Members needing renewal and outreach right away.',
          cta: 'View Members',
          onTap: () => onOpenSection('Members'),
        ),
      if (_canSeeBilling(_map(dashboard['visibility'])))
        _ActionCenterCard(
          title: 'Pending dues',
          value: _formatCurrency(dashboard['pending_dues'] ?? 0),
          description:
              'Collections to close across unpaid and partial memberships.',
          cta: 'Collect Payment',
          onTap: () => onOpenSection('Payments'),
        ),
      if (_canSeeTrials(_map(dashboard['visibility'])))
        _ActionCenterCard(
          title: 'Pending trial requests',
          value:
              '${dashboard['pending_trial_requests'] ?? dashboard['trial_requests_waiting'] ?? 0}',
          description:
              'Leads waiting for follow-up, assignment, and conversion.',
          cta: 'Open Announcements',
          onTap: () => onOpenSection('Announcements'),
        ),
      if (_canSeeAttendance(_map(dashboard['visibility'])))
        _ActionCenterCard(
          title: 'Today check-ins',
          value:
              '${dashboard['today_check_ins'] ?? dashboard['today_checkins'] ?? 0}',
          description:
              'Track attendance momentum and spot missing member visits fast.',
          cta: 'Open Attendance',
          onTap: () => onOpenSection('Attendance'),
        ),
      if (_canSeeTrainers(_map(dashboard['visibility'])))
        _ActionCenterCard(
          title: 'Trainer capacity',
          value: '${dashboard['overloaded_trainers_count'] ?? 0}',
          description:
              'Review trainer load and rebalance active member assignments.',
          cta: 'Open Trainers',
          onTap: () => onOpenSection('Trainers'),
        ),
    ];
  }

  List<Widget> _sectionPanels(BuildContext context) {
    if (role == 'platform_admin') {
      final activity = _map(dashboard['platform_activity']);
      return [
        _DashboardSectionPanel(
          title: 'Pending gym approvals',
          items: _list(dashboard['pending_gym_approvals']).map((item) {
            return _DashboardLineItem(
              title: item['name']?.toString() ?? 'Gym',
              subtitle:
                  '${item['owner_name'] ?? 'Unassigned'} • ${item['city'] ?? 'N/A'} • ${prettyDateTime(item['submitted_at'])}',
              trailing: _dashboardTitleCase(
                item['status']?.toString() ?? 'pending',
              ),
            );
          }).toList(),
          emptyTitle: 'No gyms waiting approval',
          emptyMessage:
              'New gym submissions will appear here when approval is needed.',
        ),
        _DashboardSectionPanel(
          title: 'Recently added gyms',
          items: _list(dashboard['recently_added_gyms']).map((item) {
            final badges = <String>[
              if (item['is_verified'] == true) 'Verified',
              if (item['is_featured'] == true) 'Featured',
              if (item['is_promoted'] == true) 'Promoted',
            ];
            return _DashboardLineItem(
              title: item['name']?.toString() ?? 'Gym',
              subtitle:
                  '${item['city'] ?? 'N/A'} • ${item['owner_name'] ?? 'Unassigned'}${badges.isEmpty ? '' : ' • ${badges.join(' • ')}'}',
              trailing: _dashboardTitleCase(
                item['status']?.toString() ?? 'active',
              ),
            );
          }).toList(),
          emptyTitle: 'No recent gyms',
          emptyMessage: 'Newly onboarded gyms will appear here.',
        ),
        _DashboardSectionPanel(
          title: 'Platform activity',
          items: [
            ..._list(activity['latest_gym_approvals']).map((item) {
              return _DashboardLineItem(
                title: item['title']?.toString() ?? 'Gym approval updated',
                subtitle:
                    '${item['description'] ?? 'Activity recorded'} • ${item['actor_name'] ?? 'System'}',
                trailing: prettyDateTime(item['occurred_at']),
              );
            }),
            ..._list(activity['latest_gym_creations']).map((item) {
              return _DashboardLineItem(
                title: item['title']?.toString() ?? 'Gym created',
                subtitle:
                    '${item['description'] ?? 'Activity recorded'} • ${item['actor_name'] ?? 'System'}',
                trailing: prettyDateTime(item['occurred_at']),
              );
            }),
            ..._list(activity['latest_feature_promote_changes']).map((item) {
              return _DashboardLineItem(
                title: item['title']?.toString() ?? 'Listing updated',
                subtitle:
                    '${item['description'] ?? 'Activity recorded'} • ${item['actor_name'] ?? 'System'}',
                trailing: prettyDateTime(item['occurred_at']),
              );
            }),
            ..._list(activity['latest_facility_changes']).map((item) {
              return _DashboardLineItem(
                title: item['title']?.toString() ?? 'Facility updated',
                subtitle:
                    '${item['description'] ?? 'Activity recorded'} • ${item['actor_name'] ?? 'System'}',
                trailing: prettyDateTime(item['occurred_at']),
              );
            }),
          ],
          emptyTitle: 'No recent activity',
          emptyMessage:
              'Platform audit activity will appear here as gyms and listings change.',
        ),
      ];
    }

    return [
      if (_canSeeMemberData(_map(dashboard['visibility'])))
        _DashboardSectionPanel(
          title: 'Member pulse',
          lines: [
            'Total members: ${dashboard['total_members'] ?? 0}',
            'Active members: ${dashboard['active_members'] ?? 0}',
            'Expired members: ${dashboard['expired_members'] ?? 0}',
            'Expiring soon: ${dashboard['expiring_soon'] ?? dashboard['expiring_memberships'] ?? 0}',
          ],
        ),
      if (_canSeeMemberData(_map(dashboard['visibility'])))
        _DashboardSectionPanel(
          title: 'Expiring memberships',
          items: _list(dashboard['expiring_memberships_list']).map((item) {
            return _DashboardLineItem(
              title: item['member_name']?.toString() ?? 'Member',
              subtitle:
                  '${item['plan_name'] ?? 'Membership'} • expires ${item['expiry_date'] ?? 'soon'}',
              trailing: _formatCurrency(item['due_amount'] ?? 0),
            );
          }).toList(),
          emptyTitle: 'No renewals due',
          emptyMessage: 'No memberships expire within the next seven days.',
        ),
      if (_canSeeBilling(_map(dashboard['visibility'])))
        _DashboardSectionPanel(
          title: 'Pending dues',
          items: _list(dashboard['pending_dues_list']).map((item) {
            return _DashboardLineItem(
              title: item['member_name']?.toString() ?? 'Member',
              subtitle: 'Due date ${item['due_date'] ?? 'pending'}',
              trailing: _formatCurrency(item['due_amount'] ?? 0),
            );
          }).toList(),
          emptyTitle: 'No pending dues',
          emptyMessage: 'Collections are clear for the current scope.',
        ),
      if (_canSeeTrials(_map(dashboard['visibility'])))
        _DashboardSectionPanel(
          title: 'Pending trial requests',
          items: _list(dashboard['trial_requests_waiting_list']).map((item) {
            return _DashboardLineItem(
              title: item['name']?.toString() ?? 'Trial request',
              subtitle:
                  'Preferred ${item['preferred_date'] ?? 'any day'}${item['preferred_time'] != null ? ' • ${item['preferred_time']}' : ''}',
              trailing: _dashboardTitleCase(
                item['status']?.toString() ?? 'pending',
              ),
            );
          }).toList(),
          emptyTitle: 'No trials waiting',
          emptyMessage:
              'New trial leads will appear here when members request a visit.',
        ),
      if (_canSeeAttendance(_map(dashboard['visibility'])) ||
          _canSeeMemberData(_map(dashboard['visibility'])))
        _DashboardSectionPanel(
          title: 'Retention watch',
          lines: [
            'Today check-ins: ${dashboard['today_check_ins'] ?? dashboard['today_checkins'] ?? 0}',
            'Inactive members: ${dashboard['inactive_members_count'] ?? 0}',
            'Members without trainer: ${dashboard['members_without_trainer_count'] ?? 0}',
            'High risk members: ${dashboard['high_risk_engagement_count'] ?? 0}',
          ],
        ),
      if (_canSeeTrainers(_map(dashboard['visibility'])))
        _DashboardSectionPanel(
          title: 'Trainer load alerts',
          items: _list(dashboard['overloaded_trainers_list']).map((item) {
            return _DashboardLineItem(
              title: item['name']?.toString() ?? 'Trainer',
              subtitle:
                  '${item['assigned_members_count'] ?? 0} assigned members',
            );
          }).toList(),
          emptyTitle: 'No load alerts',
          emptyMessage:
              'Trainer capacity is balanced across the current scope.',
        ),
      if (_canSeeBilling(_map(dashboard['visibility'])))
        _DashboardSectionPanel(
          title: 'Custom fee reviews',
          items: _list(dashboard['pending_custom_fee_review_list']).map((item) {
            return _DashboardLineItem(
              title: item['member_name']?.toString() ?? 'Member',
              subtitle:
                  item['custom_fee_reason']?.toString() ?? 'Reason pending',
              trailing: _formatCurrency(item['custom_fee_amount'] ?? 0),
            );
          }).toList(),
          emptyTitle: 'No custom fee reviews',
          emptyMessage: 'There are no pending custom fee reviews right now.',
        ),
    ];
  }

  bool _isGymRole() => role != 'platform_admin';

  bool _canSeeBilling(Map<String, dynamic> visibility) =>
      !_isGymRole() ||
      visibility['billing'] == true ||
      appUser.activeRole == 'gym_owner';

  bool _canSeeAttendance(Map<String, dynamic> visibility) =>
      !_isGymRole() ||
      visibility['attendance'] == true ||
      appUser.activeRole == 'gym_owner';

  bool _canSeeMemberData(Map<String, dynamic> visibility) =>
      !_isGymRole() ||
      visibility['members_view'] == true ||
      visibility['manage_members_action'] == true ||
      appUser.activeRole == 'gym_owner';

  bool _canSeeTrainers(Map<String, dynamic> visibility) =>
      !_isGymRole() ||
      visibility['trainers'] == true ||
      appUser.activeRole == 'gym_owner';

  bool _canSeeTrials(Map<String, dynamic> visibility) =>
      !_isGymRole() ||
      visibility['trials'] == true ||
      appUser.activeRole == 'gym_owner';

  List<_QuickDashboardAction> _gymQuickActions() {
    final visibility = _map(dashboard['visibility']);
    final actions = <_QuickDashboardAction>[];

    if (_canSeeMemberData(visibility)) {
      actions.add(
        _QuickDashboardAction(
          label: 'Add Member',
          icon: Icons.person_add_alt_1_rounded,
          onPressed: () => onOpenSection('Members'),
        ),
      );
    }

    if (_canSeeBilling(visibility) &&
        (visibility['collect_payment_action'] == true ||
            appUser.activeRole == 'gym_owner')) {
      actions.add(
        _QuickDashboardAction(
          label: 'Collect Payment',
          icon: Icons.payments_rounded,
          onPressed: () => onOpenSection('Payments'),
        ),
      );
    }

    if (_canSeeAttendance(visibility) &&
        (visibility['manage_attendance_action'] == true ||
            appUser.activeRole == 'gym_owner')) {
      actions.add(
        _QuickDashboardAction(
          label: 'Mark Attendance',
          icon: Icons.qr_code_scanner_rounded,
          onPressed: () => onOpenSection('Attendance'),
        ),
      );
    }

    if ((visibility['send_announcements_action'] == true ||
        appUser.activeRole == 'gym_owner')) {
      actions.add(
        _QuickDashboardAction(
          label: 'Send Announcement',
          icon: Icons.campaign_rounded,
          onPressed: () => onOpenSection('Announcements'),
        ),
      );
    }

    if (_canSeeTrainers(visibility) &&
        (visibility['manage_trainers_action'] == true ||
            appUser.activeRole == 'gym_owner')) {
      actions.add(
        _QuickDashboardAction(
          label: 'Add Trainer',
          icon: Icons.fitness_center_rounded,
          onPressed: () => onOpenSection('Trainers'),
        ),
      );
    }

    if (visibility['manage_plans_action'] == true ||
        appUser.activeRole == 'gym_owner') {
      actions.add(
        _QuickDashboardAction(
          label: 'Create Plan',
          icon: Icons.workspace_premium_rounded,
          onPressed: () => onOpenSection('Membership Plans'),
        ),
      );
    }

    return actions;
  }

  List<Map<String, dynamic>> _list(Object? value) {
    return (value as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Map<String, dynamic> _map(Object? value) {
    return Map<String, dynamic>.from(value as Map? ?? const {});
  }
}

class _QuickDashboardAction {
  const _QuickDashboardAction({
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback onPressed;
}

class _ActionCenterCard extends StatelessWidget {
  const _ActionCenterCard({
    required this.title,
    required this.value,
    required this.description,
    required this.cta,
    required this.onTap,
  });

  final String title;
  final String value;
  final String description;
  final String cta;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 290,
      child: PremiumCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 10),
            Text(value, style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: 10),
            Text(description, style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: 16),
            AppPrimaryButton(label: cta, onPressed: onTap),
          ],
        ),
      ),
    );
  }
}

class _DashboardSectionPanel extends StatelessWidget {
  const _DashboardSectionPanel({
    required this.title,
    this.lines = const [],
    this.items = const [],
    this.emptyTitle,
    this.emptyMessage,
  });

  final String title;
  final List<String> lines;
  final List<_DashboardLineItem> items;
  final String? emptyTitle;
  final String? emptyMessage;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 360,
      child: PremiumCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 14),
            if (lines.isNotEmpty)
              ...lines.map(
                (line) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Text(
                    line,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
              ),
            if (items.isNotEmpty)
              ...items.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: item,
                ),
              ),
            if (lines.isEmpty && items.isEmpty)
              EmptyState(
                title: emptyTitle ?? 'Nothing here yet',
                message: emptyMessage ?? 'No data available yet.',
              ),
          ],
        ),
      ),
    );
  }
}

class _DashboardLineItem extends StatelessWidget {
  const _DashboardLineItem({
    required this.title,
    required this.subtitle,
    this.trailing,
  });

  final String title;
  final String subtitle;
  final String? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 4),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
          if (trailing != null)
            Text(trailing!, style: Theme.of(context).textTheme.titleMedium),
        ],
      ),
    );
  }
}

class _GymOnboardingCard extends StatelessWidget {
  const _GymOnboardingCard({
    required this.onboarding,
    required this.onOpenStep,
  });

  final Map<String, dynamic> onboarding;
  final ValueChanged<String> onOpenStep;

  @override
  Widget build(BuildContext context) {
    final steps = (onboarding['steps'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final progress =
        ((onboarding['progress_percent'] as num?)?.toDouble() ?? 0) / 100;
    final nextStep = steps.cast<Map<String, dynamic>?>().firstWhere(
      (step) => (step?['completed'] as bool?) != true,
      orElse: () => null,
    );

    return RevealOnBuild(
      delay: const Duration(milliseconds: 70),
      child: PremiumCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Gym Setup Onboarding',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                ),
                StatusBadge(
                  label:
                      '${onboarding['completed_count'] ?? 0}/${onboarding['total_steps'] ?? steps.length} done',
                  color: AppColors.accent,
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'Complete the core setup checklist before handing the workspace to the full team.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: AppSpacing.lg),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                minHeight: 10,
                value: progress.clamp(0.0, 1.0),
                backgroundColor: AppColors.surfaceSoft,
                valueColor: const AlwaysStoppedAnimation<Color>(
                  AppColors.primaryBright,
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            ...steps.map(
              (step) => Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                child: PremiumCard(
                  child: Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color:
                              ((step['completed'] as bool? ?? false)
                                      ? AppColors.success
                                      : AppColors.warning)
                                  .withValues(alpha: 0.16),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          (step['completed'] as bool? ?? false)
                              ? Icons.check_circle_rounded
                              : Icons.radio_button_unchecked_rounded,
                          color: (step['completed'] as bool? ?? false)
                              ? AppColors.success
                              : AppColors.warning,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          step['label']?.toString() ?? 'Setup step',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                      ),
                      if (!(step['completed'] as bool? ?? false))
                        TextButton(
                          onPressed: () =>
                              onOpenStep(step['key']?.toString() ?? ''),
                          child: const Text('Open'),
                        ),
                    ],
                  ),
                ),
              ),
            ),
            if (nextStep != null) ...[
              const SizedBox(height: AppSpacing.sm),
              GradientButton(
                label:
                    'Continue: ${nextStep['label']?.toString() ?? 'Next setup step'}',
                onPressed: () => onOpenStep(nextStep['key']?.toString() ?? ''),
                expanded: true,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _AdminDashboardSkeleton extends StatelessWidget {
  const _AdminDashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        children: const [
          SkeletonDashboardGrid(),
          SizedBox(height: 20),
          SkeletonReportsTable(rows: 4, columns: 5),
          SizedBox(height: 20),
          SkeletonNotificationsList(items: 4),
        ],
      ),
    );
  }
}

enum _BillingWorkspaceTab {
  allPayments,
  collectPayment,
  pendingDues,
  overdue,
  partialPayments,
  paid,
}

class _PaymentsAndDuesSection extends StatefulWidget {
  const _PaymentsAndDuesSection({
    super.key,
    required this.appUser,
    required this.repository,
    required this.onOpenForm,
    required this.onOpenMemberDetail,
  });

  final AppUser appUser;
  final AdminRepository repository;
  final Future<void> Function(
    _AdminFormType? type, {
    Map<String, dynamic>? prefill,
  })
  onOpenForm;
  final Future<void> Function(Map<String, dynamic>) onOpenMemberDetail;

  @override
  State<_PaymentsAndDuesSection> createState() =>
      _PaymentsAndDuesSectionState();
}

class _PaymentsAndDuesSectionState extends State<_PaymentsAndDuesSection> {
  final TextEditingController _searchController = TextEditingController();
  _BillingWorkspaceTab _selectedTab = _BillingWorkspaceTab.allPayments;
  final List<Map<String, dynamic>> _items = <Map<String, dynamic>>[];
  bool _loading = true;
  String? _error;
  int _page = 1;
  int _lastPage = 1;
  String _paymentMode = '';

  bool get _hasMore => _page < _lastPage;

  bool get _canCollectPayment =>
      widget.appUser.activeRole == 'gym_owner' ||
      widget.appUser.activeRole == 'branch_manager' ||
      widget.appUser.hasAnyPermission(['collect_payment']);

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load({bool reset = false}) async {
    if (_selectedTab == _BillingWorkspaceTab.collectPayment) {
      setState(() {
        _loading = false;
        _error = null;
        if (reset) {
          _items.clear();
          _page = 1;
          _lastPage = 1;
        }
      });
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      if (reset) {
        _page = 1;
        _lastPage = 1;
        _items.clear();
      }
    });

    try {
      final response = await (_usesPaymentEndpoint()
          ? widget.repository.fetchGymPayments(
              page: _page,
              perPage: 15,
              queryParameters: _queryParameters(),
            )
          : widget.repository.fetchGymDues(
              page: _page,
              perPage: 15,
              queryParameters: _queryParameters(),
            ));

      final filteredItems = _filterItemsForTab(response.items);
      if (!mounted) {
        return;
      }
      setState(() {
        if (_page == 1) {
          _items
            ..clear()
            ..addAll(filteredItems);
        } else {
          _items.addAll(filteredItems);
        }
        _page = response.currentPage;
        _lastPage = response.lastPage;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  bool _usesPaymentEndpoint() {
    return _selectedTab == _BillingWorkspaceTab.allPayments ||
        _selectedTab == _BillingWorkspaceTab.paid;
  }

  Map<String, dynamic>? _queryParameters() {
    final params = <String, dynamic>{};
    final search = _searchController.text.trim();
    if (search.isNotEmpty) {
      params['member_search'] = search;
      params['search'] = search;
    }

    if (_usesPaymentEndpoint() && _paymentMode.isNotEmpty) {
      params['payment_mode'] = _paymentMode;
    }

    switch (_selectedTab) {
      case _BillingWorkspaceTab.paid:
        params['payment_status'] = 'paid';
        break;
      case _BillingWorkspaceTab.overdue:
        params['overdue_only'] = true;
        params['payment_status'] = 'overdue';
        break;
      case _BillingWorkspaceTab.partialPayments:
        params['payment_status'] = 'partial';
        break;
      case _BillingWorkspaceTab.pendingDues:
      case _BillingWorkspaceTab.allPayments:
      case _BillingWorkspaceTab.collectPayment:
        break;
    }

    return params.isEmpty ? null : params;
  }

  List<Map<String, dynamic>> _filterItemsForTab(
    List<Map<String, dynamic>> items,
  ) {
    if (_selectedTab == _BillingWorkspaceTab.pendingDues) {
      return items
          .where(
            (item) =>
                item['payment_status']?.toString().toLowerCase() != 'overdue',
          )
          .toList();
    }
    return items;
  }

  Future<void> _openCollectPaymentForRecord(Map<String, dynamic> item) async {
    final member = _recordMap(item['member']);
    final membership = _recordMap(item['membership']);
    await widget.onOpenForm(
      _AdminFormType.payment,
      prefill: {
        'member_id':
            (item['member_id'] as num?)?.toInt() ??
            (member['id'] as num?)?.toInt(),
        'member_membership_id':
            (item['member_membership_id'] as num?)?.toInt() ??
            (membership['id'] as num?)?.toInt() ??
            (item['id'] as num?)?.toInt(),
      },
    );
    await _load(reset: true);
  }

  Future<void> _openMemberHistory(Map<String, dynamic> item) async {
    final member = _recordMap(item['member']);
    final memberId =
        (item['member_id'] as num?)?.toInt() ?? (member['id'] as num?)?.toInt();
    if (memberId == null) {
      return;
    }
    await widget.onOpenMemberDetail({
      'id': memberId,
      'name': member['name'] ?? item['member_name'] ?? 'Member',
      'email': member['email'] ?? '',
      'member_profile': {
        'current_membership_id':
            (item['member_membership_id'] as num?)?.toInt() ??
            (item['id'] as num?)?.toInt(),
      },
    });
  }

  @override
  Widget build(BuildContext context) {
    final dueSummary = _dueSummary();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Payments and Dues',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(
                'Track collections, review outstanding dues, and record partial or full payments without leaving the gym billing workspace.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _MiniHighlight(
                    label: 'Due members',
                    value: '${dueSummary.memberCount}',
                  ),
                  _MiniHighlight(
                    label: 'Pending dues',
                    value: _formatCurrency(dueSummary.pending),
                  ),
                  _MiniHighlight(
                    label: 'Overdue dues',
                    value: _formatCurrency(dueSummary.overdue),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: _BillingWorkspaceTab.values.map((tab) {
                  final selected = tab == _selectedTab;
                  return ChoiceChip(
                    label: Text(_billingTabLabel(tab)),
                    selected: selected,
                    onSelected: (_) {
                      setState(() => _selectedTab = tab);
                      _load(reset: true);
                    },
                  );
                }).toList(),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        if (_selectedTab == _BillingWorkspaceTab.collectPayment)
          Expanded(
            child: _BillingCollectPaymentTab(
              appUser: widget.appUser,
              repository: widget.repository,
              canCollectPayment: _canCollectPayment,
              onPaymentRecorded: () => _load(reset: true),
            ),
          )
        else ...[
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchController,
                  onSubmitted: (_) => _load(reset: true),
                  decoration: const InputDecoration(
                    hintText: 'Search member by name, email, or phone',
                    prefixIcon: Icon(Icons.search_rounded),
                  ),
                ),
              ),
              if (_usesPaymentEndpoint()) ...[
                const SizedBox(width: 12),
                SizedBox(
                  width: 180,
                  child: DropdownButtonFormField<String>(
                    initialValue: _paymentMode.isEmpty ? null : _paymentMode,
                    decoration: const InputDecoration(
                      labelText: 'Payment mode',
                    ),
                    items: const [
                      DropdownMenuItem<String>(
                        value: '',
                        child: Text('All modes'),
                      ),
                      DropdownMenuItem<String>(
                        value: 'cash',
                        child: Text('Cash'),
                      ),
                      DropdownMenuItem<String>(
                        value: 'upi',
                        child: Text('UPI'),
                      ),
                      DropdownMenuItem<String>(
                        value: 'card',
                        child: Text('Card'),
                      ),
                      DropdownMenuItem<String>(
                        value: 'bank',
                        child: Text('Bank'),
                      ),
                    ],
                    onChanged: (value) {
                      setState(() => _paymentMode = value ?? '');
                      _load(reset: true);
                    },
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 16),
          Expanded(
            child: _isPermissionError(_error)
                ? const EmptyState(
                    title: 'Permission denied',
                    message:
                        'The current role needs view_billing access to review payments and dues.',
                    icon: Icons.lock_outline_rounded,
                  )
                : AsyncStateView(
                    isLoading: _loading && _items.isEmpty,
                    error: _error,
                    onRetry: () => _load(reset: true),
                    loadingChild: const _CollectionLoadingSkeleton(
                      destinationTitle: 'Payments',
                    ),
                    isEmpty: _items.isEmpty && !_loading,
                    emptyTitle: _selectedTab == _BillingWorkspaceTab.paid
                        ? 'No paid payments yet'
                        : _selectedTab == _BillingWorkspaceTab.overdue
                        ? 'No overdue dues'
                        : _selectedTab == _BillingWorkspaceTab.partialPayments
                        ? 'No partial payments'
                        : _selectedTab == _BillingWorkspaceTab.pendingDues
                        ? 'No pending dues'
                        : 'No payments recorded',
                    emptyMessage:
                        _selectedTab == _BillingWorkspaceTab.pendingDues
                        ? 'Outstanding dues will appear here when memberships still have a remaining balance.'
                        : 'This billing view does not have any records for the current filters yet.',
                    emptyIcon: Icons.payments_outlined,
                    child: RefreshIndicator(
                      onRefresh: () => _load(reset: true),
                      child: ListView.separated(
                        itemCount: _items.length + (_hasMore ? 1 : 0),
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          if (index >= _items.length) {
                            return Center(
                              child: OutlinedButton(
                                onPressed: () {
                                  setState(() => _page += 1);
                                  _load();
                                },
                                child: const Text('Load more'),
                              ),
                            );
                          }

                          final item = _items[index];
                          return RevealOnBuild(
                            delay: Duration(milliseconds: 40 * (index % 8)),
                            child: _usesPaymentEndpoint()
                                ? _GymPaymentRecordCard(
                                    item: item,
                                    onOpenMemberHistory: () =>
                                        _openMemberHistory(item),
                                  )
                                : _GymDueRecordCard(
                                    item: item,
                                    canCollectPayment: _canCollectPayment,
                                    onCollectPayment: () =>
                                        _openCollectPaymentForRecord(item),
                                    onOpenMemberHistory: () =>
                                        _openMemberHistory(item),
                                  ),
                          );
                        },
                      ),
                    ),
                  ),
          ),
        ],
      ],
    );
  }

  _BillingDueSummary _dueSummary() {
    double pending = 0;
    double overdue = 0;
    final memberIds = <int>{};
    for (final item in _items) {
      final due = _toDouble(item['due_amount']);
      final memberId = (item['member_id'] as num?)?.toInt();
      if (memberId != null) {
        memberIds.add(memberId);
      }
      pending += due;
      if (item['payment_status']?.toString().toLowerCase() == 'overdue') {
        overdue += due;
      }
    }
    return _BillingDueSummary(
      pending: pending,
      overdue: overdue,
      memberCount: memberIds.length,
    );
  }

  String _billingTabLabel(_BillingWorkspaceTab tab) {
    switch (tab) {
      case _BillingWorkspaceTab.allPayments:
        return 'All Payments';
      case _BillingWorkspaceTab.collectPayment:
        return 'Collect Payment';
      case _BillingWorkspaceTab.pendingDues:
        return 'Pending Dues';
      case _BillingWorkspaceTab.overdue:
        return 'Overdue';
      case _BillingWorkspaceTab.partialPayments:
        return 'Partial Payments';
      case _BillingWorkspaceTab.paid:
        return 'Paid';
    }
  }
}

class _BillingCollectPaymentTab extends StatefulWidget {
  const _BillingCollectPaymentTab({
    required this.appUser,
    required this.repository,
    required this.canCollectPayment,
    required this.onPaymentRecorded,
  });

  final AppUser appUser;
  final AdminRepository repository;
  final bool canCollectPayment;
  final Future<void> Function() onPaymentRecorded;

  @override
  State<_BillingCollectPaymentTab> createState() =>
      _BillingCollectPaymentTabState();
}

class _BillingCollectPaymentTabState extends State<_BillingCollectPaymentTab> {
  final TextEditingController _amountController = TextEditingController();
  final TextEditingController _dateController = TextEditingController(
    text: DateFormat('yyyy-MM-dd').format(DateTime.now()),
  );
  final TextEditingController _notesController = TextEditingController();
  List<Map<String, dynamic>> _members = const [];
  List<Map<String, dynamic>> _memberships = const [];
  List<Map<String, dynamic>> _history = const [];
  Map<String, dynamic> _membershipDetail = const {};
  bool _loading = true;
  bool _busy = false;
  bool _showSuccess = false;
  String? _error;
  int? _selectedMemberId;
  int? _selectedMembershipId;
  String _paymentMode = 'cash';

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  @override
  void dispose() {
    _amountController.dispose();
    _dateController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        widget.repository.fetchCollection('/gym/members', perPage: 100),
        widget.repository.fetchCollection(
          '/gym/memberships',
          perPage: 100,
          queryParameters: {'status': 'active'},
        ),
      ]);
      if (!mounted) {
        return;
      }
      setState(() {
        _members = results[0].items;
        _memberships = results[1].items;
      });
      if (_memberships.isNotEmpty) {
        final defaultMembership = _memberships.firstWhere(
          (membership) =>
              membership['payment_status']?.toString().toLowerCase() != 'paid',
          orElse: () => _memberships.first,
        );
        final defaultMembershipId = (defaultMembership['id'] as num?)?.toInt();
        final defaultMemberId = (defaultMembership['member_id'] as num?)
            ?.toInt();
        if (defaultMemberId != null) {
          _selectedMemberId = defaultMemberId;
        }
        if (defaultMembershipId != null) {
          _selectedMembershipId = defaultMembershipId;
          await _loadMembershipContext(defaultMembershipId);
        }
      }
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  List<Map<String, dynamic>> _availableMemberships() {
    if (_selectedMemberId == null) {
      return _memberships;
    }
    final memberships = _memberships
        .where(
          (membership) =>
              (membership['member_id'] as num?)?.toInt() == _selectedMemberId,
        )
        .toList();
    return memberships.isEmpty ? _memberships : memberships;
  }

  Future<void> _loadMembershipContext(int membershipId) async {
    try {
      final detail = await widget.repository.fetchMembershipDetail(
        membershipId,
      );
      final history = await widget.repository.fetchPaymentHistory(membershipId);
      if (!mounted) {
        return;
      }
      setState(() {
        _membershipDetail = Map<String, dynamic>.from(
          (detail['member_membership'] as Map?) ?? detail,
        );
        _history = history;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    }
  }

  Future<void> _submit() async {
    final amount = double.tryParse(_amountController.text.trim()) ?? 0;
    if (!widget.canCollectPayment) {
      setState(
        () => _error =
            'The collect_payment permission is required to record a payment.',
      );
      return;
    }
    if (_selectedMembershipId == null) {
      setState(
        () => _error = 'Select an active membership before collecting payment.',
      );
      return;
    }
    if (amount <= 0) {
      setState(() => _error = 'Enter a payment amount greater than zero.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
      _showSuccess = false;
    });
    try {
      await widget.repository.collectGymPayment({
        'member_id': _selectedMemberId,
        'member_membership_id': _selectedMembershipId,
        'amount': amount,
        'payment_mode': _paymentMode,
        'payment_date': _dateController.text.trim(),
        'notes': _notesController.text.trim(),
      });
      if (!mounted) {
        return;
      }
      _amountController.clear();
      _notesController.clear();
      setState(() => _showSuccess = true);
      if (_selectedMembershipId != null) {
        await _loadMembershipContext(_selectedMembershipId!);
      }
      await widget.onPaymentRecorded();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Payment recorded successfully.')),
        );
      }
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isPermissionError(_error)) {
      return const EmptyState(
        title: 'Permission denied',
        message:
            'The current role cannot access payment collection in this scope.',
        icon: Icons.lock_outline_rounded,
      );
    }

    if (_loading) {
      return const PremiumCard(
        child: LoadingState(label: 'Loading billing options...'),
      );
    }

    if (_error != null && _members.isEmpty && _memberships.isEmpty) {
      return PremiumCard(
        child: ErrorState(message: _error!, onRetry: _loadOptions),
      );
    }

    if (_memberships.isEmpty) {
      return const PremiumCard(
        child: EmptyState(
          title: 'No active memberships',
          message:
              'A member needs an active membership before the gym can collect a payment here.',
          icon: Icons.workspace_premium_outlined,
        ),
      );
    }

    final membership = _membershipDetail;
    final member = _recordMap(membership['member']);
    final plan = _recordMap(membership['membership_plan']);

    return ListView(
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Collect Payment',
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                  ),
                  AnimatedSwitcher(
                    duration: const Duration(milliseconds: 220),
                    child: _showSuccess
                        ? const StatusBadge(
                            key: ValueKey('payment-success'),
                            label: 'Payment saved',
                            color: AppColors.success,
                            icon: Icons.check_circle_rounded,
                          )
                        : const SizedBox.shrink(),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<int>(
                initialValue: _selectedMemberId,
                decoration: const InputDecoration(labelText: 'Member'),
                items: _members
                    .map(
                      (member) => DropdownMenuItem<int>(
                        value: (member['id'] as num?)?.toInt(),
                        child: Text(member['name']?.toString() ?? 'Member'),
                      ),
                    )
                    .toList(),
                onChanged: widget.canCollectPayment
                    ? (value) async {
                        setState(() {
                          _selectedMemberId = value;
                          _showSuccess = false;
                        });
                        final membership = _availableMemberships()
                            .cast<Map<String, dynamic>?>()
                            .firstWhere(
                              (record) =>
                                  (record?['member_id'] as num?)?.toInt() ==
                                  value,
                              orElse: () => null,
                            );
                        final membershipId = (membership?['id'] as num?)
                            ?.toInt();
                        setState(() => _selectedMembershipId = membershipId);
                        if (membershipId != null) {
                          await _loadMembershipContext(membershipId);
                        }
                      }
                    : null,
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<int>(
                initialValue: _selectedMembershipId,
                decoration: const InputDecoration(
                  labelText: 'Active membership',
                ),
                items: _availableMemberships()
                    .map(
                      (membership) => DropdownMenuItem<int>(
                        value: (membership['id'] as num?)?.toInt(),
                        child: Text(
                          '${_recordMap(membership['member'])['name'] ?? 'Member'} • ${_recordMap(membership['membership_plan'])['name'] ?? 'Plan'}',
                        ),
                      ),
                    )
                    .toList(),
                onChanged: widget.canCollectPayment
                    ? (value) async {
                        setState(() {
                          _selectedMembershipId = value;
                          _showSuccess = false;
                        });
                        if (value != null) {
                          await _loadMembershipContext(value);
                        }
                      }
                    : null,
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _amountController,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: const InputDecoration(labelText: 'Amount'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: _paymentMode,
                      decoration: const InputDecoration(
                        labelText: 'Payment mode',
                      ),
                      items: const ['cash', 'upi', 'card', 'bank']
                          .map(
                            (mode) => DropdownMenuItem<String>(
                              value: mode,
                              child: Text(mode.toUpperCase()),
                            ),
                          )
                          .toList(),
                      onChanged: widget.canCollectPayment
                          ? (value) =>
                                setState(() => _paymentMode = value ?? 'cash')
                          : null,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _dateController,
                decoration: const InputDecoration(
                  labelText: 'Payment date (YYYY-MM-DD)',
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _notesController,
                minLines: 2,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Notes'),
              ),
              if (_error != null && !_isPermissionError(_error)) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: Theme.of(
                    context,
                  ).textTheme.bodySmall?.copyWith(color: AppColors.error),
                ),
              ],
              const SizedBox(height: 16),
              if (member.isNotEmpty || plan.isNotEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.surface.withValues(alpha: 0.55),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.strokeStrong),
                  ),
                  child: Wrap(
                    spacing: 16,
                    runSpacing: 16,
                    children: [
                      _MiniMetric(
                        label: 'Member',
                        value: member['name']?.toString() ?? '--',
                        icon: Icons.person_rounded,
                      ),
                      _MiniMetric(
                        label: 'Plan',
                        value: plan['name']?.toString() ?? '--',
                        icon: Icons.workspace_premium_rounded,
                      ),
                      _MiniMetric(
                        label: 'Final payable',
                        value: _formatCurrency(
                          membership['final_payable_amount'],
                        ),
                        icon: Icons.receipt_long_rounded,
                      ),
                      _MiniMetric(
                        label: 'Due amount',
                        value: _formatCurrency(membership['due_amount']),
                        icon: Icons.payments_outlined,
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: 16),
              GradientButton(
                label: 'Collect Payment',
                icon: Icons.currency_rupee_rounded,
                loading: _busy,
                expanded: true,
                onPressed: widget.canCollectPayment ? _submit : null,
              ),
              if (!widget.canCollectPayment) ...[
                const SizedBox(height: 12),
                const EmptyState(
                  title: 'Collection disabled',
                  message:
                      'This role can review billing but needs collect_payment permission to record a new payment.',
                  icon: Icons.lock_outline_rounded,
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Payment History Timeline',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              if (_history.isEmpty)
                const EmptyState(
                  title: 'No payments logged yet',
                  message:
                      'The payment timeline for this membership will appear here after the first collection.',
                  icon: Icons.history_rounded,
                )
              else
                _TimelineCard(
                  items: _history.take(8).map((payment) {
                    return _TimelineEntry(
                      title:
                          '${payment['payment_mode']?.toString().toUpperCase() ?? 'PAYMENT'} • ${payment['status']?.toString().toUpperCase() ?? 'RECORDED'}',
                      subtitle: payment['notes']?.toString().isNotEmpty == true
                          ? payment['notes'].toString()
                          : 'Recorded against the selected membership.',
                      valueLabel: 'Amount',
                      value: _formatCurrency(payment['amount']),
                      meta: _formatDateTime(
                        payment['payment_date'] ?? payment['paid_at'],
                      ),
                      iconName: 'payments',
                    );
                  }).toList(),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _GymPaymentRecordCard extends StatelessWidget {
  const _GymPaymentRecordCard({
    required this.item,
    required this.onOpenMemberHistory,
  });

  final Map<String, dynamic> item;
  final VoidCallback onOpenMemberHistory;

  @override
  Widget build(BuildContext context) {
    final member = _recordMap(item['member']);
    final membership = _recordMap(item['membership']);
    final plan = _recordMap(membership['membership_plan']);
    final branch = _recordMap(item['branch']);
    final collector = _recordMap(item['collector']);
    final paymentStatus = item['payment_status']?.toString() ?? 'paid';

    return PremiumCard(
      onTap: onOpenMemberHistory,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      member['name']?.toString() ?? 'Payment',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      plan['name']?.toString() ?? 'Membership payment',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              _PaymentStatusBadge(status: paymentStatus),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 16,
            runSpacing: 16,
            children: [
              _MiniMetric(
                label: 'Amount',
                value: _formatCurrency(item['amount']),
                icon: Icons.currency_rupee_rounded,
              ),
              _MiniMetric(
                label: 'Mode',
                value: item['payment_mode']?.toString().toUpperCase() ?? '--',
                icon: Icons.account_balance_wallet_outlined,
              ),
              _MiniMetric(
                label: 'Branch',
                value: branch['name']?.toString() ?? '--',
                icon: Icons.location_on_outlined,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _InlineBadge(
                label:
                    'Date ${prettyDateTime(item['payment_date'] ?? item['paid_at'])}',
              ),
              if (membership['custom_fee_enabled'] == true)
                const _InlineBadge(label: 'Custom fee applied'),
              if (collector['name']?.toString().isNotEmpty == true)
                _InlineBadge(label: 'Collected by ${collector['name']}'),
            ],
          ),
          if (item['notes']?.toString().isNotEmpty == true) ...[
            const SizedBox(height: 12),
            Text(
              item['notes'].toString(),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ],
      ),
    );
  }
}

class _GymDueRecordCard extends StatelessWidget {
  const _GymDueRecordCard({
    required this.item,
    required this.canCollectPayment,
    required this.onCollectPayment,
    required this.onOpenMemberHistory,
  });

  final Map<String, dynamic> item;
  final bool canCollectPayment;
  final VoidCallback onCollectPayment;
  final VoidCallback onOpenMemberHistory;

  @override
  Widget build(BuildContext context) {
    final member = _recordMap(item['member']);
    final plan = _recordMap(item['membership_plan']);
    final branchName = _recordMap(item['branch'])['name']?.toString() ?? '--';
    final dueAmount = _toDouble(item['due_amount']);
    final overdue =
        item['payment_status']?.toString().toLowerCase() == 'overdue';

    return PremiumCard(
      onTap: onOpenMemberHistory,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      member['name']?.toString() ?? 'Member due',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      plan['name']?.toString() ?? 'Membership plan',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              _PaymentStatusBadge(
                status: item['payment_status']?.toString() ?? 'pending',
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Final payable',
                  value: _formatCurrency(item['final_payable_amount']),
                  icon: Icons.receipt_long_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Paid',
                  value: _formatCurrency(item['amount_paid']),
                  icon: Icons.check_circle_outline_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Due',
                  value: _formatCurrency(dueAmount),
                  icon: Icons.payments_outlined,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _InlineBadge(label: 'Due date ${item['due_date'] ?? '--'}'),
              _InlineBadge(label: branchName),
              if (item['custom_fee_enabled'] == true)
                const _InlineBadge(label: 'Custom fee'),
              if (overdue) const _InlineBadge(label: 'Attention'),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onOpenMemberHistory,
                  icon: const Icon(Icons.history_rounded),
                  label: const Text('History'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: GradientButton(
                  label: 'Collect Payment',
                  icon: Icons.currency_rupee_rounded,
                  expanded: true,
                  onPressed: canCollectPayment ? onCollectPayment : null,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PaymentStatusBadge extends StatelessWidget {
  const _PaymentStatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final normalized = status.toLowerCase();
    Color color;
    IconData icon;
    switch (normalized) {
      case 'paid':
        color = AppColors.success;
        icon = Icons.check_circle_rounded;
        break;
      case 'partial':
        color = AppColors.warning;
        icon = Icons.timelapse_rounded;
        break;
      case 'overdue':
        color = AppColors.error;
        icon = Icons.warning_amber_rounded;
        break;
      default:
        color = AppColors.info;
        icon = Icons.schedule_rounded;
    }

    return StatusBadge(
      label: _dashboardTitleCase(status.replaceAll('_', ' ')),
      color: color,
      icon: icon,
    );
  }
}

class _BillingDueSummary {
  const _BillingDueSummary({
    required this.pending,
    required this.overdue,
    required this.memberCount,
  });

  final double pending;
  final double overdue;
  final int memberCount;
}

enum _AttendanceWorkspaceTab { today, manual, qr, history }

class _AttendanceWorkspaceSection extends StatefulWidget {
  const _AttendanceWorkspaceSection({
    super.key,
    required this.appUser,
    required this.repository,
    required this.onOpenForm,
    required this.onOpenMemberDetail,
  });

  final AppUser appUser;
  final AdminRepository repository;
  final Future<void> Function(
    _AdminFormType? type, {
    Map<String, dynamic>? prefill,
  })
  onOpenForm;
  final Future<void> Function(Map<String, dynamic>) onOpenMemberDetail;

  @override
  State<_AttendanceWorkspaceSection> createState() =>
      _AttendanceWorkspaceSectionState();
}

class _AttendanceWorkspaceSectionState
    extends State<_AttendanceWorkspaceSection> {
  _AttendanceWorkspaceTab _selectedTab = _AttendanceWorkspaceTab.today;
  List<Map<String, dynamic>> _branches = const [];
  List<Map<String, dynamic>> _members = const [];
  List<Map<String, dynamic>> _todayItems = const [];
  List<Map<String, dynamic>> _historyItems = const [];
  bool _loadingOptions = true;
  bool _loadingToday = true;
  bool _loadingHistory = false;
  bool _busy = false;
  String? _error;
  String? _successMessage;
  final TextEditingController _historySearchController =
      TextEditingController();
  final TextEditingController _manualNotesController = TextEditingController();
  final TextEditingController _qrNotesController = TextEditingController();
  final TextEditingController _qrPayloadController = TextEditingController();
  final TextEditingController _startDateController = TextEditingController();
  final TextEditingController _endDateController = TextEditingController();
  int? _selectedBranchId;
  int? _selectedMemberId;
  String _selectedMethod = '';
  int? _manualBranchId;
  int? _manualMemberId;
  int? _qrBranchId;
  String? _lastQrPayload;

  bool get _canManageAttendance =>
      widget.appUser.activeRole == 'gym_owner' ||
      widget.appUser.activeRole == 'branch_manager' ||
      widget.appUser.hasAnyPermission(['manage_attendance']);

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  @override
  void dispose() {
    _historySearchController.dispose();
    _manualNotesController.dispose();
    _qrNotesController.dispose();
    _qrPayloadController.dispose();
    _startDateController.dispose();
    _endDateController.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    setState(() {
      _loadingOptions = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        widget.repository.fetchCollection('/gym/branches', perPage: 100),
        widget.repository.fetchCollection('/gym/members', perPage: 100),
      ]);
      if (!mounted) {
        return;
      }
      final branches = results[0].items;
      final members = results[1].items;
      setState(() {
        _branches = branches;
        _members = members;
        _selectedBranchId ??= (branches.firstOrNull?['id'] as num?)?.toInt();
        _manualBranchId ??= _selectedBranchId;
        _qrBranchId ??= _selectedBranchId;
      });
      await _loadToday();
      await _loadHistory();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loadingOptions = false);
      }
    }
  }

  Future<void> _loadToday() async {
    setState(() {
      _loadingToday = true;
      _error = null;
    });
    try {
      final payload = await widget.repository.fetchGymAttendanceToday(
        queryParameters: {
          if (_selectedBranchId != null) 'branch_id': _selectedBranchId,
        },
      );
      if (!mounted) {
        return;
      }
      final items = (payload['items'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .where((item) {
            if (_selectedMemberId != null &&
                (item['member_id'] as num?)?.toInt() != _selectedMemberId) {
              return false;
            }
            if (_selectedMethod.isNotEmpty &&
                item['check_in_method']?.toString() != _selectedMethod) {
              return false;
            }
            final search = _historySearchController.text.trim().toLowerCase();
            if (search.isEmpty) {
              return true;
            }
            final member = _recordMap(item['member']);
            return (member['name']?.toString().toLowerCase().contains(search) ??
                    false) ||
                (member['email']?.toString().toLowerCase().contains(search) ??
                    false);
          })
          .toList();
      setState(() => _todayItems = items);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loadingToday = false);
      }
    }
  }

  Future<void> _loadHistory() async {
    setState(() {
      _loadingHistory = true;
      _error = null;
    });
    try {
      final response = await widget.repository.fetchGymAttendance(
        page: 1,
        perPage: 50,
        queryParameters: {
          if (_selectedBranchId != null) 'branch_id': _selectedBranchId,
          if (_selectedMemberId != null) 'member_id': _selectedMemberId,
          if (_selectedMethod.isNotEmpty) 'check_in_method': _selectedMethod,
          if (_historySearchController.text.trim().isNotEmpty)
            'member_search': _historySearchController.text.trim(),
          if (_startDateController.text.trim().isNotEmpty)
            'start_date': _startDateController.text.trim(),
          if (_endDateController.text.trim().isNotEmpty)
            'end_date': _endDateController.text.trim(),
        },
      );
      if (!mounted) {
        return;
      }
      setState(() => _historyItems = response.items);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loadingHistory = false);
      }
    }
  }

  Future<void> _refreshActiveTab() async {
    if (_selectedTab == _AttendanceWorkspaceTab.today) {
      await _loadToday();
    } else if (_selectedTab == _AttendanceWorkspaceTab.history) {
      await _loadHistory();
    }
  }

  Future<void> _submitManualAttendance() async {
    if (!_canManageAttendance) {
      setState(
        () => _error =
            'The manage_attendance permission is required to mark attendance.',
      );
      return;
    }
    if (_manualBranchId == null || _manualMemberId == null) {
      setState(
        () => _error =
            'Select both branch and member before recording attendance.',
      );
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _successMessage = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.repository.manualAttendance({
        'branch_id': _manualBranchId,
        'member_id': _manualMemberId,
        'checked_in_at': DateTime.now().toIso8601String(),
        'notes': _manualNotesController.text.trim(),
        'source_device': 'flutter_admin_app',
      });
      if (!mounted) {
        return;
      }
      _manualNotesController.clear();
      setState(() => _successMessage = 'Manual attendance recorded.');
      await _loadToday();
      await _loadHistory();
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Manual attendance recorded successfully.'),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _submitQrAttendance() async {
    if (!_canManageAttendance) {
      setState(
        () => _error =
            'The manage_attendance permission is required to scan attendance.',
      );
      return;
    }
    if (_qrBranchId == null) {
      setState(() => _error = 'Select a branch before scanning attendance.');
      return;
    }
    if (_qrPayloadController.text.trim().isEmpty) {
      setState(() => _error = 'Scan or paste a QR payload before submitting.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _successMessage = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.repository.scanAttendance({
        'branch_id': _qrBranchId,
        'qr_payload': _qrPayloadController.text.trim(),
        'notes': _qrNotesController.text.trim(),
        'source_device': 'flutter_admin_app',
      });
      if (!mounted) {
        return;
      }
      _qrNotesController.clear();
      _qrPayloadController.clear();
      _lastQrPayload = null;
      setState(() => _successMessage = 'QR attendance recorded.');
      await _loadToday();
      await _loadHistory();
      messenger.showSnackBar(
        const SnackBar(content: Text('QR attendance recorded successfully.')),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  void _onQrDetected(BarcodeCapture capture) {
    final rawValue = capture.barcodes.firstOrNull?.rawValue?.trim();
    if (rawValue == null || rawValue.isEmpty || rawValue == _lastQrPayload) {
      return;
    }
    setState(() {
      _lastQrPayload = rawValue;
      _qrPayloadController.text = rawValue;
      _successMessage = null;
      _error = null;
    });
  }

  List<Map<String, dynamic>> _branchScopedMembers() {
    if (_manualBranchId == null &&
        _qrBranchId == null &&
        _selectedBranchId == null) {
      return _members;
    }
    final activeBranchId = _selectedTab == _AttendanceWorkspaceTab.manual
        ? _manualBranchId
        : _selectedTab == _AttendanceWorkspaceTab.qr
        ? _qrBranchId
        : _selectedBranchId;
    if (activeBranchId == null) {
      return _members;
    }
    final filtered = _members.where((member) {
      final profile = _recordMap(member['member_profile']);
      return (profile['branch_id'] as num?)?.toInt() == activeBranchId ||
          (member['branch_id'] as num?)?.toInt() == activeBranchId;
    }).toList();
    return filtered.isEmpty ? _members : filtered;
  }

  Future<void> _openMemberDetail(Map<String, dynamic> item) async {
    final member = _recordMap(item['member']);
    final memberId =
        (item['member_id'] as num?)?.toInt() ?? (member['id'] as num?)?.toInt();
    if (memberId == null) {
      return;
    }
    await widget.onOpenMemberDetail({
      'id': memberId,
      'name': member['name'] ?? 'Member',
      'email': member['email'] ?? '',
      'member_profile': {'branch_id': item['branch_id']},
    });
  }

  @override
  Widget build(BuildContext context) {
    final summaryCount = _selectedTab == _AttendanceWorkspaceTab.history
        ? _historyItems.length
        : _todayItems.length;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Attendance Control Room',
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                  ),
                  AnimatedSwitcher(
                    duration: const Duration(milliseconds: 220),
                    child: _successMessage != null
                        ? StatusBadge(
                            key: ValueKey(_successMessage),
                            label: _successMessage!,
                            color: AppColors.success,
                            icon: Icons.check_circle_rounded,
                          )
                        : const SizedBox.shrink(),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Track today’s check-ins, mark attendance manually, and handle QR-based entry from one scoped attendance workspace.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _MiniHighlight(
                    label: 'Visible branches',
                    value: '${_branches.length}',
                  ),
                  _MiniHighlight(label: 'Members', value: '${_members.length}'),
                  _MiniHighlight(label: 'Entries', value: '$summaryCount'),
                ],
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: _AttendanceWorkspaceTab.values.map((tab) {
                  return ChoiceChip(
                    label: Text(_tabLabel(tab)),
                    selected: _selectedTab == tab,
                    onSelected: (_) {
                      setState(() => _selectedTab = tab);
                      _refreshActiveTab();
                    },
                  );
                }).toList(),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        if (_isPermissionError(_error))
          const Expanded(
            child: EmptyState(
              title: 'Permission denied',
              message:
                  'The current role needs manage_attendance access to use this attendance workspace.',
              icon: Icons.lock_outline_rounded,
            ),
          )
        else if (_loadingOptions)
          const Expanded(
            child: PremiumCard(
              child: LoadingState(label: 'Loading attendance workspace...'),
            ),
          )
        else
          Expanded(child: _buildTabContent(context)),
      ],
    );
  }

  Widget _buildTabContent(BuildContext context) {
    switch (_selectedTab) {
      case _AttendanceWorkspaceTab.today:
        return Column(
          children: [
            _buildSharedFilters(showDateFilters: false),
            const SizedBox(height: 16),
            Expanded(
              child: AsyncStateView(
                isLoading: _loadingToday,
                error: _error,
                onRetry: _loadToday,
                loadingChild: const _CollectionLoadingSkeleton(
                  destinationTitle: 'Payments',
                ),
                isEmpty: _todayItems.isEmpty && !_loadingToday,
                emptyTitle: 'No check-ins today',
                emptyMessage:
                    'Today’s attendance activity will appear here once members start checking in.',
                emptyIcon: Icons.qr_code_scanner_rounded,
                child: RefreshIndicator(
                  onRefresh: _loadToday,
                  child: ListView.separated(
                    itemCount: _todayItems.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) => RevealOnBuild(
                      delay: Duration(milliseconds: 40 * (index % 8)),
                      child: _AttendanceLogCard(
                        item: _todayItems[index],
                        onTap: () => _openMemberDetail(_todayItems[index]),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      case _AttendanceWorkspaceTab.manual:
        return ListView(
          children: [
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Manual Attendance',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    initialValue: _manualBranchId,
                    decoration: const InputDecoration(labelText: 'Branch'),
                    items: _branches
                        .map(
                          (branch) => DropdownMenuItem<int>(
                            value: (branch['id'] as num?)?.toInt(),
                            child: Text(branch['name']?.toString() ?? 'Branch'),
                          ),
                        )
                        .toList(),
                    onChanged: _canManageAttendance
                        ? (value) => setState(() {
                            _manualBranchId = value;
                            _manualMemberId = null;
                          })
                        : null,
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<int>(
                    initialValue: _manualMemberId,
                    decoration: const InputDecoration(labelText: 'Member'),
                    items: _branchScopedMembers()
                        .map(
                          (member) => DropdownMenuItem<int>(
                            value: (member['id'] as num?)?.toInt(),
                            child: Text(member['name']?.toString() ?? 'Member'),
                          ),
                        )
                        .toList(),
                    onChanged: _canManageAttendance
                        ? (value) => setState(() => _manualMemberId = value)
                        : null,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _manualNotesController,
                    minLines: 2,
                    maxLines: 3,
                    decoration: const InputDecoration(labelText: 'Notes'),
                  ),
                  if (_error != null && !_isPermissionError(_error)) ...[
                    const SizedBox(height: 12),
                    Text(
                      _error!,
                      style: Theme.of(
                        context,
                      ).textTheme.bodySmall?.copyWith(color: AppColors.error),
                    ),
                  ],
                  const SizedBox(height: 16),
                  GradientButton(
                    label: 'Record Manual Check-in',
                    icon: Icons.how_to_reg_rounded,
                    loading: _busy,
                    expanded: true,
                    onPressed: _canManageAttendance
                        ? _submitManualAttendance
                        : null,
                  ),
                ],
              ),
            ),
          ],
        );
      case _AttendanceWorkspaceTab.qr:
        return ListView(
          children: [
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'QR Scanner',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    initialValue: _qrBranchId,
                    decoration: const InputDecoration(labelText: 'Branch'),
                    items: _branches
                        .map(
                          (branch) => DropdownMenuItem<int>(
                            value: (branch['id'] as num?)?.toInt(),
                            child: Text(branch['name']?.toString() ?? 'Branch'),
                          ),
                        )
                        .toList(),
                    onChanged: _canManageAttendance
                        ? (value) => setState(() => _qrBranchId = value)
                        : null,
                  ),
                  const SizedBox(height: 14),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(22),
                    child: SizedBox(
                      height: 300,
                      child: MobileScanner(onDetect: _onQrDetected),
                    ),
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _qrPayloadController,
                    decoration: const InputDecoration(labelText: 'QR payload'),
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _qrNotesController,
                    minLines: 2,
                    maxLines: 3,
                    decoration: const InputDecoration(labelText: 'Notes'),
                  ),
                  if (_error != null && !_isPermissionError(_error)) ...[
                    const SizedBox(height: 12),
                    Text(
                      _error!,
                      style: Theme.of(
                        context,
                      ).textTheme.bodySmall?.copyWith(color: AppColors.error),
                    ),
                  ],
                  const SizedBox(height: 16),
                  GradientButton(
                    label: 'Submit QR Check-in',
                    icon: Icons.qr_code_scanner_rounded,
                    loading: _busy,
                    expanded: true,
                    onPressed: _canManageAttendance
                        ? _submitQrAttendance
                        : null,
                  ),
                ],
              ),
            ),
          ],
        );
      case _AttendanceWorkspaceTab.history:
        return Column(
          children: [
            _buildSharedFilters(showDateFilters: true),
            const SizedBox(height: 16),
            Expanded(
              child: AsyncStateView(
                isLoading: _loadingHistory,
                error: _error,
                onRetry: _loadHistory,
                loadingChild: const _CollectionLoadingSkeleton(
                  destinationTitle: 'Payments',
                ),
                isEmpty: _historyItems.isEmpty && !_loadingHistory,
                emptyTitle: 'No attendance history',
                emptyMessage:
                    'Attendance history will appear here once the selected scope has recorded check-ins.',
                emptyIcon: Icons.history_rounded,
                child: RefreshIndicator(
                  onRefresh: _loadHistory,
                  child: ListView.separated(
                    itemCount: _historyItems.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) => RevealOnBuild(
                      delay: Duration(milliseconds: 40 * (index % 8)),
                      child: _AttendanceLogCard(
                        item: _historyItems[index],
                        onTap: () => _openMemberDetail(_historyItems[index]),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
    }
  }

  Widget _buildSharedFilters({required bool showDateFilters}) {
    return PremiumCard(
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _historySearchController,
                  onSubmitted: (_) {
                    _loadToday();
                    _loadHistory();
                  },
                  decoration: const InputDecoration(
                    hintText: 'Search member name or email',
                    prefixIcon: Icon(Icons.search_rounded),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              SizedBox(
                width: 180,
                child: DropdownButtonFormField<int?>(
                  initialValue: _selectedBranchId,
                  decoration: const InputDecoration(labelText: 'Branch'),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('All branches'),
                    ),
                    ..._branches.map(
                      (branch) => DropdownMenuItem<int?>(
                        value: (branch['id'] as num?)?.toInt(),
                        child: Text(branch['name']?.toString() ?? 'Branch'),
                      ),
                    ),
                  ],
                  onChanged: (value) {
                    setState(() => _selectedBranchId = value);
                    _loadToday();
                    _loadHistory();
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<int?>(
                  initialValue: _selectedMemberId,
                  decoration: const InputDecoration(labelText: 'Member'),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('All members'),
                    ),
                    ..._members.map(
                      (member) => DropdownMenuItem<int?>(
                        value: (member['id'] as num?)?.toInt(),
                        child: Text(member['name']?.toString() ?? 'Member'),
                      ),
                    ),
                  ],
                  onChanged: (value) {
                    setState(() => _selectedMemberId = value);
                    _loadToday();
                    _loadHistory();
                  },
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: _selectedMethod.isEmpty
                      ? null
                      : _selectedMethod,
                  decoration: const InputDecoration(labelText: 'Method'),
                  items: const [
                    DropdownMenuItem<String>(
                      value: '',
                      child: Text('All methods'),
                    ),
                    DropdownMenuItem<String>(
                      value: 'manual',
                      child: Text('Manual'),
                    ),
                    DropdownMenuItem<String>(value: 'qr', child: Text('QR')),
                  ],
                  onChanged: (value) {
                    setState(() => _selectedMethod = value ?? '');
                    _loadToday();
                    _loadHistory();
                  },
                ),
              ),
            ],
          ),
          if (showDateFilters) ...[
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _startDateController,
                    decoration: const InputDecoration(
                      labelText: 'Start date (YYYY-MM-DD)',
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _endDateController,
                    decoration: const InputDecoration(
                      labelText: 'End date (YYYY-MM-DD)',
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Align(
              alignment: Alignment.centerRight,
              child: OutlinedButton.icon(
                onPressed: _loadHistory,
                icon: const Icon(Icons.filter_alt_rounded),
                label: const Text('Apply filters'),
              ),
            ),
          ],
        ],
      ),
    );
  }

  String _tabLabel(_AttendanceWorkspaceTab tab) {
    switch (tab) {
      case _AttendanceWorkspaceTab.today:
        return 'Today Check-ins';
      case _AttendanceWorkspaceTab.manual:
        return 'Manual Attendance';
      case _AttendanceWorkspaceTab.qr:
        return 'QR Scanner';
      case _AttendanceWorkspaceTab.history:
        return 'Attendance History';
    }
  }
}

class _AttendanceLogCard extends StatelessWidget {
  const _AttendanceLogCard({required this.item, required this.onTap});

  final Map<String, dynamic> item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final member = _recordMap(item['member']);
    final branch = _recordMap(item['branch']);
    final checkedInBy = _recordMap(item['checked_in_by_user']);
    final method = item['check_in_method']?.toString() ?? 'manual';

    return PremiumCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      member['name']?.toString() ?? 'Attendance',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      branch['name']?.toString() ?? 'Branch not available',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: method.toUpperCase(),
                color: method == 'qr' ? AppColors.info : AppColors.success,
                icon: method == 'qr'
                    ? Icons.qr_code_scanner_rounded
                    : Icons.how_to_reg_rounded,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 16,
            runSpacing: 16,
            children: [
              _MiniMetric(
                label: 'Check-in',
                value: _formatDateTime(item['checked_in_at']),
                icon: Icons.schedule_rounded,
              ),
              _MiniMetric(
                label: 'Checked by',
                value: checkedInBy['name']?.toString() ?? 'System',
                icon: Icons.badge_rounded,
              ),
            ],
          ),
          if (item['notes']?.toString().isNotEmpty == true) ...[
            const SizedBox(height: 12),
            Text(
              item['notes'].toString(),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ],
      ),
    );
  }
}

enum _TrialStatusFilter { pending, accepted, completed, converted, rejected }

class _TrialRequestsWorkspaceSection extends StatefulWidget {
  const _TrialRequestsWorkspaceSection({
    super.key,
    required this.appUser,
    required this.repository,
    required this.onOpenMemberDetail,
  });

  final AppUser appUser;
  final AdminRepository repository;
  final Future<void> Function(Map<String, dynamic>) onOpenMemberDetail;

  @override
  State<_TrialRequestsWorkspaceSection> createState() =>
      _TrialRequestsWorkspaceSectionState();
}

class _TrialRequestsWorkspaceSectionState
    extends State<_TrialRequestsWorkspaceSection> {
  final TextEditingController _searchController = TextEditingController();
  List<Map<String, dynamic>> _trials = const [];
  List<Map<String, dynamic>> _branches = const [];
  List<Map<String, dynamic>> _trainers = const [];
  bool _loading = true;
  String? _error;
  _TrialStatusFilter _status = _TrialStatusFilter.pending;
  int? _branchId;
  int? _trainerId;

  @override
  void initState() {
    super.initState();
    _loadOptionsAndTrials();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadOptionsAndTrials() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        widget.repository.fetchCollection('/gym/branches', perPage: 100),
        widget.repository.fetchCollection('/gym/trainers', perPage: 100),
        _fetchTrials(),
      ]);
      if (!mounted) {
        return;
      }
      final branches =
          (results[0] as PaginatedResponse<Map<String, dynamic>>).items;
      final trainers =
          (results[1] as PaginatedResponse<Map<String, dynamic>>).items;
      setState(() {
        _branches = branches;
        _trainers = trainers;
        _trials = results[2] as List<Map<String, dynamic>>;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<List<Map<String, dynamic>>> _fetchTrials() async {
    final response = await widget.repository.fetchGymTrialRequests(
      page: 1,
      perPage: 50,
      queryParameters: {
        'status': _status.name,
        if (_branchId != null) 'branch_id': _branchId,
        if (_trainerId != null) 'assigned_trainer_id': _trainerId,
        if (_searchController.text.trim().isNotEmpty)
          'search': _searchController.text.trim(),
      },
    );
    return response.items;
  }

  Future<void> _reloadTrials() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await _fetchTrials();
      if (!mounted) {
        return;
      }
      setState(() => _trials = items);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _runStatusAction(
    Map<String, dynamic> trial,
    String action, {
    String? notes,
  }) async {
    final trialId = (trial['id'] as num?)?.toInt();
    if (trialId == null) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    try {
      switch (action) {
        case 'accept':
          await widget.repository.acceptGymTrialRequest(trialId, notes: notes);
          break;
        case 'reject':
          await widget.repository.rejectGymTrialRequest(trialId, notes: notes);
          break;
        case 'complete':
          await widget.repository.completeGymTrialRequest(
            trialId,
            notes: notes,
          );
          break;
      }
      if (!mounted) {
        return;
      }
      await _reloadTrials();
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            'Trial request ${_dashboardTitleCase(action)}ed successfully.',
          ),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<String?> _promptForNotes({
    required String title,
    required String confirmLabel,
  }) async {
    final controller = TextEditingController();
    String? result;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          left: 24,
          right: 24,
          top: 24,
          bottom: MediaQuery.of(context).viewInsets.bottom + 24,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 12),
            TextFormField(
              controller: controller,
              minLines: 3,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Notes'),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('Cancel'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: GradientButton(
                    label: confirmLabel,
                    expanded: true,
                    onPressed: () {
                      result = controller.text.trim();
                      Navigator.of(context).pop();
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
    controller.dispose();
    return result;
  }

  Future<void> _confirmAndRun(Map<String, dynamic> trial, String action) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: '${_dashboardTitleCase(action)} Trial Request',
        message: '${_dashboardTitleCase(action)} this trial request now?',
        confirmLabel: _dashboardTitleCase(action),
      ),
    );
    if (confirmed != true) {
      return;
    }
    if (!mounted) {
      return;
    }
    final notes = await _promptForNotes(
      title: '${_dashboardTitleCase(action)} Notes',
      confirmLabel: _dashboardTitleCase(action),
    );
    if (!mounted) {
      return;
    }
    await _runStatusAction(trial, action, notes: notes);
  }

  Future<void> _showAssignTrainerSheet(Map<String, dynamic> trial) async {
    final trialId = (trial['id'] as num?)?.toInt();
    if (trialId == null) {
      return;
    }
    int? selectedTrainerId =
        (_recordMap(trial['assigned_trainer'])['id'] as num?)?.toInt() ??
        (trial['assigned_trainer_id'] as num?)?.toInt();
    final notesController = TextEditingController();
    var busy = false;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => Padding(
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Assign Trainer',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int?>(
                initialValue: selectedTrainerId,
                decoration: const InputDecoration(
                  labelText: 'Assigned trainer',
                ),
                items: [
                  const DropdownMenuItem<int?>(
                    value: null,
                    child: Text('Unassigned'),
                  ),
                  ..._trainers.map(
                    (trainer) => DropdownMenuItem<int?>(
                      value: (trainer['id'] as num?)?.toInt(),
                      child: Text(trainer['name']?.toString() ?? 'Trainer'),
                    ),
                  ),
                ],
                onChanged: (value) =>
                    setModalState(() => selectedTrainerId = value),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: notesController,
                minLines: 2,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Notes'),
              ),
              const SizedBox(height: 16),
              GradientButton(
                label: 'Save Assignment',
                loading: busy,
                expanded: true,
                onPressed: () async {
                  final navigator = Navigator.of(context);
                  final messenger = ScaffoldMessenger.of(context);
                  setModalState(() => busy = true);
                  try {
                    await widget.repository.assignGymTrialTrainer(
                      trialId,
                      assignedTrainerId: selectedTrainerId,
                      notes: notesController.text.trim(),
                    );
                    if (!mounted) {
                      return;
                    }
                    navigator.pop();
                    await _reloadTrials();
                    messenger.showSnackBar(
                      const SnackBar(
                        content: Text(
                          'Trainer assignment updated successfully.',
                        ),
                      ),
                    );
                  } catch (exception) {
                    if (mounted) {
                      messenger.showSnackBar(
                        SnackBar(content: Text(exception.toString())),
                      );
                      setModalState(() => busy = false);
                    }
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
    notesController.dispose();
  }

  Future<void> _showConvertSheet(Map<String, dynamic> trial) async {
    final trialId = (trial['id'] as num?)?.toInt();
    if (trialId == null) {
      return;
    }
    final branch = _recordMap(trial['branch']);
    final assignedTrainer = _recordMap(trial['assigned_trainer']);
    final nameController = TextEditingController(
      text: trial['name']?.toString() ?? '',
    );
    final emailController = TextEditingController(
      text: trial['email']?.toString() ?? '',
    );
    final phoneController = TextEditingController(
      text: trial['phone']?.toString() ?? '',
    );
    final existingUserController = TextEditingController();
    final notesController = TextEditingController();
    int? selectedBranchId =
        (trial['branch_id'] as num?)?.toInt() ??
        (branch['id'] as num?)?.toInt();
    int? selectedTrainerId =
        (trial['assigned_trainer_id'] as num?)?.toInt() ??
        (assignedTrainer['id'] as num?)?.toInt();
    var busy = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) => Padding(
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
          ),
          child: ListView(
            shrinkWrap: true,
            children: [
              Text(
                'Convert to Member',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: existingUserController,
                decoration: const InputDecoration(
                  labelText: 'Existing user id',
                  helperText:
                      'Optional. Use this only when you want to link an existing user.',
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: nameController,
                decoration: const InputDecoration(labelText: 'Name'),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: emailController,
                decoration: const InputDecoration(labelText: 'Email'),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: phoneController,
                decoration: const InputDecoration(labelText: 'Phone'),
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<int?>(
                initialValue: selectedBranchId,
                decoration: const InputDecoration(labelText: 'Branch'),
                items: _branches
                    .map(
                      (branchItem) => DropdownMenuItem<int?>(
                        value: (branchItem['id'] as num?)?.toInt(),
                        child: Text(branchItem['name']?.toString() ?? 'Branch'),
                      ),
                    )
                    .toList(),
                onChanged: (value) =>
                    setModalState(() => selectedBranchId = value),
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<int?>(
                initialValue: selectedTrainerId,
                decoration: const InputDecoration(
                  labelText: 'Assigned trainer',
                ),
                items: [
                  const DropdownMenuItem<int?>(
                    value: null,
                    child: Text('No trainer'),
                  ),
                  ..._trainers.map(
                    (trainer) => DropdownMenuItem<int?>(
                      value: (trainer['id'] as num?)?.toInt(),
                      child: Text(trainer['name']?.toString() ?? 'Trainer'),
                    ),
                  ),
                ],
                onChanged: (value) =>
                    setModalState(() => selectedTrainerId = value),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: notesController,
                minLines: 2,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Notes'),
              ),
              const SizedBox(height: 16),
              GradientButton(
                label: 'Convert to Member',
                icon: Icons.person_add_alt_1_rounded,
                loading: busy,
                expanded: true,
                onPressed: () async {
                  final navigator = Navigator.of(context);
                  final messenger = ScaffoldMessenger.of(context);
                  setModalState(() => busy = true);
                  try {
                    final response = await widget.repository
                        .convertGymTrialRequest(trialId, {
                          if (existingUserController.text.trim().isNotEmpty)
                            'existing_user_id': int.tryParse(
                              existingUserController.text.trim(),
                            ),
                          'name': nameController.text.trim(),
                          'email': emailController.text.trim(),
                          'phone': phoneController.text.trim(),
                          'branch_id': selectedBranchId,
                          'assigned_trainer_user_id': selectedTrainerId,
                          'notes': notesController.text.trim(),
                        });
                    if (!mounted) {
                      return;
                    }
                    navigator.pop();
                    await _reloadTrials();
                    final member = _recordMap(response['member']);
                    messenger.showSnackBar(
                      SnackBar(
                        content: Text(
                          'Trial converted successfully${member['name'] != null ? ' for ${member['name']}' : ''}.',
                        ),
                      ),
                    );
                  } catch (exception) {
                    if (mounted) {
                      messenger.showSnackBar(
                        SnackBar(content: Text(exception.toString())),
                      );
                      setModalState(() => busy = false);
                    }
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );

    nameController.dispose();
    emailController.dispose();
    phoneController.dispose();
    existingUserController.dispose();
    notesController.dispose();
  }

  Future<void> _showTrialDetail(Map<String, dynamic> trial) async {
    final trialId = (trial['id'] as num?)?.toInt();
    if (trialId == null) {
      return;
    }
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => FutureBuilder<Map<String, dynamic>>(
        future: widget.repository.fetchGymTrialRequestDetail(trialId),
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 360,
              child: LoadingState(label: 'Loading trial detail...'),
            );
          }
          if (snapshot.hasError) {
            return SizedBox(
              height: 360,
              child: ErrorState(
                message: snapshot.error.toString(),
                onRetry: () {
                  Navigator.of(context).pop();
                  _showTrialDetail(trial);
                },
              ),
            );
          }
          final detail = snapshot.data ?? trial;
          return _TrialRequestDetailSheet(
            trial: detail,
            onAccept: () {
              Navigator.of(context).pop();
              _confirmAndRun(detail, 'accept');
            },
            onReject: () {
              Navigator.of(context).pop();
              _confirmAndRun(detail, 'reject');
            },
            onComplete: () {
              Navigator.of(context).pop();
              _confirmAndRun(detail, 'complete');
            },
            onAssignTrainer: () {
              Navigator.of(context).pop();
              _showAssignTrainerSheet(detail);
            },
            onConvert: () {
              Navigator.of(context).pop();
              _showConvertSheet(detail);
            },
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Trial Requests',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(
                'Review gym leads, accept or reject trials, assign trainers, complete visits, and convert strong leads into members from one pipeline view.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: _TrialStatusFilter.values.map((filter) {
                  return ChoiceChip(
                    label: Text(_dashboardTitleCase(filter.name)),
                    selected: _status == filter,
                    onSelected: (_) {
                      setState(() => _status = filter);
                      _reloadTrials();
                    },
                  );
                }).toList(),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        PremiumCard(
          child: Column(
            children: [
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _searchController,
                      onSubmitted: (_) => _reloadTrials(),
                      decoration: const InputDecoration(
                        hintText: 'Search lead name, email, or phone',
                        prefixIcon: Icon(Icons.search_rounded),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  SizedBox(
                    width: 180,
                    child: DropdownButtonFormField<int?>(
                      initialValue: _branchId,
                      decoration: const InputDecoration(labelText: 'Branch'),
                      items: [
                        const DropdownMenuItem<int?>(
                          value: null,
                          child: Text('All branches'),
                        ),
                        ..._branches.map(
                          (branch) => DropdownMenuItem<int?>(
                            value: (branch['id'] as num?)?.toInt(),
                            child: Text(branch['name']?.toString() ?? 'Branch'),
                          ),
                        ),
                      ],
                      onChanged: (value) {
                        setState(() => _branchId = value);
                        _reloadTrials();
                      },
                    ),
                  ),
                  const SizedBox(width: 12),
                  SizedBox(
                    width: 180,
                    child: DropdownButtonFormField<int?>(
                      initialValue: _trainerId,
                      decoration: const InputDecoration(labelText: 'Trainer'),
                      items: [
                        const DropdownMenuItem<int?>(
                          value: null,
                          child: Text('All trainers'),
                        ),
                        ..._trainers.map(
                          (trainer) => DropdownMenuItem<int?>(
                            value: (trainer['id'] as num?)?.toInt(),
                            child: Text(
                              trainer['name']?.toString() ?? 'Trainer',
                            ),
                          ),
                        ),
                      ],
                      onChanged: (value) {
                        setState(() => _trainerId = value);
                        _reloadTrials();
                      },
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Expanded(
          child: _isPermissionError(_error)
              ? const EmptyState(
                  title: 'Permission denied',
                  message:
                      'The current role needs trial request access to view and manage this lead pipeline.',
                  icon: Icons.lock_outline_rounded,
                )
              : AsyncStateView(
                  isLoading: _loading,
                  error: _error,
                  onRetry: _loadOptionsAndTrials,
                  loadingChild: const _CollectionLoadingSkeleton(
                    destinationTitle: 'Payments',
                  ),
                  isEmpty: _trials.isEmpty && !_loading,
                  emptyTitle: 'No trial requests',
                  emptyMessage:
                      'No trial requests match the selected filters right now.',
                  emptyIcon: Icons.flag_outlined,
                  child: RefreshIndicator(
                    onRefresh: _reloadTrials,
                    child: ListView.separated(
                      itemCount: _trials.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, index) => RevealOnBuild(
                        delay: Duration(milliseconds: 40 * (index % 8)),
                        child: _TrialRequestCard(
                          trial: _trials[index],
                          onTap: () => _showTrialDetail(_trials[index]),
                          onAccept: () =>
                              _confirmAndRun(_trials[index], 'accept'),
                          onReject: () =>
                              _confirmAndRun(_trials[index], 'reject'),
                          onComplete: () =>
                              _confirmAndRun(_trials[index], 'complete'),
                          onAssignTrainer: () =>
                              _showAssignTrainerSheet(_trials[index]),
                          onConvert: () => _showConvertSheet(_trials[index]),
                        ),
                      ),
                    ),
                  ),
                ),
        ),
      ],
    );
  }
}

class _TrialRequestCard extends StatelessWidget {
  const _TrialRequestCard({
    required this.trial,
    required this.onTap,
    required this.onAccept,
    required this.onReject,
    required this.onComplete,
    required this.onAssignTrainer,
    required this.onConvert,
  });

  final Map<String, dynamic> trial;
  final VoidCallback onTap;
  final VoidCallback onAccept;
  final VoidCallback onReject;
  final VoidCallback onComplete;
  final VoidCallback onAssignTrainer;
  final VoidCallback onConvert;

  @override
  Widget build(BuildContext context) {
    final branch = _recordMap(trial['branch']);
    final trainer = _recordMap(trial['assigned_trainer']);
    final status = trial['status']?.toString() ?? 'pending';
    final canAccept = status == 'pending';
    final canReject = status == 'pending' || status == 'accepted';
    final canComplete = status == 'accepted';
    final canConvert = status == 'accepted' || status == 'completed';

    return PremiumCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      trial['name']?.toString() ?? 'Trial request',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      trial['phone']?.toString().isNotEmpty == true
                          ? trial['phone'].toString()
                          : (trial['email']?.toString() ?? '--'),
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              _TrialStatusBadge(status: status),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 16,
            runSpacing: 16,
            children: [
              _MiniMetric(
                label: 'Preferred date',
                value: trial['preferred_date']?.toString() ?? '--',
                icon: Icons.event_rounded,
              ),
              _MiniMetric(
                label: 'Preferred time',
                value: trial['preferred_time']?.toString() ?? '--',
                icon: Icons.schedule_rounded,
              ),
              _MiniMetric(
                label: 'Branch',
                value: branch['name']?.toString() ?? '--',
                icon: Icons.location_city_rounded,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (trainer['name']?.toString().isNotEmpty == true)
                _InlineBadge(label: 'Trainer ${trainer['name']}'),
              if (trial['member_id'] != null)
                const _InlineBadge(label: 'Linked member'),
              if (trial['notes']?.toString().isNotEmpty == true)
                const _InlineBadge(label: 'Follow-up notes'),
            ],
          ),
          if (trial['notes']?.toString().isNotEmpty == true) ...[
            const SizedBox(height: 12),
            Text(
              trial['notes'].toString(),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              if (canAccept)
                QuickActionButton(
                  label: 'Accept',
                  icon: Icons.check_circle_outline_rounded,
                  onPressed: onAccept,
                ),
              if (canReject)
                QuickActionButton(
                  label: 'Reject',
                  icon: Icons.cancel_outlined,
                  onPressed: onReject,
                ),
              QuickActionButton(
                label: 'Assign Trainer',
                icon: Icons.person_add_alt_1_rounded,
                onPressed: onAssignTrainer,
              ),
              if (canComplete)
                QuickActionButton(
                  label: 'Complete',
                  icon: Icons.flag_circle_rounded,
                  onPressed: onComplete,
                ),
              if (canConvert)
                QuickActionButton(
                  label: 'Convert',
                  icon: Icons.person_add_rounded,
                  onPressed: onConvert,
                ),
              QuickActionButton(
                label: 'Detail',
                icon: Icons.visibility_rounded,
                onPressed: onTap,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TrialRequestDetailSheet extends StatelessWidget {
  const _TrialRequestDetailSheet({
    required this.trial,
    required this.onAccept,
    required this.onReject,
    required this.onComplete,
    required this.onAssignTrainer,
    required this.onConvert,
  });

  final Map<String, dynamic> trial;
  final VoidCallback onAccept;
  final VoidCallback onReject;
  final VoidCallback onComplete;
  final VoidCallback onAssignTrainer;
  final VoidCallback onConvert;

  @override
  Widget build(BuildContext context) {
    final branch = _recordMap(trial['branch']);
    final trainer = _recordMap(trial['assigned_trainer']);
    final member = _recordMap(trial['member']);
    final status = trial['status']?.toString() ?? 'pending';
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ListView(
          shrinkWrap: true,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    trial['name']?.toString() ?? 'Trial request',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                ),
                _TrialStatusBadge(status: status),
              ],
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _InfoRow(
                    label: 'Phone',
                    value: trial['phone']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Email',
                    value: trial['email']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Preferred date',
                    value: trial['preferred_date']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Preferred time',
                    value: trial['preferred_time']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Branch',
                    value: branch['name']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Assigned trainer',
                    value: trainer['name']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Linked member',
                    value: member['name']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Notes',
                    value: trial['notes']?.toString().isNotEmpty == true
                        ? trial['notes'].toString()
                        : '--',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (status == 'pending')
                  QuickActionButton(
                    label: 'Accept',
                    icon: Icons.check_circle_outline_rounded,
                    onPressed: onAccept,
                  ),
                if (status == 'pending' || status == 'accepted')
                  QuickActionButton(
                    label: 'Reject',
                    icon: Icons.cancel_outlined,
                    onPressed: onReject,
                  ),
                QuickActionButton(
                  label: 'Assign Trainer',
                  icon: Icons.person_add_alt_1_rounded,
                  onPressed: onAssignTrainer,
                ),
                if (status == 'accepted')
                  QuickActionButton(
                    label: 'Complete',
                    icon: Icons.flag_circle_rounded,
                    onPressed: onComplete,
                  ),
                if (status == 'accepted' || status == 'completed')
                  QuickActionButton(
                    label: 'Convert',
                    icon: Icons.person_add_rounded,
                    onPressed: onConvert,
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _TrialStatusBadge extends StatelessWidget {
  const _TrialStatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final normalized = status.toLowerCase();
    Color color;
    IconData icon;
    switch (normalized) {
      case 'accepted':
        color = AppColors.info;
        icon = Icons.check_circle_outline_rounded;
        break;
      case 'completed':
        color = AppColors.success;
        icon = Icons.flag_circle_rounded;
        break;
      case 'converted':
        color = AppColors.success;
        icon = Icons.person_add_alt_1_rounded;
        break;
      case 'rejected':
        color = AppColors.error;
        icon = Icons.cancel_outlined;
        break;
      default:
        color = AppColors.warning;
        icon = Icons.hourglass_top_rounded;
    }

    return StatusBadge(
      label: _dashboardTitleCase(status),
      color: color,
      icon: icon,
    );
  }
}

enum _AnnouncementAudienceOption {
  gymWide('gym_wide', 'All gym members'),
  branchSpecific('branch_specific', 'Branch members'),
  selectedMembers('selected_members', 'Selected members');

  const _AnnouncementAudienceOption(this.value, this.label);

  final String value;
  final String label;
}

class _AnnouncementsWorkspaceSection extends StatefulWidget {
  const _AnnouncementsWorkspaceSection({
    super.key,
    required this.appUser,
    required this.repository,
  });

  final AppUser appUser;
  final AdminRepository repository;

  @override
  State<_AnnouncementsWorkspaceSection> createState() =>
      _AnnouncementsWorkspaceSectionState();
}

class _AnnouncementsWorkspaceSectionState
    extends State<_AnnouncementsWorkspaceSection> {
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _messageController = TextEditingController();
  List<Map<String, dynamic>> _announcements = const [];
  List<Map<String, dynamic>> _branches = const [];
  List<Map<String, dynamic>> _members = const [];
  bool _loading = true;
  bool _sending = false;
  String? _error;
  String? _successMessage;
  _AnnouncementAudienceOption _audience = _AnnouncementAudienceOption.gymWide;
  int? _historyBranchId;
  int? _selectedBranchId;
  Set<int> _selectedMemberIds = <int>{};

  bool get _canSendAnnouncements =>
      widget.appUser.activeRole == 'gym_owner' ||
      widget.appUser.activeRole == 'branch_manager' ||
      widget.appUser.hasAnyPermission(['send_announcements']);

  int? get _gymId =>
      (_recordMap(
                widget.appUser.gyms.isNotEmpty
                    ? widget.appUser.gyms.first
                    : null,
              )['id']
              as num?)
          ?.toInt();

  @override
  void initState() {
    super.initState();
    _loadWorkspace();
  }

  @override
  void dispose() {
    _titleController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _loadWorkspace() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        widget.repository.fetchCollection('/gym/branches', perPage: 100),
        widget.repository.fetchCollection('/gym/members', perPage: 100),
        widget.repository.fetchCollection('/gym/announcements', perPage: 30),
      ]);
      if (!mounted) {
        return;
      }
      final branches = results[0].items;
      final members = results[1].items;
      final announcements = results[2].items;
      int? defaultBranchId = _selectedBranchId;
      if (widget.appUser.activeRole == 'branch_manager' &&
          branches.isNotEmpty) {
        defaultBranchId ??= (branches.first['id'] as num?)?.toInt();
      }
      setState(() {
        _branches = branches;
        _members = members;
        _announcements = announcements;
        _selectedBranchId = defaultBranchId;
        _historyBranchId ??= defaultBranchId;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _reloadAnnouncements() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.repository.fetchCollection(
        '/gym/announcements',
        perPage: 30,
        queryParameters: {
          if (_historyBranchId != null) 'branch_id': _historyBranchId,
        },
      );
      if (!mounted) {
        return;
      }
      setState(() => _announcements = response.items);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  String _branchName(int? branchId) {
    if (branchId == null) {
      return 'All branches';
    }
    for (final branch in _branches) {
      if ((branch['id'] as num?)?.toInt() == branchId) {
        return branch['name']?.toString() ?? 'Branch';
      }
    }
    return 'Branch #$branchId';
  }

  List<Map<String, dynamic>> _filteredMembers() {
    if (_selectedBranchId == null) {
      return _members;
    }
    return _members.where((member) {
      final branchId =
          (_recordMap(member['member_profile'])['branch_id'] as num?)
              ?.toInt() ??
          (_recordMap(member['branch'])['id'] as num?)?.toInt();
      return branchId == _selectedBranchId;
    }).toList();
  }

  Future<void> _submitAnnouncement() async {
    final messenger = ScaffoldMessenger.of(context);
    final title = _titleController.text.trim();
    final message = _messageController.text.trim();
    final branchRequired =
        widget.appUser.activeRole == 'branch_manager' ||
        _audience == _AnnouncementAudienceOption.branchSpecific;

    if (title.isEmpty || message.isEmpty) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Title and message are required.')),
      );
      return;
    }
    if (branchRequired && _selectedBranchId == null) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Select a branch for this announcement.')),
      );
      return;
    }
    if (_audience == _AnnouncementAudienceOption.selectedMembers &&
        _selectedMemberIds.isEmpty) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Select at least one member.')),
      );
      return;
    }

    setState(() {
      _sending = true;
      _successMessage = null;
    });
    try {
      await widget.repository.publishAnnouncement(widget.appUser.activeRole, {
        if (_gymId != null) 'gym_id': _gymId,
        if (_selectedBranchId != null) 'branch_id': _selectedBranchId,
        'audience_type': _audience.value,
        'title': title,
        'message': message,
        if (_selectedMemberIds.isNotEmpty)
          'member_ids': _selectedMemberIds.toList(),
      });
      if (!mounted) {
        return;
      }
      _titleController.clear();
      _messageController.clear();
      setState(() {
        _selectedMemberIds = <int>{};
        _audience = _AnnouncementAudienceOption.gymWide;
        _successMessage = 'Announcement sent successfully.';
      });
      await _reloadAnnouncements();
      messenger.showSnackBar(
        const SnackBar(content: Text('Announcement sent successfully.')),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final selectedMembers = _filteredMembers()
        .where(
          (member) =>
              _selectedMemberIds.contains((member['id'] as num?)?.toInt()),
        )
        .toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Announcements',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(
                'Broadcast updates to your full gym, a single branch, or hand-picked members without leaving the operations shell.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _MiniHighlight(
                    label: 'Sent broadcasts',
                    value: '${_announcements.length}',
                  ),
                  _MiniHighlight(
                    label: 'Branches in scope',
                    value: '${_branches.length}',
                  ),
                  _MiniHighlight(
                    label: 'Selectable members',
                    value: '${_filteredMembers().length}',
                  ),
                ],
              ),
              if (_successMessage != null) ...[
                const SizedBox(height: 16),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: AppColors.success.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: AppColors.success.withValues(alpha: 0.22),
                    ),
                  ),
                  child: Text(
                    _successMessage!,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        Expanded(
          child: _isPermissionError(_error)
              ? const EmptyState(
                  title: 'Permission denied',
                  message:
                      'The current role needs announcement access to view or send gym broadcasts.',
                  icon: Icons.lock_outline_rounded,
                )
              : AsyncStateView(
                  isLoading: _loading,
                  error: _error,
                  onRetry: _loadWorkspace,
                  loadingChild: const _CollectionLoadingSkeleton(
                    destinationTitle: 'Announcements',
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        flex: 5,
                        child: ListView(
                          children: [
                            PremiumCard(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          'Create announcement',
                                          style: Theme.of(
                                            context,
                                          ).textTheme.titleLarge,
                                        ),
                                      ),
                                      if (!_canSendAnnouncements)
                                        const StatusBadge(
                                          label: 'Permission required',
                                          icon: Icons.lock_outline_rounded,
                                          color: AppColors.warning,
                                        ),
                                    ],
                                  ),
                                  const SizedBox(height: 14),
                                  TextFormField(
                                    controller: _titleController,
                                    enabled: _canSendAnnouncements && !_sending,
                                    decoration: const InputDecoration(
                                      labelText: 'Title',
                                    ),
                                  ),
                                  const SizedBox(height: 12),
                                  TextFormField(
                                    controller: _messageController,
                                    enabled: _canSendAnnouncements && !_sending,
                                    minLines: 4,
                                    maxLines: 6,
                                    decoration: const InputDecoration(
                                      labelText: 'Message',
                                    ),
                                  ),
                                  const SizedBox(height: 14),
                                  Wrap(
                                    spacing: 10,
                                    runSpacing: 10,
                                    children: _AnnouncementAudienceOption.values
                                        .map((option) {
                                          return ChoiceChip(
                                            selected: _audience == option,
                                            label: Text(option.label),
                                            onSelected:
                                                !_canSendAnnouncements ||
                                                    _sending
                                                ? null
                                                : (_) => setState(() {
                                                    _audience = option;
                                                    if (option !=
                                                        _AnnouncementAudienceOption
                                                            .selectedMembers) {
                                                      _selectedMemberIds =
                                                          <int>{};
                                                    }
                                                  }),
                                          );
                                        })
                                        .toList(),
                                  ),
                                  const SizedBox(height: 14),
                                  DropdownButtonFormField<int?>(
                                    initialValue: _selectedBranchId,
                                    decoration: const InputDecoration(
                                      labelText: 'Branch selector',
                                    ),
                                    items: [
                                      const DropdownMenuItem<int?>(
                                        value: null,
                                        child: Text('All branches'),
                                      ),
                                      ..._branches.map(
                                        (branch) => DropdownMenuItem<int?>(
                                          value: (branch['id'] as num?)
                                              ?.toInt(),
                                          child: Text(
                                            branch['name']?.toString() ??
                                                'Branch',
                                          ),
                                        ),
                                      ),
                                    ],
                                    onChanged:
                                        !_canSendAnnouncements || _sending
                                        ? null
                                        : (value) => setState(() {
                                            _selectedBranchId = value;
                                            _selectedMemberIds =
                                                _selectedMemberIds
                                                    .where(
                                                      (id) => _filteredMembers()
                                                          .any(
                                                            (member) =>
                                                                (member['id']
                                                                        as num?)
                                                                    ?.toInt() ==
                                                                id,
                                                          ),
                                                    )
                                                    .toSet();
                                          }),
                                  ),
                                  if (_audience ==
                                      _AnnouncementAudienceOption
                                          .selectedMembers) ...[
                                    const SizedBox(height: 14),
                                    Text(
                                      'Selected members',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.titleMedium,
                                    ),
                                    const SizedBox(height: 10),
                                    if (_filteredMembers().isEmpty)
                                      const EmptyState(
                                        title: 'No members available',
                                        message:
                                            'There are no members in the current scope for a direct targeted announcement.',
                                        icon: Icons.groups_outlined,
                                      )
                                    else
                                      Wrap(
                                        spacing: 10,
                                        runSpacing: 10,
                                        children: _filteredMembers().map((
                                          member,
                                        ) {
                                          final memberId =
                                              (member['id'] as num?)?.toInt();
                                          if (memberId == null) {
                                            return const SizedBox.shrink();
                                          }
                                          final selected = _selectedMemberIds
                                              .contains(memberId);
                                          return FilterChip(
                                            selected: selected,
                                            label: Text(
                                              member['name']?.toString() ??
                                                  'Member',
                                            ),
                                            onSelected:
                                                !_canSendAnnouncements ||
                                                    _sending
                                                ? null
                                                : (_) => setState(() {
                                                    selected
                                                        ? _selectedMemberIds
                                                              .remove(memberId)
                                                        : _selectedMemberIds
                                                              .add(memberId);
                                                  }),
                                          );
                                        }).toList(),
                                      ),
                                  ],
                                  if (selectedMembers.isNotEmpty) ...[
                                    const SizedBox(height: 14),
                                    Wrap(
                                      spacing: 8,
                                      runSpacing: 8,
                                      children: selectedMembers.map((member) {
                                        final memberId = (member['id'] as num?)
                                            ?.toInt();
                                        return Chip(
                                          label: Text(
                                            member['name']?.toString() ??
                                                'Member',
                                          ),
                                          onDeleted:
                                              !_canSendAnnouncements || _sending
                                              ? null
                                              : () => setState(() {
                                                  if (memberId != null) {
                                                    _selectedMemberIds.remove(
                                                      memberId,
                                                    );
                                                  }
                                                }),
                                        );
                                      }).toList(),
                                    ),
                                  ],
                                  const SizedBox(height: 18),
                                  GradientButton(
                                    label: 'Send Announcement',
                                    icon: Icons.campaign_rounded,
                                    loading: _sending,
                                    expanded: true,
                                    onPressed:
                                        _canSendAnnouncements && !_sending
                                        ? _submitAnnouncement
                                        : null,
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        flex: 6,
                        child: Column(
                          children: [
                            PremiumCard(
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      'Announcement history',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.titleLarge,
                                    ),
                                  ),
                                  SizedBox(
                                    width: 220,
                                    child: DropdownButtonFormField<int?>(
                                      initialValue: _historyBranchId,
                                      decoration: const InputDecoration(
                                        labelText: 'History branch filter',
                                      ),
                                      items: [
                                        const DropdownMenuItem<int?>(
                                          value: null,
                                          child: Text('All branches'),
                                        ),
                                        ..._branches.map(
                                          (branch) => DropdownMenuItem<int?>(
                                            value: (branch['id'] as num?)
                                                ?.toInt(),
                                            child: Text(
                                              branch['name']?.toString() ??
                                                  'Branch',
                                            ),
                                          ),
                                        ),
                                      ],
                                      onChanged: (value) {
                                        setState(
                                          () => _historyBranchId = value,
                                        );
                                        _reloadAnnouncements();
                                      },
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 16),
                            Expanded(
                              child: _announcements.isEmpty
                                  ? const EmptyState(
                                      title: 'No announcements yet',
                                      message:
                                          'Send your first gym announcement to start this communication timeline.',
                                      icon: Icons.campaign_outlined,
                                    )
                                  : RefreshIndicator(
                                      onRefresh: _reloadAnnouncements,
                                      child: ListView.separated(
                                        itemCount: _announcements.length,
                                        separatorBuilder: (_, __) =>
                                            const SizedBox(height: 10),
                                        itemBuilder: (context, index) =>
                                            RevealOnBuild(
                                              delay: Duration(
                                                milliseconds: 40 * (index % 8),
                                              ),
                                              child: _AnnouncementCard(
                                                announcement:
                                                    _announcements[index],
                                                branchLabel: _branchName(
                                                  (_announcements[index]['branch_id']
                                                          as num?)
                                                      ?.toInt(),
                                                ),
                                              ),
                                            ),
                                      ),
                                    ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
        ),
      ],
    );
  }
}

class _AnnouncementCard extends StatelessWidget {
  const _AnnouncementCard({
    required this.announcement,
    required this.branchLabel,
  });

  final Map<String, dynamic> announcement;
  final String branchLabel;

  @override
  Widget build(BuildContext context) {
    final audience = announcement['audience_type']?.toString() ?? 'gym_wide';
    final recipients = (announcement['recipient_count'] as num?)?.toInt() ?? 0;

    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      announcement['title']?.toString() ?? 'Announcement',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _formatDateTime(announcement['created_at']),
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              _AudienceBadge(audience: audience),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            announcement['message']?.toString() ?? 'No announcement body.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _InlineBadge(label: branchLabel),
              _InlineBadge(label: '$recipients recipients'),
              if (announcement['status']?.toString().isNotEmpty == true)
                _InlineBadge(label: announcement['status'].toString()),
            ],
          ),
        ],
      ),
    );
  }
}

enum _NotificationFeedFilter { all, unread, read }

class _NotificationsWorkspaceSection extends StatefulWidget {
  const _NotificationsWorkspaceSection({
    super.key,
    required this.appUser,
    required this.repository,
  });

  final AppUser appUser;
  final AdminRepository repository;

  @override
  State<_NotificationsWorkspaceSection> createState() =>
      _NotificationsWorkspaceSectionState();
}

class _NotificationsWorkspaceSectionState
    extends State<_NotificationsWorkspaceSection> {
  List<Map<String, dynamic>> _notifications = const [];
  bool _loading = true;
  bool _markingAll = false;
  String? _error;
  _NotificationFeedFilter _filter = _NotificationFeedFilter.all;

  int? get _branchId =>
      (_recordMap(
                widget.appUser.branches.isNotEmpty
                    ? widget.appUser.branches.first
                    : null,
              )['id']
              as num?)
          ?.toInt();

  @override
  void initState() {
    super.initState();
    _loadNotifications();
  }

  Future<void> _loadNotifications() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.repository.fetchNotifications(perPage: 50);
      if (!mounted) {
        return;
      }
      setState(() => _notifications = response.items);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  List<Map<String, dynamic>> _visibleNotifications() {
    return switch (_filter) {
      _NotificationFeedFilter.unread =>
        _notifications.where((item) => item['read_at'] == null).toList(),
      _NotificationFeedFilter.read =>
        _notifications.where((item) => item['read_at'] != null).toList(),
      _NotificationFeedFilter.all => _notifications,
    };
  }

  int get _unreadCount =>
      _notifications.where((item) => item['read_at'] == null).length;

  Future<void> _markRead(Map<String, dynamic> notification) async {
    final id = (notification['id'] as num?)?.toInt();
    if (id == null || notification['read_at'] != null) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.repository.markNotificationRead(id);
      if (!mounted) {
        return;
      }
      setState(() {
        final index = _notifications.indexWhere(
          (item) => (item['id'] as num?)?.toInt() == id,
        );
        if (index >= 0) {
          _notifications[index] = {
            ..._notifications[index],
            'read_at': DateTime.now().toIso8601String(),
          };
        }
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _markAllRead() async {
    if (_unreadCount == 0) {
      return;
    }
    setState(() => _markingAll = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.repository.markAllNotificationsRead(branchId: _branchId);
      if (!mounted) {
        return;
      }
      setState(() {
        final timestamp = DateTime.now().toIso8601String();
        _notifications = _notifications
            .map((item) => {...item, 'read_at': item['read_at'] ?? timestamp})
            .toList();
      });
      messenger.showSnackBar(
        const SnackBar(content: Text('All notifications marked as read.')),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _markingAll = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final visibleItems = _visibleNotifications();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Notifications',
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                  ),
                  if (_unreadCount > 0)
                    StatusBadge(
                      label: '$_unreadCount unread',
                      icon: Icons.mark_email_unread_rounded,
                      color: AppColors.primaryBright,
                    ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Track announcements, dues, trainer assignments, retention nudges, and member-facing alerts from one inbox.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _MiniHighlight(label: 'Unread', value: '$_unreadCount'),
                  _MiniHighlight(
                    label: 'Total feed',
                    value: '${_notifications.length}',
                  ),
                  _MiniHighlight(
                    label: 'Read',
                    value: '${_notifications.length - _unreadCount}',
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Expanded(
          child: _isPermissionError(_error)
              ? const EmptyState(
                  title: 'Permission denied',
                  message:
                      'The current role does not have access to this notification feed.',
                  icon: Icons.lock_outline_rounded,
                )
              : AsyncStateView(
                  isLoading: _loading,
                  error: _error,
                  onRetry: _loadNotifications,
                  loadingChild: const _CollectionLoadingSkeleton(
                    destinationTitle: 'Notifications',
                  ),
                  child: Column(
                    children: [
                      PremiumCard(
                        child: Row(
                          children: [
                            Wrap(
                              spacing: 10,
                              runSpacing: 10,
                              children: _NotificationFeedFilter.values.map((
                                filter,
                              ) {
                                return ChoiceChip(
                                  selected: _filter == filter,
                                  label: Text(switch (filter) {
                                    _NotificationFeedFilter.all => 'All',
                                    _NotificationFeedFilter.unread => 'Unread',
                                    _NotificationFeedFilter.read => 'Read',
                                  }),
                                  onSelected: (_) =>
                                      setState(() => _filter = filter),
                                );
                              }).toList(),
                            ),
                            const Spacer(),
                            OutlinedButton.icon(
                              onPressed: _markingAll || _unreadCount == 0
                                  ? null
                                  : _markAllRead,
                              icon: const Icon(Icons.done_all_rounded),
                              label: Text(
                                _markingAll ? 'Updating...' : 'Read all',
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                      Expanded(
                        child: visibleItems.isEmpty
                            ? const EmptyState(
                                title: 'No notifications',
                                message:
                                    'This inbox is clear for the selected filter right now.',
                                icon: Icons.notifications_none_rounded,
                              )
                            : RefreshIndicator(
                                onRefresh: _loadNotifications,
                                child: ListView.separated(
                                  itemCount: visibleItems.length,
                                  separatorBuilder: (_, __) =>
                                      const SizedBox(height: 10),
                                  itemBuilder: (context, index) =>
                                      RevealOnBuild(
                                        delay: Duration(
                                          milliseconds: 40 * (index % 8),
                                        ),
                                        child: _NotificationCard(
                                          notification: visibleItems[index],
                                          onMarkRead: () =>
                                              _markRead(visibleItems[index]),
                                        ),
                                      ),
                                ),
                              ),
                      ),
                    ],
                  ),
                ),
        ),
      ],
    );
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({
    required this.notification,
    required this.onMarkRead,
  });

  final Map<String, dynamic> notification;
  final VoidCallback onMarkRead;

  @override
  Widget build(BuildContext context) {
    final unread = notification['read_at'] == null;
    final type = notification['type']?.toString() ?? 'notification';

    return PremiumCard(
      onTap: unread ? onMarkRead : null,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        padding: EdgeInsets.zero,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(24),
          border: Border.all(
            color: unread
                ? AppColors.primaryBright.withValues(alpha: 0.42)
                : AppColors.stroke,
          ),
          gradient: unread
              ? LinearGradient(
                  colors: [
                    AppColors.primary.withValues(alpha: 0.18),
                    AppColors.surfaceStrong.withValues(alpha: 0.92),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
              : null,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        notification['title']?.toString() ?? 'Notification',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _formatDateTime(notification['created_at']),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                _NotificationTypeBadge(type: type),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              notification['body']?.toString() ?? 'No notification body.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                if (unread)
                  const StatusBadge(
                    label: 'Unread',
                    color: AppColors.primaryBright,
                    icon: Icons.circle_notifications_rounded,
                  )
                else
                  const StatusBadge(
                    label: 'Read',
                    color: AppColors.success,
                    icon: Icons.done_rounded,
                  ),
                const Spacer(),
                if (unread)
                  QuickActionButton(
                    label: 'Mark read',
                    icon: Icons.done_rounded,
                    onPressed: onMarkRead,
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _AudienceBadge extends StatelessWidget {
  const _AudienceBadge({required this.audience});

  final String audience;

  @override
  Widget build(BuildContext context) {
    return StatusBadge(
      label: _dashboardTitleCase(audience),
      icon: switch (audience) {
        'selected_members' => Icons.how_to_reg_rounded,
        'branch_specific' => Icons.location_city_rounded,
        _ => Icons.groups_rounded,
      },
      color: switch (audience) {
        'selected_members' => AppColors.primaryBright,
        'branch_specific' => AppColors.warning,
        _ => AppColors.info,
      },
    );
  }
}

class _NotificationTypeBadge extends StatelessWidget {
  const _NotificationTypeBadge({required this.type});

  final String type;

  @override
  Widget build(BuildContext context) {
    final normalized = type.toLowerCase();
    return StatusBadge(
      label: _dashboardTitleCase(normalized),
      icon: switch (normalized) {
        'gym_announcement' => Icons.campaign_rounded,
        'payment_due' || 'custom_due' => Icons.payments_rounded,
        'membership_expiry' => Icons.event_busy_rounded,
        'trainer_assignment' => Icons.person_add_alt_1_rounded,
        'attendance_inactivity' => Icons.qr_code_scanner_rounded,
        'trial_request_update' => Icons.flag_rounded,
        'workout_reminder' => Icons.fitness_center_rounded,
        'pr_achievement' => Icons.emoji_events_rounded,
        _ => Icons.notifications_active_rounded,
      },
      color: switch (normalized) {
        'gym_announcement' => AppColors.primaryBright,
        'payment_due' || 'custom_due' => AppColors.warning,
        'membership_expiry' => AppColors.error,
        'trainer_assignment' => AppColors.info,
        'attendance_inactivity' => AppColors.warning,
        'trial_request_update' => AppColors.primary,
        'workout_reminder' => AppColors.success,
        'pr_achievement' => const Color(0xFFA78BFA),
        _ => AppColors.primary,
      },
    );
  }
}

class _GymReportsWorkspaceSection extends StatefulWidget {
  const _GymReportsWorkspaceSection({
    super.key,
    required this.appUser,
    required this.repository,
  });

  final AppUser appUser;
  final AdminRepository repository;

  @override
  State<_GymReportsWorkspaceSection> createState() =>
      _GymReportsWorkspaceSectionState();
}

class _GymReportsWorkspaceSectionState
    extends State<_GymReportsWorkspaceSection> {
  String _reportKey = 'overview';
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _report = const {};
  List<Map<String, dynamic>> _branches = const [];
  List<Map<String, dynamic>> _trainers = const [];
  List<Map<String, dynamic>> _plans = const [];
  final TextEditingController _startDateController = TextEditingController();
  final TextEditingController _endDateController = TextEditingController();
  int? _branchId;
  int? _trainerId;
  int? _planId;
  String? _status;

  @override
  void initState() {
    super.initState();
    _loadOptions();
    _loadReport();
  }

  @override
  void dispose() {
    _startDateController.dispose();
    _endDateController.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    try {
      final results = await Future.wait([
        widget.repository.fetchCollection('/gym/branches', perPage: 100),
        widget.repository.fetchCollection('/gym/trainers', perPage: 100),
        widget.repository.fetchCollection(
          '/gym/membership-plans',
          perPage: 100,
        ),
      ]);
      if (!mounted) {
        return;
      }
      setState(() {
        _branches = results[0].items;
        _trainers = results[1].items;
        _plans = results[2].items;
      });
    } catch (_) {
      // Filters remain usable without option preloading.
    }
  }

  Future<void> _loadReport() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final report = await widget.repository.fetchGymReport(
        _reportKey,
        queryParameters: {
          if (_startDateController.text.trim().isNotEmpty)
            'start_date': _startDateController.text.trim(),
          if (_endDateController.text.trim().isNotEmpty)
            'end_date': _endDateController.text.trim(),
          if (_branchId != null) 'branch_id': _branchId,
          if (_trainerId != null) 'trainer_id': _trainerId,
          if (_planId != null) 'plan_id': _planId,
          if (_status != null && _status!.isNotEmpty) 'status': _status,
        },
      );
      if (!mounted) {
        return;
      }
      final filters = _recordMap(report['filters']);
      _startDateController.text =
          filters['start_date']?.toString() ?? _startDateController.text;
      _endDateController.text =
          filters['end_date']?.toString() ?? _endDateController.text;
      setState(() {
        _report = report;
        _branchId = (filters['branch_id'] as num?)?.toInt() ?? _branchId;
        _trainerId = (filters['trainer_id'] as num?)?.toInt() ?? _trainerId;
        _planId = (filters['plan_id'] as num?)?.toInt() ?? _planId;
        _status = filters['status']?.toString().isNotEmpty == true
            ? filters['status'].toString()
            : _status;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final reportOptions = _recordMap(_report['report_options']);
    final summaryCards =
        (_report['summary_cards'] as List<dynamic>? ?? const [])
            .map((entry) => _recordMap(entry))
            .toList();
    final chartCards = (_report['chart_cards'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();
    final rows = (_report['rows'] as List<dynamic>? ?? const [])
        .map(
          (entry) =>
              (entry as List<dynamic>).map((cell) => cell.toString()).toList(),
        )
        .toList();
    final columns = (_report['columns'] as List<dynamic>? ?? const [])
        .map((entry) => entry.toString())
        .toList();
    final emptyState = _recordMap(_report['empty_state']);

    return RefreshIndicator(
      onRefresh: _loadReport,
      child: ListView(
        children: [
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Gym Reports',
                            style: Theme.of(context).textTheme.headlineSmall,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Monitor revenue, dues, memberships, attendance, trainers, custom fees, and lead conversion with gym-scoped filters.',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ],
                      ),
                    ),
                    OutlinedButton.icon(
                      onPressed: null,
                      icon: const Icon(Icons.file_download_outlined),
                      label: const Text('Export unavailable'),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: reportOptions.entries.map((entry) {
                    return ChoiceChip(
                      selected: _reportKey == entry.key,
                      label: Text(entry.value.toString()),
                      onSelected: (_) {
                        setState(() => _reportKey = entry.key);
                        _loadReport();
                      },
                    );
                  }).toList(),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _startDateController,
                        decoration: const InputDecoration(
                          labelText: 'Start date (YYYY-MM-DD)',
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: _endDateController,
                        decoration: const InputDecoration(
                          labelText: 'End date (YYYY-MM-DD)',
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<int?>(
                        initialValue: _branchId,
                        decoration: const InputDecoration(labelText: 'Branch'),
                        items: [
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('All branches'),
                          ),
                          ..._branches.map(
                            (branch) => DropdownMenuItem<int?>(
                              value: (branch['id'] as num?)?.toInt(),
                              child: Text(
                                branch['name']?.toString() ?? 'Branch',
                              ),
                            ),
                          ),
                        ],
                        onChanged: (value) => setState(() => _branchId = value),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<int?>(
                        initialValue: _trainerId,
                        decoration: const InputDecoration(labelText: 'Trainer'),
                        items: [
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('All trainers'),
                          ),
                          ..._trainers.map(
                            (trainer) => DropdownMenuItem<int?>(
                              value: (trainer['id'] as num?)?.toInt(),
                              child: Text(
                                trainer['name']?.toString() ?? 'Trainer',
                              ),
                            ),
                          ),
                        ],
                        onChanged: (value) =>
                            setState(() => _trainerId = value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<int?>(
                        initialValue: _planId,
                        decoration: const InputDecoration(labelText: 'Plan'),
                        items: [
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('All plans'),
                          ),
                          ..._plans.map(
                            (plan) => DropdownMenuItem<int?>(
                              value: (plan['id'] as num?)?.toInt(),
                              child: Text(plan['name']?.toString() ?? 'Plan'),
                            ),
                          ),
                        ],
                        onChanged: (value) => setState(() => _planId = value),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String?>(
                        initialValue: _status,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: const [
                          DropdownMenuItem<String?>(
                            value: null,
                            child: Text('All statuses'),
                          ),
                          DropdownMenuItem(
                            value: 'active',
                            child: Text('Active'),
                          ),
                          DropdownMenuItem(
                            value: 'expired',
                            child: Text('Expired'),
                          ),
                          DropdownMenuItem(
                            value: 'expiring-soon',
                            child: Text('Expiring Soon'),
                          ),
                          DropdownMenuItem(
                            value: 'partial',
                            child: Text('Partial'),
                          ),
                          DropdownMenuItem(
                            value: 'overdue',
                            child: Text('Overdue'),
                          ),
                          DropdownMenuItem(
                            value: 'pending',
                            child: Text('Pending'),
                          ),
                          DropdownMenuItem(
                            value: 'completed',
                            child: Text('Completed'),
                          ),
                          DropdownMenuItem(
                            value: 'converted',
                            child: Text('Converted'),
                          ),
                        ],
                        onChanged: (value) => setState(() => _status = value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    GradientButton(
                      label: 'Apply Filters',
                      icon: Icons.filter_alt_rounded,
                      onPressed: _loadReport,
                    ),
                    OutlinedButton.icon(
                      onPressed: () {
                        _startDateController.clear();
                        _endDateController.clear();
                        setState(() {
                          _branchId = null;
                          _trainerId = null;
                          _planId = null;
                          _status = null;
                        });
                        _loadReport();
                      },
                      icon: const Icon(Icons.restart_alt_rounded),
                      label: const Text('Reset'),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (_isPermissionError(_error))
            const EmptyState(
              title: 'Permission denied',
              message:
                  'The current role needs report access to open this reporting workspace.',
              icon: Icons.lock_outline_rounded,
            )
          else if (_loading)
            const LoadingState(label: 'Loading reports...')
          else if (_error != null)
            ErrorState(message: _error!, onRetry: _loadReport)
          else ...[
            if (summaryCards.isNotEmpty)
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: summaryCards
                    .map(
                      (card) => SizedBox(
                        width: 220,
                        child: StatCard(
                          label: card['label']?.toString() ?? 'Metric',
                          value: card['value']?.toString() ?? '--',
                          caption: card['hint']?.toString(),
                          icon: Icons.auto_graph_rounded,
                        ),
                      ),
                    )
                    .toList(),
              ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Chart Cards',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (chartCards.isEmpty)
                    const EmptyState(
                      title: 'Charts placeholder',
                      message:
                          'Trend visuals are reserved here when the backend sends chart cards for the selected report.',
                      icon: Icons.insights_rounded,
                    )
                  else
                    Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: chartCards
                          .map(
                            (card) => SizedBox(
                              width: 220,
                              child: StatCard(
                                label: card['label']?.toString() ?? 'Trend',
                                value: card['value']?.toString() ?? '--',
                                caption: card['hint']?.toString(),
                                icon: Icons.insights_rounded,
                                color: AppColors.accentPurple,
                              ),
                            ),
                          )
                          .toList(),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    columns.isNotEmpty ? 'Report Table' : 'Report Output',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (rows.isEmpty)
                    EmptyState(
                      title:
                          emptyState['title']?.toString() ?? 'No report data',
                      message:
                          emptyState['message']?.toString() ??
                          'The selected filters returned no rows.',
                      icon: Icons.inbox_rounded,
                    )
                  else
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: DataTable(
                        columns: columns
                            .map((column) => DataColumn(label: Text(column)))
                            .toList(),
                        rows: rows
                            .map(
                              (row) => DataRow(
                                cells: row
                                    .map((cell) => DataCell(Text(cell)))
                                    .toList(),
                              ),
                            )
                            .toList(),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _GymSettingsWorkspaceSection extends StatefulWidget {
  const _GymSettingsWorkspaceSection({
    super.key,
    required this.appUser,
    required this.repository,
    required this.onOpenNotificationPreferences,
    required this.onOpenSection,
  });

  final AppUser appUser;
  final AdminRepository repository;
  final Future<void> Function() onOpenNotificationPreferences;
  final ValueChanged<String> onOpenSection;

  @override
  State<_GymSettingsWorkspaceSection> createState() =>
      _GymSettingsWorkspaceSectionState();
}

class _GymSettingsWorkspaceSectionState
    extends State<_GymSettingsWorkspaceSection> {
  final TextEditingController _billingController = TextEditingController();
  bool _loading = true;
  bool _saving = false;
  String? _error;
  String? _successMessage;
  bool _preventDuplicateCheckIns = true;
  Set<String> _staffPermissionDefaults = <String>{};

  static const List<String> _permissionOptions = <String>[
    'view_billing',
    'collect_payment',
    'edit_custom_fee',
    'manage_attendance',
    'manage_members',
    'manage_trainers',
    'send_announcements',
    'view_reports',
    'manage_staff',
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _billingController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final settings = await widget.repository.fetchGymSettings();
      if (!mounted) {
        return;
      }
      _billingController.text =
          settings['billing_settings_placeholder']?.toString() ?? '';
      setState(() {
        _preventDuplicateCheckIns =
            settings['attendance_duplicate_checkin_rule'] == true;
        _staffPermissionDefaults =
            (settings['staff_permission_defaults'] as List<dynamic>? ??
                    const [])
                .map((entry) => entry.toString())
                .toSet();
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _successMessage = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.repository.updateGymSettings({
        'attendance_duplicate_checkin_rule': _preventDuplicateCheckIns,
        'billing_settings_placeholder': _billingController.text.trim().isEmpty
            ? null
            : _billingController.text.trim(),
        'staff_permission_defaults': _staffPermissionDefaults.toList(),
      });
      if (!mounted) {
        return;
      }
      setState(() => _successMessage = 'Settings saved successfully.');
      messenger.showSnackBar(
        const SnackBar(content: Text('Gym settings updated successfully.')),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AsyncStateView(
      isLoading: _loading,
      error: _error,
      onRetry: _load,
      loadingChild: const LoadingState(label: 'Loading gym settings...'),
      child: ListView(
        children: [
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Gym Settings',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 8),
                Text(
                  'Control attendance policy, notification preference access, billing placeholder notes, and default staff permissions from one surface.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                if (_successMessage != null) ...[
                  const SizedBox(height: 16),
                  StatusBadge(
                    label: _successMessage!,
                    color: AppColors.success,
                    icon: Icons.check_circle_rounded,
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Attendance Rule',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Prevent duplicate same-day check-ins'),
                  subtitle: const Text(
                    'Keep repeat same-day attendance records blocked unless the backend override allows it.',
                  ),
                  value: _preventDuplicateCheckIns,
                  onChanged: _saving
                      ? null
                      : (value) =>
                            setState(() => _preventDuplicateCheckIns = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Billing Settings Placeholder',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _billingController,
                  minLines: 4,
                  maxLines: 6,
                  decoration: const InputDecoration(
                    labelText: 'Billing notes',
                    hintText:
                        'Capture internal billing notes or future process details here.',
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Staff Permission Defaults',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: _permissionOptions.map((permission) {
                    final selected = _staffPermissionDefaults.contains(
                      permission,
                    );
                    return FilterChip(
                      selected: selected,
                      label: Text(_dashboardTitleCase(permission)),
                      onSelected: _saving
                          ? null
                          : (_) => setState(() {
                              selected
                                  ? _staffPermissionDefaults.remove(permission)
                                  : _staffPermissionDefaults.add(permission);
                            }),
                    );
                  }).toList(),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Shortcuts',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    QuickActionButton(
                      label: 'Notification Preferences',
                      icon: Icons.notifications_active_rounded,
                      onPressed: widget.onOpenNotificationPreferences,
                    ),
                    QuickActionButton(
                      label: 'Gym Profile',
                      icon: Icons.store_mall_directory_rounded,
                      onPressed: () => widget.onOpenSection('Gym Profile'),
                    ),
                    QuickActionButton(
                      label: 'Permissions',
                      icon: Icons.badge_rounded,
                      onPressed: () => widget.onOpenSection('Staff'),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          GradientButton(
            label: 'Save Settings',
            icon: Icons.save_rounded,
            loading: _saving,
            expanded: true,
            onPressed: _saving ? null : _save,
          ),
        ],
      ),
    );
  }
}

class _PlatformSettingsWorkspaceSection extends StatefulWidget {
  const _PlatformSettingsWorkspaceSection({
    super.key,
    required this.repository,
    required this.onOpenNotificationPreferences,
    required this.onOpenSection,
  });

  final AdminRepository repository;
  final Future<void> Function() onOpenNotificationPreferences;
  final ValueChanged<String> onOpenSection;

  @override
  State<_PlatformSettingsWorkspaceSection> createState() =>
      _PlatformSettingsWorkspaceSectionState();
}

class _PlatformSettingsWorkspaceSectionState
    extends State<_PlatformSettingsWorkspaceSection> {
  final _supportEmailController = TextEditingController();
  final _supportPhoneController = TextEditingController();
  final _privacyUrlController = TextEditingController();
  final _termsUrlController = TextEditingController();
  final _commissionController = TextEditingController();
  final _promotedPriceController = TextEditingController();
  final _featuredPriceController = TextEditingController();
  final _bannersPlaceholderController = TextEditingController();
  final _featureFlagsController = TextEditingController();
  bool _loading = true;
  bool _saving = false;
  String? _error;
  String? _successMessage;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _supportEmailController.dispose();
    _supportPhoneController.dispose();
    _privacyUrlController.dispose();
    _termsUrlController.dispose();
    _commissionController.dispose();
    _promotedPriceController.dispose();
    _featuredPriceController.dispose();
    _bannersPlaceholderController.dispose();
    _featureFlagsController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final settings = await widget.repository.fetchPlatformSettings();
      if (!mounted) {
        return;
      }
      _supportEmailController.text =
          settings['support_email']?.toString() ?? '';
      _supportPhoneController.text =
          settings['support_phone']?.toString() ?? '';
      _privacyUrlController.text =
          settings['privacy_policy_url']?.toString() ?? '';
      _termsUrlController.text = settings['terms_url']?.toString() ?? '';
      _commissionController.text =
          settings['default_commission_percentage']?.toString() ?? '';
      _promotedPriceController.text =
          settings['promoted_listing_price']?.toString() ?? '';
      _featuredPriceController.text =
          settings['featured_listing_price']?.toString() ?? '';
      _bannersPlaceholderController.text =
          settings['app_banners_placeholder']?.toString() ?? '';
      _featureFlagsController.text =
          settings['feature_flags_placeholder']?.toString() ?? '';
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _successMessage = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.repository.updatePlatformSettings({
        'support_email': _nullable(_supportEmailController.text),
        'support_phone': _nullable(_supportPhoneController.text),
        'privacy_policy_url': _nullable(_privacyUrlController.text),
        'terms_url': _nullable(_termsUrlController.text),
        'default_commission_percentage': _numberOrNull(
          _commissionController.text,
        ),
        'promoted_listing_price': _numberOrNull(_promotedPriceController.text),
        'featured_listing_price': _numberOrNull(_featuredPriceController.text),
        'app_banners_placeholder': _nullable(
          _bannersPlaceholderController.text,
        ),
        'feature_flags_placeholder': _nullable(_featureFlagsController.text),
      });
      if (!mounted) {
        return;
      }
      setState(() => _successMessage = 'Platform settings saved.');
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Platform settings updated successfully.'),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  String? _nullable(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  double? _numberOrNull(String value) => double.tryParse(value.trim());

  @override
  Widget build(BuildContext context) {
    return AsyncStateView(
      isLoading: _loading,
      error: _error,
      onRetry: _load,
      loadingChild: const LoadingState(label: 'Loading platform settings...'),
      child: ListView(
        children: [
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Platform Settings',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 8),
                Text(
                  'Control support contacts, policy links, listing prices, and placeholder platform feature flags.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                if (_successMessage != null) ...[
                  const SizedBox(height: 16),
                  StatusBadge(
                    label: _successMessage!,
                    color: AppColors.success,
                    icon: Icons.check_circle_rounded,
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Support', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 12),
                TextField(
                  controller: _supportEmailController,
                  decoration: const InputDecoration(labelText: 'Support email'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _supportPhoneController,
                  decoration: const InputDecoration(labelText: 'Support phone'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _privacyUrlController,
                  decoration: const InputDecoration(
                    labelText: 'Privacy policy URL',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _termsUrlController,
                  decoration: const InputDecoration(labelText: 'Terms URL'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Commercial Controls',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _commissionController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Default commission %',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _promotedPriceController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Promoted listing price',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _featuredPriceController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Featured listing price',
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Platform Placeholders',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _bannersPlaceholderController,
                  minLines: 3,
                  maxLines: 5,
                  decoration: const InputDecoration(
                    labelText: 'App banners placeholder',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _featureFlagsController,
                  minLines: 3,
                  maxLines: 5,
                  decoration: const InputDecoration(
                    labelText: 'Feature flags placeholder',
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                QuickActionButton(
                  label: 'Notification Preferences',
                  icon: Icons.notifications_active_rounded,
                  onPressed: widget.onOpenNotificationPreferences,
                ),
                QuickActionButton(
                  label: 'Banners',
                  icon: Icons.view_day_rounded,
                  onPressed: () => widget.onOpenSection('Banners'),
                ),
                QuickActionButton(
                  label: 'Audit Logs',
                  icon: Icons.history_rounded,
                  onPressed: () => widget.onOpenSection('Audit Logs'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          GradientButton(
            label: 'Save Platform Settings',
            icon: Icons.save_rounded,
            loading: _saving,
            expanded: true,
            onPressed: _saving ? null : _save,
          ),
        ],
      ),
    );
  }
}

class _GymAuditLogsWorkspaceSection extends StatefulWidget {
  const _GymAuditLogsWorkspaceSection({
    super.key,
    required this.appUser,
    required this.repository,
  });

  final AppUser appUser;
  final AdminRepository repository;

  @override
  State<_GymAuditLogsWorkspaceSection> createState() =>
      _GymAuditLogsWorkspaceSectionState();
}

class _GymAuditLogsWorkspaceSectionState
    extends State<_GymAuditLogsWorkspaceSection> {
  final TextEditingController _actionController = TextEditingController();
  final TextEditingController _actorController = TextEditingController();
  final TextEditingController _startDateController = TextEditingController();
  final TextEditingController _endDateController = TextEditingController();
  List<Map<String, dynamic>> _logs = const [];
  List<Map<String, dynamic>> _branches = const [];
  bool _loading = true;
  String? _error;
  int _page = 1;
  int _lastPage = 1;
  int? _branchId;

  bool get _hasMore => _page < _lastPage;

  @override
  void initState() {
    super.initState();
    _loadBranches();
    _load(reset: true);
  }

  @override
  void dispose() {
    _actionController.dispose();
    _actorController.dispose();
    _startDateController.dispose();
    _endDateController.dispose();
    super.dispose();
  }

  Future<void> _loadBranches() async {
    try {
      final response = await widget.repository.fetchCollection(
        '/gym/branches',
        perPage: 100,
      );
      if (!mounted) {
        return;
      }
      setState(() => _branches = response.items);
    } catch (_) {
      // Keep audit list usable without branch options.
    }
  }

  Future<void> _load({bool reset = false}) async {
    setState(() {
      _loading = true;
      _error = null;
      if (reset) {
        _page = 1;
        _lastPage = 1;
      }
    });
    try {
      final response = await widget.repository.fetchGymAuditLogs(
        page: _page,
        perPage: 20,
        queryParameters: {
          if (_actionController.text.trim().isNotEmpty)
            'action': _actionController.text.trim(),
          if (_actorController.text.trim().isNotEmpty)
            'actor': _actorController.text.trim(),
          if (_startDateController.text.trim().isNotEmpty)
            'start_date': _startDateController.text.trim(),
          if (_endDateController.text.trim().isNotEmpty)
            'end_date': _endDateController.text.trim(),
          if (_branchId != null) 'branch_id': _branchId,
        },
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _logs = reset ? response.items : [..._logs, ...response.items];
        _page = response.currentPage;
        _lastPage = response.lastPage;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  String _valuePreview(Object? raw) {
    if (raw == null) {
      return '--';
    }
    if (raw is Map || raw is List) {
      final text = const JsonEncoder.withIndent('  ').convert(raw);
      return text.length > 320 ? '${text.substring(0, 320)}...' : text;
    }
    final text = raw.toString();
    return text.length > 320 ? '${text.substring(0, 320)}...' : text;
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Audit Logs',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(
                'Inspect scoped operational changes across members, attendance, payments, public listing, permissions, and other gym activity.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _actionController,
                      decoration: const InputDecoration(labelText: 'Action'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _actorController,
                      decoration: const InputDecoration(labelText: 'Actor'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _startDateController,
                      decoration: const InputDecoration(
                        labelText: 'Start date (YYYY-MM-DD)',
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _endDateController,
                      decoration: const InputDecoration(
                        labelText: 'End date (YYYY-MM-DD)',
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int?>(
                initialValue: _branchId,
                decoration: const InputDecoration(labelText: 'Branch'),
                items: [
                  const DropdownMenuItem<int?>(
                    value: null,
                    child: Text('All branches'),
                  ),
                  ..._branches.map(
                    (branch) => DropdownMenuItem<int?>(
                      value: (branch['id'] as num?)?.toInt(),
                      child: Text(branch['name']?.toString() ?? 'Branch'),
                    ),
                  ),
                ],
                onChanged: (value) => setState(() => _branchId = value),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  GradientButton(
                    label: 'Apply Filters',
                    icon: Icons.filter_alt_rounded,
                    onPressed: () => _load(reset: true),
                  ),
                  OutlinedButton.icon(
                    onPressed: () {
                      _actionController.clear();
                      _actorController.clear();
                      _startDateController.clear();
                      _endDateController.clear();
                      setState(() => _branchId = null);
                      _load(reset: true);
                    },
                    icon: const Icon(Icons.restart_alt_rounded),
                    label: const Text('Reset'),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Expanded(
          child: _isPermissionError(_error)
              ? const EmptyState(
                  title: 'Permission denied',
                  message:
                      'The current role needs audit-log access to inspect this activity timeline.',
                  icon: Icons.lock_outline_rounded,
                )
              : AsyncStateView(
                  isLoading: _loading && _logs.isEmpty,
                  error: _error,
                  onRetry: () => _load(reset: true),
                  loadingChild: const _CollectionLoadingSkeleton(
                    destinationTitle: 'Notifications',
                  ),
                  isEmpty: _logs.isEmpty && !_loading,
                  emptyTitle: 'No audit logs',
                  emptyMessage:
                      'No audit events match the selected filters right now.',
                  emptyIcon: Icons.history_toggle_off_rounded,
                  child: RefreshIndicator(
                    onRefresh: () => _load(reset: true),
                    child: ListView.separated(
                      itemCount: _logs.length + (_hasMore ? 1 : 0),
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, index) {
                        if (index >= _logs.length) {
                          return Center(
                            child: OutlinedButton.icon(
                              onPressed: _loading
                                  ? null
                                  : () {
                                      setState(() => _page += 1);
                                      _load();
                                    },
                              icon: const Icon(Icons.expand_more_rounded),
                              label: const Text('Load more'),
                            ),
                          );
                        }
                        final log = _logs[index];
                        final actor = _recordMap(log['actor']);
                        final branch = _recordMap(log['branch']);
                        final oldValues = log['old_values'];
                        final newValues = log['new_values'];
                        return RevealOnBuild(
                          delay: Duration(milliseconds: 40 * (index % 8)),
                          child: PremiumCard(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            log['action']?.toString() ??
                                                'Audit event',
                                            style: Theme.of(
                                              context,
                                            ).textTheme.titleLarge,
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            '${actor['name'] ?? log['actor_role'] ?? 'System'} • ${_formatDateTime(log['occurred_at'] ?? log['created_at'])}',
                                            style: Theme.of(
                                              context,
                                            ).textTheme.bodySmall,
                                          ),
                                        ],
                                      ),
                                    ),
                                    if (branch['name']?.toString().isNotEmpty ==
                                        true)
                                      StatusBadge(
                                        label: branch['name'].toString(),
                                        icon: Icons.location_city_rounded,
                                        color: AppColors.info,
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: [
                                    if (log['subject']?.toString().isNotEmpty ==
                                        true)
                                      _InlineBadge(
                                        label: log['subject'].toString(),
                                      ),
                                    if (log['event']?.toString().isNotEmpty ==
                                        true)
                                      _InlineBadge(
                                        label: log['event'].toString(),
                                      ),
                                  ],
                                ),
                                if (oldValues != null || newValues != null) ...[
                                  const SizedBox(height: 14),
                                  Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Expanded(
                                        child: Container(
                                          padding: const EdgeInsets.all(14),
                                          decoration: BoxDecoration(
                                            color: AppColors.surfaceStrong
                                                .withValues(alpha: 0.65),
                                            borderRadius: BorderRadius.circular(
                                              18,
                                            ),
                                            border: Border.all(
                                              color: AppColors.stroke,
                                            ),
                                          ),
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                'Old values',
                                                style: Theme.of(
                                                  context,
                                                ).textTheme.titleMedium,
                                              ),
                                              const SizedBox(height: 8),
                                              SelectableText(
                                                _valuePreview(oldValues),
                                                style: Theme.of(
                                                  context,
                                                ).textTheme.bodySmall,
                                              ),
                                            ],
                                          ),
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Container(
                                          padding: const EdgeInsets.all(14),
                                          decoration: BoxDecoration(
                                            color: AppColors.surfaceStrong
                                                .withValues(alpha: 0.65),
                                            borderRadius: BorderRadius.circular(
                                              18,
                                            ),
                                            border: Border.all(
                                              color: AppColors.stroke,
                                            ),
                                          ),
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                'New values',
                                                style: Theme.of(
                                                  context,
                                                ).textTheme.titleMedium,
                                              ),
                                              const SizedBox(height: 8),
                                              SelectableText(
                                                _valuePreview(newValues),
                                                style: Theme.of(
                                                  context,
                                                ).textTheme.bodySmall,
                                              ),
                                            ],
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ),
        ),
      ],
    );
  }
}

class _CollectionLoadingSkeleton extends StatelessWidget {
  const _CollectionLoadingSkeleton({required this.destinationTitle});

  final String destinationTitle;

  @override
  Widget build(BuildContext context) {
    Widget content;

    switch (destinationTitle) {
      case 'Members':
        content = Column(
          children: const [
            SkeletonListCard(lines: 3),
            SizedBox(height: 10),
            SkeletonListCard(lines: 3),
            SizedBox(height: 10),
            SkeletonListCard(lines: 3),
          ],
        );
        break;
      case 'Trainers':
      case 'Staff':
        content = Column(
          children: const [
            SkeletonListCard(lines: 2),
            SizedBox(height: 10),
            SkeletonListCard(lines: 2),
            SizedBox(height: 10),
            SkeletonListCard(lines: 2),
          ],
        );
        break;
      case 'Notifications':
      case 'Announcements':
        content = const SkeletonNotificationsList(items: 5);
        break;
      case 'Payments':
        content = const SkeletonHistoryList(items: 5);
        break;
      default:
        content = const SkeletonReportsTable(rows: 5, columns: 4);
    }

    return SkeletonPulse(
      child: Column(
        children: [
          Row(
            children: const [
              Expanded(child: SkeletonBox(height: 54, radius: 18)),
              SizedBox(width: 12),
              SkeletonBox(height: 46, width: 92, radius: 16),
            ],
          ),
          const SizedBox(height: 16),
          Expanded(child: SingleChildScrollView(child: content)),
        ],
      ),
    );
  }
}

class _CollectionRecordCard extends StatelessWidget {
  const _CollectionRecordCard({
    required this.appUser,
    required this.destinationTitle,
    required this.item,
    required this.title,
    required this.subtitle,
    required this.onOpenMemberDetail,
    required this.onCollectPayment,
    required this.onSetCustomFee,
    required this.onMarkAttendance,
    required this.onAssignTrainer,
    required this.onRenewMembership,
    required this.onSendReminder,
    required this.onAssignMembers,
    required this.onViewTrainerPerformance,
    required this.onDeactivateTrainer,
    required this.onPlatformApprove,
    required this.onPlatformReject,
    required this.onPlatformVerify,
    required this.onPlatformFeature,
    required this.onPlatformPromote,
    required this.onPlatformDeactivate,
    required this.onPlatformViewDetails,
    required this.onPlatformUserViewDetails,
    required this.onPlatformUserToggle,
    required this.onPlatformGymOwnerViewDetails,
    required this.onPlatformGymOwnerToggle,
    required this.onPlatformFacilityViewDetails,
    required this.onPlatformFacilityToggle,
    required this.onPlatformFacilityDelete,
    required this.onPlatformFacilityEdit,
    required this.onStaffViewDetails,
    required this.onStaffToggle,
    required this.onStaffDelete,
    required this.onStaffEdit,
    required this.onBranchViewDetails,
    required this.onBranchToggle,
    required this.onBranchDelete,
    required this.onBranchEdit,
    required this.onMembershipPlanViewDetails,
    required this.onMembershipPlanToggle,
    required this.onMembershipViewDetails,
    this.onTap,
  });

  final AppUser appUser;
  final String destinationTitle;
  final Map<String, dynamic> item;
  final String title;
  final String subtitle;
  final VoidCallback onOpenMemberDetail;
  final VoidCallback onCollectPayment;
  final VoidCallback onSetCustomFee;
  final VoidCallback onMarkAttendance;
  final VoidCallback onAssignTrainer;
  final VoidCallback onRenewMembership;
  final VoidCallback onSendReminder;
  final VoidCallback onAssignMembers;
  final VoidCallback onViewTrainerPerformance;
  final VoidCallback onDeactivateTrainer;
  final VoidCallback onPlatformApprove;
  final VoidCallback onPlatformReject;
  final VoidCallback onPlatformVerify;
  final VoidCallback onPlatformFeature;
  final VoidCallback onPlatformPromote;
  final VoidCallback onPlatformDeactivate;
  final VoidCallback onPlatformViewDetails;
  final VoidCallback onPlatformUserViewDetails;
  final VoidCallback onPlatformUserToggle;
  final VoidCallback onPlatformGymOwnerViewDetails;
  final VoidCallback onPlatformGymOwnerToggle;
  final VoidCallback onPlatformFacilityViewDetails;
  final VoidCallback onPlatformFacilityToggle;
  final VoidCallback onPlatformFacilityDelete;
  final VoidCallback onPlatformFacilityEdit;
  final VoidCallback onStaffViewDetails;
  final VoidCallback onStaffToggle;
  final VoidCallback onStaffDelete;
  final VoidCallback onStaffEdit;
  final VoidCallback onBranchViewDetails;
  final VoidCallback onBranchToggle;
  final VoidCallback onBranchDelete;
  final VoidCallback onBranchEdit;
  final VoidCallback onMembershipPlanViewDetails;
  final VoidCallback onMembershipPlanToggle;
  final VoidCallback onMembershipViewDetails;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final memberProfile = Map<String, dynamic>.from(
      item['member_profile'] as Map? ?? const {},
    );
    final trainerProfile = Map<String, dynamic>.from(
      item['trainer_profile'] as Map? ?? const {},
    );
    final roles = (item['roles'] as List<dynamic>? ?? const [])
        .map((role) => role.toString())
        .toList();
    final dueAmount = _toDouble(item['due_amount']);
    final paidAmount = _toDouble(item['amount_paid']);
    final payableAmount = _toDouble(item['final_payable_amount']);
    final overdue =
        (item['payment_status']?.toString().toLowerCase() == 'overdue');

    if (destinationTitle == 'Members') {
      return _AdminMemberSmartCard(
        appUser: appUser,
        item: item,
        memberProfile: memberProfile,
        title: title,
        subtitle: subtitle,
        onOpenProfile: onOpenMemberDetail,
        onCollectPayment: onCollectPayment,
        onSetCustomFee: onSetCustomFee,
        onMarkAttendance: onMarkAttendance,
        onAssignTrainer: onAssignTrainer,
        onRenewMembership: onRenewMembership,
        onSendReminder: onSendReminder,
      );
    }

    if (destinationTitle == 'Custom Fees') {
      return _CustomFeeSmartCard(
        item: item,
        title: title,
        onTap: onTap ?? onSetCustomFee,
        onEdit: onSetCustomFee,
      );
    }

    if (destinationTitle == 'Payments' ||
        destinationTitle == 'Dues' ||
        destinationTitle == 'Memberships') {
      if (destinationTitle == 'Memberships') {
        return _GymMembershipSmartCard(
          item: item,
          title: title,
          onCollectPayment: onCollectPayment,
          onViewDetails: onMembershipViewDetails,
        );
      }
      return _PaymentDueSmartCard(
        item: item,
        title: title,
        dueAmount: dueAmount,
        paidAmount: paidAmount,
        payableAmount: payableAmount,
        overdue: overdue,
        onCollectPayment: onCollectPayment,
        onOpenProfile: onTap,
      );
    }

    if (destinationTitle == 'Trainers') {
      return _AdminTrainerSmartCard(
        appUser: appUser,
        item: item,
        title: title,
        subtitle: subtitle,
        onAssignMembers: onAssignMembers,
        onViewPerformance: onViewTrainerPerformance,
        onDeactivateTrainer: onDeactivateTrainer,
      );
    }

    if (destinationTitle == 'Membership Plans') {
      return _MembershipPlanSmartCard(
        item: item,
        title: title,
        subtitle: subtitle,
        onViewDetails: onMembershipPlanViewDetails,
        onToggleStatus: onMembershipPlanToggle,
      );
    }

    if (destinationTitle == 'Gyms') {
      return _PlatformGymSmartCard(
        item: item,
        title: title,
        subtitle: subtitle,
        onApprove: onPlatformApprove,
        onReject: onPlatformReject,
        onVerify: onPlatformVerify,
        onFeature: onPlatformFeature,
        onPromote: onPlatformPromote,
        onDeactivate: onPlatformDeactivate,
        onViewDetails: onPlatformViewDetails,
      );
    }

    if (destinationTitle == 'Users') {
      return _PlatformUserSmartCard(
        item: item,
        title: title,
        subtitle: subtitle,
        onViewDetails: onPlatformUserViewDetails,
        onActivateOrDeactivate: onPlatformUserToggle,
      );
    }

    if (destinationTitle == 'Gym Owners') {
      return _PlatformGymOwnerSmartCard(
        item: item,
        title: title,
        subtitle: subtitle,
        onViewDetails: onPlatformGymOwnerViewDetails,
        onActivateOrDeactivate: onPlatformGymOwnerToggle,
      );
    }

    if (destinationTitle == 'Facilities') {
      return _PlatformFacilitySmartCard(
        item: item,
        title: title,
        subtitle: subtitle,
        onViewDetails: onPlatformFacilityViewDetails,
        onToggleStatus: onPlatformFacilityToggle,
        onDelete: onPlatformFacilityDelete,
        onEdit: onPlatformFacilityEdit,
      );
    }

    if (destinationTitle == 'Staff') {
      return _GymStaffSmartCard(
        item: item,
        title: title,
        subtitle: subtitle,
        onViewDetails: onStaffViewDetails,
        onActivateOrDeactivate: onStaffToggle,
        onEdit: onStaffEdit,
        onDelete: onStaffDelete,
      );
    }

    if (destinationTitle == 'Branches') {
      return _GymBranchSmartCard(
        item: item,
        title: title,
        subtitle: subtitle,
        onViewDetails: onBranchViewDetails,
        onToggleStatus: onBranchToggle,
        onDelete: onBranchDelete,
        onEdit: onBranchEdit,
      );
    }

    return PremiumCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              if (_statusText().isNotEmpty) _InlineBadge(label: _statusText()),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            _primarySubtitle(memberProfile, trainerProfile, roles, subtitle),
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _chips(memberProfile, trainerProfile, roles),
          ),
        ],
      ),
    );
  }

  String _primarySubtitle(
    Map<String, dynamic> memberProfile,
    Map<String, dynamic> trainerProfile,
    List<String> roles,
    String fallback,
  ) {
    switch (destinationTitle) {
      case 'Members':
        return memberProfile['fitness_goal']?.toString() ??
            memberProfile['experience_level']?.toString() ??
            fallback;
      case 'Trainers':
        return trainerProfile['specialization']?.toString() ?? fallback;
      case 'Staff':
        return roles.join(', ').replaceAll('_', ' ');
      case 'Membership Plans':
        return 'Duration ${item['duration_days'] ?? '--'} days • Price ${_formatCurrency(item['plan_price'])}';
      case 'Payments':
      case 'Dues':
        return 'Due ${_formatCurrency(item['due_amount'])} • Paid ${_formatCurrency(item['amount_paid'])}';
      case 'Announcements':
        return item['message']?.toString() ?? fallback;
      default:
        return fallback;
    }
  }

  String _statusText() {
    if (item['payment_status'] != null) {
      return item['payment_status'].toString().toUpperCase();
    }
    if (item['status'] != null) {
      return item['status'].toString().toUpperCase();
    }
    if (item['approval_status'] != null) {
      return item['approval_status'].toString().toUpperCase();
    }
    return '';
  }

  List<Widget> _chips(
    Map<String, dynamic> memberProfile,
    Map<String, dynamic> trainerProfile,
    List<String> roles,
  ) {
    switch (destinationTitle) {
      case 'Members':
        return [
          _InlineBadge(
            label: memberProfile['membership_status']?.toString() ?? 'Member',
          ),
          if (memberProfile['assigned_trainer_user_id'] != null)
            _InlineBadge(label: 'Trainer Assigned'),
        ];
      case 'Trainers':
        return [
          _InlineBadge(
            label: '${trainerProfile['experience_years'] ?? 0} yrs exp',
          ),
        ];
      case 'Staff':
        return roles
            .map((role) => _InlineBadge(label: role.replaceAll('_', ' ')))
            .toList();
      case 'Payments':
      case 'Dues':
        return [
          _InlineBadge(label: 'Expiry ${item['expiry_date'] ?? '--'}'),
          if ((item['due_amount'] as num?)?.toDouble() != 0)
            const _InlineBadge(label: 'Attention'),
        ];
      default:
        return const <Widget>[];
    }
  }
}

class _AdminMemberSmartCard extends StatelessWidget {
  const _AdminMemberSmartCard({
    required this.appUser,
    required this.item,
    required this.memberProfile,
    required this.title,
    required this.subtitle,
    required this.onOpenProfile,
    required this.onCollectPayment,
    required this.onSetCustomFee,
    required this.onMarkAttendance,
    required this.onAssignTrainer,
    required this.onRenewMembership,
    required this.onSendReminder,
  });

  final AppUser appUser;
  final Map<String, dynamic> item;
  final Map<String, dynamic> memberProfile;
  final String title;
  final String subtitle;
  final VoidCallback onOpenProfile;
  final VoidCallback onCollectPayment;
  final VoidCallback onSetCustomFee;
  final VoidCallback onMarkAttendance;
  final VoidCallback onAssignTrainer;
  final VoidCallback onRenewMembership;
  final VoidCallback onSendReminder;

  bool _canAny(List<String> permissions) {
    return _hasAnyAdminPermission(appUser, permissions);
  }

  @override
  Widget build(BuildContext context) {
    final dueAmount = _toDouble(
      memberProfile['due_amount'] ?? item['due_amount'],
    );
    final assignedTrainerId = memberProfile['assigned_trainer_user_id'];
    final membershipStatus =
        memberProfile['membership_status']?.toString() ?? 'active';
    final engagement = Map<String, dynamic>.from(
      item['engagement_score'] as Map? ?? const {},
    );
    final engagementCategory = engagement['category']?.toString();
    final engagementScore = (engagement['score'] as num?)?.toInt();

    return PremiumCard(
      onTap: onOpenProfile,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 28,
                backgroundImage: item['avatar']?.toString().isNotEmpty == true
                    ? NetworkImage(item['avatar'].toString())
                    : null,
                child: item['avatar']?.toString().isNotEmpty == true
                    ? null
                    : const Icon(Icons.person_outline_rounded),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      item['email']?.toString().isNotEmpty == true
                          ? item['email'].toString()
                          : subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _InlineBadge(
                          label: membershipStatus.replaceAll('_', ' '),
                        ),
                        if (engagementCategory != null)
                          _InlineBadge(
                            label:
                                '$engagementCategory ${engagementScore ?? '--'}/100',
                          ),
                        if (dueAmount > 0)
                          _InlineBadge(
                            label: 'Due ${_formatCurrency(dueAmount)}',
                          ),
                        _InlineBadge(
                          label: assignedTrainerId != null
                              ? 'Trainer assigned'
                              : 'Trainer pending',
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Engagement',
                  value: engagementCategory ?? 'Pending',
                  icon: Icons.qr_code_scanner_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Last check-in',
                  value:
                      ((engagement['days_since_last_check_in'] as num?)
                              ?.toInt()) !=
                          null
                      ? '${(engagement['days_since_last_check_in'] as num).toInt()}d ago'
                      : 'No check-in',
                  icon: Icons.history_toggle_off_rounded,
                ),
              ),
            ],
          ),
          if (engagement['summary']?.toString().isNotEmpty == true) ...[
            const SizedBox(height: 12),
            Text(
              engagement['summary'].toString(),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              if (_canAny([
                'payment.manage',
                'payment.view',
                'collect_payment',
                'view_billing',
              ]))
                QuickActionButton(
                  label: 'Collect',
                  icon: Icons.payments_rounded,
                  onPressed: onCollectPayment,
                ),
              if (_canAny(['member.manage', 'manage_members']))
                QuickActionButton(
                  label: 'Assign Trainer',
                  icon: Icons.person_add_alt_1_rounded,
                  onPressed: onAssignTrainer,
                ),
              if (_canAny(['membership.manage']))
                QuickActionButton(
                  label: 'Assign Membership',
                  icon: Icons.autorenew_rounded,
                  onPressed: onRenewMembership,
                ),
              if (_canAny(['edit_custom_fee']))
                QuickActionButton(
                  label: 'Custom Fee',
                  icon: Icons.tune_rounded,
                  onPressed: onSetCustomFee,
                ),
              if (_canAny(['attendance.manage', 'manage_attendance']))
                QuickActionButton(
                  label: 'Attendance',
                  icon: Icons.how_to_reg_rounded,
                  onPressed: onMarkAttendance,
                ),
              if (_canAny([
                'announcement.manage',
                'announcement.view',
                'notification.manage',
                'send_announcements',
              ]))
                QuickActionButton(
                  label: 'Reminder',
                  icon: Icons.notifications_active_rounded,
                  onPressed: onSendReminder,
                ),
              QuickActionButton(
                label: 'Profile',
                icon: Icons.visibility_rounded,
                onPressed: onOpenProfile,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CustomFeeSmartCard extends StatelessWidget {
  const _CustomFeeSmartCard({
    required this.item,
    required this.title,
    required this.onTap,
    required this.onEdit,
  });

  final Map<String, dynamic> item;
  final String title;
  final VoidCallback onTap;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final member = _recordMap(item['member']);
    final plan = _recordMap(item['membership_plan']);
    final originalPrice = _toDouble(item['default_plan_price']);
    final finalPayable = _toDouble(item['final_payable_amount']);
    final paid = _toDouble(item['amount_paid']);
    final due = _toDouble(item['due_amount']);
    final reason =
        item['custom_fee_reason']?.toString() ?? 'Reason not provided';
    final updatedBy =
        _recordMap(
          item['latest_custom_fee_audit_log'],
        )['changed_by']?.toString() ??
        _recordMap(
          item['latest_custom_fee_audit_log'],
        )['changed_by_name']?.toString() ??
        '--';

    return PremiumCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      member['name']?.toString() ?? title,
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${plan['name'] ?? 'Membership plan'} • Branch ${item['branch_id'] ?? '--'}',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: due > 0 ? 'Due' : 'Settled',
                color: due > 0 ? AppColors.warning : AppColors.success,
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Original',
                  value: _formatCurrency(originalPrice),
                  icon: Icons.receipt_long_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Final',
                  value: _formatCurrency(finalPayable),
                  icon: Icons.tune_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Due',
                  value: _formatCurrency(due),
                  icon: Icons.payments_outlined,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(reason, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _InlineBadge(label: 'Paid ${_formatCurrency(paid)}'),
              _InlineBadge(label: 'Updated by $updatedBy'),
            ],
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              QuickActionButton(
                label: 'Review',
                icon: Icons.visibility_rounded,
                onPressed: onTap,
              ),
              QuickActionButton(
                label: 'Edit',
                icon: Icons.edit_rounded,
                onPressed: onEdit,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MembershipPlanSmartCard extends StatelessWidget {
  const _MembershipPlanSmartCard({
    required this.item,
    required this.title,
    required this.subtitle,
    required this.onViewDetails,
    required this.onToggleStatus,
  });

  final Map<String, dynamic> item;
  final String title;
  final String subtitle;
  final VoidCallback onViewDetails;
  final VoidCallback onToggleStatus;

  @override
  Widget build(BuildContext context) {
    final branch = _recordMap(item['branch']);
    final isActive = item['status']?.toString() == 'active';
    return PremiumCard(
      onTap: onViewDetails,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      branch['name']?.toString() ?? subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: isActive ? 'Active' : 'Inactive',
                color: isActive ? AppColors.success : AppColors.warning,
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Duration',
                  value: '${item['duration_days'] ?? '--'} days',
                  icon: Icons.timelapse_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Price',
                  value: _formatCurrency(item['plan_price']),
                  icon: Icons.currency_rupee_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Members',
                  value: '${item['member_memberships_count'] ?? 0}',
                  icon: Icons.groups_rounded,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              QuickActionButton(
                label: 'Details',
                icon: Icons.visibility_rounded,
                onPressed: onViewDetails,
              ),
              QuickActionButton(
                label: isActive ? 'Deactivate' : 'Activate',
                icon: Icons.power_settings_new_rounded,
                onPressed: onToggleStatus,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GymMembershipSmartCard extends StatelessWidget {
  const _GymMembershipSmartCard({
    required this.item,
    required this.title,
    required this.onCollectPayment,
    required this.onViewDetails,
  });

  final Map<String, dynamic> item;
  final String title;
  final VoidCallback onCollectPayment;
  final VoidCallback onViewDetails;

  @override
  Widget build(BuildContext context) {
    final plan = _recordMap(item['membership_plan']);
    final member = _recordMap(item['member']);
    final status = item['status']?.toString() ?? 'active';
    final paymentStatus = item['payment_status']?.toString() ?? '--';
    return PremiumCard(
      onTap: onViewDetails,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      '${member['name'] ?? 'Member'} • ${plan['name'] ?? 'Plan'}',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: status.replaceAll('_', ' '),
                color: _badgeColorForLabel(status),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _InlineBadge(
                label: 'Payment ${paymentStatus.replaceAll('_', ' ')}',
              ),
              _InlineBadge(label: 'Expiry ${item['expiry_date'] ?? '--'}'),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Payable',
                  value: _formatCurrency(item['final_payable_amount']),
                  icon: Icons.receipt_long_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Paid',
                  value: _formatCurrency(item['amount_paid']),
                  icon: Icons.check_circle_outline_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Due',
                  value: _formatCurrency(item['due_amount']),
                  icon: Icons.payments_outlined,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              QuickActionButton(
                label: 'Details',
                icon: Icons.visibility_rounded,
                onPressed: onViewDetails,
              ),
              QuickActionButton(
                label: 'Collect',
                icon: Icons.currency_rupee_rounded,
                onPressed: onCollectPayment,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AdminTrainerSmartCard extends StatelessWidget {
  const _AdminTrainerSmartCard({
    required this.appUser,
    required this.item,
    required this.title,
    required this.subtitle,
    required this.onAssignMembers,
    required this.onViewPerformance,
    required this.onDeactivateTrainer,
  });

  final AppUser appUser;
  final Map<String, dynamic> item;
  final String title;
  final String subtitle;
  final VoidCallback onAssignMembers;
  final VoidCallback onViewPerformance;
  final VoidCallback onDeactivateTrainer;

  bool _canAny(List<String> permissions) {
    return _hasAnyAdminPermission(appUser, permissions);
  }

  @override
  Widget build(BuildContext context) {
    final profile = _recordMap(item['managed_trainer_profile']);
    final branch = _recordMap(profile['assigned_branch']);
    final isActive = item['is_active'] == true || profile['is_active'] == true;
    final certifications =
        (profile['certifications'] as List<dynamic>? ?? const [])
            .map((entry) => entry.toString())
            .where((entry) => entry.isNotEmpty)
            .take(2)
            .toList();
    final memberCountLabel =
        profile['client_count_placeholder']?['label']?.toString() ??
        '${profile['client_count'] ?? 0} active Members';

    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 28,
                backgroundImage: item['avatar']?.toString().isNotEmpty == true
                    ? NetworkImage(item['avatar'].toString())
                    : null,
                child: item['avatar']?.toString().isNotEmpty == true
                    ? null
                    : const Icon(Icons.fitness_center_rounded),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      profile['primary_specialization']?.toString() ??
                          profile['specializations']?.toString() ??
                          subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _InlineBadge(label: memberCountLabel),
                        if (branch.isNotEmpty)
                          _InlineBadge(
                            label: branch['name']?.toString() ?? 'Branch',
                          ),
                        _InlineBadge(
                          label:
                              '${profile['profile_completion_percentage'] ?? 0}% complete',
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: profile['is_active'] == false ? 'Inactive' : 'Active',
                color: profile['is_active'] == false
                    ? AppColors.warning
                    : AppColors.success,
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (certifications.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Text(
                certifications.join(' • '),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              if (_canAny(['member.manage', 'manage_members']))
                QuickActionButton(
                  label: 'Assign Members',
                  icon: Icons.group_add_rounded,
                  onPressed: onAssignMembers,
                ),
              QuickActionButton(
                label: 'Profile',
                icon: Icons.visibility_rounded,
                onPressed: onViewPerformance,
              ),
              if (_canAny(['trainer.manage', 'manage_trainers']))
                QuickActionButton(
                  label: isActive ? 'Deactivate' : 'Activate',
                  icon: isActive
                      ? Icons.person_off_rounded
                      : Icons.person_add_alt_1_rounded,
                  onPressed: onDeactivateTrainer,
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GymTrainerDetailSheet extends StatelessWidget {
  const _GymTrainerDetailSheet({
    required this.trainer,
    this.onEdit,
    this.onAssignMembers,
    this.onActivateOrDeactivate,
  });

  final Map<String, dynamic> trainer;
  final VoidCallback? onEdit;
  final VoidCallback? onAssignMembers;
  final VoidCallback? onActivateOrDeactivate;

  @override
  Widget build(BuildContext context) {
    final profile = _recordMap(trainer['managed_trainer_profile']);
    final gym = _recordMap(profile['gym']);
    final directBranch = _recordMap(profile['branch']);
    final assignedBranch = _recordMap(profile['assigned_branch']);
    final branch = directBranch.isNotEmpty ? directBranch : assignedBranch;
    final assignedMembers =
        (trainer['assignedMembers'] as List<dynamic>? ??
                trainer['assigned_members'] as List<dynamic>? ??
                const [])
            .map((entry) => _recordMap(entry))
            .toList();
    final specializations =
        (profile['specializations'] as List<dynamic>? ?? const [])
            .map((entry) => entry.toString())
            .where((entry) => entry.isNotEmpty)
            .toList();
    final certifications =
        (profile['certifications'] as List<dynamic>? ?? const [])
            .map((entry) => entry.toString())
            .where((entry) => entry.isNotEmpty)
            .toList();
    final availability =
        (profile['availability_slots'] as List<dynamic>? ?? const [])
            .map((entry) => _recordMap(entry))
            .where((slot) => slot.isNotEmpty)
            .take(3)
            .toList();
    final assignedMemberCount =
        (trainer['assigned_members_count'] as num?)?.toInt() ??
        (profile['assigned_members_count'] as num?)?.toInt() ??
        assignedMembers.length;
    final isActive =
        trainer['is_active'] == true || profile['is_active'] == true;
    final photoUrl = profile['profile_photo_url']?.toString().isNotEmpty == true
        ? profile['profile_photo_url']?.toString()
        : trainer['avatar']?.toString();

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ListView(
          shrinkWrap: true,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  radius: 34,
                  backgroundImage: photoUrl != null && photoUrl.isNotEmpty
                      ? NetworkImage(photoUrl)
                      : null,
                  child: photoUrl != null && photoUrl.isNotEmpty
                      ? null
                      : const Icon(Icons.fitness_center_rounded),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        trainer['name']?.toString() ?? 'Trainer detail',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        profile['experience_label']?.toString() ??
                            profile['primary_specialization']?.toString() ??
                            trainer['email']?.toString() ??
                            '--',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          StatusBadge(
                            label: isActive ? 'Active' : 'Inactive',
                            color: isActive
                                ? AppColors.success
                                : AppColors.warning,
                          ),
                          _InlineBadge(
                            label: '$assignedMemberCount assigned members',
                          ),
                          _InlineBadge(
                            label:
                                '${profile['profile_completion_percentage'] ?? 0}% profile complete',
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: _MiniMetric(
                    label: 'Branch',
                    value: branch['name']?.toString() ?? '--',
                    icon: Icons.location_city_rounded,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _MiniMetric(
                    label: 'Gym',
                    value: gym['name']?.toString() ?? '--',
                    icon: Icons.store_mall_directory_rounded,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _MiniMetric(
                    label: 'Experience',
                    value: '${profile['experience_years'] ?? 0} yrs',
                    icon: Icons.workspace_premium_rounded,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Profile',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: 'Email',
                    value: trainer['email']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Phone',
                    value: trainer['phone']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Specialization',
                    value:
                        profile['primary_specialization']?.toString() ??
                        profile['specialization']?.toString() ??
                        '--',
                  ),
                  _InfoRow(
                    label: 'Bio',
                    value: profile['bio']?.toString() ?? '--',
                  ),
                  if (specializations.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: specializations
                          .map(
                            (specialization) =>
                                _InlineBadge(label: specialization),
                          )
                          .toList(),
                    ),
                  ],
                  if (certifications.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(
                      'Certifications',
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: certifications
                          .map(
                            (certification) =>
                                _InlineBadge(label: certification),
                          )
                          .toList(),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Workload Summary',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: _MiniMetric(
                          label: 'Assigned Members',
                          value: '$assignedMemberCount',
                          icon: Icons.groups_rounded,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _MiniMetric(
                          label: 'Availability',
                          value: availability.isEmpty
                              ? 'Pending'
                              : '${availability.length} slots',
                          icon: Icons.schedule_rounded,
                        ),
                      ),
                    ],
                  ),
                  if (availability.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: availability.map((slot) {
                        final dayLabel =
                            slot['day']?.toString() ??
                            slot['label']?.toString() ??
                            'Slot';
                        final start = slot['start_time']?.toString() ?? '';
                        final end = slot['end_time']?.toString() ?? '';
                        final timeLabel = start.isNotEmpty || end.isNotEmpty
                            ? ' $start-$end'
                            : '';
                        return _InlineBadge(
                          label: '$dayLabel$timeLabel'.trim(),
                        );
                      }).toList(),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Assigned Members',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (assignedMembers.isEmpty)
                    const EmptyState(
                      title: 'No members assigned',
                      message:
                          'This trainer does not have assigned members yet.',
                      icon: Icons.group_off_rounded,
                    )
                  else
                    ...assignedMembers.map((member) {
                      final memberProfile = _recordMap(
                        member['member_profile'],
                      );
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: AppColors.surface.withValues(alpha: 0.5),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: AppColors.strokeStrong),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                member['name']?.toString() ?? 'Member',
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                              const SizedBox(height: 4),
                              Text(
                                memberProfile['fitness_goal']?.toString() ??
                                    member['email']?.toString() ??
                                    '--',
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                            ],
                          ),
                        ),
                      );
                    }),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Coach Notes',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Feedback and deeper trainer analytics will appear here as the coaching workflow grows.',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (onEdit != null)
                  QuickActionButton(
                    label: 'Edit',
                    icon: Icons.edit_rounded,
                    onPressed: onEdit,
                  ),
                if (onAssignMembers != null)
                  QuickActionButton(
                    label: 'Assign Members',
                    icon: Icons.group_add_rounded,
                    onPressed: onAssignMembers,
                  ),
                if (onActivateOrDeactivate != null)
                  QuickActionButton(
                    label: isActive ? 'Deactivate' : 'Activate',
                    icon: Icons.power_settings_new_rounded,
                    onPressed: onActivateOrDeactivate,
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PlatformGymSmartCard extends StatelessWidget {
  const _PlatformGymSmartCard({
    required this.item,
    required this.title,
    required this.subtitle,
    required this.onApprove,
    required this.onReject,
    required this.onVerify,
    required this.onFeature,
    required this.onPromote,
    required this.onDeactivate,
    required this.onViewDetails,
  });

  final Map<String, dynamic> item;
  final String title;
  final String subtitle;
  final VoidCallback onApprove;
  final VoidCallback onReject;
  final VoidCallback onVerify;
  final VoidCallback onFeature;
  final VoidCallback onPromote;
  final VoidCallback onDeactivate;
  final VoidCallback onViewDetails;

  @override
  Widget build(BuildContext context) {
    final owner = _recordMap(item['owner']);
    final logoUrl = item['logo_url']?.toString();
    final city = item['city']?.toString() ?? '--';
    final branchesCount = (item['branches_count'] as num?)?.toInt() ?? 0;
    final membersCount = (item['member_profiles_count'] as num?)?.toInt() ?? 0;
    final trainersCount =
        (item['trainer_profiles_count'] as num?)?.toInt() ?? 0;

    return PremiumCard(
      onTap: onViewDetails,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 26,
                backgroundImage: logoUrl != null && logoUrl.isNotEmpty
                    ? NetworkImage(logoUrl)
                    : null,
                child: logoUrl != null && logoUrl.isNotEmpty
                    ? null
                    : const Icon(Icons.storefront_rounded),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      owner['name']?.toString().isNotEmpty == true
                          ? '${owner['name']} • $city'
                          : city,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: item['is_active'] == true ? 'Active' : 'Inactive',
                color: item['is_active'] == true
                    ? AppColors.success
                    : AppColors.warning,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusBadge(
                label: item['approval_status']?.toString() ?? 'pending',
                color: _statusChipColor(item['approval_status']?.toString()),
              ),
              _InlineBadge(
                label: item['is_verified'] == true
                    ? 'Verified'
                    : 'Not verified',
              ),
              if (item['is_featured'] == true)
                const _InlineBadge(label: 'Featured'),
              if (item['is_promoted'] == true)
                const _InlineBadge(label: 'Promoted'),
              if (item['is_active'] == false)
                const _InlineBadge(label: 'Inactive'),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Branches',
                  value: '$branchesCount',
                  icon: Icons.location_city_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Members',
                  value: '$membersCount',
                  icon: Icons.groups_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Trainers',
                  value: '$trainersCount',
                  icon: Icons.fitness_center_rounded,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              QuickActionButton(
                label: 'Approve',
                icon: Icons.check_circle_rounded,
                onPressed: onApprove,
              ),
              QuickActionButton(
                label: 'Reject',
                icon: Icons.cancel_rounded,
                onPressed: onReject,
              ),
              QuickActionButton(
                label: 'Verify',
                icon: Icons.verified_rounded,
                onPressed: onVerify,
              ),
              QuickActionButton(
                label: 'Feature',
                icon: Icons.workspace_premium_rounded,
                onPressed: onFeature,
              ),
              QuickActionButton(
                label: 'Promote',
                icon: Icons.trending_up_rounded,
                onPressed: onPromote,
              ),
              QuickActionButton(
                label: item['is_active'] == false ? 'Activate' : 'Deactivate',
                icon: Icons.power_settings_new_rounded,
                onPressed: onDeactivate,
              ),
              QuickActionButton(
                label: 'Details',
                icon: Icons.visibility_rounded,
                onPressed: onViewDetails,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PlatformGymDetailSheet extends StatelessWidget {
  const _PlatformGymDetailSheet({
    required this.gym,
    this.onApprove,
    this.onReject,
    this.onActivateOrDeactivate,
    this.onVerify,
    this.onFeature,
    this.onPromote,
    this.onEdit,
  });

  final Map<String, dynamic> gym;
  final VoidCallback? onApprove;
  final VoidCallback? onReject;
  final VoidCallback? onActivateOrDeactivate;
  final VoidCallback? onVerify;
  final VoidCallback? onFeature;
  final VoidCallback? onPromote;
  final VoidCallback? onEdit;

  @override
  Widget build(BuildContext context) {
    final owner = _recordMap(gym['owner']);
    final branches = (gym['branches'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();
    final facilities = (gym['facilities'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();
    final locationText = [
      gym['city']?.toString(),
      gym['state']?.toString(),
    ].where((value) => value != null && value.isNotEmpty).join(', ');
    final countCards = [
      _MetricConfig(
        'Branches',
        '${gym['branches_count'] ?? branches.length}',
        Icons.location_city_rounded,
      ),
      _MetricConfig(
        'Members',
        '${gym['member_profiles_count'] ?? 0}',
        Icons.groups_rounded,
      ),
      _MetricConfig(
        'Trainers',
        '${gym['trainer_profiles_count'] ?? 0}',
        Icons.fitness_center_rounded,
      ),
      _MetricConfig(
        'Plans',
        '${gym['membership_plans_count'] ?? 0}',
        Icons.workspace_premium_rounded,
      ),
    ];

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ListView(
          shrinkWrap: true,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundImage:
                      gym['logo_url']?.toString().isNotEmpty == true
                      ? NetworkImage(gym['logo_url'].toString())
                      : null,
                  child: gym['logo_url']?.toString().isNotEmpty == true
                      ? null
                      : const Icon(Icons.storefront_rounded),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        gym['name']?.toString() ?? 'Gym detail',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        locationText.isEmpty ? '--' : locationText,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
                StatusBadge(
                  label: gym['is_active'] == true ? 'Active' : 'Inactive',
                  color: gym['is_active'] == true
                      ? AppColors.success
                      : AppColors.warning,
                ),
              ],
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                StatusBadge(
                  label: gym['approval_status']?.toString() ?? 'pending',
                  color: _statusChipColor(gym['approval_status']?.toString()),
                ),
                StatusBadge(
                  label: gym['is_verified'] == true ? 'Verified' : 'Unverified',
                  color: gym['is_verified'] == true
                      ? AppColors.info
                      : AppColors.warning,
                ),
                if (gym['is_featured'] == true)
                  const StatusBadge(
                    label: 'Featured',
                    color: AppColors.accentPurple,
                  ),
                if (gym['is_promoted'] == true)
                  const StatusBadge(label: 'Promoted', color: AppColors.accent),
              ],
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: countCards
                  .map(
                    (metric) => SizedBox(
                      width: 170,
                      child: StatCard(
                        label: metric.label,
                        value: metric.value,
                        icon: metric.icon,
                      ),
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Profile',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: 'Owner',
                    value: owner['name']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Owner Email',
                    value: owner['email']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Address',
                    value: gym['address']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'City',
                    value: gym['city']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Status',
                    value: gym['status']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Description',
                    value: gym['description']?.toString().isNotEmpty == true
                        ? gym['description'].toString()
                        : '--',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Public Listing',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: 'Listing Enabled',
                    value: gym['public_listing_enabled'] == true ? 'Yes' : 'No',
                  ),
                  _InfoRow(
                    label: 'Listing Approval',
                    value:
                        gym['public_listing_approval_status']?.toString() ??
                        '--',
                  ),
                  _InfoRow(
                    label: 'Pricing Visible',
                    value:
                        gym['show_pricing'] == true ||
                            gym['pricing_visible'] == true
                        ? 'Yes'
                        : 'No',
                  ),
                  _InfoRow(
                    label: 'Trial Available',
                    value: gym['trial_available'] == true ? 'Yes' : 'No',
                  ),
                  _InfoRow(
                    label: 'Contact Visible',
                    value: gym['contact_visible'] == true ? 'Yes' : 'No',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Branches',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (branches.isEmpty)
                    const EmptyState(
                      title: 'No branches yet',
                      message: 'This gym has not added any branches yet.',
                      icon: Icons.location_city_outlined,
                    )
                  else
                    ...branches.map(
                      (branch) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: AppColors.surface.withValues(alpha: 0.5),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: AppColors.strokeStrong),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                branch['name']?.toString() ?? 'Branch',
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                              const SizedBox(height: 4),
                              Text(
                                branch['address']?.toString() ??
                                    branch['address_line']?.toString() ??
                                    branch['city']?.toString() ??
                                    '--',
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Facilities',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (facilities.isEmpty)
                    const EmptyState(
                      title: 'No facilities linked',
                      message: 'This gym does not have facility metadata yet.',
                      icon: Icons.spa_outlined,
                    )
                  else
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: facilities
                          .map(
                            (facility) => _InlineBadge(
                              label: facility['name']?.toString() ?? 'Facility',
                            ),
                          )
                          .toList(),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (onApprove != null)
                  QuickActionButton(
                    label: 'Approve',
                    icon: Icons.check_circle_rounded,
                    onPressed: onApprove,
                  ),
                if (onReject != null)
                  QuickActionButton(
                    label: 'Reject',
                    icon: Icons.cancel_rounded,
                    onPressed: onReject,
                  ),
                if (onActivateOrDeactivate != null)
                  QuickActionButton(
                    label: gym['is_active'] == true ? 'Deactivate' : 'Activate',
                    icon: Icons.power_settings_new_rounded,
                    onPressed: onActivateOrDeactivate,
                  ),
                if (onVerify != null)
                  QuickActionButton(
                    label: 'Verify',
                    icon: Icons.verified_rounded,
                    onPressed: onVerify,
                  ),
                if (onFeature != null)
                  QuickActionButton(
                    label: gym['is_featured'] == true ? 'Unfeature' : 'Feature',
                    icon: Icons.workspace_premium_rounded,
                    onPressed: onFeature,
                  ),
                if (onPromote != null)
                  QuickActionButton(
                    label: gym['is_promoted'] == true ? 'Unpromote' : 'Promote',
                    icon: Icons.trending_up_rounded,
                    onPressed: onPromote,
                  ),
                if (onEdit != null)
                  QuickActionButton(
                    label: 'Edit',
                    icon: Icons.edit_rounded,
                    onPressed: onEdit,
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PlatformUserSmartCard extends StatelessWidget {
  const _PlatformUserSmartCard({
    required this.item,
    required this.title,
    required this.subtitle,
    required this.onViewDetails,
    required this.onActivateOrDeactivate,
  });

  final Map<String, dynamic> item;
  final String title;
  final String subtitle;
  final VoidCallback onViewDetails;
  final VoidCallback onActivateOrDeactivate;

  @override
  Widget build(BuildContext context) {
    final roles = (item['roles'] as List<dynamic>? ?? const [])
        .map((entry) => entry.toString())
        .toList();
    final gyms = (item['gyms'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();
    final branches = (item['branches'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();

    return PremiumCard(
      onTap: onViewDetails,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 26,
                backgroundImage: item['avatar']?.toString().isNotEmpty == true
                    ? NetworkImage(item['avatar'].toString())
                    : null,
                child: item['avatar']?.toString().isNotEmpty == true
                    ? null
                    : const Icon(Icons.person_outline_rounded),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        StatusBadge(
                          label: item['is_active'] == true
                              ? 'Active'
                              : 'Inactive',
                          color: item['is_active'] == true
                              ? AppColors.success
                              : AppColors.warning,
                        ),
                        ...roles
                            .take(3)
                            .map(
                              (role) => _InlineBadge(
                                label: role.replaceAll('_', ' '),
                              ),
                            ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Active role',
                  value:
                      item['active_role']?.toString().replaceAll('_', ' ') ??
                      '--',
                  icon: Icons.verified_user_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Gyms',
                  value: '${gyms.length}',
                  icon: Icons.store_mall_directory_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Branches',
                  value: '${branches.length}',
                  icon: Icons.location_city_rounded,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              QuickActionButton(
                label: 'Details',
                icon: Icons.visibility_rounded,
                onPressed: onViewDetails,
              ),
              QuickActionButton(
                label: item['is_active'] == true ? 'Deactivate' : 'Activate',
                icon: Icons.power_settings_new_rounded,
                onPressed: onActivateOrDeactivate,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PlatformGymOwnerSmartCard extends StatelessWidget {
  const _PlatformGymOwnerSmartCard({
    required this.item,
    required this.title,
    required this.subtitle,
    required this.onViewDetails,
    required this.onActivateOrDeactivate,
  });

  final Map<String, dynamic> item;
  final String title;
  final String subtitle;
  final VoidCallback onViewDetails;
  final VoidCallback onActivateOrDeactivate;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      onTap: onViewDetails,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 26,
                child: Text(
                  title.isNotEmpty ? title[0].toUpperCase() : 'G',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        StatusBadge(
                          label: item['is_active'] == true
                              ? 'Active'
                              : 'Inactive',
                          color: item['is_active'] == true
                              ? AppColors.success
                              : AppColors.warning,
                        ),
                        _InlineBadge(
                          label: '${item['owned_gyms_count'] ?? 0} gyms',
                        ),
                        _InlineBadge(
                          label:
                              '${item['active_owned_gyms_count'] ?? 0} active',
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              QuickActionButton(
                label: 'Details',
                icon: Icons.visibility_rounded,
                onPressed: onViewDetails,
              ),
              QuickActionButton(
                label: item['is_active'] == true ? 'Deactivate' : 'Activate',
                icon: Icons.power_settings_new_rounded,
                onPressed: onActivateOrDeactivate,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PlatformUserDetailSheet extends StatelessWidget {
  const _PlatformUserDetailSheet({
    required this.user,
    this.onActivateOrDeactivate,
  });

  final Map<String, dynamic> user;
  final VoidCallback? onActivateOrDeactivate;

  @override
  Widget build(BuildContext context) {
    final roles = (user['roles'] as List<dynamic>? ?? const [])
        .map((entry) => entry.toString())
        .toList();
    final permissions = (user['permissions'] as List<dynamic>? ?? const [])
        .map((entry) => entry.toString())
        .toList();
    final gyms = (user['gyms'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();
    final branches = (user['branches'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();
    final activityLogs = (user['activity_logs'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ListView(
          shrinkWrap: true,
          children: [
            Text(
              user['name']?.toString() ?? 'User detail',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 6),
            Text(
              user['email']?.toString() ?? '--',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                StatusBadge(
                  label: user['is_active'] == true ? 'Active' : 'Inactive',
                  color: user['is_active'] == true
                      ? AppColors.success
                      : AppColors.warning,
                ),
                ...roles.map(
                  (role) => _InlineBadge(label: role.replaceAll('_', ' ')),
                ),
              ],
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Profile',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: 'Phone',
                    value: user['phone']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Auth Provider',
                    value: user['auth_provider']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Active Role',
                    value:
                        user['active_role']?.toString().replaceAll('_', ' ') ??
                        '--',
                  ),
                  _InfoRow(
                    label: 'Last Login',
                    value: _formatDateTime(user['last_login_at']),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Assignments',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: 'Gyms',
                    value: gyms.isEmpty
                        ? '--'
                        : gyms.map((gym) => gym['name']).join(', '),
                  ),
                  _InfoRow(
                    label: 'Branches',
                    value: branches.isEmpty
                        ? '--'
                        : branches.map((branch) => branch['name']).join(', '),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Permissions',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (permissions.isEmpty)
                    const EmptyState(
                      title: 'No direct permissions',
                      message:
                          'This user currently inherits access through roles.',
                      icon: Icons.lock_outline_rounded,
                    )
                  else
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: permissions
                          .take(20)
                          .map((permission) => _InlineBadge(label: permission))
                          .toList(),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (activityLogs.isNotEmpty)
              PremiumCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Recent Activity',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 12),
                    ...activityLogs
                        .take(6)
                        .map(
                          (log) => Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: _InfoRow(
                              label: log['event']?.toString() ?? 'Activity',
                              value: _formatDateTime(log['occurred_at']),
                            ),
                          ),
                        ),
                  ],
                ),
              ),
            if (onActivateOrDeactivate != null) ...[
              const SizedBox(height: 16),
              QuickActionButton(
                label: user['is_active'] == true ? 'Deactivate' : 'Activate',
                icon: Icons.power_settings_new_rounded,
                onPressed: onActivateOrDeactivate,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _PlatformGymOwnerDetailSheet extends StatelessWidget {
  const _PlatformGymOwnerDetailSheet({
    required this.owner,
    this.onActivateOrDeactivate,
  });

  final Map<String, dynamic> owner;
  final VoidCallback? onActivateOrDeactivate;

  @override
  Widget build(BuildContext context) {
    final ownedGyms = (owner['owned_gyms'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ListView(
          shrinkWrap: true,
          children: [
            Text(
              owner['name']?.toString() ?? 'Gym owner detail',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 6),
            Text(
              owner['email']?.toString() ?? '--',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                StatusBadge(
                  label: owner['is_active'] == true ? 'Active' : 'Inactive',
                  color: owner['is_active'] == true
                      ? AppColors.success
                      : AppColors.warning,
                ),
                _InlineBadge(label: '${owner['owned_gyms_count'] ?? 0} gyms'),
                _InlineBadge(
                  label: '${owner['active_owned_gyms_count'] ?? 0} active gyms',
                ),
              ],
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Owner Profile',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: 'Phone',
                    value: owner['phone']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Active Role',
                    value:
                        owner['active_role']?.toString().replaceAll('_', ' ') ??
                        '--',
                  ),
                  if (owner['temporary_password'] != null)
                    _InfoRow(
                      label: 'Temporary Password',
                      value: owner['temporary_password']?.toString() ?? '--',
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Owned Gyms',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (ownedGyms.isEmpty)
                    const EmptyState(
                      title: 'No gyms assigned',
                      message: 'This owner does not currently own any gyms.',
                      icon: Icons.store_mall_directory_outlined,
                    )
                  else
                    ...ownedGyms.map(
                      (gym) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: AppColors.surface.withValues(alpha: 0.5),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: AppColors.strokeStrong),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                gym['name']?.toString() ?? 'Gym',
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                              const SizedBox(height: 4),
                              Text(
                                '${gym['city'] ?? '--'} • ${gym['branches_count'] ?? 0} branches • ${gym['member_profiles_count'] ?? 0} members',
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            if (onActivateOrDeactivate != null) ...[
              const SizedBox(height: 16),
              QuickActionButton(
                label: owner['is_active'] == true ? 'Deactivate' : 'Activate',
                icon: Icons.power_settings_new_rounded,
                onPressed: onActivateOrDeactivate,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _PlatformFacilitySmartCard extends StatelessWidget {
  const _PlatformFacilitySmartCard({
    required this.item,
    required this.title,
    required this.subtitle,
    required this.onViewDetails,
    required this.onToggleStatus,
    required this.onDelete,
    required this.onEdit,
  });

  final Map<String, dynamic> item;
  final String title;
  final String subtitle;
  final VoidCallback onViewDetails;
  final VoidCallback onToggleStatus;
  final VoidCallback onDelete;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final usageCount = (item['usage_count'] as num?)?.toInt() ?? 0;
    final gymsCount = (item['gyms_count'] as num?)?.toInt() ?? 0;
    final branchesCount = (item['branches_count'] as num?)?.toInt() ?? 0;

    return PremiumCard(
      onTap: onViewDetails,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 26,
                child: Icon(
                  Icons.spa_rounded,
                  color: Theme.of(context).colorScheme.secondary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      item['slug']?.toString() ?? subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        StatusBadge(
                          label: item['is_active'] == true
                              ? 'Active'
                              : 'Inactive',
                          color: item['is_active'] == true
                              ? AppColors.success
                              : AppColors.warning,
                        ),
                        _InlineBadge(label: '$usageCount usages'),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Gyms',
                  value: '$gymsCount',
                  icon: Icons.store_mall_directory_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Branches',
                  value: '$branchesCount',
                  icon: Icons.location_city_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Icon',
                  value: item['icon']?.toString().isNotEmpty == true
                      ? item['icon'].toString()
                      : '--',
                  icon: Icons.tag_rounded,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              QuickActionButton(
                label: 'Details',
                icon: Icons.visibility_rounded,
                onPressed: onViewDetails,
              ),
              QuickActionButton(
                label: 'Edit',
                icon: Icons.edit_rounded,
                onPressed: onEdit,
              ),
              QuickActionButton(
                label: item['is_active'] == true ? 'Deactivate' : 'Activate',
                icon: Icons.power_settings_new_rounded,
                onPressed: onToggleStatus,
              ),
              QuickActionButton(
                label: 'Delete',
                icon: Icons.delete_outline_rounded,
                onPressed: onDelete,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PlatformFacilityDetailSheet extends StatelessWidget {
  const _PlatformFacilityDetailSheet({
    required this.facility,
    this.onEdit,
    this.onToggleStatus,
    this.onDelete,
  });

  final Map<String, dynamic> facility;
  final VoidCallback? onEdit;
  final VoidCallback? onToggleStatus;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ListView(
          shrinkWrap: true,
          children: [
            Text(
              facility['name']?.toString() ?? 'Facility detail',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                StatusBadge(
                  label: facility['is_active'] == true ? 'Active' : 'Inactive',
                  color: facility['is_active'] == true
                      ? AppColors.success
                      : AppColors.warning,
                ),
                _InlineBadge(label: '${facility['usage_count'] ?? 0} usages'),
              ],
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Facility Profile',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: 'Slug',
                    value: facility['slug']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Icon',
                    value: facility['icon']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Gyms',
                    value: '${facility['gyms_count'] ?? 0}',
                  ),
                  _InfoRow(
                    label: 'Branches',
                    value: '${facility['branches_count'] ?? 0}',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (onEdit != null)
                  QuickActionButton(
                    label: 'Edit',
                    icon: Icons.edit_rounded,
                    onPressed: onEdit,
                  ),
                if (onToggleStatus != null)
                  QuickActionButton(
                    label: facility['is_active'] == true
                        ? 'Deactivate'
                        : 'Activate',
                    icon: Icons.power_settings_new_rounded,
                    onPressed: onToggleStatus,
                  ),
                if (onDelete != null)
                  QuickActionButton(
                    label: 'Delete',
                    icon: Icons.delete_outline_rounded,
                    onPressed: onDelete,
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _GymProfileWorkspace extends StatefulWidget {
  const _GymProfileWorkspace({required this.repository, required this.onEdit});

  final AdminRepository repository;
  final ValueChanged<Map<String, dynamic>> onEdit;

  @override
  State<_GymProfileWorkspace> createState() => _GymProfileWorkspaceState();
}

class _GymProfileWorkspaceState extends State<_GymProfileWorkspace> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _profile = const {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _profile = await widget.repository.fetchGymProfile();
    } catch (exception) {
      _error = exception.toString();
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AsyncStateView(
      isLoading: _loading,
      error: _error,
      onRetry: _load,
      loadingChild: const LoadingState(label: 'Loading gym profile...'),
      child: ListView(
        children: [
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_profile['cover_image_url']?.toString().isNotEmpty == true)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(24),
                    child: AspectRatio(
                      aspectRatio: 16 / 7,
                      child: Image.network(
                        _profile['cover_image_url'].toString(),
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => Container(
                          color: AppColors.surfaceSoft,
                          child: const Icon(Icons.image_outlined, size: 40),
                        ),
                      ),
                    ),
                  ),
                if (_profile['cover_image_url']?.toString().isNotEmpty == true)
                  const SizedBox(height: 16),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CircleAvatar(
                      radius: 32,
                      backgroundImage:
                          _profile['logo_url']?.toString().isNotEmpty == true
                          ? NetworkImage(_profile['logo_url'].toString())
                          : null,
                      child: _profile['logo_url']?.toString().isNotEmpty == true
                          ? null
                          : const Icon(Icons.storefront_rounded),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _profile['name']?.toString() ?? 'Gym profile',
                            style: Theme.of(context).textTheme.headlineSmall,
                          ),
                          const SizedBox(height: 6),
                          Text(
                            [
                                  _profile['city']?.toString(),
                                  _profile['state']?.toString(),
                                  _profile['pincode']?.toString(),
                                ]
                                .where(
                                  (item) => item != null && item.isNotEmpty,
                                )
                                .join(' • '),
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                          const SizedBox(height: 10),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              _InlineBadge(
                                label:
                                    _profile['timezone']?.toString() ??
                                    'Timezone pending',
                              ),
                              if (_profile['opening_time']
                                          ?.toString()
                                          .isNotEmpty ==
                                      true ||
                                  _profile['closing_time']
                                          ?.toString()
                                          .isNotEmpty ==
                                      true)
                                _InlineBadge(
                                  label:
                                      '${_profile['opening_time'] ?? '--'} - ${_profile['closing_time'] ?? '--'}',
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    AppPrimaryButton(
                      label: 'Edit',
                      icon: Icons.edit_rounded,
                      onPressed: () => widget.onEdit(_profile),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Text(
                  _profile['description']?.toString().isNotEmpty == true
                      ? _profile['description'].toString()
                      : 'Add a description to strengthen the public and internal gym profile.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 16,
            runSpacing: 16,
            children: [
              SizedBox(
                width: 360,
                child: PremiumCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Location',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 12),
                      _InfoRow(
                        label: 'Address',
                        value: _profile['address']?.toString() ?? '--',
                      ),
                      _InfoRow(
                        label: 'City',
                        value: _profile['city']?.toString() ?? '--',
                      ),
                      _InfoRow(
                        label: 'State',
                        value: _profile['state']?.toString() ?? '--',
                      ),
                      _InfoRow(
                        label: 'Country',
                        value: _profile['country']?.toString() ?? '--',
                      ),
                    ],
                  ),
                ),
              ),
              SizedBox(
                width: 360,
                child: PremiumCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Facilities',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 12),
                      if ((_profile['facilities'] as List<dynamic>? ?? const [])
                          .isEmpty)
                        const EmptyState(
                          title: 'No facilities mapped',
                          message:
                              'Select facilities so the gym and its branches show stronger capability cues.',
                          icon: Icons.spa_outlined,
                        )
                      else
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children:
                              (_profile['facilities'] as List<dynamic>? ??
                                      const [])
                                  .map(
                                    (entry) => _InlineBadge(
                                      label:
                                          _recordMap(
                                            entry,
                                          )['name']?.toString() ??
                                          'Facility',
                                    ),
                                  )
                                  .toList(),
                        ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GymPublicListingWorkspace extends StatefulWidget {
  const _GymPublicListingWorkspace({
    required this.repository,
    required this.onEdit,
  });

  final AdminRepository repository;
  final ValueChanged<Map<String, dynamic>> onEdit;

  @override
  State<_GymPublicListingWorkspace> createState() =>
      _GymPublicListingWorkspaceState();
}

class _GymPublicListingWorkspaceState
    extends State<_GymPublicListingWorkspace> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _settings = const {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _settings = await widget.repository.fetchGymPublicListingSettings();
    } catch (exception) {
      _error = exception.toString();
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Widget _settingCard(
    BuildContext context,
    String title,
    String subtitle,
    bool value,
    IconData icon,
  ) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: (value ? AppColors.success : AppColors.surfaceSoft)
                  .withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              icon,
              color: value ? AppColors.success : AppColors.textSecondary,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 4),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
          StatusBadge(
            label: value ? 'Enabled' : 'Hidden',
            color: value ? AppColors.success : AppColors.warning,
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AsyncStateView(
      isLoading: _loading,
      error: _error,
      onRetry: _load,
      loadingChild: const LoadingState(
        label: 'Loading public listing settings...',
      ),
      child: ListView(
        children: [
          PremiumCard(
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Public Listing Controls',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Control public discovery, pricing visibility, trial CTA exposure, and contact availability from one clean surface.',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
                AppPrimaryButton(
                  label: 'Edit',
                  icon: Icons.tune_rounded,
                  onPressed: () => widget.onEdit(_settings),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _settingCard(
            context,
            'Public listing enabled',
            'Whether this gym is allowed to appear in the public discovery layer.',
            _settings['public_listing_enabled'] == true,
            Icons.public_rounded,
          ),
          const SizedBox(height: 12),
          _settingCard(
            context,
            'Show pricing',
            'Whether membership pricing can be displayed on discovery and detail pages.',
            _settings['show_pricing'] == true ||
                _settings['pricing_visible'] == true,
            Icons.sell_rounded,
          ),
          const SizedBox(height: 12),
          _settingCard(
            context,
            'Trial available',
            'Whether visitors can request trials from public pages.',
            _settings['trial_available'] == true,
            Icons.flag_rounded,
          ),
          const SizedBox(height: 12),
          _settingCard(
            context,
            'Contact visible',
            'Whether public contact visibility is exposed alongside listing CTAs.',
            _settings['contact_visible'] == true,
            Icons.call_rounded,
          ),
        ],
      ),
    );
  }
}

class _GymBranchSmartCard extends StatelessWidget {
  const _GymBranchSmartCard({
    required this.item,
    required this.title,
    required this.subtitle,
    required this.onViewDetails,
    required this.onToggleStatus,
    required this.onDelete,
    required this.onEdit,
  });

  final Map<String, dynamic> item;
  final String title;
  final String subtitle;
  final VoidCallback onViewDetails;
  final VoidCallback onToggleStatus;
  final VoidCallback onDelete;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final facilities = (item['facilities'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry)['name']?.toString() ?? 'Facility')
        .where((entry) => entry.isNotEmpty)
        .take(4)
        .toList();

    return PremiumCard(
      onTap: onViewDetails,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      item['address']?.toString().isNotEmpty == true
                          ? item['address'].toString()
                          : subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: item['status']?.toString() ?? 'active',
                color: item['is_active'] == false
                    ? AppColors.warning
                    : AppColors.success,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Members',
                  value: '${item['member_profiles_count'] ?? 0}',
                  icon: Icons.groups_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Trainers',
                  value: '${item['trainer_profiles_count'] ?? 0}',
                  icon: Icons.fitness_center_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Check-ins',
                  value: '${item['today_check_ins_count'] ?? 0}',
                  icon: Icons.qr_code_scanner_rounded,
                ),
              ),
            ],
          ),
          if (facilities.isNotEmpty) ...[
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: facilities
                  .map((label) => _InlineBadge(label: label))
                  .toList(),
            ),
          ],
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              QuickActionButton(
                label: 'Details',
                icon: Icons.visibility_rounded,
                onPressed: onViewDetails,
              ),
              QuickActionButton(
                label: 'Edit',
                icon: Icons.edit_rounded,
                onPressed: onEdit,
              ),
              QuickActionButton(
                label: item['is_active'] == false ? 'Activate' : 'Deactivate',
                icon: Icons.power_settings_new_rounded,
                onPressed: onToggleStatus,
              ),
              QuickActionButton(
                label: 'Delete',
                icon: Icons.delete_outline_rounded,
                onPressed: onDelete,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GymBranchDetailSheet extends StatelessWidget {
  const _GymBranchDetailSheet({
    required this.branch,
    this.onEdit,
    this.onToggleStatus,
    this.onDelete,
  });

  final Map<String, dynamic> branch;
  final VoidCallback? onEdit;
  final VoidCallback? onToggleStatus;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final facilities = (branch['facilities'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ListView(
          shrinkWrap: true,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        branch['name']?.toString() ?? 'Branch',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        branch['address']?.toString() ?? '--',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
                StatusBadge(
                  label: branch['status']?.toString() ?? 'active',
                  color: branch['is_active'] == false
                      ? AppColors.warning
                      : AppColors.success,
                ),
              ],
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                SizedBox(
                  width: 170,
                  child: StatCard(
                    label: 'Members',
                    value: '${branch['member_profiles_count'] ?? 0}',
                    icon: Icons.groups_rounded,
                  ),
                ),
                SizedBox(
                  width: 170,
                  child: StatCard(
                    label: 'Trainers',
                    value: '${branch['trainer_profiles_count'] ?? 0}',
                    icon: Icons.fitness_center_rounded,
                  ),
                ),
                SizedBox(
                  width: 170,
                  child: StatCard(
                    label: 'Today Check-ins',
                    value: '${branch['today_check_ins_count'] ?? 0}',
                    icon: Icons.qr_code_scanner_rounded,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Branch Details',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: 'City',
                    value: branch['city']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'State',
                    value: branch['state']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Pincode',
                    value: branch['pincode']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Timezone',
                    value: branch['timezone']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Timings',
                    value:
                        '${branch['opening_time'] ?? '--'} - ${branch['closing_time'] ?? '--'}',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Facilities',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (facilities.isEmpty)
                    const EmptyState(
                      title: 'No facilities mapped',
                      message:
                          'Attach facilities to this branch for stronger discovery and ops visibility.',
                      icon: Icons.spa_outlined,
                    )
                  else
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: facilities
                          .map(
                            (facility) => _InlineBadge(
                              label: facility['name']?.toString() ?? 'Facility',
                            ),
                          )
                          .toList(),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (onEdit != null)
                  QuickActionButton(
                    label: 'Edit',
                    icon: Icons.edit_rounded,
                    onPressed: onEdit,
                  ),
                if (onToggleStatus != null)
                  QuickActionButton(
                    label: branch['is_active'] == false
                        ? 'Activate'
                        : 'Deactivate',
                    icon: Icons.power_settings_new_rounded,
                    onPressed: onToggleStatus,
                  ),
                if (onDelete != null)
                  QuickActionButton(
                    label: 'Delete',
                    icon: Icons.delete_outline_rounded,
                    onPressed: onDelete,
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _GymStaffSmartCard extends StatelessWidget {
  const _GymStaffSmartCard({
    required this.item,
    required this.title,
    required this.subtitle,
    required this.onViewDetails,
    required this.onActivateOrDeactivate,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> item;
  final String title;
  final String subtitle;
  final VoidCallback onViewDetails;
  final VoidCallback onActivateOrDeactivate;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final roles = (item['roles'] as List<dynamic>? ?? const [])
        .map((entry) => entry.toString())
        .toList();
    final branches = (item['branches'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry)['name']?.toString() ?? 'Branch')
        .where((entry) => entry.isNotEmpty)
        .toList();
    final assignments =
        (item['staff_assignments'] as List<dynamic>? ?? const [])
            .map((entry) => _recordMap(entry))
            .toList();
    final customPermissions = assignments
        .expand(
          (assignment) =>
              (assignment['custom_permissions'] as List<dynamic>? ?? const [])
                  .map((permission) => permission.toString()),
        )
        .toSet()
        .toList();

    return PremiumCard(
      onTap: onViewDetails,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 24,
                backgroundImage: item['avatar']?.toString().isNotEmpty == true
                    ? NetworkImage(item['avatar'].toString())
                    : null,
                child: item['avatar']?.toString().isNotEmpty == true
                    ? null
                    : const Icon(Icons.badge_rounded),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      item['email']?.toString().isNotEmpty == true
                          ? item['email'].toString()
                          : subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: item['is_active'] == true ? 'Active' : 'Inactive',
                color: item['is_active'] == true
                    ? AppColors.success
                    : AppColors.warning,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              ...roles.map(
                (role) => _InlineBadge(label: role.replaceAll('_', ' ')),
              ),
              ...branches.take(2).map((branch) => _InlineBadge(label: branch)),
              if (customPermissions.isNotEmpty)
                _InlineBadge(label: '${customPermissions.length} permissions'),
            ],
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              QuickActionButton(
                label: 'Details',
                icon: Icons.visibility_rounded,
                onPressed: onViewDetails,
              ),
              QuickActionButton(
                label: 'Edit',
                icon: Icons.edit_rounded,
                onPressed: onEdit,
              ),
              QuickActionButton(
                label: item['is_active'] == true ? 'Deactivate' : 'Activate',
                icon: Icons.power_settings_new_rounded,
                onPressed: onActivateOrDeactivate,
              ),
              QuickActionButton(
                label: 'Delete',
                icon: Icons.delete_outline_rounded,
                onPressed: onDelete,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GymStaffDetailSheet extends StatelessWidget {
  const _GymStaffDetailSheet({
    required this.user,
    this.onEdit,
    this.onActivateOrDeactivate,
    this.onDelete,
  });

  final Map<String, dynamic> user;
  final VoidCallback? onEdit;
  final VoidCallback? onActivateOrDeactivate;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final roles = (user['roles'] as List<dynamic>? ?? const [])
        .map((entry) => entry.toString())
        .toList();
    final branches = (user['branches'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();
    final assignments =
        (user['staff_assignments'] as List<dynamic>? ?? const [])
            .map((entry) => _recordMap(entry))
            .toList();
    final permissions = assignments
        .expand(
          (assignment) =>
              (assignment['custom_permissions'] as List<dynamic>? ?? const [])
                  .map((permission) => permission.toString()),
        )
        .toSet()
        .toList();

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ListView(
          shrinkWrap: true,
          children: [
            Text(
              user['name']?.toString() ?? 'Staff detail',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 6),
            Text(
              user['email']?.toString() ?? '--',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                StatusBadge(
                  label: user['is_active'] == true ? 'Active' : 'Inactive',
                  color: user['is_active'] == true
                      ? AppColors.success
                      : AppColors.warning,
                ),
                ...roles.map(
                  (role) => _InlineBadge(label: role.replaceAll('_', ' ')),
                ),
                ...branches.map(
                  (branch) => _InlineBadge(
                    label: branch['name']?.toString() ?? 'Branch',
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Profile',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _InfoRow(
                    label: 'Phone',
                    value: user['phone']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Role',
                    value: roles.isEmpty
                        ? '--'
                        : roles
                              .map((role) => role.replaceAll('_', ' '))
                              .join(', '),
                  ),
                  _InfoRow(
                    label: 'Branches',
                    value: branches.isEmpty
                        ? '--'
                        : branches.map((branch) => branch['name']).join(', '),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Granted Permissions',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (permissions.isEmpty)
                    const EmptyState(
                      title: 'No custom permissions',
                      message:
                          'This staff member currently relies on role-level access only.',
                      icon: Icons.lock_outline_rounded,
                    )
                  else
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: permissions
                          .map(
                            (permission) => _InlineBadge(
                              label: permission.replaceAll('_', ' '),
                            ),
                          )
                          .toList(),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (onEdit != null)
                  QuickActionButton(
                    label: 'Edit',
                    icon: Icons.edit_rounded,
                    onPressed: onEdit,
                  ),
                if (onActivateOrDeactivate != null)
                  QuickActionButton(
                    label: user['is_active'] == true
                        ? 'Deactivate'
                        : 'Activate',
                    icon: Icons.power_settings_new_rounded,
                    onPressed: onActivateOrDeactivate,
                  ),
                if (onDelete != null)
                  QuickActionButton(
                    label: 'Delete',
                    icon: Icons.delete_outline_rounded,
                    onPressed: onDelete,
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PlatformReportsWorkspace extends StatefulWidget {
  const _PlatformReportsWorkspace({required this.repository});

  final AdminRepository repository;

  @override
  State<_PlatformReportsWorkspace> createState() =>
      _PlatformReportsWorkspaceState();
}

class _PlatformReportsWorkspaceState extends State<_PlatformReportsWorkspace> {
  String _reportKey = 'overview';
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _report = const {};
  List<Map<String, dynamic>> _gyms = const [];
  final TextEditingController _startDateController = TextEditingController();
  final TextEditingController _endDateController = TextEditingController();
  final TextEditingController _cityController = TextEditingController();
  String? _status;
  int? _gymId;

  @override
  void initState() {
    super.initState();
    _loadGyms();
    _loadReport();
  }

  @override
  void dispose() {
    _startDateController.dispose();
    _endDateController.dispose();
    _cityController.dispose();
    super.dispose();
  }

  Future<void> _loadGyms() async {
    try {
      final gyms = await widget.repository.fetchPlatformGyms();
      if (!mounted) {
        return;
      }
      setState(() => _gyms = gyms);
    } catch (_) {
      // Keep report surface usable even if gym filter options fail.
    }
  }

  Future<void> _loadReport() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final report = await widget.repository.fetchPlatformReport(
        _reportKey,
        queryParameters: {
          if (_startDateController.text.trim().isNotEmpty)
            'start_date': _startDateController.text.trim(),
          if (_endDateController.text.trim().isNotEmpty)
            'end_date': _endDateController.text.trim(),
          if (_cityController.text.trim().isNotEmpty)
            'city': _cityController.text.trim(),
          if (_status != null && _status!.isNotEmpty) 'status': _status,
          if (_gymId != null) 'gym': _gymId,
        },
      );
      if (!mounted) {
        return;
      }
      setState(() => _report = report);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = exception.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final reportOptions = _recordMap(_report['report_options']);
    final summaryCards =
        (_report['summary_cards'] as List<dynamic>? ?? const [])
            .map((entry) => _recordMap(entry))
            .toList();
    final chartCards = (_report['chart_cards'] as List<dynamic>? ?? const [])
        .map((entry) => _recordMap(entry))
        .toList();
    final rows = (_report['rows'] as List<dynamic>? ?? const [])
        .map(
          (entry) =>
              (entry as List<dynamic>).map((cell) => cell.toString()).toList(),
        )
        .toList();
    final columns = (_report['columns'] as List<dynamic>? ?? const [])
        .map((entry) => entry.toString())
        .toList();
    final emptyState = _recordMap(_report['empty_state']);

    return RefreshIndicator(
      onRefresh: _loadReport,
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            _report['report_title']?.toString() ?? 'Platform Reports',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          Text(
            _report['report_description']?.toString() ??
                'Platform-wide reporting for growth, users, payments, attendance, custom fee usage, and trials.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Report Type',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: reportOptions.entries.map((entry) {
                    return FilterChip(
                      selected: _reportKey == entry.key,
                      label: Text(entry.value.toString()),
                      onSelected: (_) {
                        setState(() => _reportKey = entry.key);
                        _loadReport();
                      },
                    );
                  }).toList(),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _startDateController,
                        decoration: const InputDecoration(
                          labelText: 'Start date (YYYY-MM-DD)',
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: _endDateController,
                        decoration: const InputDecoration(
                          labelText: 'End date (YYYY-MM-DD)',
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _cityController,
                        decoration: const InputDecoration(labelText: 'City'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String?>(
                        initialValue: _status,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: const [
                          DropdownMenuItem<String?>(
                            value: null,
                            child: Text('All statuses'),
                          ),
                          DropdownMenuItem(
                            value: 'active',
                            child: Text('Active'),
                          ),
                          DropdownMenuItem(
                            value: 'inactive',
                            child: Text('Inactive'),
                          ),
                          DropdownMenuItem(
                            value: 'pending',
                            child: Text('Pending'),
                          ),
                          DropdownMenuItem(
                            value: 'approved',
                            child: Text('Approved'),
                          ),
                          DropdownMenuItem(
                            value: 'rejected',
                            child: Text('Rejected'),
                          ),
                          DropdownMenuItem(value: 'paid', child: Text('Paid')),
                          DropdownMenuItem(
                            value: 'overdue',
                            child: Text('Overdue'),
                          ),
                          DropdownMenuItem(
                            value: 'converted',
                            child: Text('Converted'),
                          ),
                        ],
                        onChanged: (value) => setState(() => _status = value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int?>(
                  initialValue: _gymId,
                  decoration: const InputDecoration(labelText: 'Gym'),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('All gyms'),
                    ),
                    ..._gyms.map(
                      (gym) => DropdownMenuItem<int?>(
                        value: (gym['id'] as num?)?.toInt(),
                        child: Text(gym['name']?.toString() ?? 'Gym'),
                      ),
                    ),
                  ],
                  onChanged: (value) => setState(() => _gymId = value),
                ),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    GradientButton(
                      label: 'Apply Filters',
                      icon: Icons.filter_alt_rounded,
                      onPressed: _loadReport,
                    ),
                    OutlinedButton.icon(
                      onPressed: () {
                        _startDateController.clear();
                        _endDateController.clear();
                        _cityController.clear();
                        setState(() {
                          _status = null;
                          _gymId = null;
                        });
                        _loadReport();
                      },
                      icon: const Icon(Icons.restart_alt_rounded),
                      label: const Text('Reset'),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (_loading)
            const LoadingState(label: 'Loading reports...')
          else if (_error != null)
            ErrorState(message: _error!, onRetry: _loadReport)
          else ...[
            if (summaryCards.isNotEmpty)
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: summaryCards
                    .map(
                      (card) => SizedBox(
                        width: 220,
                        child: StatCard(
                          label: card['label']?.toString() ?? 'Metric',
                          value: card['value']?.toString() ?? '--',
                          caption: card['hint']?.toString(),
                          icon: Icons.auto_graph_rounded,
                        ),
                      ),
                    )
                    .toList(),
              ),
            if (chartCards.isNotEmpty) ...[
              const SizedBox(height: 16),
              PremiumCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Trend Cards',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: chartCards
                          .map(
                            (card) => SizedBox(
                              width: 220,
                              child: StatCard(
                                label: card['label']?.toString() ?? 'Trend',
                                value: card['value']?.toString() ?? '--',
                                caption: card['hint']?.toString(),
                                icon: Icons.trending_up_rounded,
                                color: AppColors.accentPurple,
                              ),
                            ),
                          )
                          .toList(),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    columns.isNotEmpty ? 'Report Table' : 'Report Output',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (rows.isEmpty)
                    EmptyState(
                      title:
                          emptyState['title']?.toString() ?? 'No report data',
                      message:
                          emptyState['message']?.toString() ??
                          'The selected filters returned no rows.',
                      icon: Icons.inbox_rounded,
                    )
                  else
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: DataTable(
                        columns: columns
                            .map((column) => DataColumn(label: Text(column)))
                            .toList(),
                        rows: rows
                            .map(
                              (row) => DataRow(
                                cells: row
                                    .map((cell) => DataCell(Text(cell)))
                                    .toList(),
                              ),
                            )
                            .toList(),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _PaymentDueSmartCard extends StatelessWidget {
  const _PaymentDueSmartCard({
    required this.item,
    required this.title,
    required this.payableAmount,
    required this.paidAmount,
    required this.dueAmount,
    required this.overdue,
    required this.onCollectPayment,
    this.onOpenProfile,
  });

  final Map<String, dynamic> item;
  final String title;
  final double payableAmount;
  final double paidAmount;
  final double dueAmount;
  final bool overdue;
  final VoidCallback onCollectPayment;
  final VoidCallback? onOpenProfile;

  @override
  Widget build(BuildContext context) {
    final plan = Map<String, dynamic>.from(
      item['membership_plan'] as Map? ?? item['plan'] as Map? ?? const {},
    );

    return PremiumCard(
      onTap: onOpenProfile,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(
                      plan['name']?.toString() ?? 'Membership plan',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              if (overdue)
                const StatusBadge(
                  label: 'Overdue',
                  color: AppColors.error,
                  icon: Icons.warning_amber_rounded,
                ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  label: 'Payable',
                  value: _formatCurrency(payableAmount),
                  icon: Icons.receipt_long_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Paid',
                  value: _formatCurrency(paidAmount),
                  icon: Icons.check_circle_outline_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniMetric(
                  label: 'Due',
                  value: _formatCurrency(dueAmount),
                  icon: Icons.payments_outlined,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Due date: ${item['due_date']?.toString() ?? '--'}',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
              QuickActionButton(
                label: 'Collect Payment',
                icon: Icons.currency_rupee_rounded,
                onPressed: onCollectPayment,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GymMembershipDetailSheet extends StatelessWidget {
  const _GymMembershipDetailSheet({
    required this.membership,
    this.onRenew,
    this.onFreeze,
    this.onExtend,
    this.onCancel,
  });

  final Map<String, dynamic> membership;
  final VoidCallback? onRenew;
  final VoidCallback? onFreeze;
  final VoidCallback? onExtend;
  final VoidCallback? onCancel;

  @override
  Widget build(BuildContext context) {
    final member = _recordMap(membership['member']);
    final plan = _recordMap(membership['membership_plan']);
    final status = membership['status']?.toString() ?? 'active';
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ListView(
          shrinkWrap: true,
          children: [
            Text(
              member['name']?.toString() ?? 'Membership detail',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 8),
            Text(
              plan['name']?.toString() ?? 'Membership plan',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _InfoRow(label: 'Status', value: status.replaceAll('_', ' ')),
                  _InfoRow(
                    label: 'Start date',
                    value: membership['start_date']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Expiry date',
                    value: membership['expiry_date']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Payment status',
                    value: membership['payment_status']?.toString() ?? '--',
                  ),
                  _InfoRow(
                    label: 'Final payable',
                    value: _formatCurrency(membership['final_payable_amount']),
                  ),
                  _InfoRow(
                    label: 'Amount paid',
                    value: _formatCurrency(membership['amount_paid']),
                  ),
                  _InfoRow(
                    label: 'Due amount',
                    value: _formatCurrency(membership['due_amount']),
                  ),
                  _InfoRow(
                    label: 'Due date',
                    value: membership['due_date']?.toString() ?? '--',
                  ),
                  if (membership['custom_fee_enabled'] == true)
                    _InfoRow(label: 'Custom fee', value: 'Applied'),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (onRenew != null)
                  QuickActionButton(
                    label: 'Renew',
                    icon: Icons.autorenew_rounded,
                    onPressed: onRenew,
                  ),
                if (onFreeze != null)
                  QuickActionButton(
                    label: 'Freeze',
                    icon: Icons.ac_unit_rounded,
                    onPressed: onFreeze,
                  ),
                if (onExtend != null)
                  QuickActionButton(
                    label: 'Extend',
                    icon: Icons.add_circle_outline_rounded,
                    onPressed: onExtend,
                  ),
                if (onCancel != null)
                  QuickActionButton(
                    label: 'Cancel',
                    icon: Icons.cancel_rounded,
                    onPressed: onCancel,
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberDetailSheet extends StatelessWidget {
  const _MemberDetailSheet({
    required this.appUser,
    required this.detail,
    required this.repository,
    required this.onOpenForm,
  });

  final AppUser appUser;
  final Map<String, dynamic> detail;
  final AdminRepository repository;
  final Future<void> Function(
    _AdminFormType? type, {
    Map<String, dynamic>? prefill,
  })
  onOpenForm;

  bool _canAny(List<String> permissions) {
    return _hasAnyAdminPermission(appUser, permissions);
  }

  @override
  Widget build(BuildContext context) {
    final member = Map<String, dynamic>.from(
      detail['member'] as Map? ?? detail,
    );
    final profile = Map<String, dynamic>.from(
      detail['member_profile'] as Map? ??
          member['member_profile'] as Map? ??
          const {},
    );
    final engagement = Map<String, dynamic>.from(
      (member['engagement_score'] as Map?) ??
          (profile['engagement_score'] as Map?) ??
          const {},
    );
    final memberTimeline =
        (detail['member_timeline'] as List<dynamic>? ?? const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
    final activityTimeline =
        (detail['activity_timeline'] as List<dynamic>? ?? const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();

    final memberId = (member['id'] as num?)?.toInt() ?? 0;
    final assignedTrainerId = (profile['assigned_trainer_user_id'] as num?)
        ?.toInt();
    final membershipStatus = profile['membership_status']?.toString() ?? '--';
    final membershipExpiry =
        profile['membership_expires_on']?.toString() ?? '--';
    final currentMembershipId = (member['current_membership_id'] as num?)
        ?.toInt();

    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Text(
          member['name']?.toString() ?? 'Member Detail',
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 8),
        Text(
          member['email']?.toString() ?? 'No email available',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: 20),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Profile Snapshot',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              _InfoRow(
                label: 'Branch',
                value: profile['branch_id']?.toString() ?? '--',
              ),
              _InfoRow(
                label: 'Assigned trainer',
                value: assignedTrainerId != null
                    ? 'Trainer #$assignedTrainerId'
                    : 'Not assigned',
              ),
              _InfoRow(
                label: 'Fitness goal',
                value: profile['fitness_goal']?.toString() ?? 'Not set',
              ),
              _InfoRow(
                label: 'Experience',
                value: profile['experience_level']?.toString() ?? 'Unknown',
              ),
              _InfoRow(
                label: 'Height',
                value: profile['height_cm']?.toString().isNotEmpty == true
                    ? '${profile['height_cm']} cm'
                    : '--',
              ),
              _InfoRow(
                label: 'Weight',
                value: profile['weight_kg']?.toString().isNotEmpty == true
                    ? '${profile['weight_kg']} kg'
                    : '--',
              ),
              _InfoRow(
                label: 'Medical notes',
                value: profile['medical_notes']?.toString() ?? 'None recorded',
              ),
              _InfoRow(
                label: 'Injury notes',
                value: profile['injury_notes']?.toString() ?? 'None recorded',
              ),
              _InfoRow(
                label: 'Emergency contact',
                value:
                    profile['emergency_contact_phone']?.toString() ??
                    'Not provided',
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Membership Summary',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              _InfoRow(
                label: 'Status',
                value: membershipStatus.replaceAll('_', ' '),
              ),
              _InfoRow(label: 'Expiry date', value: membershipExpiry),
              _InfoRow(
                label: 'Current membership',
                value: currentMembershipId != null
                    ? '#$currentMembershipId'
                    : 'No active membership',
              ),
            ],
          ),
        ),
        if (engagement.isNotEmpty) ...[
          const SizedBox(height: 16),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Engagement Score',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    _MiniHighlight(
                      label: 'Score',
                      value: '${engagement['score'] ?? '--'}/100',
                    ),
                    _MiniHighlight(
                      label: 'Category',
                      value: engagement['category']?.toString() ?? '--',
                    ),
                    _MiniHighlight(
                      label: 'Attendance 30d',
                      value: '${engagement['attendance_last_30_days'] ?? 0}',
                    ),
                    _MiniHighlight(
                      label: 'Workouts this week',
                      value:
                          '${engagement['workouts_completed_this_week'] ?? 0}',
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  engagement['summary']?.toString() ??
                      'No engagement summary available yet.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
            ),
          ),
        ],
        if (memberTimeline.isNotEmpty) ...[
          const SizedBox(height: 16),
          _SectionHeader(
            title: 'Membership Timeline',
            subtitle:
                'Member lifecycle, billing, attendance, coaching, and progress history.',
          ),
          const SizedBox(height: 12),
          _TimelineCard(
            items: memberTimeline
                .map(
                  (item) => _TimelineEntry(
                    title: item['title']?.toString() ?? 'Audit event',
                    subtitle:
                        item['change_summary']?.toString().isNotEmpty == true
                        ? item['change_summary'].toString()
                        : (item['reason']?.toString().isNotEmpty == true
                              ? item['reason'].toString()
                              : 'Activity recorded in the gym timeline'),
                    valueLabel:
                        item['amount_label']?.toString().isNotEmpty == true
                        ? item['amount_label'].toString()
                        : 'When',
                    value: item['amount_value']?.toString().isNotEmpty == true
                        ? item['amount_value'].toString()
                        : item['date']?.toString() ?? '--',
                    meta:
                        '${item['changed_by'] ?? 'System'} • ${item['date'] ?? '--'}',
                    iconName: item['icon']?.toString(),
                  ),
                )
                .toList(),
          ),
        ],
        const SizedBox(height: 16),
        _MemberDetailAsyncHistorySection(
          memberId: memberId,
          repository: repository,
        ),
        if (activityTimeline.isNotEmpty) ...[
          const SizedBox(height: 16),
          _SectionHeader(
            title: 'Attendance and Payment Activity',
            subtitle: 'Recent operational events tracked against this member.',
          ),
          const SizedBox(height: 12),
          _TimelineCard(
            items: activityTimeline
                .take(8)
                .map(
                  (item) => _TimelineEntry(
                    title: item['title']?.toString() ?? 'Activity',
                    subtitle:
                        item['description']?.toString() ??
                        'Recorded in member history',
                    valueLabel: 'When',
                    value: item['date']?.toString() ?? '--',
                    meta: item['changed_by']?.toString() ?? 'System',
                    iconName: item['icon']?.toString(),
                  ),
                )
                .toList(),
          ),
        ],
        const SizedBox(height: 16),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Progress and Workouts',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              const Text(
                'Workout completion, body progress, and coaching insights will appear here as the member training workflow expands.',
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            if (_canAny(['member.manage', 'manage_members']))
              QuickActionButton(
                label: 'Edit',
                icon: Icons.edit_rounded,
                onPressed: () =>
                    onOpenForm(_AdminFormType.member, prefill: member),
              ),
            if (_canAny(['member.manage', 'manage_members']))
              QuickActionButton(
                label: 'Assign Trainer',
                icon: Icons.person_add_alt_1_rounded,
                onPressed: () =>
                    onOpenForm(_AdminFormType.member, prefill: member),
              ),
            if (_canAny(['membership.manage']))
              QuickActionButton(
                label: 'Assign Membership',
                icon: Icons.autorenew_rounded,
                onPressed: () => onOpenForm(
                  _AdminFormType.membershipAssign,
                  prefill: {'member_id': memberId},
                ),
              ),
            if (_canAny([
              'payment.manage',
              'payment.view',
              'collect_payment',
              'view_billing',
            ]))
              QuickActionButton(
                label: 'Collect',
                icon: Icons.payments_rounded,
                onPressed: () => onOpenForm(
                  _AdminFormType.payment,
                  prefill: {'member_id': memberId},
                ),
              ),
            if (_canAny(['edit_custom_fee']))
              QuickActionButton(
                label: 'Custom Fee',
                icon: Icons.tune_rounded,
                onPressed: () => onOpenForm(
                  _AdminFormType.customFee,
                  prefill: {'member_id': memberId},
                ),
              ),
            if (_canAny(['attendance.manage', 'manage_attendance']))
              QuickActionButton(
                label: 'Attendance',
                icon: Icons.how_to_reg_rounded,
                onPressed: () => onOpenForm(
                  _AdminFormType.attendance,
                  prefill: {'member_id': memberId},
                ),
              ),
            if (_canAny([
              'announcement.manage',
              'announcement.view',
              'notification.manage',
              'send_announcements',
            ]))
              QuickActionButton(
                label: 'Reminder',
                icon: Icons.notifications_active_rounded,
                onPressed: () => onOpenForm(
                  _AdminFormType.announcement,
                  prefill: {
                    'audience_type': 'selected_members',
                    'member_ids': '$memberId',
                    'title': 'Friendly reminder',
                  },
                ),
              ),
            QuickActionButton(
              label: 'Audit Log',
              icon: Icons.history_rounded,
              onPressed: () {},
            ),
          ],
        ),
      ],
    );
  }
}

class _MemberDetailAsyncHistorySection extends StatelessWidget {
  const _MemberDetailAsyncHistorySection({
    required this.memberId,
    required this.repository,
  });

  final int memberId;
  final AdminRepository repository;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<
      (List<Map<String, dynamic>>, List<Map<String, dynamic>>)
    >(
      future: () async {
        final payments = await repository.fetchMemberPayments(memberId);
        final attendance = await repository.fetchMemberAttendance(memberId);
        return (payments, attendance);
      }(),
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const PremiumCard(
            child: LoadingState(label: 'Loading member activity...'),
          );
        }

        if (snapshot.hasError) {
          return PremiumCard(
            child: ErrorState(
              message: snapshot.error.toString(),
              onRetry: () {},
            ),
          );
        }

        final payments = snapshot.data?.$1 ?? const <Map<String, dynamic>>[];
        final attendance = snapshot.data?.$2 ?? const <Map<String, dynamic>>[];

        return Column(
          children: [
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Payment History',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (payments.isEmpty)
                    const EmptyState(
                      title: 'No payments yet',
                      message:
                          'Payment history for this member will appear here.',
                      icon: Icons.payments_outlined,
                    )
                  else
                    ...payments
                        .take(6)
                        .map(
                          (payment) => Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: _InfoRow(
                              label:
                                  '${payment['payment_mode'] ?? 'Payment'} • ${payment['payment_status'] ?? '--'}',
                              value:
                                  '${_formatCurrency(payment['amount'])} • ${_formatDateTime(payment['paid_at'] ?? payment['payment_date'])}',
                            ),
                          ),
                        ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Attendance History',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  if (attendance.isEmpty)
                    const EmptyState(
                      title: 'No attendance yet',
                      message: 'Member attendance entries will appear here.',
                      icon: Icons.event_busy_rounded,
                    )
                  else
                    ...attendance
                        .take(6)
                        .map(
                          (entry) => Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: _InfoRow(
                              label:
                                  '${entry['check_in_method'] ?? 'manual'} • ${entry['branch_name'] ?? entry['branch'] ?? '--'}',
                              value: _formatDateTime(entry['checked_in_at']),
                            ),
                          ),
                        ),
                ],
              ),
            ),
          ],
        );
      },
    );
  }
}

class _MembershipOverviewCard extends StatelessWidget {
  const _MembershipOverviewCard({
    required this.membershipDetail,
    required this.planName,
    required this.loading,
  });

  final Map<String, dynamic> membershipDetail;
  final String planName;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Selected Membership',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              if (loading)
                const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
            ],
          ),
          const SizedBox(height: 12),
          _InfoRow(label: 'Plan', value: planName),
          _InfoRow(
            label: 'Default plan price',
            value: _formatCurrency(membershipDetail['default_plan_price']),
          ),
          _InfoRow(
            label: 'Joining fee',
            value: _formatCurrency(membershipDetail['default_joining_fee']),
          ),
          _InfoRow(
            label: 'Paid',
            value: _formatCurrency(membershipDetail['amount_paid']),
          ),
          _InfoRow(
            label: 'Due',
            value: _formatCurrency(membershipDetail['due_amount']),
          ),
        ],
      ),
    );
  }
}

class _TimelineCard extends StatelessWidget {
  const _TimelineCard({required this.items});

  final List<_TimelineEntry> items;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Column(
        children: items.asMap().entries.map((entry) {
          final item = entry.value;
          final isLast = entry.key == items.length - 1;
          final accent = _timelineColorForIcon(item.iconName);
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Column(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: accent.withValues(alpha: 0.14),
                      border: Border.all(color: accent.withValues(alpha: 0.28)),
                    ),
                    child: Icon(
                      _timelineIconForName(item.iconName),
                      color: accent,
                      size: 18,
                    ),
                  ),
                  if (!isLast)
                    Container(width: 1.5, height: 54, color: AppColors.stroke),
                ],
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Padding(
                  padding: EdgeInsets.only(bottom: isLast ? 0 : 18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        item.subtitle,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      if (item.meta != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          item.meta!,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                      const SizedBox(height: 4),
                      Text(
                        '${item.valueLabel}: ${item.value}',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: accent,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        }).toList(),
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 4),
        Text(subtitle, style: Theme.of(context).textTheme.bodyMedium),
      ],
    );
  }
}

class _MiniHighlight extends StatelessWidget {
  const _MiniHighlight({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        color: AppColors.surfaceSoft,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 4),
          Text(value, style: Theme.of(context).textTheme.titleMedium),
        ],
      ),
    );
  }
}

class _InlineBadge extends StatelessWidget {
  const _InlineBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final accent = _badgeColorForLabel(label);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: accent.withValues(alpha: 0.16),
        border: Border.all(color: accent.withValues(alpha: 0.3)),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: accent,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(label, style: Theme.of(context).textTheme.bodySmall),
          ),
          Expanded(
            child: Text(value, style: Theme.of(context).textTheme.bodyMedium),
          ),
        ],
      ),
    );
  }
}

class _TimelineEntry {
  const _TimelineEntry({
    required this.title,
    required this.subtitle,
    required this.valueLabel,
    required this.value,
    this.meta,
    this.iconName,
  });

  final String title;
  final String subtitle;
  final String valueLabel;
  final String value;
  final String? meta;
  final String? iconName;
}

IconData _timelineIconForName(String? iconName) {
  switch (iconName) {
    case 'member_created':
      return Icons.person_add_alt_1_rounded;
    case 'membership_assigned':
      return Icons.card_membership_rounded;
    case 'membership_renewed':
      return Icons.autorenew_rounded;
    case 'membership_status':
      return Icons.pause_circle_filled_rounded;
    case 'trainer_assigned':
      return Icons.fitness_center_rounded;
    case 'custom_fee':
      return Icons.tune_rounded;
    case 'payment':
      return Icons.payments_rounded;
    case 'attendance':
      return Icons.qr_code_scanner_rounded;
    case 'workout_plan':
      return Icons.assignment_rounded;
    case 'progress_photo':
      return Icons.add_a_photo_rounded;
    default:
      return Icons.bolt_rounded;
  }
}

Color _timelineColorForIcon(String? iconName) {
  switch (iconName) {
    case 'payment':
      return AppColors.success;
    case 'custom_fee':
      return AppColors.warning;
    case 'membership_status':
      return AppColors.error;
    case 'trainer_assigned':
    case 'workout_plan':
    case 'progress_photo':
      return AppColors.primaryBright;
    default:
      return AppColors.primary;
  }
}

class _MetricConfig {
  const _MetricConfig(this.label, this.value, this.icon);

  final String label;
  final String value;
  final IconData icon;
}

String _formatCurrency(dynamic raw) {
  final value = (raw as num?)?.toDouble() ?? double.tryParse('$raw') ?? 0;
  return 'Rs ${value.toStringAsFixed(0)}';
}

double _toDouble(dynamic raw) {
  return (raw as num?)?.toDouble() ?? double.tryParse('$raw') ?? 0;
}

String _formatDateTime(dynamic raw) {
  final value = raw?.toString();
  if (value == null || value.isEmpty) {
    return '--';
  }
  final parsed = DateTime.tryParse(value);
  if (parsed == null) {
    return value;
  }
  return DateFormat('dd MMM, hh:mm a').format(parsed);
}

Color _badgeColorForLabel(String label) {
  final normalized = label.trim().toLowerCase().replaceAll('_', ' ');

  if ({
    'active',
    'paid',
    'accepted',
    'completed',
    'converted',
    'open',
    'ready',
    'verified',
    'public',
  }.contains(normalized)) {
    return normalized == 'verified' || normalized == 'public'
        ? AppColors.primaryBright
        : AppColors.success;
  }

  if ({
    'inactive',
    'expired',
    'cancelled',
    'overdue',
    'rejected',
    'closed',
    'private',
    'error',
  }.contains(normalized)) {
    return AppColors.error;
  }

  if ({
    'expiring soon',
    'due',
    'partial',
    'unpaid',
    'frozen',
    'trial',
    'duplicate',
    'attention',
  }.contains(normalized)) {
    return AppColors.warning;
  }

  if ({'pending', 'unverified'}.contains(normalized)) {
    return AppColors.primary;
  }

  if (normalized == 'featured') {
    return const Color(0xFFA78BFA);
  }

  if (normalized == 'promoted') {
    return const Color(0xFFF5C451);
  }

  if (normalized == 'trainer assigned') {
    return AppColors.primaryBright;
  }

  return AppColors.primary;
}

Color _statusChipColor(String? status) {
  switch ((status ?? '').toLowerCase()) {
    case 'approved':
    case 'active':
      return AppColors.success;
    case 'rejected':
    case 'overdue':
      return AppColors.error;
    default:
      return AppColors.warning;
  }
}

class _SingleActionCard extends StatelessWidget {
  const _SingleActionCard({
    required this.title,
    required this.description,
    required this.actionLabel,
    required this.onTap,
  });

  final String title;
  final String description;
  final String actionLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return RevealOnBuild(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 12),
              Text(description),
              const SizedBox(height: 20),
              AppPrimaryButton(label: actionLabel, onPressed: onTap),
            ],
          ),
        ),
      ),
    );
  }
}

class _MiniMetric extends StatelessWidget {
  const _MiniMetric({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceStrong.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.primaryBright),
          const SizedBox(height: 10),
          Text(value, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _AdminDestination {
  const _AdminDestination(
    this.title,
    this.icon, {
    this.endpoint,
    this.formType,
  });

  final String title;
  final IconData icon;
  final String? endpoint;
  final _AdminFormType? formType;
}

class _CollectionState {
  List<Map<String, dynamic>> items = [];
  bool loading = false;
  String? error;
  int page = 1;
  int lastPage = 1;

  bool get hasMore => page < lastPage;
}

String _dashboardTitleCase(String value) {
  if (value.trim().isEmpty) {
    return '--';
  }
  return value
      .split('_')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

class _QuickFilterOption {
  const _QuickFilterOption(this.key, this.label);

  final String key;
  final String label;
}

class _WorkoutBookWorkspaceSection extends StatefulWidget {
  const _WorkoutBookWorkspaceSection({
    required this.appUser,
    required this.repository,
  });

  final AppUser appUser;
  final AdminRepository repository;

  @override
  State<_WorkoutBookWorkspaceSection> createState() =>
      _WorkoutBookWorkspaceSectionState();
}

class _WorkoutBookWorkspaceSectionState
    extends State<_WorkoutBookWorkspaceSection> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _books = const [];
  final TextEditingController _searchController = TextEditingController();
  String? _statusFilter;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      _books = await widget.repository.fetchPlatformWorkoutBooks(
        search: _searchController.text.trim().isEmpty
            ? null
            : _searchController.text.trim(),
        status: _statusFilter,
      );
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _openForm({Map<String, dynamic>? prefill}) async {
    final changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _WorkoutBookFormSheet(
        repository: widget.repository,
        prefill: prefill,
      ),
    );

    if (changed == true && mounted) {
      await _load();
    }
  }

  Future<void> _showDetail(Map<String, dynamic> book) async {
    final id = (book['id'] as num?)?.toInt();
    if (id == null) {
      return;
    }

    final detail = await widget.repository.fetchPlatformWorkoutBookDetail(id);
    if (!mounted) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => FitModalSurface(
        title: detail['name']?.toString() ?? 'Workout book',
        subtitle: detail['description']?.toString() ?? 'No description set.',
        icon: Icons.menu_book_rounded,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  _InlineBadge(label: '${detail['plans_count'] ?? 0} plans'),
                  if (detail['difficulty'] != null)
                    _InlineBadge(label: '${detail['difficulty']}'),
                  if (detail['days_per_week'] != null)
                    _InlineBadge(label: '${detail['days_per_week']} days/week'),
                  if (detail['total_exercises'] != null)
                    _InlineBadge(
                      label: '${detail['total_exercises']} exercise slots',
                    ),
                ],
              ),
              const SizedBox(height: 16),
              ...((detail['plans'] as List<dynamic>? ?? const [])
                  .map((item) => Map<String, dynamic>.from(item as Map))
                  .map(
                    (plan) => Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(18),
                        color: AppColors.surfaceSoft,
                        border: Border.all(color: AppColors.strokeStrong),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            plan['name']?.toString() ?? 'Plan',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: 6),
                          Text(
                            plan['goal']?.toString() ??
                                'Structured training plan',
                          ),
                          const SizedBox(height: 10),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              if (plan['total_workout_days'] != null)
                                _InlineBadge(
                                  label: '${plan['total_workout_days']} days',
                                ),
                              if (plan['total_exercises'] != null)
                                _InlineBadge(
                                  label: '${plan['total_exercises']} exercises',
                                ),
                              if (plan['estimated_session_minutes'] != null)
                                _InlineBadge(
                                  label:
                                      '${plan['estimated_session_minutes']} min',
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  )),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _delete(Map<String, dynamic> book) async {
    final id = (book['id'] as num?)?.toInt();
    if (id == null) {
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: 'Delete workout book?',
        message:
            'Delete ${book['name']?.toString() ?? 'this workout book'} and all plans inside it?',
        confirmLabel: 'Delete',
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await widget.repository.deletePlatformWorkoutBook(id);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Workout book deleted.')));
      await _load();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    return AsyncStateView(
      isLoading: _loading,
      error: _error,
      onRetry: _load,
      loadingChild: const LoadingState(label: 'Loading workout books...'),
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Workout Book Catalog',
                            style: Theme.of(context).textTheme.headlineSmall,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Manage platform-level training books and the nested plan payload members can adopt into their own workout library.',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    GradientButton(
                      label: 'Create Book',
                      icon: Icons.add_rounded,
                      onPressed: _openForm,
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _searchController,
                        decoration: InputDecoration(
                          labelText: 'Search workout books',
                          suffixIcon: IconButton(
                            onPressed: _load,
                            icon: const Icon(Icons.search_rounded),
                          ),
                        ),
                        onSubmitted: (_) => _load(),
                      ),
                    ),
                    const SizedBox(width: 12),
                    SizedBox(
                      width: 180,
                      child: DropdownButtonFormField<String?>(
                        initialValue: _statusFilter,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: const [
                          DropdownMenuItem<String?>(
                            value: null,
                            child: Text('All'),
                          ),
                          DropdownMenuItem(
                            value: 'active',
                            child: Text('Active'),
                          ),
                          DropdownMenuItem(
                            value: 'inactive',
                            child: Text('Inactive'),
                          ),
                        ],
                        onChanged: (value) =>
                            setState(() => _statusFilter = value),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          if (_books.isEmpty)
            const EmptyState(
              title: 'No workout books created',
              message:
                  'Create a platform workout book to publish catalog plans for members.',
              icon: Icons.menu_book_rounded,
            )
          else
            ..._books.map((book) {
              final plans =
                  (book['plans'] as List<dynamic>? ?? const []).length;
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: PremiumCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              book['name']?.toString() ?? 'Workout book',
                              style: Theme.of(context).textTheme.titleLarge,
                            ),
                          ),
                          _InlineBadge(
                            label: book['status']?.toString() ?? 'active',
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        book['description']?.toString() ??
                            'No description set.',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          _InlineBadge(label: '$plans plans'),
                          if (book['difficulty'] != null)
                            _InlineBadge(label: '${book['difficulty']}'),
                          if (book['days_per_week'] != null)
                            _InlineBadge(
                              label: '${book['days_per_week']} days/week',
                            ),
                          if (book['estimated_session_minutes'] != null)
                            _InlineBadge(
                              label: '${book['estimated_session_minutes']} min',
                            ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () => _showDetail(book),
                              icon: const Icon(Icons.visibility_outlined),
                              label: const Text('Preview'),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () => _openForm(prefill: book),
                              icon: const Icon(Icons.edit_rounded),
                              label: const Text('Edit'),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () => _delete(book),
                              icon: const Icon(Icons.delete_outline_rounded),
                              label: const Text('Delete'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }
}

class _WorkoutBookFormSheet extends StatefulWidget {
  const _WorkoutBookFormSheet({required this.repository, this.prefill});

  final AdminRepository repository;
  final Map<String, dynamic>? prefill;

  @override
  State<_WorkoutBookFormSheet> createState() => _WorkoutBookFormSheetState();
}

class _WorkoutBookFormSheetState extends State<_WorkoutBookFormSheet> {
  late final TextEditingController _nameController;
  late final TextEditingController _audienceController;
  late final TextEditingController _goalController;
  late final TextEditingController _programTypeController;
  late final TextEditingController _equipmentController;
  late final TextEditingController _daysController;
  late final TextEditingController _durationController;
  late final TextEditingController _minutesController;
  late final TextEditingController _descriptionController;
  late final TextEditingController _coachNotesController;
  late final TextEditingController _plansJsonController;
  late String _difficulty;
  late String _status;
  bool _featured = false;
  bool _saving = false;
  static const String _samplePlanJson = '''
[
  {
    "name": "Starter Full Body",
    "goal": "General strength and movement quality",
    "difficulty": "beginner",
    "program_type": "full_body",
    "equipment_profile": "mixed_gym",
    "duration_weeks": 4,
    "estimated_session_minutes": 45,
    "weekly_schedule": ["monday", "wednesday", "friday"],
    "notes": "Keep 1-2 reps in reserve on every set.",
    "status": "active",
    "days": [
      {
        "day_number": 1,
        "label": "Day 1",
        "focus": "Squat and push",
        "notes": "",
        "exercises": [
          {"exercise_id": 1, "sort_order": 1, "sets": 3, "reps": "8-10", "target_weight": null, "rest_seconds": 90, "notes": ""}
        ]
      }
    ]
  }
]
''';

  @override
  void initState() {
    super.initState();
    final prefill = widget.prefill ?? const <String, dynamic>{};
    _nameController = TextEditingController(
      text: prefill['name']?.toString() ?? '',
    );
    _audienceController = TextEditingController(
      text: prefill['audience']?.toString() ?? '',
    );
    _goalController = TextEditingController(
      text: prefill['goal']?.toString() ?? '',
    );
    _programTypeController = TextEditingController(
      text: prefill['program_type']?.toString() ?? '',
    );
    _equipmentController = TextEditingController(
      text: prefill['equipment_profile']?.toString() ?? '',
    );
    _daysController = TextEditingController(
      text: '${prefill['days_per_week'] ?? 3}',
    );
    _durationController = TextEditingController(
      text: '${prefill['duration_weeks'] ?? 4}',
    );
    _minutesController = TextEditingController(
      text: '${prefill['estimated_session_minutes'] ?? 45}',
    );
    _descriptionController = TextEditingController(
      text: prefill['description']?.toString() ?? '',
    );
    _coachNotesController = TextEditingController(
      text: prefill['coach_notes']?.toString() ?? '',
    );
    _difficulty = prefill['difficulty']?.toString() ?? 'beginner';
    _status = prefill['status']?.toString() ?? 'active';
    _featured = prefill['is_featured'] == true;
    final plans = (prefill['plans'] as List<dynamic>? ?? const []);
    _plansJsonController = TextEditingController(
      text: const JsonEncoder.withIndent('  ').convert(
        plans
            .map(
              (item) =>
                  _normalizePlanJson(Map<String, dynamic>.from(item as Map)),
            )
            .toList(),
      ),
    );
    if (plans.isEmpty) {
      _plansJsonController.text = _samplePlanJson;
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _audienceController.dispose();
    _goalController.dispose();
    _programTypeController.dispose();
    _equipmentController.dispose();
    _daysController.dispose();
    _durationController.dispose();
    _minutesController.dispose();
    _descriptionController.dispose();
    _coachNotesController.dispose();
    _plansJsonController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Workout book name is required.')),
      );
      return;
    }

    List<dynamic> plans;
    try {
      plans = jsonDecode(_plansJsonController.text.trim()) as List<dynamic>;
    } catch (_) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Plans JSON is not valid.')));
      return;
    }

    final payload = <String, dynamic>{
      'name': name,
      'audience': _audienceController.text.trim(),
      'goal': _goalController.text.trim(),
      'difficulty': _difficulty,
      'program_type': _programTypeController.text.trim(),
      'equipment_profile': _equipmentController.text.trim(),
      'days_per_week': int.tryParse(_daysController.text.trim()) ?? 3,
      'duration_weeks': int.tryParse(_durationController.text.trim()) ?? 4,
      'estimated_session_minutes':
          int.tryParse(_minutesController.text.trim()) ?? 45,
      'description': _descriptionController.text.trim(),
      'coach_notes': _coachNotesController.text.trim(),
      'is_featured': _featured,
      'status': _status,
      'plans': plans,
    };

    setState(() => _saving = true);
    try {
      final id = (widget.prefill?['id'] as num?)?.toInt();
      if (id == null) {
        await widget.repository.createPlatformWorkoutBook(payload);
      } else {
        await widget.repository.updatePlatformWorkoutBook(id, payload);
      }
      if (!mounted) {
        return;
      }
      Navigator.of(context).pop(true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
      setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FitModalSurface(
      title: 'Workout book setup',
      subtitle:
          'Edit metadata and plan payloads using the same admin modal pattern.',
      icon: Icons.menu_book_rounded,
      showClose: false,
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.prefill == null
                  ? 'Create workout book'
                  : 'Edit workout book',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 8),
            Text(
              'Edit the book metadata and nested plan payload. Plans JSON must match the backend schema for days and exercises.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 8),
            Text(
              'Tip: use the sample JSON and replace exercise ids with ids from your exercise catalog.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _nameController,
              decoration: const InputDecoration(labelText: 'Name'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _audienceController,
              decoration: const InputDecoration(labelText: 'Audience'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _goalController,
              decoration: const InputDecoration(labelText: 'Goal'),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: _difficulty,
                    decoration: const InputDecoration(labelText: 'Difficulty'),
                    items: const [
                      DropdownMenuItem(
                        value: 'beginner',
                        child: Text('Beginner'),
                      ),
                      DropdownMenuItem(
                        value: 'intermediate',
                        child: Text('Intermediate'),
                      ),
                      DropdownMenuItem(
                        value: 'advanced',
                        child: Text('Advanced'),
                      ),
                    ],
                    onChanged: (value) =>
                        setState(() => _difficulty = value ?? 'beginner'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: _status,
                    decoration: const InputDecoration(labelText: 'Status'),
                    items: const [
                      DropdownMenuItem(value: 'active', child: Text('Active')),
                      DropdownMenuItem(
                        value: 'inactive',
                        child: Text('Inactive'),
                      ),
                    ],
                    onChanged: (value) =>
                        setState(() => _status = value ?? 'active'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _programTypeController,
                    decoration: const InputDecoration(
                      labelText: 'Program type',
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _equipmentController,
                    decoration: const InputDecoration(
                      labelText: 'Equipment profile',
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _daysController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Days/week'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _durationController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Duration weeks',
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _minutesController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Minutes/session',
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            SwitchListTile.adaptive(
              value: _featured,
              onChanged: (value) => setState(() => _featured = value),
              title: const Text('Featured in catalog'),
              contentPadding: EdgeInsets.zero,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _descriptionController,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Description'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _coachNotesController,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Coach notes'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _plansJsonController,
              maxLines: 18,
              decoration: const InputDecoration(
                labelText: 'Plans JSON',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                OutlinedButton.icon(
                  onPressed: () => setState(
                    () => _plansJsonController.text = _samplePlanJson,
                  ),
                  icon: const Icon(Icons.auto_fix_high_rounded),
                  label: const Text('Load sample JSON'),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _saving
                        ? null
                        : () => Navigator.of(context).pop(false),
                    child: const Text('Cancel'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: GradientButton(
                    label: _saving ? 'Saving...' : 'Save workout book',
                    icon: Icons.save_rounded,
                    loading: _saving,
                    onPressed: _saving ? null : _save,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

Map<String, dynamic> _normalizePlanJson(Map<String, dynamic> plan) {
  return {
    'name': plan['name'],
    'goal': plan['goal'],
    'difficulty': plan['difficulty'],
    'program_type': plan['program_type'],
    'equipment_profile': plan['equipment_profile'],
    'duration_weeks': plan['duration_weeks'],
    'estimated_session_minutes': plan['estimated_session_minutes'],
    'weekly_schedule': plan['weekly_schedule'] ?? const [],
    'notes': plan['notes'],
    'status': plan['status'] ?? 'active',
    'days': (plan['days'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .map(
          (day) => {
            'day_number': day['day_number'],
            'label': day['label'],
            'focus': day['focus'],
            'notes': day['notes'],
            'exercises': (day['exercises'] as List<dynamic>? ?? const [])
                .map((exercise) => Map<String, dynamic>.from(exercise as Map))
                .map(
                  (exercise) => {
                    'exercise_id': exercise['exercise_id'],
                    'sort_order': exercise['sort_order'],
                    'sets': exercise['sets'],
                    'reps': exercise['reps'],
                    'target_weight': exercise['target_weight'],
                    'rest_seconds': exercise['rest_seconds'],
                    'notes': exercise['notes'],
                  },
                )
                .toList(),
          },
        )
        .toList(),
  };
}

enum _AdminFormType {
  platformGym,
  platformGymOwner,
  platformFacility,
  gymProfile,
  branch,
  staff,
  trainer,
  member,
  plan,
  membershipAssign,
  customFee,
  payment,
  attendance,
  manualAttendance,
  announcement,
  publicListing,
  platformReports,
}
