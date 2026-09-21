import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/core/models.dart';
import 'package:flutter_member_app/src/core/secure_storage_service.dart';
import 'package:flutter_member_app/src/features/auth/auth_service.dart';
import 'package:flutter_member_app/src/features/auth/session_controller.dart';
import 'package:flutter_member_app/src/features/member/member_home_screen.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/intl.dart';
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
      final today = DateTime.now().toIso8601String().split('T').first;
      return ChangeNotifierProvider<MemberSessionController>.value(
        value: session,
        child: MaterialApp(
          home: MediaQuery(
            data: MediaQueryData(
              size: size,
              textScaler: TextScaler.linear(2),
              disableAnimations: true,
            ),
            child: MemberHomeScreen(
              storePreviewData: {
                'context': {
                  'user_state': 'gym_member',
                  'member_profile': {'member_onboarding_completed': true},
                  'user': {'member_onboarding_completed': true},
                  'capabilities': <String, dynamic>{},
                  'selected_gym_id': 11,
                  'gym_relationships': [
                    {
                      'gym_id': 11,
                      'gym': {
                        'name': 'Atlas Performance Club With A Long Name',
                      },
                      'branch': {
                        'name': 'Downtown Strength Studio',
                        'city': 'Bengaluru',
                      },
                      'membership': <String, dynamic>{},
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
                'plans': const [
                  {
                    'name': 'Assigned Plan Only',
                    'status': 'active',
                    'goal': 'Strength',
                  },
                ],
                'history': [
                  {
                    'session_date': today,
                    'plan_name': 'Actual History Workout',
                    'duration_minutes': 42,
                    'estimated_kcal': 310,
                    'status': 'completed',
                  },
                ],
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
    expect(find.text('AP'), findsOneWidget);
    expect(find.byKey(const ValueKey('member-unified-hero')), findsOneWidget);
    expect(find.text('ACTIVE GYM'), findsOneWidget);
    expect(find.text("TODAY'S READINESS"), findsOneWidget);
    expect(find.text('Membership active'), findsNothing);
    expect(find.text('Profile ready'), findsNothing);
    expect(find.text('Switch gym'), findsOneWidget);
    expect(find.text('More ways to manage your fitness'), findsNothing);
    expect(find.text('Coach connection'), findsNothing);
    expect(find.text('Today at a glance'), findsNothing);
    expect(find.text('Your next session'), findsNothing);
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
      find.byKey(const ValueKey('weekly-activity-chart')),
      350,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Training rhythm'), findsOneWidget);
    expect(find.bySemanticsLabel(RegExp(r'.*, 1 session')), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Actual History Workout'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    expect(
      find.text(
        '${DateFormat('MMM d').format(DateTime.now())} · 42 min · 310 kcal',
      ),
      findsOneWidget,
    );
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

  testWidgets('home opens the active workout in the shared preview sheet', (
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
            id: 7,
            name: 'Preview Member',
            email: 'preview@example.com',
            activeRole: 'member',
            isActive: true,
            roles: ['member'],
          )
          ..token = 'preview-token';

    await tester.pumpWidget(
      ChangeNotifierProvider<MemberSessionController>.value(
        value: session,
        child: MaterialApp(
          home: MediaQuery(
            data: const MediaQueryData(
              size: Size(375, 812),
              textScaler: TextScaler.linear(1.2),
              disableAnimations: true,
            ),
            child: const MemberHomeScreen(
              storePreviewData: {
                'context': {
                  'user_state': 'gym_member',
                  'member_profile': {'member_onboarding_completed': true},
                  'user': {'member_onboarding_completed': true},
                  'capabilities': <String, dynamic>{},
                  'gym_relationships': <Map<String, dynamic>>[],
                },
                'plans': [
                  {
                    'name': 'Assigned Plan Only',
                    'status': 'active',
                    'goal': 'Strength',
                  },
                ],
              },
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Open workout'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Open workout'));
    await tester.pumpAndSettle();

    expect(find.text('PLAN PREVIEW'), findsOneWidget);
    expect(find.text('Assigned Plan Only'), findsWidgets);
    expect(find.text('Continue to workout'), findsOneWidget);
    expect(find.text('Schedule details unavailable'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('independent member gets a purposeful personal workspace', (
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
            id: 2,
            name: 'Independent Member',
            email: 'independent@example.com',
            activeRole: 'member',
            isActive: true,
            roles: ['member'],
          )
          ..token = 'preview-token';

    await tester.pumpWidget(
      ChangeNotifierProvider<MemberSessionController>.value(
        value: session,
        child: MaterialApp(
          home: MediaQuery(
            data: const MediaQueryData(
              size: Size(375, 812),
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
                  'gym_relationships': <Map<String, dynamic>>[],
                },
              },
            ),
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.byKey(const ValueKey('member-unified-hero')), findsOneWidget);
    expect(
      find.byKey(const ValueKey('personal-workspace-icon')),
      findsOneWidget,
    );
    expect(find.text('INDEPENDENT TRAINING'), findsOneWidget);
    expect(find.text('Your personal fitness space'), findsOneWidget);
    expect(find.text('ACTIVE GYM'), findsNothing);
    expect(find.text('Switch gym'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('train tab keeps the workout decision focused and accessible', (
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
            id: 3,
            name: 'Train Member',
            email: 'train@example.com',
            activeRole: 'member',
            isActive: true,
            roles: ['member'],
          )
          ..token = 'preview-token';

    Widget buildTrain(Size size) {
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
              initialIndex: 1,
              storePreviewData: {
                'context': {
                  'user_state': 'gym_member',
                  'member_profile': {'member_onboarding_completed': true},
                  'user': {'member_onboarding_completed': true},
                  'capabilities': {'workout_day_selection': true},
                  'selected_gym_id': 11,
                },
                'plans': [
                  {
                    'id': 101,
                    'name': 'Strength Foundation',
                    'status': 'active',
                    'goal': 'Build strength',
                    'estimated_session_minutes': 45,
                    'days': [
                      {'id': 1001, 'day_number': 1, 'label': 'Upper body'},
                      {'id': 1002, 'day_number': 2, 'label': 'Lower body'},
                    ],
                  },
                  {
                    'id': 102,
                    'name': 'Mobility Reset',
                    'status': 'active',
                    'goal': 'Move better',
                    'estimated_session_minutes': 25,
                    'days': [
                      {'id': 1003, 'day_number': 1, 'label': 'Full body'},
                    ],
                  },
                ],
                'history': [
                  {
                    'id': 501,
                    'session_date': '2026-09-19',
                    'plan_name': 'Strength Foundation',
                    'duration_minutes': 45,
                    'status': 'completed',
                  },
                ],
              },
            ),
          ),
        ),
      );
    }

    await tester.pumpWidget(buildTrain(const Size(375, 812)));
    await tester.pump();

    expect(find.text('Strength Foundation'), findsOneWidget);
    expect(find.text('Mobility Reset'), findsNothing);
    expect(find.text('Logged'), findsNothing);
    expect(find.bySemanticsLabel('Workout history'), findsOneWidget);
    expect(
      find.bySemanticsLabel(RegExp('Open workout history')),
      findsOneWidget,
    );

    await tester.tap(find.text('Change'));
    await tester.pumpAndSettle();
    expect(find.text('Choose a workout plan'), findsOneWidget);
    expect(find.text('Strength Foundation'), findsWidgets);
    expect(find.text('Mobility Reset'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('Mobility Reset'));
    await tester.pumpAndSettle();
    expect(find.text('Mobility Reset'), findsOneWidget);
    expect(find.text('Strength Foundation'), findsNothing);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(buildTrain(const Size(812, 375)));
    await tester.pump();
    expect(tester.takeException(), isNull);
  });
}
