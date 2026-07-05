class MemberUser {
  const MemberUser({
    required this.id,
    required this.name,
    required this.email,
    required this.activeRole,
    required this.isActive,
    required this.roles,
  });

  final int id;
  final String name;
  final String email;
  final String activeRole;
  final bool isActive;
  final List<String> roles;

  bool get isMemberRole => activeRole == 'member' || roles.contains('member');

  Map<String, dynamic> toJson() {
    return <String, dynamic>{
      'id': id,
      'name': name,
      'email': email,
      'active_role': activeRole,
      'is_active': isActive,
      'roles': roles,
    };
  }

  factory MemberUser.fromJson(Map<String, dynamic> json) {
    final roles = (json['roles'] as List<dynamic>? ?? const <dynamic>[])
        .map((role) => role.toString())
        .toList();

    return MemberUser(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      activeRole: json['active_role']?.toString() ?? '',
      isActive: json['is_active'] == true,
      roles: roles,
    );
  }
}
