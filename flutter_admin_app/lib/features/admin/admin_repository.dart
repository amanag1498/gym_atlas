import '../../core/models/session_models.dart';
import '../../core/network/api_client.dart';

class AdminRepository {
  AdminRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<Map<String, dynamic>> fetchDashboard(String role) async {
    final path = role == 'platform_admin'
        ? '/platform-admin/dashboard'
        : '/gym/dashboard';
    final response = await _apiClient.get(path);
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> fetchGymContext() async {
    final response = await _apiClient.get('/gym/context');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<PaginatedResponse<Map<String, dynamic>>> fetchCollection(
    String path, {
    int page = 1,
    int perPage = 15,
    Map<String, dynamic>? queryParameters,
  }) async {
    final response = await _apiClient.get(
      path,
      queryParameters: {'page': page, 'per_page': perPage, ...?queryParameters},
    );
    final items = (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final pagination = Map<String, dynamic>.from(
      ((response['meta'] as Map?)?['pagination'] as Map?) ?? const {},
    );

    final firstPage = PaginatedResponse<Map<String, dynamic>>(
      items: items,
      currentPage: _paginationInt(pagination['current_page']) ?? page,
      lastPage: _paginationInt(pagination['last_page']) ?? page,
      total: _paginationInt(pagination['total']) ?? items.length,
    );

    // Existing max-sized requests are complete option/audit datasets used by
    // forms rather than visible paginated tables. Continue through every API
    // page so gyms with more than 100 records are not silently truncated.
    if (page != 1 || perPage < 100 || !firstPage.hasMore) {
      return firstPage;
    }

    final merged = <Map<String, dynamic>>[...firstPage.items];
    final seenIds = firstPage.items
        .map((item) => item['id'])
        .where((id) => id != null)
        .toSet();
    var currentPage = firstPage.currentPage;
    var lastPage = firstPage.lastPage;
    var total = firstPage.total;

    while (currentPage < lastPage) {
      final next = await fetchCollection(
        path,
        page: currentPage + 1,
        perPage: perPage,
        queryParameters: queryParameters,
      );
      for (final item in next.items) {
        final id = item['id'];
        if (id == null || seenIds.add(id)) {
          merged.add(item);
        }
      }
      if (next.currentPage <= currentPage) break;
      currentPage = next.currentPage;
      lastPage = next.lastPage;
      total = next.total;
    }

    return PaginatedResponse<Map<String, dynamic>>(
      items: merged,
      currentPage: currentPage,
      lastPage: lastPage,
      total: total,
    );
  }

  int? _paginationInt(Object? value) {
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  Future<Map<String, dynamic>> fetchMemberDetail(int memberId) async {
    final response = await _apiClient.get('/gym/members/$memberId');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> assignMemberTrainer(
    int memberId, {
    int? assignedTrainerUserId,
  }) => _apiClient.post(
    '/gym/members/$memberId/assign-trainer',
    data: {'assigned_trainer_user_id': assignedTrainerUserId},
  );

  Future<Map<String, dynamic>> fetchMembershipDetail(int membershipId) async {
    final response = await _apiClient.get(
      '/gym/member-memberships/$membershipId',
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> fetchPlatformGymDetail(int gymId) async {
    final response = await _apiClient.get('/platform-admin/gyms/$gymId');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> fetchPlatformUserDetail(int userId) async {
    final response = await _apiClient.get('/platform-admin/users/$userId');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> activatePlatformUser(int userId) =>
      _apiClient.post('/platform-admin/users/$userId/activate');

  Future<void> deactivatePlatformUser(int userId) =>
      _apiClient.post('/platform-admin/users/$userId/deactivate');

  Future<List<Map<String, dynamic>>> fetchPlatformGymOwners({
    String? search,
  }) async {
    final response = await fetchCollection(
      '/platform-admin/gym-owners',
      perPage: 100,
      queryParameters: {
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      },
    );
    return response.items;
  }

  Future<Map<String, dynamic>> fetchPlatformGymOwnerDetail(int userId) async {
    final response = await _apiClient.get('/platform-admin/gym-owners/$userId');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> createPlatformGymOwner(
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.post(
      '/platform-admin/gym-owners',
      data: payload,
    );
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    final owner = Map<String, dynamic>.from(data['owner'] as Map? ?? const {});
    if (data['temporary_password'] != null) {
      owner['temporary_password'] = data['temporary_password'];
    }
    return owner;
  }

  Future<void> activatePlatformGymOwner(int userId) =>
      _apiClient.post('/platform-admin/gym-owners/$userId/activate');

  Future<void> deactivatePlatformGymOwner(
    int userId, {
    bool confirmOrphanActiveGyms = false,
  }) => _apiClient.post(
    '/platform-admin/gym-owners/$userId/deactivate',
    data: {'confirm_orphan_active_gyms': confirmOrphanActiveGyms},
  );

  Future<List<Map<String, dynamic>>> fetchPlatformFacilities({
    String? search,
    String? status,
  }) async {
    final response = await fetchCollection(
      '/platform-admin/facilities',
      perPage: 100,
      queryParameters: {
        if (status != null && status.trim().isNotEmpty) 'status': status.trim(),
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      },
    );
    return response.items;
  }

  Future<Map<String, dynamic>> fetchPlatformFacilityDetail(
    int facilityId,
  ) async {
    final response = await _apiClient.get(
      '/platform-admin/facilities/$facilityId',
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> createPlatformFacility(
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.post(
      '/platform-admin/facilities',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> updatePlatformFacility(
    int facilityId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.put(
      '/platform-admin/facilities/$facilityId',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> togglePlatformFacilityStatus(
    int facilityId,
  ) async {
    final response = await _apiClient.post(
      '/platform-admin/facilities/$facilityId/toggle-status',
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> deletePlatformFacility(int facilityId) =>
      _apiClient.delete('/platform-admin/facilities/$facilityId');

  Future<PaginatedResponse<Map<String, dynamic>>> fetchPlatformWorkoutBooks({
    int page = 1,
    int perPage = 20,
    String? search,
    String? status,
  }) {
    return fetchCollection(
      '/platform-admin/workout-books',
      page: page,
      perPage: perPage,
      queryParameters: {
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
        if (status != null && status.trim().isNotEmpty) 'status': status.trim(),
      },
    );
  }

  Future<Map<String, dynamic>> fetchPlatformWorkoutBookDetail(int id) async {
    final response = await _apiClient.get('/platform-admin/workout-books/$id');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> createPlatformWorkoutBook(
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.post(
      '/platform-admin/workout-books',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> updatePlatformWorkoutBook(
    int id,
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.put(
      '/platform-admin/workout-books/$id',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> deletePlatformWorkoutBook(int id) =>
      _apiClient.delete('/platform-admin/workout-books/$id');

  Future<PaginatedResponse<Map<String, dynamic>>> fetchPlatformDietTemplates({
    int page = 1,
    int perPage = 20,
  }) {
    return fetchCollection(
      '/platform-admin/diet-templates',
      page: page,
      perPage: perPage,
    );
  }

  Future<Map<String, dynamic>> createPlatformDietTemplate(
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.post(
      '/platform-admin/diet-templates',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> updatePlatformDietTemplate(
    int id,
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.put(
      '/platform-admin/diet-templates/$id',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<PaginatedResponse<Map<String, dynamic>>> fetchGymDietPlans({
    required int gymId,
    int? branchId,
    int page = 1,
    int perPage = 20,
  }) {
    return fetchCollection(
      '/gym/diet-plans',
      page: page,
      perPage: perPage,
      queryParameters: {
        'gym_id': gymId,
        if (branchId != null) 'branch_id': branchId,
      },
    );
  }

  Future<List<Map<String, dynamic>>> fetchGymDietTemplates({
    required int gymId,
    int? branchId,
  }) async {
    final response = await fetchCollection(
      '/gym/diet-templates',
      perPage: 100,
      queryParameters: {
        'gym_id': gymId,
        if (branchId != null) 'branch_id': branchId,
      },
    );
    return response.items;
  }

  Future<List<Map<String, dynamic>>> fetchGymDietMembers({
    required int gymId,
    int? branchId,
  }) async {
    final response = await fetchCollection(
      '/gym/members',
      perPage: 100,
      queryParameters: {
        'gym_id': gymId,
        if (branchId != null) 'branch_id': branchId,
      },
    );
    return response.items;
  }

  Future<Map<String, dynamic>> createGymDietPlan(
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.post('/gym/diet-plans', data: payload);
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> assignGymDietTemplate(
    int templateId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.post(
      '/gym/diet-templates/$templateId/assign',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> updateGymDietPlan(
    int planId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.put(
      '/gym/diet-plans/$planId',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> deleteGymDietPlan(int planId) =>
      _apiClient.delete('/gym/diet-plans/$planId');

  Future<Map<String, dynamic>> fetchPlatformReport(
    String reportKey, {
    Map<String, dynamic>? queryParameters,
  }) async {
    final path = switch (reportKey) {
      'gyms' => '/platform-admin/reports/gyms',
      'users' => '/platform-admin/reports/users',
      'payments' => '/platform-admin/reports/payments',
      'attendance' => '/platform-admin/reports/attendance',
      'custom-fees' => '/platform-admin/reports/custom-fees',
      _ => '/platform-admin/reports',
    };

    final response = await _apiClient.get(
      path,
      queryParameters: queryParameters,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<List<Map<String, dynamic>>> fetchPlatformGyms({String? search}) async {
    final response = await fetchCollection(
      '/platform-admin/gyms',
      perPage: 100,
      queryParameters: {
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      },
    );
    return response.items;
  }

  Future<Map<String, dynamic>> createPlatformGym(
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.post(
      '/platform-admin/gyms',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> updatePlatformGym(
    int gymId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.put(
      '/platform-admin/gyms/$gymId',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<List<Map<String, dynamic>>> fetchPaymentHistory(
    int membershipId,
  ) async {
    final response = await _apiClient.get(
      '/gym/member-memberships/$membershipId/payments',
      queryParameters: {'per_page': 20},
    );
    return (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<List<Map<String, dynamic>>> fetchMemberPayments(int memberId) async {
    final response = await _apiClient.get(
      '/gym/members/$memberId/payments',
      queryParameters: {'per_page': 20},
    );
    return (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<PaginatedResponse<Map<String, dynamic>>> fetchGymPayments({
    int page = 1,
    int perPage = 15,
    Map<String, dynamic>? queryParameters,
  }) {
    return fetchCollection(
      '/gym/payments',
      page: page,
      perPage: perPage,
      queryParameters: queryParameters,
    );
  }

  Future<PaginatedResponse<Map<String, dynamic>>> fetchGymDues({
    int page = 1,
    int perPage = 15,
    Map<String, dynamic>? queryParameters,
  }) {
    return fetchCollection(
      '/gym/dues',
      page: page,
      perPage: perPage,
      queryParameters: queryParameters,
    );
  }

  Future<List<Map<String, dynamic>>> fetchMemberAttendance(int memberId) async {
    final response = await _apiClient.get(
      '/gym/members/$memberId/attendance',
      queryParameters: {'per_page': 20},
    );
    return (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<PaginatedResponse<Map<String, dynamic>>> fetchGymAttendance({
    int page = 1,
    int perPage = 15,
    Map<String, dynamic>? queryParameters,
  }) {
    return fetchCollection(
      '/gym/attendance',
      page: page,
      perPage: perPage,
      queryParameters: queryParameters,
    );
  }

  Future<PaginatedResponse<Map<String, dynamic>>> fetchGymAttendanceToday({
    int page = 1,
    int perPage = 20,
    Map<String, dynamic>? queryParameters,
  }) async {
    final response = await _apiClient.get(
      '/gym/attendance/today',
      queryParameters: {'page': page, 'per_page': perPage, ...?queryParameters},
    );
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    final items = (data['items'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final pagination = Map<String, dynamic>.from(
      ((response['meta'] as Map?)?['pagination'] as Map?) ?? const {},
    );
    return PaginatedResponse<Map<String, dynamic>>(
      items: items,
      currentPage: _paginationInt(pagination['current_page']) ?? page,
      lastPage: _paginationInt(pagination['last_page']) ?? page,
      total: _paginationInt(pagination['total']) ?? items.length,
    );
  }

  Future<PaginatedResponse<Map<String, dynamic>>> fetchGymTrialRequests({
    int page = 1,
    int perPage = 15,
    Map<String, dynamic>? queryParameters,
  }) {
    return fetchCollection(
      '/gym/trial-requests',
      page: page,
      perPage: perPage,
      queryParameters: queryParameters,
    );
  }

  Future<Map<String, dynamic>> fetchGymTrialRequestDetail(
    int trialRequestId,
  ) async {
    final response = await _apiClient.get(
      '/gym/trial-requests/$trialRequestId',
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> acceptGymTrialRequest(
    int trialRequestId, {
    String? notes,
  }) async {
    final response = await _apiClient.post(
      '/gym/trial-requests/$trialRequestId/accept',
      data: {
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> rejectGymTrialRequest(
    int trialRequestId, {
    String? notes,
  }) async {
    final response = await _apiClient.post(
      '/gym/trial-requests/$trialRequestId/reject',
      data: {
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> completeGymTrialRequest(
    int trialRequestId, {
    String? notes,
  }) async {
    final response = await _apiClient.post(
      '/gym/trial-requests/$trialRequestId/complete',
      data: {
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> assignGymTrialTrainer(
    int trialRequestId, {
    int? assignedTrainerId,
    String? notes,
  }) async {
    final response = await _apiClient.post(
      '/gym/trial-requests/$trialRequestId/assign-trainer',
      data: {
        'assigned_trainer_id': assignedTrainerId,
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> convertGymTrialRequest(
    int trialRequestId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.post(
      '/gym/trial-requests/$trialRequestId/convert',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<List<Map<String, dynamic>>> fetchCustomFeeAudits(
    int membershipId,
  ) async {
    final response = await _apiClient.get(
      '/gym/member-memberships/$membershipId/custom-fee-audits',
      queryParameters: {'per_page': 20},
    );
    return (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<Map<String, dynamic>> fetchMemberCustomFee(
    int memberId, {
    int? membershipId,
  }) async {
    final response = await _apiClient.get(
      '/gym/members/$memberId/custom-fee',
      queryParameters: {
        if (membershipId != null) 'member_membership_id': membershipId,
      },
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<List<Map<String, dynamic>>> fetchCustomFeeAuditLogs({
    int? branchId,
    int? memberId,
  }) async {
    final response = await _apiClient.get(
      '/gym/custom-fees/audit-logs',
      queryParameters: {
        'per_page': 100,
        if (branchId != null) 'branch_id': branchId,
        if (memberId != null) 'member_id': memberId,
      },
    );
    return (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<List<Map<String, dynamic>>> fetchNotificationPreferences() async {
    final response = await _apiClient.get('/notification-preferences');
    return (response['data'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<List<Map<String, dynamic>>> updateNotificationPreferences(
    List<Map<String, dynamic>> preferences,
  ) async {
    final response = await _apiClient.put(
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

  Future<PaginatedResponse<Map<String, dynamic>>> fetchNotifications({
    int page = 1,
    int perPage = 20,
    Map<String, dynamic>? queryParameters,
  }) {
    return fetchCollection(
      '/notifications',
      page: page,
      perPage: perPage,
      queryParameters: queryParameters,
    );
  }

  Future<void> markNotificationRead(int notificationId) =>
      _apiClient.post('/notifications/$notificationId/read');

  Future<void> markAllNotificationsRead({int? gymId, int? branchId}) =>
      _apiClient.post(
        '/notifications/read-all',
        data: {
          if (gymId != null) 'gym_id': gymId,
          if (branchId != null) 'branch_id': branchId,
        },
      );

  Future<Map<String, dynamic>> fetchGymProfile() async {
    final response = await _apiClient.get('/gym/profile');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> fetchGymPublicListingSettings() async {
    final response = await _apiClient.get('/gym/public-listing-settings');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> updateGymProfile(Map<String, dynamic> payload) =>
      _apiClient.put('/gym/profile', data: payload);
  Future<void> updateGymPublicListingSettings(Map<String, dynamic> payload) =>
      _apiClient.put('/gym/public-listing-settings', data: payload);
  Future<Map<String, dynamic>> fetchGymReport(
    String reportKey, {
    Map<String, dynamic>? queryParameters,
  }) async {
    final path = switch (reportKey) {
      'revenue' => '/gym/reports/revenue',
      'dues' => '/gym/reports/dues',
      'memberships' => '/gym/reports/memberships',
      'attendance' => '/gym/reports/attendance',
      'trainers' => '/gym/reports/trainers',
      'custom-fees' => '/gym/reports/custom-fees',
      'leads' => '/gym/reports/leads',
      _ => '/gym/reports',
    };
    final response = await _apiClient.get(
      path,
      queryParameters: queryParameters,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> fetchGymSettings() async {
    final response = await _apiClient.get('/gym/settings');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> fetchPlatformSettings() async {
    final response = await _apiClient.get('/platform-admin/settings');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> updatePlatformSettings(
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.put(
      '/platform-admin/settings',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<Map<String, dynamic>> updateGymSettings(
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.put('/gym/settings', data: payload);
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<PaginatedResponse<Map<String, dynamic>>> fetchGymAuditLogs({
    int page = 1,
    int perPage = 20,
    Map<String, dynamic>? queryParameters,
  }) {
    return fetchCollection(
      '/gym/audit-logs',
      page: page,
      perPage: perPage,
      queryParameters: queryParameters,
    );
  }

  Future<void> createBranch(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/branches', data: payload);
  Future<Map<String, dynamic>> fetchBranchDetail(int branchId) async {
    final response = await _apiClient.get('/gym/branches/$branchId');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> updateBranch(int branchId, Map<String, dynamic> payload) =>
      _apiClient.put('/gym/branches/$branchId', data: payload);

  Future<void> toggleBranchStatus(int branchId) =>
      _apiClient.post('/gym/branches/$branchId/toggle-status');

  Future<void> deleteBranch(int branchId) =>
      _apiClient.delete('/gym/branches/$branchId');
  Future<Map<String, dynamic>> fetchStaffDetail(int staffId) async {
    final response = await _apiClient.get('/gym/staff/$staffId');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> createStaff(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/staff', data: payload);
  Future<void> updateStaff(int staffId, Map<String, dynamic> payload) =>
      _apiClient.put('/gym/staff/$staffId', data: payload);

  Future<void> activateStaff(int staffId) =>
      _apiClient.post('/gym/staff/$staffId/activate');

  Future<void> deactivateStaff(int staffId) =>
      _apiClient.post('/gym/staff/$staffId/deactivate');

  Future<void> deleteStaff(int staffId) =>
      _apiClient.delete('/gym/staff/$staffId');
  Future<void> createTrainer(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/trainers', data: payload);
  Future<Map<String, dynamic>> fetchTrainerDetail(int trainerId) async {
    final response = await _apiClient.get('/gym/trainers/$trainerId');
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> createMember(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/members', data: payload);
  Future<void> updateTrainer(int trainerId, Map<String, dynamic> payload) =>
      _apiClient.put('/gym/trainers/$trainerId', data: payload);
  Future<void> activateTrainer(int trainerId) =>
      _apiClient.post('/gym/trainers/$trainerId/activate');

  Future<void> deactivateTrainer(int trainerId) =>
      _apiClient.post('/gym/trainers/$trainerId/deactivate');

  Future<void> assignTrainerMembers(int trainerId, List<int> memberIds) =>
      _apiClient.post(
        '/gym/trainers/$trainerId/assign-members',
        data: {'member_ids': memberIds},
      );

  Future<void> deleteTrainer(int trainerId) =>
      _apiClient.delete('/gym/trainers/$trainerId');
  Future<void> updateMember(int memberId, Map<String, dynamic> payload) =>
      _apiClient.put('/gym/members/$memberId', data: payload);
  Future<void> createMembershipPlan(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/membership-plans', data: payload);
  Future<void> updateMembershipPlan(int planId, Map<String, dynamic> payload) =>
      _apiClient.put('/gym/membership-plans/$planId', data: payload);
  Future<void> activateMembershipPlan(int planId) =>
      _apiClient.post('/gym/membership-plans/$planId/activate');
  Future<void> deactivateMembershipPlan(int planId) =>
      _apiClient.post('/gym/membership-plans/$planId/deactivate');
  Future<void> assignMembership(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/member-memberships', data: payload);
  Future<void> assignMembershipForMember(
    int memberId,
    Map<String, dynamic> payload,
  ) => _apiClient.post(
    '/gym/members/$memberId/assign-membership',
    data: payload,
  );
  Future<void> renewMembership(
    int membershipId,
    Map<String, dynamic> payload,
  ) => _apiClient.post('/gym/memberships/$membershipId/renew', data: payload);
  Future<void> freezeMembership(int membershipId, {String? notes}) =>
      _apiClient.post(
        '/gym/memberships/$membershipId/freeze',
        data: {
          if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
        },
      );
  Future<void> extendMembership(
    int membershipId,
    Map<String, dynamic> payload,
  ) => _apiClient.post('/gym/memberships/$membershipId/extend', data: payload);
  Future<void> cancelMembership(int membershipId, {String? notes}) =>
      _apiClient.post(
        '/gym/memberships/$membershipId/cancel',
        data: {
          if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
        },
      );
  Future<void> updateCustomFee(
    int membershipId,
    Map<String, dynamic> payload,
  ) => _apiClient.post(
    '/gym/member-memberships/$membershipId/custom-fee',
    data: payload,
  );
  Future<void> updateMemberCustomFee(
    int memberId,
    Map<String, dynamic> payload,
  ) => _apiClient.post('/gym/members/$memberId/custom-fee', data: payload);
  Future<void> collectPayment(int membershipId, Map<String, dynamic> payload) =>
      _apiClient.post(
        '/gym/member-memberships/$membershipId/payments',
        data: payload,
      );

  Future<void> collectGymPayment(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/payments', data: payload);
  Future<void> manualAttendance(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/attendance/manual', data: payload);
  Future<void> scanAttendance(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/attendance/qr-scan', data: payload);
  Future<void> biometricAttendance(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/attendance/biometric-scan', data: payload);
  Future<void> publishAnnouncement(String role, Map<String, dynamic> payload) {
    final path = role == 'platform_admin'
        ? '/platform-admin/announcements'
        : '/gym/announcements';
    return _apiClient.post(path, data: payload);
  }

  String _communicationBase(String role) => role == 'platform_admin'
      ? '/platform-admin/communications'
      : '/gym/communications';

  Future<Map<String, dynamic>> fetchWhatsAppConnection(String role) async {
    final response = await _apiClient.get(
      '${_communicationBase(role)}/whatsapp',
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<List<Map<String, dynamic>>> fetchCommunicationCampaigns(
    String role,
  ) async {
    final response = await _apiClient.get(
      '${_communicationBase(role)}/campaigns',
    );
    return (response['data'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<List<Map<String, dynamic>>> fetchCommunicationAutomations(
    String role,
  ) async {
    final response = await _apiClient.get(
      '${_communicationBase(role)}/automations',
    );
    return (response['data'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<List<Map<String, dynamic>>> fetchCommunicationNotificationTypes(
    String role,
  ) async {
    final response = await _apiClient.get(
      '${_communicationBase(role)}/notification-types',
    );
    return (response['data'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<List<Map<String, dynamic>>> fetchWhatsAppInbox(String role) async {
    try {
      final response = await _apiClient.get(
        '${_communicationBase(role)}/whatsapp/inbox',
      );
      return (response['data'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
    } catch (_) {
      return const [];
    }
  }

  Future<Uri> createWhatsAppOnboardingSession(String role) async {
    final response = await _apiClient.post(
      '${_communicationBase(role)}/whatsapp/onboarding-session',
    );
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    return Uri.parse(data['url'] as String);
  }

  Future<void> syncWhatsAppTemplates(String role) =>
      _apiClient.post('${_communicationBase(role)}/whatsapp/sync-templates');

  Future<void> createWhatsAppTemplate(
    String role,
    Map<String, dynamic> payload,
  ) => _apiClient.post(
    '${_communicationBase(role)}/whatsapp/templates',
    data: payload,
  );

  Future<void> updateWhatsAppTemplate(
    String role,
    int templateId,
    Map<String, dynamic> payload,
  ) => _apiClient.put(
    '${_communicationBase(role)}/whatsapp/templates/$templateId',
    data: payload,
  );

  Future<void> disconnectWhatsApp(String role) =>
      _apiClient.delete('${_communicationBase(role)}/whatsapp');

  Future<Map<String, dynamic>> createCommunicationCampaign(
    String role,
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.post(
      '${_communicationBase(role)}/campaigns',
      data: payload,
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> sendCommunicationCampaign(String role, int campaignId) =>
      _apiClient.post('${_communicationBase(role)}/campaigns/$campaignId/send');

  Future<Map<String, dynamic>> previewCommunicationCampaign(
    String role,
    int campaignId,
  ) async {
    final response = await _apiClient.get(
      '${_communicationBase(role)}/campaigns/$campaignId/preview',
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> saveCommunicationAutomation(
    String role,
    Map<String, dynamic> payload,
  ) => _apiClient.put('${_communicationBase(role)}/automations', data: payload);

  Future<Map<String, dynamic>> fetchWhatsAppConversation(
    String role,
    int conversationId,
  ) async {
    final response = await _apiClient.get(
      '${_communicationBase(role)}/whatsapp/inbox/$conversationId',
    );
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  Future<void> replyToWhatsAppConversation(
    String role,
    int conversationId, {
    String? body,
    int? templateId,
  }) => _apiClient.post(
    '${_communicationBase(role)}/whatsapp/inbox/$conversationId/reply',
    data: {
      if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
      if (templateId != null) 'whatsapp_template_id': templateId,
      if (templateId != null) 'template_parameters': <dynamic>[],
    },
  );

  Future<void> runReminderEngine(Map<String, dynamic> payload) =>
      _apiClient.post('/gym/scheduled-reminders/run-due', data: payload);

  Future<void> updatePlatformGymApproval(
    int gymId,
    Map<String, dynamic> payload,
  ) => _apiClient.patch('/platform-admin/gyms/$gymId/approval', data: payload);

  Future<void> updatePlatformGymStatus(
    int gymId,
    Map<String, dynamic> payload,
  ) => _apiClient.patch('/platform-admin/gyms/$gymId/status', data: payload);

  Future<void> updatePlatformGymVerification(
    int gymId,
    Map<String, dynamic> payload,
  ) => _apiClient.patch(
    '/platform-admin/gyms/$gymId/verification',
    data: payload,
  );

  Future<void> updatePlatformGymListingFlags(
    int gymId,
    Map<String, dynamic> payload,
  ) => _apiClient.patch(
    '/platform-admin/gyms/$gymId/listing-flags',
    data: payload,
  );

  Future<void> approvePlatformGym(int gymId, {String? approvalNotes}) =>
      _apiClient.post(
        '/platform-admin/gyms/$gymId/approve',
        data: {
          if (approvalNotes != null && approvalNotes.trim().isNotEmpty)
            'approval_notes': approvalNotes.trim(),
        },
      );

  Future<void> rejectPlatformGym(int gymId, {String? approvalNotes}) =>
      _apiClient.post(
        '/platform-admin/gyms/$gymId/reject',
        data: {
          if (approvalNotes != null && approvalNotes.trim().isNotEmpty)
            'approval_notes': approvalNotes.trim(),
        },
      );

  Future<void> activatePlatformGym(int gymId) =>
      _apiClient.post('/platform-admin/gyms/$gymId/activate');

  Future<void> deactivatePlatformGym(int gymId) =>
      _apiClient.post('/platform-admin/gyms/$gymId/deactivate');

  Future<void> verifyPlatformGym(int gymId) =>
      _apiClient.post('/platform-admin/gyms/$gymId/verify');

  Future<void> featurePlatformGym(int gymId) =>
      _apiClient.post('/platform-admin/gyms/$gymId/feature');

  Future<void> promotePlatformGym(int gymId) =>
      _apiClient.post('/platform-admin/gyms/$gymId/promote');
}
