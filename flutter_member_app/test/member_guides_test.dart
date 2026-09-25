import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/guides.dart';
import 'package:provider/provider.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/core/models.dart';
import 'package:flutter_member_app/src/features/auth/auth_service.dart';
import 'package:flutter_member_app/src/features/auth/session_controller.dart';
import 'package:flutter_member_app/src/features/member/member_home_screen.dart';
import 'package:flutter_member_app/src/features/member/member_settings_screen.dart';
import 'package:flutter_member_app/src/core/secure_storage_service.dart';
import 'package:flutter_member_app/src/features/member/member_repository.dart';

void main() {
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));
  testWidgets(
    'member overview targets exist and settings lists feature replays',
    (tester) async {
      final client = MemberApiClient();
      final session =
          MemberSessionController(
              storage: const SecureStorageService(),
              apiClient: client,
              authService: AuthService(client),
            )
            ..user = const MemberUser(
              id: 1,
              name: 'Atlas User',
              email: 'user@example.com',
              activeRole: 'member',
              isActive: true,
              roles: ['member'],
            )
            ..token = 'preview-token';
      Widget app(Widget screen) =>
          ChangeNotifierProvider<MemberSessionController>.value(
            value: session,
            child: MaterialApp(
              builder: (_, child) => GuideScope(
                account: 'member:1',
                guides: memberGuides,
                child: child!,
              ),
              home: screen,
            ),
          );
      await tester.pumpWidget(
        app(
          const MemberHomeScreen(
            storePreviewData: {
              'context': {
                'user_state': 'gym_member',
                'member_profile': {'member_onboarding_completed': true},
                'user': {'member_onboarding_completed': true},
                'capabilities': <String, dynamic>{},
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
      for (final step in memberGuides.first.steps) {
        expect(targets, contains('${memberGuides.first.id}/${step.target}'));
      }
      // Store previews must never be interrupted by first-login guides.
      expect(find.byType(GuideOverlay), findsNothing);
      await tester.pumpWidget(
        app(
          MemberSettingsScreen(
            repository: MemberRepository(client),
            session: session,
            onOpenProfile: () async {},
            onOpenMembership: () async {},
            onOpenAttendance: () async {},
            onPreferencesChanged: () async {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.pump(const Duration(milliseconds: 700));
      await tester.pumpAndSettle();
      if (find.byType(GuideOverlay).evaluate().isNotEmpty) {
        await tester.tap(find.text('Skip'));
        await tester.pumpAndSettle();
      }
      await tester.tap(find.text('Replay guides'));
      await tester.pumpAndSettle();
      for (final guide in memberGuides) {
        expect(find.text(guide.title), findsAtLeastNWidgets(1));
      }
      expect(find.text('Replay all guides'), findsOneWidget);
      expect(find.text('Turn guides off'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
