import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:socket_io_client/socket_io_client.dart' as io;

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import '../../core/models.dart';
import '../auth/session_controller.dart';
import 'member_logbook_screen.dart';
import 'member_assigned_trainer_screen.dart';
import 'member_assigned_workout_screen.dart';
import 'member_membership_screen.dart';
import 'member_notifications_screen.dart';
import 'member_onboarding_flow.dart';
import 'member_profile_screen.dart';
import 'member_progress_screen.dart';
import 'member_gym_discovery_screen.dart';
import 'member_settings_screen.dart';
import 'member_trial_requests_screen.dart';
import 'member_workout_book_screen.dart';
import 'member_repository.dart';
import 'services/step_sync_service.dart';
import 'socket_service.dart';
import 'health/step_health_types.dart';
import 'widgets/step_dashboard_widget.dart';

Map<String, dynamic> _recordMap(dynamic value) {
  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }

  return const <String, dynamic>{};
}

class MemberHomeScreen extends StatefulWidget {
  const MemberHomeScreen({super.key});

  @override
  State<MemberHomeScreen> createState() => _MemberHomeScreenState();
}

class _MemberHomeScreenState extends State<MemberHomeScreen>
    with WidgetsBindingObserver {
  MemberRepository? _repository;
  StepSyncService? _stepSyncService;
  final MemberSocketService _socketService = MemberSocketService();
  io.Socket? _socket;
  int _index = 0;
  int? _preferredWorkoutPlanId;
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _contextData = const {};
  List<Map<String, dynamic>> _attendance = const [];
  List<Map<String, dynamic>> _plans = const [];
  List<Map<String, dynamic>> _history = const [];
  Map<String, dynamic> _progressSummary = const {};
  Map<String, dynamic> _logbookSummary = const {};
  List<Map<String, dynamic>> _notifications = const [];
  Map<String, dynamic> _qrData = const {};
  List<Map<String, dynamic>> _publicGyms = const [];
  int _chatEventVersion = 0;
  bool _stepSyncInFlight = false;
  String _stepPermissionStatus = 'unknown';
  bool _stepSyncLoading = false;
  StepSyncResult? _latestStepSyncResult;

  Future<Map<String, dynamic>> _safeMapRequest(
    Future<Map<String, dynamic>> Function() request, {
    required String label,
  }) async {
    try {
      return await request();
    } catch (exception) {
      debugPrint('[member-home][warn] $label failed: $exception');
      return const <String, dynamic>{'data': null};
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    scheduleMicrotask(_bootstrap);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _ensureRepository();
  }

  void _ensureRepository() {
    final session = context.read<MemberSessionController>();
    _repository ??= MemberRepository(session.client);
    _stepSyncService ??= StepSyncService(repository: _repository!);
  }

  MemberRepository get _memberRepository {
    _ensureRepository();
    return _repository!;
  }

  Future<void> _bootstrap() async {
    final session = context.read<MemberSessionController>();
    await _syncStepsIfNeeded();
    await _load();
    if (session.token != null) {
      _socket = _socketService.connect(session.token!);
      _socket?.on('notification:new', (data) {
        setState(
          () => _notifications = [
            Map<String, dynamic>.from(data as Map? ?? const {}),
            ..._notifications,
          ],
        );
      });
      _socket?.on('chat:new_message', (_) {
        if (!mounted) {
          return;
        }
        setState(() => _chatEventVersion++);
      });
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final contextResponse = await _memberRepository.fetchContext();
      final results = await Future.wait([
        _safeMapRequest(
          _memberRepository.fetchAttendanceHistory,
          label: 'attendance history',
        ),
        _safeMapRequest(
          _memberRepository.fetchWorkoutPlans,
          label: 'workout plans',
        ),
        _safeMapRequest(
          _memberRepository.fetchWorkoutHistory,
          label: 'workout history',
        ),
        _safeMapRequest(
          _memberRepository.fetchLogbookSummary,
          label: 'logbook summary',
        ),
        _safeMapRequest(
          _memberRepository.fetchProgressSummary,
          label: 'progress summary',
        ),
        _safeMapRequest(
          _memberRepository.fetchNotifications,
          label: 'notifications',
        ),
        _safeMapRequest(_memberRepository.fetchQrCode, label: 'qr code'),
        _safeMapRequest(
          _memberRepository.fetchPublicGyms,
          label: 'public gyms',
        ),
      ]);
      _contextData = Map<String, dynamic>.from(
        contextResponse['data'] as Map? ?? const {},
      );
      final userData = _contextData['user'];
      if (mounted && userData is Map) {
        await context.read<MemberSessionController>().updateCurrentUser(
          MemberUser.fromJson(Map<String, dynamic>.from(userData)),
        );
      }
      _attendance = (results[0]['data'] as List<dynamic>? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      _plans = (results[1]['data'] as List<dynamic>? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      _history = (results[2]['data'] as List<dynamic>? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      _logbookSummary = Map<String, dynamic>.from(
        results[3]['data'] as Map? ?? const {},
      );
      _progressSummary = Map<String, dynamic>.from(
        results[4]['data'] as Map? ?? const {},
      );
      _notifications = (results[5]['data'] as List<dynamic>? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      _qrData = Map<String, dynamic>.from(
        results[6]['data'] as Map? ?? const {},
      );
      _publicGyms = (results[7]['data'] as List<dynamic>? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
    } catch (exception) {
      _error = exception.toString();
    }
    if (mounted) {
      setState(() => _loading = false);
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    if (state == AppLifecycleState.resumed && _index == 0) {
      unawaited(_handleDashboardFocus());
    }
  }

  Future<void> _handleDashboardFocus() async {
    await _syncStepsIfNeeded();
    if (mounted) {
      await _load();
    }
  }

  Future<void> _handleManualRefresh() async {
    await _syncStepsIfNeeded(force: true);
    if (mounted) {
      await _load();
    }
  }

  Future<void> _syncStepsIfNeeded({bool force = false}) async {
    _ensureRepository();
    if (_stepSyncInFlight) {
      return;
    }

    if (mounted) {
      setState(() => _stepSyncLoading = true);
    }
    _stepSyncInFlight = true;
    try {
      final result = await _stepSyncService?.syncToday(force: force);
      if (mounted && result != null) {
        setState(() {
          _stepPermissionStatus = result.snapshot.permissionStatus.name;
          _latestStepSyncResult = result;
        });
      }
    } catch (exception, stackTrace) {
      debugPrint('[member-home][steps] sync failed: $exception');
      debugPrintStack(stackTrace: stackTrace);
    } finally {
      _stepSyncInFlight = false;
      if (mounted) {
        setState(() => _stepSyncLoading = false);
      }
    }
  }

  Future<void> _handleStepPermissionRequest() async {
    _ensureRepository();
    if (mounted) {
      setState(() => _stepSyncLoading = true);
    }

    try {
      final snapshot = await _stepSyncService?.requestPermission();
      if (mounted && snapshot != null) {
        setState(() {
          _stepPermissionStatus = snapshot.permissionStatus.name;
        });
      }
    } catch (exception, stackTrace) {
      debugPrint('[member-home][steps] permission request failed: $exception');
      debugPrintStack(stackTrace: stackTrace);
    } finally {
      if (mounted) {
        setState(() => _stepSyncLoading = false);
      }
    }

    await _syncStepsIfNeeded(force: true);
    if (mounted) {
      await _load();
    }
  }

  Future<void> _openNotificationsScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MemberNotificationsScreen(
          repository: _memberRepository,
          initialNotifications: _notifications,
          onChanged: _load,
        ),
      ),
    );

    if (mounted) {
      await _load();
    }
  }

  Future<void> _openSettingsScreen() async {
    final session = context.read<MemberSessionController>();
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MemberSettingsScreen(
          repository: _memberRepository,
          session: session,
          onOpenProfile: _openProfileScreen,
          onEditProfile: _openProfileEditScreen,
          onOpenMembership: _openMembershipScreen,
          onOpenAttendance: _openAttendanceScreen,
          onPreferencesChanged: _load,
        ),
      ),
    );

    if (mounted) {
      await _load();
    }
  }

  Future<void> _openLogbookScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) =>
            MemberLogbookScreen(repository: _memberRepository),
      ),
    );

    if (mounted) {
      await _load();
    }
  }

  Future<void> _openQrScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MemberQrScreen(
          repository: _memberRepository,
          onDiscoverGyms: () => setState(() => _index = 4),
        ),
      ),
    );
  }

  Future<void> _openMembershipScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MemberMembershipScreen(
          repository: _memberRepository,
          onDiscoverGyms: () => setState(() => _index = 4),
          onShowQr: _openQrScreen,
          onOpenAttendance: _openAttendanceScreen,
        ),
      ),
    );
  }

  Future<void> _openAttendanceScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) =>
            MemberAttendanceScreen(repository: _memberRepository),
      ),
    );
  }

  Future<void> _openProfileScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MemberProfileScreen(
          repository: _memberRepository,
          onProfileUpdated: _load,
        ),
      ),
    );
  }

  Future<void> _openProfileEditScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MemberProfileScreen(
          repository: _memberRepository,
          onProfileUpdated: _load,
          openEditOnLoad: true,
        ),
      ),
    );
  }

  Future<void> _openTrialRequestsScreen({
    Map<String, dynamic>? initialGym,
    bool initialStatusTab = false,
  }) async {
    final user = context.read<MemberSessionController>().user;
    if (user == null) {
      return;
    }

    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MemberTrialRequestsScreen(
          repository: _memberRepository,
          currentUser: user,
          initialGym: initialGym,
          initialStatusTab: initialStatusTab,
        ),
      ),
    );

    if (mounted) {
      await _load();
    }
  }

  Future<void> _openAssignedWorkoutScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MemberAssignedWorkoutScreen(
          repository: _memberRepository,
          initialPlans: _plans,
          onOpenWorkoutBook: _openWorkoutBookScreen,
          onStartAssignedWorkout: (planId) {
            Navigator.of(context).pop();
            setState(() {
              _preferredWorkoutPlanId = planId;
              _index = 1;
            });
          },
        ),
      ),
    );
  }

  Future<void> _openWorkoutBookScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MemberWorkoutBookScreen(
          repository: _memberRepository,
          onStartPlan: (planId) {
            Navigator.of(context).pop();
            setState(() {
              _preferredWorkoutPlanId = planId;
              _index = 1;
            });
          },
        ),
      ),
    );

    if (mounted) {
      await _load();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _socketService.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<MemberSessionController>();
    final user = session.user;
    if (user == null) {
      return const SizedBox.shrink();
    }

    final trainerConnection = Map<String, dynamic>.from(
      _contextData['trainer_connection'] as Map? ?? const {},
    );
    final memberProfile = Map<String, dynamic>.from(
      _contextData['member_profile'] as Map? ?? const {},
    );
    final memberUser = Map<String, dynamic>.from(
      _contextData['user'] as Map? ?? const {},
    );
    final userState =
        _contextData['user_state']?.toString() ?? 'independent_user';
    final onboardingCompleted =
        (memberProfile['member_onboarding_completed'] as bool?) ??
        (memberUser['member_onboarding_completed'] as bool?) ??
        false;
    final stepsData = _recordMap(_contextData['steps']);
    final localStepSnapshot = _latestStepSyncResult?.snapshot;
    final localStepReadAt = _latestStepSyncResult?.readAt;
    final hasServerStepData =
        stepsData.isNotEmpty &&
        (stepsData['lastSyncedAt'] != null ||
            (stepsData['today'] as num? ?? 0) > 0);
    final effectiveSteps = hasServerStepData
        ? stepsData
        : _fallbackStepsFromLocalSnapshot(
            localStepSnapshot,
            readAt: localStepReadAt,
          );

    final pages = [
      _DashboardPage(
        userName: user.name,
        unreadNotifications: _notifications
            .where((item) => item['read_at'] == null)
            .length,
        contextData: _contextData,
        attendance: _attendance,
        qrData: _qrData,
        plans: _plans,
        history: _history,
        progressSummary: _progressSummary,
        logbookSummary: _logbookSummary,
        steps: effectiveSteps,
        stepPermissionStatus: _stepPermissionStatus,
        stepLoading: _stepSyncLoading,
        stepStatusMessage: _latestStepSyncResult?.errorMessage,
        onRefresh: _handleManualRefresh,
        onRequestStepPermission: () =>
            unawaited(_handleStepPermissionRequest()),
        onOpenNotifications: _openNotificationsScreen,
        onStartWorkout: () => setState(() => _index = 1),
        onShowQr: _openQrScreen,
        onMessageTrainer: () => setState(() => _index = 3),
        onLogWeight: () => setState(() => _index = 2),
        onFindGyms: () => setState(() => _index = 4),
        onViewMembership: _openMembershipScreen,
        onOpenProfile: _openProfileScreen,
        onOpenSettings: _openSettingsScreen,
        onOpenLogbook: _openLogbookScreen,
        onOpenAttendance: _openAttendanceScreen,
        onOpenWorkout: _openAssignedWorkoutScreen,
        onOpenTrials: () => _openTrialRequestsScreen(initialStatusTab: true),
      ),
      _WorkoutPage(
        plans: _plans,
        history: _history,
        logbookSummary: _logbookSummary,
        repository: _memberRepository,
        onOpenWorkoutBook: _openWorkoutBookScreen,
        initialPlanId: _preferredWorkoutPlanId,
        onPlanConsumed: () {
          if (_preferredWorkoutPlanId != null) {
            setState(() => _preferredWorkoutPlanId = null);
          }
        },
      ),
      MemberProgressScreen(
        repository: _memberRepository,
        initialSummary: _progressSummary,
        onRefreshParent: _load,
      ),
      MemberAssignedTrainerScreen(
        repository: _memberRepository,
        socket: _socket,
        chatEventVersion: _chatEventVersion,
        userState: userState,
        fallbackTrainerConnection: trainerConnection,
        onOpenAssignedWorkout: _openAssignedWorkoutScreen,
      ),
      MemberGymDiscoveryScreen(
        repository: _memberRepository,
        onRefreshParent: _load,
        currentUser: user,
        onOpenTrialRequests:
            ({
              Map<String, dynamic>? initialGym,
              bool initialStatusTab = false,
            }) => _openTrialRequestsScreen(
              initialGym: initialGym,
              initialStatusTab: initialStatusTab,
            ),
      ),
    ];

    return AppGradientScaffold(
      title: 'Welcome Back',
      subtitle: user.name,
      actions: [
        _UnreadBellAction(
          unreadCount: _notifications
              .where((item) => item['read_at'] == null)
              .length,
          onPressed: _openNotificationsScreen,
        ),
        IconButton(
          onPressed: _openSettingsScreen,
          icon: const Icon(Icons.settings_outlined),
        ),
        IconButton(
          onPressed: _handleManualRefresh,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 240),
        transitionBuilder: (child, animation) {
          final curved = CurvedAnimation(
            parent: animation,
            curve: Curves.easeOutCubic,
            reverseCurve: Curves.easeInCubic,
          );
          return FadeTransition(
            opacity: curved,
            child: SlideTransition(
              position: Tween<Offset>(
                begin: const Offset(0.03, 0.015),
                end: Offset.zero,
              ).animate(curved),
              child: child,
            ),
          );
        },
        child: _loading
            ? const _MemberHomeSkeleton(key: ValueKey('member-loading'))
            : _error != null
            ? ErrorStateView(
                key: const ValueKey('member-error'),
                message: _error!,
                onRetry: _handleManualRefresh,
              )
            : !onboardingCompleted
            ? KeyedSubtree(
                key: const ValueKey('member-onboarding'),
                child: MemberOnboardingFlow(
                  repository: _memberRepository,
                  profile: memberProfile,
                  publicGyms: _publicGyms,
                  trainerConnection: trainerConnection,
                  onFinished: () async {
                    await _load();
                    if (mounted) {
                      setState(() => _index = 1);
                    }
                  },
                ),
              )
            : KeyedSubtree(
                key: ValueKey('member-page-$_index'),
                child: pages[_index],
              ),
      ),
      floatingActionButton: null,
      bottomNavigationBar: onboardingCompleted
          ? _MemberBottomNav(
              currentIndex: _index,
              onSelect: (value) {
                setState(() => _index = value);
                if (value == 0) {
                  unawaited(_handleDashboardFocus());
                }
              },
            )
          : null,
    );
  }

  Map<String, dynamic>? _fallbackStepsFromLocalSnapshot(
    StepHealthSnapshot? snapshot, {
    DateTime? readAt,
  }) {
    if (snapshot == null ||
        snapshot.permissionStatus != StepPermissionStatus.granted) {
      return null;
    }

    final goal = 10000;
    final progress = snapshot.steps == 0
        ? 0
        : ((snapshot.steps / goal) * 100).round().clamp(0, 100);

    return <String, dynamic>{
      'today': snapshot.steps,
      'goal': goal,
      'progressPercent': progress,
      'distanceKm': snapshot.distanceMeters / 1000,
      'calories': snapshot.caloriesEstimated,
      'streakDays': 0,
      'lastSyncedAt': readAt?.toIso8601String(),
    };
  }
}

class _MemberBottomNav extends StatelessWidget {
  const _MemberBottomNav({required this.currentIndex, required this.onSelect});

  final int currentIndex;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    return _GlassBottomNav(currentIndex: currentIndex, onSelect: onSelect);
  }
}

class _GlassBottomNav extends StatelessWidget {
  const _GlassBottomNav({required this.currentIndex, required this.onSelect});

  final int currentIndex;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      minimum: const EdgeInsets.only(bottom: 0),
      child: SizedBox(
        height: 92,
        child: Stack(
          alignment: Alignment.topCenter,
          children: [
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Container(
                height: 62,
                decoration: BoxDecoration(
                  color: Colors.white,
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.shadow.withValues(alpha: 0.10),
                      blurRadius: 14,
                      offset: const Offset(0, -3),
                    ),
                  ],
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _GlassBottomNavItem(
                      label: 'Home',
                      icon: Icons.home_rounded,
                      active: currentIndex == 0,
                      onTap: () => onSelect(0),
                    ),
                    _GlassBottomNavItem(
                      label: 'Train',
                      icon: Icons.fitness_center_rounded,
                      active: currentIndex == 1,
                      onTap: () => onSelect(1),
                    ),
                    const SizedBox(width: 58),
                    _GlassBottomNavItem(
                      label: 'Body',
                      icon: Icons.monitor_weight_rounded,
                      active: currentIndex == 2,
                      onTap: () => onSelect(2),
                    ),
                    _GlassBottomNavItem(
                      label: 'Chats',
                      icon: Icons.chat_bubble_rounded,
                      active: currentIndex == 3,
                      onTap: () => onSelect(3),
                    ),
                  ],
                ),
              ),
            ),
            _MemberCenterAction(
              active: currentIndex == 4,
              onTap: () => onSelect(4),
            ),
          ],
        ),
      ),
    );
  }
}

class _GlassBottomNavItem extends StatelessWidget {
  const _GlassBottomNavItem({
    required this.label,
    required this.icon,
    required this.active,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(22),
      child: SizedBox(
        width: 58,
        height: 56,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedScale(
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOutCubic,
              scale: active ? 1.08 : 1,
              child: Icon(
                icon,
                color: active ? AppColors.primary : AppColors.textMuted,
                size: 25,
              ),
            ),
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOutCubic,
              height: active ? 8 : 12,
            ),
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOutCubic,
              width: active ? 5 : 0,
              height: active ? 5 : 0,
              decoration: BoxDecoration(
                color: AppColors.primaryBright,
                borderRadius: BorderRadius.circular(3),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberCenterAction extends StatelessWidget {
  const _MemberCenterAction({required this.active, required this.onTap});

  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 72,
      height: 72,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(36),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: active ? 0.34 : 0.24),
                blurRadius: active ? 24 : 16,
                offset: const Offset(0, 8),
              ),
            ],
          ),
            child: Container(
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppColors.primaryBright, AppColors.primary],
                  begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              shape: BoxShape.circle,
              boxShadow: const [
                BoxShadow(
                  color: Colors.black12,
                  blurRadius: 2,
                  offset: Offset(0, 1),
                ),
              ],
            ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Icon(
                    Icons.search_rounded,
                    color: Colors.white.withValues(alpha: 0.16),
                    size: 42,
                  ),
                  const Icon(
                    Icons.travel_explore_rounded,
                    color: Colors.white,
                    size: 28,
                  ),
                ],
              ),
          ),
        ),
      ),
    );
  }
}

class _MemberHomeSkeleton extends StatelessWidget {
  const _MemberHomeSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: const [
          SkeletonProfileHeader(),
          SizedBox(height: AppSpacing.lg),
          SkeletonDashboardGrid(),
          SizedBox(height: AppSpacing.lg),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.lg),
          SkeletonDiscoveryCard(),
          SizedBox(height: AppSpacing.lg),
          SkeletonHistoryList(items: 3),
          SizedBox(height: AppSpacing.lg),
          SkeletonNotificationsList(items: 4),
        ],
      ),
    );
  }
}

class _DashboardPage extends StatelessWidget {
  const _DashboardPage({
    required this.userName,
    required this.unreadNotifications,
    required this.contextData,
    required this.attendance,
    required this.qrData,
    required this.plans,
    required this.history,
    required this.progressSummary,
    required this.logbookSummary,
    required this.steps,
    required this.stepPermissionStatus,
    required this.stepLoading,
    required this.stepStatusMessage,
    required this.onRefresh,
    required this.onRequestStepPermission,
    required this.onOpenNotifications,
    required this.onStartWorkout,
    required this.onShowQr,
    required this.onMessageTrainer,
    required this.onLogWeight,
    required this.onFindGyms,
    required this.onViewMembership,
    required this.onOpenProfile,
    required this.onOpenSettings,
    required this.onOpenLogbook,
    required this.onOpenAttendance,
    required this.onOpenWorkout,
    required this.onOpenTrials,
  });

