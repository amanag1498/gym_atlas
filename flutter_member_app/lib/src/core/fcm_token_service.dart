import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart'
    show TargetPlatform, debugPrint, defaultTargetPlatform, kIsWeb;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:gym_flutter_core/fcm_retry_policy.dart';

import 'api_client.dart';

class MemberFcmTokenService {
  MemberFcmTokenService(this._client, {FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const _devicePresenceKey = 'member_device_presence_id';
  static const _appVersion = String.fromEnvironment('APP_VERSION');

  final MemberApiClient _client;
  final FlutterSecureStorage _storage;
  final FcmRetryPolicy _retryPolicy = FcmRetryPolicy();
  Timer? _retryTimer;
  StreamSubscription<String>? _tokenRefreshSubscription;
  Future<void>? _registrationFuture;
  final Set<Future<void>> _refreshRegistrations = {};
  String? _lastAppRole;
  String? _registeredToken;
  int _generation = 0;
  bool _active = false;

  Future<void> registerPresence({required String appRole}) async {
    try {
      await _sendPresence(appRole);
      debugPrint('[fcm] app presence recorded for $appRole app');
    } catch (exception) {
      debugPrint('[fcm] app presence registration skipped: $exception');
    }
  }

  Future<void> registerToken({required String appRole}) async {
    _active = true;
    _lastAppRole = appRole;
    _retryTimer?.cancel();
    _retryTimer = null;
    _retryPolicy.reset();
    final generation = ++_generation;

    final previousRegistration = _registrationFuture;
    if (previousRegistration != null) {
      await previousRegistration;
    }
    if (!_isCurrent(appRole, generation)) return;
    await _runRegistration(appRole, generation);
  }

  Future<void> _runRegistration(String appRole, int generation) async {
    if (!_isCurrent(appRole, generation)) return;
    final registration = _attemptRegistration(appRole, generation);
    _registrationFuture = registration;
    try {
      await registration;
    } finally {
      if (identical(_registrationFuture, registration)) {
        _registrationFuture = null;
      }
    }
  }

  Future<void> _attemptRegistration(String appRole, int generation) async {
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.setAutoInitEnabled(true);
      if (!_isCurrent(appRole, generation)) return;

      final settings = await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );
      if (!_isCurrent(appRole, generation)) return;
      if (settings.authorizationStatus == AuthorizationStatus.denied) {
        debugPrint('[fcm] notification permission denied');
        _retryTimer?.cancel();
        return;
      }

      _listenForTokenRefresh(messaging);
      if (!await _waitForApnsRegistration(messaging, appRole, generation)) {
        if (_isCurrent(appRole, generation)) {
          debugPrint('[fcm] APNs registration timed out');
          _scheduleRetry(appRole, generation);
        }
        return;
      }

      final token = await messaging.getToken();
      if (!_isCurrent(appRole, generation)) return;
      if (token == null || token.isEmpty) {
        _scheduleRetry(appRole, generation);
        return;
      }

      _registeredToken = token;
      await _sendToken(token, appRole);
      if (!_isCurrent(appRole, generation)) return;
      _retryPolicy.reset();
      _retryTimer?.cancel();
      _retryTimer = null;
      debugPrint('[fcm] token registered for $appRole app');
    } catch (exception) {
      if (!_isCurrent(appRole, generation)) return;
      debugPrint('[fcm] token registration failed: $exception');
      _scheduleRetry(appRole, generation);
    }
  }

  void _scheduleRetry(String appRole, int generation) {
    if (!_isCurrent(appRole, generation) || _retryTimer?.isActive == true) {
      return;
    }

    final delay = _retryPolicy.nextDelay();
    if (delay == null) {
      debugPrint('[fcm] token registration paused after repeated failures');
      return;
    }
    _retryTimer = Timer(delay, () {
      _retryTimer = null;
      unawaited(_runRegistration(appRole, generation));
    });
    debugPrint('[fcm] retrying token registration in ${delay.inSeconds}s');
  }

  void _listenForTokenRefresh(FirebaseMessaging messaging) {
    if (_tokenRefreshSubscription != null) return;

    _tokenRefreshSubscription = messaging.onTokenRefresh.listen(
      (updatedToken) {
        final appRole = _lastAppRole;
        final generation = _generation;
        if (!_active || appRole == null || updatedToken.isEmpty) return;
        final registration = _registerRefreshedToken(
          updatedToken,
          appRole,
          generation,
        );
        _refreshRegistrations.add(registration);
        unawaited(
          registration.whenComplete(
            () => _refreshRegistrations.remove(registration),
          ),
        );
      },
      onError: (Object exception) {
        final appRole = _lastAppRole;
        if (!_active || appRole == null) return;
        debugPrint('[fcm] token refresh listener failed: $exception');
        _scheduleRetry(appRole, _generation);
      },
    );
  }

