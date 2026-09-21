import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/core/api_client.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_profile_overview_screen.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_repository.dart';

void main() {
  testWidgets('trainer profile opens overview, then a separate editor', (
    tester,
  ) async {
    final repository = _ProfileRepository();
    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: const MediaQueryData(
            size: Size(320, 700),
            textScaler: TextScaler.linear(1.5),
            disableAnimations: true,
          ),
          child: TrainerProfileOverviewScreen(repository: repository),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Profile Overview'), findsOneWidget);
    expect(find.text('Personal Details'), findsOneWidget);
    expect(find.text('Edit profile'), findsOneWidget);
    expect(find.byType(TextFormField), findsNothing);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('Edit profile'));
    await tester.pumpAndSettle();
    expect(find.text('Edit Profile'), findsOneWidget);
    expect(find.text('Add photo'), findsOneWidget);
    expect(find.text('Save changes'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('Add photo'));
    await tester.pumpAndSettle();
    expect(find.text('Choose profile photo'), findsOneWidget);
    expect(find.text('Gallery'), findsOneWidget);
    expect(find.text('Camera'), findsOneWidget);
    await tester.tapAt(const Offset(5, 5));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Save changes'));
    await tester.pumpAndSettle();
    expect(repository.saved, isTrue);
    expect(find.text('Profile Overview'), findsOneWidget);
    expect(find.text('Edit Profile'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

class _ProfileRepository extends TrainerRepository {
  _ProfileRepository() : super(TrainerApiClient());

  bool saved = false;

  @override
  Future<Map<String, dynamic>> fetchProfile() async => {
    'data': {
      'trainer_user': {
        'name': 'Atlas Trainer',
        'email': 'trainer@example.com',
        'phone': '+15551234567',
        'gender': 'female',
        'date_of_birth': '1990-01-01',
      },
      'trainer_profile': {
        'id': 2,
        'profile_photo_url': '',
        'bio': 'Strength coach',
        'specializations': ['Strength'],
        'experience_years': 5,
        'certifications': [],
        'languages': ['English'],
        'assigned_gym': {'name': 'Atlas Gym'},
        'assigned_branch': {'name': 'Main'},
        'profile_completion_percentage': 75,
        'verification_status': 'pending',
        'verification_submitted': true,
      },
    },
  };

  @override
  Future<Map<String, dynamic>> updateProfile(
    Map<String, dynamic> payload,
  ) async {
    saved = true;
    return {'data': payload};
  }
}
