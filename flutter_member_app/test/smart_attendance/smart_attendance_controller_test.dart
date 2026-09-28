import 'dart:async';

import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_ble_scanner.dart';
import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_check_in_cache.dart';
import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_check_in_client.dart';
import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_controller.dart';
import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_detection.dart';
import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_session_store.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'foreground scan stores detections and suppresses quick duplicates',
    () async {
      final scanner = _FakeScanner();
      var now = DateTime(2026, 9, 26, 10);
      final controller = SmartAttendanceController(
        scanner: scanner,
        requestPermissions: () async => true,
        clock: () => now,
      );

      await controller.startForegroundScan();
      expect(controller.scanning, isTrue);
      expect(scanner.started, isTrue);

      scanner.emit(_detection('SAHABC123DEF4567', now));
      await Future<void>.delayed(Duration.zero);
      expect(controller.detections, hasLength(1));
      expect(controller.latestDetection!.publicId, 'SAHABC123DEF4567');

      now = now.add(const Duration(seconds: 4));
      scanner.emit(_detection('SAHABC123DEF4567', now));
      await Future<void>.delayed(Duration.zero);
      expect(controller.detections, hasLength(1));

      now = now.add(const Duration(seconds: 13));
      scanner.emit(_detection('SAHABC123DEF4567', now));
      await Future<void>.delayed(Duration.zero);
      expect(controller.detections, hasLength(2));

      await controller.stopScan();
      expect(controller.scanning, isFalse);
      expect(scanner.started, isFalse);
    },
  );

  test('background scan uses scanner background mode', () async {
    final scanner = _FakeScanner();
    final controller = SmartAttendanceController(
      scanner: scanner,
      requestPermissions: () async => true,
    );

    await controller.startBackgroundScan();

    expect(controller.scanning, isTrue);
    expect(controller.backgroundScanning, isTrue);
    expect(controller.bluetoothPermissionStatus, 'granted');
    expect(scanner.started, isTrue);
    expect(scanner.backgroundStarted, isTrue);
  });

  test(
    'iOS background discovery records from its coalesced callback',
    () async {
      final scanner = _FakeScanner();
      final client = _FakeCheckInClient();
      final now = DateTime(2026, 9, 28, 12);
      final controller = SmartAttendanceController(
        scanner: scanner,
        checkInClient: client,
        successCache: _FakeSuccessCache(),
        selectedGymIdProvider: () async => 7,
        memberIdProvider: () => 42,
        requestPermissions: () async => true,
        clock: () => now,
        presenceWindow: const Duration(seconds: 3),
        requestDebounce: Duration.zero,
      );

      await controller.startBackgroundScan();
      scanner.emit(
        _detection('SAHABC123DEF4567', now, source: 'ios_background_ble'),
      );
      await _pumpAsync();

      expect(client.calls, hasLength(1));
      expect(
        controller.lastAttendanceRequestResult,
        'First check-in confirmed',
      );
    },
  );

  test('iOS background discovery accepts foreground-cached hub data', () async {
    final scanner = _FakeScanner();
    final client = _FakeCheckInClient();
    final now = DateTime(2026, 9, 28, 12);
    final controller = SmartAttendanceController(
      scanner: scanner,
      checkInClient: client,
      successCache: _FakeSuccessCache(),
      selectedGymIdProvider: () async => 7,
      memberIdProvider: () => 42,
      requestPermissions: () async => true,
      clock: () => now,
      presenceWindow: const Duration(seconds: 3),
      requestDebounce: Duration.zero,
    );

    await controller.startBackgroundScan();
    scanner.emit(
      _detection('SAHABC123DEF4567', now, source: 'ios_background_ble_cached'),
    );
    await _pumpAsync();

    expect(client.calls, hasLength(1));
  });

  test(
    'iOS background beacon checks in once and exits with exact time',
    () async {
      final scanner = _FakeScanner();
      final client = _SessionCheckInClient();
      final store = _FakeSessionStore();
      var now = DateTime(2026, 9, 28, 12);
      final controller = SmartAttendanceController(
        scanner: scanner,
        checkInClient: client,
        sessionStore: store,
        selectedGymIdProvider: () async => 7,
        memberIdProvider: () => 42,
        requestPermissions: () async => true,
        clock: () => now,
        presenceWindow: const Duration(seconds: 3),
        requestDebounce: Duration.zero,
      );

      await controller.startBackgroundScan();
      scanner.emit(
        SmartAttendanceDetection(
          publicId: 'BEACON_17',
          hubId: 17,
          protocolVersion: 3,
          rssi: -61,
          detectedAt: now,
          source: 'ios_background_beacon',
        ),
      );
      await _pumpAsync();

      expect(client.checkInCalls, 1);
      expect(controller.activeSession?.usesExitEvents, isTrue);

      now = now.add(const Duration(hours: 3));
      scanner.emit(
        SmartAttendanceDetection(
          publicId: 'BEACON_17',
          hubId: 17,
          protocolVersion: 3,
          rssi: null,
          detectedAt: now,
          source: 'ios_background_beacon',
          isExit: true,
        ),
      );
      await _pumpAsync();

      expect(client.checkOutCalls, [now]);
      expect(controller.activeSession?.checkedOutAt, now);
    },
  );

  test('starting the same scan mode twice is idempotent', () async {
    final scanner = _FakeScanner();
    final controller = SmartAttendanceController(
      scanner: scanner,
      requestPermissions: () async => true,
    );

    await controller.startForegroundScan();
    await controller.startForegroundScan();

    expect(scanner.foregroundStartCalls, 1);
  });

  test('permission denial prevents scanning', () async {
    final scanner = _FakeScanner();
    final controller = SmartAttendanceController(
      scanner: scanner,
      requestPermissions: () async => false,
    );

    await controller.startForegroundScan();

    expect(controller.scanning, isFalse);
    expect(controller.permissionDenied, isTrue);
    expect(controller.bluetoothPermissionStatus, 'denied');
    expect(scanner.started, isFalse);
  });

  test(
    'qualified detection posts check-in after rssi and presence guards',
    () async {
      final scanner = _FakeScanner();
      final client = _FakeCheckInClient();
      final cache = _FakeSuccessCache();
      var now = DateTime(2026, 9, 26, 10);
      final controller = SmartAttendanceController(
        scanner: scanner,
        checkInClient: client,
        successCache: cache,
        selectedGymIdProvider: () async => 7,
        memberIdProvider: () => 42,
        requestPermissions: () async => true,
        clock: () => now,
        minimumRssi: -70,
        presenceWindow: const Duration(milliseconds: 2400),
        requestDebounce: Duration.zero,
      );

      await controller.startForegroundScan();
      scanner.emit(_detection('SAHABC123DEF4567', now, rssi: -85));
      await Future<void>.delayed(Duration.zero);
      expect(client.calls, isEmpty);

      scanner.emit(_detection('SAHABC123DEF4567', now, rssi: -62));
      await Future<void>.delayed(Duration.zero);
      expect(client.calls, isEmpty);

      now = now.add(const Duration(milliseconds: 2500));
      scanner.emit(_detection('SAHABC123DEF4567', now, rssi: -60));
      await _pumpAsync();

      expect(client.calls, hasLength(1));
      expect(client.calls.single.publicId, 'SAHABC123DEF4567');
      expect(client.calls.single.detectedAt, DateTime(2026, 9, 26, 10));
      expect(
        cache.successfulKeys,
        contains('42|7|SAHABC123DEF4567|2026-09-26'),
      );
      expect(
        controller.lastCheckInMessage,
        'Smart Attendance check-in recorded.',
      );
    },
  );

  test(
    'successful same-day cache does not suppress presence updates',
    () async {
      final scanner = _FakeScanner();
      final client = _FakeCheckInClient();
      final cache = _FakeSuccessCache()
        ..successfulKeys.add('42|7|SAHABC123DEF4567|2026-09-26');
      final now = DateTime(2026, 9, 26, 10);
      final controller = SmartAttendanceController(
        scanner: scanner,
        checkInClient: client,
        successCache: cache,
        selectedGymIdProvider: () async => 7,
        memberIdProvider: () => 42,
        requestPermissions: () async => true,
        clock: () => now,
        presenceWindow: Duration.zero,
        requestDebounce: Duration.zero,
      );

      await controller.startForegroundScan();
      scanner.emit(_detection('SAHABC123DEF4567', now));
      await _pumpAsync();

      expect(client.calls, hasLength(1));
    },
  );

  test(
    'parallel foreground detections do not create parallel check-ins',
    () async {
      final scanner = _FakeScanner();
      final client = _SlowCheckInClient();
      final now = DateTime(2026, 9, 26, 10);
      final controller = SmartAttendanceController(
        scanner: scanner,
        checkInClient: client,
        successCache: _FakeSuccessCache(),
        selectedGymIdProvider: () async => 7,
        memberIdProvider: () => 42,
        requestPermissions: () async => true,
        clock: () => now,
        presenceWindow: Duration.zero,
        requestDebounce: Duration.zero,
      );

      await controller.startForegroundScan();
      scanner.emit(_detection('SAHABC123DEF4567', now));
      scanner.emit(_detection('SAHABC123DEF4567', now));
      await _pumpAsync();

      expect(client.calls, 1);
      expect(controller.checkInInFlight, isTrue);
      client.complete();
      await _pumpAsync();
      expect(controller.checkInInFlight, isFalse);
    },
  );

  test('presence window resets after a long detection gap', () async {
    final scanner = _FakeScanner();
    final client = _FakeCheckInClient();
    var now = DateTime(2026, 9, 26, 10);
    final controller = SmartAttendanceController(
      scanner: scanner,
      checkInClient: client,
      successCache: _FakeSuccessCache(),
      selectedGymIdProvider: () async => 7,
      memberIdProvider: () => 42,
      requestPermissions: () async => true,
      clock: () => now,
      presenceWindow: const Duration(seconds: 2),
      presenceContinuityTimeout: const Duration(seconds: 10),
      requestDebounce: Duration.zero,
    );

    await controller.startForegroundScan();
    scanner.emit(_detection('SAHABC123DEF4567', now));
    now = now.add(const Duration(minutes: 2));
    scanner.emit(_detection('SAHABC123DEF4567', now));
    await _pumpAsync();
    expect(client.calls, isEmpty);

    now = now.add(const Duration(seconds: 3));
    scanner.emit(_detection('SAHABC123DEF4567', now));
    await _pumpAsync();
    expect(client.calls, hasLength(1));
  });

  test('presence session saves the latest timestamp as out time', () async {
    final scanner = _FakeScanner();
    final client = _SessionCheckInClient();
    final store = _FakeSessionStore();
    var now = DateTime(2026, 9, 26, 10);
    final controller = SmartAttendanceController(
      scanner: scanner,
      checkInClient: client,
      sessionStore: store,
      selectedGymIdProvider: () async => 7,
      memberIdProvider: () => 42,
      requestPermissions: () async => true,
      clock: () => now,
      presenceWindow: Duration.zero,
      requestDebounce: Duration.zero,
      absenceTimeout: const Duration(hours: 2),
    );

    await controller.startForegroundScan();
    scanner.emit(_detection('SAHABC123DEF4567', now));
    await _pumpAsync();
    expect(controller.activeSession?.checkedInAt, now);

    now = now.add(const Duration(minutes: 45));
    scanner.emit(_detection('SAHABC123DEF4567', now));
    await _pumpAsync();
    expect(controller.activeSession?.lastPresenceAt, now);
    expect(client.checkInCalls, 1);
    expect(controller.localPresenceUpdateCount, 1);
    expect(controller.logicState, contains('no check-in request sent'));

    final expectedOutTime = now;
    now = now.add(const Duration(hours: 2, minutes: 1));
    await controller.startForegroundScan();

    expect(client.checkOutCalls, [expectedOutTime]);
    expect(controller.activeSession?.checkedOutAt, expectedOutTime);
    expect(store.session?.checkedOutAt, expectedOutTime);

    client.failNextCheckIn = true;
    now = now.add(const Duration(minutes: 30));
    scanner.emit(_detection('SAHABC123DEF4567', now));
    await _pumpAsync();
    expect(client.checkInCalls, 2);
    expect(controller.activeSession?.checkedOutAt, expectedOutTime);

    client.failNextCheckIn = false;
    now = now.add(const Duration(minutes: 1));
    scanner.emit(_detection('SAHABC123DEF4567', now));
    await _pumpAsync();
    expect(client.checkInCalls, 3);
    expect(controller.activeSession?.checkedOutAt, isNull);
    expect(controller.activeSession?.lastPresenceAt, now);
  });

  test('test control closes the visit and arms the next detection', () async {
    final scanner = _FakeScanner();
    final client = _SessionCheckInClient();
    final now = DateTime(2026, 9, 28, 12);
    final previousPresence = now.subtract(const Duration(minutes: 1));
    final store = _FakeSessionStore()
      ..session = SmartAttendanceSession(
        attendanceLogId: 91,
        memberId: 42,
        gymId: 7,
        hubPublicId: 'SAHABC123DEF4567',
        checkedInAt: now.subtract(const Duration(minutes: 20)),
        lastPresenceAt: previousPresence,
        windowEndsAt: now.add(const Duration(hours: 5, minutes: 40)),
      );
    final controller = SmartAttendanceController(
      scanner: scanner,
      checkInClient: client,
      sessionStore: store,
      selectedGymIdProvider: () async => 7,
      memberIdProvider: () => 42,
      requestPermissions: () async => true,
      clock: () => now,
      presenceWindow: Duration.zero,
      requestDebounce: Duration.zero,
    );

    await controller.startForegroundScan();
    await controller.armNextBackgroundDetectionForTesting();

    expect(scanner.started, isFalse);
    expect(client.checkOutCalls, [previousPresence]);
    expect(store.session, isNull);
    expect(controller.activeSession, isNull);
    expect(controller.logicState, contains('next Hub detection'));

    await controller.startBackgroundScan();
    scanner.emit(
      _detection('SAHABC123DEF4567', now, source: 'ios_background_ble'),
    );
    await _pumpAsync();

    expect(client.checkInCalls, 1);
    expect(controller.activeSession?.checkedOutAt, isNull);
  });
}

