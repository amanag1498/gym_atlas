import '../../core/models/app_models.dart';
import '../../core/network/api_client.dart';

class PublicRepository {
  PublicRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<PaginatedResponse<ApiRecord>> discoveryGyms({
    int page = 1,
    int perPage = 15,
    String? city,
    double? latitude,
    double? longitude,
    double? distance,
    bool? openNow,
  }) async {
    final response = await _apiClient.get<List<ApiRecord>>(
      '/public/discovery/gyms',
      queryParameters: <String, dynamic>{
        'page': page,
        'per_page': perPage,
        if (city != null && city.isNotEmpty) 'city': city,
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
        if (distance != null) 'distance': distance,
        if (openNow != null) 'open_now': openNow,
      },
      decoder: (data) => (data as List)
          .whereType<Map>()
          .map((item) => ApiRecord(item.cast<String, dynamic>()))
          .toList(growable: false),
    );

    return PaginatedResponse<ApiRecord>(
      items: response.data,
      pagination: response.pagination ?? const PaginationMeta(currentPage: 1, lastPage: 1, perPage: 15, total: 0),
    );
  }

  Future<ApiRecord> gymDetail(String slug, {double? latitude, double? longitude}) async {
    final response = await _apiClient.get<ApiRecord>(
      '/public/discovery/gyms/$slug',
      queryParameters: <String, dynamic>{
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
      },
      decoder: (data) => ApiRecord((data as Map).cast<String, dynamic>()),
    );
    return response.data;
  }

  Future<void> createTrialRequest(JsonMap payload) async {
    await _apiClient.post<dynamic>('/public/trial-requests', data: payload);
  }

  Future<PaginatedResponse<NotificationItem>> notifications({int page = 1, int perPage = 20}) async {
    final response = await _apiClient.get<List<NotificationItem>>(
      '/public/notifications',
      queryParameters: <String, dynamic>{'page': page, 'per_page': perPage},
      decoder: (data) => (data as List)
          .whereType<Map>()
          .map((item) => NotificationItem.fromJson(item.cast<String, dynamic>()))
          .toList(growable: false),
    );

    return PaginatedResponse<NotificationItem>(
      items: response.data,
      pagination: response.pagination ?? const PaginationMeta(currentPage: 1, lastPage: 1, perPage: 20, total: 0),
    );
  }

  Future<void> markNotificationRead(int id, {bool read = true}) async {
    await _apiClient.post<dynamic>('/public/notifications/$id/${read ? 'read' : 'unread'}');
  }
}