  final String userName;
  final int unreadNotifications;
  final Map<String, dynamic> contextData;
  final List<Map<String, dynamic>> attendance;
  final Map<String, dynamic> qrData;
  final List<Map<String, dynamic>> plans;
  final List<Map<String, dynamic>> history;
  final Map<String, dynamic> progressSummary;
  final Map<String, dynamic> logbookSummary;
  final Map<String, dynamic>? steps;
  final String stepPermissionStatus;
  final bool stepLoading;
  final String? stepStatusMessage;
  final Future<void> Function() onRefresh;
  final VoidCallback onRequestStepPermission;
  final VoidCallback onOpenNotifications;
  final VoidCallback onStartWorkout;
  final VoidCallback onShowQr;
  final VoidCallback onMessageTrainer;
  final VoidCallback onLogWeight;
  final VoidCallback onFindGyms;
  final VoidCallback onViewMembership;
  final VoidCallback onOpenProfile;
  final VoidCallback onOpenSettings;
  final VoidCallback onOpenLogbook;
  final VoidCallback onOpenAttendance;
  final VoidCallback onOpenWorkout;
  final VoidCallback onOpenTrials;

  @override
  Widget build(BuildContext context) {
    final userState =
        contextData['user_state']?.toString() ?? 'independent_user';
    final isTrialUser = userState == 'trial_user';
    final hasGymMembership =
        userState == 'gym_member' || userState == 'gym_member_with_trainer';
    final hasTrainer = userState == 'gym_member_with_trainer';
    final membership = Map<String, dynamic>.from(
      contextData['current_membership'] as Map? ?? const {},
    );
    final memberProfile = Map<String, dynamic>.from(
      contextData['member_profile'] as Map? ?? const {},
    );
    final trainerConnection = Map<String, dynamic>.from(
      contextData['trainer_connection'] as Map? ?? const {},
    );
    final attendanceStatus = Map<String, dynamic>.from(
      contextData['attendance_status'] as Map? ?? const {},
    );
    final assignedTrainer = Map<String, dynamic>.from(
      trainerConnection['assigned_trainer'] as Map? ?? const {},
    );
    final latestWeightLog = Map<String, dynamic>.from(
      progressSummary['latest_weight_log'] as Map? ?? const {},
    );
    final todayWorkout = plans.firstWhere(
      (plan) => (plan['status']?.toString() ?? '').toLowerCase() == 'active',
      orElse: () => plans.firstOrNull ?? const {},
    );
    final workoutStreak = _calculateWorkoutStreak(history);
    final dueAmount = (membership['due_amount'] as num?)?.toDouble() ?? 0;
    final expiryDate = membership['expiry_date']?.toString();
    final hasWarning =
        hasGymMembership && (dueAmount > 0 || _isExpiringSoon(expiryDate));
    final checkedInToday = attendanceStatus['checked_in_today'] == true;
    final latestWeightText = latestWeightLog['weight_kg'] != null
        ? '${latestWeightLog['weight_kg']} kg'
        : '--';
    final totalVolume =
        (logbookSummary['total_volume'] as num?)?.toDouble() ?? 0;
    final stepSummary = steps == null
        ? null
        : StepDashboardData(
            today: (steps!['today'] as num?)?.toInt() ?? 0,
            goal: (steps!['goal'] as num?)?.toInt() ?? 10000,
            progressPercent: (steps!['progressPercent'] as num?)?.toInt() ?? 0,
            distanceKm: (steps!['distanceKm'] as num?)?.toDouble() ?? 0,
            calories: (steps!['calories'] as num?)?.toInt() ?? 0,
            streakDays: (steps!['streakDays'] as num?)?.toInt() ?? 0,
            lastSyncedAt: steps!['lastSyncedAt']?.toString(),
          );
    final activeWorkoutLabel =
        todayWorkout['name']?.toString() ?? 'No workout assigned';
    final assignedTrainerName = hasTrainer
        ? (assignedTrainer['name']?.toString() ?? 'Trainer pending')
        : 'No trainer linked';
    final profileReady =
        (memberProfile['member_onboarding_completed'] as bool?) ?? false;
    final hasWeightLog = latestWeightLog['weight_kg'] != null;
    final stepToday = stepSummary?.today ?? 0;
    final stepGoal = stepSummary?.goal ?? 10000;
    final stepGoalReached = stepToday >= stepGoal && stepGoal > 0;
    final hasActivePlan = todayWorkout.isNotEmpty;
    final readinessSignals = <bool>[
      profileReady,
      hasWeightLog,
      hasActivePlan,
      checkedInToday || !hasGymMembership,
      stepGoalReached || stepToday >= 3000,
    ];
    final readinessCount = readinessSignals.where((value) => value).length;
    final readinessPercent = (0.18 + (readinessCount * 0.16)).clamp(0.18, 0.98);
    final weeklyBars = _weeklyActivityBars(history);
    final latestWorkoutItems = _latestWorkoutItems(plans, history);
    final heroLabel = hasTrainer
        ? 'Trainer connected'
        : hasGymMembership
        ? 'Membership active'
        : isTrialUser
        ? 'Trial access active'
        : 'Independent member';
    final heroTitle = checkedInToday
        ? 'Session active'
        : stepGoalReached
        ? 'Hit today\'s goal'
        : hasGymMembership
        ? 'Membership active'
        : 'Ready to train?';
    final heroSubtitle = hasWarning
        ? 'Membership needs attention'
        : hasTrainer
        ? 'Coach-backed training is ready'
        : hasGymMembership
        ? 'Start strong and log today\'s work'
        : isTrialUser
        ? 'Explore gyms and keep moving'
        : 'Build your independent routine';
    final heroActionLabel = hasGymMembership ? 'QR pass' : 'Profile';
    final firstName =
        userName.trim().split(RegExp(r'\s+')).firstOrNull ?? userName;
    final metricCards = [
      _DashboardMetricData(
        label: 'Streak',
        value: workoutStreak > 0 ? '$workoutStreak d' : '0 d',
        helper: checkedInToday ? 'Checked in' : 'Workout rhythm',
        icon: Icons.local_fire_department_rounded,
        color: AppColors.primary,
      ),
      _DashboardMetricData(
        label: 'Weight',
        value: latestWeightText,
        helper: hasWeightLog ? 'Latest log' : 'Add log',
        icon: Icons.monitor_weight_rounded,
        color: AppColors.primaryBright,
      ),
      _DashboardMetricData(
        label: 'Volume',
        value: totalVolume > 0 ? _formatVolume(totalVolume) : '--',
        helper: totalVolume > 0 ? 'Training load' : 'No volume',
        icon: Icons.fitness_center_rounded,
        color: AppColors.primaryBright,
      ),
      _DashboardMetricData(
        label: 'Access',
        value: hasGymMembership
            ? 'Active'
            : isTrialUser
            ? 'Trial'
            : 'Solo',
        helper: hasTrainer ? 'Coach linked' : heroLabel,
        icon: hasGymMembership ? Icons.verified_rounded : Icons.explore_rounded,
        color: AppColors.primary,
      ),
    ];
    final dashboardActions = [
      _DashboardActionData(
        label: hasGymMembership ? 'Start workout' : 'Explore gyms',
        helper: hasGymMembership ? 'Open today plan' : 'Browse nearby gyms',
        description: hasGymMembership
            ? 'Jump straight into your assigned plan for today.'
            : 'Discover gyms, compare options, and request access.',
        icon: hasGymMembership
            ? Icons.fitness_center_rounded
            : Icons.travel_explore_rounded,
        color: AppColors.primary,
        onTap: hasGymMembership ? onStartWorkout : onFindGyms,
      ),
      _DashboardActionData(
        label: hasGymMembership ? 'Show QR pass' : 'Open profile',
        helper: hasGymMembership ? 'Scan at the front desk' : 'Update your setup',
        description: hasGymMembership
            ? 'Open your access pass instantly when you arrive.'
            : 'Review your baseline and keep your profile complete.',
        icon: hasGymMembership ? Icons.qr_code_2_rounded : Icons.person_rounded,
        color: AppColors.primaryBright,
        onTap: hasGymMembership ? onShowQr : onOpenProfile,
      ),
      _DashboardActionData(
        label: 'Body progress',
        helper: 'Weight logs and trends',
        description: 'Track body updates and keep your progress history current.',
        icon: Icons.monitor_weight_rounded,
        color: AppColors.accentPurple,
        onTap: onLogWeight,
      ),
      _DashboardActionData(
        label: hasTrainer ? 'Message coach' : 'Trainer support',
        helper: hasTrainer ? 'Open coaching chat' : 'Unlock coach access',
        description: hasTrainer
            ? 'Continue your conversation and review coach updates.'
            : 'Connect with a gym to unlock trainer-backed support.',
        icon: hasTrainer ? Icons.chat_bubble_rounded : Icons.support_agent_rounded,
        color: AppColors.primary,
        onTap: hasTrainer ? onMessageTrainer : onFindGyms,
      ),
      _DashboardActionData(
        label: 'Membership',
        helper: hasGymMembership ? 'Status and due dates' : 'See access options',
        description: 'Review access status, dues, and membership details.',
        icon: Icons.card_membership_rounded,
        color: AppColors.primaryBright,
        onTap: onViewMembership,
      ),
      _DashboardActionData(
        label: 'Trial requests',
        helper: 'Manage gym trials',
        description: 'Check trial request progress and pending gym responses.',
        icon: Icons.assignment_turned_in_rounded,
        color: AppColors.accentPurple,
        onTap: onOpenTrials,
      ),
      _DashboardActionData(
        label: 'Workout logs',
        helper: 'History, PRs, volume',
        description: 'See completed sessions, personal records, and volume.',
        icon: Icons.menu_book_rounded,
        color: AppColors.primary,
        onTap: onOpenLogbook,
      ),
      _DashboardActionData(
        label: 'Attendance',
        helper: 'Check-ins and visit history',
        description: 'Review daily check-ins and your recent gym visits.',
        icon: Icons.fact_check_outlined,
        color: AppColors.primaryBright,
        onTap: onOpenAttendance,
      ),
      _DashboardActionData(
        label: 'Settings',
        helper: 'Account, alerts, logout',
        description: 'Manage preferences, notifications, and account actions.',
        icon: Icons.settings_rounded,
        color: AppColors.textSecondary,
        onTap: onOpenSettings,
      ),
    ];
    final focusBanner = hasWarning
        ? _DashboardFocusBannerData(
            eyebrow: 'Membership Alert',
            title: dueAmount > 0
                ? 'Your membership has pending dues'
                : 'Your membership is nearing renewal',
            description: dueAmount > 0
                ? 'Review membership details and clear dues to avoid access issues.'
                : 'Renew early and keep your access uninterrupted.',
            label: 'Open membership',
            icon: Icons.card_membership_rounded,
            color: AppColors.primary,
            onTap: onViewMembership,
          )
        : hasActivePlan
        ? _DashboardFocusBannerData(
            eyebrow: 'Today Focus',
            title: activeWorkoutLabel,
            description: checkedInToday
                ? 'You are checked in. Open your plan and keep momentum going.'
                : 'Your next training session is ready whenever you are.',
            label: 'Open workout',
            icon: Icons.fitness_center_rounded,
            color: AppColors.primaryBright,
            onTap: onOpenWorkout,
          )
        : hasTrainer
        ? _DashboardFocusBannerData(
            eyebrow: 'Coach Support',
            title: 'Your coach channel is open',
            description:
                'Message your trainer for guidance, changes, or workout clarification.',
            label: 'Open chat',
            icon: Icons.chat_bubble_rounded,
            color: AppColors.accentPurple,
            onTap: onMessageTrainer,
          )
        : _DashboardFocusBannerData(
            eyebrow: 'Next Step',
            title: 'Keep your member profile progressing',
            description:
                'Complete your setup, explore gyms, and build a stronger routine.',
            label: 'Open profile',
            icon: Icons.person_rounded,
            color: AppColors.primary,
            onTap: onOpenProfile,
          );
    return RefreshIndicator(
      onRefresh: onRefresh,
      color: const Color(0xFF19D6A6),
      backgroundColor: Colors.white,
      child: _PremiumDashboardBackground(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 104),
          children: [
            RevealOnBuild(
              child: _MemberGreetingHeader(
                firstName: firstName,
                subtitle: 'Ready for today\'s session?',
                unreadNotifications: unreadNotifications,
                onOpenNotifications: onOpenNotifications,
                onOpenSettings: onOpenSettings,
              ),
            ),
            const SizedBox(height: 14),
            RevealOnBuild(
              delay: const Duration(milliseconds: 40),
              child: _PerformanceHeroPanel(
                title: heroTitle,
                subtitle: heroSubtitle,
                badge: heroLabel,
                progress: readinessPercent,
                progressLabel: '${(readinessPercent * 100).round()}%',
                primaryActionLabel: hasGymMembership
                    ? 'Start Workout'
                    : 'Explore Gyms',
                secondaryActionLabel: heroActionLabel,
                onPrimaryAction: hasGymMembership ? onStartWorkout : onFindGyms,
                onSecondaryAction: hasGymMembership ? onShowQr : onOpenProfile,
                chips: [
                  _DashboardChipData(
                    icon: Icons.directions_walk_rounded,
                    label: stepSummary != null
                        ? '${_formatCompactNumber(stepSummary.today)} steps'
                        : 'Steps pending',
                  ),
                  _DashboardChipData(
                    icon: Icons.local_fire_department_rounded,
                    label: workoutStreak > 0
                        ? '$workoutStreak day streak'
                        : 'Start streak',
                  ),
                  _DashboardChipData(
                    icon: hasGymMembership
                        ? Icons.verified_user_rounded
                        : Icons.person_outline_rounded,
                    label: hasGymMembership
                        ? (checkedInToday ? 'Checked in' : 'Access active')
                        : (profileReady ? 'Profile ready' : 'Setup pending'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 65),
              child: _DashboardSection(
                eyebrow: 'Snapshot',
                title: 'Today at a glance',
                child: _MetricGrid(metrics: metricCards),
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 85),
              child: _DashboardSection(
                eyebrow: 'Actions',
                title: 'Featured shortcuts',
                child: _DashboardActionCarousel(actions: dashboardActions),
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 95),
              child: _DashboardSection(
                eyebrow: 'Focus',
                title: 'What deserves attention now',
                child: _DashboardFocusBanner(data: focusBanner),
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 105),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final split = constraints.maxWidth >= 680;
                  final stepCard = StepDashboardWidget(
                    steps: stepSummary,
                    permissionStatus: stepPermissionStatus,
                    loading: stepLoading,
                    statusMessage: stepStatusMessage,
                    onRefresh: () => unawaited(onRefresh()),
                    onRequestPermission: onRequestStepPermission,
                  );
                  final workoutCard = _WorkoutTicket(
                    hasPlan: hasActivePlan,
                    title: hasActivePlan
                        ? activeWorkoutLabel
                        : 'Choose today\'s plan',
                    subtitle: hasActivePlan
                        ? (todayWorkout['goal']?.toString() ??
                              todayWorkout['focus']?.toString() ??
                              'Training plan')
                        : 'Pick a workout from your library',
                    duration: hasActivePlan
                        ? '${todayWorkout['estimated_duration_minutes']?.toString() ?? '30'} min'
                        : 'Flexible',
                    status: checkedInToday
                        ? 'Checked in'
                        : hasActivePlan
                        ? 'Ready'
                        : 'No plan',
                    onOpenWorkout: onOpenWorkout,
                  );

                  if (!split) {
                    return Column(
                      children: [
                        _DashboardSection(
                          eyebrow: 'Movement',
                          title: 'Health and training',
                          child: stepCard,
                        ),
                        const SizedBox(height: 16),
                        _DashboardSection(
                          eyebrow: 'Workout',
                          title: 'Your next session',
                          child: workoutCard,
                        ),
                      ],
                    );
                  }

                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: _DashboardSection(
                          eyebrow: 'Movement',
                          title: 'Health and training',
                          child: stepCard,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: _DashboardSection(
                          eyebrow: 'Workout',
                          title: 'Your next session',
                          child: workoutCard,
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 130),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final split = constraints.maxWidth >= 680;
                  final activityCard = _WeeklyActivityStrip(
                    checkedInToday: checkedInToday,
                    streakDays: workoutStreak,
                    weeklyBars: weeklyBars,
                  );
                  final recentCard = _RecentWorkoutRows(
                    workouts: latestWorkoutItems.take(2).toList(),
                    hasActivePlan: hasActivePlan,
                    onOpenLogbook: onOpenLogbook,
                  );

                  if (!split) {
                    return Column(
                      children: [
                        _DashboardSection(
                          eyebrow: 'Insights',
                          title: 'Weekly consistency',
                          child: activityCard,
                        ),
                        const SizedBox(height: 16),
                        _DashboardSection(
                          eyebrow: 'History',
                          title: 'Recent training',
                          child: recentCard,
                        ),
                      ],
                    );
                  }

                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: _DashboardSection(
                          eyebrow: 'Insights',
                          title: 'Weekly consistency',
                          child: activityCard,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: _DashboardSection(
                          eyebrow: 'History',
                          title: 'Recent training',
                          child: recentCard,
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
            if (hasTrainer || hasGymMembership || isTrialUser) ...[
              const SizedBox(height: 16),
              RevealOnBuild(
                delay: const Duration(milliseconds: 150),
                child: _DashboardSection(
                  eyebrow: 'Support',
                  title: hasTrainer ? 'Coach connection' : 'Next unlock',
                  child: _CoachMiniPanel(
                    hasTrainer: hasTrainer,
                    trainerName: assignedTrainerName,
                    workoutLabel: activeWorkoutLabel,
                    onTap: hasTrainer ? onMessageTrainer : onFindGyms,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  List<double> _weeklyActivityBars(List<Map<String, dynamic>> sessions) {
    final today = DateTime.now();
    final normalizedToday = DateTime(today.year, today.month, today.day);
    return List<double>.generate(7, (index) {
      final day = normalizedToday.subtract(Duration(days: 6 - index));
      final count = sessions.where((session) {
        final date = DateTime.tryParse(
          session['session_date']?.toString() ?? '',
        );
        if (date == null) {
          return false;
        }
        return date.year == day.year &&
            date.month == day.month &&
            date.day == day.day;
      }).length;
      return count == 0 ? 0.18 : (count / 3).clamp(0.22, 1.0);
    });
  }

  List<Map<String, Object>> _latestWorkoutItems(
    List<Map<String, dynamic>> plans,
    List<Map<String, dynamic>> history,
  ) {
    if (plans.isNotEmpty) {
      return plans.take(3).map((plan) {
        final name = plan['name']?.toString() ?? 'Workout';
        final duration = plan['estimated_duration_minutes']?.toString();
        final focus =
            plan['focus']?.toString() ??
            plan['goal']?.toString() ??
            'Training plan';
        final progressRaw = (plan['completion_ratio'] as num?)?.toDouble();
        return <String, Object>{
          'title': name,
          'meta':
              '${focus.isEmpty ? 'Workout plan' : focus} • ${duration ?? '30'} min',
          'progress': progressRaw == null ? 0.16 : progressRaw.clamp(0.08, 1.0),
        };
      }).toList();
    }

    return history.take(3).map((session) {
      final name =
          session['plan_name']?.toString() ??
          session['workout_name']?.toString() ??
          'Workout session';
      final calories = session['estimated_kcal']?.toString();
      final duration = session['duration_minutes']?.toString();
      final completed =
          (session['status']?.toString() ?? '').toLowerCase() == 'completed';
      return <String, Object>{
        'title': name,
        'meta':
            '${calories ?? '180'} Calories Burn | ${duration ?? '25'} minutes',
        'progress': completed ? 1.0 : 0.4,
      };
    }).toList();
  }

  int _calculateWorkoutStreak(List<Map<String, dynamic>> sessions) {
    final dates =
        sessions
            .map(
              (session) =>
                  DateTime.tryParse(session['session_date']?.toString() ?? ''),
            )
            .whereType<DateTime>()
            .map((date) => DateTime(date.year, date.month, date.day))
            .toSet()
            .toList()
          ..sort((a, b) => b.compareTo(a));
    if (dates.isEmpty) {
      return 0;
    }

    var streak = 1;
    for (var index = 1; index < dates.length; index++) {
      final diff = dates[index - 1].difference(dates[index]).inDays;
      if (diff == 1) {
        streak += 1;
        continue;
      }
      if (diff > 1) {
        break;
      }
    }
    return streak;
  }

  bool _isExpiringSoon(String? date) {
    final parsed = DateTime.tryParse(date ?? '');
    if (parsed == null) {
      return false;
    }
    final now = DateTime.now();
    final diff = parsed
        .difference(DateTime(now.year, now.month, now.day))
        .inDays;
    return diff >= 0 && diff <= 7;
  }

  String _formatVolume(double value) {
    if (value <= 0) {
      return '0 kg';
    }
    return '${value.toStringAsFixed(value.truncateToDouble() == value ? 0 : 1)} kg';
  }

  String _formatCompactNumber(num value) {
    if (value >= 1000000) {
      return '${(value / 1000000).toStringAsFixed(value >= 10000000 ? 0 : 1)}M';
    }
    if (value >= 1000) {
      return '${(value / 1000).toStringAsFixed(value >= 10000 ? 0 : 1)}k';
    }
    return value.toStringAsFixed(0);
  }
}

class _DashboardChipData {
  const _DashboardChipData({required this.icon, required this.label});

  final IconData icon;
  final String label;
}

class _DashboardMetricData {
  const _DashboardMetricData({
    required this.label,
    required this.value,
    required this.helper,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final String helper;
  final IconData icon;
  final Color color;
}

class _DashboardActionData {
  const _DashboardActionData({
    required this.label,
    required this.helper,
    required this.description,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String label;
  final String helper;
  final String description;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;
}

class _DashboardFocusBannerData {
  const _DashboardFocusBannerData({
    required this.eyebrow,
    required this.title,
    required this.description,
    required this.label,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String eyebrow;
  final String title;
  final String description;
  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;
}

class _DashboardSection extends StatelessWidget {
  const _DashboardSection({
    required this.eyebrow,
    required this.title,
    required this.child,
  });

  final String eyebrow;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 2, bottom: 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                eyebrow.toUpperCase(),
                style: theme.textTheme.labelSmall?.copyWith(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 0.9,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ),
        child,
      ],
    );
  }
}

class _PremiumDashboardBackground extends StatefulWidget {
  const _PremiumDashboardBackground({required this.child});

  final Widget child;

  @override
  State<_PremiumDashboardBackground> createState() =>
      _PremiumDashboardBackgroundState();
}

class _PremiumDashboardBackgroundState extends State<_PremiumDashboardBackground>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 14),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final phase = _controller.value * math.pi * 2;
        final topRightDx = math.sin(phase) * 10;
        final topRightDy = math.cos(phase) * 8;
        final leftDx = math.cos(phase * 0.8) * 8;
        final leftDy = math.sin(phase * 0.8) * 10;
        final bottomDx = math.sin(phase * 0.6) * 12;
        final bottomDy = math.cos(phase * 0.6) * 9;

        return Stack(
          children: [
            Container(
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    const Color(0xFFF8FAFC),
                    const Color(0xFFF1F5F9).withValues(alpha: 0.92),
                  ],
                ),
              ),
            ),
            Positioned(
              top: -96 + topRightDy,
              right: -82 + topRightDx,
              child: const _DashboardGlowOrb(
                size: 220,
                color: AppColors.primary,
                opacity: 0.08,
              ),
            ),
            Positioned(
              top: 280 + leftDy,
              left: -110 + leftDx,
              child: const _DashboardGlowOrb(
                size: 210,
                color: AppColors.primaryBright,
                opacity: 0.04,
              ),
            ),
            Positioned(
              bottom: 140 + bottomDy,
              right: -120 + bottomDx,
              child: const _DashboardGlowOrb(
                size: 230,
                color: AppColors.accentPurple,
                opacity: 0.035,
              ),
            ),
            widget.child,
          ],
        );
      },
    );
  }
}

class _DashboardGlowOrb extends StatelessWidget {
  const _DashboardGlowOrb({
    required this.size,
    required this.color,
    required this.opacity,
  });

  final double size;
  final Color color;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [
              color.withValues(alpha: opacity),
              color.withValues(alpha: 0),
            ],
          ),
        ),
      ),
    );
  }
}

class _MemberGreetingHeader extends StatelessWidget {
  const _MemberGreetingHeader({
    required this.firstName,
    required this.subtitle,
    required this.unreadNotifications,
    required this.onOpenNotifications,
    required this.onOpenSettings,
  });

  final String firstName;
  final String subtitle;
  final int unreadNotifications;
  final VoidCallback onOpenNotifications;
  final VoidCallback onOpenSettings;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Hi, $firstName',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.7,
                    height: 1.02,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          _HeaderAction(
            icon: Icons.notifications_none_rounded,
            count: unreadNotifications,
            onTap: onOpenNotifications,
          ),
          const SizedBox(width: 10),
          _HeaderAction(icon: Icons.settings_rounded, onTap: onOpenSettings),
        ],
      ),
    );
  }
}

