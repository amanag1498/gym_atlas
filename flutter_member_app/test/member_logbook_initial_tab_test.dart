import 'package:flutter/material.dart';
import 'package:flutter_member_app/src/core/api_client.dart';
import 'package:flutter_member_app/src/features/member/member_logbook_screen.dart';
import 'package:flutter_member_app/src/features/member/member_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('workout history entry opens the History tab', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: MemberLogbookScreen(
          repository: _FakeMemberRepository(),
          initialTabIndex: 1,
        ),
      ),
    );
    await tester.pumpAndSettle();

    final tabView = find.byType(TabBarView);
    expect(tabView, findsOneWidget);
    expect(DefaultTabController.of(tester.element(tabView)).index, 1);
    expect(find.text('History'), findsOneWidget);
  });
}

class _FakeMemberRepository extends MemberRepository {
  _FakeMemberRepository() : super(MemberApiClient());

  @override
  Future<Map<String, dynamic>> fetchWorkoutHistory({
    int page = 1,
    int perPage = 15,
  }) async => {
    'data': <Map<String, dynamic>>[],
    'meta': const {'current_page': 1, 'last_page': 1},
  };

  @override
  Future<Map<String, dynamic>> fetchPersonalRecords({
    int page = 1,
    int perPage = 15,
  }) async => {
    'data': const <String, dynamic>{'personal_records': <dynamic>[]},
    'meta': const {'current_page': 1, 'last_page': 1},
  };
}
