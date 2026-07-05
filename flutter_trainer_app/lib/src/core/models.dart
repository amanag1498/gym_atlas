class TrainerUser {
  const TrainerUser({
    required this.id,
    required this.name,
    required this.email,
    required this.activeRole,
    required this.isActive,
    required this.roles,
    required this.permissions,
  });

  final int id;
  final String name;
  final String email;
  final String activeRole;
  final bool isActive;
  final List<String> roles;
  final List<String> permissions;

  bool get isTrainerRole => activeRole == 'trainer' || roles.contains('trainer');

  Map<String, dynamic> toJson() {
    return <String, dynamic>{
      'id': id,
      'name': name,
      'email': email,
      'active_role': activeRole,
      'is_active': isActive,
      'roles': roles,
      'permissions': permissions,
    };
  }

  factory TrainerUser.fromJson(Map<String, dynamic> json) {
    return TrainerUser(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      activeRole: json['active_role']?.toString() ?? '',
      isActive: json['is_active'] == true,
      roles: (json['roles'] as List<dynamic>? ?? const [])
          .map((e) => e.toString())
          .toList(),
      permissions: (json['permissions'] as List<dynamic>? ?? const [])
          .map((e) => e.toString())
          .toList(),
    );
  }
}

class TrainerSession {
  const TrainerSession({required this.token, required this.user});

  final String token;
  final TrainerUser user;
}
