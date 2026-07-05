import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:health/health.dart';
import 'package:permission_handler/permission_handler.dart';

import 'step_health_types.dart';

class StepHealthService {
  StepHealthService({Health? health}) : _health = health ?? Health();

  static const MethodChannel _stepSensorChannel = MethodChannel(
    'com.techybugs.gymatlas.member/step_sensor',
  );

  final Health _health;
  bool _configured = false;

  Future<StepHealthSnapshot> readTodaySummary() async {
    await _configureIfNeeded();

    if (kIsWeb || (!Platform.isAndroid && !Platform.isIOS)) {
      return const StepHealthSnapshot(
        steps: 0,
        distanceMeters: 0,
        caloriesEstimated: 0,
        source: 'health_connect',
        permissionStatus: StepPermissionStatus.unavailable,
      );
    }

    if (Platform.isAndroid) {
      final activityPermission = await _requestActivityRecognitionPermission();
      if (activityPermission != StepPermissionStatus.granted) {
        return StepHealthSnapshot(
          steps: 0,
          distanceMeters: 0,
          caloriesEstimated: 0,
          source: 'android_sensor',
          permissionStatus: activityPermission,
        );
      }

      final healthConnectAvailable = await _isHealthConnectAvailable();
      if (!healthConnectAvailable) {
        return _readAndroidSensorFallback();
      }
    }

    final start = _startOfToday();
    final now = DateTime.now();
    final source = Platform.isIOS ? 'healthkit' : 'health_connect';

    final granted = await _requestReadAccess();
    if (!granted) {
      return StepHealthSnapshot(
        steps: 0,
        distanceMeters: 0,
        caloriesEstimated: 0,
        source: source,
        permissionStatus: StepPermissionStatus.denied,
      );
    }

    try {
      final totalSteps = await _health.getTotalStepsInInterval(start, now) ?? 0;
      final healthData = await _health.getHealthDataFromTypes(
        startTime: start,
        endTime: now,
        types: _supportedTypes(),
      );

      return StepHealthSnapshot(
        steps: totalSteps,
        distanceMeters: _sumData(
          healthData,
          types: <HealthDataType>{
            HealthDataType.DISTANCE_DELTA,
            HealthDataType.DISTANCE_WALKING_RUNNING,
          },
        ),
        caloriesEstimated: _sumData(
          healthData,
          types: <HealthDataType>{HealthDataType.ACTIVE_ENERGY_BURNED},
        ),
        source: source,
        permissionStatus: StepPermissionStatus.granted,
      );
    } catch (exception, stackTrace) {
      debugPrint('[steps][health] failed to read local health data: $exception');
      debugPrintStack(stackTrace: stackTrace);
      return StepHealthSnapshot(
        steps: 0,
        distanceMeters: 0,
        caloriesEstimated: 0,
        source: source,
        permissionStatus: StepPermissionStatus.unavailable,
      );
    }
  }

  Future<StepHealthSnapshot> requestStepPermission() async {
    await _configureIfNeeded();

    if (kIsWeb || (!Platform.isAndroid && !Platform.isIOS)) {
      return const StepHealthSnapshot(
        steps: 0,
        distanceMeters: 0,
        caloriesEstimated: 0,
        source: 'health_connect',
        permissionStatus: StepPermissionStatus.unavailable,
      );
    }

    if (Platform.isAndroid) {
      final activityPermission = await _requestActivityRecognitionPermission();
      if (activityPermission != StepPermissionStatus.granted) {
        return StepHealthSnapshot(
          steps: 0,
          distanceMeters: 0,
          caloriesEstimated: 0,
          source: 'android_sensor',
          permissionStatus: activityPermission,
        );
      }

      final healthConnectAvailable = await _isHealthConnectAvailable();
      if (!healthConnectAvailable) {
        return const StepHealthSnapshot(
          steps: 0,
          distanceMeters: 0,
          caloriesEstimated: 0,
          source: 'android_sensor',
          permissionStatus: StepPermissionStatus.granted,
        );
      }
    }

    final source = Platform.isIOS ? 'healthkit' : 'health_connect';
    final granted = await _requestReadAccess();

    return StepHealthSnapshot(
      steps: 0,
      distanceMeters: 0,
      caloriesEstimated: 0,
      source: source,
      permissionStatus: granted
          ? StepPermissionStatus.granted
          : StepPermissionStatus.denied,
    );
  }

