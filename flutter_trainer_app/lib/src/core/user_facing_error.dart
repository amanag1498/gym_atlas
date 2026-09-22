import 'package:dio/dio.dart';

import 'api_client.dart';

/// Keeps server and framework details out of messages shown to trainers.
String userFacingError(Object error) {
  final statusCode = error is TrainerApiException
      ? error.statusCode
      : error is DioException
      ? error.response?.statusCode
      : null;
  switch (statusCode) {
    case 401:
      return 'Please sign in again to continue.';
    case 403:
      return "You don't have access to this right now.";
    case 404:
      return 'This is no longer available. Please refresh and try again.';
    case 422:
      return 'Please check your details and try again.';
    case 429:
      return 'Too many attempts. Please wait a moment and try again.';
  }
  if (error is DioException) {
    switch (error.type) {
      case DioExceptionType.connectionError:
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.sendTimeout:
        return 'Check your internet connection and try again.';
      default:
        break;
    }
  }
  if (error is TrainerApiException && error.isConnectionError) {
    return 'Check your internet connection and try again.';
  }
  return 'Something went wrong. Please try again.';
}
