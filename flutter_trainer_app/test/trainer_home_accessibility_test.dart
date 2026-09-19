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
      find.text('Unread conversations'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pump();
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(buildHome(const Size(812, 375)));
    await tester.pump();
    expect(tester.takeException(), isNull);
  });
}
