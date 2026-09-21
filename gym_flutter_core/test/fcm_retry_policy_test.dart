import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/fcm_retry_policy.dart';

void main() {
  test('backs off and stops after the configured retry limit', () {
    final policy = FcmRetryPolicy();

    expect(List.generate(6, (_) => policy.nextDelay()), const [
      Duration(seconds: 1),
      Duration(seconds: 2),
      Duration(seconds: 4),
      Duration(seconds: 8),
      Duration(seconds: 16),
      Duration(seconds: 32),
    ]);
    expect(policy.exhausted, isTrue);
    expect(policy.nextDelay(), isNull);
  });

  test('reset starts a fresh registration lifecycle', () {
    final policy = FcmRetryPolicy(maxAttempts: 2);

    expect(policy.nextDelay(), const Duration(seconds: 1));
    expect(policy.nextDelay(), const Duration(seconds: 2));
    expect(policy.nextDelay(), isNull);

    policy.reset();

    expect(policy.attempts, 0);
    expect(policy.nextDelay(), const Duration(seconds: 1));
  });
}
