import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart'
    show TargetPlatform, debugPrint, defaultTargetPlatform, kIsWeb;

import 'api_client.dart';

class TrainerFcmTokenService {
  TrainerFcmTokenService(this._client);

  final TrainerApiClient _client;
  bool _listeningForRefresh = false;
  bool _registrationInFlight = false;
  Timer? _retryTimer;
  int _retryAttempt = 0;
  String? _lastAppRole;

  Future<void> registerToken({required String appRole}) async {
    _lastAppRole = appRole;
    _retryTimer?.cancel();
    _retryAttempt = 0;
    await _attemptRegistration(appRole);
  }

  Future<void> _attemptRegistration(String appRole) async {
    if (_registrationInFlight) {
      return;
    }

    _registrationInFlight = true;
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.setAutoInitEnabled(true);
      final settings = await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );
      if (settings.authorizationStatus == AuthorizationStatus.denied) {
        debugPrint('[fcm] notification permission denied');
        _retryTimer?.cancel();
        return;
      }

      _listenForTokenRefresh(messaging, appRole);
      if (!await _waitForApnsRegistration(messaging)) {
        debugPrint('[fcm] APNs registration timed out');
        _scheduleRetry();
        return;
      }

      final token = await messaging.getToken();
      if (token == null || token.isEmpty) {
        _scheduleRetry();
        return;
      }

      await _sendToken(token, appRole);
      _retryAttempt = 0;
      _retryTimer?.cancel();
      debugPrint('[fcm] token registered for $appRole app');
    } catch (exception) {
      debugPrint('[fcm] token registration failed: $exception');
      _scheduleRetry();
    } finally {
      _registrationInFlight = false;
    }
  }

  void _scheduleRetry() {
    final appRole = _lastAppRole;
    if (appRole == null || _retryTimer?.isActive == true) {
      return;
    }

    _retryAttempt = (_retryAttempt + 1).clamp(1, 6).toInt();
    final delaySeconds = 1 << (_retryAttempt - 1);
    _retryTimer = Timer(Duration(seconds: delaySeconds), () {
      unawaited(_attemptRegistration(appRole));
    });
    debugPrint('[fcm] retrying token registration in ${delaySeconds}s');
  }

  void _listenForTokenRefresh(FirebaseMessaging messaging, String appRole) {
    if (_listeningForRefresh) {
      return;
    }

    _listeningForRefresh = true;
    messaging.onTokenRefresh.listen(
      (updatedToken) {
        unawaited(
          Future<void>(() async {
            if (updatedToken.isEmpty) {
              return;
            }
            try {
              await _sendToken(updatedToken, appRole);
              _retryAttempt = 0;
              _retryTimer?.cancel();
            } catch (exception) {
              debugPrint(
                '[fcm] refreshed token registration failed: $exception',
              );
              _scheduleRetry();
            }
          }),
        );
      },
      onError: (Object exception) {
        debugPrint('[fcm] token refresh listener failed: $exception');
      },
    );
  }

  Future<bool> _waitForApnsRegistration(FirebaseMessaging messaging) async {
    if (kIsWeb ||
        (defaultTargetPlatform != TargetPlatform.iOS &&
            defaultTargetPlatform != TargetPlatform.macOS)) {
      return true;
    }

    for (var attempt = 0; attempt < 20; attempt++) {
      final apnsToken = await messaging.getAPNSToken();
      if (apnsToken != null && apnsToken.isNotEmpty) {
        return true;
      }
      await Future<void>.delayed(const Duration(milliseconds: 500));
    }

    return false;
  }

  Future<void> _sendToken(String token, String appRole) {
    return _client.post(
      '/fcm-tokens',
      data: {
        'token': token,
        'platform': _platformLabel(),
        'app_role': appRole,
        'device_name': _deviceName(),
      },
    );
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
      return 'trainer-web';
    }
    return 'trainer-${_platformLabel()}';
  }
}
