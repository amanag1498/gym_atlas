import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/core/api_client.dart';
import 'package:flutter_trainer_app/src/core/user_facing_error.dart';

void main() {
  test('hides arbitrary trainer API exception details', () {
    expect(
      userFacingError(const TrainerApiException(message: 'secret SQL error')),
      'Something went wrong. Please try again.',
    );
  });

  test('gives recovery guidance for connection failures', () {
    expect(
      userFacingError(
        DioException(
          requestOptions: RequestOptions(path: '/private'),
          type: DioExceptionType.connectionError,
          message: 'SocketException: private host',
        ),
      ),
      'Check your internet connection and try again.',
    );
  });

  test('keeps connection guidance after API wrapping', () {
    final wrapped = TrainerApiException.fromDio(
      DioException(
        requestOptions: RequestOptions(path: '/private'),
        type: DioExceptionType.connectionTimeout,
        message: 'private connection detail',
      ),
    );
    expect(wrapped.isConnectionError, isTrue);
    expect(
      userFacingError(wrapped),
      'Check your internet connection and try again.',
    );
  });
}
