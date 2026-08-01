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
          return location == '/login' ? null : '/login';
        }

        if (location == '/' || location == '/login') {
          return '/home';
        }

        return null;
      },
    );
    sessionController.addListener(_openPendingChatIfReady);
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
    if (message.data['type'] != 'chat_message') {
      return;
    }

    final notification = message.notification;
    _chatNotificationService
        .show(
          title: notification?.title ?? 'New chat message',
          body: notification?.body ?? 'Open the app to view your message.',
          data: message.data,
        )
        .catchError((Object exception) {
          debugPrint(
            '[notifications] foreground chat alert skipped: $exception',
          );
        });
  }

  void _handleNotificationData(Map<String, dynamic> data) {
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

  @override
  void dispose() {
    _foregroundNotificationSubscription?.cancel();
    _notificationOpenSubscription?.cancel();
    sessionController.removeListener(_openPendingChatIfReady);
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
