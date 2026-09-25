import 'dart:async';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:gym_flutter_core/guides.dart';

import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/features/member/member_repository.dart';
import 'package:flutter_member_app/src/features/member/member_workout_book_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Workout Book guide launches after data loads and skips safely', (
    tester,
  ) async {
    FlutterSecureStorage.setMockInitialValues({});
    await tester.pumpWidget(
      GuideScope(
        account: 'member:guide-test',
        guides: memberGuides,
        child: _buildScreen(_WorkoutBuilderRepository()),
      ),
    );
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 700));
    await tester.pumpAndSettle();
    expect(find.text('Find your next workout'), findsOneWidget);
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('Browse and compare'), findsOneWidget);
    await tester.tap(find.text('Skip'));
    await tester.pumpAndSettle();
    expect(find.byType(GuideOverlay), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('builder loads the next exercise page from the picker', (
    tester,
  ) async {
    final pageTwoGate = Completer<void>();
    final repository = _WorkoutBuilderRepository(pageTwoGate: pageTwoGate);
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
    final picker = find.byType(DropdownMenu<int>);
    expect(picker, findsOneWidget);
    await tester.ensureVisible(picker);
    await tester.pumpAndSettle();
    await tester.tap(picker);
    await tester.pumpAndSettle();
    expect(find.text('Load more exercise results'), findsOneWidget);

    await tester.tap(find.text('Load more exercise results'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    await tester.pump(const Duration(milliseconds: 200));
    expect(find.text('Bench Press • Chest'), findsOneWidget);
    pageTwoGate.complete();
    await tester.pumpAndSettle();
    expect(repository.exercisePages, contains(2));

    // Pagination must leave the picker open so the next result is selectable.
    expect(find.text('Back Squat • Quads'), findsWidgets);
    await tester.tap(find.text('Back Squat • Quads'));
    await tester.pumpAndSettle();
    expect(find.text('Back Squat • Quads'), findsWidgets);
  });

  testWidgets(
    'group type, rounds, and transition are visible before grouping',
    (tester) async {
      final repository = _WorkoutBuilderRepository();
      tester.view.physicalSize = const Size(375, 812);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(_buildScreen(repository));
      await tester.pumpAndSettle();
      await tester.tap(find.bySemanticsLabel('Builder'));
      await tester.pumpAndSettle();

      final picker = find.byType(DropdownMenu<int>);
      for (var index = 0; index < 8 && picker.evaluate().isEmpty; index++) {
        await tester.drag(
          find.byKey(const ValueKey('workout-builder-scroll')),
          const Offset(0, -420),
        );
        await tester.pumpAndSettle();
      }
      await tester.ensureVisible(picker);
      await tester.pumpAndSettle();
      await tester.tap(picker);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bench Press • Chest').last);
      await tester.pumpAndSettle();

      final groupType = find.byWidgetPredicate(
        (widget) =>
            widget is DropdownButtonFormField<String> &&
            widget.decoration.labelText == 'Group type',
      );
      for (var index = 0; index < 12 && groupType.evaluate().isEmpty; index++) {
        await tester.drag(
          find.byKey(const ValueKey('workout-builder-scroll')),
          const Offset(0, -420),
        );
        await tester.pumpAndSettle();
      }
      expect(groupType, findsOneWidget);
      expect(find.widgetWithText(TextField, 'Rounds'), findsOneWidget);
      expect(find.widgetWithText(TextField, 'Transition sec'), findsOneWidget);
      await tester.enterText(
        find.widgetWithText(TextField, 'Group label (optional)'),
        'A',
      );
      await tester.enterText(find.widgetWithText(TextField, 'Rounds'), '4');
      await tester.enterText(
        find.widgetWithText(TextField, 'Transition sec'),
        '25',
      );
      tester.testTextInput.hide();
      await tester.ensureVisible(groupType);
      await tester.pumpAndSettle();
      await tester.tap(groupType);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Circuit').last);
      await tester.pumpAndSettle();

      final addExercise = find.textContaining('Add exercise to');
      await tester.ensureVisible(addExercise);
      await tester.tap(addExercise);
      await tester.pumpAndSettle();
      expect(
        find.textContaining('A1 circuit • 4 rounds • 25s transition'),
        findsOneWidget,
      );
      _expectNoFlutterException(tester);
    },
  );

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
      expect(find.textContaining('4 rounds • 25s transition'), findsOneWidget);
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
      for (
        var index = 0;
        index < 12 && find.text('Exercise group (optional)').evaluate().isEmpty;
        index++
      ) {
        await tester.drag(
          find.byKey(const ValueKey('workout-builder-scroll')),
          const Offset(0, -420),
        );
        await tester.pumpAndSettle();
      }
      expect(find.text('Exercise group (optional)'), findsOneWidget);
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
  _WorkoutBuilderRepository({
    this.includeSavedExercise = false,
    this.pageTwoGate,
  }) : super(MemberApiClient());

  final bool includeSavedExercise;
  final Completer<void>? pageTwoGate;

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
                'group_key': 'A',
                'group_type': 'circuit',
                'group_order': 1,
                'group_rounds': 4,
                'transition_seconds': 25,
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
      await pageTwoGate?.future;
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
