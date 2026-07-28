import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
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
  StreamSubscription<RemoteMessage>? _notificationOpenSubscription;
  int? _pendingChatMemberId;
  int _chatLaunchVersion = 0;

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
    _notificationOpenSubscription = FirebaseMessaging.onMessageOpenedApp.listen(
      _handleNotificationOpen,
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
    _sessionController.bootstrap();
  }

  void _handleNotificationOpen(RemoteMessage message) {
    if (message.data['type'] != 'chat_message') {
      return;
    }

    final memberId = int.tryParse(message.data['member_id']?.toString() ?? '');
    if (memberId == null || memberId <= 0 || !mounted) {
      return;
    }

    setState(() {
      _pendingChatMemberId = memberId;
      _chatLaunchVersion++;
    });
  }

  @override
  void dispose() {
    _notificationOpenSubscription?.cancel();
    _sessionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider<TrainerSessionController>.value(
      value: _sessionController,
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        title: 'Atlas Trainer',
        theme: AppTheme.build(),
        home: Consumer<TrainerSessionController>(
          builder: (context, session, _) {
            if (session.initializing) {
              return const Scaffold(
                body: Center(child: CircularProgressIndicator()),
              );
            }

            return session.isAuthenticated
                ? TrainerHomeScreen(
                    initialChatMemberId: _pendingChatMemberId,
                    chatLaunchVersion: _chatLaunchVersion,
                  )
                : const TrainerLoginScreen();
          },
        ),
      ),
    );
  }
}
