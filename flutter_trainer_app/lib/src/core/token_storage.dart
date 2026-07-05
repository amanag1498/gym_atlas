import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'models.dart';

class TrainerTokenStorage {
  const TrainerTokenStorage() : _storage = const FlutterSecureStorage();

  static const _tokenKey = 'trainer_access_token';
  static const _userKey = 'trainer_user';
  final FlutterSecureStorage _storage;

  Future<void> write(String token) =>
      _storage.write(key: _tokenKey, value: token);
  Future<String?> read() => _storage.read(key: _tokenKey);
  Future<void> saveSession({
    required String token,
    required TrainerUser user,
  }) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _userKey, value: jsonEncode(user.toJson()));
  }

  Future<TrainerUser?> readUser() async {
    final raw = await _storage.read(key: _userKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }

    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return TrainerUser.fromJson(decoded);
      }
      if (decoded is Map) {
        return TrainerUser.fromJson(Map<String, dynamic>.from(decoded));
      }
    } catch (_) {
      return null;
    }

    return null;
  }

  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
  }
}
