import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/workout_plan_summary_view.dart';

void main() {
  testWidgets('renders complete workout plan and exercise prescriptions', (
    tester,
  ) async {
    final plan = <String, dynamic>{
      'name': 'Strength foundation',
      'goal': 'Build full body strength',
      'difficulty': 'intermediate',
      'duration_weeks': 6,
      'estimated_session_minutes': 55,
      'notes': 'Progress load when all reps are clean',
      'days': [
        {
          'day_number': 1,
          'label': 'Push day',
          'focus': 'Chest and shoulders',
          'notes': 'Warm up for ten minutes',
          'exercises': [
            {
              'sets': 4,
              'reps': '8-10',
              'target_weight': 40,
              'rest_seconds': 75,
              'notes': 'Control the eccentric',
              'exercise': {'name': 'Incline press'},
            },
          ],
        },
      ],
    };

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: WorkoutPlanSummaryView(plan: plan),
          ),
        ),
      ),
    );

    for (final text in [
      'Strength foundation',
      'Build full body strength',
      'Intermediate',
      '6 weeks',
      '55 min/session',
      'Progress load when all reps are clean',
      'Push day',
      'Chest and shoulders',
      'Warm up for ten minutes',
      'Incline press',
      '4 sets • 8-10 reps • 40 target weight • 75 sec rest',
      'Control the eccentric',
    ]) {
      expect(find.text(text), findsOneWidget);
    }
  });
}
