import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/core/models.dart';
import 'package:flutter_member_app/src/core/secure_storage_service.dart';
import 'package:flutter_member_app/src/features/auth/auth_service.dart';
import 'package:flutter_member_app/src/features/auth/session_controller.dart';
import 'package:flutter_member_app/src/features/member/member_repository.dart';
import 'package:flutter_member_app/src/features/member/member_settings_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('settings remains usable on a compact screen with large text', (
    tester,
  ) async {
    final client = MemberApiClient();
    final session =
        MemberSessionController(
            storage: const SecureStorageService(),
            apiClient: client,
            authService: AuthService(client),
          )
          ..user = const MemberUser(
            id: 1,
            name: 'Atlas Member With A Long Name',
            email: 'member@example.com',
            activeRole: 'member',
            isActive: true,
            roles: ['member'],
          )
          ..token = 'preview-token';

    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: const MediaQueryData(
            size: Size(320, 700),
            textScaler: TextScaler.linear(1.5),
            disableAnimations: true,
          ),
          child: MemberSettingsScreen(
            repository: MemberRepository(client),
            session: session,
            onOpenProfile: _noop,
            onOpenMembership: _noop,
            onOpenAttendance: _noop,
            onPreferencesChanged: _noop,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('member@example.com'), findsOneWidget);
    expect(find.text('View'), findsOneWidget);
    expect(find.text('Profile Overview'), findsNothing);
    expect(find.text('Membership'), findsOneWidget);
    expect(find.text('Activity History'), findsOneWidget);
    expect(find.text('Progress & Reminders'), findsOneWidget);
    expect(find.text('Cloud'), findsNothing);
    expect(tester.takeException(), isNull);

    await tester.scrollUntilVisible(
      find.text('Delete Account'),
      350,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Delete Account'));
    await tester.pumpAndSettle();
    expect(find.text('Review account deletion?'), findsOneWidget);
    expect(find.text('Review deletion'), findsOneWidget);
    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Logout'),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Logout'));
    await tester.pumpAndSettle();
    expect(find.text('Sign out?'), findsOneWidget);
    expect(find.text('Stay signed in'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _noop() async {}
