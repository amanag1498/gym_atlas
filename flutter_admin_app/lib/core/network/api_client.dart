import 'package:dio/dio.dart';

import '../config/app_config.dart';

class ApiClient {
  ApiClient({
    required String? token,
    required Future<void> Function() onUnauthorized,
  }) : _dio = Dio(
         BaseOptions(
           baseUrl: AppConfig.apiBaseUrl,
           connectTimeout: const Duration(seconds: 20),
           receiveTimeout: const Duration(seconds: 20),
           headers: {
             'Accept': 'application/json',
             if (token != null && token.isNotEmpty)
               'Authorization': 'Bearer $token',
           },
         ),
       ),
       _onUnauthorized = onUnauthorized {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onError: (error, handler) async {
          if (error.response?.statusCode == 401) {
            await _onUnauthorized();
          }
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;
  Future<void> Function() _onUnauthorized;

  void updateUnauthorizedHandler(Future<void> Function() handler) {
    _onUnauthorized = handler;
  }

  void setBearerToken(String? token) {
    if (token == null || token.isEmpty) {
      _dio.options.headers.remove('Authorization');
      return;
    }
    _dio.options.headers['Authorization'] = 'Bearer $token';
  }

  void clearBearerToken() {
    _dio.options.headers.remove('Authorization');
  }

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    final response = await _dio.get<dynamic>(
      path,
      queryParameters: queryParameters,
    );
    return Map<String, dynamic>.from(response.data as Map);
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Object? data,
    Map<String, dynamic>? queryParameters,
  }) async {
    final response = await _dio.post<dynamic>(
      path,
      data: data,
      queryParameters: queryParameters,
    );
    return Map<String, dynamic>.from(response.data as Map);
  }

  Future<Map<String, dynamic>> put(String path, {Object? data}) async {
    final response = await _dio.put<dynamic>(path, data: data);
    return Map<String, dynamic>.from(response.data as Map);
  }

  Future<Map<String, dynamic>> patch(String path, {Object? data}) async {
    final response = await _dio.patch<dynamic>(path, data: data);
    return Map<String, dynamic>.from(response.data as Map);
  }

  Future<void> delete(String path) async {
    await _dio.delete<dynamic>(path);
  }
}
