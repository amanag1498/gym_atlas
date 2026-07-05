import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
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
  late final GoRouter router;

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
          pageBuilder: (context, state) =>
              _buildPage(state, const MemberHomeScreen()),
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
    sessionController.bootstrap();
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
        title: 'Connected Gym Member',
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
