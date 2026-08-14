import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart';

void main() {
  testWidgets('startup loader is animated and contains no visible text', (
    tester,
  ) async {
    await tester.pumpWidget(const MaterialApp(home: BrandedStartupLoader()));

    expect(find.byType(AnimatedBuilder), findsWidgets);
    expect(find.byType(CustomPaint), findsWidgets);
    expect(find.byType(Text), findsNothing);
    expect(find.textContaining('session'), findsNothing);
  });
}
