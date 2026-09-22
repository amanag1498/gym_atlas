import 'package:dio/dio.dart';
import 'package:flutter_admin_app/core/user_facing_error.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('hides server error details', () {
    final request = RequestOptions(path: '/admin/private');
    final error = DioException(
      requestOptions: request,
      response: Response<dynamic>(
        requestOptions: request,
        statusCode: 500,
        data: const {'message': 'Internal stack trace'},
      ),
      type: DioExceptionType.badResponse,
    );
    expect(userFacingError(error), 'Something went wrong. Please try again.');
  });
}
