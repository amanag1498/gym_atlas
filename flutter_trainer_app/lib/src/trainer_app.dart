import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/api_client.dart';
import 'core/fcm_token_service.dart';
import '../core/theme/app_theme.dart';
import 'features/auth/auth_service.dart';
import 'features/auth/login_screen.dart';
import 'features/auth/session_controller.dart';
import 'features/trainer/trainer_home_screen.dart';
import 'core/token_storage.dart';

class TrainerApp extends StatefulWidget {
  const TrainerApp({super.key});

  @override
  State<TrainerApp> createState() => _TrainerAppState();
}

class _TrainerAppState extends State<TrainerApp> {
  late final TrainerTokenStorage _storage;
  late final TrainerApiClient _apiClient;
  late final TrainerAuthService _authService;
  late final TrainerFcmTokenService _fcmTokenService;
  late final TrainerSessionController _sessionController;

  @override
  void initState() {
    super.initState();
    _storage = const TrainerTokenStorage();
    _apiClient = TrainerApiClient(token: null, onUnauthorized: () async {});
    _authService = TrainerAuthService(_apiClient);
    _fcmTokenService = TrainerFcmTokenService(_apiClient);
    _sessionController = TrainerSessionController(
      storage: _storage,
      apiClient: _apiClient,
      authService: _authService,
      fcmTokenService: _fcmTokenService,
    );
    _sessionController.bootstrap();
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider<TrainerSessionController>.value(
      value: _sessionController,
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        title: 'Connected Gym Trainer',
        theme: AppTheme.build(),
        home: Consumer<TrainerSessionController>(
          builder: (context, session, _) {
            if (session.initializing) {
              return const Scaffold(
                body: Center(child: CircularProgressIndicator()),
              );
            }

            return session.isAuthenticated
                ? const TrainerHomeScreen()
                : const TrainerLoginScreen();
          },
        ),
      ),
    );
  }
}
