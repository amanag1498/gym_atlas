import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../core/theme/app_theme.dart';
import '../features/admin/admin_shell_screen.dart';
import '../features/admin/platform_workout_books_screen.dart';
import '../features/auth/login_screen.dart';
import '../features/auth/role_selector_screen.dart';
import '../features/auth/session_controller.dart';

class GymAdminApp extends StatefulWidget {
  const GymAdminApp({super.key});

  @override
  State<GymAdminApp> createState() => _GymAdminAppState();
}

class _GymAdminAppState extends State<GymAdminApp> {
  late final SessionController _sessionController;
  late final GoRouter _router;
  final GlobalKey<ScaffoldMessengerState> _messengerKey =
      GlobalKey<ScaffoldMessengerState>();
  StreamSubscription<RemoteMessage>? _foregroundNotificationSubscription;
  StreamSubscription<RemoteMessage>? _notificationOpenSubscription;
  String? _pendingDestination;
  bool _openingPendingDestination = false;

  @override
  void initState() {
    super.initState();
    _sessionController = SessionController()..bootstrap();
    _router = GoRouter(
      refreshListenable: _sessionController,
      initialLocation: '/login',
      redirect: (context, state) {
        final loggedIn = _sessionController.isAuthenticated;
        final location = state.matchedLocation;

        if (!loggedIn && location != '/login') {
          return '/login';
        }

        if (loggedIn && location == '/login') {
          return '/home';
        }

        if (loggedIn &&
            location == '/roles' &&
            !_sessionController.hasMultipleRoles) {
          return '/home';
        }

        if (loggedIn &&
            location == '/home' &&
            _sessionController.user == null) {
          return '/login';
        }

        return null;
      },
      routes: [
        GoRoute(
          path: '/login',
          pageBuilder: (context, state) =>
              _buildPage(state, const LoginScreen()),
        ),
        GoRoute(
          path: '/roles',
          pageBuilder: (context, state) =>
              _buildPage(state, const RoleSelectorScreen()),
        ),
        GoRoute(
          path: '/home',
          pageBuilder: (context, state) => _buildPage(
            state,
            AdminShellScreen(
              initialDestinationTitle: state.uri.queryParameters['section'],
            ),
          ),
        ),
        GoRoute(
          path: '/platform-admin/workout-books',
          pageBuilder: (context, state) =>
              _buildPage(state, const PlatformWorkoutBooksScreen()),
        ),
      ],
    );
    _sessionController.addListener(_openPendingDestinationIfReady);
    FirebaseMessaging.instance
        .setForegroundNotificationPresentationOptions(
          alert: true,
          badge: true,
          sound: true,
        )
        .catchError((Object exception) {
          debugPrint(
            '[notifications][admin] foreground setup skipped: $exception',
          );
        });
    _notificationOpenSubscription = FirebaseMessaging.onMessageOpenedApp.listen(
      (message) => _handleNotificationData(message.data),
    );
    _foregroundNotificationSubscription = FirebaseMessaging.onMessage.listen((
      message,
    ) {
      final notification = message.notification;
      _messengerKey.currentState
        ?..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(
              '${notification?.title ?? 'New notification'}\n'
              '${notification?.body ?? 'Open to view details.'}',
            ),
            action: SnackBarAction(
              label: 'Open',
              onPressed: () => _handleNotificationData(message.data),
            ),
          ),
        );
    });
    FirebaseMessaging.instance
        .getInitialMessage()
        .then((message) {
          if (message != null) _handleNotificationData(message.data);
        })
        .catchError((Object exception) {
          debugPrint(
            '[notifications][admin] initial message skipped: $exception',
          );
        });
  }

  void _handleNotificationData(Map<String, dynamic> data) {
    final route = data['route']?.toString().toLowerCase() ?? '';
    final type = data['type']?.toString().toLowerCase() ?? '';

    _pendingDestination = switch ((route, type)) {
      (final value, _) when value.contains('trial') => 'Trial Requests',
      (final value, _) when value.contains('member') => 'Members',
      (final value, _)
          when value.contains('payment') || value.contains('due') =>
        'Payments',
      (_, final value) when value.contains('trial') => 'Trial Requests',
      (_, final value)
          when value.contains('payment') || value.contains('due') =>
        'Payments',
      _ => 'Notifications',
    };
    unawaited(_openPendingDestinationIfReady());
  }

  Future<void> _openPendingDestinationIfReady() async {
    final destination = _pendingDestination;
    if (destination == null ||
        _openingPendingDestination ||
        !_sessionController.isAuthenticated) {
      return;
    }

    _openingPendingDestination = true;
    _pendingDestination = null;
    try {
      _router.go('/home?section=${Uri.encodeQueryComponent(destination)}');
    } finally {
      _openingPendingDestination = false;
    }
  }

  @override
  void dispose() {
    _sessionController.removeListener(_openPendingDestinationIfReady);
    _foregroundNotificationSubscription?.cancel();
    _notificationOpenSubscription?.cancel();
    _router.dispose();
    _sessionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider<SessionController>.value(
      value: _sessionController,
      child: MaterialApp.router(
        scaffoldMessengerKey: _messengerKey,
        debugShowCheckedModeBanner: false,
        title: 'Gym Command',
        theme: AppTheme.build(),
        routerConfig: _router,
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
