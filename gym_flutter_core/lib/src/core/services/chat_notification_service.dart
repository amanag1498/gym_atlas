import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

typedef ChatNotificationTap = void Function(Map<String, dynamic> data);

class ChatNotificationService {
  ChatNotificationService({FlutterLocalNotificationsPlugin? plugin})
    : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  static const _channel = AndroidNotificationChannel(
    'chat_messages',
    'Chat messages',
    description: 'Messages from your trainer or member',
    importance: Importance.high,
  );

  final FlutterLocalNotificationsPlugin _plugin;
  ChatNotificationTap? _onTap;

  Future<void> initialize(ChatNotificationTap onTap) async {
    _onTap = onTap;
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) {
      return;
    }

    await _plugin.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('ic_stat_chat'),
      ),
      onDidReceiveNotificationResponse: _handleResponse,
    );
    await _plugin
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >()
        ?.createNotificationChannel(_channel);

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
    if (kIsWeb ||
        defaultTargetPlatform != TargetPlatform.android ||
        data['type'] != 'chat_message') {
      return;
    }

    final messageId = int.tryParse(data['message_id']?.toString() ?? '');
    await _plugin.show(
      messageId ?? DateTime.now().millisecondsSinceEpoch.remainder(1 << 31),
      title,
      body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'chat_messages',
          'Chat messages',
          channelDescription: 'Messages from your trainer or member',
          importance: Importance.high,
          priority: Priority.high,
          category: AndroidNotificationCategory.message,
        ),
      ),
      payload: jsonEncode(data),
    );
  }

  void _handleResponse(NotificationResponse response) {
    final payload = response.payload;
    if (payload == null || payload.isEmpty) {
      return;
    }

    try {
      final decoded = jsonDecode(payload);
      if (decoded is Map && decoded['type'] == 'chat_message') {
        _onTap?.call(Map<String, dynamic>.from(decoded));
      }
    } on FormatException {
      debugPrint('[notifications] ignored malformed chat payload');
    }
  }
}
