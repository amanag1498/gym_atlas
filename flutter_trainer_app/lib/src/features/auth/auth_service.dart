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

  Future<TrainerUser> fetchMe() async {
    final response = await _client.get('/public/me');
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    return TrainerUser.fromJson(data);
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