class _HeaderAction extends StatelessWidget {
  const _HeaderAction({
    required this.icon,
    required this.onTap,
    this.count = 0,
  });

  final IconData icon;
  final VoidCallback onTap;
  final int count;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.92),
              boxShadow: [
                BoxShadow(
                  color: AppColors.shadow.withValues(alpha: 0.10),
                  blurRadius: 14,
                  offset: const Offset(0, 8),
                ),
              ],
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.stroke.withValues(alpha: 0.5)),
            ),
            child: Icon(icon, color: AppColors.textPrimary, size: 21),
          ),
          if (count > 0)
            Positioned(
              top: -4,
              right: -4,
              child: Container(
                constraints: const BoxConstraints(minWidth: 19, minHeight: 19),
                padding: const EdgeInsets.symmetric(horizontal: 5),
                decoration: BoxDecoration(
                  color: AppColors.primary,
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: Colors.white, width: 2),
                ),
                alignment: Alignment.center,
                child: Text(
                  count > 9 ? '9+' : '$count',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    height: 1,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _PerformanceHeroPanel extends StatelessWidget {
  const _PerformanceHeroPanel({
    required this.title,
    required this.subtitle,
    required this.badge,
    required this.progress,
    required this.progressLabel,
    required this.primaryActionLabel,
    required this.secondaryActionLabel,
    required this.onPrimaryAction,
    required this.onSecondaryAction,
    required this.chips,
  });

  final String title;
  final String subtitle;
  final String badge;
  final double progress;
  final String progressLabel;
  final String primaryActionLabel;
  final String secondaryActionLabel;
  final VoidCallback onPrimaryAction;
  final VoidCallback onSecondaryAction;
  final List<_DashboardChipData> chips;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ClipRRect(
      borderRadius: BorderRadius.circular(34),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Colors.white.withValues(alpha: 0.98),
              const Color(0xFFF6FBFF),
              const Color(0xFFF8FAFC),
            ],
          ),
          border: Border.all(color: AppColors.stroke.withValues(alpha: 0.8)),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.06),
              blurRadius: 20,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Stack(
          children: [
            Positioned(
              top: -54,
              left: -28,
              child: Container(
                width: 134,
                height: 134,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.34),
                ),
              ),
            ),
            Positioned(
              top: -34,
              right: -28,
              child: const _DashboardGlowOrb(
                size: 154,
                color: AppColors.primary,
                opacity: 0.14,
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final compact = constraints.maxWidth < 360;
                  final ringSize = compact ? 86.0 : 104.0;
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 7,
                                  ),
                                  decoration: BoxDecoration(
                                    color: Colors.white.withValues(alpha: 0.68),
                                    borderRadius: BorderRadius.circular(999),
                                    border: Border.all(
                                      color: AppColors.stroke.withValues(alpha: 0.5),
                                    ),
                                  ),
                                  child: Text(
                                    badge.toUpperCase(),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: theme.textTheme.labelSmall?.copyWith(
                                      color: AppColors.primary,
                                      fontWeight: FontWeight.w900,
                                      letterSpacing: 0.9,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  title,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.displaySmall?.copyWith(
                                    color: AppColors.textPrimary,
                                    fontWeight: FontWeight.w900,
                                    height: 0.95,
                                    letterSpacing: -1.4,
                                    fontSize: compact ? 28 : null,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  subtitle,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.labelSmall?.copyWith(
                                    color: AppColors.textSecondary,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 14),
                          _HeroRing(
                            progress: progress,
                            label: progressLabel,
                            size: ringSize,
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: chips
                            .map((chip) => _HeroChip(data: chip))
                            .toList(),
                      ),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          Expanded(
                            child: _DashboardPillButton(
                              label: primaryActionLabel,
                              onTap: onPrimaryAction,
                              filled: true,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _DashboardPillButton(
                              label: secondaryActionLabel,
                              onTap: onSecondaryAction,
                              filled: false,
                            ),
                          ),
                        ],
                      ),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HeroRing extends StatelessWidget {
  const _HeroRing({
    required this.progress,
    required this.label,
    required this.size,
  });

  final double progress;
  final String label;
  final double size;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: SizedBox(
        width: size,
        height: size,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Container(
              width: size,
              height: size,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.78),
                border: Border.all(color: AppColors.stroke.withValues(alpha: 0.7)),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.10),
                    blurRadius: 16,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
            ),
            TweenAnimationBuilder<double>(
              tween: Tween<double>(begin: 0, end: progress),
              duration: const Duration(milliseconds: 850),
              curve: Curves.easeOutCubic,
              builder: (context, value, _) => SizedBox(
                width: size - 26,
                height: size - 26,
                child: CircularProgressIndicator(
                  value: value,
                  strokeWidth: size < 100 ? 8 : 10,
                  backgroundColor: AppColors.stroke.withValues(alpha: 0.8),
                  valueColor: const AlwaysStoppedAnimation<Color>(AppColors.primary),
                ),
              ),
            ),
            Text(
              label,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w900,
                fontSize: size < 100 ? 18 : null,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HeroChip extends StatelessWidget {
  const _HeroChip({required this.data});

  final _DashboardChipData data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.4)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(data.icon, size: 14, color: AppColors.primary),
          const SizedBox(width: 6),
          Text(
            data.label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _MetricRail extends StatelessWidget {
  const _MetricRail({required this.metrics});

  final List<_DashboardMetricData> metrics;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 70,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        clipBehavior: Clip.none,
        itemCount: metrics.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) => _MetricRailItem(data: metrics[index]),
      ),
    );
  }
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.metrics});

  final List<_DashboardMetricData> metrics;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 620 ? 4 : 2;
        final spacing = constraints.maxWidth >= 620 ? 12.0 : 10.0;
        final tileWidth =
            (constraints.maxWidth - (spacing * (columns - 1))) / columns;

        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final entry in metrics.asMap().entries)
              SizedBox(
                width: tileWidth,
                child: RevealOnBuild(
                  delay: Duration(milliseconds: 30 * entry.key),
                  offset: const Offset(0, 0.06),
                  duration: const Duration(milliseconds: 420),
                  child: _MetricGridItem(data: entry.value),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _MetricGridItem extends StatelessWidget {
  const _MetricGridItem({required this.data});

  final _DashboardMetricData data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.75)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.08),
            blurRadius: 12,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: data.color.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: data.color.withValues(alpha: 0.14)),
            ),
            child: Icon(data.icon, color: data.color, size: 20),
          ),
          const SizedBox(height: 18),
          Text(
            data.value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            data.label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            data.helper,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricRailItem extends StatelessWidget {
  const _MetricRailItem({required this.data});

  final _DashboardMetricData data;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 126,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            data.color.withValues(alpha: 0.14),
            Colors.white.withValues(alpha: 0.86),
          ],
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: data.color.withValues(alpha: 0.12),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: data.color.withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(15),
            ),
            child: Icon(data.icon, color: data.color, size: 18),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  data.value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: const Color(0xFF18202A),
                    fontWeight: FontWeight.w900,
                    fontSize: 13,
                  ),
                ),
                Text(
                  data.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: const Color(0xFF758092),
                    fontWeight: FontWeight.w700,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DashboardActionCarousel extends StatefulWidget {
  const _DashboardActionCarousel({required this.actions});

  final List<_DashboardActionData> actions;

  @override
  State<_DashboardActionCarousel> createState() => _DashboardActionCarouselState();
}

class _DashboardActionCarouselState extends State<_DashboardActionCarousel> {
  late final PageController _controller;
  double _page = 0;

  @override
  void initState() {
    super.initState();
    _controller = PageController(viewportFraction: 0.78)
      ..addListener(() {
        if (_controller.hasClients) {
          setState(() => _page = _controller.page ?? 0);
        }
      });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          height: 214,
          child: PageView.builder(
            controller: _controller,
            physics: const BouncingScrollPhysics(),
            itemCount: widget.actions.length,
            itemBuilder: (context, index) {
              final delta = (_page - index).abs().clamp(0.0, 1.0);
              final scale = 1 - (delta * 0.12);
              final opacity = 1 - (delta * 0.22);
              final lift = 18 * delta;

              return Transform.translate(
                offset: Offset(0, lift),
                child: Transform.scale(
                  scale: scale,
                  child: Opacity(
                    opacity: opacity,
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 6),
                      child: RevealOnBuild(
                        delay: Duration(milliseconds: 35 * index),
                        offset: const Offset(0.04, 0.07),
                        duration: const Duration(milliseconds: 460),
                        child: _DashboardActionFeaturedCard(
                          data: widget.actions[index],
                        ),
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
        const SizedBox(height: 12),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(widget.actions.length, (index) {
            final active = (_page.round() == index);
            return AnimatedContainer(
              duration: const Duration(milliseconds: 220),
              curve: Curves.easeOutCubic,
              width: active ? 18 : 6,
              height: 6,
              margin: const EdgeInsets.symmetric(horizontal: 3),
              decoration: BoxDecoration(
                color: active ? AppColors.primary : AppColors.strokeStrong,
                borderRadius: BorderRadius.circular(999),
              ),
            );
          }),
        ),
      ],
    );
  }
}

class _DashboardActionFeaturedCard extends StatelessWidget {
  const _DashboardActionFeaturedCard({required this.data});

  final _DashboardActionData data;

  @override
  Widget build(BuildContext context) {
    const accentColor = AppColors.primary;

    return InkWell(
      onTap: data.onTap,
      borderRadius: BorderRadius.circular(28),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: AppColors.stroke.withValues(alpha: 0.72)),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.12),
              blurRadius: 24,
              offset: const Offset(0, 16),
            ),
            BoxShadow(
              color: accentColor.withValues(alpha: 0.08),
              blurRadius: 22,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(28),
          child: Stack(
            children: [
              Container(
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.96),
                ),
              ),
              Positioned(
                top: -8,
                right: -6,
                child: Transform.rotate(
                  angle: -0.24,
                  child: Container(
                    width: 88,
                    height: 88,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(28),
                      color: const Color(0xFFF8FAFC),
                      border: Border.all(
                        color: accentColor.withValues(alpha: 0.08),
                      ),
                    ),
                  ),
                ),
              ),
              Positioned(
                top: 18,
                right: 18,
                child: Icon(
                  Icons.north_east_rounded,
                  color: accentColor.withValues(alpha: 0.70),
                  size: 20,
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Transform.translate(
                          offset: const Offset(0, -2),
                          child: Container(
                            width: 50,
                            height: 50,
                            decoration: BoxDecoration(
                              color: accentColor.withValues(alpha: 0.10),
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(
                                color: accentColor.withValues(alpha: 0.14),
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: accentColor.withValues(alpha: 0.08),
                                  blurRadius: 12,
                                  offset: const Offset(0, 8),
                                ),
                              ],
                            ),
                            child: Icon(data.icon, color: accentColor, size: 22),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            data.helper,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                                  color: AppColors.textSecondary,
                                  fontWeight: FontWeight.w800,
                                  height: 1.15,
                                ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 22),
                    Text(
                      data.label,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            color: AppColors.textPrimary,
                            fontWeight: FontWeight.w900,
                            height: 1.04,
                          ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      data.description,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: AppColors.textSecondary,
                            fontWeight: FontWeight.w700,
                            height: 1.35,
                          ),
                    ),
                    const Spacer(),
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          'Open',
                          style: Theme.of(context).textTheme.labelLarge?.copyWith(
                                color: accentColor,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                        const SizedBox(width: 6),
                        Icon(
                          Icons.arrow_forward_rounded,
                          size: 18,
                          color: accentColor,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DashboardFocusBanner extends StatelessWidget {
  const _DashboardFocusBanner({required this.data});

  final _DashboardFocusBannerData data;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: data.onTap,
      borderRadius: BorderRadius.circular(28),
      child: Container(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              AppColors.surface,
              AppColors.surfaceSoft,
            ],
          ),
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: AppColors.stroke.withValues(alpha: 0.72)),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.08),
              blurRadius: 16,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: AppColors.primaryBright.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: AppColors.primaryBright.withValues(alpha: 0.14),
                ),
              ),
              child: Icon(data.icon, color: AppColors.primaryBright, size: 24),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    data.eyebrow.toUpperCase(),
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: AppColors.primaryBright,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 0.7,
                        ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    data.title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.w900,
                        ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    data.description,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                          fontWeight: FontWeight.w700,
                          height: 1.35,
                        ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  data.label,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: AppColors.primaryBright,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 6),
                Icon(
                  Icons.arrow_forward_rounded,
                  color: AppColors.primaryBright,
                  size: 18,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _WorkoutTicket extends StatelessWidget {
  const _WorkoutTicket({
    required this.hasPlan,
    required this.title,
    required this.subtitle,
    required this.duration,
    required this.status,
    required this.onOpenWorkout,
  });

  final bool hasPlan;
  final String title;
  final String subtitle;
  final String duration;
  final String status;
  final VoidCallback onOpenWorkout;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.90),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.75)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: IntrinsicHeight(
        child: Row(
          children: [
            Container(
              width: 7,
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [AppColors.primaryBright, AppColors.primary],
                ),
                borderRadius: BorderRadius.horizontal(
                  left: Radius.circular(28),
                ),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          'TODAY WORKOUT',
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: AppColors.primary,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 1.1,
                          ),
                        ),
                        const Spacer(),
                        _TicketPill(label: status),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                        height: 1.02,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _TicketPill(label: subtitle),
                        _TicketPill(label: duration),
                      ],
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      height: 42,
                      child: _DashboardPillButton(
                        label: hasPlan ? 'Open Workout' : 'Choose Plan',
                        onTap: onOpenWorkout,
                        filled: true,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TicketPill extends StatelessWidget {
  const _TicketPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.55)),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: AppColors.textSecondary,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _WeeklyActivityStrip extends StatelessWidget {
  const _WeeklyActivityStrip({
    required this.checkedInToday,
    required this.streakDays,
    required this.weeklyBars,
  });

  final bool checkedInToday;
  final int streakDays;
  final List<double> weeklyBars;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.88),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.75)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Weekly activity',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              _TicketPill(
                label: checkedInToday
                    ? 'Checked in'
                    : streakDays > 0
                    ? '$streakDays day streak'
                    : '7 days',
              ),
            ],
          ),
          const SizedBox(height: 16),
          SizedBox(
            height: 106,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: List.generate(weeklyBars.length, (index) {
                final bar = weeklyBars[index];
                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Expanded(
                          child: Align(
                            alignment: Alignment.bottomCenter,
                            child: TweenAnimationBuilder<double>(
                              tween: Tween<double>(begin: 0.16, end: bar),
                              duration: Duration(
                                milliseconds: 360 + (index * 70),
                              ),
                              curve: Curves.easeOutCubic,
                              builder: (context, value, _) =>
                                  FractionallySizedBox(
                                    heightFactor: value,
                                    child: Container(
                                      decoration: BoxDecoration(
                                        gradient: LinearGradient(
                                          begin: Alignment.bottomCenter,
                                          end: Alignment.topCenter,
                                          colors: index == weeklyBars.length - 1
                                              ? const [
                                                  AppColors.primaryBright,
                                                  AppColors.primary,
                                                ]
                                              : [
                                                  AppColors.strokeStrong,
                                                  AppColors.stroke,
                                                ],
                                        ),
                                        borderRadius: BorderRadius.circular(
                                          999,
                                        ),
                                      ),
                                    ),
                                  ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          const ['S', 'M', 'T', 'W', 'T', 'F', 'S'][index],
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: AppColors.textMuted,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }),
            ),
          ),
        ],
      ),
    );
  }
}

class _RecentWorkoutRows extends StatelessWidget {
  const _RecentWorkoutRows({
    required this.workouts,
    required this.hasActivePlan,
    required this.onOpenLogbook,
  });

  final List<Map<String, Object>> workouts;
  final bool hasActivePlan;
  final VoidCallback onOpenLogbook;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.88),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.75)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Recent workouts',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              TextButton(
                onPressed: onOpenLogbook,
                child: Text(
                  'Open logs',
                  style: theme.textTheme.labelLarge?.copyWith(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          if (workouts.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 10, bottom: 4),
              child: Row(
                children: [
                  const Icon(
                    Icons.fitness_center_rounded,
                    color: AppColors.primary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      hasActivePlan
                          ? 'Open your plan and complete your first session.'
                          : 'Choose a workout to start building history.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            )
          else
            ...workouts.asMap().entries.map(
              (entry) => _RecentWorkoutRow(
                title: entry.value['title']?.toString() ?? 'Workout',
                meta: entry.value['meta']?.toString() ?? '',
                progress: (entry.value['progress'] as num?)?.toDouble() ?? 0,
                showDivider: entry.key < workouts.length - 1,
                onTap: onOpenLogbook,
              ),
            ),
        ],
      ),
    );
  }
}

class _RecentWorkoutRow extends StatelessWidget {
  const _RecentWorkoutRow({
    required this.title,
    required this.meta,
    required this.progress,
    required this.showDivider,
    required this.onTap,
  });

