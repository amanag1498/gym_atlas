import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart'
    show BrandedStartupLoader, ChatNotificationService;
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
  late final ChatNotificationService _chatNotificationService;
  StreamSubscription<RemoteMessage>? _foregroundNotificationSubscription;
  StreamSubscription<RemoteMessage>? _notificationOpenSubscription;
  int? _pendingChatMemberId;
  int _chatLaunchVersion = 0;
  int? _pendingEventId;
  int _eventLaunchVersion = 0;
  int? _pendingTrialRequestId;
  int _trialLaunchVersion = 0;

  @override
  void initState() {
    super.initState();
    _storage = const TrainerTokenStorage();
    _apiClient = TrainerApiClient(token: null, onUnauthorized: () async {});
    _authService = TrainerAuthService(_apiClient);
    _fcmTokenService = TrainerFcmTokenService(_apiClient);
    _chatNotificationService = ChatNotificationService();
    _sessionController = TrainerSessionController(
      storage: _storage,
      apiClient: _apiClient,
      authService: _authService,
      fcmTokenService: _fcmTokenService,
    );
    FirebaseMessaging.instance
        .setForegroundNotificationPresentationOptions(
          alert: true,
          badge: true,
          sound: true,
        )
        .catchError((Object exception) {
          debugPrint('[fcm] foreground presentation setup skipped: $exception');
        });
    _chatNotificationService.initialize(_handleNotificationData).catchError((
      Object exception,
    ) {
      debugPrint(
        '[notifications] local notification setup skipped: $exception',
      );
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
    _sessionController.bootstrap();
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
    final eventId = int.tryParse(
      (data['event_id'] ?? data['eventId'])?.toString() ?? '',
    );
    if (eventId != null && eventId > 0 && mounted) {
      setState(() {
        _pendingEventId = eventId;
        _eventLaunchVersion++;
      });
      return;
    }
    final trialRequestId = int.tryParse(
      (data['trial_request_id'] ?? data['trialRequestId'])?.toString() ?? '',
    );
    if (trialRequestId != null && trialRequestId > 0 && mounted) {
      setState(() {
        _pendingTrialRequestId = trialRequestId;
        _trialLaunchVersion++;
      });
      return;
    }
    if (data['type'] != 'chat_message') {
      return;
    }

    final memberId = int.tryParse(data['member_id']?.toString() ?? '');
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
    _foregroundNotificationSubscription?.cancel();
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
              return const BrandedStartupLoader();
            }

            return session.isAuthenticated
                ? TrainerHomeScreen(
                    initialChatMemberId: _pendingChatMemberId,
                    chatLaunchVersion: _chatLaunchVersion,
                    initialEventId: _pendingEventId,
                    eventLaunchVersion: _eventLaunchVersion,
                    initialTrialRequestId: _pendingTrialRequestId,
                    trialLaunchVersion: _trialLaunchVersion,
                  )
                : const TrainerLoginScreen();
          },
        ),
      ),
    );
  }
}
