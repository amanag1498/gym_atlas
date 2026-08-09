import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/core/api_client.dart';

void main() {
  test('surfaces Laravel validation details instead of generic 422 text', () {
    final request = RequestOptions(
      path: '/trainer/profile/verification/submit',
    );
    final exception = TrainerApiException.fromDio(
      DioException(
        requestOptions: request,
        response: Response<dynamic>(
          requestOptions: request,
          statusCode: 422,
          data: const {
            'message': 'The given data was invalid.',
            'errors': {
              'bio': ['Add a professional bio before submitting verification.'],
              'certifications': [
                'Add at least one certification before submitting verification.',
              ],
            },
          },
        ),
        type: DioExceptionType.badResponse,
      ),
    );

    expect(exception.statusCode, 422);
    expect(
      exception.toString(),
      'Add a professional bio before submitting verification.\n'
      'Add at least one certification before submitting verification.',
    );
  });

  test('falls back to the API response message', () {
    final request = RequestOptions(path: '/trainer/profile');
    final exception = TrainerApiException.fromDio(
      DioException(
        requestOptions: request,
        response: Response<dynamic>(
          requestOptions: request,
          statusCode: 403,
          data: const {'message': 'Trainer account is inactive.'},
        ),
        type: DioExceptionType.badResponse,
      ),
    );

    expect(exception.toString(), 'Trainer account is inactive.');
  });
}