  Future<bool> _isHealthConnectAvailable() async {
    try {
      final sdkStatus = await _health.getHealthConnectSdkStatus();
      return sdkStatus == HealthConnectSdkStatus.sdkAvailable;
    } catch (exception) {
      debugPrint(
        '[steps][health] Health Connect availability check failed: $exception',
      );
      return false;
    }
  }

  Future<StepPermissionStatus> _requestActivityRecognitionPermission() async {
    final activityPermission = await Permission.activityRecognition.status;
    if (activityPermission.isGranted) {
      return StepPermissionStatus.granted;
    }
    if (activityPermission.isPermanentlyDenied ||
        activityPermission.isRestricted) {
      return StepPermissionStatus.denied;
    }

    if (!activityPermission.isGranted) {
      final requested = await Permission.activityRecognition.request();
      if (requested.isGranted) {
        return StepPermissionStatus.granted;
      }
      return requested.isPermanentlyDenied || requested.isRestricted
          ? StepPermissionStatus.denied
          : StepPermissionStatus.denied;
    }

    return StepPermissionStatus.denied;
  }

  Future<StepHealthSnapshot> _readAndroidSensorFallback() async {
    try {
      final payload = await _stepSensorChannel.invokeMapMethod<String, dynamic>(
        'readTodaySensorSteps',
      );

      if (payload == null || payload['available'] != true) {
        return const StepHealthSnapshot(
          steps: 0,
          distanceMeters: 0,
          caloriesEstimated: 0,
          source: 'android_sensor',
          permissionStatus: StepPermissionStatus.unavailable,
        );
      }

      return StepHealthSnapshot(
        steps: (payload['steps'] as num?)?.round() ?? 0,
        distanceMeters: (payload['distanceMeters'] as num?)?.round() ?? 0,
        caloriesEstimated:
            (payload['caloriesEstimated'] as num?)?.round() ?? 0,
        source: 'android_sensor',
        permissionStatus: StepPermissionStatus.granted,
      );
    } on PlatformException catch (exception, stackTrace) {
      debugPrint(
        '[steps][health] Android step sensor fallback failed: ${exception.message ?? exception.code}',
      );
      debugPrintStack(stackTrace: stackTrace);
      return const StepHealthSnapshot(
        steps: 0,
        distanceMeters: 0,
        caloriesEstimated: 0,
        source: 'android_sensor',
        permissionStatus: StepPermissionStatus.unavailable,
      );
    } catch (exception, stackTrace) {
      debugPrint('[steps][health] Android step sensor fallback failed: $exception');
      debugPrintStack(stackTrace: stackTrace);
      return const StepHealthSnapshot(
        steps: 0,
        distanceMeters: 0,
        caloriesEstimated: 0,
        source: 'android_sensor',
        permissionStatus: StepPermissionStatus.unavailable,
      );
    }
  }

  Future<bool> _requestReadAccess() async {
    final types = _supportedTypes();
    final permissions = types.map((_) => HealthDataAccess.READ).toList();

    try {
      final hasPermissions = await _health.hasPermissions(
        types,
        permissions: permissions,
      );
      if (hasPermissions == true) {
        return true;
      }

      return await _health.requestAuthorization(
        types,
        permissions: permissions,
      );
    } catch (exception) {
      debugPrint('[steps][health] permission request failed: $exception');
      return false;
    }
  }

  Future<void> _configureIfNeeded() async {
    if (_configured) {
      return;
    }

    await _health.configure();
    _configured = true;
  }

  List<HealthDataType> _supportedTypes() {
    if (Platform.isIOS) {
      return const <HealthDataType>[
        HealthDataType.STEPS,
        HealthDataType.ACTIVE_ENERGY_BURNED,
        HealthDataType.DISTANCE_WALKING_RUNNING,
      ];
    }

    return const <HealthDataType>[
      HealthDataType.STEPS,
      HealthDataType.ACTIVE_ENERGY_BURNED,
      HealthDataType.DISTANCE_DELTA,
    ];
  }

  int _sumData(
    List<HealthDataPoint> points, {
    required Set<HealthDataType> types,
  }) {
    var sum = 0.0;
    for (final point in points) {
      if (!types.contains(point.type)) {
        continue;
      }

      final value = point.toJson()['value'];
      if (value is num) {
        sum += value.toDouble();
      } else if (value is Map && value['numeric_value'] is num) {
        sum += (value['numeric_value'] as num).toDouble();
      }
    }

    return sum.round();
  }

  DateTime _startOfToday() {
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day);
  }
}
