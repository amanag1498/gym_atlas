import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import 'config.dart';

class MemberApiClient {
  MemberApiClient({String? token, Future<void> Function()? onUnauthorized})
    : _dio = Dio(
        BaseOptions(
          baseUrl: MemberConfig.apiBaseUrl,
          headers: const <String, Object?>{'Accept': 'application/json'},
        ),
      ),
      _onUnauthorized = onUnauthorized {
    setBearerToken(token);
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          _logRequest(options);
          handler.next(options);
        },
        onResponse: (response, handler) {
          _logResponse(response);
          handler.next(response);
        },
        onError: (error, handler) async {
          _logError(error);
          if (error.response?.statusCode == 401) {
            await _onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;
  Future<void> Function()? _onUnauthorized;

  Dio get dio => _dio;

  void updateUnauthorizedHandler(Future<void> Function()? handler) {
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

  void setGymContext(int? gymId) {
    if (gymId == null) {
      _dio.options.headers.remove('X-Gym-Id');
      return;
    }

    _dio.options.headers['X-Gym-Id'] = gymId.toString();
  }

  void _logRequest(RequestOptions options) {
    if (!kDebugMode) return;
    debugPrint(
      '[dio][request] ${options.method} ${options.baseUrl}${options.path} '
      'query=${_summarizePayload(options.queryParameters)} '
      'body=${_summarizePayload(options.data)}',
    );
  }

  void _logResponse(Response<dynamic> response) {
    if (!kDebugMode) return;
    debugPrint(
      '[dio][response] ${response.statusCode} ${response.requestOptions.method} '
      '${response.requestOptions.baseUrl}${response.requestOptions.path} '
      'body=${_summarizePayload(response.data)}',
    );
  }

  void _logError(DioException error) {
    if (!kDebugMode) return;
    debugPrint(
      '[dio][error] status=${error.response?.statusCode} type=${error.type} '
      '${error.requestOptions.method} '
      '${error.requestOptions.baseUrl}${error.requestOptions.path} '
      'query=${_summarizePayload(error.requestOptions.queryParameters)} '
      'body=${_summarizePayload(error.requestOptions.data)} '
      'response=${_summarizePayload(error.response?.data)}',
    );
  }

  String _summarizePayload(dynamic value) {
    if (value == null) return 'null';
    if (value is FormData) {
      return 'FormData(fields=${value.fields.length}, files=${value.files.length})';
    }
    if (value is Map) {
      return 'Map(keys=${value.keys.map((key) => key.toString()).take(8).join(',')})';
    }
    if (value is Iterable) {
      return '${value.runtimeType}(length=${value.length})';
    }
    final text = value.toString();
    return text.length <= 120 ? text : '${text.substring(0, 120)}...';
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

  Future<Map<String, dynamic>> post(String path, {Object? data}) async {
    final response = await _dio.post<dynamic>(path, data: data);
    return Map<String, dynamic>.from(response.data as Map);
  }

  Future<Map<String, dynamic>> put(String path, {Object? data}) async {
    final response = await _dio.put<dynamic>(path, data: data);
    return Map<String, dynamic>.from(response.data as Map);
  }

  Future<Map<String, dynamic>> delete(String path, {Object? data}) async {
    final response = await _dio.delete<dynamic>(path, data: data);
    return Map<String, dynamic>.from(response.data as Map);
  }
}