  final String title;
  final String meta;
  final double progress;
  final bool showDivider;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        AppColors.primary.withValues(alpha: 0.10),
                        AppColors.primaryBright.withValues(alpha: 0.06),
                      ],
                    ),
                    borderRadius: BorderRadius.circular(17),
                  ),
                  child: const Icon(
                    Icons.bolt_rounded,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        meta,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(
                              color: AppColors.textSecondary,
                              fontWeight: FontWeight.w700,
                            ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                SizedBox(
                  width: 42,
                  height: 42,
                  child: CircularProgressIndicator(
                    value: progress.clamp(0.0, 1.0),
                    strokeWidth: 4,
                    backgroundColor: AppColors.stroke,
                    valueColor: const AlwaysStoppedAnimation<Color>(AppColors.primary),
                  ),
                ),
              ],
            ),
          ),
          if (showDivider) Divider(height: 1, color: AppColors.stroke),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _MemberUtilityRail extends StatelessWidget {
  const _MemberUtilityRail({
    required this.onOpenLogbook,
    required this.onOpenAttendance,
    required this.onOpenSettings,
  });

  final VoidCallback onOpenLogbook;
  final VoidCallback onOpenAttendance;
  final VoidCallback onOpenSettings;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 360;
        final children = [
          _MemberUtilityPill(
            icon: Icons.menu_book_rounded,
            title: 'Workout logs',
            subtitle: 'History, PRs, volume',
            colors: const [Color(0xFF19D6A6), Color(0xFF67A7FF)],
            onTap: onOpenLogbook,
          ),
          _MemberUtilityPill(
            icon: Icons.fact_check_outlined,
            title: 'Attendance',
            subtitle: 'Check-ins, QR visits',
            colors: const [Color(0xFF67A7FF), Color(0xFF9A7BFF)],
            onTap: onOpenAttendance,
          ),
          _MemberUtilityPill(
            icon: Icons.settings_rounded,
            title: 'Settings',
            subtitle: 'Account, alerts, logout',
            colors: const [Color(0xFF9A7BFF), Color(0xFFFF8D5C)],
            onTap: onOpenSettings,
          ),
        ];

        if (compact) {
          return Column(
            children: [
              for (var index = 0; index < children.length; index++) ...[
                if (index > 0) const SizedBox(height: 10),
                children[index],
              ],
            ],
          );
        }

        return SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          physics: const BouncingScrollPhysics(),
          child: Row(
            children: [
              for (var index = 0; index < children.length; index++) ...[
                if (index > 0) const SizedBox(width: 12),
                SizedBox(
                  width: (constraints.maxWidth - 12) / 2,
                  child: children[index],
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _MemberUtilityPill extends StatelessWidget {
  const _MemberUtilityPill({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.colors,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final List<Color> colors;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 10, 14, 10),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.88),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: AppColors.stroke.withValues(alpha: 0.72)),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.08),
              blurRadius: 12,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: colors),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: Colors.white, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }
}

class _CoachMiniPanel extends StatelessWidget {
  const _CoachMiniPanel({
    required this.hasTrainer,
    required this.trainerName,
    required this.workoutLabel,
    required this.onTap,
  });

  final bool hasTrainer;
  final String trainerName;
  final String workoutLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.90),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: AppColors.stroke.withValues(alpha: 0.72)),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.08),
              blurRadius: 12,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: hasTrainer
                      ? const [AppColors.primary, AppColors.accentPurple]
                      : [
                          AppColors.primary.withValues(alpha: 0.10),
                          AppColors.primaryBright.withValues(alpha: 0.06),
                        ],
                ),
                shape: BoxShape.circle,
              ),
              child: Icon(
                hasTrainer ? Icons.person_rounded : Icons.lock_rounded,
                color: hasTrainer ? Colors.white : AppColors.primary,
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    hasTrainer ? trainerName : 'Coach locked',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  Text(
                    hasTrainer
                        ? workoutLabel
                        : 'Join a gym to unlock trainer support',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Icon(
              hasTrainer ? Icons.chat_bubble_rounded : Icons.north_east_rounded,
              color: AppColors.primary,
              size: 20,
            ),
          ],
        ),
      ),
    );
  }
}

class _DashboardPillButton extends StatelessWidget {
  const _DashboardPillButton({
    required this.label,
    required this.onTap,
    required this.filled,
  });

  final String label;
  final VoidCallback onTap;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        height: 46,
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          gradient: filled
              ? const LinearGradient(
                  colors: [AppColors.primaryBright, AppColors.primary],
                )
              : null,
          color: filled ? null : Colors.white.withValues(alpha: 0.70),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: filled ? Colors.transparent : AppColors.stroke.withValues(alpha: 0.72),
          ),
        ),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
            color: filled ? Colors.white : AppColors.textPrimary,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

// ignore: unused_element
class _DashboardTopBar extends StatelessWidget {
  const _DashboardTopBar({
    required this.firstName,
    required this.userLabel,
    required this.unreadNotifications,
    required this.onOpenNotifications,
    required this.onOpenProfile,
  });

  final String firstName;
  final String userLabel;
  final int unreadNotifications;
  final VoidCallback onOpenNotifications;
  final VoidCallback onOpenProfile;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: 4, bottom: 16),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Hi, $firstName',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Ready to move today',
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.auto_awesome_rounded,
                  size: 14,
                  color: AppColors.primary,
                ),
                const SizedBox(width: 6),
                Text(
                  userLabel,
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          _DashboardIconButton(
            icon: Icons.notifications_none_rounded,
            count: unreadNotifications,
            onTap: onOpenNotifications,
          ),
          const SizedBox(width: 10),
          InkWell(
            onTap: onOpenProfile,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppColors.surface, AppColors.surfaceStrong],
                ),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.stroke),
              ),
              child: const Icon(
                Icons.person_outline_rounded,
                color: AppColors.textPrimary,
                size: 22,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _DashboardIconButton extends StatelessWidget {
  const _DashboardIconButton({
    required this.icon,
    required this.onTap,
    this.count = 0,
  });

  final IconData icon;
  final VoidCallback onTap;
  final int count;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [AppColors.surface, AppColors.surfaceStrong],
              ),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Icon(icon, color: AppColors.textPrimary, size: 22),
          ),
          if (count > 0)
            Positioned(
              top: -3,
              right: -3,
              child: Container(
                constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                padding: const EdgeInsets.symmetric(horizontal: 5),
                decoration: BoxDecoration(
                  color: AppColors.accentNeon,
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: AppColors.surface, width: 2),
                ),
                alignment: Alignment.center,
                child: Text(
                  count > 9 ? '9+' : '$count',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    height: 1,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _DashboardMetricGrid extends StatelessWidget {
  const _DashboardMetricGrid({required this.metrics});

  final List<_DashboardMetricData> metrics;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth < 680 ? 2 : 4;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: metrics.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisExtent: 112,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
          ),
          itemBuilder: (context, index) =>
              _DashboardMetricCard(data: metrics[index]),
        );
      },
    );
  }
}

// ignore: unused_element
class _DashboardMetricCard extends StatelessWidget {
  const _DashboardMetricCard({required this.data});

  final _DashboardMetricData data;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return PremiumCard(
      padding: const EdgeInsets.all(14),
      glowColor: data.color,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: data.color.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(data.icon, color: data.color, size: 18),
              ),
              const Spacer(),
              Container(
                width: 7,
                height: 7,
                decoration: BoxDecoration(
                  color: data.color,
                  shape: BoxShape.circle,
                ),
              ),
            ],
          ),
          const Spacer(),
          Text(
            data.value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            data.label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.labelMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w700,
            ),
          ),
          Text(
            data.helper,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.labelSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _DashboardHeroCard extends StatelessWidget {
  const _DashboardHeroCard({
    required this.title,
    required this.subtitle,
    required this.badge,
    required this.progress,
    required this.progressLabel,
    required this.primaryActionLabel,
    required this.secondaryActionLabel,
    required this.onPrimaryAction,
    required this.onSecondaryAction,
    required this.chips,
  });

  final String title;
  final String subtitle;
  final String badge;
  final double progress;
  final String progressLabel;
  final String primaryActionLabel;
  final String secondaryActionLabel;
  final VoidCallback onPrimaryAction;
  final VoidCallback onSecondaryAction;
  final List<_DashboardChipData> chips;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 420;
        final ultraCompact = constraints.maxWidth < 360;
        final radius = compact ? 28.0 : 34.0;
        final padding = compact ? 18.0 : 22.0;
        final titleStyle =
            (compact
                    ? theme.textTheme.titleLarge
                    : theme.textTheme.headlineSmall)
                ?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  height: 1.08,
                );
        final subtitleStyle =
            (compact ? theme.textTheme.bodySmall : theme.textTheme.bodyMedium)
                ?.copyWith(
                  color: Colors.white.withValues(alpha: 0.82),
                  fontWeight: FontWeight.w500,
                );

        return Container(
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [
                AppColors.primaryBright,
                AppColors.primary,
                AppColors.accentPurple,
              ],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(radius),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.18),
                blurRadius: compact ? 22 : 28,
                offset: Offset(0, compact ? 12 : 16),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(radius),
            child: Stack(
              children: [
                Positioned(
                  top: compact ? -28 : -34,
                  right: compact ? -18 : -10,
                  child: Container(
                    width: compact ? 132 : 170,
                    height: compact ? 132 : 170,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white.withValues(alpha: 0.10),
                    ),
                  ),
                ),
                Positioned(
                  bottom: compact ? -36 : -44,
                  left: compact ? -34 : -24,
                  child: Container(
                    width: compact ? 120 : 140,
                    height: compact ? 120 : 140,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white.withValues(alpha: 0.08),
                    ),
                  ),
                ),
                Padding(
                  padding: EdgeInsets.all(padding),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: EdgeInsets.symmetric(
                          horizontal: compact ? 10 : 12,
                          vertical: compact ? 6 : 7,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.18),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.14),
                          ),
                        ),
                        child: Text(
                          badge,
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      SizedBox(height: compact ? 12 : 16),
                      Builder(
                        builder: (context) {
                          final details = Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(title, style: titleStyle),
                              SizedBox(height: compact ? 8 : 10),
                              Text(subtitle, style: subtitleStyle),
                              SizedBox(height: compact ? 14 : 18),
                              Wrap(
                                spacing: compact ? 8 : 10,
                                runSpacing: compact ? 8 : 10,
                                children: chips
                                    .map(
                                      (chip) => _DashboardHeroChip(
                                        data: chip,
                                        compact: compact,
                                      ),
                                    )
                                    .toList(),
                              ),
                              SizedBox(height: compact ? 14 : 18),
                              ultraCompact
                                  ? Column(
                                      children: [
                                        SizedBox(
                                          width: double.infinity,
                                          child: _ReferenceMiniButton(
                                            title: primaryActionLabel,
                                            onPressed: onPrimaryAction,
                                          ),
                                        ),
                                        const SizedBox(height: 10),
                                        SizedBox(
                                          width: double.infinity,
                                          child: _ReferenceMiniButton(
                                            title: secondaryActionLabel,
                                            secondary: true,
                                            onPressed: onSecondaryAction,
                                          ),
                                        ),
                                      ],
                                    )
                                  : Row(
                                      children: [
                                        Expanded(
                                          child: _ReferenceMiniButton(
                                            title: primaryActionLabel,
                                            onPressed: onPrimaryAction,
                                          ),
                                        ),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: _ReferenceMiniButton(
                                            title: secondaryActionLabel,
                                            secondary: true,
                                            onPressed: onSecondaryAction,
                                          ),
                                        ),
                                      ],
                                    ),
                            ],
                          );
                          final orb = _DashboardHeroProgressOrb(
                            progress: progress,
                            progressLabel: progressLabel,
                            compact: compact,
                          );

                          if (compact) {
                            return Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                details,
                                const SizedBox(height: 16),
                                Align(
                                  alignment: Alignment.centerRight,
                                  child: orb,
                                ),
                              ],
                            );
                          }

                          return Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(child: details),
                              const SizedBox(width: 18),
                              orb,
                            ],
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

// ignore: unused_element
class _DashboardHeroChip extends StatelessWidget {
  const _DashboardHeroChip({required this.data, required this.compact});

