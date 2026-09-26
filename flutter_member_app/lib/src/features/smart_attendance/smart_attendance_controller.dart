import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:permission_handler/permission_handler.dart';

import 'smart_attendance_ble_scanner.dart';
import 'smart_attendance_check_in_cache.dart';
import 'smart_attendance_check_in_client.dart';
import 'smart_attendance_detection.dart';
import 'smart_attendance_session_store.dart';

class SmartAttendanceController extends ChangeNotifier {
  SmartAttendanceController({
    required SmartAttendanceBleScanner scanner,
    SmartAttendanceCheckInClient? checkInClient,
    SmartAttendanceCheckInCache? successCache,
    SmartAttendanceSessionStore? sessionStore,
    Future<int?> Function()? selectedGymIdProvider,
    int? Function()? memberIdProvider,
    Future<bool> Function()? requestPermissions,
    DateTime Function()? clock,
    Duration duplicateWindow = const Duration(seconds: 12),
    Duration presenceWindow = const Duration(milliseconds: 2400),
    Duration requestDebounce = const Duration(seconds: 15),
    Duration presenceContinuityTimeout = const Duration(seconds: 30),
    Duration attendanceWindow = const Duration(hours: 6),
    Duration absenceTimeout = const Duration(hours: 2),
    int minimumRssi = -78,
  }) : _scanner = scanner,
       _checkInClient = checkInClient,
       _successCache = successCache,
       _sessionStore = sessionStore,
       _selectedGymIdProvider = selectedGymIdProvider,
       _memberIdProvider = memberIdProvider,
       _requestPermissions = requestPermissions,
       _clock = clock ?? DateTime.now,
       _duplicateWindow = duplicateWindow,
       _presenceWindow = presenceWindow,
       _requestDebounce = requestDebounce,
       _presenceContinuityTimeout = presenceContinuityTimeout,
       _attendanceWindow = attendanceWindow,
       _absenceTimeout = absenceTimeout,
       _minimumRssi = minimumRssi;

  final SmartAttendanceBleScanner _scanner;
  final SmartAttendanceCheckInClient? _checkInClient;
  final SmartAttendanceCheckInCache? _successCache;
  final SmartAttendanceSessionStore? _sessionStore;
  final Future<int?> Function()? _selectedGymIdProvider;
  final int? Function()? _memberIdProvider;
  final Future<bool> Function()? _requestPermissions;
  final DateTime Function() _clock;
  final Duration _duplicateWindow;
  final Duration _presenceWindow;
  final Duration _requestDebounce;
  final Duration _presenceContinuityTimeout;
  final Duration _attendanceWindow;
  final Duration _absenceTimeout;
  final int _minimumRssi;
  final List<SmartAttendanceDetection> _detections = [];
  final List<SmartAttendanceScanDiagnostic> _diagnostics = [];
  final Map<String, DateTime> _lastDetectionByHub = {};
  final Map<String, DateTime> _firstQualifiedDetectionByHub = {};
  final Map<String, DateTime> _lastQualifiedDetectionByHub = {};
  final Map<String, DateTime> _lastRequestAttemptByHub = {};
  final Set<String> _inFlightHubs = <String>{};

  StreamSubscription<SmartAttendanceDetection>? _detectionSub;
  StreamSubscription<SmartAttendanceScanDiagnostic>? _diagnosticSub;
  Timer? _sessionTimer;
  SmartAttendanceSession? _session;
  bool _sessionLoaded = false;
  bool _scanning = false;
  bool _permissionDenied = false;
  bool _checkInInFlight = false;
  bool _backgroundScanning = false;
  String? _lastError;
  String? _lastCheckInMessage;

  bool get scanning => _scanning;
  bool get permissionDenied => _permissionDenied;
  bool get checkInInFlight => _checkInInFlight;
  bool get backgroundScanning => _backgroundScanning;
  String? get lastError => _lastError;
  String? get lastCheckInMessage => _lastCheckInMessage;
  SmartAttendanceSession? get activeSession => _session;
  SmartAttendanceDetection? get latestDetection =>
      _detections.isEmpty ? null : _detections.last;
  List<SmartAttendanceDetection> get detections =>
      List.unmodifiable(_detections);
  List<SmartAttendanceScanDiagnostic> get diagnostics =>
      List.unmodifiable(_diagnostics);

