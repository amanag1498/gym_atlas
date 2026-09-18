import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart';

void main() {
  testWidgets('keeps the email controller alive through dialog dismissal', (
    tester,
  ) async {
    String? submittedEmail;

    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: FilledButton(
              onPressed: () async {
                submittedEmail = await showDemoLoginDialog(
                  context: context,
                  title: 'Reviewer sign-in',
                  helperText: 'Enter the configured reviewer email.',
                );
              },
              child: const Text('Open dialog'),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Open dialog'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'reviewer@example.com');
    await tester.tap(find.text('Continue'));

    await tester.pump();
    expect(tester.takeException(), isNull);
    await tester.pumpAndSettle();

    expect(submittedEmail, 'reviewer@example.com');
    expect(tester.takeException(), isNull);
  });
}
