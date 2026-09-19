import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/core/models.dart';
import 'package:flutter_member_app/src/core/secure_storage_service.dart';
import 'package:flutter_member_app/src/features/auth/auth_service.dart';
import 'package:flutter_member_app/src/features/auth/session_controller.dart';
import 'package:flutter_member_app/src/features/member/member_home_screen.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('member home supports compact screens and large text', (
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

    Widget buildHome(Size size) {
      return ChangeNotifierProvider<MemberSessionController>.value(
        value: session,
        child: MaterialApp(
          home: MediaQuery(
            data: MediaQueryData(
              size: size,
              textScaler: TextScaler.linear(2),
              disableAnimations: true,
            ),
            child: const MemberHomeScreen(
              storePreviewData: {
                'context': {
                  'user_state': 'independent_user',
                  'member_profile': {'member_onboarding_completed': true},
                  'user': {'member_onboarding_completed': true},
                  'capabilities': <String, dynamic>{},
                  'selected_gym_id': 11,
                  'gym_relationships': [
                    {
                      'gym_id': 11,
                      'gym': {
                        'name': 'Atlas Performance Club With A Long Name',
                        'logo_url': '/storage/gyms/atlas-logo.png',
                      },
                      'branch': {
                        'name': 'Downtown Strength Studio',
                        'city': 'Bengaluru',
                      },
                      'membership': {
                        'plan': {'name': 'Performance Plus'},
                      },
                      'assigned_trainer': {'name': 'Coach With A Long Name'},
                    },
                    {
                      'gym_id': 12,
                      'gym': {'name': 'Second Gym'},
                      'membership': {
                        'plan': {'name': 'Standard'},
                      },
                    },
                  ],
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
    expect(find.text('Gyms'), findsOneWidget);
    expect(find.byKey(const ValueKey('active-gym-logo')), findsOneWidget);
    expect(find.text('ACTIVE GYM'), findsOneWidget);
    expect(find.text('Switch gym'), findsOneWidget);
    expect(find.text('More ways to manage your fitness'), findsNothing);
    expect(find.text('Coach connection'), findsNothing);
    final gymsAction = find.bySemanticsLabel('Gyms');
    expect(gymsAction, findsOneWidget);
    expect(
      tester.getSemantics(gymsAction),
      matchesSemantics(
        label: 'Gyms',
        isButton: true,
        hasTapAction: true,
        hasSelectedState: true,
        isSelected: false,
      ),
    );
    expect(tester.takeException(), isNull);
    await tester.scrollUntilVisible(
      find.text('Events and bookings'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Upcoming events'), findsOneWidget);
    expect(find.text('Classes, sessions, and your bookings'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Recent training'),
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
