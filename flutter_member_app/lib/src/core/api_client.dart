import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import 'config.dart';

class MemberApiClient {
  MemberApiClient({
    String? token,
    Future<void> Function()? onUnauthorized,
  }) : _dio = Dio(
         BaseOptions(
           baseUrl: MemberConfig.apiBaseUrl,
           headers: const <String, Object?>{
             'Accept': 'application/json',
           },
         ),
       ),
       _onUnauthorized = onUnauthorized {
    setBearerToken(token);
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          debugPrint(
            '[dio][request] ${options.method} ${options.baseUrl}${options.path} '
            'query=${options.queryParameters} data=${options.data}',
          );
          handler.next(options);
        },
        onResponse: (response, handler) {
          debugPrint(
            '[dio][response] ${response.statusCode} ${response.requestOptions.method} '
            '${response.requestOptions.baseUrl}${response.requestOptions.path} '
            'data=${response.data}',
          );
          handler.next(response);
        },
        onError: (error, handler) async {
          debugPrint(
            '[dio][error] status=${error.response?.statusCode} '
            'type=${error.type} '
            '${error.requestOptions.method} ${error.requestOptions.baseUrl}${error.requestOptions.path} '
            'query=${error.requestOptions.queryParameters} data=${error.requestOptions.data} '
            'response=${error.response?.data}',
          );
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
