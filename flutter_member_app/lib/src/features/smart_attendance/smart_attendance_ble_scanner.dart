import 'dart:async';
import 'package:flutter/services.dart';

import 'smart_attendance_ble_protocol.dart';
import 'smart_attendance_detection.dart';

abstract class SmartAttendanceBleScanner {
  Stream<SmartAttendanceDetection> get detections;
  Stream<SmartAttendanceScanDiagnostic> get diagnostics;

  Future<void> startForegroundScan();
  Future<void> startBackgroundScan();
  Future<void> stopScan();
  Future<int?> androidSdkInt();
}

class MethodChannelSmartAttendanceBleScanner
    implements SmartAttendanceBleScanner {
  MethodChannelSmartAttendanceBleScanner({
    MethodChannel? methodChannel,
    EventChannel? eventChannel,
    DateTime Function()? clock,
  }) : _methodChannel =
           methodChannel ?? const MethodChannel(_methodChannelName),
       _eventChannel = eventChannel ?? const EventChannel(_eventChannelName),
       _clock = clock ?? DateTime.now;

  static const _methodChannelName =
      'com.techybugs.gymatlas.member/smart_attendance_ble';
  static const _eventChannelName =
      'com.techybugs.gymatlas.member/smart_attendance_ble_events';

  final MethodChannel _methodChannel;
  final EventChannel _eventChannel;
  final DateTime Function() _clock;
  Stream<dynamic>? _nativeEvents;
  final _diagnostics =
      StreamController<SmartAttendanceScanDiagnostic>.broadcast();

  @override
  Stream<SmartAttendanceDetection> get detections =>
      _events().map(_parseNativeEvent).where((event) {
        if (event == null) {
          return false;
        }
        return true;
      }).cast<SmartAttendanceDetection>();

  @override
  Stream<SmartAttendanceScanDiagnostic> get diagnostics => _diagnostics.stream;

  @override
  Future<void> startForegroundScan() async {
    await _methodChannel.invokeMethod<void>('startForegroundScan', {
      'serviceUuid': SmartAttendanceBleProtocol.serviceUuid,
    });
  }

  @override
  Future<void> startBackgroundScan() async {
    await _methodChannel.invokeMethod<void>('startBackgroundScan', {
      'serviceUuid': SmartAttendanceBleProtocol.serviceUuid,
    });
  }

  @override
  Future<void> stopScan() async {
    await _methodChannel.invokeMethod<void>('stopScan');
  }

  @override
  Future<int?> androidSdkInt() =>
      _methodChannel.invokeMethod<int>('androidSdkInt');

  Stream<dynamic> _events() {
    return _nativeEvents ??= _eventChannel.receiveBroadcastStream({
      'serviceUuid': SmartAttendanceBleProtocol.serviceUuid,
    });
  }

  SmartAttendanceDetection? _parseNativeEvent(dynamic event) {
    if (event is! Map) {
      _diagnostics.add(_diagnostic('Ignoring malformed BLE event.'));
      return null;
    }

    final diagnostic = event['diagnostic']?.toString();
    if (diagnostic != null && diagnostic.isNotEmpty) {
      _diagnostics.add(
        SmartAttendanceScanDiagnostic(
          message: diagnostic,
          detectedAt: _dateTime(event['detectedAt']) ?? _clock(),
          rssi: event['rssi'] is num ? (event['rssi'] as num).round() : null,
          source: event['source']?.toString(),
        ),
      );
      return null;
    }

    final serviceUuid = '${event['serviceUuid'] ?? ''}'.toLowerCase();
    if (serviceUuid != SmartAttendanceBleProtocol.serviceUuid) {
      return null;
    }

    final raw = _bytes(event['serviceData']);
    final payload = SmartAttendanceBleProtocol.parseServiceData(raw);
    final detectedAt = _dateTime(event['detectedAt']) ?? _clock();
    final rssi = event['rssi'] is num ? (event['rssi'] as num).round() : null;
    final source = '${event['source'] ?? 'platform'}';

    if (payload == null) {
      _diagnostics.add(
        SmartAttendanceScanDiagnostic(
          message: raw == null
              ? 'Atlas hub signal found, but scan response service data was missing.'
              : 'Atlas hub signal ignored because the BLE payload did not match V1.',
          detectedAt: detectedAt,
          rssi: rssi,
          source: source,
        ),
      );
      return null;
    }

    return SmartAttendanceDetection(
      publicId: payload.publicId,
      protocolVersion: payload.protocolVersion,
      rssi: rssi,
      detectedAt: detectedAt,
      source: source,
      rawServiceData: Uint8List.fromList(raw!),
    );
  }

  SmartAttendanceScanDiagnostic _diagnostic(String message) =>
      SmartAttendanceScanDiagnostic(message: message, detectedAt: _clock());

  DateTime? _dateTime(dynamic value) {
    if (value is String) {
      return DateTime.tryParse(value);
    }
    if (value is int) {
      return DateTime.fromMillisecondsSinceEpoch(value);
    }
    return null;
  }

  List<int>? _bytes(dynamic value) {
    if (value is Uint8List) {
      return value;
    }
    if (value is List<int>) {
      return value;
    }
    if (value is List) {
      final bytes = <int>[];
      for (final item in value) {
        if (item is! num || item < 0 || item > 255) {
          return null;
        }
        bytes.add(item.round());
      }
      return bytes;
    }
    return null;
  }
}
