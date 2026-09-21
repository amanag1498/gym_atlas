import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/features/member/member_repository.dart';
import 'package:flutter_member_app/src/features/member/member_workout_book_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('builder loads the next exercise page from the picker', (
    tester,
  ) async {
    final repository = _WorkoutBuilderRepository();
    tester.view.physicalSize = const Size(375, 812);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(_buildScreen(repository));
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel('Builder'));
    await tester.pumpAndSettle();

    expect(find.text('Save to My Plans'), findsOneWidget);
    for (
      var index = 0;
      index < 8 && find.text('Bench Press • Chest').evaluate().isEmpty;
      index++
    ) {
      await tester.drag(
        find.byKey(const ValueKey('workout-builder-scroll')),
        const Offset(0, -420),
      );
      await tester.pumpAndSettle();
    }
    final picker = find.byKey(const ValueKey('member-builder-exercise-picker'));
    expect(picker, findsOneWidget);
    await tester.ensureVisible(picker);
    await tester.pumpAndSettle();
    await tester.tap(picker);
    await tester.pumpAndSettle();
    expect(find.text('Load more exercise results'), findsOneWidget);

    await tester.tap(find.text('Load more exercise results'));
    await tester.pumpAndSettle();
    expect(repository.exercisePages, contains(2));

    // Pagination must leave the picker open so the next result is selectable.
    expect(find.text('Back Squat • Quads'), findsOneWidget);
    await tester.tap(find.text('Back Squat • Quads'));
    await tester.pumpAndSettle();
    expect(find.text('Back Squat • Quads'), findsOneWidget);
  });

  testWidgets(
    'editing keeps a saved exercise outside the loaded catalog page',
    (tester) async {
      final repository = _WorkoutBuilderRepository(includeSavedExercise: true);
      tester.view.physicalSize = const Size(375, 812);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(_buildScreen(repository));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Edit').first);
      await tester.tap(find.text('Edit').first);
      await tester.pumpAndSettle();

      for (
        var index = 0;
        index < 12 && find.text('Back Squat').evaluate().isEmpty;
        index++
      ) {
        await tester.drag(
          find.byKey(const ValueKey('workout-builder-scroll')),
          const Offset(0, -420),
        );
        await tester.pumpAndSettle();
      }
      expect(find.text('Back Squat'), findsWidgets);
      _expectNoFlutterException(tester);
    },
  );

  testWidgets(
    'workout book tabs remain usable on a compact large-text screen',
    (tester) async {
      final repository = _WorkoutBuilderRepository();
      tester.view.physicalSize = const Size(320, 700);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(
        _buildScreen(
          repository,
          mediaData: const MediaQueryData(
            size: Size(320, 700),
            textScaler: TextScaler.linear(1.5),
            disableAnimations: true,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Portable Strength'), findsOneWidget);
      _expectNoFlutterException(tester);

      await tester.tap(find.bySemanticsLabel('Catalog'));
      await tester.pumpAndSettle();
      expect(find.text('Show programs'), findsOneWidget);
      _expectNoFlutterException(tester);

      await tester.tap(find.bySemanticsLabel('Builder'));
      await tester.pumpAndSettle();
      expect(find.text('Save to My Plans'), findsOneWidget);
      _expectNoFlutterException(tester);
    },
  );
}

void _expectNoFlutterException(WidgetTester tester) {
  final exception = tester.takeException();
  expect(
    exception,
    isNull,
    reason: exception is FlutterError
        ? exception.toStringDeep()
        : exception?.toString(),
  );
}

Widget _buildScreen(
  MemberRepository repository, {
  MediaQueryData mediaData = const MediaQueryData(
    size: Size(375, 812),
    disableAnimations: true,
  ),
}) {
  return MaterialApp(
    home: MediaQuery(
      data: mediaData,
      child: MemberWorkoutBookScreen(
        repository: repository,
        onStartPlan: (_) {},
      ),
    ),
  );
}

class _WorkoutBuilderRepository extends MemberRepository {
  _WorkoutBuilderRepository({this.includeSavedExercise = false})
    : super(MemberApiClient());

  final bool includeSavedExercise;

  final List<int> exercisePages = <int>[];

  static const Map<String, dynamic> _plan = {
    'id': 41,
    'name': 'Portable Strength',
    'goal': 'Build strength',
    'plan_origin': 'member_custom',
    'is_member_editable': true,
    'total_workout_days': 3,
    'total_exercises': 12,
    'estimated_session_minutes': 45,
  };

  @override
  Future<Map<String, dynamic>> fetchWorkoutBooks({
    Map<String, dynamic>? queryParameters,
  }) async => _page(const <Map<String, dynamic>>[]);

  @override
  Future<Map<String, dynamic>> fetchRecommendedWorkoutBooks({
    Map<String, dynamic>? queryParameters,
  }) async => _page(const <Map<String, dynamic>>[]);

  @override
  Future<Map<String, dynamic>> fetchWorkoutPlans({
    int? relationshipId,
    int page = 1,
    int perPage = 15,
  }) async => _page([
    if (includeSavedExercise)
      {
        ..._plan,
        'days': const [
          {
            'day_number': 1,
            'label': 'Leg day',
            'exercises': [
              {
                'exercise_id': 2,
                'sets': 3,
                'reps': '10',
                'exercise': {
                  'id': 2,
                  'name': 'Back Squat',
                  'body_part': 'quads',
                  'body_part_label': 'Quads',
                },
              },
            ],
          },
        ],
      }
    else
      _plan,
  ]);

  @override
  Future<Map<String, dynamic>> fetchWorkoutExercises({
    Map<String, dynamic>? queryParameters,
  }) async {
    final page = (queryParameters?['page'] as num?)?.toInt() ?? 1;
    exercisePages.add(page);
    if (page == 2) {
      return _page(
        const [
          {
            'id': 2,
            'name': 'Back Squat',
            'body_part': 'quads',
            'body_part_label': 'Quads',
            'default_tracking_mode': 'reps',
          },
        ],
        page: 2,
        lastPage: 2,
      );
    }
    return _page(const [
      {
        'id': 1,
        'name': 'Bench Press',
        'body_part': 'chest',
        'body_part_label': 'Chest',
        'default_tracking_mode': 'reps',
      },
    ], lastPage: 2);
  }

  @override
  Future<Map<String, dynamic>> fetchEquipmentProfiles() async => const {
    'data': {'profiles': <Map<String, dynamic>>[]},
  };

  static Map<String, dynamic> _page(
    List<Map<String, dynamic>> items, {
    int page = 1,
    int lastPage = 1,
  }) => {
    'data': items,
    'meta': {
      'pagination': {
        'current_page': page,
        'last_page': lastPage,
        'per_page': 100,
        'total': items.length,
      },
    },
  };
}
