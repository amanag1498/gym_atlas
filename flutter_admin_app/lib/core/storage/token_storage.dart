import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/session_models.dart';

class TokenStorage {
  const TokenStorage() : _storage = const FlutterSecureStorage();

  static const _tokenKey = 'admin_access_token';
  static const _userKey = 'admin_user';
  static const _activeRoleKey = 'admin_active_role';
  static const _permissionsKey = 'admin_permissions';
  final FlutterSecureStorage _storage;

  Future<void> writeToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> writeSession({
    required String token,
    required AppUser user,
  }) async {
    await Future.wait([
      _storage.write(key: _tokenKey, value: token),
      _storage.write(key: _userKey, value: jsonEncode(user.toJson())),
      _storage.write(key: _activeRoleKey, value: user.activeRole),
      _storage.write(key: _permissionsKey, value: jsonEncode(user.permissions)),
    ]);
  }

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<AppUser?> readUser() async {
    final raw = await _storage.read(key: _userKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }
    final decoded = jsonDecode(raw);
    if (decoded is Map<String, dynamic>) {
      return AppUser.fromJson(decoded);
    }
    if (decoded is Map) {
      return AppUser.fromJson(
        decoded.map((key, value) => MapEntry(key.toString(), value)),
      );
    }
    return null;
  }

  Future<void> clear() async {
    await Future.wait([
      _storage.delete(key: _tokenKey),
      _storage.delete(key: _userKey),
      _storage.delete(key: _activeRoleKey),
      _storage.delete(key: _permissionsKey),
    ]);
  }
}