  final _DashboardChipData data;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 10 : 12,
        vertical: compact ? 8 : 9,
      ),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.13),
        borderRadius: BorderRadius.circular(compact ? 14 : 16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(data.icon, size: compact ? 14 : 15, color: Colors.white),
          SizedBox(width: compact ? 6 : 8),
          Text(
            data.label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _DashboardHeroProgressOrb extends StatelessWidget {
  const _DashboardHeroProgressOrb({
    required this.progress,
    required this.progressLabel,
    required this.compact,
  });

  final double progress;
  final String progressLabel;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final outerSize = compact ? 108.0 : 124.0;
    final innerSize = compact ? 86.0 : 100.0;
    return Container(
      width: outerSize,
      height: outerSize,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: 0.14),
        border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          TweenAnimationBuilder<double>(
            tween: Tween<double>(begin: 0, end: progress),
            duration: const Duration(milliseconds: 900),
            curve: Curves.easeOutCubic,
            builder: (context, value, _) => SizedBox(
              width: innerSize,
              height: innerSize,
              child: CircularProgressIndicator(
                value: value,
                strokeWidth: compact ? 8 : 10,
                backgroundColor: Colors.white.withValues(alpha: 0.22),
                valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
              ),
            ),
          ),
          Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                progressLabel,
                style:
                    (compact
                            ? theme.textTheme.titleLarge
                            : theme.textTheme.headlineSmall)
                        ?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                        ),
              ),
              Text(
                'readiness',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.82),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _DashboardSectionHeader extends StatelessWidget {
  const _DashboardSectionHeader({
    required this.title,
    required this.subtitle,
    // ignore: unused_element_parameter
    this.trailingLabel,
    // ignore: unused_element_parameter
    this.onTrailingTap,
  });

  final String title;
  final String subtitle;
  final String? trailingLabel;
  final VoidCallback? onTrailingTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        if (trailingLabel != null)
          TextButton(
            onPressed: onTrailingTap,
            child: Text(
              trailingLabel!,
              style: theme.textTheme.labelLarge?.copyWith(
                color: AppColors.primary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
      ],
    );
  }
}

// ignore: unused_element
class _DashboardTodayPlanCard extends StatelessWidget {
  const _DashboardTodayPlanCard({
    required this.hasPlan,
    required this.title,
    required this.subtitle,
    required this.duration,
    required this.status,
    required this.onOpenWorkout,
  });

  final bool hasPlan;
  final String title;
  final String subtitle;
  final String duration;
  final String status;
  final VoidCallback onOpenWorkout;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return PremiumCard(
      glowColor: hasPlan ? AppColors.accentPurple : AppColors.primaryBright,
      padding: const EdgeInsets.all(18),
      child: Row(
        children: [
          Container(
            width: 58,
            height: 72,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: hasPlan
                    ? const [AppColors.accentPurple, AppColors.accentNeon]
                    : const [AppColors.primaryBright, AppColors.primary],
              ),
              borderRadius: BorderRadius.circular(22),
              boxShadow: [
                BoxShadow(
                  color: (hasPlan ? AppColors.accentPurple : AppColors.primary)
                      .withValues(alpha: 0.20),
                  blurRadius: 18,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: Icon(
              hasPlan ? Icons.fitness_center_rounded : Icons.add_rounded,
              color: Colors.white,
              size: 28,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Today Plan',
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: AppColors.textSecondary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    _CompactStatusPill(label: status),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$subtitle • $duration',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  height: 38,
                  width: 142,
                  child: _ReferenceMiniButton(
                    title: hasPlan ? 'Open Workout' : 'Choose Plan',
                    onPressed: onOpenWorkout,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _DashboardTrainerStrip extends StatelessWidget {
  const _DashboardTrainerStrip({
    required this.hasTrainer,
    required this.trainerName,
    required this.workoutLabel,
    required this.onTap,
  });

  final bool hasTrainer;
  final String trainerName;
  final String workoutLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return PremiumCard(
      onTap: onTap,
      glowColor: hasTrainer ? AppColors.primary : AppColors.accentNeon,
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: hasTrainer
                    ? const [AppColors.primaryBright, AppColors.primary]
                    : const [AppColors.accentNeon, AppColors.accentPurple],
              ),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(
              hasTrainer ? Icons.person_rounded : Icons.lock_outline_rounded,
              color: Colors.white,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  hasTrainer ? trainerName : 'Trainer support',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  hasTrainer
                      ? workoutLabel
                      : 'Connect with a gym to unlock coaching',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Icon(
            hasTrainer ? Icons.chat_bubble_rounded : Icons.north_east_rounded,
            color: AppColors.primary,
            size: 20,
          ),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _CompactStatusPill extends StatelessWidget {
  const _CompactStatusPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: AppColors.primary,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

// ignore: unused_element
class _DashboardWeeklyActivityCard extends StatelessWidget {
  const _DashboardWeeklyActivityCard({
    required this.checkedInToday,
    required this.streakDays,
    required this.weeklyBars,
  });

  final bool checkedInToday;
  final int streakDays;
  final List<double> weeklyBars;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 380;
        return PremiumCard(
          glowColor: AppColors.primaryBright,
          padding: EdgeInsets.all(compact ? 18 : 22),
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
                          'Weekly Activity',
                          style: theme.textTheme.titleMedium?.copyWith(
                            color: AppColors.textPrimary,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          checkedInToday
                              ? 'Attendance is live today'
                              : streakDays > 0
                              ? '$streakDays day workout streak'
                              : 'No active streak yet',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.textSecondary,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceSoft,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '7 days',
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              SizedBox(height: compact ? 16 : 18),
              SizedBox(
                height: compact ? 148 : 170,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: List.generate(weeklyBars.length, (index) {
                    final bar = weeklyBars[index];
                    return Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            TweenAnimationBuilder<double>(
                              tween: Tween<double>(begin: 0.18, end: bar),
                              duration: Duration(
                                milliseconds: 350 + (index * 90),
                              ),
                              curve: Curves.easeOutCubic,
                              builder: (context, value, _) => Container(
                                height:
                                    (compact ? 24 : 28) +
                                    (value * (compact ? 92 : 108)),
                                decoration: BoxDecoration(
                                  gradient: LinearGradient(
                                    colors: index == weeklyBars.length - 1
                                        ? const [
                                            AppColors.accentNeon,
                                            AppColors.accentPurple,
                                          ]
                                        : const [
                                            AppColors.primaryBright,
                                            AppColors.primary,
                                          ],
                                    begin: Alignment.bottomCenter,
                                    end: Alignment.topCenter,
                                  ),
                                  borderRadius: BorderRadius.circular(18),
                                ),
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              const ['S', 'M', 'T', 'W', 'T', 'F', 'S'][index],
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: AppColors.textSecondary,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

// ignore: unused_element
class _ReferenceMiniButton extends StatelessWidget {
  const _ReferenceMiniButton({
    required this.title,
    required this.onPressed,
    this.secondary = false,
  });

  final String title;
  final VoidCallback onPressed;
  final bool secondary;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(25),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: secondary
                ? const [AppColors.accentNeon, AppColors.accentPurple]
                : const [AppColors.primaryBright, AppColors.primary],
          ),
          borderRadius: BorderRadius.circular(25),
          boxShadow: const [
            BoxShadow(
              color: Colors.black12,
              blurRadius: 1,
              offset: Offset(0, 0.5),
            ),
          ],
        ),
        alignment: Alignment.center,
        child: Text(
          title,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

// ignore: unused_element
class _ReferenceWorkoutRow extends StatelessWidget {
  const _ReferenceWorkoutRow({
    required this.title,
    required this.meta,
    required this.progress,
    required this.onTap,
  });

  final String title;
  final String meta;
  final double progress;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 8, horizontal: 2),
        padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 15),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 2)],
        ),
        child: Row(
          children: [
            Container(
              width: 60,
              height: 60,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppColors.primaryBright, AppColors.primary],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Icon(
                Icons.fitness_center_rounded,
                color: Colors.white,
                size: 26,
              ),
            ),
            const SizedBox(width: 15),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textPrimary,
                    ),
                  ),
                  Text(
                    meta,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 6),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(7.5),
                    child: LinearProgressIndicator(
                      value: progress,
                      minHeight: 14,
                      backgroundColor: Colors.grey.shade100,
                      valueColor: const AlwaysStoppedAnimation<Color>(
                        AppColors.primary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              onPressed: onTap,
              icon: const Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ignore: unused_element
class _FeatureLockedCard extends StatelessWidget {
  const _FeatureLockedCard({
    required this.title,
    required this.message,
    required this.icon,
  });

  final String title;
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Icon(icon, color: AppColors.primaryBright),
          ),
          const SizedBox(height: 14),
          Text(title, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          Text(message, style: Theme.of(context).textTheme.bodyMedium),
        ],
      ),
    );
  }
}

class _WorkoutPage extends StatefulWidget {
  const _WorkoutPage({
    required this.plans,
    required this.history,
    required this.logbookSummary,
    required this.repository,
    required this.onOpenWorkoutBook,
    this.initialPlanId,
    this.onPlanConsumed,
  });

  final List<Map<String, dynamic>> plans;
  final List<Map<String, dynamic>> history;
  final Map<String, dynamic> logbookSummary;
  final MemberRepository repository;
  final VoidCallback onOpenWorkoutBook;
  final int? initialPlanId;
  final VoidCallback? onPlanConsumed;

  @override
  State<_WorkoutPage> createState() => __WorkoutPageState();
}

class __WorkoutPageState extends State<_WorkoutPage> {
  final _planIdController = TextEditingController();
  final Map<int, List<Map<String, dynamic>>> _exerciseHistoryCache =
      <int, List<Map<String, dynamic>>>{};
  final Map<int, bool> _exerciseHistoryLoading = <int, bool>{};
  final Map<int, String?> _exerciseHistoryError = <int, String?>{};
  List<Map<String, dynamic>> _workoutHistory = const [];
  int? _activeSessionId;
  DateTime? _activeStartedAt;
  List<Map<String, dynamic>> _sessionExercises = const [];
  Map<String, dynamic>? _summaryData;
  bool _startingWorkout = false;
  bool _completingWorkout = false;
  bool _addingExercise = false;
  bool _customExerciseLibraryLoading = false;
  bool _showPrAchievement = false;
  int? _restExerciseIndex;
  int _restRemainingSeconds = 0;
  int _restTotalSeconds = 0;
  Timer? _restTimer;
  List<Map<String, dynamic>> _customExerciseLibrary = const [];

  @override
  void initState() {
    super.initState();
    _workoutHistory = widget.history
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    final initialPlanId =
        widget.initialPlanId ??
        (widget.plans.firstOrNull?['id'] as num?)?.toInt();
    if (initialPlanId != null) {
      _planIdController.text = '$initialPlanId';
    }
    scheduleMicrotask(_restoreActiveSessionIfAny);
  }

  @override
  void didUpdateWidget(covariant _WorkoutPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.history != widget.history && _activeSessionId == null) {
      _workoutHistory = widget.history
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
    }
    if (_planIdController.text.trim().isEmpty) {
      final initialPlanId =
          widget.initialPlanId ??
          (widget.plans.firstOrNull?['id'] as num?)?.toInt();
      if (initialPlanId != null) {
        _planIdController.text = '$initialPlanId';
      }
    }
    if (widget.initialPlanId != null &&
        widget.initialPlanId != oldWidget.initialPlanId) {
      _planIdController.text = '${widget.initialPlanId}';
      WidgetsBinding.instance.addPostFrameCallback((_) {
        widget.onPlanConsumed?.call();
      });
    }
  }

  @override
  void dispose() {
    _restTimer?.cancel();
    _planIdController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final personalRecords =
        (widget.logbookSummary['personal_records'] as List<dynamic>? ??
                const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
    final selectedPlan = widget.plans.firstWhere(
      (plan) =>
          _planIdController.text.trim().isNotEmpty &&
          plan['id']?.toString() == _planIdController.text.trim(),
      orElse: () => widget.plans.firstOrNull ?? const <String, dynamic>{},
    );
    final activeDuration = _activeStartedAt == null
        ? null
        : DateTime.now().difference(_activeStartedAt!);
    final totalVolume = _sessionExercises.fold<double>(
      0,
      (sum, exercise) => sum + _exerciseVolume(exercise),
    );
    final completedExercises = _sessionExercises
        .where(
          (exercise) =>
              ((exercise['sets'] as List<dynamic>? ?? const []).isNotEmpty),
        )
        .length;

    final selectedPlanDays = selectedPlan['days'] is List
        ? (selectedPlan['days'] as List).length
        : 0;
    final selectedGoal =
        selectedPlan['goal']?.toString() ?? 'Build a stronger routine';
    final selectedPlanId = _selectedPlanId();
    final hasAssignedPlans = widget.plans.isNotEmpty;
    final canStartWorkout = !_startingWorkout && _activeSessionId == null;
    final historyPreview = _workoutHistory.take(4).toList();

    return ListView(
      padding: EdgeInsets.zero,
      physics: const BouncingScrollPhysics(),
      children: [
        _FitLifeWorkoutHeader(
          active: _activeSessionId != null,
          planCount: widget.plans.length,
          exerciseCount: _sessionExercises.length,
          totalVolume: totalVolume,
          duration: activeDuration ?? Duration.zero,
          onOpenBook: widget.onOpenWorkoutBook,
          onOpenLogbook: _openLogbook,
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (_restExerciseIndex != null && _restRemainingSeconds > 0) ...[
                RevealOnBuild(
                  child: _RestTimerOverlay(
                    exerciseName: _sessionExercises.length > _restExerciseIndex!
                        ? (Map<String, dynamic>.from(
                                _sessionExercises[_restExerciseIndex!]['exercise']
                                        as Map? ??
                                    const {},
                              )['name']?.toString() ??
                              'Exercise')
                        : 'Exercise',
                    remainingSeconds: _restRemainingSeconds,
                    totalSeconds: _restTotalSeconds,
                  ),
                ),
                const SizedBox(height: 18),
              ],
              RevealOnBuild(
                child: _FitLifeWorkoutStats(
                  completedExercises: completedExercises,
                  totalExercises: _sessionExercises.length,
                  totalVolume: _formatVolume(totalVolume),
                  planDays: selectedPlanDays,
                ),
              ),
              const SizedBox(height: 22),
              _FitLifeSectionHeader(
                title: _activeSessionId == null
                    ? 'Choose Workout'
                    : 'Workout Session',
                actionLabel: widget.plans.isEmpty ? null : 'Library',
                onAction: widget.plans.isEmpty
                    ? null
                    : widget.onOpenWorkoutBook,
              ),
              const SizedBox(height: 12),
              if (_activeSessionId != null)
                _ActiveWorkoutMiniBar(
                  duration: activeDuration ?? Duration.zero,
                  exerciseCount: _sessionExercises.length,
                  totalVolume: totalVolume,
                )
              else if (widget.plans.isEmpty)
                _FitLifeEmptyPanel(
                  title: 'No assigned workout yet',
                  message:
                      'Start a custom workout now and add exercises from the exercise library, or open the workout book to adopt a plan.',
                  icon: Icons.fitness_center_rounded,
                  actionLabel: 'Start Custom Workout',
                  onAction: canStartWorkout ? _startWorkout : null,
                )
              else
                ...widget.plans.asMap().entries.map((entry) {
                  final planValue = '${entry.value['id']}';
                  final selectedValue = _planIdController.text.trim().isEmpty
                      ? '${widget.plans.first['id']}'
                      : _planIdController.text.trim();
                  final isSelected = selectedValue == planValue;
                  return RevealOnBuild(
                    delay: Duration(milliseconds: 45 * entry.key),
                    child: _FitLifeWorkoutPlanRow(
                      plan: entry.value,
                      selected: isSelected,
                      onTap: () => setState(() {
                        _planIdController.text = planValue;
                      }),
                    ),
                  );
                }),
              const SizedBox(height: 18),
              _FitLifePrimaryAction(
                label: _startingWorkout
                    ? 'Starting workout...'
                    : _activeSessionId == null
                    ? (hasAssignedPlans
                        ? (selectedPlanId != null
                            ? 'Start Workout'
                            : 'Select Workout Plan')
                        : 'Start Custom Workout')
                    : 'Workout Active',
                icon: _activeSessionId == null
                    ? Icons.play_arrow_rounded
                    : Icons.pause_circle_filled_rounded,
                loading: _startingWorkout,
                enabled: canStartWorkout &&
                    (!hasAssignedPlans || selectedPlanId != null),
                onTap: _startWorkout,
              ),
              if (hasAssignedPlans &&
                  selectedPlan.isNotEmpty &&
                  _activeSessionId == null) ...[
                const SizedBox(height: 12),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    StatusBadge(
                      label: '$selectedPlanDays workout days',
                      color: AppColors.primaryBright,
                    ),
                    StatusBadge(
                      label: selectedGoal,
                      color: AppColors.primary,
                    ),
                  ],
                ),
              ],
              if (_activeSessionId != null) ...[
                const SizedBox(height: 24),
                _FitLifeSectionHeader(
                  title: 'Active Exercises',
                  actionLabel: _addingExercise ? 'Adding...' : 'Add',
                  onAction: _addingExercise ? null : _openAddExerciseSheet,
                ),
                const SizedBox(height: 12),
                if (_sessionExercises.isEmpty)
                  _FitLifeEmptyPanel(
                    title: 'No exercises loaded',
                    message: widget.plans.isEmpty
                        ? 'Add custom exercises from the exercise library, or end the workout below.'
                        : 'Add exercises from your assigned workout library, or end the workout below.',
                    icon: Icons.playlist_add_rounded,
                  )
                else ...[
                  ..._sessionExercises.asMap().entries.map(
                    (entry) => Padding(
                      padding: const EdgeInsets.only(bottom: 14),
                      child: _WorkoutExerciseCard(
                        exercise: entry.value,
                        initiallyExpanded: entry.key == 0,
                        previousBest: _recordForExercise(
                          personalRecords,
                          entry.value,
                        ),
                        recentHistory:
                            _exerciseHistoryCache[_exerciseId(entry.value)] ??
                            const [],
                        historyLoading:
                            _exerciseHistoryLoading[_exerciseId(entry.value)] ==
                            true,
                        historyError:
                            _exerciseHistoryError[_exerciseId(entry.value)],
                        onLoadHistory: () => _loadExerciseHistory(entry.value),
                        onAddSet: () => _addSet(entry.key),
                        onDuplicateLastSet: () => _duplicateLastSet(entry.key),
                        onUpdateExerciseNotes: (value) =>
                            _updateExerciseNotes(entry.key, value),
                        onUpdateSet: (setIndex, field, value) =>
                            _updateSet(entry.key, setIndex, field, value),
                        onDeleteSet: (setIndex) =>
                            _deleteSet(entry.key, setIndex),
                        onStartRest: (seconds) =>
                            _startRest(entry.key, seconds),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 4),
                _FitLifePrimaryAction(
                  label: _completingWorkout
                      ? (_sessionExercises.isEmpty
                          ? 'Ending workout...'
                          : 'Completing workout...')
                      : (_sessionExercises.isEmpty
                          ? 'End Workout'
                          : 'Complete Workout'),
                  icon: _sessionExercises.isEmpty
                      ? Icons.stop_circle_rounded
                      : Icons.emoji_events_rounded,
                  loading: _completingWorkout,
                  enabled: !_completingWorkout,
                  gradient: const [AppColors.primaryBright, AppColors.primary],
                  onTap: _completeWorkout,
                ),
              ],
              if (_showPrAchievement) ...[
                const SizedBox(height: 18),
                PulseGlow(
                  enabled: true,
                  glowColor: AppColors.primaryBright,
                  child: _FitLifeAchievementPanel(
                    title: 'New personal record',
                    message: 'Your latest session unlocked a new best.',
                  ),
                ),
              ],
              const SizedBox(height: 24),
              _FitLifeSectionHeader(
                title: 'Latest Activity',
                actionLabel: 'Logbook',
                onAction: _openLogbook,
              ),
              const SizedBox(height: 12),
              if (_summaryData != null) ...[
                _WorkoutCompletionSummary(summary: _summaryData!),
                const SizedBox(height: 14),
              ],
              if (historyPreview.isEmpty)
                _FitLifeEmptyPanel(
                  title: 'No workout history yet',
                  message:
                      'Complete your first workout to unlock trends, PRs, and volume history.',
                  icon: Icons.insights_rounded,
                  actionLabel: canStartWorkout
                      ? (hasAssignedPlans
                          ? (selectedPlanId != null ? 'Start Workout' : null)
                          : 'Start Custom Workout')
                      : null,
                  onAction: canStartWorkout
                      ? _startWorkout
                      : null,
                )
              else
                ...historyPreview.asMap().entries.map(
                  (entry) => RevealOnBuild(
                    delay: Duration(milliseconds: 45 * entry.key),
                    child: _FitLifeHistoryRow(
                      title:
                          entry.value['session_date']?.toString() ?? 'Session',
                      subtitle:
                          '${_titleCase(entry.value['status']?.toString() ?? 'completed')} • Volume ${_formatVolume((entry.value['total_volume'] as num?)?.toDouble() ?? 0)}',
                      index: entry.key,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _startWorkout() async {
    final messenger = ScaffoldMessenger.of(context);
    final selectedPlanId = _selectedPlanId();

    if (widget.plans.isNotEmpty && selectedPlanId == null) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Select an assigned workout plan before starting a workout.',
          ),
        ),
      );
      return;
    }

    setState(() => _startingWorkout = true);

    try {
      if (_activeSessionId != null) {
        messenger.showSnackBar(
          const SnackBar(content: Text('A workout session is already active.')),
        );
        return;
      }
      final response = await widget.repository.startWorkout({
        if (selectedPlanId != null) 'workout_plan_id': selectedPlanId,
        'session_date': DateTime.now().toIso8601String().split('T').first,
      });
      final data = Map<String, dynamic>.from(
        response['data'] as Map? ?? const {},
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _activeSessionId =
            (data['id'] as num?)?.toInt() ??
            (data['session'] is Map
                ? (Map<String, dynamic>.from(data['session'] as Map)['id']
                          as num?)
                      ?.toInt()
                : null);
        _activeStartedAt =
            DateTime.tryParse(data['started_at']?.toString() ?? '') ??
            DateTime.now();
        _sessionExercises = _normalizeSessionExercises(data['exercises']);
        _summaryData = null;
        _showPrAchievement = false;
        _restExerciseIndex = null;
        _restRemainingSeconds = 0;
        _restTotalSeconds = 0;
      });
      messenger.showSnackBar(
        const SnackBar(content: Text('Workout session started.')),
      );
    } catch (exception) {
      final message = exception.toString().toLowerCase();
      if (message.contains('active workout session')) {
        await _restoreActiveSessionIfAny(forceReloadHistory: true);
      }
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _startingWorkout = false);
      }
    }
  }

  Future<void> _completeWorkout() async {
    final sessionId = _activeSessionId;
    if (sessionId == null) {
      return;
    }

    final messenger = ScaffoldMessenger.of(context);
    final endingWithoutExercises = _sessionExercises.isEmpty;
    setState(() => _completingWorkout = true);

    try {
      final response = await widget.repository
          .completeWorkoutSession(sessionId, {
            'completed_at': DateTime.now().toIso8601String(),
            'notes': 'Completed from member app',
            'exercises': _buildCompletionPayload(),
          });
      final data = Map<String, dynamic>.from(
        response['data'] as Map? ?? const {},
      );
      final records = data['personal_records'] as List<dynamic>? ?? const [];

      if (!mounted) {
        return;
      }

      setState(() {
        _activeSessionId = null;
        _activeStartedAt = null;
        _summaryData = _buildSummaryData(
          records.isNotEmpty,
          Map<String, dynamic>.from(response['data'] as Map? ?? const {}),
        );
        _workoutHistory = [
          Map<String, dynamic>.from(response['data'] as Map? ?? const {}),
          ..._workoutHistory.where(
            (item) => (item['id'] as num?)?.toInt() != sessionId,
          ),
        ];
        _sessionExercises = const [];
        _showPrAchievement = records.isNotEmpty;
        _restExerciseIndex = null;
        _restRemainingSeconds = 0;
        _restTotalSeconds = 0;
      });
      await _showCompletionCelebration(context, records.isNotEmpty);
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            endingWithoutExercises
                ? 'Workout ended successfully.'
                : 'Workout completed successfully.',
          ),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _completingWorkout = false);
      }
    }
  }

  List<Map<String, dynamic>> _normalizeSessionExercises(Object? raw) {
    final items = (raw as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    return items.map((exercise) {
      final sets = (exercise['sets'] as List<dynamic>? ?? const [])
          .map((set) => Map<String, dynamic>.from(set as Map))
          .toList();
      final plannedSets = (exercise['planned_sets'] as num?)?.toInt() ?? 0;
      if (sets.isEmpty && plannedSets > 0) {
        for (var index = 0; index < plannedSets; index++) {
          sets.add({
            'set_number': index + 1,
            'reps': 0,
            'weight': (exercise['target_weight'] as num?)?.toDouble() ?? 0,
            'rest_seconds':
                (exercise['rest_timer_seconds'] as num?)?.toInt() ?? 60,
            'notes': null,
            'is_completed': true,
          });
        }
      }

      return {
        ...exercise,
        'exercise': Map<String, dynamic>.from(
          exercise['exercise'] as Map? ?? const {},
        ),
        'sets': sets,
        'notes_controller_seed': exercise['notes']?.toString() ?? '',
      };
    }).toList();
  }

  List<Map<String, dynamic>> _buildCompletionPayload() {
    return _sessionExercises.map((exercise) {
      final sets = (exercise['sets'] as List<dynamic>? ?? const [])
          .map((set) => Map<String, dynamic>.from(set as Map))
          .where(
            (set) =>
                ((set['reps'] as num?)?.toInt() ?? 0) > 0 ||
                ((set['weight'] as num?)?.toDouble() ?? 0) > 0,
          )
          .toList();

      if (sets.isEmpty) {
        sets.add({
          'set_number': 1,
          'reps': 0,
          'weight': 0,
          'rest_seconds':
              (exercise['rest_timer_seconds'] as num?)?.toInt() ?? 60,
          'notes': null,
          'is_completed': true,
        });
      }

      return {
        'id': exercise['id'],
        'exercise_id': exercise['exercise_id'],
        'sort_order': exercise['sort_order'],
        'planned_sets': exercise['planned_sets'],
        'planned_reps': exercise['planned_reps']?.toString(),
        'target_weight': exercise['target_weight'],
        'rest_timer_seconds': exercise['rest_timer_seconds'],
        'notes': exercise['notes']?.toString(),
        'sets': sets.map((set) {
          return {
            'set_number': (set['set_number'] as num?)?.toInt() ?? 1,
            'reps': (set['reps'] as num?)?.toInt() ?? 0,
            'weight': (set['weight'] as num?)?.toDouble() ?? 0,
            'rest_seconds': (set['rest_seconds'] as num?)?.toInt() ?? 0,
            'notes': set['notes']?.toString(),
            'is_completed': set['is_completed'] != false,
          };
        }).toList(),
      };
    }).toList();
  }

  Future<void> _loadExerciseHistory(Map<String, dynamic> exercise) async {
    final exerciseId = _exerciseId(exercise);
    if (exerciseId == null || _exerciseHistoryLoading[exerciseId] == true) {
      return;
    }
    setState(() {
      _exerciseHistoryLoading[exerciseId] = true;
      _exerciseHistoryError[exerciseId] = null;
    });
    try {
      final response = await widget.repository.fetchExerciseHistory(exerciseId);
      final data = Map<String, dynamic>.from(
        response['data'] as Map? ?? const {},
      );
      final history = (data['history'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      if (!mounted) {
        return;
      }
      setState(() {
        _exerciseHistoryCache[exerciseId] = history;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() {
        _exerciseHistoryError[exerciseId] = exception.toString();
      });
    } finally {
      if (mounted) {
        setState(() {
          _exerciseHistoryLoading[exerciseId] = false;
        });
      }
    }
  }

  int? _exerciseId(Map<String, dynamic> exercise) {
    return (exercise['exercise_id'] as num?)?.toInt();
  }

  Map<String, dynamic>? _recordForExercise(
    List<Map<String, dynamic>> records,
    Map<String, dynamic> exercise,
  ) {
    final exerciseId = _exerciseId(exercise);
    for (final record in records) {
      if ((record['exercise_id'] as num?)?.toInt() == exerciseId) {
        return record;
      }
    }
    return null;
  }

  void _addSet(int exerciseIndex) {
    setState(() {
      final exercise = Map<String, dynamic>.from(
        _sessionExercises[exerciseIndex],
      );
      final sets = (exercise['sets'] as List<dynamic>? ?? const [])
          .map((set) => Map<String, dynamic>.from(set as Map))
          .toList();
      sets.add({
        'set_number': sets.length + 1,
        'reps': 0,
        'weight': sets.isEmpty ? 0 : sets.last['weight'],
        'rest_seconds': (exercise['rest_timer_seconds'] as num?)?.toInt() ?? 60,
        'notes': null,
        'is_completed': true,
      });
      exercise['sets'] = sets;
      _sessionExercises = _replaceExercise(exerciseIndex, exercise);
    });
  }

  void _duplicateLastSet(int exerciseIndex) {
    setState(() {
      final exercise = Map<String, dynamic>.from(
        _sessionExercises[exerciseIndex],
      );
      final sets = (exercise['sets'] as List<dynamic>? ?? const [])
          .map((set) => Map<String, dynamic>.from(set as Map))
          .toList();
      if (sets.isEmpty) {
        _addSet(exerciseIndex);
        return;
      }
      final previous = Map<String, dynamic>.from(sets.last);
      sets.add({...previous, 'set_number': sets.length + 1});
      exercise['sets'] = sets;
      _sessionExercises = _replaceExercise(exerciseIndex, exercise);
    });
  }

  void _deleteSet(int exerciseIndex, int setIndex) {
    setState(() {
      final exercise = Map<String, dynamic>.from(
        _sessionExercises[exerciseIndex],
      );
      final sets = (exercise['sets'] as List<dynamic>? ?? const [])
          .map((set) => Map<String, dynamic>.from(set as Map))
          .toList();
      if (setIndex < 0 || setIndex >= sets.length) {
        return;
      }
      sets.removeAt(setIndex);
      for (var index = 0; index < sets.length; index++) {
        sets[index]['set_number'] = index + 1;
      }
      exercise['sets'] = sets;
      _sessionExercises = _replaceExercise(exerciseIndex, exercise);
    });
  }

  void _updateExerciseNotes(int exerciseIndex, String value) {
    setState(() {
      final exercise = Map<String, dynamic>.from(
        _sessionExercises[exerciseIndex],
      );
      exercise['notes'] = value;
      _sessionExercises = _replaceExercise(exerciseIndex, exercise);
    });
  }

  void _updateSet(int exerciseIndex, int setIndex, String field, String value) {
    setState(() {
      final exercise = Map<String, dynamic>.from(
        _sessionExercises[exerciseIndex],
      );
      final sets = (exercise['sets'] as List<dynamic>? ?? const [])
          .map((set) => Map<String, dynamic>.from(set as Map))
          .toList();
      final set = Map<String, dynamic>.from(sets[setIndex]);
      switch (field) {
        case 'weight':
          set[field] = double.tryParse(value) ?? 0;
          break;
        case 'reps':
        case 'rest_seconds':
          set[field] = int.tryParse(value) ?? 0;
          break;
        default:
          set[field] = value;
      }
      sets[setIndex] = set;
      exercise['sets'] = sets;
      _sessionExercises = _replaceExercise(exerciseIndex, exercise);
    });
  }

  List<Map<String, dynamic>> _replaceExercise(
    int exerciseIndex,
    Map<String, dynamic> exercise,
  ) {
    final items = _sessionExercises.toList();
    items[exerciseIndex] = exercise;
    return items;
  }

  void _startRest(int exerciseIndex, int seconds) {
    _restTimer?.cancel();
    setState(() {
      _restExerciseIndex = exerciseIndex;
      _restRemainingSeconds = seconds <= 0 ? 45 : seconds;
      _restTotalSeconds = seconds <= 0 ? 45 : seconds;
    });
    _restTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_restRemainingSeconds <= 1) {
        timer.cancel();
        setState(() {
          _restRemainingSeconds = 0;
          _restExerciseIndex = null;
          _restTotalSeconds = 0;
        });
        return;
      }
      setState(() {
        _restRemainingSeconds -= 1;
      });
    });
  }

  double _exerciseVolume(Map<String, dynamic> exercise) {
    return (exercise['sets'] as List<dynamic>? ?? const []).fold<double>(0, (
      sum,
      item,
    ) {
      final set = Map<String, dynamic>.from(item as Map);
      return sum +
          (((set['weight'] as num?)?.toDouble() ?? 0) *
              ((set['reps'] as num?)?.toInt() ?? 0));
    });
  }

  Map<String, dynamic> _buildSummaryData(
    bool hasPr,
    Map<String, dynamic> sessionData,
  ) {
    final exercises = (sessionData['exercises'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final startedAt = _activeStartedAt;
    final completedAt =
        DateTime.tryParse(sessionData['completed_at']?.toString() ?? '') ??
        DateTime.now();
    final duration = startedAt == null
        ? Duration.zero
        : completedAt.difference(startedAt);
    final muscleGroups = <String, int>{};
    for (final exercise in exercises) {
      final group = exercise['exercise']?['muscle_group']?.toString();
      if (group == null || group.isEmpty) {
        continue;
      }
      muscleGroups[group] = (muscleGroups[group] ?? 0) + 1;
    }
    return {
      'total_volume': (sessionData['total_volume'] as num?)?.toDouble() ?? 0,
      'duration': duration,
      'exercises_completed': exercises.length,
      'has_pr': hasPr,
      'muscle_groups': muscleGroups,
    };
  }

  String _formatVolume(double value) {
    return '${value.toStringAsFixed(0)} kg';
  }

  int? _selectedPlanId() {
    if (_planIdController.text.trim().isNotEmpty) {
      return int.tryParse(_planIdController.text.trim());
    }

    return (widget.plans.firstOrNull?['id'] as num?)?.toInt();
  }

  Future<void> _ensureCustomExerciseLibraryLoaded() async {
    if (_customExerciseLibraryLoading || _customExerciseLibrary.isNotEmpty) {
      return;
    }

    setState(() => _customExerciseLibraryLoading = true);

    try {
      final response = await widget.repository.fetchWorkoutExercises();
      final rawItems = response['data'] as List<dynamic>? ?? const [];
      final exercises = rawItems
          .map((item) => Map<String, dynamic>.from(item as Map))
          .map((exercise) => <String, dynamic>{
                'exercise_id': (exercise['id'] as num?)?.toInt(),
                'exercise': exercise,
                'sets': 3,
                'reps': '10',
                'target_weight': 0,
                'rest_seconds': 60,
                'notes': null,
              })
          .where((exercise) => exercise['exercise_id'] != null)
          .toList();

      if (!mounted) {
        return;
      }

      setState(() {
        _customExerciseLibrary = exercises;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _customExerciseLibraryLoading = false);
      }
    }
  }

  String _titleCase(String value) {
    if (value.isEmpty) {
      return 'Unknown';
    }
    return value
        .split('_')
        .map(
          (part) => part.isEmpty
              ? part
              : '${part[0].toUpperCase()}${part.substring(1)}',
        )
        .join(' ');
  }

  Future<void> _restoreActiveSessionIfAny({
    bool forceReloadHistory = false,
  }) async {
    List<Map<String, dynamic>> sourceHistory = _workoutHistory;

    if (forceReloadHistory) {
      try {
        final response = await widget.repository.fetchWorkoutHistory();
        sourceHistory = (response['data'] as List<dynamic>? ?? const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
        _workoutHistory = sourceHistory;
      } catch (_) {
        return;
      }
    }

    Map<String, dynamic>? activeSession;
    for (final session in sourceHistory) {
      if ((session['status']?.toString().toLowerCase() ?? '') == 'active') {
        activeSession = session;
        break;
      }
    }

    final sessionId = (activeSession?['id'] as num?)?.toInt();
    if (sessionId == null) {
      return;
    }

    try {
      final response = await widget.repository.fetchWorkoutSession(sessionId);
      final data = Map<String, dynamic>.from(
        response['data'] as Map? ?? const {},
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _activeSessionId = (data['id'] as num?)?.toInt() ?? sessionId;
        _activeStartedAt =
            DateTime.tryParse(data['started_at']?.toString() ?? '') ??
            DateTime.now();
        _sessionExercises = _normalizeSessionExercises(data['exercises']);
      });
    } catch (_) {
      // Ignore stale references and let the user start fresh.
    }
  }

  List<Map<String, dynamic>> _collectExerciseLibrary() {
    if (widget.plans.isEmpty) {
      return _customExerciseLibrary;
    }

    final library = <int, Map<String, dynamic>>{};

    for (final plan in widget.plans) {
      final days = (plan['days'] as List<dynamic>? ?? const []);
      for (final dayItem in days) {
        final day = Map<String, dynamic>.from(dayItem as Map);
        final exercises = (day['exercises'] as List<dynamic>? ?? const []);
        for (final exerciseItem in exercises) {
          final exercise = Map<String, dynamic>.from(exerciseItem as Map);
          final exerciseId = (exercise['exercise_id'] as num?)?.toInt();
          if (exerciseId == null) {
            continue;
          }
          library.putIfAbsent(exerciseId, () => exercise);
        }
      }
    }

    final values = library.values.toList();
    values.sort((a, b) {
      final aName =
          Map<String, dynamic>.from(
            a['exercise'] as Map? ?? const {},
          )['name']?.toString() ??
          '';
      final bName =
          Map<String, dynamic>.from(
            b['exercise'] as Map? ?? const {},
          )['name']?.toString() ??
          '';
      return aName.compareTo(bName);
    });
    return values;
  }

  List<Map<String, dynamic>> _seedSetsFromPlan(Map<String, dynamic> exercise) {
    final count = (exercise['sets'] as num?)?.toInt() ?? 3;
    final reps = int.tryParse(exercise['reps']?.toString() ?? '') ?? 0;
    final weight = (exercise['target_weight'] as num?)?.toDouble() ?? 0;
    final rest = (exercise['rest_seconds'] as num?)?.toInt() ?? 60;

    return List<Map<String, dynamic>>.generate(count, (index) {
      return {
        'set_number': index + 1,
        'reps': reps,
        'weight': weight,
        'rest_seconds': rest,
        'notes': null,
        'is_completed': true,
      };
    });
  }

  Future<void> _openAddExerciseSheet() async {
    final sessionId = _activeSessionId;
    if (sessionId == null) {
      return;
    }

    if (widget.plans.isEmpty) {
      await _ensureCustomExerciseLibraryLoaded();
    }

    final library = _collectExerciseLibrary();
    if (library.isEmpty) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            widget.plans.isEmpty
                ? 'No custom exercise library is available right now.'
                : 'No exercise library is available from your assigned workout plans yet.',
          ),
        ),
      );
      return;
    }

    final existingIds = _sessionExercises
        .map((exercise) => (exercise['exercise_id'] as num?)?.toInt())
        .whereType<int>()
        .toSet();
    final available = library
        .where(
          (exercise) =>
              !existingIds.contains((exercise['exercise_id'] as num?)?.toInt()),
        )
        .toList();

    if (available.isEmpty) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            widget.plans.isEmpty
                ? 'All available custom exercises are already in this workout.'
                : 'All available plan exercises are already in this workout.',
          ),
        ),
      );
      return;
    }

    if (!mounted) {
      return;
    }

    final selected = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _AddExerciseSheet(exercises: available),
    );

    if (selected == null) {
      return;
    }

    setState(() => _addingExercise = true);
    try {
      final response = await widget.repository.addWorkoutExercise(sessionId, {
        'exercise_id': (selected['exercise_id'] as num?)?.toInt(),
        'sort_order': _sessionExercises.length + 1,
        'planned_sets': (selected['sets'] as num?)?.toInt() ?? 3,
        'planned_reps': selected['reps']?.toString() ?? '10',
        'target_weight': (selected['target_weight'] as num?)?.toDouble() ?? 0,
        'rest_timer_seconds': (selected['rest_seconds'] as num?)?.toInt() ?? 60,
        'notes': selected['notes']?.toString(),
        'sets': _seedSetsFromPlan(selected),
      });
      final data = Map<String, dynamic>.from(
        response['data'] as Map? ?? const {},
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _sessionExercises = _normalizeSessionExercises(data['exercises']);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Exercise added to active workout.')),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _addingExercise = false);
      }
    }
  }

  Future<void> _openLogbook() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => MemberLogbookScreen(repository: widget.repository),
      ),
    );
  }

  Future<void> _showCompletionCelebration(BuildContext context, bool hasPr) {
    return showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Workout complete',
      pageBuilder: (context, _, __) => const SizedBox.shrink(),
      transitionDuration: const Duration(milliseconds: 260),
      transitionBuilder: (context, animation, secondaryAnimation, child) {
        return FadeTransition(
          opacity: animation,
          child: ScaleTransition(
            scale: CurvedAnimation(
              parent: animation,
              curve: Curves.easeOutBack,
            ),
            child: Center(
              child: PremiumCard(
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      PulseGlow(
                        enabled: true,
                        pulseScale: hasPr ? 1.08 : 1.05,
                        glowColor: Theme.of(context).colorScheme.secondary,
                        child: Icon(
                          hasPr
                              ? Icons.emoji_events_rounded
                              : Icons.check_circle_rounded,
                          size: 56,
                          color: Theme.of(context).colorScheme.secondary,
                        ),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        hasPr
                            ? 'Workout complete. New PR unlocked.'
                            : 'Workout complete.',
                        style: Theme.of(context).textTheme.headlineSmall,
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _FitLifeWorkoutHeader extends StatelessWidget {
  const _FitLifeWorkoutHeader({
    required this.active,
    required this.planCount,
    required this.exerciseCount,
    required this.totalVolume,
    required this.duration,
    required this.onOpenBook,
    required this.onOpenLogbook,
  });

  final bool active;
  final int planCount;
  final int exerciseCount;
  final double totalVolume;
  final Duration duration;
  final VoidCallback onOpenBook;
  final VoidCallback onOpenLogbook;

  @override
  Widget build(BuildContext context) {
    final primaryValue = active ? '$exerciseCount' : '$planCount';
    final primaryLabel = active ? 'Exercises loaded' : 'Plans ready';
    final secondaryValue = active
        ? '${duration.inMinutes} min'
        : '${totalVolume.toStringAsFixed(0)} kg volume';
    final secondaryLabel = active ? 'Elapsed time' : 'Last tracked volume';

    return Container(
      margin: const EdgeInsets.fromLTRB(0, 0, 0, 22),
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppColors.surface,
            AppColors.surfaceSoft,
            AppColors.primaryBright.withValues(alpha: 0.04),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(36)),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.82)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.08),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -38,
            top: -16,
            child: Container(
              width: 168,
              height: 168,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.primaryBright.withValues(alpha: 0.04),
              ),
            ),
          ),
          Positioned(
            right: 22,
            top: 36,
            child: Container(
              width: 92,
              height: 92,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.primary.withValues(alpha: 0.04),
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 58,
            child: Container(
              height: 1,
              color: AppColors.stroke.withValues(alpha: 0.82),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.primaryBright.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(
                        color: AppColors.primaryBright.withValues(alpha: 0.14),
                      ),
                    ),
                    child: Text(
                      active ? 'ACTIVE SESSION' : 'WORKOUT TRACKER',
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: AppColors.primaryBright,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.8,
                      ),
                    ),
                  ),
                  const Spacer(),
                  _FitLifeHeaderButton(
                    icon: Icons.insights_rounded,
                    onTap: onOpenLogbook,
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Text(
                'Workout Tracker',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.6,
                  height: 1.02,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                active
                    ? 'Track your sets, rest, and volume with a cleaner live session view.'
                    : 'Choose a plan and start logging with a more focused setup.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 18),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [
                            AppColors.surface,
                            AppColors.surfaceStrong,
                          ],
                        ),
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(
                          color: AppColors.stroke.withValues(alpha: 0.88),
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            primaryValue,
                            style: Theme.of(context).textTheme.displaySmall
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w900,
                                  height: 0.92,
                                ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            primaryLabel,
                            style: Theme.of(context).textTheme.labelLarge
                                ?.copyWith(
                                  color: AppColors.textSecondary,
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                          const SizedBox(height: 14),
                          Row(
                            children: [
                              Expanded(
                                child: _FitLifeHeaderMetric(
                                  label: secondaryLabel,
                                  value: secondaryValue,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: _FitLifeHeaderMetric(
                                  label: active ? 'Volume' : 'Quick access',
                                  value: active
                                      ? '${totalVolume.toStringAsFixed(0)} kg'
                                      : 'Book + Logbook',
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: _FitLifeHeaderRing(active: active),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  _FitLifeHeaderPill(
                    icon: Icons.library_books_rounded,
                    label: 'Workout Book',
                    onTap: onOpenBook,
                  ),
                  _FitLifeHeaderPill(
                    icon: Icons.menu_book_rounded,
                    label: 'Logbook',
                    onTap: onOpenLogbook,
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _FitLifeHeaderMetric extends StatelessWidget {
  const _FitLifeHeaderMetric({
    required this.label,
    required this.value,
  });

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: AppColors.textMuted,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.7,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _FitLifeHeaderButton extends StatelessWidget {
  const _FitLifeHeaderButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.stroke.withValues(alpha: 0.9)),
        ),
        child: Icon(icon, color: AppColors.primaryBright),
      ),
    );
  }
}

class _FitLifeHeaderRing extends StatelessWidget {
  const _FitLifeHeaderRing({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: active ? 0.76 : 0.42),
      duration: const Duration(milliseconds: 700),
      curve: Curves.easeOutCubic,
      builder: (context, value, _) {
        return Container(
          width: 88,
          height: 88,
          decoration: BoxDecoration(
            color: AppColors.surface,
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.stroke.withValues(alpha: 0.9)),
          ),
          child: Stack(
            alignment: Alignment.center,
            children: [
              SizedBox(
                width: 70,
                height: 70,
                child: CircularProgressIndicator(
                  value: 1,
                  strokeWidth: 8,
                  color: AppColors.stroke,
                ),
              ),
              SizedBox(
                width: 70,
                height: 70,
                child: CircularProgressIndicator(
                  value: value,
                  strokeWidth: 8,
                  strokeCap: StrokeCap.round,
                  color: AppColors.primaryBright,
                ),
              ),
              Icon(
                active ? Icons.bolt_rounded : Icons.fitness_center_rounded,
                color: AppColors.primaryBright,
                size: 26,
              ),
            ],
          ),
        );
      },
    );
  }
}

class _FitLifeHeaderPill extends StatelessWidget {
  const _FitLifeHeaderPill({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.stroke.withValues(alpha: 0.88)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: AppColors.primaryBright, size: 18),
            const SizedBox(width: 8),
            Text(
              label,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FitLifeWorkoutStats extends StatelessWidget {
  const _FitLifeWorkoutStats({
    required this.completedExercises,
    required this.totalExercises,
    required this.totalVolume,
    required this.planDays,
  });

  final int completedExercises;
  final int totalExercises;
  final String totalVolume;
  final int planDays;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.surface, AppColors.surfaceStrong],
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.stroke),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.06),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: _FitLifeStatCell(
              value: '$completedExercises/$totalExercises',
              label: 'Logged',
              gradient: const [AppColors.primaryBright, AppColors.primary],
              icon: Icons.checklist_rounded,
            ),
          ),
          _FitLifeDivider(),
          Expanded(
            child: _FitLifeStatCell(
              value: totalVolume,
              label: 'Volume',
              gradient: const [AppColors.primary, AppColors.primaryBright],
              icon: Icons.fitness_center_rounded,
            ),
          ),
          _FitLifeDivider(),
          Expanded(
            child: _FitLifeStatCell(
              value: '$planDays',
              label: 'Days',
              gradient: const [AppColors.primaryBright, AppColors.primary],
              icon: Icons.calendar_today_rounded,
            ),
          ),
        ],
      ),
    );
  }
}

class _FitLifeStatCell extends StatelessWidget {
  const _FitLifeStatCell({
    required this.value,
    required this.label,
    required this.gradient,
    required this.icon,
  });

  final String value;
  final String label;
  final List<Color> gradient;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: gradient),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Icon(icon, color: Colors.white, size: 18),
        ),
        const SizedBox(height: 12),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: AppColors.textSecondary,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _FitLifeDivider extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      height: 34,
      margin: const EdgeInsets.symmetric(horizontal: 12),
      color: AppColors.stroke,
    );
  }
}

class _FitLifeSectionHeader extends StatelessWidget {
  const _FitLifeSectionHeader({
    required this.title,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        if (actionLabel != null)
          TextButton(
            onPressed: onAction,
            child: Text(
              actionLabel!,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
      ],
    );
  }
}

class _FitLifeWorkoutPlanRow extends StatelessWidget {
  const _FitLifeWorkoutPlanRow({
    required this.plan,
    required this.selected,
    required this.onTap,
  });

  final Map<String, dynamic> plan;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final days = plan['days'] is List ? (plan['days'] as List).length : 0;
    final duration = plan['estimated_session_minutes']?.toString() ?? '45';

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppColors.surface, AppColors.surfaceStrong],
          ),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: selected ? AppColors.primaryBright : AppColors.stroke,
          ),
          boxShadow: [
            BoxShadow(
              color: (selected ? AppColors.primary : AppColors.shadow)
                  .withValues(alpha: selected ? 0.10 : 0.05),
              blurRadius: selected ? 20 : 12,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(18),
                gradient: LinearGradient(
                  colors: selected
                      ? const [AppColors.primaryBright, AppColors.primary]
                      : [
                          AppColors.primaryBright.withValues(alpha: 0.08),
                          AppColors.primary.withValues(alpha: 0.04),
                        ],
                ),
                border: Border.all(
                  color: selected
                      ? Colors.transparent
                      : AppColors.stroke.withValues(alpha: 0.85),
                ),
              ),
              child: Icon(
                selected
                    ? Icons.play_arrow_rounded
                    : Icons.fitness_center_rounded,
                color: selected ? Colors.white : AppColors.primaryBright,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    plan['name']?.toString() ?? 'Workout plan',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '$days days | $duration mins',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            _FitLifeMiniBadge(
              label: selected ? 'Selected' : 'Load',
              selected: selected,
            ),
          ],
        ),
      ),
    );
  }
}