  Future<void> startForegroundScan() async {
    _lastError = null;
    _permissionDenied = false;
    final allowed = await (_requestPermissions ?? _defaultPermissionRequest)
        .call();
    if (!allowed) {
      _permissionDenied = true;
      _scanning = false;
      notifyListeners();
      return;
    }
    await _restoreAndReconcileSession();

    _detectionSub ??= _scanner.detections.listen(
      _handleDetection,
      onError: _handleError,
    );
    _diagnosticSub ??= _scanner.diagnostics.listen(
      _handleDiagnostic,
      onError: _handleError,
    );

    try {
      await _scanner.startForegroundScan();
      _scanning = true;
      _backgroundScanning = false;
    } catch (error) {
      _lastError = _friendlyError(error);
      _scanning = false;
    }
    notifyListeners();
  }

  Future<void> startBackgroundScan() async {
    _lastError = null;
    _permissionDenied = false;
    final allowed = await (_requestPermissions ?? _defaultPermissionRequest)
        .call();
    if (!allowed) {
      _permissionDenied = true;
      _scanning = false;
      _backgroundScanning = false;
      notifyListeners();
      return;
    }
    await _restoreAndReconcileSession();

    _detectionSub ??= _scanner.detections.listen(
      _handleDetection,
      onError: _handleError,
    );
    _diagnosticSub ??= _scanner.diagnostics.listen(
      _handleDiagnostic,
      onError: _handleError,
    );

    try {
      await _scanner.startBackgroundScan();
      _scanning = true;
      _backgroundScanning = true;
    } catch (error) {
      _lastError = _friendlyError(error);
      _scanning = false;
      _backgroundScanning = false;
    }
    notifyListeners();
  }

  Future<void> stopScan() async {
    try {
      await _scanner.stopScan();
    } finally {
      _scanning = false;
      _backgroundScanning = false;
      _firstQualifiedDetectionByHub.clear();
      _lastQualifiedDetectionByHub.clear();
      _sessionTimer?.cancel();
      notifyListeners();
    }
  }

  void _handleDetection(SmartAttendanceDetection detection) {
    unawaited(_maybeSubmitAttendance(detection));

    final now = _clock();
    final previous = _lastDetectionByHub[detection.publicId];
    if (previous != null && now.difference(previous) < _duplicateWindow) {
      return;
    }
    _lastDetectionByHub[detection.publicId] = now;
    _detections.add(detection);
    notifyListeners();
  }

