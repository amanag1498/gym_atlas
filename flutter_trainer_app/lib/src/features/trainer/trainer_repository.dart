import 'package:dio/dio.dart';

import '../../core/api_client.dart';

class TrainerRepository {
  TrainerRepository(this._client);

  final TrainerApiClient _client;

  Future<Map<String, dynamic>> _getMemberPath(
    int memberId,
    String suffix,
  ) async {
    try {
      return await _client.get('/trainer/assigned-members/$memberId$suffix');
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        return _client.get('/trainer/assigned-members/$memberId$suffix');
      }
      if (error.response?.statusCode == 403) {
        throw Exception('You do not have permission to view this member.');
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> fetchContext() async =>
      _client.get('/trainer/context');
  Future<Map<String, dynamic>> inviteMember(Map<String, dynamic> payload) =>
      _client.post('/trainer/member-invitations', data: payload);
  Future<Map<String, dynamic>> respondToGymInvitation(
    int invitationId,
    String decision,
  ) => _client.post(
    '/trainer-invitations/$invitationId/respond',
    data: {'decision': decision},
  );
  Future<Map<String, dynamic>> fetchTasks() async =>
      _client.get('/trainer/tasks');
  Future<Map<String, dynamic>> fetchTodayClients({int page = 1}) async =>
      _client.get(
        '/trainer/today-clients',
        queryParameters: {'page': page, 'per_page': 20},
      );
  Future<Map<String, dynamic>> fetchPendingFollowUps({int page = 1}) async =>
      _client.get(
        '/trainer/pending-follow-ups',
        queryParameters: {'page': page, 'per_page': 20},
      );
  Future<Map<String, dynamic>> fetchProfile() async =>
      _client.get('/trainer/profile');
  Future<Map<String, dynamic>> fetchAssignedMembers({
    int page = 1,
    String? search,
    String? membershipStatus,
    bool paymentDueOnly = false,
  }) async {
    final query = <String, dynamic>{
      'page': page,
      'per_page': 15,
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      if (membershipStatus != null && membershipStatus.trim().isNotEmpty)
        'membership_status': membershipStatus.trim(),
      if (paymentDueOnly) 'payment_due_only': true,
    };

    try {
      return await _client.get(
        '/trainer/assigned-members',
        queryParameters: query,
      );
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        return _client.get('/trainer/assigned-members', queryParameters: query);
      }
      if (error.response?.statusCode == 403) {
        throw Exception('You do not have permission to view assigned members.');
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> fetchMemberDetail(int memberId) async =>
      _getMemberPath(memberId, '');
  Future<Map<String, dynamic>> fetchMemberAttendance(
    int memberId, {
    int page = 1,
  }) async {
    try {
      return await _client.get(
        '/trainer/assigned-members/$memberId/attendance',
        queryParameters: {'page': page, 'per_page': 20},
      );
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        return _client.get(
          '/trainer/assigned-members/$memberId/attendance',
          queryParameters: {'page': page, 'per_page': 20},
        );
      }
      if (error.response?.statusCode == 403) {
        throw Exception('You do not have permission to view this member.');
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> fetchMemberProgress(int memberId) async =>
      _getMemberPath(memberId, '/progress');
  Future<Map<String, dynamic>> fetchMemberNotes(
    int memberId, {
    int page = 1,
  }) async {
    try {
      return await _client.get(
        '/trainer/assigned-members/$memberId/notes',
        queryParameters: {'page': page, 'per_page': 20},
      );
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        return _client.get(
          '/trainer/assigned-members/$memberId/notes',
          queryParameters: {'page': page, 'per_page': 20},
        );
      }
      if (error.response?.statusCode == 403) {
        throw Exception('You do not have permission to view this member.');
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> fetchMemberPlans(int memberId) async =>
      _getMemberPath(memberId, '/workout-plans');
  Future<Map<String, dynamic>> fetchMemberWorkoutLogbook(int memberId) async =>
      _getMemberPath(memberId, '/workout-logbook');
  Future<Map<String, dynamic>> fetchExercises({
    int page = 1,
    String? search,
  }) async => _client.get(
    '/trainer/exercises',
    queryParameters: {
      'page': page,
      'per_page': 20,
      if (search != null && search.trim().isNotEmpty) 'search': search,
    },
  );
  Future<Map<String, dynamic>> fetchWorkoutTemplates() async => _client.get(
    '/trainer/workout-templates',
    queryParameters: {'per_page': 50},
  );
  Future<Map<String, dynamic>> fetchWorkoutPlans() async =>
      _client.get('/trainer/workout-plans');
  Future<Map<String, dynamic>> fetchDietPlans({int? memberId}) => _client.get(
    '/trainer/diet-plans',
    queryParameters: {
      'per_page': 100,
      if (memberId != null) 'member_id': memberId,
    },
  );
  Future<Map<String, dynamic>> fetchDietTemplates() =>
      _client.get('/trainer/diet-templates');
  Future<Map<String, dynamic>> createDietTemplate(
    Map<String, dynamic> payload,
  ) => _client.post('/trainer/diet-templates', data: payload);
  Future<Map<String, dynamic>> updateDietTemplate(
    int templateId,
    Map<String, dynamic> payload,
  ) => _client.put('/trainer/diet-templates/$templateId', data: payload);
  Future<Map<String, dynamic>> deleteDietTemplate(int templateId) =>
      _client.delete('/trainer/diet-templates/$templateId');
  Future<Map<String, dynamic>> assignDietTemplate(
    int templateId,
    Map<String, dynamic> payload,
  ) =>
      _client.post('/trainer/diet-templates/$templateId/assign', data: payload);
  Future<Map<String, dynamic>> createDietPlan(Map<String, dynamic> payload) =>
      _client.post('/trainer/diet-plans', data: payload);
  Future<Map<String, dynamic>> updateDietPlan(
    int planId,
    Map<String, dynamic> payload,
  ) => _client.put('/trainer/diet-plans/$planId', data: payload);
  Future<Map<String, dynamic>> deleteDietPlan(int planId) =>
      _client.delete('/trainer/diet-plans/$planId');
  Future<Map<String, dynamic>> fetchTrialRequests({int page = 1}) async =>
      _client.get(
        '/trainer/trial-requests',
        queryParameters: {'page': page, 'per_page': 20},
      );
  Future<Map<String, dynamic>> fetchNotifications() async {
    try {
      return await _client.get('/notifications');
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        return _client.get('/trainer/notifications');
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> fetchChatMessages(
    int recipientId, {
    int? beforeId,
    int perPage = 80,
  }) => _client.get(
    '/chat/messages',
    queryParameters: {
      'recipient_id': recipientId,
      'per_page': perPage,
      if (beforeId != null) 'before_id': beforeId,
    },
  );

  Future<Map<String, dynamic>> fetchChatConversations() =>
      _client.get('/chat/conversations');

  Future<Map<String, dynamic>> sendChatMessage(
    int recipientId,
    String message, {
    String? clientMessageId,
  }) => _client.post(
    '/chat/messages',
    data: {
      'recipient_id': recipientId,
      'message': message,
      if (clientMessageId != null) 'client_message_id': clientMessageId,
    },
  );

  Future<Map<String, dynamic>> markChatRead(int recipientId) =>
      _client.post('/chat/read', data: {'recipient_id': recipientId});

  Future<Map<String, dynamic>> fetchChatSafety(int otherUserId) => _client.get(
    '/chat/safety',
    queryParameters: {'other_user_id': otherUserId},
  );

  Future<Map<String, dynamic>> acceptChatTerms() =>
      _client.post('/chat/safety/terms');

  Future<Map<String, dynamic>> reportChatUser(
    int userId, {
    required String reason,
    String? details,
    int? messageId,
  }) => _client.post(
    '/chat/safety/report',
    data: {
      'reported_user_id': userId,
      'reason': reason,
      if (details != null && details.isNotEmpty) 'details': details,
      if (messageId != null) 'message_id': messageId,
    },
  );

  Future<Map<String, dynamic>> blockChatUser(int userId) =>
      _client.post('/chat/safety/block', data: {'blocked_user_id': userId});

  Future<Map<String, dynamic>> unblockChatUser(int userId) =>
      _client.delete('/chat/safety/block/$userId');

  Future<List<Map<String, dynamic>>> fetchNotificationPreferences() async {
    try {
      final response = await _client.get('/notification-preferences');
      return (response['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        throw Exception(
          'Notification preferences are not enabled for this trainer account.',
        );
      }
      rethrow;
    }
  }

  Future<List<Map<String, dynamic>>> updateNotificationPreferences(
    List<Map<String, dynamic>> preferences,
  ) async {
    final payload = {
      'preferences': preferences
          .map(
            (item) => {
              'notification_type': item['notification_type'],
              'gym_id': item['gym_id'],
              'branch_id': item['branch_id'],
              'is_enabled': item['is_enabled'] == true,
            },
          )
          .toList(),
    };
    final response = await _client.put(
      '/notification-preferences',
      data: payload,
    );

    return (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> payload) =>
      _client.put('/trainer/profile', data: payload);
  Future<Map<String, dynamic>> uploadProfilePhoto({
    required List<int> bytes,
    required String filename,
  }) {
    final formData = FormData.fromMap({
      'photo': MultipartFile.fromBytes(bytes, filename: filename),
    });
    return _client.post('/trainer/profile/photo', data: formData);
  }

  Future<Map<String, dynamic>> uploadCertificationFile({
    required List<int> bytes,
    required String filename,
  }) {
    final formData = FormData.fromMap({
      'certificate': MultipartFile.fromBytes(bytes, filename: filename),
    });
    return _client.post(
      '/trainer/profile/certifications/upload',
      data: formData,
    );
  }

  Future<Map<String, dynamic>> createNote(
    int memberId,
    Map<String, dynamic> payload,
  ) async {
    try {
      return await _client.post(
        '/trainer/assigned-members/$memberId/notes',
        data: payload,
      );
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        return _client.post(
          '/trainer/assigned-members/$memberId/notes',
          data: payload,
        );
      }
      if (error.response?.statusCode == 403) {
        throw Exception(
          'You do not have permission to add notes for this member.',
        );
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> updateNote(
    int noteId,
    Map<String, dynamic> payload,
  ) => _client.put('/trainer/notes/$noteId', data: payload);
  Future<Map<String, dynamic>> completeNote(int noteId) async {
    try {
      return await _client.post('/trainer/notes/$noteId/complete');
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        throw Exception(
          'Task completion is not enabled for this trainer account.',
        );
      }
      if (error.response?.statusCode == 403) {
        throw Exception(
          'You do not have permission to complete this follow-up.',
        );
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> createWorkoutTemplate(
    Map<String, dynamic> payload,
  ) => _client.post('/trainer/workout-templates', data: payload);
  Future<Map<String, dynamic>> assignWorkoutTemplate(
    int templateId,
    Map<String, dynamic> payload,
  ) => _client.post(
    '/trainer/workout-templates/$templateId/assign',
    data: payload,
  );
  Future<Map<String, dynamic>> createWorkoutPlan(
    Map<String, dynamic> payload,
  ) => _client.post('/trainer/workout-plans', data: payload);
  Future<Map<String, dynamic>> createExercise(Map<String, dynamic> payload) =>
      _client.post('/trainer/exercises', data: payload);
  Future<Map<String, dynamic>> updateWorkoutPlan(
    int planId,
    Map<String, dynamic> payload,
  ) => _client.put('/trainer/workout-plans/$planId', data: payload);
  Future<Map<String, dynamic>> deleteWorkoutPlan(int planId) =>
      _client.delete('/trainer/workout-plans/$planId');
  Future<Map<String, dynamic>> markNotificationRead(int notificationId) async {
    try {
      return await _client.post('/notifications/$notificationId/read');
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        return _client.post('/trainer/notifications/$notificationId/read');
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> markAllNotificationsRead() async {
    try {
      return await _client.post('/notifications/read-all');
    } on DioException catch (error) {
      if (error.response?.statusCode == 404) {
        throw Exception(
          'Bulk read status is not enabled for this trainer account.',
        );
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> createAnnouncement(
    Map<String, dynamic> payload,
  ) => _client.post('/trainer/announcements', data: payload);

  Future<Map<String, dynamic>> updateTrialRequest(
    int trialRequestId,
    Map<String, dynamic> payload,
  ) => _client.put('/trainer/trial-requests/$trialRequestId', data: payload);
}