class _FitLifeMiniBadge extends StatelessWidget {
  const _FitLifeMiniBadge({required this.label, this.selected = false});

  final String label;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: selected
            ? AppColors.primaryBright.withValues(alpha: 0.10)
            : AppColors.backgroundAlt,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: selected
              ? AppColors.primaryBright.withValues(alpha: 0.18)
              : AppColors.stroke,
        ),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: selected ? AppColors.primaryBright : AppColors.textSecondary,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _FitLifePrimaryAction extends StatelessWidget {
  const _FitLifePrimaryAction({
    required this.label,
    required this.icon,
    required this.loading,
    required this.enabled,
    required this.onTap,
    this.gradient = const [AppColors.primaryBright, AppColors.primary],
  });

  final String label;
  final IconData icon;
  final bool loading;
  final bool enabled;
  final VoidCallback onTap;
  final List<Color> gradient;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: enabled || loading ? 1 : 0.58,
      child: InkWell(
        onTap: enabled ? onTap : null,
        borderRadius: BorderRadius.circular(28),
        child: Container(
          height: 56,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: gradient,
            ),
            borderRadius: BorderRadius.circular(28),
            boxShadow: [
              BoxShadow(
                color: gradient.last.withValues(alpha: 0.28),
                blurRadius: 18,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Center(
            child: loading
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2.5,
                    ),
                  )
                : Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(icon, color: Colors.white),
                      const SizedBox(width: 8),
                      Text(
                        label,
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}

class _FitLifeEmptyPanel extends StatelessWidget {
  const _FitLifeEmptyPanel({
    required this.title,
    required this.message,
    required this.icon,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String message;
  final IconData icon;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.surface, AppColors.surfaceStrong],
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.stroke),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              color: AppColors.primaryBright.withValues(alpha: 0.10),
              border: Border.all(
                color: AppColors.primaryBright.withValues(alpha: 0.16),
              ),
            ),
            child: Icon(icon, color: AppColors.primaryBright),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  message,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    height: 1.35,
                  ),
                ),
                if (actionLabel != null && onAction != null) ...[
                  const SizedBox(height: 12),
                  SizedBox(
                    width: 160,
                    height: 36,
                    child: _FitLifeSmallGradientButton(
                      label: actionLabel!,
                      onTap: onAction!,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FitLifeSmallGradientButton extends StatelessWidget {
  const _FitLifeSmallGradientButton({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.stroke),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        child: Center(
          child: Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: AppColors.primaryBright,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ),
    );
  }
}

class _FitLifeAchievementPanel extends StatelessWidget {
  const _FitLifeAchievementPanel({required this.title, required this.message});

  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.primaryBright.withValues(alpha: 0.08),
            AppColors.surface,
          ],
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: AppColors.primaryBright.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.emoji_events_rounded,
              color: AppColors.primaryBright,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(
                    context,
                  ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
                ),
                Text(
                  message,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FitLifeHistoryRow extends StatelessWidget {
  const _FitLifeHistoryRow({
    required this.title,
    required this.subtitle,
    required this.index,
  });

  final String title;
  final String subtitle;
  final int index;

  @override
  Widget build(BuildContext context) {
    final gradients = const [
      [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
      [Color(0xFFEEA4CE), Color(0xFFC58BF2)],
      [Color(0xFFFFC6A5), Color(0xFFFF8D77)],
      [Color(0xFF95E3D7), Color(0xFF4CB8C4)],
    ];
    final gradient = gradients[index % gradients.length];

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 2)],
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(colors: gradient),
            ),
            child: const Icon(Icons.insights_rounded, color: Colors.white),
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
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted),
        ],
      ),
    );
  }
}

class _ActiveWorkoutMiniBar extends StatelessWidget {
  const _ActiveWorkoutMiniBar({
    required this.duration,
    required this.exerciseCount,
    required this.totalVolume,
  });

