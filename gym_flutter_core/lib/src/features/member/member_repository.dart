import '../../core/models/app_models.dart';
import '../../core/network/api_client.dart';

class MemberRepository {
  MemberRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<ApiRecord> context() => _record('/member/context');
  Future<ApiRecord> profile() => _record('/member/profile');
  Future<void> updateProfile(JsonMap payload) => _apiClient.put<dynamic>('/member/profile', data: payload);
  Future<ApiRecord> membership() => _record('/member/membership');
  Future<ApiRecord> trainer() => _record('/member/trainer');
  Future<ApiRecord> qrCode() => _record('/member/attendance/qr-code');
  Future<ApiRecord> attendanceStatus() => _record('/member/attendance/status');
  Future<ApiRecord> progressSummary() => _record('/member/progress/summary');
  Future<ApiRecord> logbookSummary() => _record('/member/logbook-summary');

  Future<PaginatedResponse<ApiRecord>> attendanceHistory({int page = 1, int perPage = 20}) =>
      _paginated('/member/attendance/history', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> workoutPlans({int page = 1, int perPage = 15}) =>
      _paginated('/member/workout-plans', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> workoutHistory({int page = 1, int perPage = 15}) =>
      _paginated('/member/workout-history', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> weightLogs({int page = 1, int perPage = 15}) =>
      _paginated('/member/progress/weight-logs', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> bodyMeasurements({int page = 1, int perPage = 15}) =>
      _paginated('/member/progress/body-measurements', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> progressPhotos({int page = 1, int perPage = 15}) =>
      _paginated('/member/progress/photos', page: page, perPage: perPage);

  Future<ApiRecord> workoutPlanDetail(int id) => _record('/member/workout-plans/$id');
  Future<ApiRecord> workoutSessionDetail(int id) => _record('/member/workout-sessions/$id');
  Future<ApiRecord> exerciseHistory(int exerciseId) => _record('/member/exercise-history/$exerciseId');

  Future<void> startWorkoutSession(JsonMap payload) => _apiClient.post<dynamic>('/member/workout-sessions/start', data: payload);
  Future<void> addWorkoutExercise(int sessionId, JsonMap payload) => _apiClient.post<dynamic>('/member/workout-sessions/$sessionId/exercises', data: payload);
  Future<void> completeWorkoutSession(int sessionId, JsonMap payload) => _apiClient.post<dynamic>('/member/workout-sessions/$sessionId/complete', data: payload);
  Future<void> createWeightLog(JsonMap payload) => _apiClient.post<dynamic>('/member/progress/weight-logs', data: payload);
  Future<void> createBodyMeasurement(JsonMap payload) => _apiClient.post<dynamic>('/member/progress/body-measurements', data: payload);
  Future<void> createProgressPhoto(JsonMap payload) => _apiClient.post<dynamic>('/member/progress/photos', data: payload);

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
