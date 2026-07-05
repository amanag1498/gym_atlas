import '../../core/models/app_models.dart';
import '../../core/network/api_client.dart';

class TrainerRepository {
  TrainerRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<ApiRecord> context() => _record('/trainer/context');
  Future<ApiRecord> profile() => _record('/trainer/profile');
  Future<void> updateProfile(JsonMap payload) => _apiClient.put<dynamic>('/trainer/profile', data: payload);
  Future<ApiRecord> tasksSummary() => _record('/trainer/tasks');

  Future<PaginatedResponse<ApiRecord>> assignedMembers({int page = 1, int perPage = 15}) =>
      _paginated('/trainer/assigned-members', page: page, perPage: perPage);

  Future<ApiRecord> memberDetail(int id) => _record('/trainer/assigned-members/$id');
  Future<ApiRecord> memberProgress(int id) => _record('/trainer/assigned-members/$id/progress');

  Future<PaginatedResponse<ApiRecord>> memberAttendance(int id, {int page = 1, int perPage = 20}) =>
      _paginated('/trainer/assigned-members/$id/attendance', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> memberNotes(int id, {int page = 1, int perPage = 20}) =>
      _paginated('/trainer/assigned-members/$id/notes', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> todayClients({int page = 1, int perPage = 20}) =>
      _paginated('/trainer/today-clients', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> pendingFollowUps({int page = 1, int perPage = 20}) =>
      _paginated('/trainer/pending-follow-ups', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> workoutTemplates({int page = 1, int perPage = 20}) =>
      _paginated('/trainer/workout-templates', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> workoutPlans({int page = 1, int perPage = 20}) =>
      _paginated('/trainer/workout-plans', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> notifications({int page = 1, int perPage = 20}) =>
      _paginated('/trainer/notifications', page: page, perPage: perPage);

  Future<void> addNote(int memberId, JsonMap payload) => _apiClient.post<dynamic>('/trainer/assigned-members/$memberId/notes', data: payload);
  Future<void> completeNote(int noteId) => _apiClient.post<dynamic>('/trainer/notes/$noteId/complete');
  Future<void> createWorkoutTemplate(JsonMap payload) => _apiClient.post<dynamic>('/trainer/workout-templates', data: payload);
  Future<void> assignWorkoutTemplate(int templateId, JsonMap payload) => _apiClient.post<dynamic>('/trainer/workout-templates/$templateId/assign', data: payload);
  Future<void> createWorkoutPlan(JsonMap payload) => _apiClient.post<dynamic>('/trainer/workout-plans', data: payload);
  Future<void> createExercise(JsonMap payload) => _apiClient.post<dynamic>('/trainer/exercises', data: payload);
  Future<void> markNotificationRead(int id) => _apiClient.post<dynamic>('/trainer/notifications/$id/read');

  Future<ApiRecord> _record(String path) async {
    final response = await _apiClient.get<ApiRecord>(
      path,
      decoder: (data) => ApiRecord((data as Map).cast<String, dynamic>()),
    );
    return response.data;
  }

  Future<PaginatedResponse<ApiRecord>> _paginated(
    String path, {
    required int page,
    required int perPage,
  }) async {
    final response = await _apiClient.get<List<ApiRecord>>(
      path,
      queryParameters: <String, dynamic>{'page': page, 'per_page': perPage},
      decoder: (data) => (data as List)
          .whereType<Map>()
          .map((item) => ApiRecord(item.cast<String, dynamic>()))
          .toList(growable: false),
    );
    return PaginatedResponse<ApiRecord>(
      items: response.data,
      pagination: response.pagination ?? PaginationMeta(currentPage: page, lastPage: page, perPage: perPage, total: response.data.length),
    );
  }
}