Future<void> _pumpAsync() async {
  await Future<void>.delayed(Duration.zero);
  await Future<void>.delayed(Duration.zero);
}

SmartAttendanceDetection _detection(
  String publicId,
  DateTime detectedAt, {
  int rssi = -61,
  String source = 'test',
}) {
  return SmartAttendanceDetection(
    publicId: publicId,
    protocolVersion: 1,
    rssi: rssi,
    detectedAt: detectedAt,
    source: source,
  );
}

class _FakeScanner implements SmartAttendanceBleScanner {
  final _detections = StreamController<SmartAttendanceDetection>.broadcast();
  final _diagnostics =
      StreamController<SmartAttendanceScanDiagnostic>.broadcast();
  bool started = false;
  bool backgroundStarted = false;
  int foregroundStartCalls = 0;

  @override
  Stream<SmartAttendanceDetection> get detections => _detections.stream;

  @override
  Stream<SmartAttendanceScanDiagnostic> get diagnostics => _diagnostics.stream;

  void emit(SmartAttendanceDetection detection) => _detections.add(detection);

  @override
  Future<void> configureBackgroundAttendance({
    required String baseUrl,
    required String accessToken,
    required int gymId,
  }) async {}

  @override
  Future<void> startForegroundScan() async {
    foregroundStartCalls++;
    started = true;
    backgroundStarted = false;
  }