  final Duration duration;
  final int exerciseCount;
  final double totalVolume;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: const LinearGradient(
          colors: [Color(0xFFEAF6FF), Color(0xFFFFF4FB)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
              ),
            ),
            child: const Icon(Icons.bolt_rounded, color: Colors.white),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'Workout active • ${duration.inMinutes}m • $exerciseCount exercises • ${totalVolume.toStringAsFixed(0)} kg',
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WorkoutExerciseCard extends StatefulWidget {
  const _WorkoutExerciseCard({
    required this.exercise,
    required this.initiallyExpanded,
    required this.previousBest,
    required this.recentHistory,
    required this.historyLoading,
    required this.historyError,
    required this.onLoadHistory,
    required this.onAddSet,
    required this.onDuplicateLastSet,
    required this.onUpdateExerciseNotes,
    required this.onUpdateSet,
    required this.onDeleteSet,
    required this.onStartRest,
  });

  final Map<String, dynamic> exercise;
  final bool initiallyExpanded;
  final Map<String, dynamic>? previousBest;
  final List<Map<String, dynamic>> recentHistory;
  final bool historyLoading;
  final String? historyError;
  final VoidCallback onLoadHistory;
  final VoidCallback onAddSet;
  final VoidCallback onDuplicateLastSet;
  final ValueChanged<String> onUpdateExerciseNotes;
  final void Function(int setIndex, String field, String value) onUpdateSet;
  final ValueChanged<int> onDeleteSet;
  final ValueChanged<int> onStartRest;

  @override
  State<_WorkoutExerciseCard> createState() => _WorkoutExerciseCardState();
}

class _WorkoutExerciseCardState extends State<_WorkoutExerciseCard> {
  late bool _expanded;
  bool _expandedHistory = false;
  late final TextEditingController _notesController;

  @override
  void initState() {
    super.initState();
    _expanded = widget.initiallyExpanded;
    _notesController = TextEditingController(
      text: widget.exercise['notes']?.toString() ?? '',
    );
  }

  @override
  void didUpdateWidget(covariant _WorkoutExerciseCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    final nextText = widget.exercise['notes']?.toString() ?? '';
    if (_notesController.text != nextText) {
      _notesController.text = nextText;
    }
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final exerciseInfo = Map<String, dynamic>.from(
      widget.exercise['exercise'] as Map? ?? const {},
    );
    final sets = (widget.exercise['sets'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final previousBest = widget.previousBest;
    final completedSetCount = sets
        .where(
          (set) =>
              ((set['reps'] as num?)?.toInt() ?? 0) > 0 ||
              ((set['weight'] as num?)?.toDouble() ?? 0) > 0,
        )
        .length;
    final plannedSets =
        (widget.exercise['planned_sets'] as num?)?.toInt() ?? sets.length;
    final plannedReps = widget.exercise['planned_reps']?.toString();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF92A3FD).withValues(alpha: 0.12),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
                  ),
                ),
                child: Center(
                  child: Text(
                    '$completedSetCount/$plannedSets',
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      exerciseInfo['name']?.toString() ?? 'Exercise',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      previousBest == null
                          ? '${sets.length} sets${plannedReps == null ? '' : ' • $plannedReps reps'}'
                          : '${sets.length} sets • Best ${_formatBest(previousBest)}',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  if ((exerciseInfo['muscle_group']?.toString() ?? '')
                      .isNotEmpty)
                    StatusBadge(
                      label: exerciseInfo['muscle_group']?.toString() ?? '',
                      color: const Color(0xFFA78BFA),
                    ),
                  IconButton(
                    onPressed: () => setState(() => _expanded = !_expanded),
                    icon: AnimatedRotation(
                      turns: _expanded ? 0.5 : 0,
                      duration: const Duration(milliseconds: 220),
                      child: const Icon(Icons.keyboard_arrow_down_rounded),
                    ),
                    tooltip: _expanded ? 'Collapse exercise' : 'Log exercise',
                  ),
                ],
              ),
            ],
          ),
          AnimatedCrossFade(
            firstChild: Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Row(
                children: [
                  Expanded(
                    child: LinearProgressIndicator(
                      value: plannedSets <= 0
                          ? 0
                          : (completedSetCount / plannedSets).clamp(0, 1),
                      minHeight: 7,
                      borderRadius: BorderRadius.circular(999),
                      backgroundColor: const Color(0xFFF0F3F8),
                      color: const Color(0xFF92A3FD),
                    ),
                  ),
                  const SizedBox(width: 12),
                  TextButton(
                    onPressed: () => setState(() => _expanded = true),
                    child: const Text('Log sets'),
                  ),
                ],
              ),
            ),
            secondChild: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 14),
                ...sets.asMap().entries.map(
                  (entry) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _WorkoutSetRow(
                      set: entry.value,
                      onChanged: (field, value) =>
                          widget.onUpdateSet(entry.key, field, value),
                      onDelete: () => widget.onDeleteSet(entry.key),
                      onStartRest: () => widget.onStartRest(
                        (entry.value['rest_seconds'] as num?)?.toInt() ??
                            (widget.exercise['rest_timer_seconds'] as num?)
                                ?.toInt() ??
                            45,
                      ),
                    ),
                  ),
                ),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    QuickActionButton(
                      label: 'Add set',
                      icon: Icons.add_rounded,
                      onTap: widget.onAddSet,
                    ),
                    QuickActionButton(
                      label: 'Duplicate last',
                      icon: Icons.copy_rounded,
                      onTap: widget.onDuplicateLastSet,
                    ),
                    QuickActionButton(
                      label: _expandedHistory
                          ? 'Hide history'
                          : 'Recent history',
                      icon: Icons.history_rounded,
                      onTap: () {
                        final nextValue = !_expandedHistory;
                        setState(() => _expandedHistory = nextValue);
                        if (nextValue) {
                          widget.onLoadHistory();
                        }
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _notesController,
                  maxLines: 2,
                  onChanged: widget.onUpdateExerciseNotes,
                  decoration: const InputDecoration(
                    labelText: 'Exercise notes',
                    hintText: 'Add notes for form, tempo, or fatigue',
                  ),
                ),
              ],
            ),
            crossFadeState: _expanded
                ? CrossFadeState.showSecond
                : CrossFadeState.showFirst,
            duration: const Duration(milliseconds: 220),
            sizeCurve: Curves.easeOutCubic,
          ),
          if (_expanded && _expandedHistory) ...[
            const SizedBox(height: 14),
            if (widget.historyLoading)
              const LoadingStateView(
                label: 'Loading recent history for this exercise...',
              )
            else if (widget.historyError != null)
              ErrorStateView(
                message: widget.historyError!,
                onRetry: widget.onLoadHistory,
              )
            else if (widget.recentHistory.isEmpty)
              const EmptyStateView(
                title: 'No recent history',
                message:
                    'Previous sessions for this exercise will show here after more workouts.',
                icon: Icons.history_toggle_off_rounded,
              )
            else
              ...widget.recentHistory
                  .take(3)
                  .map(
                    (session) => Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF7F8F8),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Text(
                        '${session['session_date'] ?? 'Recent'} • Volume ${((session['total_volume'] as num?)?.toDouble() ?? 0).toStringAsFixed(0)} kg',
                      ),
                    ),
                  ),
          ],
        ],
      ),
    );
  }

  String _formatBest(Map<String, dynamic> record) {
    final weight = (record['best_weight'] as num?)?.toDouble() ?? 0;
    final reps = (record['best_reps'] as num?)?.toInt() ?? 0;
    return '${weight.toStringAsFixed(0)}kg x $reps';
  }
}

class _WorkoutSetRow extends StatelessWidget {
  const _WorkoutSetRow({
    required this.set,
    required this.onChanged,
    required this.onDelete,
    required this.onStartRest,
  });

