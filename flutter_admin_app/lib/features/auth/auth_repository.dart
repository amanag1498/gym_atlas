import '../../core/models/session_models.dart';
import '../../core/network/api_client.dart';

class AuthRepository {
  AuthRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<AuthSession> signInWithFirebase({
    required String idToken,
  }) async {
    final response = await _apiClient.post(
      '/public/auth/firebase/login',
      data: {
        'id_token': idToken,
        'device_name': 'flutter_admin_app',
      },
    );

    return AuthSession.fromJson(
      Map<String, dynamic>.from(response['data'] as Map? ?? const {}),
    );
  }

  Future<AuthSession> signInWithDemo({
    required String email,
    required String accessCode,
  }) async {
    final response = await _apiClient.post(
      '/public/auth/demo/login',
      data: {
        'email': email.trim(),
        'access_code': accessCode,
        'device_name': 'flutter_admin_app_demo',
        'app_type': 'admin',
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
