import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/data/latest.dart' as tz_data;
import 'package:timezone/timezone.dart' as tz;

typedef ChatNotificationTap = void Function(Map<String, dynamic> data);

class ChatNotificationService {
  factory ChatNotificationService({FlutterLocalNotificationsPlugin? plugin}) =>
      plugin == null ? _shared : ChatNotificationService._(plugin);

  ChatNotificationService._([FlutterLocalNotificationsPlugin? plugin])
    : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  static final ChatNotificationService _shared = ChatNotificationService._();

  static const _channel = AndroidNotificationChannel(
    'chat_messages',
    'Chat messages',
    description: 'Messages from your trainer or member',
    importance: Importance.high,
  );
  static const _notificationChannel = AndroidNotificationChannel(
    'gym_atlas_notifications',
    'Gym Atlas notifications',
    description: 'Membership, attendance, workout, event, and account updates',
    importance: Importance.high,
  );
  static const _workoutTimerChannel = AndroidNotificationChannel(
    'workout_timers',
    'Workout timers',
    description: 'Rest and work timer completion alerts',
    importance: Importance.high,
  );
  static const workoutTimerNotificationId = 882401;

  final FlutterLocalNotificationsPlugin _plugin;
  ChatNotificationTap? _onTap;

  Future<void> initialize(ChatNotificationTap onTap) async {
    _onTap = onTap;
    if (kIsWeb ||
        !const {
          TargetPlatform.android,
          TargetPlatform.iOS,
        }.contains(defaultTargetPlatform)) {
      return;
    }

    await _plugin.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('ic_stat_chat'),
        iOS: DarwinInitializationSettings(),
      ),
      onDidReceiveNotificationResponse: _handleResponse,
    );
    if (defaultTargetPlatform == TargetPlatform.android) {
      final android = _plugin
          .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin
          >();
      await android?.createNotificationChannel(_channel);
      await android?.createNotificationChannel(_notificationChannel);
      await android?.createNotificationChannel(_workoutTimerChannel);
    }

    final launchDetails = await _plugin.getNotificationAppLaunchDetails();
    final response = launchDetails?.notificationResponse;
    if (launchDetails?.didNotificationLaunchApp == true && response != null) {
      _handleResponse(response);
    }
  }

  Future<void> show({
    required String title,
    required String body,
    required Map<String, dynamic> data,
  }) async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) {
      return;
    }

    final isChat = data['type'] == 'chat_message';
    final notificationId = int.tryParse(
      data['notification_id']?.toString() ?? '',
    );
    final messageId = int.tryParse(data['message_id']?.toString() ?? '');
    await _plugin.show(
      notificationId ??
          messageId ??
          DateTime.now().millisecondsSinceEpoch.remainder(1 << 31),
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          isChat ? 'chat_messages' : 'gym_atlas_notifications',
          isChat ? 'Chat messages' : 'Gym Atlas notifications',
          channelDescription: isChat
              ? 'Messages from your trainer or member'
              : 'Membership, attendance, workout, event, and account updates',
          importance: Importance.high,
          priority: Priority.high,
          category: isChat ? AndroidNotificationCategory.message : null,
        ),
      ),
      payload: jsonEncode(data),
    );
  }

  Future<void> scheduleWorkoutTimer({
    required DateTime endsAt,
    required String exerciseName,
    required bool playSound,
    required bool enableVibration,
  }) async {
    if (kIsWeb ||
        !const {
          TargetPlatform.android,
          TargetPlatform.iOS,
        }.contains(defaultTargetPlatform) ||
        endsAt.isBefore(DateTime.now())) {
      return;
    }
    tz_data.initializeTimeZones();
    await _plugin.cancel(workoutTimerNotificationId);
    await _plugin.zonedSchedule(
      workoutTimerNotificationId,
      'Rest complete',
      'Ready for your next $exerciseName set.',
      tz.TZDateTime.from(endsAt.toUtc(), tz.UTC),
      NotificationDetails(
        android: AndroidNotificationDetails(
          'workout_timers',
          'Workout timers',
          channelDescription: 'Rest and work timer completion alerts',
          importance: Importance.high,
          priority: Priority.high,
          playSound: playSound,
          enableVibration: enableVibration,
          category: AndroidNotificationCategory.alarm,
        ),
        iOS: DarwinNotificationDetails(
          presentAlert: true,
          presentSound: playSound,
        ),
      ),
      androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
      payload: jsonEncode({'type': 'workout_timer'}),
    );
  }

  Future<void> cancelWorkoutTimer() =>
      _plugin.cancel(workoutTimerNotificationId);

  void _handleResponse(NotificationResponse response) {
    final payload = response.payload;
    if (payload == null || payload.isEmpty) {
      return;
    }

    try {
      final decoded = jsonDecode(payload);
      if (decoded is Map) {
        _onTap?.call(Map<String, dynamic>.from(decoded));
      }
    } on FormatException {
      debugPrint('[notifications] ignored malformed chat payload');
    }
  }
}
