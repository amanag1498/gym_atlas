import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/features/member/member_assigned_workout_screen.dart';
import 'package:flutter_member_app/src/features/member/member_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('assigned workout has one clear start action at large text', (
    tester,
  ) async {
    final repository = _AssignedWorkoutRepository();

    Widget buildScreen(Size size) {
      return MaterialApp(
        home: MediaQuery(
          data: MediaQueryData(
            size: size,
            textScaler: TextScaler.linear(2),
            disableAnimations: true,
          ),
          child: MemberAssignedWorkoutScreen(
            repository: repository,
            initialPlans: const [_AssignedWorkoutRepository.plan],
            onStartAssignedWorkout: (_) {},
            onOpenWorkoutBook: () {},
          ),
        ),
      );
    }

    await tester.pumpWidget(buildScreen(const Size(375, 812)));
    await tester.pumpAndSettle();

    expect(find.text('Start assigned workout'), findsOneWidget);
    expect(find.text('Open tracker'), findsNothing);
    expect(find.bySemanticsLabel('Back to training'), findsOneWidget);
    expect(find.bySemanticsLabel('Workout book'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(buildScreen(const Size(812, 375)));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
}

class _AssignedWorkoutRepository extends MemberRepository {
  _AssignedWorkoutRepository() : super(MemberApiClient());

  static const Map<String, dynamic> plan = {
    'id': 101,
    'name': 'Strength Foundation With A Long Plan Name',
    'status': 'active',
    'goal': 'Build strength safely and consistently',
    'days': [
      {
        'id': 1001,
        'day_number': 1,
        'label': 'Upper body strength',
        'focus': 'Chest, back, and shoulders',
        'exercises': [
          {
            'exercise_id': 51,
            'sets': 4,
            'reps': '8-10',
            'rest_seconds': 60,
            'exercise': {'id': 51, 'name': 'Bench press'},
          },
        ],
      },
      {
        'id': 1002,
        'day_number': 2,
        'label': 'Lower body strength',
        'focus': 'Quads and posterior chain',
        'exercises': <Map<String, dynamic>>[],
      },
    ],
  };

  @override
  Future<Map<String, dynamic>> fetchWorkoutPlans({
    int? relationshipId,
    int page = 1,
    int perPage = 15,
  }) async => {
    'data': [plan],
    'meta': {
      'pagination': {
        'current_page': 1,
        'last_page': 1,
        'per_page': 15,
        'total': 1,
      },
    },
  };

  @override
  Future<Map<String, dynamic>> fetchWorkoutPlan(int workoutPlanId) async => {
    'data': plan,
  };
}