  Future<void> _maybeSubmitAttendance(
    SmartAttendanceDetection detection,
  ) async {
    final client = _checkInClient;
    if (client == null) {
      return;
    }

    final now = _clock();
    final hubKey = detection.publicId.toUpperCase();
    final rssi = detection.rssi;
    if (rssi != null && rssi < _minimumRssi) {
      _firstQualifiedDetectionByHub.remove(hubKey);
      _lastQualifiedDetectionByHub.remove(hubKey);
      return;
    }

    final previousQualified = _lastQualifiedDetectionByHub[hubKey];
    if (previousQualified == null ||
        now.difference(previousQualified) > _presenceContinuityTimeout ||
        now.isBefore(previousQualified)) {
      _firstQualifiedDetectionByHub[hubKey] = now;
    }
    _lastQualifiedDetectionByHub[hubKey] = now;
    final firstSeen = _firstQualifiedDetectionByHub[hubKey] ?? now;
    if (now.difference(firstSeen) < _presenceWindow) {
      return;
    }

    final currentSession = _session;
    final startsNewVisit =
        currentSession == null || !now.isBefore(currentSession.windowEndsAt);
    final requestDetection = startsNewVisit
        ? SmartAttendanceDetection(
            publicId: detection.publicId,
            protocolVersion: detection.protocolVersion,
            rssi: detection.rssi,
            detectedAt: firstSeen,
            source: detection.source,
            rawServiceData: detection.rawServiceData,
          )
        : detection;

    _recordLocalPresence(detection, now);

    final lastAttempt = _lastRequestAttemptByHub[hubKey];
    if (lastAttempt != null && now.difference(lastAttempt) < _requestDebounce) {
      return;
    }
    if (_inFlightHubs.contains(hubKey)) {
      return;
    }

    _inFlightHubs.add(hubKey);
    _lastRequestAttemptByHub[hubKey] = now;
    _checkInInFlight = true;
    notifyListeners();

    final memberId = _memberIdProvider?.call();
    final selectedGymId = await _selectedGymIdProvider?.call();
    if (!_scanning) {
      _inFlightHubs.remove(hubKey);
      _checkInInFlight = _inFlightHubs.isNotEmpty;
      notifyListeners();
      return;
    }
    try {
      final response = await client.recordSmartAttendanceCheckIn(
        requestDetection,
      );
      final resolvedGymId = response.gymId ?? selectedGymId;
      if (response.recordedSmartAttendance &&
          memberId != null &&
          resolvedGymId != null) {
        await _successCache?.markSuccessful(
          memberId: memberId,
          gymId: resolvedGymId,
          hubPublicId: hubKey,
          localDate: now,
          attendanceDate: response.attendanceDate,
          validUntil: response.duplicateSuppressionUntil,
        );
        final attendanceLogId = response.attendanceLogId;
        if (attendanceLogId != null) {
          final checkedInAt =
              response.checkedInAt ?? requestDetection.detectedAt;
          final responseLastPresence =
              response.lastPresenceAt ?? requestDetection.detectedAt;
          final current = _session;
          final hasNewerLocalPresence =
              current != null &&
              current.attendanceLogId == attendanceLogId &&
              current.lastPresenceAt.isAfter(responseLastPresence);
          _session = SmartAttendanceSession(
            attendanceLogId: attendanceLogId,
            memberId: memberId,
            gymId: resolvedGymId,
            hubPublicId: hasNewerLocalPresence ? current.hubPublicId : hubKey,
            checkedInAt: checkedInAt,
            lastPresenceAt: hasNewerLocalPresence
                ? current.lastPresenceAt
                : responseLastPresence,
            windowEndsAt:
                response.attendanceWindowEndsAt ??
                checkedInAt.add(_attendanceWindow),
            checkedOutAt: hasNewerLocalPresence ? null : response.checkedOutAt,
          );
          await _persistSession();
          _scheduleSessionTimer();
        }
        _lastCheckInMessage = 'Smart Attendance check-in recorded.';
      }
    } catch (error) {
      _lastError = _friendlyError(error);
    } finally {
      _inFlightHubs.remove(hubKey);
      _checkInInFlight = _inFlightHubs.isNotEmpty;
      notifyListeners();
    }
  }

  void _recordLocalPresence(SmartAttendanceDetection detection, DateTime now) {
    final session = _session;
    if (session == null || !now.isBefore(session.windowEndsAt)) {
      return;
    }
    final detectedAt = detection.detectedAt.isAfter(now)
        ? now
        : detection.detectedAt;
    if (detectedAt.isBefore(session.lastPresenceAt)) {
      return;
    }
    _session = session.copyWith(
      hubPublicId: detection.publicId.toUpperCase(),
      lastPresenceAt: detectedAt,
      clearCheckedOutAt: true,
    );
    unawaited(_persistSession());
    _scheduleSessionTimer();
  }

  Future<void> _restoreAndReconcileSession() async {
    if (!_sessionLoaded) {
      _sessionLoaded = true;
      _session = await _sessionStore?.read();
    }
    final session = _session;
    if (session == null) return;

    final memberId = _memberIdProvider?.call();
    final gymId = await _selectedGymIdProvider?.call();
    if (memberId == null ||
        gymId == null ||
        session.memberId != memberId ||
        session.gymId != gymId) {
      _session = null;
      await _sessionStore?.clear();
      return;
    }

    final now = _clock();
    if (!now.isBefore(session.windowEndsAt)) {
      await _finalizeSession(clearAfterSuccess: true);
      return;
    }
    if (session.checkedOutAt == null &&
        !now.isBefore(session.lastPresenceAt.add(_absenceTimeout))) {
      await _finalizeSession(clearAfterSuccess: false);
      return;
    }
    _scheduleSessionTimer();
  }

