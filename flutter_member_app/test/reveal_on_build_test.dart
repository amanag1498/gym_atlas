import 'package:flutter/material.dart';
import 'package:flutter_member_app/core/widgets/reveal_on_build.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('skips decorative entrance motion when animations are disabled', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: MediaQuery(
          data: MediaQueryData(disableAnimations: true),
          child: RevealOnBuild(child: Text('Dashboard content')),
        ),
      ),
    );

    expect(find.text('Dashboard content'), findsOneWidget);
    expect(find.byType(AnimatedSlide), findsNothing);
    expect(find.byType(AnimatedOpacity), findsNothing);
  });
}
