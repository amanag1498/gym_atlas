import '../../core/api_client.dart';
import '../../core/models.dart';

class TrainerAuthService {
  const TrainerAuthService(this._client);

  final TrainerApiClient _client;

  Future<TrainerSession> signInWithFirebase({
    required String idToken,
    required String appType,
  }) async {
    final response = await _client.post(
      '/public/auth/firebase/login',
      data: <String, dynamic>{
        'id_token': idToken,
        'device_name': 'flutter_trainer_app',
        'app_type': appType,
      },
    );

    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );

    return TrainerSession(
      token: data['token']?.toString() ?? '',
      user: TrainerUser.fromJson(
        Map<String, dynamic>.from(data['user'] as Map? ?? const {}),
      ),
    );
  }

  Future<bool> fetchDemoLoginEnabled() async {
    final response = await _client.get('/public/app-config');
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    return data['demo_login_enabled'] == true;
  }

  Future<TrainerSession> signInWithDemoEmail({
    required String email,
    required String appType,
  }) async {
    final response = await _client.post(
      '/public/auth/demo/login',
      data: <String, dynamic>{
        'email': email.trim(),
        'device_name': 'flutter_trainer_app_demo',
        'app_type': appType,
      },
    );

    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );

    return TrainerSession(
      token: data['token']?.toString() ?? '',
      user: TrainerUser.fromJson(
        Map<String, dynamic>.from(data['user'] as Map? ?? const {}),
      ),
    );
  }

  Future<TrainerUser> fetchMe() async {
    final response = await _client.get('/public/me');
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    return TrainerUser.fromJson(data);
  }

  Future<Map<String, dynamic>> fetchConsentState() async {
    final response = await _client.get('/public/privacy/consents');
    return Map<String, dynamic>.from(
      response['data'] as Map? ?? const <String, dynamic>{},
    );
  }

  Future<Map<String, dynamic>> grantConsent(String purpose) async {
    final response = await _client.post(
      '/public/privacy/consents',
      data: <String, dynamic>{'purpose': purpose, 'source': 'trainer_app'},
    );
    return Map<String, dynamic>.from(
      response['data'] as Map? ?? const <String, dynamic>{},
    );
  }

  Future<Map<String, dynamic>> withdrawConsent(String purpose) async {
    final response = await _client.delete('/public/privacy/consents/$purpose');
    return Map<String, dynamic>.from(
      response['data'] as Map? ?? const <String, dynamic>{},
    );
  }

  Future<TrainerUser> switchToTrainerRole() async {
    final response = await _client.post(
      '/public/auth/active-role',
      data: const <String, dynamic>{'active_role': 'trainer'},
    );
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    return TrainerUser.fromJson(data);
  }

  Future<void> logout() async {
    await _client.post('/public/auth/logout');
  }
}
