import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_ble_scanner.dart';
import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_controller.dart';
import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_debug_overlay.dart';
import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_detection.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('debug overlay exposes background scanner and latest hub state', (
    tester,
  ) async {
    final scanner = _DebugScanner();
    final controller = SmartAttendanceController(
      scanner: scanner,
      requestPermissions: () async => true,
    );
    await controller.startBackgroundScan();
    scanner.emit(
      SmartAttendanceDetection(
        publicId: 'SAH2BBTXY0CCBJV',
        protocolVersion: 2,
        rssi: -58,
        detectedAt: DateTime(2026, 9, 27, 12),
        source: 'ios_background_ble',
      ),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: SmartAttendanceDebugOverlay(
          controller: controller,
          child: const Scaffold(body: SizedBox.expand()),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('SA BG'), findsOneWidget);
    await tester.tap(
      find.byKey(const ValueKey('smart-attendance-debug-control')),
    );
    await tester.pumpAndSettle();

    expect(find.text('Smart Attendance diagnostics'), findsOneWidget);
    expect(find.text('Bluetooth permission'), findsOneWidget);
    expect(find.text('granted'), findsOneWidget);
    expect(find.text('Background'), findsOneWidget);
    expect(find.text('SAH2BBTXY0CCBJV'), findsOneWidget);
    expect(find.text('ios_background_ble'), findsOneWidget);
  });
}

class _DebugScanner implements SmartAttendanceBleScanner {
  final _detections = StreamController<SmartAttendanceDetection>.broadcast();
  final _diagnostics =
      StreamController<SmartAttendanceScanDiagnostic>.broadcast();

  @override
  Stream<SmartAttendanceDetection> get detections => _detections.stream;

  @override
  Stream<SmartAttendanceScanDiagnostic> get diagnostics => _diagnostics.stream;

  void emit(SmartAttendanceDetection detection) => _detections.add(detection);

  @override
  Future<int?> androidSdkInt() async => 35;

  @override
  Future<void> startBackgroundScan() async {}

  @override
  Future<void> startForegroundScan() async {}

  @override
  Future<void> stopScan() async {}
}
