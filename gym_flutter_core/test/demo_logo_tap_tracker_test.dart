import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart';

void main() {
  test('opens only after three quick taps', () {
    final tracker = DemoLogoTapTracker();
    final start = DateTime(2026, 9, 18, 10);

    expect(tracker.register(start), isFalse);
    expect(
      tracker.register(start.add(const Duration(milliseconds: 300))),
      isFalse,
    );
    expect(
      tracker.register(start.add(const Duration(milliseconds: 600))),
      isTrue,
    );
    expect(
      tracker.register(start.add(const Duration(milliseconds: 700))),
      isFalse,
    );
  });

  test('resets taps outside the time window', () {
    final tracker = DemoLogoTapTracker();
    final start = DateTime(2026, 9, 18, 10);

    expect(tracker.register(start), isFalse);
    expect(
      tracker.register(start.add(const Duration(milliseconds: 1300))),
      isFalse,
    );
    expect(
      tracker.register(start.add(const Duration(milliseconds: 1500))),
      isFalse,
    );
  });
}