  @override
  Future<void> startBackgroundScan() async {
    started = true;
    backgroundStarted = true;
  }

  @override
  Future<void> stopScan() async {
    started = false;
    backgroundStarted = false;
  }

  @override
  Future<int?> androidSdkInt() async => 35;
}

class _FakeCheckInClient implements SmartAttendanceCheckInClient {
  final calls = <SmartAttendanceDetection>[];

  @override
  Future<SmartAttendanceCheckInResponse> recordSmartAttendanceCheckIn(
    SmartAttendanceDetection detection,
  ) async {
    calls.add(detection);
    return const SmartAttendanceCheckInResponse(
      checkedInToday: true,
      checkInMethod: 'smart_attendance',
      gymId: 7,
      attendanceLogId: 91,
    );
  }

  @override
  Future<void> recordSmartAttendanceCheckOut({
    required int attendanceLogId,
    required DateTime lastPresenceAt,
  }) async {}
}

class _SlowCheckInClient implements SmartAttendanceCheckInClient {
  final _completer = Completer<SmartAttendanceCheckInResponse>();
  var calls = 0;

  @override
  Future<SmartAttendanceCheckInResponse> recordSmartAttendanceCheckIn(
    SmartAttendanceDetection detection,
  ) {
    calls++;
    return _completer.future;
  }