  final Map<String, dynamic> set;
  final void Function(String field, String value) onChanged;
  final VoidCallback onDelete;
  final VoidCallback onStartRest;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F8F8),
        borderRadius: BorderRadius.circular(18),
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 520;
          return Column(
            children: [
              if (compact) ...[
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Set ${(set['set_number'] as num?)?.toInt() ?? 1}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        initialValue:
                            ((set['weight'] as num?)?.toDouble() ?? 0) == 0
                            ? ''
                            : ((set['weight'] as num?)?.toDouble() ?? 0)
                                  .toStringAsFixed(0),
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(labelText: 'kg'),
                        onChanged: (value) => onChanged('weight', value),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextFormField(
                        initialValue: ((set['reps'] as num?)?.toInt() ?? 0) == 0
                            ? ''
                            : '${(set['reps'] as num?)?.toInt() ?? 0}',
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(labelText: 'reps'),
                        onChanged: (value) => onChanged('reps', value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                TextFormField(
                  initialValue:
                      '${(set['rest_seconds'] as num?)?.toInt() ?? 45}',
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Rest (sec)'),
                  onChanged: (value) => onChanged('rest_seconds', value),
                ),
                const SizedBox(height: 10),
                TextFormField(
                  initialValue: set['notes']?.toString() ?? '',
                  decoration: const InputDecoration(labelText: 'Set notes'),
                  onChanged: (value) => onChanged('notes', value),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: onStartRest,
                        icon: const Icon(Icons.timer_outlined),
                        label: const Text('Rest'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    IconButton(
                      onPressed: onDelete,
                      icon: const Icon(Icons.delete_outline_rounded),
                      color: AppColors.error,
                      tooltip: 'Delete set',
                    ),
                  ],
                ),
              ] else ...[
                Row(
                  children: [
                    SizedBox(
                      width: 70,
                      child: Text(
                        'Set ${(set['set_number'] as num?)?.toInt() ?? 1}',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                    ),
                    Expanded(
                      child: TextFormField(
                        initialValue:
                            ((set['weight'] as num?)?.toDouble() ?? 0) == 0
                            ? ''
                            : ((set['weight'] as num?)?.toDouble() ?? 0)
                                  .toStringAsFixed(0),
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(labelText: 'kg'),
                        onChanged: (value) => onChanged('weight', value),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextFormField(
                        initialValue: ((set['reps'] as num?)?.toInt() ?? 0) == 0
                            ? ''
                            : '${(set['reps'] as num?)?.toInt() ?? 0}',
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(labelText: 'reps'),
                        onChanged: (value) => onChanged('reps', value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        initialValue:
                            '${(set['rest_seconds'] as num?)?.toInt() ?? 45}',
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Rest (sec)',
                        ),
                        onChanged: (value) => onChanged('rest_seconds', value),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextFormField(
                        initialValue: set['notes']?.toString() ?? '',
                        decoration: const InputDecoration(
                          labelText: 'Set notes',
                        ),
                        onChanged: (value) => onChanged('notes', value),
                      ),
                    ),
                    const SizedBox(width: 10),
                    SizedBox(
                      width: 108,
                      child: OutlinedButton.icon(
                        onPressed: onStartRest,
                        icon: const Icon(Icons.timer_outlined),
                        label: const Text('Rest'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton(
                      onPressed: onDelete,
                      icon: const Icon(Icons.delete_outline_rounded),
                      color: AppColors.error,
                      tooltip: 'Delete set',
                    ),
                  ],
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _RestTimerOverlay extends StatelessWidget {
  const _RestTimerOverlay({
    required this.exerciseName,
    required this.remainingSeconds,
    required this.totalSeconds,
  });

  final String exerciseName;
  final int remainingSeconds;
  final int totalSeconds;

  @override
  Widget build(BuildContext context) {
    final total = totalSeconds <= 0 ? 1 : totalSeconds;
    final progress = remainingSeconds / total;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: const LinearGradient(
          colors: [Color(0xFFFFF2E8), Color(0xFFFFFFFF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFF59E0B).withValues(alpha: 0.16),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Rest timer',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text('Recover for $exerciseName'),
          const SizedBox(height: 14),
          LinearProgressIndicator(value: progress.clamp(0, 1)),
          const SizedBox(height: 10),
          Text(
            '$remainingSeconds seconds remaining',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _AddExerciseSheet extends StatefulWidget {
  const _AddExerciseSheet({required this.exercises});

  final List<Map<String, dynamic>> exercises;

  @override
  State<_AddExerciseSheet> createState() => _AddExerciseSheetState();
}

class _AddExerciseSheetState extends State<_AddExerciseSheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final filtered = widget.exercises.where((exercise) {
      final info = Map<String, dynamic>.from(
        exercise['exercise'] as Map? ?? const {},
      );
      final name = info['name']?.toString().toLowerCase() ?? '';
      final group = info['muscle_group']?.toString().toLowerCase() ?? '';
      final query = _query.trim().toLowerCase();
      if (query.isEmpty) {
        return true;
      }
      return name.contains(query) || group.contains(query);
    }).toList();

    return SafeArea(
      child: Container(
        decoration: const BoxDecoration(
          color: Color(0xFFFFFBF4),
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        child: Padding(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 20,
            bottom: MediaQuery.of(context).viewInsets.bottom + 20,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Add Exercise',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                onChanged: (value) => setState(() => _query = value),
                decoration: const InputDecoration(
                  labelText: 'Search exercise library',
                  prefixIcon: Icon(Icons.search_rounded),
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                height: MediaQuery.of(context).size.height * 0.56,
                child: filtered.isEmpty
                    ? const EmptyStateView(
                        title: 'No matching exercises',
                        message:
                            'Try a different search term or use the available assigned workout exercises.',
                        icon: Icons.search_off_rounded,
                      )
                    : ListView.separated(
                        itemCount: filtered.length,
                        separatorBuilder: (_, __) =>
                            const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, index) {
                          final exercise = filtered[index];
                          final info = Map<String, dynamic>.from(
                            exercise['exercise'] as Map? ?? const {},
                          );
                          return Container(
                            margin: EdgeInsets.zero,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(20),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.05),
                                  blurRadius: 14,
                                  offset: const Offset(0, 8),
                                ),
                              ],
                            ),
                            child: InkWell(
                              onTap: () => Navigator.of(context).pop(exercise),
                              child: Row(
                                children: [
                                  const SizedBox(width: AppSpacing.md),
                                  Container(
                                    width: 44,
                                    height: 44,
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(16),
                                      color: AppColors.primary.withValues(
                                        alpha: 0.12,
                                      ),
                                    ),
                                    child: const Icon(
                                      Icons.fitness_center_rounded,
                                      color: AppColors.primaryBright,
                                    ),
                                  ),
                                  const SizedBox(width: AppSpacing.md),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          info['name']?.toString() ??
                                              'Exercise',
                                          style: Theme.of(
                                            context,
                                          ).textTheme.titleMedium,
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          '${info['muscle_group']?.toString() ?? 'General'} • ${exercise['sets'] ?? 0} sets • ${exercise['reps'] ?? '--'} reps',
                                          style: Theme.of(
                                            context,
                                          ).textTheme.bodySmall,
                                        ),
                                      ],
                                    ),
                                  ),
                                  const Icon(Icons.chevron_right_rounded),
                                  const SizedBox(width: AppSpacing.md),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _WorkoutCompletionSummary extends StatelessWidget {
  const _WorkoutCompletionSummary({required this.summary});

  final Map<String, dynamic> summary;

  @override
  Widget build(BuildContext context) {
    final muscleGroups = Map<String, dynamic>.from(
      summary['muscle_groups'] as Map? ?? const {},
    );
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: const LinearGradient(
          colors: [Color(0xFFE9FFF8), Color(0xFFFFFFFF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF34D399).withValues(alpha: 0.14),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            summary['has_pr'] == true
                ? 'Workout summary • PR unlocked'
                : 'Workout summary',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            'Total volume: ${((summary['total_volume'] as num?)?.toDouble() ?? 0).toStringAsFixed(0)} kg',
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          Text(
            'Duration: ${_formatDuration(summary['duration'] as Duration? ?? Duration.zero)}',
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          Text(
            'Exercises completed: ${summary['exercises_completed'] ?? 0}',
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 10),
          if (muscleGroups.isNotEmpty)
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: muscleGroups.entries
                  .map(
                    (entry) => StatusBadge(
                      label: '${entry.key} ${entry.value}',
                      color: const Color(0xFF22D3EE),
                    ),
                  )
                  .toList(),
            ),
        ],
      ),
    );
  }

  String _formatDuration(Duration duration) {
    final hours = duration.inHours;
    final minutes = duration.inMinutes.remainder(60);
    final seconds = duration.inSeconds.remainder(60);
    if (hours > 0) {
      return '${hours}h ${minutes}m';
    }
    if (minutes > 0) {
      return '${minutes}m ${seconds}s';
    }
    return '${seconds}s';
  }
}

class _UnreadBellAction extends StatelessWidget {
  const _UnreadBellAction({required this.unreadCount, required this.onPressed});

  final int unreadCount;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return PulseGlow(
      enabled: unreadCount > 0,
      pulseScale: 1.05,
      glowColor: Theme.of(context).colorScheme.secondary,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          IconButton(
            onPressed: onPressed,
            icon: Icon(
              unreadCount > 0
                  ? Icons.notifications_active_rounded
                  : Icons.notifications_none_rounded,
            ),
          ),
          if (unreadCount > 0)
            Positioned(
              right: 8,
              top: 8,
              child: Container(
                constraints: const BoxConstraints(minWidth: 18),
                height: 18,
                padding: const EdgeInsets.symmetric(horizontal: 4),
                decoration: BoxDecoration(
                  color: AppColors.success,
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: AppColors.background, width: 1.5),
                ),
                alignment: Alignment.center,
                child: Text(
                  unreadCount > 9 ? '9+' : '$unreadCount',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Colors.black,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _DiscoveryPage extends StatefulWidget {
  const _DiscoveryPage({
    required this.publicGyms,
    required this.savedGyms,
    required this.notifications,
    required this.onRefresh,
    required this.onRequestTrial,
    required this.onViewProfile,
    required this.onToggleSaved,
  });

  final List<Map<String, dynamic>> publicGyms;
  final List<Map<String, dynamic>> savedGyms;
  final List<Map<String, dynamic>> notifications;
  final Future<void> Function() onRefresh;
  final Future<void> Function(Map<String, dynamic> gym) onRequestTrial;
  final Future<void> Function(Map<String, dynamic> gym) onViewProfile;
  final Future<void> Function(Map<String, dynamic> gym) onToggleSaved;

  @override
  State<_DiscoveryPage> createState() => _DiscoveryPageState();
}

class _DiscoveryPageState extends State<_DiscoveryPage> {
  final TextEditingController _searchController = TextEditingController();
  bool _openNowOnly = false;
  bool _trialOnly = false;
  bool _ptOnly = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final savedGymIds = widget.savedGyms
        .map((gym) => (gym['id'] as num?)?.toInt())
        .whereType<int>()
        .toSet();
    final query = _searchController.text.trim().toLowerCase();
    final filteredGyms = widget.publicGyms.where((gym) {
      final title = gym['name']?.toString().toLowerCase() ?? '';
      final city = gym['city']?.toString().toLowerCase() ?? '';
      final facilities = (gym['facilities'] as List<dynamic>? ?? const [])
          .map(
            (item) => item is Map
                ? (item['name']?.toString() ?? '').toLowerCase()
                : item.toString().toLowerCase(),
          )
          .join(' ');

      if (query.isNotEmpty &&
          !title.contains(query) &&
          !city.contains(query) &&
          !facilities.contains(query)) {
        return false;
      }

      if (_openNowOnly && gym['is_open_now'] != true) {
        return false;
      }

      if (_trialOnly && gym['trial_available'] != true) {
        return false;
      }

      if (_ptOnly && gym['personal_training_available'] != true) {
        return false;
      }

      return true;
    }).toList();

    return RefreshIndicator(
      onRefresh: widget.onRefresh,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          RevealOnBuild(
            child: PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Public gym discovery',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _searchController,
                    onChanged: (_) => setState(() {}),
                    decoration: InputDecoration(
                      hintText: 'Search by gym, city, or facility',
                      prefixIcon: const Icon(Icons.search_rounded),
                      suffixIcon: query.isEmpty
                          ? null
                          : IconButton(
                              onPressed: () {
                                _searchController.clear();
                                setState(() {});
                              },
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        FilterChip(
                          selected: _openNowOnly,
                          label: const Text('Open now'),
                          onSelected: (value) =>
                              setState(() => _openNowOnly = value),
                        ),
                        const SizedBox(width: 8),
                        FilterChip(
                          selected: _trialOnly,
                          label: const Text('Trial available'),
                          onSelected: (value) =>
                              setState(() => _trialOnly = value),
                        ),
                        const SizedBox(width: 8),
                        FilterChip(
                          selected: _ptOnly,
                          label: const Text('Personal Training'),
                          onSelected: (value) =>
                              setState(() => _ptOnly = value),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (widget.savedGyms.isNotEmpty) ...[
                    Text(
                      'Saved gyms',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 10),
                    SizedBox(
                      height: 110,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: widget.savedGyms.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 10),
                        itemBuilder: (context, index) {
                          final gym = widget.savedGyms[index];
                          return SizedBox(
                            width: 220,
                            child: PremiumCard(
                              child: InkWell(
                                onTap: () => widget.onViewProfile(gym),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      gym['name']?.toString() ?? 'Saved gym',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.titleMedium,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      gym['city']?.toString() ?? 'Nearby',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.bodySmall,
                                    ),
                                    const Spacer(),
                                    const StatusBadge(
                                      label: 'Saved',
                                      icon: Icons.bookmark_rounded,
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    const SizedBox(height: 14),
                  ],
                  if (filteredGyms.isEmpty)
                    SizedBox(
                      width: double.infinity,
                      child: EmptyStateView(
                        title:
                            query.isEmpty &&
                                !_openNowOnly &&
                                !_trialOnly &&
                                !_ptOnly
                            ? 'No gyms found'
                            : 'No gyms match these filters',
                        message:
                            'Try increasing distance or refreshing discovery to load more nearby gyms.',
                        icon: Icons.travel_explore_rounded,
                        action: SizedBox(
                          width: 220,
                          child: GradientButton(
                            label: 'Refresh Discovery',
                            icon: Icons.refresh_rounded,
                            expanded: true,
                            onPressed: widget.onRefresh,
                          ),
                        ),
                      ),
                    )
                  else
                    ...filteredGyms.asMap().entries.map(
                      (entry) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: RevealOnBuild(
                          delay: Duration(milliseconds: 50 * entry.key),
                          child: _GymDiscoveryCard(
                            gym: entry.value,
                            isSaved: savedGymIds.contains(
                              (entry.value['id'] as num?)?.toInt(),
                            ),
                            onViewProfile: () =>
                                widget.onViewProfile(entry.value),
                            onRequestTrial: () =>
                                widget.onRequestTrial(entry.value),
                            onToggleSaved: () =>
                                widget.onToggleSaved(entry.value),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          RevealOnBuild(
            delay: const Duration(milliseconds: 120),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Notifications',
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: 12),
                    ...widget.notifications
                        .take(8)
                        .toList()
                        .asMap()
                        .entries
                        .map(
                          (entry) => RevealOnBuild(
                            delay: Duration(milliseconds: 40 * entry.key),
                            child: ListTile(
                              title: Text(
                                entry.value['title']?.toString() ??
                                    'Notification',
                              ),
                              subtitle: Text(
                                entry.value['body']?.toString() ?? '',
                              ),
                            ),
                          ),
                        ),
                    if (widget.notifications.isEmpty)
                      const EmptyStateView(
                        title: 'No notifications yet',
                        message:
                            'Membership reminders, dues updates, and gym announcements will show up here.',
                        icon: Icons.notifications_none_rounded,
                      ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _GymDiscoveryCard extends StatelessWidget {
  const _GymDiscoveryCard({
    required this.gym,
    required this.isSaved,
    required this.onViewProfile,
    required this.onRequestTrial,
    required this.onToggleSaved,
  });

  final Map<String, dynamic> gym;
  final bool isSaved;
  final VoidCallback onViewProfile;
  final VoidCallback onRequestTrial;
  final VoidCallback onToggleSaved;

  @override
  Widget build(BuildContext context) {
    final feeSummary = Map<String, dynamic>.from(
      gym['fee_summary'] as Map? ?? const {},
    );
    final gallery = (gym['photo_urls'] as List<dynamic>? ?? const [])
        .map((item) => item.toString())
        .where((item) => item.isNotEmpty)
        .toList();
    final facilities = (gym['facilities'] as List<dynamic>? ?? const [])
        .map(
          (item) => item is Map
              ? (item['name']?.toString() ?? item.toString())
              : item.toString(),
        )
        .take(4)
        .toList();
    final isOpen = gym['is_open_now'] == true;
    final distance =
        gym['distance_km']?.toString() ?? gym['distance']?.toString();
    final showFees = gym['pricing_visible'] == true && feeSummary.isNotEmpty;
    final heroImage =
        gym['cover_image_url']?.toString() ??
        (gallery.isNotEmpty ? gallery.first : null) ??
        gym['logo_url']?.toString();

    return GlassCard(
      gradient: const LinearGradient(
        colors: [Color(0x221C3856), Color(0x33111A28)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AppNetworkImage(
            imageUrl: heroImage,
            height: 160,
            width: double.infinity,
            borderRadius: 22,
            placeholderIcon: Icons.storefront_rounded,
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      gym['name']?.toString() ?? 'Gym',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        if (gym['is_verified'] == true)
                          const StatusBadge(
                            label: 'Verified',
                            icon: Icons.verified_rounded,
                          ),
                        if (gym['is_featured'] == true)
                          const StatusBadge(
                            label: 'Featured',
                            icon: Icons.workspace_premium_rounded,
                          ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      [
                        if (distance != null && distance.isNotEmpty)
                          '$distance away',
                        if (gym['city']?.toString().isNotEmpty == true)
                          gym['city'].toString(),
                      ].join(' • '),
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: isOpen ? 'Open' : 'Closed',
                color: isOpen ? AppColors.success : AppColors.warning,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (showFees)
                StatusBadge(
                  label:
                      'From ${feeSummary['min_price']?.toString() ?? feeSummary['starting_fee']?.toString() ?? 'NA'}',
                  color: AppColors.primaryBright,
                  icon: Icons.currency_rupee_rounded,
                ),
              StatusBadge(
                label: gym['trial_available'] == true
                    ? 'Trial available'
                    : 'No trial',
                color: gym['trial_available'] == true
                    ? AppColors.accent
                    : AppColors.textSecondary,
              ),
              const StatusBadge(
                label: '4.8 rating',
                color: AppColors.warning,
                icon: Icons.star_rounded,
              ),
            ],
          ),
          if (facilities.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: facilities
                  .map(
                    (facility) => StatusBadge(
                      label: facility,
                      color: AppColors.textSecondary,
                    ),
                  )
                  .toList(),
            ),
          ],
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onViewProfile,
                  icon: const Icon(Icons.remove_red_eye_rounded, size: 18),
                  label: const Text('View Profile'),
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              IconButton(
                onPressed: onToggleSaved,
                icon: Icon(
                  isSaved
                      ? Icons.bookmark_rounded
                      : Icons.bookmark_border_rounded,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: GradientButton(
                  onPressed: gym['trial_available'] == true
                      ? onRequestTrial
                      : null,
                  label: gym['trial_available'] == true
                      ? 'Start Trial'
                      : 'Trial unavailable',
                  icon: Icons.flash_on_rounded,
                  expanded: true,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ignore: unused_element
class _PublicGymProfileSheet extends StatelessWidget {
  const _PublicGymProfileSheet({
    required this.detail,
    required this.onRequestTrial,
  });

  final Map<String, dynamic> detail;
  final Future<void> Function() onRequestTrial;

  @override
  Widget build(BuildContext context) {
    final gallery = {
      if ((detail['cover_image_url']?.toString() ?? '').isNotEmpty)
        detail['cover_image_url'].toString(),
      ...((detail['photo_urls'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .where((item) => item.isNotEmpty)),
    }.toList();
    final facilities = (detail['facilities'] as List<dynamic>? ?? const [])
        .map(
          (item) => item is Map
              ? (item['name']?.toString() ?? item.toString())
              : item.toString(),
        )
        .where((item) => item.isNotEmpty)
        .toList();
    final trainers = (detail['trainers'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final plans = (detail['fees'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final whyJoin = (detail['why_join'] as List<dynamic>? ?? const [])
        .map((item) => item.toString())
        .where((item) => item.isNotEmpty)
        .toList();
    final isOpen = detail['is_open_now'] == true;
    final pricingVisible = detail['pricing_visible'] == true;
    final feeSummary = Map<String, dynamic>.from(
      detail['fee_summary'] as Map? ?? const {},
    );
    final contactAction = Map<String, dynamic>.from(
      detail['contact_action'] as Map? ?? const {},
    );
    final canRequestContact = contactAction['enabled'] == true;
    final distance = detail['distance_km']?.toString();

    return FractionallySizedBox(
      heightFactor: 0.94,
      child: Container(
        decoration: const BoxDecoration(
          color: Color(0xFF081018),
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        child: SafeArea(
          top: false,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.only(top: 12, bottom: 8),
                child: Container(
                  width: 48,
                  height: 5,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.18),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
                  children: [
                    RevealOnBuild(
                      child: GlassCard(
                        padding: EdgeInsets.zero,
                        gradient: const LinearGradient(
                          colors: [Color(0x551A3150), Color(0x55111A27)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Stack(
                              children: [
                                AppNetworkImage(
                                  imageUrl: gallery.isNotEmpty
                                      ? gallery.first
                                      : null,
                                  height: 228,
                                  width: double.infinity,
                                  borderRadius: 24,
                                  placeholderIcon: Icons.fitness_center_rounded,
                                ),
                                Positioned(
                                  left: 16,
                                  right: 16,
                                  bottom: 16,
                                  child: Wrap(
                                    spacing: 8,
                                    runSpacing: 8,
                                    children: [
                                      if (detail['is_verified'] == true)
                                        const StatusBadge(
                                          label: 'Verified',
                                          icon: Icons.verified_rounded,
                                        ),
                                      StatusBadge(
                                        label: isOpen ? 'Open' : 'Closed',
                                        color: isOpen
                                            ? AppColors.success
                                            : AppColors.error,
                                      ),
                                      if (distance != null &&
                                          distance.isNotEmpty)
                                        StatusBadge(
                                          label: '$distance away',
                                          color: AppColors.primaryBright,
                                          icon: Icons.near_me_rounded,
                                        ),
                                      if (detail['trial_available'] == true)
                                        const StatusBadge(
                                          label: 'Trial',
                                          icon: Icons.flash_on_rounded,
                                        ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            Padding(
                              padding: const EdgeInsets.all(AppSpacing.lg),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    detail['name']?.toString() ?? 'Gym',
                                    style: Theme.of(context)
                                        .textTheme
                                        .headlineSmall
                                        ?.copyWith(fontWeight: FontWeight.w800),
                                  ),
                                  const SizedBox(height: AppSpacing.xs),
                                  Text(
                                    [
                                          detail['address_line']?.toString() ??
                                              '',
                                          detail['city']?.toString() ?? '',
                                          detail['state']?.toString() ?? '',
                                        ]
                                        .where((item) => item.trim().isNotEmpty)
                                        .join(', '),
                                    style: Theme.of(
                                      context,
                                    ).textTheme.bodyMedium,
                                  ),
                                  const SizedBox(height: AppSpacing.md),
                                  Wrap(
                                    spacing: 12,
                                    runSpacing: 12,
                                    children: [
                                      if (pricingVisible &&
                                          feeSummary.isNotEmpty)
                                        _InlineInfoChip(
                                          icon: Icons.currency_rupee_rounded,
                                          label:
                                              'Starts at ${feeSummary['min_price'] ?? '--'}',
                                        ),
                                      _InlineInfoChip(
                                        icon: Icons.schedule_rounded,
                                        label: _timingSummary(
                                          detail['timings'],
                                        ),
                                      ),
                                      _InlineInfoChip(
                                        icon: Icons.event_busy_rounded,
                                        label: _weeklyOffSummary(
                                          detail['weekly_off'],
                                        ),
                                      ),
                                    ],
                                  ),
                                  if ((detail['description']?.toString() ?? '')
                                      .trim()
                                      .isNotEmpty) ...[
                                    const SizedBox(height: AppSpacing.md),
                                    Text(
                                      detail['description'].toString(),
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyMedium
                                          ?.copyWith(height: 1.5),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    if (gallery.length > 1) ...[
                      const SizedBox(height: AppSpacing.lg),
                      _ProfileSection(
                        title: 'Gallery',
                        subtitle: 'A quick look inside the training space.',
                        child: SizedBox(
                          height: 98,
                          child: ListView.separated(
                            scrollDirection: Axis.horizontal,
                            itemBuilder: (context, index) => AppNetworkImage(
                              imageUrl: gallery[index],
                              height: 98,
                              width: 132,
                              borderRadius: 18,
                              placeholderIcon: Icons.photo_library_rounded,
                            ),
                            separatorBuilder: (_, __) =>
                                const SizedBox(width: AppSpacing.sm),
                            itemCount: gallery.length,
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: AppSpacing.lg),
                    _ProfileSection(
                      title: 'Why join this gym?',
                      subtitle:
                          'Public highlights based on the current listing.',
                      child: whyJoin.isEmpty
                          ? const EmptyStateView(
                              title: 'More details coming soon',
                              message:
                                  'The gym has not added more public highlights yet.',
                              icon: Icons.auto_awesome_rounded,
                            )
                          : Column(
                              children: whyJoin
                                  .map(
                                    (reason) => Padding(
                                      padding: const EdgeInsets.only(
                                        bottom: AppSpacing.sm,
                                      ),
                                      child: Row(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          const Padding(
                                            padding: EdgeInsets.only(top: 4),
                                            child: Icon(
                                              Icons.check_circle_rounded,
                                              color: AppColors.success,
                                              size: 18,
                                            ),
                                          ),
                                          const SizedBox(width: 10),
                                          Expanded(
                                            child: Text(
                                              reason,
                                              style: Theme.of(
                                                context,
                                              ).textTheme.bodyMedium,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  )
                                  .toList(),
                            ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _ProfileSection(
                      title: 'Facilities',
                      subtitle: 'Amenities shown on the public listing.',
                      child: facilities.isEmpty
                          ? const EmptyStateView(
                              title: 'Facility details coming soon',
                              message:
                                  'This gym has not listed its facility highlights yet.',
                              icon: Icons.grid_view_rounded,
                            )
                          : Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: facilities
                                  .map(
                                    (facility) => StatusBadge(
                                      label: facility,
                                      color: AppColors.textSecondary,
                                    ),
                                  )
                                  .toList(),
                            ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _ProfileSection(
                      title: 'Coaching team',
                      subtitle: 'Trainers currently visible on this profile.',
                      child: trainers.isEmpty
                          ? const EmptyStateView(
                              title: 'Trainer profiles coming soon',
                              message:
                                  'The gym has not added public trainer highlights yet.',
                              icon: Icons.groups_rounded,
                            )
                          : SizedBox(
                              height: 210,
                              child: ListView.separated(
                                scrollDirection: Axis.horizontal,
                                itemCount: trainers.length,
                                separatorBuilder: (_, __) =>
                                    const SizedBox(width: AppSpacing.md),
                                itemBuilder: (context, index) =>
                                    _TrainerPreviewCard(
                                      trainer: trainers[index],
                                    ),
                              ),
                            ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _ProfileSection(
                      title: 'Membership plans',
                      subtitle: pricingVisible
                          ? 'Visible pricing from the public listing.'
                          : 'Pricing is currently hidden by this gym.',
                      child: !pricingVisible
                          ? const EmptyStateView(
                              title: 'Pricing hidden',
                              message:
                                  'Use the trial action to connect with the gym and get the latest plan details.',
                              icon: Icons.visibility_off_rounded,
                            )
                          : plans.isEmpty
                          ? const EmptyStateView(
                              title: 'No public plans yet',
                              message:
                                  'The gym has not published membership plans yet.',
                              icon: Icons.sell_rounded,
                            )
                          : Column(
                              children: plans
                                  .map(
                                    (plan) => Padding(
                                      padding: const EdgeInsets.only(
                                        bottom: AppSpacing.sm,
                                      ),
                                      child: _PlanPreviewCard(plan: plan),
                                    ),
                                  )
                                  .toList(),
                            ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _ProfileSection(
                      title: 'Location',
                      subtitle: 'Map preview placeholder',
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(AppSpacing.lg),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(20),
                          gradient: const LinearGradient(
                            colors: [Color(0x331B3A5A), Color(0x22131A26)],
                          ),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.08),
                          ),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Row(
                              children: [
                                Icon(
                                  Icons.map_rounded,
                                  color: AppColors.primaryBright,
                                ),
                                SizedBox(width: 10),
                                Text('Map preview placeholder'),
                              ],
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            Text(
                              [
                                    detail['address_line']?.toString() ?? '',
                                    detail['city']?.toString() ?? '',
                                    detail['state']?.toString() ?? '',
                                    detail['country']?.toString() ?? '',
                                  ]
                                  .where((item) => item.trim().isNotEmpty)
                                  .join(', '),
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                            if (detail['latitude'] != null &&
                                detail['longitude'] != null) ...[
                              const SizedBox(height: AppSpacing.xs),
                              Text(
                                'Coordinates: ${detail['latitude']}, ${detail['longitude']}',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _ProfileSection(
                      title: 'Reviews',
                      subtitle: 'Community feedback placeholder',
                      child: const EmptyStateView(
                        title: 'Reviews coming soon',
                        message:
                            'Ratings and member reviews will appear here in a future public listing update.',
                        icon: Icons.reviews_rounded,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: canRequestContact
                                ? onRequestTrial
                                : null,
                            icon: const Icon(Icons.call_rounded, size: 18),
                            label: const Text('Contact Gym'),
                          ),
                        ),
                        const SizedBox(width: AppSpacing.md),
                        Expanded(
                          child: GradientButton(
                            onPressed: detail['trial_available'] == true
                                ? onRequestTrial
                                : null,
                            label: detail['trial_available'] == true
                                ? 'Start Trial'
                                : 'Trial unavailable',
                            icon: Icons.flash_on_rounded,
                            expanded: true,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _timingSummary(dynamic value) {
    if (value is! Map || value.isEmpty) {
      return 'Timing details soon';
    }

    final preferredKeys = [
      'all_days',
      'monday_to_saturday',
      'weekdays',
      'monday',
    ];

    for (final key in preferredKeys) {
      final slot = value[key];
      if (slot is Map && slot['open'] != null && slot['close'] != null) {
        return '${slot['open']} - ${slot['close']}';
      }
    }

    return 'See branch timings';
  }

  static String _weeklyOffSummary(dynamic value) {
    if (value is! List || value.isEmpty) {
      return 'No weekly off listed';
    }

    final labels = value
        .map((item) => item.toString())
        .where((item) => item.isNotEmpty);

    return 'Weekly off: ${labels.join(', ')}';
  }
}

class _ProfileSection extends StatelessWidget {
  const _ProfileSection({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  final String title;
  final String subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return RevealOnBuild(
      child: PremiumCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: AppSpacing.xs),
            Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: AppSpacing.md),
            child,
          ],
        ),
      ),
    );
  }
}

class _InlineInfoChip extends StatelessWidget {
  const _InlineInfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: Colors.white.withValues(alpha: 0.06),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: AppColors.primaryBright),
          const SizedBox(width: 8),
          Text(label, style: Theme.of(context).textTheme.labelLarge),
        ],
      ),
    );
  }
}

class _TrainerPreviewCard extends StatelessWidget {
  const _TrainerPreviewCard({required this.trainer});

  final Map<String, dynamic> trainer;

  @override
  Widget build(BuildContext context) {
    final specializations =
        (trainer['specializations'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .where((item) => item.isNotEmpty)
            .take(2)
            .toList();
    final branch = _recordMap(trainer['assigned_branch']);
    final availability =
        (trainer['availability_slots'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .where((item) => item.isNotEmpty)
            .take(2)
            .toList();
    final programs =
        (_recordMap(trainer['programs_offered_placeholder'])['items']
                    as List<dynamic>? ??
                const [])
            .map((item) => item.toString())
            .where((item) => item.isNotEmpty)
            .take(2)
            .toList();

    return SizedBox(
      width: 220,
      child: GlassCard(
        gradient: const LinearGradient(
          colors: [Color(0x221B3656), Color(0x22111822)],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 24,
                  backgroundColor: Colors.white.withValues(alpha: 0.08),
                  backgroundImage:
                      ((trainer['profile_photo_url']?.toString() ?? '')
                                  .isNotEmpty
                              ? NetworkImage(
                                  trainer['profile_photo_url'].toString(),
                                )
                              : (trainer['photo']?.toString() ?? '').isNotEmpty
                              ? NetworkImage(trainer['photo'].toString())
                              : null)
                          as ImageProvider<Object>?,
                  child:
                      ((trainer['profile_photo_url']?.toString() ?? '')
                              .isEmpty &&
                          (trainer['photo']?.toString() ?? '').isEmpty)
                      ? const Icon(Icons.person_rounded)
                      : null,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    trainer['name']?.toString() ?? 'Trainer',
                    style: Theme.of(context).textTheme.titleMedium,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              trainer['experience_label']?.toString() ??
                  '${trainer['experience_years'] ?? 0} yrs experience',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            if (branch.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                branch['name']?.toString() ?? 'Assigned branch',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            if (specializations.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.sm),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: specializations
                    .map(
                      (item) => StatusBadge(
                        label: item,
                        color: AppColors.textSecondary,
                      ),
                    )
                    .toList(),
              ),
            ],
            if ((trainer['bio']?.toString() ?? '').trim().isNotEmpty) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                trainer['bio'].toString(),
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            if (availability.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                availability.join(' • '),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            const SizedBox(height: AppSpacing.sm),
            Text(
              trainer['rating_placeholder']?['label']?.toString() ??
                  'Rating coming soon',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            if (programs.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                programs.join(' • '),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _PlanPreviewCard extends StatelessWidget {
  const _PlanPreviewCard({required this.plan});

  final Map<String, dynamic> plan;

  @override
  Widget build(BuildContext context) {
    final price = (plan['plan_price'] as num?)?.toDouble() ?? 0;
    final joiningFee = (plan['joining_fee'] as num?)?.toDouble() ?? 0;
    final durationDays = (plan['duration_days'] as num?)?.toInt() ?? 0;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        color: Colors.white.withValues(alpha: 0.04),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  plan['name']?.toString() ?? 'Plan',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              StatusBadge(
                label:
                    '${_formatCurrency(price)} / ${_durationLabel(durationDays)}',
                color: AppColors.primaryBright,
                icon: Icons.currency_rupee_rounded,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusBadge(
                label: 'Joining ${_formatCurrency(joiningFee)}',
                color: AppColors.textSecondary,
              ),
              StatusBadge(
                label: plan['pt_included'] == true
                    ? 'PT included'
                    : 'PT optional',
                color: plan['pt_included'] == true
                    ? AppColors.success
                    : AppColors.warning,
              ),
            ],
          ),
          if ((plan['description']?.toString() ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(
              plan['description'].toString(),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ],
      ),
    );
  }

  static String _formatCurrency(double amount) {
    final formatter = NumberFormat.compactCurrency(
      locale: 'en_IN',
      symbol: '₹',
      decimalDigits: amount == amount.roundToDouble() ? 0 : 1,
    );

    return formatter.format(amount);
  }

  static String _durationLabel(int days) {
    if (days >= 365 && days % 365 == 0) {
      final years = days ~/ 365;
      return years == 1 ? '1 yr' : '$years yrs';
    }

    if (days >= 30 && days % 30 == 0) {
      final months = days ~/ 30;
      return months == 1 ? '1 mo' : '$months mos';
    }

    return '$days days';
  }
}
