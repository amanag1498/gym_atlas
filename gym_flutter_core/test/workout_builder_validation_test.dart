import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/workout_builder_validation.dart';

void main() {
  Map<String, dynamic> payload(List<Map<String, dynamic>> exercises) => {
    'duration_weeks': 4,
    'days': [
      {'day_number': 1, 'exercises': exercises},
    ],
  };

  Map<String, dynamic> exercise(int id) => {
    'exercise_id': id,
    'sets': 3,
    'tracking_mode': 'reps',
    'progression_policy': 'off',
  };

  test('accepts a valid plan or template day', () {
    expect(validateWorkoutBuilderPayload(payload([exercise(1)])), isNull);
  });

  test('requires mode-specific targets on both builders', () {
    final timed = {...exercise(1), 'tracking_mode': 'timed'};
    expect(
      validateWorkoutBuilderPayload(payload([timed])),
      contains('planned duration'),
    );
    expect(
      validateWorkoutBuilderPayload(
        payload([
          {...timed, 'planned_duration_seconds': 45},
        ]),
      ),
      isNull,
    );
  });

  test('rejects duplicate weekdays and split groups', () {
    final duplicate = payload([exercise(1)]);
    (duplicate['days'] as List).add({
      'day_number': 1,
      'exercises': [exercise(2)],
    });
    expect(validateWorkoutBuilderPayload(duplicate), contains('only once'));

    final grouped = payload([
      {...exercise(1), 'group_key': 'A', 'group_order': 1},
      exercise(2),
      {...exercise(3), 'group_key': 'A', 'group_order': 2},
    ]);
    expect(validateWorkoutBuilderPayload(grouped), contains('together'));
  });

  test('requires a valid double-progression rep range', () {
    expect(
      validateWorkoutBuilderPayload(
        payload([
          {
            ...exercise(1),
            'progression_policy': 'double_progression',
            'progression_config': {'min_reps': 12, 'max_reps': 8},
          },
        ]),
      ),
      contains('rep range'),
    );
  });
}
