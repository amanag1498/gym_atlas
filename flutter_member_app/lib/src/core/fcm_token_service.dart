import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart'
    show TargetPlatform, debugPrint, defaultTargetPlatform, kIsWeb;

import 'api_client.dart';

class MemberFcmTokenService {
  MemberFcmTokenService(this._client);

  final MemberApiClient _client;
  bool _listeningForRefresh = false;

  Future<void> registerToken({required String appRole}) async {
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      final token = await messaging.getToken();
      if (token == null || token.isEmpty) {
        return;
      }

      await _client.post(
        '/fcm-tokens',
        data: {
          'token': token,
          'platform': _platformLabel(),
          'app_role': appRole,
          'device_name': _deviceName(),
        },
      );

      if (!_listeningForRefresh) {
        _listeningForRefresh = true;
        FirebaseMessaging.instance.onTokenRefresh.listen((updatedToken) {
          if (updatedToken.isEmpty) {
            return;
          }
          _client.post(
            '/fcm-tokens',
            data: {
              'token': updatedToken,
              'platform': _platformLabel(),
              'app_role': appRole,
              'device_name': _deviceName(),
            },
          );
        });
      }
    } catch (exception) {
      debugPrint('[fcm] token registration skipped: $exception');
    }
  }

  Future<void> unregisterCurrentToken() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.isEmpty) {
        return;
      }
      await _client.delete('/fcm-tokens', data: {'token': token});
    } catch (exception) {
      debugPrint('[fcm] token unregister skipped: $exception');
    }
  }

  String _platformLabel() {
    if (kIsWeb) {
      return 'web';
    }
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return 'android';
      case TargetPlatform.iOS:
        return 'ios';
      case TargetPlatform.macOS:
        return 'macos';
      case TargetPlatform.windows:
        return 'windows';
      case TargetPlatform.linux:
        return 'linux';
      case TargetPlatform.fuchsia:
        return 'fuchsia';
    }
  }

  String _deviceName() {
    if (kIsWeb) {
      return 'member-web';
    }
    return 'member-${_platformLabel()}';
  }
}
