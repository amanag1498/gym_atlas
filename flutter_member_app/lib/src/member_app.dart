import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:gym_flutter_core/guides.dart';
import 'package:go_router/go_router.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart'
    show AppRuntimeController, AppRuntimeGate, ChatNotificationService;
import 'package:provider/provider.dart';

import '../core/theme/app_theme.dart';
import 'core/api_client.dart';
import 'core/fcm_token_service.dart';
import 'core/member_route_redirect.dart';
import 'core/secure_storage_service.dart';
import 'features/auth/auth_gate.dart';
import 'features/auth/auth_service.dart';
import 'features/auth/login_screen.dart';
import 'features/auth/member_consent_screen.dart';
import 'features/auth/session_controller.dart';
import 'features/member/member_home_screen.dart';
import 'features/member/member_events_screen.dart';
import 'features/member/gym_self_enrollment_screen.dart';
import 'features/member/member_repository.dart';
import 'features/member/shared_workout_plan_screen.dart';
import 'features/smart_attendance/smart_attendance_ble_scanner.dart';
import 'features/smart_attendance/smart_attendance_check_in_cache.dart';
import 'features/smart_attendance/smart_attendance_controller.dart';
import 'features/smart_attendance/smart_attendance_debug_overlay.dart';
import 'features/smart_attendance/smart_attendance_session_store.dart';

class MemberApp extends StatefulWidget {
  const MemberApp({super.key});

  @override
  State<MemberApp> createState() => _MemberAppState();
}

