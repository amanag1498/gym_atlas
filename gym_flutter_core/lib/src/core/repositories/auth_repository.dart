import '../models/app_models.dart';
import '../network/api_client.dart';
import '../storage/secure_token_storage.dart';

class AuthRepository {
  AuthRepository({
    required ApiClient apiClient,
    required SecureTokenStorage storage,
  })  : _apiClient = apiClient,
        _storage = storage;

  final ApiClient _apiClient;
  final SecureTokenStorage _storage;

  Future<AuthSession> loginWithGoogle({
    required String idToken,
    required String deviceName,
  }) async {
    final response = await _apiClient.post<AuthSession>(
      '/public/auth/google/login',
      data: <String, dynamic>{
        'id_token': idToken,
        'device_name': deviceName,
      },
      decoder: (data) => AuthSession.fromJson((data as Map).cast<String, dynamic>()),
    );

    await _storage.saveToken(response.data.token);
    await _storage.saveActiveRole(response.data.user.activeRole);

    return response.data;
  }

  Future<SessionUser> me() async {
    final response = await _apiClient.get<SessionUser>(
      '/public/me',
      decoder: (data) => SessionUser.fromJson((data as Map).cast<String, dynamic>()),
    );
    return response.data;
  }

  Future<SessionUser> switchActiveRole(String role) async {
    final response = await _apiClient.post<SessionUser>(
      '/public/auth/active-role',
      data: <String, dynamic>{'active_role': role},
      decoder: (data) => SessionUser.fromJson((data as Map).cast<String, dynamic>()),
    );
    await _storage.saveActiveRole(response.data.activeRole);
    return response.data;
  }

  Future<void> logout() async {
    try {
      await _apiClient.post<dynamic>('/public/auth/logout');
    } finally {
      await _storage.clear();
    }
  }

  Future<String?> readStoredToken() => _storage.readToken();
}
