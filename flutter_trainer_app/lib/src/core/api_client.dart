import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import 'config.dart';

class TrainerApiClient {
  TrainerApiClient({
    String? token,
    Future<void> Function()? onUnauthorized,
    Future<void> Function()? onForbiddenRole,
  }) : _dio = Dio(
         BaseOptions(
           baseUrl: TrainerConfig.apiBaseUrl,
           headers: const {'Accept': 'application/json'},
         ),
       ),
       _onUnauthorized = onUnauthorized,
       _onForbiddenRole = onForbiddenRole {
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
          if (_canRecoverForbiddenRole(error)) {
            try {
              await _onForbiddenRole?.call();
              error.requestOptions.extra['_trainer_role_retry'] = true;
              final response = await _dio.fetch<dynamic>(error.requestOptions);
              handler.resolve(response);
              return;
            } catch (_) {
              // Surface the original trainer endpoint error if recovery fails.
            }
          }
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;
  Future<void> Function()? _onUnauthorized;
  Future<void> Function()? _onForbiddenRole;

  Dio get dio => _dio;

  void updateUnauthorizedHandler(Future<void> Function()? handler) {
    _onUnauthorized = handler;
  }

  void updateForbiddenRoleHandler(Future<void> Function()? handler) {
    _onForbiddenRole = handler;
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
    return _request(
      () => _dio.get<dynamic>(path, queryParameters: queryParameters),
    );
  }

  Future<Map<String, dynamic>> post(String path, {Object? data}) async {
    return _request(() => _dio.post<dynamic>(path, data: data));
  }

  Future<Map<String, dynamic>> put(String path, {Object? data}) async {
    return _request(() => _dio.put<dynamic>(path, data: data));
  }

  Future<Map<String, dynamic>> delete(String path, {Object? data}) async {
    return _request(() => _dio.delete<dynamic>(path, data: data));
  }

  Future<Map<String, dynamic>> _request(
    Future<Response<dynamic>> Function() execute,
  ) async {
    try {
      final response = await execute();
      return Map<String, dynamic>.from(response.data as Map);
    } on DioException catch (error) {
      throw TrainerApiException.fromDio(error);
    }
  }

  bool _canRecoverForbiddenRole(DioException error) {
    if (error.response?.statusCode != 403) {
      return false;
    }
    if (error.requestOptions.extra['_trainer_role_retry'] == true) {
      return false;
    }
    if (!error.requestOptions.path.startsWith('/trainer/')) {
      return false;
    }

    final data = error.response?.data;
    final message = data is Map
        ? data['message']?.toString().toLowerCase()
        : null;
    return message?.contains('active role') == true;
  }

  void _logRequest(RequestOptions options) {
    if (!kDebugMode) {
      return;
    }
    debugPrint(
      '[dio][request] ${options.method} ${_requestUrl(options)} '
      'query=${_summarizeMap(options.queryParameters)} '
      'body=${_summarizePayload(options.data)}',
    );
  }

  void _logResponse(Response<dynamic> response) {
    if (!kDebugMode) {
      return;
    }
    debugPrint(
      '[dio][response] ${response.statusCode} '
      '${response.requestOptions.method} ${_requestUrl(response.requestOptions)} '
      'body=${_summarizePayload(response.data)}',
    );
  }

  void _logError(DioException error) {
    if (!kDebugMode) {
      return;
    }
    debugPrint(
      '[dio][error] status=${error.response?.statusCode} type=${error.type} '
      '${error.requestOptions.method} ${_requestUrl(error.requestOptions)} '
      'query=${_summarizeMap(error.requestOptions.queryParameters)} '
      'body=${_summarizePayload(error.requestOptions.data)} '
      'response=${_summarizePayload(error.response?.data)}',
    );
  }

  String _requestUrl(RequestOptions options) {
    return '${options.baseUrl}${options.path}';
  }

  String _summarizeMap(Map<String, dynamic> value) {
    if (value.isEmpty) {
      return '{}';
    }
    return _summarizePayload(value);
  }

  String _summarizePayload(dynamic value) {
    if (value == null) {
      return 'null';
    }
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
}

class TrainerApiException implements Exception {
  const TrainerApiException({required this.message, this.statusCode});

  factory TrainerApiException.fromDio(DioException error) {
    final responseBody = error.response?.data;
    final body = responseBody is Map
        ? responseBody.map((key, value) => MapEntry(key.toString(), value))
        : const <String, dynamic>{};
    final validationMessages = _flattenApiMessages(body['errors']);
    final responseMessage = body['message']?.toString().trim() ?? '';
    final dioMessage = error.message?.trim() ?? '';

    return TrainerApiException(
      message: validationMessages.isNotEmpty
          ? validationMessages.join('\n')
          : responseMessage.isNotEmpty
          ? responseMessage
          : dioMessage.isNotEmpty
          ? dioMessage
          : 'Network request failed. Please try again.',
      statusCode: error.response?.statusCode,
    );
  }

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

List<String> _flattenApiMessages(dynamic value) {
  final messages = <String>[];

  void collect(dynamic item) {
    if (item is Map) {
      for (final nested in item.values) {
        collect(nested);
      }
      return;
    }
    if (item is Iterable) {
      for (final nested in item) {
        collect(nested);
      }
      return;
    }
    final message = item?.toString().trim() ?? '';
    if (message.isNotEmpty && !messages.contains(message)) {
      messages.add(message);
    }
  }

  collect(value);
  return messages;
}
