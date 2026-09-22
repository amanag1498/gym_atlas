import 'package:dio/dio.dart';
import 'package:flutter_member_app/src/core/user_facing_error.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final request = RequestOptions(path: '/private');

  test('hides server validation details', () {
    final error = DioException(
      requestOptions: request,
      response: Response<dynamic>(
        requestOptions: request,
        statusCode: 422,
        data: const {'message': 'SQL field bio failed validation'},
      ),
      type: DioExceptionType.badResponse,
    );
    expect(userFacingError(error), 'Please check your details and try again.');
  });

  test('gives recovery guidance for connection failures', () {
    final error = DioException(
      requestOptions: request,
      type: DioExceptionType.connectionError,
      message: 'SocketException: private host',
    );
    expect(
      userFacingError(error),
      'Check your internet connection and try again.',
    );
  });

  test('hides unknown framework details', () {
    expect(
      userFacingError(Exception('secret backend trace')),
      'Something went wrong. Please try again.',
    );
  });
}
