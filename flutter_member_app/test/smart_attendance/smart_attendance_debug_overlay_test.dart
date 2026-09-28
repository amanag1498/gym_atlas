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
    final navigatorKey = GlobalKey<NavigatorState>();

    await tester.pumpWidget(
      MaterialApp(
        navigatorKey: navigatorKey,
        theme: ThemeData(splashFactory: NoSplash.splashFactory),
        home: SmartAttendanceDebugOverlay(
          controller: controller,
          navigatorKey: navigatorKey,
          child: const Scaffold(body: SizedBox.expand()),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('SMART ATTENDANCE DEBUG'), findsOneWidget);
    expect(find.text('BG scan · Bluetooth granted'), findsOneWidget);
    expect(
      find.textContaining('Waiting for an Atlas hub signal'),
      findsNothing,
    );
    expect(find.text('View live logic'), findsOneWidget);
    await tester.tap(find.text('View live logic'));
    await tester.pumpAndSettle();

    expect(find.text('Smart Attendance diagnostics'), findsOneWidget);
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
