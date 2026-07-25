import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/diet_plan_form_codec.dart';

void main() {
  test('round trips repeatable diet product nutrition fields', () {
    final lines = DietPlanFormCodec.itemsToLines([
      {
        'name': 'Paneer',
        'quantity': '150g',
        'calories': 398,
        'protein_g': 28,
        'carbs_g': 6,
        'fats_g': 30,
        'notes': 'Grilled',
      },
      {
        'name': 'Salad',
        'quantity': '1 bowl',
        'calories': 90,
      },
    ]);

    final items = DietPlanFormCodec.linesToItems(lines);

    expect(items, hasLength(2));
    expect(items.first, {
      'name': 'Paneer',
      'quantity': '150g',
      'calories': 398,
      'protein_g': 28,
      'carbs_g': 6,
      'fats_g': 30,
      'notes': 'Grilled',
    });
    expect(items.last['name'], 'Salad');
    expect(items.last['quantity'], '1 bowl');
    expect(items.last['calories'], 90);
  });

  test('ignores blank product lines and parses decimal nutrition', () {
    final items = DietPlanFormCodec.linesToItems(
      '\nOats | 80g | 302 | 10.5 | 54.2 | 5.8 | Soak overnight\n',
    );

    expect(items, hasLength(1));
    expect(items.single['protein_g'], 10.5);
    expect(items.single['carbs_g'], 54.2);
    expect(items.single['fats_g'], 5.8);
  });
}
