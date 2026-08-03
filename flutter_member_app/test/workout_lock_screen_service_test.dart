import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_member_app/src/features/member/services/workout_lock_screen_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const methodChannel = MethodChannel('workout-lock-screen-test');

  tearDown(() async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(methodChannel, null);
  });

  test('parses native action payloads without losing numeric values', () {
    final action = WorkoutLockScreenAction.fromMap(<String, Object>{
      'action': 'complete_set',
      'sessionId': '41',
      'exerciseId': 12,
      'setNumber': 3,
      'reps': 8.0,
      'weight': '42.5',
    });

    expect(action.action, 'complete_set');
    expect(action.sessionId, 41);
    expect(action.exerciseId, 12);
    expect(action.setNumber, 3);
    expect(action.reps, 8);
    expect(action.weight, 42.5);
  });

  test('returns the native start result to the workout UI', () async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(methodChannel, (call) async {
          expect(call.method, 'start');
          expect((call.arguments as Map<Object?, Object?>)['sessionId'], 41);
          return true;
        });
    final service = WorkoutLockScreenService(
      methodChannel: methodChannel,
      eventChannel: const EventChannel('workout-lock-screen-events-test'),
    );

    expect(await service.start(<String, dynamic>{'sessionId': 41}), isTrue);
  });
}
