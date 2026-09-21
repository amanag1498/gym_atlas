import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/core/api_client.dart';
import 'package:flutter_trainer_app/src/core/models.dart';
import 'package:flutter_trainer_app/src/core/token_storage.dart';
import 'package:flutter_trainer_app/src/features/auth/auth_service.dart';
import 'package:flutter_trainer_app/src/features/auth/session_controller.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_home_screen.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('trainer home supports compact screens and large text', (
    tester,
  ) async {
    final client = TrainerApiClient();
    final session =
        TrainerSessionController(
            storage: const TrainerTokenStorage(),
            apiClient: client,
            authService: TrainerAuthService(client),
          )
          ..user = const TrainerUser(
            id: 2,
            name: 'Atlas Trainer With A Long Name',
            email: 'trainer@example.com',
            activeRole: 'trainer',
            isActive: true,
            roles: ['trainer'],
            permissions: [],
          )
          ..token = 'preview-token';

    Widget buildHome(Size size) {
      return ChangeNotifierProvider<TrainerSessionController>.value(
        value: session,
        child: MaterialApp(
          home: MediaQuery(
            data: MediaQueryData(
              size: size,
              textScaler: TextScaler.linear(2),
              disableAnimations: true,
            ),
            child: const TrainerHomeScreen(
              storePreviewData: {
                'context': {
                  'user': {
                    'name': 'Atlas Trainer With A Long Name',
                    'trainer_onboarding_completed': true,
                  },
                  'trainer_profile': {
                    'id': 2,
                    'trainer_type': 'gym',
                    'primary_specialization': 'Strength coaching',
                    'profile_completion_percentage': 80,
                    'verification_status': 'verified',
                  },
                },
              },
            ),
          ),
        ),
      );
    }

    await tester.pumpWidget(buildHome(const Size(375, 812)));
    await tester.pump();

    expect(find.text('Hi, Atlas'), findsOneWidget);
    expect(find.text('Plans'), findsOneWidget);
    expect(find.text('Unread conversations'), findsNothing);
    expect(find.text('Trial leads'), findsNothing);
    final plansAction = find.bySemanticsLabel('Plans');
    expect(plansAction, findsOneWidget);
    expect(
      tester.getSemantics(plansAction),
      matchesSemantics(
        label: 'Plans',
        isButton: true,
        hasTapAction: true,
        hasSelectedState: true,
        isSelected: false,
      ),
    );
    expect(tester.takeException(), isNull);
    await tester.scrollUntilVisible(
      find.text('Recent member progress'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pump();
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(buildHome(const Size(812, 375)));
    await tester.pump();
    expect(tester.takeException(), isNull);

    await tester.tap(find.bySemanticsLabel('Plans'));
    await tester.pump();
    expect(find.text('Minutes per session'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'trainer alerts show the notification inbox on a compact screen',
    (tester) async {
      final client = TrainerApiClient();
      final session =
          TrainerSessionController(
              storage: const TrainerTokenStorage(),
              apiClient: client,
              authService: TrainerAuthService(client),
            )
            ..user = const TrainerUser(
              id: 2,
              name: 'Atlas Trainer',
              email: 'trainer@example.com',
              activeRole: 'trainer',
              isActive: true,
              roles: ['trainer'],
              permissions: [],
            )
            ..token = 'preview-token';

      await tester.pumpWidget(
        ChangeNotifierProvider<TrainerSessionController>.value(
          value: session,
          child: MaterialApp(
            home: MediaQuery(
              data: const MediaQueryData(
                size: Size(375, 812),
                textScaler: TextScaler.linear(1.5),
                disableAnimations: true,
              ),
              child: const TrainerHomeScreen(
                initialIndex: 4,
                storePreviewData: {
                  'context': {
                    'user': {'trainer_onboarding_completed': true},
                    'trainer_profile': {'id': 2},
                  },
                  'notifications': [
                    {
                      'id': 1,
                      'title': 'New member update',
                      'body': 'A member completed their workout.',
                      'type': 'workout_update',
                      'read_at': null,
                    },
                    {
                      'id': 2,
                      'title': 'Previous update',
                      'body': 'Your schedule has changed.',
                      'type': 'schedule_update',
                      'read_at': '2026-09-20T10:00:00Z',
                    },
                  ],
                },
              ),
            ),
          ),
        ),
      );
      await tester.pump();

      expect(find.text('Notification'), findsOneWidget);
      expect(find.text('Updates'), findsOneWidget);
      expect(find.text('Latest'), findsOneWidget);
      expect(find.byTooltip('Mark all notifications read'), findsOneWidget);
      expect(find.byTooltip('Mark as read'), findsOneWidget);
      expect(find.byTooltip('Mark as unread'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
