import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SmartAttendanceSession {
  const SmartAttendanceSession({
    required this.attendanceLogId,
    required this.memberId,
    required this.gymId,
    required this.hubPublicId,
    required this.checkedInAt,
    required this.lastPresenceAt,
    required this.windowEndsAt,
    this.checkedOutAt,
  });

  final int attendanceLogId;
  final int memberId;
  final int gymId;
  final String hubPublicId;
  final DateTime checkedInAt;
  final DateTime lastPresenceAt;
  final DateTime windowEndsAt;
  final DateTime? checkedOutAt;

  SmartAttendanceSession copyWith({
    String? hubPublicId,
    DateTime? lastPresenceAt,
    DateTime? checkedOutAt,
    bool clearCheckedOutAt = false,
  }) => SmartAttendanceSession(
    attendanceLogId: attendanceLogId,
    memberId: memberId,
    gymId: gymId,
    hubPublicId: hubPublicId ?? this.hubPublicId,
    checkedInAt: checkedInAt,
    lastPresenceAt: lastPresenceAt ?? this.lastPresenceAt,
    windowEndsAt: windowEndsAt,
    checkedOutAt: clearCheckedOutAt
        ? null
        : (checkedOutAt ?? this.checkedOutAt),
  );

  Map<String, Object?> toJson() => {
    'attendance_log_id': attendanceLogId,
    'member_id': memberId,
    'gym_id': gymId,
    'hub_public_id': hubPublicId,
    'checked_in_at': checkedInAt.toUtc().toIso8601String(),
    'last_presence_at': lastPresenceAt.toUtc().toIso8601String(),
    'window_ends_at': windowEndsAt.toUtc().toIso8601String(),
    'checked_out_at': checkedOutAt?.toUtc().toIso8601String(),
  };

  factory SmartAttendanceSession.fromJson(Map<String, dynamic> json) {
    return SmartAttendanceSession(
      attendanceLogId: (json['attendance_log_id'] as num).toInt(),
      memberId: (json['member_id'] as num).toInt(),
      gymId: (json['gym_id'] as num).toInt(),
      hubPublicId: json['hub_public_id'].toString(),
      checkedInAt: DateTime.parse(json['checked_in_at'].toString()),
      lastPresenceAt: DateTime.parse(json['last_presence_at'].toString()),
      windowEndsAt: DateTime.parse(json['window_ends_at'].toString()),
      checkedOutAt: json['checked_out_at'] == null
          ? null
          : DateTime.parse(json['checked_out_at'].toString()),
    );
  }
}

abstract class SmartAttendanceSessionStore {
  Future<SmartAttendanceSession?> read();
  Future<void> write(SmartAttendanceSession session);
  Future<void> clear();
}

class SecureSmartAttendanceSessionStore implements SmartAttendanceSessionStore {
  const SecureSmartAttendanceSessionStore({
    FlutterSecureStorage storage = const FlutterSecureStorage(),
  }) : _storage = storage;

  static const storageKey = 'member_smart_attendance_active_session';
  final FlutterSecureStorage _storage;

  @override
  Future<SmartAttendanceSession?> read() async {
    final raw = await _storage.read(key: storageKey);
    if (raw == null || raw.isEmpty) return null;
    try {
      return SmartAttendanceSession.fromJson(
        Map<String, dynamic>.from(jsonDecode(raw) as Map),
      );
    } catch (_) {
      await clear();
      return null;
    }
  }

  @override
  Future<void> write(SmartAttendanceSession session) =>
      _storage.write(key: storageKey, value: jsonEncode(session.toJson()));

  @override
  Future<void> clear() => _storage.delete(key: storageKey);
}
