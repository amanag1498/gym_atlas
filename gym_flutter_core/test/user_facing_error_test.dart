import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/src/core/network/api_exception.dart';
import 'package:gym_flutter_core/src/core/network/user_facing_error.dart';

void main() {
  test('hides server error details', () {
    expect(
      userFacingError(const ApiException(message: 'Internal stack trace')),
      'Something went wrong. Please try again.',
    );
  });

  test('keeps validation guidance', () {
    expect(
      userFacingError(
        const ApiException(message: 'private field name', statusCode: 422),
      ),
      'Please check your details and try again.',
    );
  });
}
