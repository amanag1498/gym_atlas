import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

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
    final payload = <String, dynamic>{
      'id_token': idToken,
      'device_name': 'flutter_member_app',
      'app_type': appType,
    };

    debugPrint(
      '[auth][login] POST /public/auth/firebase/login '
      'appType=$appType device=flutter_member_app '
      'token=${_maskToken(idToken)} length=${idToken.length}',
    );

    Map<String, dynamic> response;
    try {
      response = await _client.post(
        '/public/auth/firebase/login',
        data: payload,
      );
    } on DioException catch (exception) {
      debugPrint(
        '[auth][login][error] status=${exception.response?.statusCode} '
        'type=${exception.type} message=${exception.message} '
        'response=${exception.response?.data}',
      );
      rethrow;
    }

    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );

    final token = data['token']?.toString() ?? '';
    final activeRole = data['active_role']?.toString();
    final roles = data['roles'];
    debugPrint(
      '[auth][login][success] tokenPresent=${token.isNotEmpty} '
      'activeRole=$activeRole roles=$roles',
    );

    return AuthSessionModel.fromJson(data);
  }

  String _maskToken(String token) {
    if (token.length <= 16) {
      return token;
    }
    return '${token.substring(0, 8)}...${token.substring(token.length - 8)}';
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
