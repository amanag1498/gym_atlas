import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_member_app/src/features/member/widgets/step_dashboard_widget.dart';

void main() {
  Widget buildHarness(Widget child) {
    return MaterialApp(home: Scaffold(body: child));
  }

  testWidgets('renders granted step metrics', (tester) async {
    await tester.pumpWidget(
      buildHarness(
        const StepDashboardWidget(
          steps: StepDashboardData(
            today: 8420,
            goal: 10000,
            progressPercent: 84,
            distanceKm: 6.4,
            calories: 332,
            streakDays: 5,
            lastSyncedAt: '2026-05-10T12:00:00Z',
          ),
          permissionStatus: 'granted',
          loading: false,
          onRefresh: _noop,
          onRequestPermission: _noop,
        ),
      ),
    );

    expect(find.text("Today's Steps"), findsOneWidget);
    expect(find.text('8,420'), findsOneWidget);
    expect(find.text('84% of 10,000'), findsOneWidget);
    expect(find.text('6.4 km'), findsOneWidget);
    expect(find.text('332'), findsOneWidget);
    expect(find.text('5 d'), findsOneWidget);
  });

  testWidgets('renders permission CTA when access is denied', (tester) async {
    await tester.pumpWidget(
      buildHarness(
        const StepDashboardWidget(
          steps: null,
          permissionStatus: 'denied',
          loading: false,
          onRefresh: _noop,
          onRequestPermission: _noop,
        ),
      ),
    );

    expect(find.text('Step access is denied'), findsOneWidget);
    expect(find.text('Allow Steps Access'), findsOneWidget);
  });
}

void _noop() {}
