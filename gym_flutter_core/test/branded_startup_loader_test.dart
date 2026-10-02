import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart';

void main() {
  testWidgets('startup loader uses the current Atlas mark and Coach name', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(home: BrandedStartupLoader(audience: 'Trainer')),
    );

    expect(find.byType(AnimatedBuilder), findsWidgets);
    expect(find.byType(CustomPaint), findsWidgets);
    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Image &&
            widget.image is AssetImage &&
            (widget.image as AssetImage).assetName ==
                'assets/branding/gym_atlas_mark.png',
      ),
      findsOneWidget,
    );
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
    expect(find.text('GYM ATLAS'), findsOneWidget);
  });

  testWidgets('splash appears at launch and then reveals the app', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: BrandedStartupSplash(
          audience: 'Member',
          child: Scaffold(body: Text('Member home')),
        ),
      ),
    );

    expect(find.text('GYM ATLAS'), findsOneWidget);
    expect(find.text('Member home'), findsNothing);

    await tester.pump(const Duration(milliseconds: 1400));
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Member home'), findsOneWidget);
    expect(find.text('GYM ATLAS'), findsNothing);
  });
}
