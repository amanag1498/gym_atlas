import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/diet_plan_meals_editor.dart';

void main() {
  testWidgets('repeatable meal editor adds a complete product payload', (
    tester,
  ) async {
    List<Map<String, dynamic>> payload = const [];

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: Form(
              child: DietPlanMealsEditor(
                initialMeals: const [
                  {'name': 'Breakfast', 'meal_type': 'breakfast', 'items': []},
                ],
                onChanged: (value) => payload = value,
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Add product'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Food or product'),
      'Oats',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Quantity or serving'),
      '80g',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Calories').last,
      '302',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Protein g').last,
      '10.5',
    );

    expect(payload, hasLength(1));
    expect(payload.single['name'], 'Breakfast');
    final items = payload.single['items'] as List<dynamic>;
    expect(items, hasLength(1));
    expect(items.single['name'], 'Oats');
    expect(items.single['quantity'], '80g');
    expect(items.single['calories'], 302);
    expect(items.single['protein_g'], 10.5);
  });

  testWidgets('plan details editor emits full scheduling and guidance fields', (
    tester,
  ) async {
    Map<String, dynamic> payload = const {};

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: Form(
              child: DietPlanDetailsEditor(
                initialPlan: const {},
                onChanged: (value) => payload = value,
              ),
            ),
          ),
        ),
      ),
    );

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Plan name'),
      'Recovery plan',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Starts on'),
      '2026-08-01',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Dietary preferences'),
      'Vegetarian',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Allergies and restrictions'),
      'Peanuts',
    );

    expect(payload['name'], 'Recovery plan');
    expect(payload['starts_on'], '2026-08-01');
    expect(payload['dietary_preferences'], 'Vegetarian');
    expect(payload['allergies_and_restrictions'], 'Peanuts');
  });
}
