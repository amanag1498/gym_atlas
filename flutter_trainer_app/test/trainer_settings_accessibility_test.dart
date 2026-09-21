import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/core/api_client.dart';
import 'package:flutter_trainer_app/src/core/models.dart';
import 'package:flutter_trainer_app/src/core/token_storage.dart';
import 'package:flutter_trainer_app/src/features/auth/auth_service.dart';
import 'package:flutter_trainer_app/src/features/auth/session_controller.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_settings_screen.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('trainer settings uses the member layout on compact screens', (
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
    var viewedProfile = false;

    await tester.pumpWidget(
      ChangeNotifierProvider<TrainerSessionController>.value(
        value: session,
        child: MaterialApp(
          home: MediaQuery(
            data: const MediaQueryData(
              size: Size(320, 700),
              textScaler: TextScaler.linear(1.5),
              disableAnimations: true,
            ),
            child: TrainerSettingsScreen(
              onViewProfile: () async => viewedProfile = true,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('trainer@example.com'), findsOneWidget);
    expect(find.text('View'), findsOneWidget);
    expect(find.text('Help & Legal'), findsOneWidget);
    expect(find.text('Account Controls'), findsOneWidget);
    expect(find.text('Cloud'), findsNothing);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('View'));
    await tester.pump();
    expect(viewedProfile, isTrue);

    await tester.scrollUntilVisible(
      find.text('Delete Account'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Delete Account'));
    await tester.pumpAndSettle();
    expect(find.text('Review account deletion?'), findsOneWidget);
    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
}
