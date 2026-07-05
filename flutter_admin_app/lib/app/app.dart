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

        if (loggedIn &&
            location == '/login') {
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
          pageBuilder: (context, state) =>
              _buildPage(state, const AdminShellScreen()),
        ),
        GoRoute(
          path: '/platform-admin/workout-books',
          pageBuilder: (context, state) =>
              _buildPage(state, const PlatformWorkoutBooksScreen()),
        ),
      ],
    );
  }

  @override
  void dispose() {
    _router.dispose();
    _sessionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider<SessionController>.value(
      value: _sessionController,
      child: MaterialApp.router(
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
