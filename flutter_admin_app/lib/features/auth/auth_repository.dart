import '../../core/models/session_models.dart';
import '../../core/network/api_client.dart';

class AuthRepository {
  AuthRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<AuthSession> signInWithFirebase({
    required String idToken,
    required String appType,
  }) async {
    final response = await _apiClient.post(
      '/public/auth/firebase/login',
      data: {
        'id_token': idToken,
        'device_name': 'flutter_admin_app',
        'app_type': appType,
      },
    );

    return AuthSession.fromJson(
      Map<String, dynamic>.from(response['data'] as Map? ?? const {}),
    );
  }

  Future<AppUser> fetchMe() async {
    final response = await _apiClient.get('/public/me');
    return AppUser.fromJson(
      Map<String, dynamic>.from(response['data'] as Map? ?? const {}),
    );
  }

  Future<AppUser> switchRole(String activeRole) async {
    final response = await _apiClient.post(
      '/public/auth/active-role',
      data: {'active_role': activeRole},
    );

    return AppUser.fromJson(
      Map<String, dynamic>.from(response['data'] as Map? ?? const {}),
    );
  }

  Future<void> logout() => _apiClient.post('/public/auth/logout');
}
