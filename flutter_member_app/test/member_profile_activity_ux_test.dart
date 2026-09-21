import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/features/member/member_membership_screen.dart';
import 'package:flutter_member_app/src/features/member/member_profile_screen.dart';
import 'package:flutter_member_app/src/features/member/member_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('profile overview remains usable with large text', (
    tester,
  ) async {
    final repository = _ProfileActivityRepository();

    await tester.pumpWidget(
      _testApp(
        MemberProfileScreen(
          repository: repository,
          onProfileUpdated: () async {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Profile Overview'), findsOneWidget);
    expect(find.text('Edit profile'), findsOneWidget);
    expect(find.text('Personal Details'), findsOneWidget);
    expect(find.text('12 May 1994'), findsOneWidget);
    expect(find.text('Prefer not to say'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.scrollUntilVisible(
      find.text('Health Notes'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Sensitive training-safety information'), findsOneWidget);
    expect(find.text('Previous ankle sprain'), findsNothing);

    await tester.tap(find.text('Health Notes'));
    await tester.pumpAndSettle();
    expect(find.text('Previous ankle sprain'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('edit profile keeps primary save action available', (
    tester,
  ) async {
    final repository = _ProfileActivityRepository();

    await tester.pumpWidget(
      _testApp(
        MemberProfileScreen(
          repository: repository,
          onProfileUpdated: () async {},
          openEditOnLoad: true,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Edit Profile'), findsWidgets);
    expect(find.text('Save changes'), findsOneWidget);
    expect(find.text('Basic Details'), findsOneWidget);
    expect(find.text('Profile photo'), findsOneWidget);
    expect(find.text('Add photo'), findsOneWidget);
    expect(find.text('Body Metrics'), findsNothing);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('Add photo'));
    await tester.pumpAndSettle();
    expect(find.text('Choose profile photo'), findsOneWidget);
    expect(find.text('Gallery'), findsOneWidget);
    expect(find.text('Camera'), findsOneWidget);
    Navigator.of(tester.element(find.text('Gallery'))).pop();
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Body Metrics'),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Body Metrics'), findsOneWidget);
    expect(find.text('Save changes'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('profile refreshes when the editor closes without form save', (
    tester,
  ) async {
    final repository = _ProfileActivityRepository();
    var parentRefreshes = 0;

    await tester.pumpWidget(
      _testApp(
        MemberProfileScreen(
          repository: repository,
          onProfileUpdated: () async => parentRefreshes++,
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(repository.profileLoads, 1);

    await tester.tap(find.text('Edit profile'));
    await tester.pumpAndSettle();
    Navigator.of(tester.element(find.text('Save changes'))).pop();
    await tester.pumpAndSettle();

    expect(repository.profileLoads, 2);
    expect(parentRefreshes, 1);
    expect(tester.takeException(), isNull);
  });

  testWidgets('activity history keeps pagination with its records', (
    tester,
  ) async {
    final repository = _ProfileActivityRepository();

    await tester.pumpWidget(
      _testApp(MemberAttendanceScreen(repository: repository)),
    );
    await tester.pumpAndSettle();

    expect(find.text('Checked in today'), findsOneWidget);
    expect(find.text('2'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Recent Check-ins'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.drag(find.byType(Scrollable).first, const Offset(0, -260));
    await tester.pumpAndSettle();
    expect(find.textContaining('Koramangala'), findsOneWidget);
    expect(find.text('Koramangala • Biometric check-in'), findsOneWidget);
    expect(find.text('Load more visits'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('Load more visits'));
    await tester.pumpAndSettle();
    expect(repository.attendancePages, <int>[1, 2]);
    expect(find.textContaining('Indiranagar'), findsOneWidget);
    expect(find.text('Load more visits'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

Widget _testApp(Widget child) {
  return MaterialApp(
    home: MediaQuery(
      data: const MediaQueryData(
        size: Size(360, 760),
        textScaler: TextScaler.linear(1.35),
        disableAnimations: true,
      ),
      child: child,
    ),
  );
}

class _ProfileActivityRepository extends MemberRepository {
  _ProfileActivityRepository() : super(MemberApiClient());

  final List<int> attendancePages = <int>[];
  int profileLoads = 0;

  @override
  Future<Map<String, dynamic>> fetchProfile() async {
    profileLoads++;
    return <String, dynamic>{
      'data': <String, dynamic>{
        'name': 'Atlas Member With A Long Name',
        'email': 'member@example.com',
        'phone': '+91 98765 43210',
        'date_of_birth': '1994-05-12',
        'gender': 'prefer_not_to_say',
        'height_cm': 178,
        'weight_kg': 76,
        'experience_level': 'intermediate',
        'injuries_limitations': 'Previous ankle sprain',
        'medical_notes': 'No current restrictions',
        'fitness_goals': <Map<String, dynamic>>[
          <String, dynamic>{'id': 1, 'name': 'Build strength'},
        ],
        'available_fitness_goals': <Map<String, dynamic>>[
          <String, dynamic>{'id': 1, 'name': 'Build strength'},
        ],
        'current_gym': <String, dynamic>{'id': 4, 'name': 'Atlas Fitness'},
        'current_branch': <String, dynamic>{'id': 8, 'name': 'Koramangala'},
        'assigned_trainer': <String, dynamic>{'id': 3, 'name': 'Alex Coach'},
      },
    };
  }

  @override
  Future<Map<String, dynamic>> fetchContext() async => <String, dynamic>{
    'data': <String, dynamic>{
      'current_membership': <String, dynamic>{
        'status': 'active',
        'current_gym': <String, dynamic>{'id': 4, 'name': 'Atlas Fitness'},
      },
      'gym_relationships': <Map<String, dynamic>>[
        <String, dynamic>{'id': 1},
      ],
    },
  };

  @override
  Future<Map<String, dynamic>> fetchAttendanceHistory({
    int page = 1,
    int perPage = 15,
  }) async {
    attendancePages.add(page);
    final visit = <String, dynamic>{
      'id': page,
      'gym': <String, dynamic>{'id': 4, 'name': 'Atlas Fitness'},
      'branch': <String, dynamic>{
        'id': page,
        'name': page == 1 ? 'Koramangala' : 'Indiranagar',
      },
      'check_in_method': page == 1 ? 'biometric' : 'manual',
      'checked_in_at': '2026-09-${page == 1 ? '20' : '18'}T07:30:00Z',
    };
    return <String, dynamic>{
      'data': <Map<String, dynamic>>[visit],
      'meta': <String, dynamic>{
        'pagination': <String, dynamic>{
          'current_page': page,
          'last_page': 2,
          'total': 2,
        },
      },
    };
  }

  @override
  Future<Map<String, dynamic>> fetchAttendanceStatus() async =>
      <String, dynamic>{
        'data': <String, dynamic>{'enabled': true, 'checked_in_today': true},
      };

  @override
  Future<Map<String, dynamic>> fetchBiometricAttendanceProfile() async =>
      <String, dynamic>{
        'data': <String, dynamic>{
          'biometric_enabled': true,
          'biometric_registered': true,
          'message': 'Use the scanner at your gym entrance.',
        },
      };
}
