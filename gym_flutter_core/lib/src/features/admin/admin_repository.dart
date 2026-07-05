import '../../core/models/app_models.dart';
import '../../core/network/api_client.dart';

class AdminRepository {
  AdminRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<ApiRecord> platformDashboard() => _record('/platform-admin/dashboard');
  Future<ApiRecord> gymDashboard() => _record('/gym/dashboard');
  Future<ApiRecord> gymContext() => _record('/gym/context');
  Future<ApiRecord> gymProfile() => _record('/gym/profile');

  Future<PaginatedResponse<ApiRecord>> platformGyms({int page = 1, int perPage = 20}) =>
      _paginated('/platform-admin/gyms', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> platformUsers({int page = 1, int perPage = 20}) =>
      _paginated('/platform-admin/users', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> platformTrainers({int page = 1, int perPage = 20}) =>
      _paginated('/platform-admin/trainers', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> platformMembers({int page = 1, int perPage = 20}) =>
      _paginated('/platform-admin/members', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> facilities({int page = 1, int perPage = 50}) =>
      _paginated('/platform-admin/facilities', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> branches({int page = 1, int perPage = 30}) =>
      _paginated('/gym/branches', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> staff({int page = 1, int perPage = 30}) =>
      _paginated('/gym/staff', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> trainers({int page = 1, int perPage = 30}) =>
      _paginated('/gym/trainers', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> members({int page = 1, int perPage = 30}) =>
      _paginated('/gym/members', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> membershipPlans({int page = 1, int perPage = 30}) =>
      _paginated('/gym/membership-plans', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> memberships({int page = 1, int perPage = 30}) =>
      _paginated('/gym/member-memberships', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> attendanceToday({int page = 1, int perPage = 30}) =>
      _paginated('/gym/attendance/today', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> notifications({int page = 1, int perPage = 20}) =>
      _paginated('/public/notifications', page: page, perPage: perPage);

  Future<PaginatedResponse<ApiRecord>> announcements({int page = 1, int perPage = 30}) =>
      _paginated('/gym/announcements', page: page, perPage: perPage);

  Future<void> approveGym(int gymId, JsonMap payload) => _apiClient.patch<dynamic>('/platform-admin/gyms/$gymId/approval', data: payload);
  Future<void> updatePublicListing(int gymId, JsonMap payload) => _apiClient.patch<dynamic>('/platform-admin/gyms/$gymId/public-listing', data: payload);
  Future<void> updateGymProfile(JsonMap payload) => _apiClient.put<dynamic>('/gym/profile', data: payload);
  Future<void> createBranch(JsonMap payload) => _apiClient.post<dynamic>('/gym/branches', data: payload);
  Future<void> createStaff(JsonMap payload) => _apiClient.post<dynamic>('/gym/staff', data: payload);
  Future<void> createTrainer(JsonMap payload) => _apiClient.post<dynamic>('/gym/trainers', data: payload);
  Future<void> createMember(JsonMap payload) => _apiClient.post<dynamic>('/gym/members', data: payload);
  Future<ApiRecord> memberDetail(int memberId) => _record('/gym/members/$memberId');
  Future<void> updateMember(int memberId, JsonMap payload) => _apiClient.put<dynamic>('/gym/members/$memberId', data: payload);
  Future<void> createMembershipPlan(JsonMap payload) => _apiClient.post<dynamic>('/gym/membership-plans', data: payload);
  Future<void> assignMembership(JsonMap payload) => _apiClient.post<dynamic>('/gym/member-memberships', data: payload);
  Future<ApiRecord> membershipDetail(int membershipId) => _record('/gym/member-memberships/$membershipId');
  Future<void> updateCustomFee(int membershipId, JsonMap payload) => _apiClient.post<dynamic>('/gym/member-memberships/$membershipId/custom-fee', data: payload);
  Future<PaginatedResponse<ApiRecord>> paymentHistory(int membershipId, {int page = 1, int perPage = 20}) =>
      _paginated('/gym/member-memberships/$membershipId/payments', page: page, perPage: perPage);
  Future<void> createPayment(int membershipId, JsonMap payload) => _apiClient.post<dynamic>('/gym/member-memberships/$membershipId/payments', data: payload);
  Future<void> markMembershipPaid(int membershipId) => _apiClient.post<dynamic>('/gym/member-memberships/$membershipId/mark-paid');
  Future<void> markMembershipUnpaid(int membershipId) => _apiClient.post<dynamic>('/gym/member-memberships/$membershipId/mark-unpaid');
  Future<void> scanAttendance(JsonMap payload) => _apiClient.post<dynamic>('/gym/attendance/scan', data: payload);
  Future<void> manualAttendance(JsonMap payload) => _apiClient.post<dynamic>('/gym/attendance/manual', data: payload);
  Future<void> createAnnouncement(JsonMap payload) => _apiClient.post<dynamic>('/gym/announcements', data: payload);
  Future<void> createFacility(JsonMap payload) => _apiClient.post<dynamic>('/platform-admin/facilities', data: payload);
  Future<void> markNotificationRead(int id) => _apiClient.post<dynamic>('/public/notifications/$id/read');

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
