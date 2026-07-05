class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.isActive,
    required this.activeRole,
    required this.roles,
    required this.permissions,
    required this.gyms,
    required this.branches,
    required this.avatar,
  });

  final int id;
  final String name;
  final String email;
  final bool isActive;
  final String activeRole;
  final List<String> roles;
  final List<String> permissions;
  final List<Map<String, dynamic>> gyms;
  final List<Map<String, dynamic>> branches;
  final String? avatar;

  bool get isPlatformAdmin => activeRole == 'platform_admin';
  bool get isGymSideAdmin =>
      ['gym_owner', 'branch_manager', 'gym_staff'].contains(activeRole);
  List<String> get adminRoles => roles
      .where(
        (role) => const [
          'platform_admin',
          'gym_owner',
          'branch_manager',
          'gym_staff',
        ].contains(role),
      )
      .toList();

  bool hasRole(String role) => roles.contains(role);
  bool hasPermission(String permission) => permissions.contains(permission);
  bool hasAnyPermission(Iterable<String> values) =>
      values.any(permissions.contains);

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name']?.toString() ?? 'Unknown',
      email: json['email']?.toString() ?? '',
      isActive: json['is_active'] == true,
      activeRole: json['active_role']?.toString() ?? '',
      roles: (json['roles'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(),
      permissions: (json['permissions'] as List<dynamic>? ?? const [])
          .map((item) => item.toString())
          .toList(),
      gyms: (json['gyms'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(),
      branches: (json['branches'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(),
      avatar: json['avatar']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'is_active': isActive,
      'active_role': activeRole,
      'roles': roles,
      'permissions': permissions,
      'gyms': gyms,
      'branches': branches,
      'avatar': avatar,
    };
  }
}

class AuthSession {
  const AuthSession({required this.token, required this.user});

  final String token;
  final AppUser user;

  factory AuthSession.fromJson(Map<String, dynamic> json) {
    return AuthSession(
      token: json['token']?.toString() ?? '',
      user: AppUser.fromJson(
        Map<String, dynamic>.from(json['user'] as Map? ?? const {}),
      ),
    );
  }
}

class PaginatedResponse<T> {
  const PaginatedResponse({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
}
