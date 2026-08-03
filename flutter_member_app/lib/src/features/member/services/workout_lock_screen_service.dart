import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:permission_handler/permission_handler.dart';

class WorkoutLockScreenAction {
  const WorkoutLockScreenAction({
    required this.action,
    required this.sessionId,
    this.exerciseId,
    this.setNumber,
    this.reps,
    this.weight,
  });

  final String action;
  final int sessionId;
  final int? exerciseId;
  final int? setNumber;
  final int? reps;
  final double? weight;

  factory WorkoutLockScreenAction.fromMap(Map<dynamic, dynamic> map) {
    return WorkoutLockScreenAction(
      action: map['action']?.toString() ?? '',
      sessionId: _asInt(map['sessionId']) ?? 0,
      exerciseId: _asInt(map['exerciseId']),
      setNumber: _asInt(map['setNumber']),
      reps: _asInt(map['reps']),
      weight: _asDouble(map['weight']),
    );
  }

  static int? _asInt(Object? value) {
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  static double? _asDouble(Object? value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }
}

class WorkoutLockScreenService {
  WorkoutLockScreenService({
    MethodChannel? methodChannel,
    EventChannel? eventChannel,
  }) : _methodChannel =
           methodChannel ??
           const MethodChannel(
             'com.techybugs.gymatlas.member/workout_lock_screen',
           ),
       _eventChannel =
           eventChannel ??
           const EventChannel(
             'com.techybugs.gymatlas.member/workout_lock_screen_actions',
           );

  final MethodChannel _methodChannel;
  final EventChannel _eventChannel;
  Stream<WorkoutLockScreenAction>? _actions;

  Stream<WorkoutLockScreenAction> get actions {
    return _actions ??= _eventChannel
        .receiveBroadcastStream()
        .where((event) => event is Map)
        .map(
          (event) => WorkoutLockScreenAction.fromMap(
            Map<dynamic, dynamic>.from(event as Map),
          ),
        )
        .where((event) => event.action.isNotEmpty && event.sessionId > 0)
        .handleError((Object error) {
          debugPrint('[workout-lock-screen] action stream unavailable: $error');
        });
  }

  Future<bool> ensurePermissions() async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) {
      return true;
    }
    final notification = await Permission.notification.request();
    final activity = await Permission.activityRecognition.request();
    if (notification.isPermanentlyDenied || activity.isPermanentlyDenied) {
      await openAppSettings();
    }
    return notification.isGranted && activity.isGranted;
  }

  Future<bool> hasPermissions() async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) {
      return true;
    }
    final notification = await Permission.notification.status;
    final activity = await Permission.activityRecognition.status;
    return notification.isGranted && activity.isGranted;
  }

  Future<bool> openSettings() => openAppSettings();

  Future<bool> isSupported() async {
    try {
      return await _methodChannel.invokeMethod<bool>('isSupported') ?? false;
    } on MissingPluginException {
      return false;
    } on PlatformException catch (error) {
      debugPrint('[workout-lock-screen] support check failed: $error');
      return false;
    }
  }

  Future<bool> start(Map<String, dynamic> state) => _invoke('start', state);

  Future<bool> update(Map<String, dynamic> state) => _invoke('update', state);

  Future<bool> end({required int sessionId, bool completed = true}) => _invoke(
    'end',
    <String, dynamic>{'sessionId': sessionId, 'completed': completed},
  );

  Future<bool> _invoke(String method, Map<String, dynamic> arguments) async {
    try {
      return await _methodChannel.invokeMethod<bool>(method, arguments) ?? true;
    } on MissingPluginException {
      // Desktop, web and older builds intentionally have no native surface.
      return false;
    } on PlatformException catch (error) {
      debugPrint('[workout-lock-screen] $method failed: $error');
      return false;
    }
  }
}
