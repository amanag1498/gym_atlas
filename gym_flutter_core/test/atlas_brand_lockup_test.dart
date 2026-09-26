import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart';

void main() {
  testWidgets('renders centered Atlas branding without audience chip', (
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
    expect(find.text('GYM ATLAS'), findsOneWidget);
    expect(find.text('DISCIPLINE IN MOTION'), findsOneWidget);
    expect(find.text('MEMBER APP'), findsNothing);
    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Semantics && widget.properties.label == 'Gym Atlas app',
      ),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('announces the coach product name for trainer audiences', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: AtlasBrandLockup(audience: 'Trainer')),
      ),
    );

    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Semantics &&
            widget.properties.label == 'Gym Atlas Coach app',
      ),
      findsOneWidget,
    );
    expect(find.text('COACH'), findsOneWidget);
  });

  testWidgets('announces the admin product name for admin audiences', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: AtlasBrandLockup(audience: 'Admin')),
      ),
    );

    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Semantics &&
            widget.properties.label == 'Gym Atlas Admin app',
      ),
      findsOneWidget,
    );
    expect(find.text('ADMIN'), findsOneWidget);
  });
}
