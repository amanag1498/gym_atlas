import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart';

void main() {
  testWidgets('renders centered Atlas branding with app audience', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: Center(
            child: AtlasBrandLockup(audience: 'Member', markSize: 64),
          ),
        ),
      ),
    );

    expect(find.byType(AtlasBrandMark), findsOneWidget);
    expect(find.text('GYM'), findsOneWidget);
    expect(find.text('ATLAS'), findsOneWidget);
    expect(find.text('MEMBER APP'), findsOneWidget);
    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Semantics &&
            widget.properties.label == 'Gym Atlas Member app',
      ),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
}