  void _scheduleSessionTimer() {
    _sessionTimer?.cancel();
    final session = _session;
    if (session == null) return;
    final now = _clock();
    final deadline = session.checkedOutAt == null
        ? session.lastPresenceAt.add(_absenceTimeout)
        : session.windowEndsAt;
    final effectiveDeadline = deadline.isBefore(session.windowEndsAt)
        ? deadline
        : session.windowEndsAt;
    final delay = effectiveDeadline.difference(now);
    _sessionTimer = Timer(
      delay.isNegative ? Duration.zero : delay,
      () => unawaited(
        _finalizeSession(
          clearAfterSuccess: !_clock().isBefore(session.windowEndsAt),
        ),
      ),
    );
  }

  Future<void> _finalizeSession({required bool clearAfterSuccess}) async {
    final session = _session;
    final client = _checkInClient;
    if (session == null || client == null) return;
    try {
      await client.recordSmartAttendanceCheckOut(
        attendanceLogId: session.attendanceLogId,
        lastPresenceAt: session.lastPresenceAt,
      );
      if (clearAfterSuccess || !_clock().isBefore(session.windowEndsAt)) {
        final current = _session;
        if (current != null &&
            current.attendanceLogId == session.attendanceLogId &&
            !current.lastPresenceAt.isAfter(session.lastPresenceAt)) {
          _session = null;
          await _sessionStore?.clear();
        } else {
          _scheduleSessionTimer();
        }
      } else {
        final current = _session;
        if (current != null &&
            current.attendanceLogId == session.attendanceLogId &&
            current.lastPresenceAt.isAfter(session.lastPresenceAt)) {
          _scheduleSessionTimer();
        } else {
          _session = session.copyWith(checkedOutAt: session.lastPresenceAt);
          await _persistSession();
          _scheduleSessionTimer();
        }
      }
      _lastCheckInMessage = 'Smart Attendance out time saved.';
    } catch (error) {
      _lastError = _friendlyError(error);
      _sessionTimer?.cancel();
      _sessionTimer = Timer(
        const Duration(minutes: 5),
        () => unawaited(_finalizeSession(clearAfterSuccess: clearAfterSuccess)),
      );
    }
    notifyListeners();
  }

  Future<void> _persistSession() async {
    final session = _session;
    if (session != null) {
      await _sessionStore?.write(session);
    }
  }

  void _handleDiagnostic(SmartAttendanceScanDiagnostic diagnostic) {
    _diagnostics.add(diagnostic);
    notifyListeners();
  }

  void _handleError(Object error) {
    _lastError = _friendlyError(error);
    notifyListeners();
  }

  Future<bool> _defaultPermissionRequest() async {
    if (kIsWeb) {
      return false;
    }

    final permissions = switch (defaultTargetPlatform) {
      TargetPlatform.android =>
        (await _scanner.androidSdkInt() ?? 31) >= 31
            ? <Permission>[Permission.bluetoothScan]
            : <Permission>[Permission.locationWhenInUse],
      TargetPlatform.iOS => <Permission>[Permission.bluetooth],
      _ => <Permission>[],
    };
    if (permissions.isEmpty) {
      return false;
    }
    final statuses = await permissions.request();
    return statuses.values.every(
      (status) => status.isGranted || status.isLimited,
    );
  }

  String _friendlyError(Object error) {
    if (error is PlatformException) {
      return error.message ?? error.code;
    }
    return '$error';
  }

  @override
  void dispose() {
    _sessionTimer?.cancel();
    _detectionSub?.cancel();
    _diagnosticSub?.cancel();
    _scanner.stopScan();
    super.dispose();
  }
}