  void complete() {
    _completer.complete(
      const SmartAttendanceCheckInResponse(
        checkedInToday: true,
        checkInMethod: 'smart_attendance',
        gymId: 7,
        attendanceLogId: 91,
      ),
    );
  }

  @override
  Future<void> recordSmartAttendanceCheckOut({
    required int attendanceLogId,
    required DateTime lastPresenceAt,
  }) async {}
}

class _FakeSuccessCache implements SmartAttendanceCheckInCache {
  final successfulKeys = <String>{};

  @override
  Future<void> markSuccessful({
    required int memberId,
    required int gymId,
    required String hubPublicId,
    required DateTime localDate,
    String? attendanceDate,
    DateTime? validUntil,
  }) async {
    successfulKeys.add(_key(memberId, gymId, hubPublicId, localDate));
  }

  @override
  Future<bool> wasSuccessful({
    required int memberId,
    required int gymId,
    required String hubPublicId,
    required DateTime localDate,
  }) async {
    return successfulKeys.contains(
      _key(memberId, gymId, hubPublicId, localDate),
    );
  }

  String _key(int memberId, int gymId, String hubPublicId, DateTime date) {
    final local = date.toLocal();
    final month = local.month.toString().padLeft(2, '0');
    final day = local.day.toString().padLeft(2, '0');
    return '$memberId|$gymId|${hubPublicId.toUpperCase()}|${local.year}-$month-$day';
  }
}

