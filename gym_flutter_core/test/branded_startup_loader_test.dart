import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart';

void main() {
  testWidgets('startup loader uses Atlas branding and helpful loading copy', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(home: BrandedStartupLoader(audience: 'Trainer')),
    );

    expect(find.byType(AnimatedBuilder), findsWidgets);
    expect(find.byType(CustomPaint), findsWidgets);
    expect(find.byType(AtlasBrandMark), findsWidgets);
    expect(find.text('Getting things ready'), findsOneWidget);
    expect(find.text('Preparing your training space'), findsOneWidget);
    expect(find.text('GYM ATLAS'), findsOneWidget);
    expect(find.text('COACH'), findsOneWidget);
    expect(find.byIcon(Icons.fitness_center_rounded), findsNothing);
  });

  testWidgets('startup loader respects reduced motion', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: MediaQuery(
          data: MediaQueryData(disableAnimations: true),
          child: BrandedStartupLoader(),
        ),
      ),
    );

    expect(
      find.byKey(const ValueKey<String>('startup-loader-animation')),
      findsNothing,
    );
    expect(find.text('Getting things ready'), findsOneWidget);
  });
}
