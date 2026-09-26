import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract class SmartAttendanceCheckInCache {
  Future<bool> wasSuccessful({
    required int memberId,
    required int gymId,
    required String hubPublicId,
    required DateTime localDate,
  });

  Future<void> markSuccessful({
    required int memberId,
    required int gymId,
    required String hubPublicId,
    required DateTime localDate,
    String? attendanceDate,
    DateTime? validUntil,
  });
}

class SecureSmartAttendanceCheckInCache implements SmartAttendanceCheckInCache {
  const SecureSmartAttendanceCheckInCache({
    FlutterSecureStorage storage = const FlutterSecureStorage(),
  }) : _storage = storage;

  static const storageKey = 'member_smart_attendance_success_cache';
  final FlutterSecureStorage _storage;

  @override
  Future<bool> wasSuccessful({
    required int memberId,
    required int gymId,
    required String hubPublicId,
    required DateTime localDate,
  }) async {
    final cache = await _readCache();
    final exactValue = cache[_key(memberId, gymId, hubPublicId, localDate)];
    if (exactValue != null) {
      final validUntil = DateTime.tryParse(exactValue);
      return validUntil == null || localDate.isBefore(validUntil);
    }

    final identitySuffix = '|$memberId|$gymId|${hubPublicId.toUpperCase()}';
    return cache.entries.any((entry) {
      if (!entry.key.endsWith(identitySuffix)) {
        return false;
      }
      final validUntil = DateTime.tryParse(entry.value);
      return validUntil != null && localDate.isBefore(validUntil);
    });
  }

  @override
  Future<void> markSuccessful({
    required int memberId,
    required int gymId,
    required String hubPublicId,
    required DateTime localDate,
    String? attendanceDate,
    DateTime? validUntil,
  }) async {
    final cache = await _readCache();
    final todayPrefix = '${_dateKey(localDate)}|';
    cache.removeWhere((_, value) {
      final expiry = DateTime.tryParse(value);
      if (expiry != null) {
        return !localDate.isBefore(expiry);
      }
      return !value.startsWith(todayPrefix);
    });
    final keyDate = attendanceDate ?? _dateKey(localDate);
    cache['$keyDate|$memberId|$gymId|${hubPublicId.toUpperCase()}'] =
        validUntil?.toUtc().toIso8601String() ??
        '$todayPrefix${DateTime.now().toIso8601String()}';
    await _storage.write(key: storageKey, value: jsonEncode(cache));
  }

  Future<Map<String, String>> _readCache() async {
    final raw = await _storage.read(key: storageKey);
    if (raw == null || raw.isEmpty) {
      return <String, String>{};
    }
    try {
      final decoded = Map<String, dynamic>.from(jsonDecode(raw) as Map);
      return decoded.map((key, value) => MapEntry(key, value.toString()));
    } catch (_) {
      return <String, String>{};
    }
  }

  String _key(
    int memberId,
    int gymId,
    String hubPublicId,
    DateTime localDate,
  ) => '${_dateKey(localDate)}|$memberId|$gymId|${hubPublicId.toUpperCase()}';

  String _dateKey(DateTime date) {
    final local = date.toLocal();
    final month = local.month.toString().padLeft(2, '0');
    final day = local.day.toString().padLeft(2, '0');
    return '${local.year}-$month-$day';
  }
}
