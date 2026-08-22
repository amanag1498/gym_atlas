import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart'
    show TargetPlatform, debugPrint, defaultTargetPlatform, kIsWeb;

import '../network/api_client.dart';

class AdminFcmTokenService {
  AdminFcmTokenService(this._client);

  final ApiClient _client;
  bool _listeningForRefresh = false;
  bool _registrationInFlight = false;
  Timer? _retryTimer;
  int _retryAttempt = 0;

  Future<void> registerToken() async {
    _retryTimer?.cancel();
    _retryAttempt = 0;
    await _attemptRegistration();
  }

  Future<void> _attemptRegistration() async {
    if (_registrationInFlight) return;

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
        debugPrint('[fcm][admin] notification permission denied');
        _retryTimer?.cancel();
        return;
      }

      _listenForTokenRefresh(messaging);
      if (!await _waitForApnsRegistration(messaging)) {
        _scheduleRetry();
        return;
      }

      final token = await messaging.getToken();
      if (token == null || token.isEmpty) {
        _scheduleRetry();
        return;
      }

      await _sendToken(token);
      _retryAttempt = 0;
      _retryTimer?.cancel();
      debugPrint('[fcm][admin] token registered');
    } catch (exception) {
      debugPrint('[fcm][admin] token registration failed: $exception');
      _scheduleRetry();
    } finally {
      _registrationInFlight = false;
    }
  }

  void _listenForTokenRefresh(FirebaseMessaging messaging) {
    if (_listeningForRefresh) return;

    _listeningForRefresh = true;
    messaging.onTokenRefresh.listen(
      (updatedToken) {
        if (updatedToken.isEmpty) return;
        unawaited(_sendToken(updatedToken));
      },
      onError: (Object exception) {
        debugPrint('[fcm][admin] token refresh listener failed: $exception');
        _scheduleRetry();
      },
    );
  }

  void _scheduleRetry() {
    if (_retryTimer?.isActive == true) return;

    _retryAttempt = (_retryAttempt + 1).clamp(1, 6).toInt();
    final delaySeconds = 1 << (_retryAttempt - 1);
    _retryTimer = Timer(
      Duration(seconds: delaySeconds),
      () => unawaited(_attemptRegistration()),
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
      if (apnsToken != null && apnsToken.isNotEmpty) return true;
      await Future<void>.delayed(const Duration(milliseconds: 500));
    }

    return false;
  }

  Future<void> _sendToken(String token) {
    return _client.post(
      '/fcm-tokens',
      data: {
        'token': token,
        'platform': _platformLabel(),
        'app_role': 'admin',
        'device_name': 'admin-${_platformLabel()}',
      },
    );
  }

  Future<void> unregisterCurrentToken() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.isEmpty) return;
      await _client.delete('/fcm-tokens', data: {'token': token});
    } catch (exception) {
      debugPrint('[fcm][admin] token unregister skipped: $exception');
    }
  }

  String _platformLabel() {
    if (kIsWeb) return 'web';

    return switch (defaultTargetPlatform) {
      TargetPlatform.android => 'android',
      TargetPlatform.iOS => 'ios',
      TargetPlatform.macOS => 'macos',
      TargetPlatform.windows => 'windows',
      TargetPlatform.linux => 'linux',
      TargetPlatform.fuchsia => 'fuchsia',
    };
  }
}