class _SessionCheckInClient implements SmartAttendanceCheckInClient {
  final checkOutCalls = <DateTime>[];
  DateTime? firstPresence;
  int checkInCalls = 0;
  bool failNextCheckIn = false;

  @override
  Future<SmartAttendanceCheckInResponse> recordSmartAttendanceCheckIn(
    SmartAttendanceDetection detection,
  ) async {
    checkInCalls++;
    if (failNextCheckIn) {
      throw Exception('temporary reopen failure');
    }
    firstPresence ??= detection.detectedAt;
    return SmartAttendanceCheckInResponse(
      checkedInToday: true,
      checkInMethod: 'smart_attendance',
      gymId: 7,
      attendanceLogId: 91,
      checkedInAt: firstPresence,
      lastPresenceAt: detection.detectedAt,
      attendanceWindowEndsAt: firstPresence!.add(const Duration(hours: 6)),
    );
  }

  @override
  Future<void> recordSmartAttendanceCheckOut({
    required int attendanceLogId,
    required DateTime lastPresenceAt,
  }) async {
    expect(attendanceLogId, 91);
    checkOutCalls.add(lastPresenceAt);
  }
}

class _FakeSessionStore implements SmartAttendanceSessionStore {
  SmartAttendanceSession? session;

  @override
  Future<void> clear() async => session = null;

  @override
  Future<SmartAttendanceSession?> read() async => session;

  @override
  Future<void> write(SmartAttendanceSession session) async {
    this.session = session;
  }
}
