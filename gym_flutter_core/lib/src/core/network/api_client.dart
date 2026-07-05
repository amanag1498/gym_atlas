import 'package:dio/dio.dart';

import '../../config/app_environment.dart';
import '../models/app_models.dart';
import '../network/api_exception.dart';
import '../storage/secure_token_storage.dart';

class ApiClient {
  ApiClient({
    required AppEnvironment environment,
    required SecureTokenStorage storage,
    void Function()? onUnauthorized,
  })  : _storage = storage,
        _onUnauthorized = onUnauthorized,
        dio = Dio(
          BaseOptions(
            baseUrl: environment.apiBaseUrl,
            connectTimeout: const Duration(seconds: 20),
            receiveTimeout: const Duration(seconds: 20),
            headers: const {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
          ),
        ) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.readToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) async {
          if (error.response?.statusCode == 401) {
            _onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
  }

  final Dio dio;
  final SecureTokenStorage _storage;
  final void Function()? _onUnauthorized;

  Future<ApiEnvelope<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
    T Function(dynamic data)? decoder,
  }) async {
    return _request(
      () => dio.get<dynamic>(
        path,
        queryParameters: queryParameters,
        options: options,
      ),
      decoder: decoder,
    );
  }

  Future<ApiEnvelope<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    T Function(dynamic data)? decoder,
  }) async {
    return _request(
      () => dio.post<dynamic>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      ),
      decoder: decoder,
    );
  }

  Future<ApiEnvelope<T>> put<T>(
    String path, {
    dynamic data,
    Options? options,
    T Function(dynamic data)? decoder,
  }) async {
    return _request(
      () => dio.put<dynamic>(path, data: data, options: options),
      decoder: decoder,
    );
  }

  Future<ApiEnvelope<T>> patch<T>(
    String path, {
    dynamic data,
    Options? options,
    T Function(dynamic data)? decoder,
  }) async {
    return _request(
      () => dio.patch<dynamic>(path, data: data, options: options),
      decoder: decoder,
    );
  }

  Future<ApiEnvelope<T>> delete<T>(
    String path, {
    dynamic data,
    Options? options,
    T Function(dynamic data)? decoder,
  }) async {
    return _request(
      () => dio.delete<dynamic>(path, data: data, options: options),
      decoder: decoder,
    );
  }

  Future<ApiEnvelope<T>> _request<T>(
    Future<Response<dynamic>> Function() execute, {
    T Function(dynamic data)? decoder,
  }) async {
    try {
      final response = await execute();
      final body = (response.data as Map).cast<String, dynamic>();
      final data = decoder != null ? decoder(body['data']) : body['data'] as T;
      final paginationJson = (body['meta'] as Map?)?['pagination'];

      return ApiEnvelope<T>(
        success: body['success'] == true,
        message: body['message']?.toString() ?? 'Request successful.',
        data: data,
        errors: (body['errors'] as Map?)?.cast<String, dynamic>(),
        pagination: paginationJson is JsonMap ? PaginationMeta.fromJson(paginationJson) : null,
      );
    } on DioException catch (error) {
      final body = error.response?.data;
      if (body is Map<String, dynamic>) {
        throw ApiException(
          message: body['message']?.toString() ?? error.message ?? 'Network request failed.',
          statusCode: error.response?.statusCode,
          errors: (body['errors'] as Map?)?.cast<String, dynamic>(),
        );
      }
      throw ApiException(
        message: error.message ?? 'Network request failed.',
        statusCode: error.response?.statusCode,
      );
    }
  }
}
