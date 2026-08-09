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

    await tester.tap(find.text('Add custom food'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Food or product'),
      'Oats',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Quantity or serving'),
      '80g',
    );
    await tester.ensureVisible(find.text('Optional nutrition'));
    await tester.tap(find.text('Optional nutrition'));
    await tester.pumpAndSettle();
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

  testWidgets('meal editor copies catalog nutrition and keeps catalog id', (
    tester,
  ) async {
    List<Map<String, dynamic>> payload = const [];

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: DietPlanMealsEditor(
              initialMeals: const [
                {'name': 'Breakfast', 'items': []},
              ],
              foodCatalog: const [
                {
                  'id': 44,
                  'name': 'Rolled oats',
                  'category': 'Grains',
                  'default_quantity': '80 g',
                  'calories': 300,
                  'protein_g': 10,
                  'fiber_g': 8,
                  'dietary_tags': ['vegan'],
                  'allergens': ['gluten'],
                },
              ],
              onChanged: (value) => payload = value,
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Choose catalog food'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(ListTile, 'Rolled oats'));
    await tester.pumpAndSettle();

    final items = payload.single['items'] as List<dynamic>;
    expect(items, hasLength(1));
    expect(items.single['food_catalog_item_id'], 44);
    expect(items.single['name'], 'Rolled oats');
    expect(items.single['quantity'], '80 g');
    expect(items.single['calories'], 300);
    expect(items.single['protein_g'], 10);
    expect(items.single['fiber_g'], 8);
    expect(find.text('From food catalog'), findsOneWidget);
    expect(find.text('Add custom food'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.link_off_rounded));
    await tester.pump();
    final customItems = payload.single['items'] as List<dynamic>;
    expect(customItems.single.containsKey('food_catalog_item_id'), isFalse);
    expect(customItems.single['name'], 'Rolled oats');
  });

  testWidgets('meal editor adds a freely named meal instead of fixed slots', (
    tester,
  ) async {
    List<Map<String, dynamic>> payload = const [];

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: DietPlanMealsEditor(
              initialMeals: const [],
              onChanged: (value) => payload = value,
            ),
          ),
        ),
      ),
    );

    expect(find.text('Meal 1'), findsWidgets);
    await tester.tap(find.text('Add meal'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.byType(TextField),
      ),
      'Post-workout recovery',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Add'));
    await tester.pumpAndSettle();

    expect(find.text('Post-workout recovery'), findsWidgets);
    expect(payload, hasLength(2));
    expect(payload.last['name'], 'Post-workout recovery');
    expect(payload.last['meal_type'], 'post_workout_recovery');
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
    await tester.tap(find.text('Optional plan details'));
    await tester.pumpAndSettle();
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
