import '../../core/api_client.dart';
import '../../core/models.dart';
import 'auth_model.dart';

class AuthService {
  const AuthService(this._client);

  final MemberApiClient _client;

  Future<AuthSessionModel> signInWithFirebase({
    required String idToken,
    required String appType,
  }) async {
    final response = await _client.post(
      '/public/auth/firebase/login',
      data: <String, dynamic>{
        'id_token': idToken,
        'device_name': 'flutter_member_app',
        'app_type': appType,
      },
    );

    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );

    return AuthSessionModel.fromJson(data);
  }

  Future<MemberUser> fetchMe() async {
    final response = await _client.get('/public/me');
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    return MemberUser.fromJson(data);
  }

  Future<MemberUser> switchToMemberRole() async {
    final response = await _client.post(
      '/public/auth/active-role',
      data: const <String, dynamic>{'active_role': 'member'},
    );
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    return MemberUser.fromJson(data);
  }

  Future<void> logout() async {
    await _client.post('/public/auth/logout');
  }
}
