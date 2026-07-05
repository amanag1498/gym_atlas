import 'dart:math';

typedef JsonMap = Map<String, dynamic>;

class AppOption {
  const AppOption({
    required this.label,
    required this.value,
  });

  final String label;
  final String value;
}

class PaginationMeta {
  const PaginationMeta({
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  bool get hasMore => currentPage < lastPage;

  factory PaginationMeta.fromJson(JsonMap json) {
    return PaginationMeta(
      currentPage: _asInt(json['current_page'], fallback: 1),
      lastPage: max(_asInt(json['last_page'], fallback: 1), 1),
      perPage: _asInt(json['per_page'], fallback: 15),
      total: _asInt(json['total'], fallback: 0),
    );
  }
}

class ApiEnvelope<T> {
  const ApiEnvelope({
    required this.success,
    required this.message,
    required this.data,
    this.errors,
    this.pagination,
  });

  final bool success;
  final String message;
  final T data;
  final JsonMap? errors;
  final PaginationMeta? pagination;
}

class PaginatedResponse<T> {
  const PaginatedResponse({
    required this.items,
    required this.pagination,
  });

  final List<T> items;
  final PaginationMeta pagination;
}

class SessionUser {
  const SessionUser({
    required this.id,
    required this.name,
    required this.email,
    required this.activeRole,
    required this.roles,
    required this.permissions,
    required this.avatar,
    required this.authProvider,
    required this.gyms,
    required this.branches,
    this.memberProfile,
    this.trainerProfile,
  });

  final int id;
  final String name;
  final String email;
  final String activeRole;
  final List<String> roles;
  final List<String> permissions;
  final String? avatar;
  final String? authProvider;
  final List<ApiRecord> gyms;
  final List<ApiRecord> branches;
  final ApiRecord? memberProfile;
  final ApiRecord? trainerProfile;

  bool get hasMultipleRoles => roles.length > 1;

  factory SessionUser.fromJson(JsonMap json) {
    return SessionUser(
      id: _asInt(json['id']),
      name: (json['name'] ?? '') as String,
      email: (json['email'] ?? '') as String,
      activeRole: (json['active_role'] ?? '') as String,
      roles: _asStringList(json['roles']),
      permissions: _asStringList(json['permissions']),
      avatar: json['avatar'] as String?,
      authProvider: json['auth_provider'] as String?,
      gyms: _asRecordList(json['gyms']),
      branches: _asRecordList(json['branches']),
      memberProfile: _asNullableRecord(json['member_profile']),
      trainerProfile: _asNullableRecord(json['trainer_profile']),
    );
  }
}

class AuthSession {
  const AuthSession({
    required this.token,
    required this.tokenType,
    required this.user,
  });

  final String token;
  final String tokenType;
  final SessionUser user;

  factory AuthSession.fromJson(JsonMap json) {
    return AuthSession(
      token: (json['token'] ?? '') as String,
      tokenType: (json['token_type'] ?? 'Bearer') as String,
      user: SessionUser.fromJson((json['user'] ?? <String, dynamic>{}) as JsonMap),
    );
  }
}

class ApiRecord {
  const ApiRecord(this.raw);

  final JsonMap raw;

  String? string(String key) => raw[key]?.toString();

  int? intValue(String key) {
    final value = raw[key];
    if (value is int) return value;
    return int.tryParse(value?.toString() ?? '');
  }

  double? doubleValue(String key) {
    final value = raw[key];
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  bool boolValue(String key, {bool fallback = false}) {
    final value = raw[key];
    if (value is bool) return value;
    if (value is num) return value != 0;
    if (value is String) {
      return value.toLowerCase() == 'true' || value == '1';
    }

    return fallback;
  }

  List<ApiRecord> records(String key) => _asRecordList(raw[key]);

  List<String> strings(String key) => _asStringList(raw[key]);

  JsonMap? map(String key) {
    final value = raw[key];
    return value is JsonMap ? value : null;
  }
}

class NotificationItem {
  const NotificationItem({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.isRead,
    required this.createdAt,
    required this.data,
  });

  final int id;
  final String type;
  final String title;
  final String body;
  final bool isRead;
  final DateTime? createdAt;
  final JsonMap data;

  factory NotificationItem.fromJson(JsonMap json) {
    return NotificationItem(
      id: _asInt(json['id']),
      type: (json['type'] ?? '') as String,
      title: (json['title'] ?? '') as String,
      body: (json['body'] ?? '') as String,
      isRead: json['read_at'] != null,
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
      data: (json['data'] is JsonMap) ? json['data'] as JsonMap : <String, dynamic>{},
    );
  }
}

class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.room,
    required this.senderId,
    required this.recipientId,
    required this.body,
    required this.createdAt,
    required this.isMine,
    required this.persisted,
  });

  final String id;
  final String room;
  final int senderId;
  final int recipientId;
  final String body;
  final DateTime createdAt;
  final bool isMine;
  final bool persisted;

  factory ChatMessage.fromSocket(JsonMap json, {required int currentUserId}) {
    return ChatMessage(
      id: (json['id'] ?? '') as String,
      room: (json['room'] ?? '') as String,
      senderId: _asInt(json['senderId'] ?? json['sender_id']),
      recipientId: _asInt(json['recipientId'] ?? json['recipient_id']),
      body: (json['body'] ?? json['message'] ?? '') as String,
      createdAt: DateTime.tryParse(json['createdAt']?.toString() ?? '') ?? DateTime.now(),
      isMine: _asInt(json['senderId'] ?? json['sender_id']) == currentUserId,
      persisted: json['persisted'] == true,
    );
  }
}

List<ApiRecord> _asRecordList(dynamic value) {
  if (value is List) {
    return value.whereType<JsonMap>().map(ApiRecord.new).toList(growable: false);
  }

  return const <ApiRecord>[];
}

ApiRecord? _asNullableRecord(dynamic value) {
  return value is JsonMap ? ApiRecord(value) : null;
}

List<String> _asStringList(dynamic value) {
  if (value is List) {
    return value.map((item) => item.toString()).toList(growable: false);
  }

  return const <String>[];
}

int _asInt(dynamic value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}
