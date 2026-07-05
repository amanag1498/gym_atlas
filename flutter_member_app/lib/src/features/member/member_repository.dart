import '../../core/api_client.dart';

class MemberRepository {
  MemberRepository(this._client);

  final MemberApiClient _client;

  Future<Map<String, dynamic>> fetchContext() async =>
      _client.get('/member/context');
  Future<Map<String, dynamic>> fetchAttendanceHistory() async =>
      _client.get('/member/attendance');
  Future<Map<String, dynamic>> fetchAttendanceStatus() async =>
      _client.get('/member/attendance/status');
  Future<Map<String, dynamic>> fetchQrCode() async =>
      _client.get('/member/qr-code');
  Future<Map<String, dynamic>> fetchMembership() async =>
      _client.get('/member/membership');
  Future<Map<String, dynamic>> leaveCurrentGym() async =>
      _client.post('/member/membership/leave');
  Future<Map<String, dynamic>> fetchMemberTrainer() async =>
      _client.get('/member/trainer');
  Future<Map<String, dynamic>> fetchWorkoutPlans() async =>
      _client.get('/member/workout-plans');
  Future<Map<String, dynamic>> fetchWorkoutPlan(int workoutPlanId) async =>
      _client.get('/member/workout-plans/$workoutPlanId');
  Future<Map<String, dynamic>> fetchWorkoutBooks({
    Map<String, dynamic>? queryParameters,
  }) async =>
      _client.get('/member/workout-books', queryParameters: queryParameters);
  Future<Map<String, dynamic>> fetchRecommendedWorkoutBooks({
    Map<String, dynamic>? queryParameters,
  }) async => _client.get(
    '/member/workout-books/recommended',
    queryParameters: queryParameters,
  );
  Future<Map<String, dynamic>> fetchWorkoutExercises({
    Map<String, dynamic>? queryParameters,
  }) async => _client.get(
    '/member/workout-exercises',
    queryParameters: queryParameters,
  );
  Future<Map<String, dynamic>> fetchWorkoutHistory() async =>
      _client.get('/member/workout-history');
  Future<Map<String, dynamic>> fetchExerciseHistory(int exerciseId) async =>
      _client.get('/member/exercise-history/$exerciseId');
  Future<Map<String, dynamic>> fetchLogbookSummary() async =>
      _client.get('/member/logbook-summary');
  Future<Map<String, dynamic>> fetchPersonalRecords() async =>
      _client.get('/member/logbook-summary');
  Future<Map<String, dynamic>> fetchProgressSummary() async =>
      _client.get('/member/progress/summary');
  Future<Map<String, dynamic>> syncDailySteps(Map<String, dynamic> payload) =>
      _client.post('/member/steps/sync', data: payload);
  Future<Map<String, dynamic>> fetchTodaySteps() async =>
      _client.get('/member/steps/today');
  Future<Map<String, dynamic>> fetchStepSummary({String range = '7d'}) async =>
      _client.get('/member/steps/summary', queryParameters: {'range': range});
  Future<Map<String, dynamic>> fetchWeightLogs() async =>
      _client.get('/member/progress/weight-logs');
  Future<Map<String, dynamic>> fetchBodyMeasurements() async =>
      _client.get('/member/progress/body-measurements');
  Future<Map<String, dynamic>> fetchPhotos() async =>
      _client.get('/member/progress/photos');
  Future<Map<String, dynamic>> fetchNotifications() async =>
      _client.get('/notifications');
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
  Future<Map<String, dynamic>> markNotificationRead(int notificationId) async =>
      _client.post('/notifications/$notificationId/read');
  Future<Map<String, dynamic>> markNotificationUnread(
    int notificationId,
  ) async => _client.post('/notifications/$notificationId/unread');
  Future<Map<String, dynamic>> markAllNotificationsRead() async =>
      _client.post('/notifications/read-all');
  Future<Map<String, dynamic>> fetchGymInvitations({
    String? status,
    int perPage = 15,
  }) async => _client.get(
    '/member/gym-invitations',
    queryParameters: {
      if (status != null) 'status': status,
      'per_page': perPage,
    },
  );
  Future<Map<String, dynamic>> acceptGymInvitation(int invitationId) =>
      _client.post('/member/gym-invitations/$invitationId/accept');
  Future<Map<String, dynamic>> rejectGymInvitation(int invitationId) =>
      _client.post('/member/gym-invitations/$invitationId/reject');
  Future<List<Map<String, dynamic>>> fetchNotificationPreferences() async {
    final response = await _client.get('/notification-preferences');
    return (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<List<Map<String, dynamic>>> updateNotificationPreferences(
    List<Map<String, dynamic>> preferences,
  ) async {
    final response = await _client.put(
      '/notification-preferences',
      data: {
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
      },
    );

    return (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<Map<String, dynamic>> fetchPublicGyms({
    Map<String, dynamic>? filters,
  }) async => _client.get('/public/discovery/gyms', queryParameters: filters);
  Future<Map<String, dynamic>> fetchPublicGymDetail(
    String slug, {
    Map<String, dynamic>? filters,
  }) async =>
      _client.get('/public/discovery/gyms/$slug', queryParameters: filters);
  Future<Map<String, dynamic>> fetchSavedGyms() async =>
      _client.get('/member/favorite-gyms');
  Future<Map<String, dynamic>> saveGym(int gymId) =>
      _client.post('/member/favorite-gyms/$gymId');
  Future<Map<String, dynamic>> removeSavedGym(int gymId) =>
      _client.delete('/member/favorite-gyms/$gymId');
  Future<Map<String, dynamic>> fetchProfile() => _client.get('/member/profile');
  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> payload) =>
      _client.put('/member/profile', data: payload);
  Future<Map<String, dynamic>> submitTrialRequest(
    Map<String, dynamic> payload,
  ) => _client.post('/member/trial-requests', data: payload);
  Future<Map<String, dynamic>> startWorkout(Map<String, dynamic> payload) =>
      _client.post('/member/workout-sessions/start', data: payload);
  Future<Map<String, dynamic>> createWorkoutPlan(
    Map<String, dynamic> payload,
  ) => _client.post('/member/workout-plans', data: payload);
  Future<Map<String, dynamic>> adoptWorkoutBookPlan(
    int workoutTemplateId,
    Map<String, dynamic> payload,
  ) => _client.post(
    '/member/workout-book-plans/$workoutTemplateId/adopt',
    data: payload,
  );
  Future<Map<String, dynamic>> duplicateWorkoutPlan(
    int workoutPlanId, {
    Map<String, dynamic>? payload,
  }) => _client.post(
    '/member/workout-plans/$workoutPlanId/duplicate',
    data: payload ?? const {},
  );
  Future<Map<String, dynamic>> updateWorkoutPlan(
    int workoutPlanId,
    Map<String, dynamic> payload,
  ) => _client.put('/member/workout-plans/$workoutPlanId', data: payload);
  Future<Map<String, dynamic>> deleteWorkoutPlan(int workoutPlanId) =>
      _client.delete('/member/workout-plans/$workoutPlanId');
  Future<Map<String, dynamic>> fetchWorkoutSession(int sessionId) =>
      _client.get('/member/workout-sessions/$sessionId');
  Future<Map<String, dynamic>> addWorkoutExercise(
    int sessionId,
    Map<String, dynamic> payload,
  ) => _client.post(
    '/member/workout-sessions/$sessionId/exercises',
    data: payload,
  );
  Future<Map<String, dynamic>> completeWorkoutSession(
    int sessionId,
    Map<String, dynamic> payload,
  ) => _client.post(
    '/member/workout-sessions/$sessionId/complete',
    data: payload,
  );
  Future<Map<String, dynamic>> addWeightLog(Map<String, dynamic> payload) =>
      _client.post('/member/progress/weight-logs', data: payload);
  Future<Map<String, dynamic>> addBodyMeasurement(
    Map<String, dynamic> payload,
  ) => _client.post('/member/progress/body-measurements', data: payload);
  Future<Map<String, dynamic>> addProgressPhoto(Map<String, dynamic> payload) =>
      _client.post('/member/progress/photos', data: payload);
}
