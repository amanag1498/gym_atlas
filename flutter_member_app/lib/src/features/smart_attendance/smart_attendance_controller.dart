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
    String? Function()? accessTokenProvider,
    String? backgroundApiBaseUrl,
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
       _accessTokenProvider = accessTokenProvider,
       _backgroundApiBaseUrl = backgroundApiBaseUrl,
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
  final String? Function()? _accessTokenProvider;
  final String? _backgroundApiBaseUrl;
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
  String _bluetoothPermissionStatus = 'unknown';
  String _backendSyncStatus = 'not_sent';
  DateTime? _lastBackendSyncAt;
  String? _backendSyncError;
  String? _lastError;
  String? _lastCheckInMessage;
  String _logicState = 'Waiting for a nearby Atlas hub.';
  int _localPresenceUpdateCount = 0;
  DateTime? _lastLocalPresenceAt;
  DateTime? _lastAttendanceRequestAt;
  String _lastAttendanceRequestResult = 'Not sent';
  DateTime? _lastCheckoutRequestAt;
  String _lastCheckoutRequestResult = 'Not sent';

  bool get scanning => _scanning;
  bool get permissionDenied => _permissionDenied;
  bool get checkInInFlight => _checkInInFlight;
  bool get backgroundScanning => _backgroundScanning;
  String get bluetoothPermissionStatus => _bluetoothPermissionStatus;
  String get backendSyncStatus => _backendSyncStatus;
  DateTime? get lastBackendSyncAt => _lastBackendSyncAt;
  String? get backendSyncError => _backendSyncError;
  String? get lastError => _lastError;
  String? get lastCheckInMessage => _lastCheckInMessage;
  String get logicState => _logicState;
  int get localPresenceUpdateCount => _localPresenceUpdateCount;
  DateTime? get lastLocalPresenceAt => _lastLocalPresenceAt;
  DateTime? get lastAttendanceRequestAt => _lastAttendanceRequestAt;
  String get lastAttendanceRequestResult => _lastAttendanceRequestResult;
  DateTime? get lastCheckoutRequestAt => _lastCheckoutRequestAt;
  String get lastCheckoutRequestResult => _lastCheckoutRequestResult;
  SmartAttendanceSession? get activeSession => _session;
  SmartAttendanceDetection? get latestDetection =>
      _detections.isEmpty ? null : _detections.last;
  List<SmartAttendanceDetection> get detections =>
      List.unmodifiable(_detections);
  List<SmartAttendanceScanDiagnostic> get diagnostics =>
      List.unmodifiable(_diagnostics);

  void markBackendSyncStarted() {
    _backendSyncStatus = 'sending';
    _backendSyncError = null;
    notifyListeners();
  }

  void markBackendSyncFinished({required bool success, String? error}) {
    _backendSyncStatus = success ? 'synced' : 'failed';
    _lastBackendSyncAt = _clock();
    _backendSyncError = success ? null : error;
    notifyListeners();
  }

  Future<void> startForegroundScan() async {
    _lastError = null;
    _permissionDenied = false;
    final allowed = await _requestBluetoothPermission();
    if (!allowed) {
      _permissionDenied = true;
      _scanning = false;
      notifyListeners();
      return;
    }
    await _restoreAndReconcileSession();
    final accessToken = _accessTokenProvider?.call();
    final selectedGymId = await _selectedGymIdProvider?.call();
    if (defaultTargetPlatform == TargetPlatform.android &&
        accessToken != null &&
        accessToken.isNotEmpty &&
        selectedGymId != null &&
        _backgroundApiBaseUrl != null) {
      await _scanner.configureBackgroundAttendance(
        baseUrl: _backgroundApiBaseUrl,
        accessToken: accessToken,
        gymId: selectedGymId,
      );
    }
    if (_scanning && !_backgroundScanning) {
      return;
    }

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
    final allowed = await _requestBluetoothPermission();
    if (!allowed) {
      _permissionDenied = true;
      _scanning = false;
      _backgroundScanning = false;
      notifyListeners();
      return;
    }
    await _restoreAndReconcileSession();
    final accessToken = _accessTokenProvider?.call();
    final selectedGymId = await _selectedGymIdProvider?.call();
    if (defaultTargetPlatform == TargetPlatform.android &&
        accessToken != null &&
        accessToken.isNotEmpty &&
        selectedGymId != null &&
        _backgroundApiBaseUrl != null) {
      await _scanner.configureBackgroundAttendance(
        baseUrl: _backgroundApiBaseUrl,
        accessToken: accessToken,
        gymId: selectedGymId,
      );
    }
    if (_scanning && _backgroundScanning) {
      return;
    }

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

  /// Used only by builds that explicitly enable Smart Attendance test tools.
  /// It closes the current presence, clears the phone's active six-hour
  /// session, and stops scanning so the next background transition can prove
  /// that a fresh Hub detection reaches the server.
  Future<void> armNextBackgroundDetectionForTesting() async {
    await stopScan();
    final session = _session;
    if (session != null && session.checkedOutAt == null) {
      final client = _checkInClient;
      if (client == null) {
        throw StateError('Smart Attendance checkout is unavailable.');
      }
      await client.recordSmartAttendanceCheckOut(
        attendanceLogId: session.attendanceLogId,
        lastPresenceAt: session.lastPresenceAt,
      );
    }
    _session = null;
    await _sessionStore?.clear();
    _firstQualifiedDetectionByHub.clear();
    _lastQualifiedDetectionByHub.clear();
    _lastDetectionByHub.clear();
    _lastRequestAttemptByHub.clear();
    _lastCheckoutRequestAt = _clock();
    _lastCheckoutRequestResult = session == null
        ? 'No active window; next detection is armed'
        : 'Test window cleared; next detection is armed';
    _logicState =
        'Test armed. Press Home or lock the phone; the next Hub detection should reach the server.';
    notifyListeners();
  }

  void _handleDetection(SmartAttendanceDetection detection) {
    if (detection.isExit) {
      _detections.add(detection);
      unawaited(_handleBeaconExit(detection));
      notifyListeners();
      return;
    }
    if (detection.source == 'android_background_native') {
      final previous = _lastDetectionByHub[detection.publicId];
      final now = _clock();
      if (previous == null || now.difference(previous) >= _duplicateWindow) {
        _lastDetectionByHub[detection.publicId] = now;
        _detections.add(detection);
      }
      _logicState =
          'Android background service detected the Hub and owns attendance sync.';
      notifyListeners();
      return;
    }
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

  Future<void> _handleBeaconExit(SmartAttendanceDetection detection) async {
    final session = _session;
    if (session == null || session.checkedOutAt != null) {
      _logicState = 'Gym exit detected with no open Smart Attendance visit.';
      notifyListeners();
      return;
    }
    final exitAt = detection.detectedAt.isAfter(_clock())
        ? _clock()
        : detection.detectedAt;
    if (exitAt.isAfter(session.lastPresenceAt)) {
      _session = session.copyWith(lastPresenceAt: exitAt);
      _lastLocalPresenceAt = exitAt;
      _localPresenceUpdateCount++;
      await _persistSession();
    }
    _logicState = 'Gym exit detected. Saving out time.';
    await _finalizeSession(clearAfterSuccess: false);
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
      _logicState =
          'Signal is too weak ($rssi dBm; minimum $_minimumRssi dBm).';
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
    // iOS coalesces duplicate BLE discoveries while an app is in the
    // background, even when AllowDuplicates is requested. A service-filtered,
    // strong background discovery must therefore be handled as the confirmed
    // presence event; waiting for a second packet can prevent the visit from
    // ever being recorded.
    final isCoalescedIosBackgroundDetection =
        detection.source.startsWith('ios_background_ble') ||
        detection.source.startsWith('ios_background_beacon');
    if (!isCoalescedIosBackgroundDetection &&
        now.difference(firstSeen) < _presenceWindow) {
      _logicState = 'Confirming continuous hub presence for 2.4 seconds.';
      return;
    }

    final currentSession = _session;
    final startsNewVisit =
        currentSession == null || !now.isBefore(currentSession.windowEndsAt);
    final reopensCheckedOutVisit =
        !startsNewVisit && currentSession.checkedOutAt != null;
    final requestDetection = startsNewVisit
        ? SmartAttendanceDetection(
            publicId: detection.publicId,
            protocolVersion: detection.protocolVersion,
            rssi: detection.rssi,
            detectedAt: firstSeen,
            source: detection.source,
            hubId: detection.hubId,
            rawServiceData: detection.rawServiceData,
          )
        : detection;

    if (!reopensCheckedOutVisit) {
      _recordLocalPresence(detection, now);
    }

    // Once the first check-in has been confirmed, detections only advance the
    // locally persisted last-presence timestamp. The backend is contacted
    // again only when reopening a checked-out visit or starting a new window.
    if (!startsNewVisit && !reopensCheckedOutVisit) {
      _logicState =
          'Visit confirmed. Presence is updating on this phone; no check-in request sent.';
      notifyListeners();
      return;
    }

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
    _lastAttendanceRequestAt = now;
    _lastAttendanceRequestResult = reopensCheckedOutVisit
        ? 'Sending visit reopen'
        : 'Sending first check-in';
    _logicState = _lastAttendanceRequestResult;
    _lastError = null;
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
            usesExitEvents: hasNewerLocalPresence
                ? current.usesExitEvents
                : detection.source.contains('beacon'),
            checkedOutAt: hasNewerLocalPresence ? null : response.checkedOutAt,
          );
          await _persistSession();
          _scheduleSessionTimer();
        }
        _lastCheckInMessage = 'Smart Attendance check-in recorded.';
        _lastAttendanceRequestResult = reopensCheckedOutVisit
            ? 'Visit reopened successfully'
            : 'First check-in confirmed';
        _logicState = reopensCheckedOutVisit
            ? 'Visit reopened. Local presence tracking resumed.'
            : 'Visit confirmed. Local presence tracking is active.';
      }
    } catch (error) {
      _lastError = _friendlyError(error);
      _lastAttendanceRequestResult = 'Failed: $_lastError';
      _logicState = 'Check-in request failed. Waiting to retry.';
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
    _localPresenceUpdateCount++;
    _lastLocalPresenceAt = detectedAt;
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
    if (!session.usesExitEvents &&
        session.checkedOutAt == null &&
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
    final deadline = session.checkedOutAt == null && !session.usesExitEvents
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
    _lastCheckoutRequestAt = _clock();
    _lastCheckoutRequestResult = 'Sending last presence as out time';
    _logicState = session.usesExitEvents
        ? 'Saving beacon exit as out time.'
        : 'Two-hour absence reached. Saving out time.';
    _lastError = null;
    notifyListeners();
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
      _lastCheckoutRequestResult = 'Out time saved successfully';
      _logicState = clearAfterSuccess
          ? 'Six-hour window closed.'
          : 'Out time saved. Waiting for a return before the window ends.';
    } catch (error) {
      _lastError = _friendlyError(error);
      _lastCheckoutRequestResult = 'Failed: $_lastError';
      _logicState = 'Out-time save failed. Retrying in five minutes.';
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

  Future<bool> _requestBluetoothPermission() async {
    final request = _requestPermissions;
    if (request != null) {
      final allowed = await request();
      _bluetoothPermissionStatus = allowed ? 'granted' : 'denied';
      return allowed;
    }

    return _defaultPermissionRequest();
  }

  Future<bool> _defaultPermissionRequest() async {
    if (kIsWeb) {
      _bluetoothPermissionStatus = 'unavailable';
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
      _bluetoothPermissionStatus = 'unavailable';
      return false;
    }
    final statuses = await permissions.request();
    if (defaultTargetPlatform == TargetPlatform.iOS) {
      final bluetoothStatus = statuses[Permission.bluetooth];
      final bluetoothAllowed = bluetoothStatus?.isGranted == true;
      if (!bluetoothAllowed) {
        _bluetoothPermissionStatus = _permissionStatusName(bluetoothStatus);
        return false;
      }
      var whenInUse = await Permission.locationWhenInUse.status;
      if (!whenInUse.isGranted) {
        whenInUse = await Permission.locationWhenInUse.request();
      }
      var always = await Permission.locationAlways.status;
      if (whenInUse.isGranted && !always.isGranted) {
        await Permission.locationAlways.request();
      }
      // iOS can defer its second-stage "Always" prompt. Start the native
      // scanner once Bluetooth is granted so CLLocationManager remains armed
      // and begins beacon monitoring as soon as that authorization changes.
      _bluetoothPermissionStatus = 'granted';
      return true;
    }
    final allowed = statuses.values.every(
      (status) => status.isGranted || status.isLimited,
    );
    _bluetoothPermissionStatus = switch (statuses.values) {
      final values when values.any((status) => status.isPermanentlyDenied) =>
        'permanently_denied',
      final values when values.any((status) => status.isRestricted) =>
        'restricted',
      final values when values.any((status) => status.isDenied) => 'denied',
      final values when values.any((status) => status.isLimited) => 'limited',
      _ when allowed => 'granted',
      _ => 'unknown',
    };
    return allowed;
  }

  String _permissionStatusName(PermissionStatus? status) => switch (status) {
    PermissionStatus.permanentlyDenied => 'permanently_denied',
    PermissionStatus.restricted => 'restricted',
    PermissionStatus.denied => 'denied',
    PermissionStatus.limited => 'limited',
    PermissionStatus.granted || PermissionStatus.provisional => 'granted',
    _ => 'unknown',
  };

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
