import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/features/member/member_repository.dart';
import 'package:flutter_member_app/src/features/member/member_workout_book_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('My Plans exposes sharing on each workout plan', (tester) async {
    final repository = _WorkoutBookRepository();

    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: const MediaQueryData(
            size: Size(375, 812),
            textScaler: TextScaler.linear(1.4),
            disableAnimations: true,
          ),
          child: MemberWorkoutBookScreen(
            repository: repository,
            onStartPlan: (_) {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('My Plans'), findsOneWidget);
    expect(find.text('Portable Strength'), findsOneWidget);
    expect(find.text('Share'), findsOneWidget);
    expect(find.text('Choose a plan to share'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

class _WorkoutBookRepository extends MemberRepository {
  _WorkoutBookRepository() : super(MemberApiClient());

  static const Map<String, dynamic> _emptyPage = {
    'data': <Map<String, dynamic>>[],
    'meta': {
      'pagination': {
        'current_page': 1,
        'last_page': 1,
        'per_page': 15,
        'total': 0,
      },
    },
  };

  static const Map<String, dynamic> _plans = {
    'data': [
      {
        'id': 41,
        'name': 'Portable Strength',
        'goal': 'Build strength',
        'plan_origin': 'member_created',
        'is_member_editable': true,
        'total_workout_days': 3,
        'total_exercises': 12,
        'estimated_session_minutes': 45,
      },
    ],
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
  Future<Map<String, dynamic>> fetchWorkoutBooks({
    Map<String, dynamic>? queryParameters,
  }) async => _emptyPage;

  @override
  Future<Map<String, dynamic>> fetchRecommendedWorkoutBooks({
    Map<String, dynamic>? queryParameters,
  }) async => _emptyPage;

  @override
  Future<Map<String, dynamic>> fetchWorkoutPlans({
    int? relationshipId,
    int page = 1,
    int perPage = 15,
  }) async => _plans;

  @override
  Future<Map<String, dynamic>> fetchWorkoutExercises({
    Map<String, dynamic>? queryParameters,
  }) async => _emptyPage;

  @override
  Future<Map<String, dynamic>> fetchEquipmentProfiles() async => const {
    'data': {'profiles': <Map<String, dynamic>>[]},
  };
}
