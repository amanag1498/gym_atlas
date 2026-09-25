import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart';

void main() {
  testWidgets('privacy consent dialog separates account and optional choices', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(375, 667));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final withdrawn = <String>[];

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PrivacyConsentDialog(
            appName: 'Gym Atlas',
            items: const [
              {
                'purpose': 'core_account',
                'title': 'Account and service data',
                'description': 'Required account data.',
                'required': true,
                'granted': true,
              },
              {
                'purpose': 'photos',
                'title': 'Photos',
                'description': 'Store photos you choose to upload.',
                'required': false,
                'granted': true,
              },
            ],
            onGrant: (_) async {},
            onWithdraw: (purpose) async => withdrawn.add(purpose),
          ),
        ),
      ),
    );

    expect(find.text('Account services'), findsOneWidget);
    expect(find.text('Optional features'), findsOneWidget);
    expect(find.text('1 of 1 on'), findsOneWidget);

    await tester.ensureVisible(find.text('Photos'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Photos'));
    await tester.pumpAndSettle();

    expect(withdrawn, ['photos']);
    expect(find.text('0 of 1 on'), findsOneWidget);

    await tester.ensureVisible(find.text('Withdraw account consent'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Withdraw account consent'));
    await tester.pumpAndSettle();

    expect(find.text('Stop using this account?'), findsOneWidget);
    expect(find.text('Keep account active'), findsOneWidget);
  });

  testWidgets('privacy request dialog submits and refreshes history', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(667, 375));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    var submittedType = '';
    var fetchCount = 0;

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PrivacyRequestsDialog(
            fetchRequests: () async {
              fetchCount++;
              if (fetchCount == 1) return const [];
              return const [
                {
                  'type': 'access',
                  'status': 'pending',
                  'created_at': '2026-09-23T10:00:00.000Z',
                },
              ];
            },
            submitRequest: (type, details) async {
              submittedType = type;
            },
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.text('No requests yet. New requests will appear here.'),
      findsOneWidget,
    );

    await tester.ensureVisible(find.text('Send request'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();

    expect(submittedType, 'access');
    expect(
      find.text('Request sent. You can track its status below.'),
      findsOneWidget,
    );
    expect(find.text('Get a copy of my information'), findsWidgets);
    expect(find.text('Pending'), findsOneWidget);
  });
}
