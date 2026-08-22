import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart'
    show ChatNotificationService;
import 'package:provider/provider.dart';

import '../core/theme/app_theme.dart';
import 'core/api_client.dart';
import 'core/fcm_token_service.dart';
import 'core/secure_storage_service.dart';
import 'features/auth/auth_gate.dart';
import 'features/auth/auth_service.dart';
import 'features/auth/login_screen.dart';
import 'features/auth/session_controller.dart';
import 'features/member/member_home_screen.dart';
import 'features/member/member_events_screen.dart';
import 'features/member/gym_self_enrollment_screen.dart';
import 'features/member/member_repository.dart';

class MemberApp extends StatefulWidget {
  const MemberApp({super.key});

  @override
  State<MemberApp> createState() => _MemberAppState();
}

class _MemberAppState extends State<MemberApp> {
  late final SecureStorageService storage;
  late final MemberApiClient apiClient;
  late final AuthService authService;
  late final MemberFcmTokenService fcmTokenService;
  late final MemberSessionController sessionController;
  late final MemberRepository memberRepository;
  late final ChatNotificationService _chatNotificationService;
  late final GoRouter router;
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

  @override
  void initState() {
    super.initState();
    storage = const SecureStorageService();
    apiClient = MemberApiClient();
    authService = AuthService(apiClient);
    fcmTokenService = MemberFcmTokenService(apiClient);
    sessionController = MemberSessionController(
      storage: storage,
      apiClient: apiClient,
      authService: authService,
      fcmTokenService: fcmTokenService,
    );
    memberRepository = MemberRepository(apiClient);
    _chatNotificationService = ChatNotificationService();
    router = GoRouter(
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
          path: '/home',
          pageBuilder: (context, state) => _buildPage(
            state,
            MemberHomeScreen(
              initialIndex: state.uri.queryParameters['section'] == 'chat'
                  ? 3
                  : 0,
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
          pageBuilder: (context, state) => _buildPage(
            state,
            MemberEventsScreen(
              repository: memberRepository,
              initialEventId: int.tryParse(
                state.pathParameters['eventId'] ?? '',
              ),
            ),
          ),
        ),
      ],
      redirect: (context, state) {
        final location = state.matchedLocation;

        if (sessionController.initializing) {
          return location == '/' ? null : '/';
        }

        if (!sessionController.isAuthenticated) {
          if (location == '/login') return null;
          if (location.startsWith('/join/')) {
            final token = state.pathParameters['token'];
            return '/login?join=${Uri.encodeComponent(token ?? '')}';
          }
          return '/login';
        }

        if (location == '/login' && state.uri.queryParameters['join'] != null) {
          return '/join/${state.uri.queryParameters['join']}';
        }

        if (location == '/' || location == '/login') {
          return '/home';
        }

        return null;
      },
    );
    sessionController.addListener(_openPendingChatIfReady);
    sessionController.addListener(_openPendingEventIfReady);
    sessionController.addListener(_openPendingTrialRequestsIfReady);
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
      _showForegroundChatNotification,
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
    sessionController.bootstrap();
  }

  void _handleNotificationOpen(RemoteMessage message) {
    _handleNotificationData(message.data);
  }

  void _showForegroundChatNotification(RemoteMessage message) {
    final notification = message.notification;
    _chatNotificationService
        .show(
          title: notification?.title ?? 'New notification',
          body: notification?.body ?? 'Open the app to view details.',
          data: message.data,
        )
        .catchError((Object exception) {
          debugPrint(
            '[notifications] foreground chat alert skipped: $exception',
          );
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

  @override
  void dispose() {
    _foregroundNotificationSubscription?.cancel();
    _notificationOpenSubscription?.cancel();
    sessionController.removeListener(_openPendingChatIfReady);
    sessionController.removeListener(_openPendingEventIfReady);
    sessionController.removeListener(_openPendingTrialRequestsIfReady);
    router.dispose();
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
      ],
      child: MaterialApp.router(
        debugShowCheckedModeBanner: false,
        title: 'Atlas Member',
        routerConfig: router,
        theme: AppTheme.build(),
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