class _MemberAppState extends State<MemberApp> with WidgetsBindingObserver {
  late final SecureStorageService storage;
  late final MemberApiClient apiClient;
  late final AuthService authService;
  late final MemberFcmTokenService fcmTokenService;
  late final MemberSessionController sessionController;
  late final AppRuntimeController runtimeController;
  late final MemberRepository memberRepository;
  late final SmartAttendanceController smartAttendanceController;
  late final ChatNotificationService _chatNotificationService;
  late final AppLinks _appLinks;
  late final GoRouter router;
  late final GlobalKey<NavigatorState> _rootNavigatorKey;
  StreamSubscription<Uri>? _appLinkSubscription;
  StreamSubscription<RemoteMessage>? _foregroundNotificationSubscription;
  StreamSubscription<RemoteMessage>? _notificationOpenSubscription;
  int _chatLaunchSequence = 0;
  bool _pendingChatLaunch = false;
  int? _pendingChatGymId;
  int? _pendingChatTrainerId;
  bool _openingPendingChat = false;
  int? _pendingEventId;
  bool _openingPendingEvent = false;
  bool _pendingTrialRequestsOpen = false;
  bool _openingPendingTrialRequests = false;
  String? _pendingHomeSection;
  bool _openingPendingHomeSection = false;
  String? _lastHandledAppLink;
  DateTime? _lastHandledAppLinkAt;
  String? _lastSmartAttendanceReportSignature;
  DateTime? _lastSmartAttendanceReportAt;
  bool _smartAttendanceReportInFlight = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    storage = const SecureStorageService();
    apiClient = MemberApiClient();
    runtimeController = AppRuntimeController(
      dio: apiClient.dio,
      appType: 'member',
    );
    authService = AuthService(apiClient);
    fcmTokenService = MemberFcmTokenService(apiClient);
    sessionController = MemberSessionController(
      storage: storage,
      apiClient: apiClient,
      authService: authService,
      fcmTokenService: fcmTokenService,
    );
    memberRepository = MemberRepository(apiClient);
    smartAttendanceController = SmartAttendanceController(
      scanner: MethodChannelSmartAttendanceBleScanner(),
      checkInClient: memberRepository,
      successCache: const SecureSmartAttendanceCheckInCache(),
      sessionStore: const SecureSmartAttendanceSessionStore(),
      selectedGymIdProvider: storage.readSelectedGymId,
      memberIdProvider: () => sessionController.user?.id,
    );
    _chatNotificationService = ChatNotificationService();
    _appLinks = AppLinks();
    _rootNavigatorKey = GlobalKey<NavigatorState>();
    router = GoRouter(
      navigatorKey: _rootNavigatorKey,
      refreshListenable: sessionController,
      routes: <GoRoute>[
        GoRoute(
          path: '/',
          pageBuilder: (context, state) =>
              _buildPage(state, const AuthGateScreen()),
        ),
        GoRoute(
          path: '/login',
          pageBuilder: (context, state) =>
              _buildPage(state, const MemberLoginScreen()),
        ),
        GoRoute(
          path: '/consent',
          pageBuilder: (context, state) => _buildPage(
            state,
            MemberConsentScreen(session: sessionController),
          ),
        ),
        GoRoute(
          path: '/home',
          pageBuilder: (context, state) => _buildPage(
            state,
            MemberHomeScreen(
              initialIndex: switch (state.uri.queryParameters['section']) {
                'workout' => 1,
                'progress' => 2,
                'chat' => 3,
                'gyms' => 4,
                _ => 0,
              },
              chatLaunchVersion:
                  int.tryParse(
                    state.uri.queryParameters['launch']?.toString() ?? '',
                  ) ??
                  0,
              chatTargetTrainerId: int.tryParse(
                state.uri.queryParameters['trainer']?.toString() ?? '',
              ),
              openTrialRequestsOnLoad:
                  state.uri.queryParameters['section'] == 'trials',
            ),
          ),
        ),
        GoRoute(
          path: '/join/:token',
          pageBuilder: (context, state) => _buildPage(
            state,
            GymSelfEnrollmentScreen(
              token: state.pathParameters['token'] ?? '',
              repository: memberRepository,
            ),
          ),
        ),
        GoRoute(
          path: '/events/:eventId',
          pageBuilder: (context, state) {
            final reference = state.pathParameters['eventId'] ?? '';
            return _buildPage(
              state,
              MemberEventsScreen(
                repository: memberRepository,
                initialEventId: int.tryParse(reference),
                initialPublicToken: int.tryParse(reference) == null
                    ? reference
                    : null,
                initialManageToken: eventClaimToken(state.uri),
              ),
            );
          },
        ),
        GoRoute(
          path: '/workouts/shared/:token',
          pageBuilder: (context, state) => _buildPage(
            state,
            SharedWorkoutPlanScreen(
              token: state.pathParameters['token'] ?? '',
              repository: memberRepository,
            ),
          ),
        ),
      ],
      redirect: (context, state) {
        return memberRouteRedirect(
          uri: state.uri,
          initializing: sessionController.initializing,
          isAuthenticated: sessionController.isAuthenticated,
          requiresConsent:
              sessionController.isAuthenticated &&
              !sessionController.hasRequiredConsent,
        );
      },
    );
    sessionController.addListener(_openPendingChatIfReady);
    sessionController.addListener(_openPendingEventIfReady);
    sessionController.addListener(_openPendingTrialRequestsIfReady);
    sessionController.addListener(_openPendingHomeSectionIfReady);
    sessionController.addListener(_syncSmartAttendanceScan);
    _appLinkSubscription = _appLinks.uriLinkStream.listen(
      _openAppLink,
      onError: (Object exception) {
        debugPrint('[app-links] incoming link skipped: $exception');
      },
    );
    _chatNotificationService.initialize(_handleNotificationData).catchError((
      Object exception,
    ) {
      debugPrint(
        '[notifications] local notification setup skipped: $exception',
      );
    });
    FirebaseMessaging.instance
        .setForegroundNotificationPresentationOptions(
          alert: true,
          badge: true,
          sound: true,
        )
        .catchError((Object exception) {
          debugPrint('[fcm] foreground presentation setup skipped: $exception');
        });
    _notificationOpenSubscription = FirebaseMessaging.onMessageOpenedApp.listen(
      _handleNotificationOpen,
    );
    _foregroundNotificationSubscription = FirebaseMessaging.onMessage.listen(
      _showForegroundNotification,
    );
    FirebaseMessaging.instance
        .getInitialMessage()
        .then((message) {
          if (message != null) {
            _handleNotificationOpen(message);
          }
        })
        .catchError((Object exception) {
          debugPrint('[fcm] initial notification skipped: $exception');
        });
    unawaited(_start());
  }

  Future<void> _start() async {
    final initialLink = await _appLinks.getInitialLink().catchError((
      Object exception,
    ) {
      debugPrint('[app-links] initial link skipped: $exception');
      return null;
    });
    await runtimeController.initialize();
    await sessionController.bootstrap();
    if (initialLink != null) {
      _openAppLink(initialLink);
    }
  }

  void _openAppLink(Uri uri) {
    final destination = memberDeepLinkDestination(uri);
    if (destination == null) {
      debugPrint('[app-links] unsupported link ignored: $uri');
      return;
    }

    final now = DateTime.now();
    if (_lastHandledAppLink == destination &&
        _lastHandledAppLinkAt != null &&
        now.difference(_lastHandledAppLinkAt!) < const Duration(seconds: 2)) {
      return;
    }
    _lastHandledAppLink = destination;
    _lastHandledAppLinkAt = now;

    router.go(destination);
  }

  void _handleNotificationOpen(RemoteMessage message) {
    _handleNotificationData(message.data);
  }

  void _showForegroundNotification(RemoteMessage message) {
    final notification = message.notification;
    _chatNotificationService
        .show(
          title: notification?.title ?? 'New notification',
          body: notification?.body ?? 'Open the app to view details.',
          data: message.data,
        )
        .catchError((Object exception) {
          debugPrint('[notifications] foreground alert skipped: $exception');
        });
  }

  void _handleNotificationData(Map<String, dynamic> data) {
    final eventId = _notificationInt(data['event_id'] ?? data['eventId']);
    if (eventId != null && eventId > 0) {
      _pendingEventId = eventId;
      unawaited(_openPendingEventIfReady());
      return;
    }
    final trialRequestId = _notificationInt(
      data['trial_request_id'] ?? data['trialRequestId'],
    );
    if (trialRequestId != null && trialRequestId > 0) {
      _pendingTrialRequestsOpen = true;
      unawaited(_openPendingTrialRequestsIfReady());
      return;
    }
    final deepLink = data['deep_link']?.toString();
    if (deepLink != null && deepLink.startsWith('/home?section=')) {
      final uri = Uri.tryParse(deepLink);
      final section = uri?.queryParameters['section'];
      if (const {'workout', 'progress', 'gyms'}.contains(section)) {
        _pendingHomeSection = section;
        unawaited(_openPendingHomeSectionIfReady());
        return;
      }
    }
    if (data['type'] != 'chat_message') {
      return;
    }

    _chatLaunchSequence++;
    _pendingChatGymId = _notificationInt(data['gym_id']);
    _pendingChatTrainerId = _notificationInt(
      data['trainer_id'] ?? data['sender_id'] ?? data['senderId'],
    );
    _pendingChatLaunch = true;
    unawaited(_openPendingChatIfReady());
  }

  void _syncSmartAttendanceScan() {
    final shouldScan =
        sessionController.isAuthenticated &&
        sessionController.hasRequiredConsent &&
        sessionController.hasConsent('biometric_attendance');
    if (!shouldScan) {
      if (smartAttendanceController.scanning) {
        unawaited(_stopSmartAttendance());
      }
      return;
    }

    if (!smartAttendanceController.scanning ||
        smartAttendanceController.backgroundScanning) {
      unawaited(_startSmartAttendance(background: false));
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    final shouldScan =
        sessionController.isAuthenticated &&
        sessionController.hasRequiredConsent &&
        sessionController.hasConsent('biometric_attendance');
    if (!shouldScan) {
      return;
    }

    if (state == AppLifecycleState.resumed) {
      unawaited(_startSmartAttendance(background: false));
      return;
    }

    if ((defaultTargetPlatform == TargetPlatform.android ||
            defaultTargetPlatform == TargetPlatform.iOS) &&
        (state == AppLifecycleState.paused ||
            state == AppLifecycleState.inactive ||
            state == AppLifecycleState.hidden)) {
      unawaited(_startSmartAttendance(background: true));
    }
  }

  Future<void> _startSmartAttendance({required bool background}) async {
    if (background) {
      await smartAttendanceController.startBackgroundScan();
    } else {
      await smartAttendanceController.startForegroundScan();
    }
    await _reportSmartAttendanceState();
  }

  Future<void> _stopSmartAttendance() async {
    await smartAttendanceController.stopScan();
    await _reportSmartAttendanceState();
  }

  Future<void> _reportSmartAttendanceState() async {
    final signature = [
      smartAttendanceController.bluetoothPermissionStatus,
      smartAttendanceController.scanning,
      smartAttendanceController.backgroundScanning,
    ].join('|');
    final now = DateTime.now();
    final lastReportAt = _lastSmartAttendanceReportAt;
    if (_smartAttendanceReportInFlight ||
        (_lastSmartAttendanceReportSignature == signature &&
            lastReportAt != null &&
            now.difference(lastReportAt) < const Duration(minutes: 10))) {
      return;
    }
    _smartAttendanceReportInFlight = true;
    smartAttendanceController.markBackendSyncStarted();
    try {
      final success = await fcmTokenService.registerPresence(
        appRole: 'member',
        bluetoothPermissionStatus:
            smartAttendanceController.bluetoothPermissionStatus,
        smartAttendanceScanning: smartAttendanceController.scanning,
        smartAttendanceMode: smartAttendanceController.scanning
            ? (smartAttendanceController.backgroundScanning
                  ? 'background'
                  : 'foreground')
            : 'stopped',
        smartAttendanceLastDetectionAt:
            smartAttendanceController.latestDetection?.detectedAt,
      );
      _lastSmartAttendanceReportSignature = signature;
      _lastSmartAttendanceReportAt = now;
      smartAttendanceController.markBackendSyncFinished(
        success: success,
        error: fcmTokenService.lastPresenceError,
      );
    } finally {
      _smartAttendanceReportInFlight = false;
    }
  }

  Future<void> _openPendingChatIfReady() async {
    if (!_pendingChatLaunch ||
        _openingPendingChat ||
        sessionController.initializing ||
        !sessionController.isAuthenticated) {
      return;
    }

    _openingPendingChat = true;
    _pendingChatLaunch = false;
    final gymId = _pendingChatGymId;
    final trainerId = _pendingChatTrainerId;
    final launchSequence = _chatLaunchSequence;
    _pendingChatGymId = null;
    _pendingChatTrainerId = null;
    try {
      if (gymId != null) {
        await sessionController.selectGymContext(gymId);
      }
      final trainerQuery = trainerId == null ? '' : '&trainer=$trainerId';
      router.go('/home?section=chat&launch=$launchSequence$trainerQuery');
    } finally {
      _openingPendingChat = false;
      if (_pendingChatLaunch) {
        unawaited(_openPendingChatIfReady());
      }
    }
  }

  Future<void> _openPendingEventIfReady() async {
    final eventId = _pendingEventId;
    if (eventId == null ||
        _openingPendingEvent ||
        sessionController.initializing ||
        !sessionController.isAuthenticated) {
      return;
    }

    _openingPendingEvent = true;
    _pendingEventId = null;
    try {
      router.go('/events/$eventId');
    } finally {
      _openingPendingEvent = false;
      if (_pendingEventId != null) {
        unawaited(_openPendingEventIfReady());
      }
    }
  }

  Future<void> _openPendingTrialRequestsIfReady() async {
    if (!_pendingTrialRequestsOpen ||
        _openingPendingTrialRequests ||
        sessionController.initializing ||
        !sessionController.isAuthenticated) {
      return;
    }
    _openingPendingTrialRequests = true;
    _pendingTrialRequestsOpen = false;
    try {
      router.go('/home?section=trials');
    } finally {
      _openingPendingTrialRequests = false;
      if (_pendingTrialRequestsOpen) {
        unawaited(_openPendingTrialRequestsIfReady());
      }
    }
  }

  Future<void> _openPendingHomeSectionIfReady() async {
    final section = _pendingHomeSection;
    if (section == null ||
        _openingPendingHomeSection ||
        sessionController.initializing ||
        !sessionController.isAuthenticated) {
      return;
    }
    _openingPendingHomeSection = true;
    _pendingHomeSection = null;
    try {
      router.go('/home?section=$section');
    } finally {
      _openingPendingHomeSection = false;
      if (_pendingHomeSection != null) {
        unawaited(_openPendingHomeSectionIfReady());
      }
    }
  }

  @override
  void dispose() {
    _appLinkSubscription?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    _foregroundNotificationSubscription?.cancel();
    _notificationOpenSubscription?.cancel();
    sessionController.removeListener(_openPendingChatIfReady);
    sessionController.removeListener(_openPendingEventIfReady);
    sessionController.removeListener(_openPendingTrialRequestsIfReady);
    sessionController.removeListener(_openPendingHomeSectionIfReady);
    sessionController.removeListener(_syncSmartAttendanceScan);
    router.dispose();
    runtimeController.dispose();
    smartAttendanceController.dispose();
    sessionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<MemberSessionController>.value(
          value: sessionController,
        ),
        Provider<MemberRepository>.value(value: memberRepository),
        ChangeNotifierProvider<SmartAttendanceController>.value(
          value: smartAttendanceController,
        ),
      ],
      child: MaterialApp.router(
        debugShowCheckedModeBanner: false,
        title: 'Gym Atlas',
        routerConfig: router,
        theme: AppTheme.build(),
        builder: (context, child) => AppRuntimeGate(
          controller: runtimeController,
          audience: 'Member',
          child: Consumer<MemberSessionController>(
            builder: (context, session, _) {
              final content = GuideScope(
                account: session.isAuthenticated && session.hasRequiredConsent
                    ? 'member:${session.user!.id}'
                    : null,
                guides: memberGuides,
                child: child ?? const SizedBox.shrink(),
              );
              if (!kDebugMode || !session.isAuthenticated) {
                return content;
              }
              return SmartAttendanceDebugOverlay(
                controller: smartAttendanceController,
                navigatorKey: _rootNavigatorKey,
                child: content,
              );
            },
          ),
        ),
      ),
    );
  }

  CustomTransitionPage<void> _buildPage(GoRouterState state, Widget child) {
    return CustomTransitionPage<void>(
      key: state.pageKey,
      child: child,
      transitionDuration: const Duration(milliseconds: 260),
      reverseTransitionDuration: const Duration(milliseconds: 220),
      transitionsBuilder: (context, animation, secondaryAnimation, child) {
        final curved = CurvedAnimation(
          parent: animation,
          curve: Curves.easeOutCubic,
          reverseCurve: Curves.easeInCubic,
        );

        return FadeTransition(
          opacity: curved,
          child: SlideTransition(
            position: Tween<Offset>(
              begin: const Offset(0.025, 0.015),
              end: Offset.zero,
            ).animate(curved),
            child: child,
          ),
        );
      },
    );
  }
}

int? _notificationInt(dynamic value) {
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}