  Future<void> _registerRefreshedToken(
    String token,
    String appRole,
    int generation,
  ) async {
    if (!_isCurrent(appRole, generation)) return;
    try {
      _registeredToken = token;
      await _sendToken(token, appRole);
      if (!_isCurrent(appRole, generation)) return;
      _retryPolicy.reset();
      _retryTimer?.cancel();
      _retryTimer = null;
    } catch (exception) {
      if (!_isCurrent(appRole, generation)) return;
      debugPrint('[fcm] refreshed token registration failed: $exception');
      _scheduleRetry(appRole, generation);
    }
  }

  Future<bool> _waitForApnsRegistration(
    FirebaseMessaging messaging,
    String appRole,
    int generation,
  ) async {
    if (kIsWeb ||
        (defaultTargetPlatform != TargetPlatform.iOS &&
            defaultTargetPlatform != TargetPlatform.macOS)) {
      return true;
    }

    for (var attempt = 0; attempt < 20; attempt++) {
      if (!_isCurrent(appRole, generation)) return false;
      final apnsToken = await messaging.getAPNSToken();
      if (apnsToken != null && apnsToken.isNotEmpty) return true;
      await Future<void>.delayed(const Duration(milliseconds: 500));
    }
    return false;
  }

  Future<void> unregisterCurrentToken() async {
    final inFlightRegistration = _registrationFuture;
    final inFlightRefreshes = List<Future<void>>.of(_refreshRegistrations);
    await stop();
    if (inFlightRegistration != null) {
      await inFlightRegistration;
    }
    if (inFlightRefreshes.isNotEmpty) {
      await Future.wait(inFlightRefreshes);
    }

    try {
      final token = _registeredToken ?? await _readTokenIfAvailable();
      if (token != null && token.isNotEmpty) {
        await _client.delete(
          '/fcm-tokens',
          data: {
            'token': token,
            'app_role': _lastAppRole ?? 'member',
            'device_id': await _devicePresenceId(),
          },
        );
      }
    } catch (exception) {
      debugPrint('[fcm] token unregister skipped: $exception');
    } finally {
      _registeredToken = null;
      await _deleteNativeToken();
    }
  }

  Future<void> stop({bool deleteNativeToken = false}) async {
    final shouldDeleteNativeToken =
        deleteNativeToken &&
        (_active ||
            _registeredToken != null ||
            _registrationFuture != null ||
            _refreshRegistrations.isNotEmpty);
    _active = false;
    _lastAppRole = null;
    _generation++;
    _retryTimer?.cancel();
    _retryTimer = null;
    _retryPolicy.reset();
    if (shouldDeleteNativeToken) await _deleteNativeToken();
  }

  void dispose() {
    _active = false;
    _generation++;
    _retryTimer?.cancel();
    _retryTimer = null;
    final subscription = _tokenRefreshSubscription;
    _tokenRefreshSubscription = null;
    if (subscription != null) unawaited(subscription.cancel());
  }

  bool _isCurrent(String appRole, int generation) {
    return _active && _lastAppRole == appRole && _generation == generation;
  }

  Future<String?> _readTokenIfAvailable() async {
    final messaging = FirebaseMessaging.instance;
    if (!kIsWeb &&
        (defaultTargetPlatform == TargetPlatform.iOS ||
            defaultTargetPlatform == TargetPlatform.macOS)) {
      final apnsToken = await messaging.getAPNSToken();
      if (apnsToken == null || apnsToken.isEmpty) return null;
    }
    return messaging.getToken();
  }

  Future<void> _deleteNativeToken() async {
    try {
      await FirebaseMessaging.instance.deleteToken();
    } catch (exception) {
      debugPrint('[fcm] native token cleanup skipped: $exception');
    }
  }

  Future<void> _sendPresence(String appRole) async {
    await _client.post(
      '/app-presence',
      data: {
        'platform': _platformLabel(),
        'app_role': appRole,
        'device_name': _deviceName(),
        'device_id': await _devicePresenceId(),
        if (_appVersion.isNotEmpty) 'app_version': _appVersion,
      },
    );
  }

  Future<void> _sendToken(String token, String appRole) async {
    await _client.post(
      '/fcm-tokens',
      data: {
        'token': token,
        'platform': _platformLabel(),
        'app_role': appRole,
        'device_name': _deviceName(),
        'device_id': await _devicePresenceId(),
        if (_appVersion.isNotEmpty) 'app_version': _appVersion,
      },
    );
  }

  Future<String> _devicePresenceId() async {
    final existing = await _storage.read(key: _devicePresenceKey);
    if (existing != null && existing.isNotEmpty) return existing;

    final random = Random.secure();
    final bytes = List<int>.generate(18, (_) => random.nextInt(256));
    final id = base64UrlEncode(bytes).replaceAll('=', '');
    await _storage.write(key: _devicePresenceKey, value: id);
    return id;
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

  String _deviceName() {
    if (kIsWeb) return 'member-web';
    return 'member-${_platformLabel()}';
  }
}
