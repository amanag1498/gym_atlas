import '../../core/models.dart';

class AuthSessionModel {
  const AuthSessionModel({
    required this.token,
    required this.user,
  });

  final String token;
  final MemberUser user;

  factory AuthSessionModel.fromJson(Map<String, dynamic> json) {
    return AuthSessionModel(
      token: json['token']?.toString() ?? '',
      user: MemberUser.fromJson(
        Map<String, dynamic>.from(json['user'] as Map? ?? const {}),
      ),
    );
  }
}
