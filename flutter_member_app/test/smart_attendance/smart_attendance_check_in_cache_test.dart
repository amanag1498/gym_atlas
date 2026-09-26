import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_check_in_cache.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    FlutterSecureStorage.setMockInitialValues(<String, String>{});
  });

  test(
    'server attendance-day expiry works across phone timezone dates',
    () async {
      const cache = SecureSmartAttendanceCheckInCache();
      final recordedAt = DateTime.utc(2026, 9, 26, 19);
      final validUntil = DateTime.utc(2026, 9, 27, 18, 29, 59);

      await cache.markSuccessful(
        memberId: 42,
        gymId: 7,
        hubPublicId: 'SAHABC123DEF4567',
        localDate: recordedAt,
        attendanceDate: '2026-09-27',
        validUntil: validUntil,
      );

      expect(
        await cache.wasSuccessful(
          memberId: 42,
          gymId: 7,
          hubPublicId: 'SAHABC123DEF4567',
          localDate: DateTime.utc(2026, 9, 27, 10),
        ),
        isTrue,
      );
      expect(
        await cache.wasSuccessful(
          memberId: 42,
          gymId: 7,
          hubPublicId: 'SAHABC123DEF4567',
          localDate: validUntil.add(const Duration(seconds: 1)),
        ),
        isFalse,
      );
    },
  );
}
