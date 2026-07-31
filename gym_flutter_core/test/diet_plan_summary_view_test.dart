import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/diet_plan_summary_view.dart';

void main() {
  testWidgets('renders every plan, meal, and food detail', (tester) async {
    final plan = <String, dynamic>{
      'name': 'Performance plan',
      'goal': 'Build strength',
      'status': 'active',
      'daily_calorie_target': 2200,
      'protein_target_g': 160,
      'carbs_target_g': 240,
      'fats_target_g': 70,
      'dietary_preferences': 'Vegetarian',
      'allergies_and_restrictions': 'No peanuts',
      'notes': 'Drink three litres of water',
      'starts_on': '2026-08-01',
      'ends_on': '2026-09-01',
      'assigned_at': '2026-07-31T10:00:00Z',
      'member': {'name': 'Member One'},
      'trainer': {'name': 'Trainer One'},
      'meals': [
        {
          'name': 'Pre workout',
          'meal_type': 'pre_workout',
          'scheduled_time': '07:30',
          'calories': 450,
          'protein_g': 25,
          'carbs_g': 65,
          'fats_g': 10,
          'notes': 'Eat 45 minutes before training',
          'items': [
            {
              'name': 'Banana oats',
              'quantity': '1 bowl',
              'calories': 420,
              'protein_g': 22,
              'carbs_g': 62,
              'fats_g': 9,
              'notes': 'Use soy milk',
            },
          ],
        },
      ],
    };

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(child: DietPlanSummaryView(plan: plan)),
        ),
      ),
    );

    for (final text in [
      'Performance plan',
      'Build strength',
      'Active',
      '2200 kcal/day',
      'Protein 160g',
      'Carbs 240g',
      'Fats 70g',
      'Member One',
      'Trainer One',
      '2026-08-01',
      '2026-09-01',
      'Vegetarian',
      'No peanuts',
      'Drink three litres of water',
      'Pre workout',
      'Pre Workout',
      '07:30 • 450 kcal • P 25g • C 65g • F 10g',
      'Eat 45 minutes before training',
      'Banana oats',
      '1 bowl • 420 kcal • P 22g • C 62g • F 9g',
      'Use soy milk',
    ]) {
      expect(find.text(text), findsOneWidget);
    }
  });
}
