import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'models.dart';

class SecureStorageService {
  const SecureStorageService({
    FlutterSecureStorage storage = const FlutterSecureStorage(),
  }) : _storage = storage;

  static const _tokenKey = 'member_access_token';
  static const _userKey = 'member_user';
  static const _roleKey = 'member_active_role';
  static const _trialRequestsKey = 'member_trial_requests';

  final FlutterSecureStorage _storage;

  Future<void> saveSession({
    required String token,
    required MemberUser user,
  }) async {
    await Future.wait<void>([
      _storage.write(key: _tokenKey, value: token),
      _storage.write(key: _userKey, value: jsonEncode(user.toJson())),
      _storage.write(key: _roleKey, value: user.activeRole),
    ]);
  }

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<MemberUser?> readUser() async {
    final raw = await _storage.read(key: _userKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }

    try {
      return MemberUser.fromJson(
        Map<String, dynamic>.from(jsonDecode(raw) as Map),
      );
    } catch (_) {
      return null;
    }
  }

  Future<String?> readActiveRole() => _storage.read(key: _roleKey);

  Future<List<Map<String, dynamic>>> readTrialRequests(int userId) async {
    final raw = await _storage.read(key: _trialRequestsKey);
    if (raw == null || raw.isEmpty) {
      return const [];
    }

    try {
      final decoded = Map<String, dynamic>.from(jsonDecode(raw) as Map);
      final records = decoded['$userId'] as List<dynamic>? ?? const [];

      return records
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (_) {
      return const [];
    }
  }

  Future<void> saveTrialRequests(
    int userId,
    List<Map<String, dynamic>> requests,
  ) async {
    Map<String, dynamic> decoded = <String, dynamic>{};
    final raw = await _storage.read(key: _trialRequestsKey);
    if (raw != null && raw.isNotEmpty) {
      try {
        decoded = Map<String, dynamic>.from(jsonDecode(raw) as Map);
      } catch (_) {
        decoded = <String, dynamic>{};
      }
    }

    decoded['$userId'] = requests;
    await _storage.write(key: _trialRequestsKey, value: jsonEncode(decoded));
  }

  Future<void> clear() async {
    await Future.wait<void>([
      _storage.delete(key: _tokenKey),
      _storage.delete(key: _userKey),
      _storage.delete(key: _roleKey),
      _storage.delete(key: _trialRequestsKey),
    ]);
  }
}
