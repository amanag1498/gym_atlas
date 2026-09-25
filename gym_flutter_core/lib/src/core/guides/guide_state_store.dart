import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'guide_definitions.dart';

/// Separate keys avoid lost updates between guides and isolate accounts/roles.
class GuideStateStore {
  GuideStateStore({FlutterSecureStorage? storage})
    : storage = storage ?? const FlutterSecureStorage();
  final FlutterSecureStorage storage;
  String key(String account, String id) =>
      'atlas_guides.${Uri.encodeComponent(account)}.$id';
  Future<bool> disabled(String account) async =>
      await storage.read(key: key(account, 'disabled')) == 'true';
  Future<void> setDisabled(String account, bool value) =>
      storage.write(key: key(account, 'disabled'), value: '$value');
  Future<bool> seen(String account, GuideDefinition guide) async =>
      await storage.read(key: key(account, guide.id)) != null;
  Future<void> mark(String account, GuideDefinition guide, String status) =>
      storage.write(
        key: key(account, guide.id),
        value: jsonEncode({
          'guide_id': guide.id,
          'version': guide.version,
          'status': status,
          'last_shown_at': DateTime.now().toUtc().toIso8601String(),
        }),
      );
  Future<void> reset(String account, GuideDefinition guide) =>
      storage.delete(key: key(account, guide.id));
}
