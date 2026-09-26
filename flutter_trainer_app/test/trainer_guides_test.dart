import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/guides.dart';
import 'package:provider/provider.dart';
import 'package:flutter_trainer_app/src/core/api_client.dart';
import 'package:flutter_trainer_app/src/core/models.dart';
import 'package:flutter_trainer_app/src/features/auth/auth_service.dart';
import 'package:flutter_trainer_app/src/features/auth/session_controller.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_home_screen.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_settings_screen.dart';
import 'package:flutter_trainer_app/src/core/token_storage.dart';

void main() {
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));
  testWidgets(
    'trainer overview targets exist and settings hides guide replay controls',
    (tester) async {
      final client = TrainerApiClient();
      final session =
          TrainerSessionController(
              storage: const TrainerTokenStorage(),
              apiClient: client,
              authService: TrainerAuthService(client),
            )
            ..user = const TrainerUser(
              id: 1,
              name: 'Atlas User',
              email: 'user@example.com',
              activeRole: 'trainer',
              isActive: true,
              roles: ['trainer'],
              permissions: [],
            )
            ..token = 'preview-token';
      Widget app(Widget screen) =>
          ChangeNotifierProvider<TrainerSessionController>.value(
            value: session,
            child: MaterialApp(
              builder: (_, child) => GuideScope(
                account: 'trainer:1',
                guides: trainerGuides,
                child: child!,
              ),
              home: screen,
            ),
          );
      await tester.pumpWidget(
        app(
          const TrainerHomeScreen(
            storePreviewData: {
              'context': {
                'user': {'name': 'Coach', 'trainer_onboarding_completed': true},
                'trainer_profile': {
                  'id': 1,
                  'trainer_type': 'gym',
                  'verification_status': 'verified',
                },
              },
            },
          ),
        ),
      );
      await tester.pump(const Duration(seconds: 1));
      final targets = tester
          .widgetList<GuideTarget>(find.byType(GuideTarget))
          .map((target) => target.id)
          .toSet();
      for (final step in trainerGuides.first.steps) {
        expect(targets, contains('${trainerGuides.first.id}/${step.target}'));
      }
      // Store previews must never be interrupted by first-login guides.
      expect(find.byType(GuideOverlay), findsNothing);
      await tester.pumpWidget(
        app(TrainerSettingsScreen(onViewProfile: () async {})),
      );
      await tester.pumpAndSettle();
      await tester.pump(const Duration(milliseconds: 700));
      if (find.byType(GuideOverlay).evaluate().isNotEmpty) {
        await tester.tap(find.text('Skip'));
        await tester.pumpAndSettle();
      }
      expect(find.text('Replay guides'), findsNothing);
      expect(find.text('Replay all guides'), findsNothing);
      expect(find.text('Turn guides off'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
