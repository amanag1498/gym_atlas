import 'package:flutter_member_app/src/features/member/workout_day_selection.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'filtered trainer selection never falls back to another trainer plan',
    () {
      final plans = <Map<String, dynamic>>[
        {'id': 10, 'independent_trainer_member_relationship_id': 100},
        {'id': 20, 'independent_trainer_member_relationship_id': 200},
      ];

      expect(
        selectedWorkoutPlanId(
          plans: plans,
          relationshipId: 200,
          requestedPlanId: 10,
        ),
        20,
      );
      expect(
        selectedWorkoutPlanId(
          plans: plans,
          relationshipId: 300,
          requestedPlanId: 10,
        ),
        isNull,
      );
    },
  );

  final plan = <String, dynamic>{
    'id': 50,
    'days': [
      {'id': 102, 'day_number': 2, 'label': 'Pull'},
      {'id': 101, 'day_number': 1, 'label': 'Push'},
      {'id': 103, 'day_number': 3, 'label': 'Legs'},
    ],
  };

  test('orders workout day choices by day number', () {
    expect(workoutPlanDays(plan).map((day) => day['id']), [101, 102, 103]);
  });

  test('recommends the day after the most recent completed selected day', () {
    final recommended = recommendedWorkoutDay(
      plan: plan,
      history: const [
        {
          'workout_plan_id': 50,
          'workout_plan_day_id': 102,
          'status': 'completed',
        },
      ],
    );

    expect(recommended?['id'], 103);
  });

  test('recommendation wraps from the last day to the first day', () {
    final recommended = recommendedWorkoutDay(
      plan: plan,
      history: const [
        {
          'workout_plan_id': 50,
          'workout_plan_day_id': 103,
          'status': 'completed',
        },
      ],
    );

    expect(recommended?['id'], 101);
  });

  test('recommendation uses the day snapshot after a plan is edited', () {
    final recommended = recommendedWorkoutDay(
      plan: plan,
      history: const [
        {
          'workout_plan_id': 50,
          'workout_plan_day_id': null,
          'plan_day_number': 1,
          'status': 'completed',
        },
      ],
    );

    expect(recommended?['id'], 102);
  });

  test('start payload adds selected day without changing legacy fields', () {
    expect(
      workoutStartPayload(
        workoutPlanId: 50,
        workoutPlanDayId: 102,
        sessionDate: '2026-08-09',
      ),
      {
        'workout_plan_id': 50,
        'workout_plan_day_id': 102,
        'session_date': '2026-08-09',
      },
    );
    expect(workoutStartPayload(sessionDate: '2026-08-09'), {
      'session_date': '2026-08-09',
    });
  });

  test(
    'formats persisted day snapshots for active and historical sessions',
    () {
      expect(
        workoutSessionDayLabel(const {
          'plan_day_number': 2,
          'plan_day_label': 'Pull',
        }),
        'Day 2 — Pull',
      );
      expect(workoutSessionDayLabel(const {}), isNull);
    },
  );
}
