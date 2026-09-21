import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/features/member/member_repository.dart';
import 'package:flutter_member_app/src/features/member/shared_workout_plan_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('shared workout previews and saves on compact screens', (
    tester,
  ) async {
    final repository = _SharedWorkoutRepository();

    Widget buildScreen(Size size) {
      return MaterialApp(
        home: MediaQuery(
          data: MediaQueryData(
            size: size,
            textScaler: TextScaler.linear(1.5),
            disableAnimations: true,
          ),
          child: SharedWorkoutPlanScreen(
            token: _SharedWorkoutRepository.token,
            repository: repository,
          ),
        ),
      );
    }

    await tester.pumpWidget(buildScreen(const Size(375, 812)));
    await tester.pumpAndSettle();

    expect(find.text('Portable Strength'), findsOneWidget);
    expect(find.text('Save to my workouts'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.drag(find.byType(ListView), const Offset(0, -420));
    await tester.pumpAndSettle();
    expect(find.text('Bench Press'), findsOneWidget);

    await tester.tap(find.text('Save to my workouts'));
    await tester.pumpAndSettle();

    expect(repository.savedToken, _SharedWorkoutRepository.token);
    expect(find.text('Open my workouts'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(buildScreen(const Size(812, 375)));
    await tester.pumpAndSettle();
    expect(find.text('Open my workouts'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

class _SharedWorkoutRepository extends MemberRepository {
  _SharedWorkoutRepository() : super(MemberApiClient());

  static const token = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
  String? savedToken;

  static const response = {
    'data': {
      'shared_by': {'name': 'Aman'},
      'snapshot': {
        'name': 'Portable Strength',
        'goal': 'Strength',
        'difficulty': 'Intermediate',
        'duration_weeks': 4,
        'days': [
          {
            'day_number': 1,
            'label': 'Push day',
            'exercises': [
              {'exercise_name': 'Bench Press', 'sets': 3, 'reps': '8-10'},
            ],
          },
        ],
      },
    },
  };

  @override
  Future<Map<String, dynamic>> fetchWorkoutPlanShare(String token) async =>
      response;

  @override
  Future<Map<String, dynamic>> adoptWorkoutPlanShare(
    String token, {
    String? name,
  }) async {
    savedToken = token;
    return const {
      'data': {'id': 99},
    };
  }
}
