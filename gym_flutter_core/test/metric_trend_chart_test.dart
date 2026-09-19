import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/metric_trend_chart.dart';

void main() {
  testWidgets('renders a labelled trend and latest value', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MetricTrendChart(
            title: 'Weight trend',
            subtitle: 'Recent check-ins',
            unit: ' kg',
            accentColor: Colors.blue,
            goalValue: 78,
            points: [
              MetricChartPoint(
                label: '1 Aug',
                value: 82,
                timestamp: DateTime(2026, 8),
              ),
              MetricChartPoint(
                label: '3 Aug',
                value: 81.5,
                timestamp: DateTime(2026, 8, 3),
              ),
              MetricChartPoint(
                label: '8 Aug',
                value: 81,
                timestamp: DateTime(2026, 8, 8),
              ),
              MetricChartPoint(
                label: '18 Aug',
                value: 80.5,
                timestamp: DateTime(2026, 8, 18),
              ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Weight trend'), findsOneWidget);
    expect(find.text('80.5 kg'), findsOneWidget);
    expect(find.text('-1.5 kg overall'), findsOneWidget);
    expect(find.text('Goal: 78 kg'), findsOneWidget);
    expect(find.text('1 Aug'), findsOneWidget);
    expect(find.text('18 Aug'), findsOneWidget);
    expect(find.text('18 Aug  •  80.5 kg'), findsOneWidget);
    expect(find.text('Tap the chart to inspect a check-in.'), findsOneWidget);
    expect(find.byType(CustomPaint), findsWidgets);
  });

  testWidgets('shows an actionable empty state for one point', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: MetricTrendChart(
            title: 'Waist trend',
            unit: ' cm',
            accentColor: Colors.purple,
            points: [MetricChartPoint(label: '12 Aug', value: 88)],
          ),
        ),
      ),
    );

    expect(find.text('88 cm'), findsNWidgets(2));
    expect(find.text('First check-in saved'), findsOneWidget);
    expect(
      find.text('Add 3 more entries for a reliable trend line.'),
      findsOneWidget,
    );
  });

  testWidgets('uses an early-progress summary for sparse data', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: MetricTrendChart(
            title: 'Body-fat trend',
            unit: '%',
            accentColor: Colors.orange,
            points: [
              MetricChartPoint(label: '1 Aug', value: 24),
              MetricChartPoint(label: '8 Aug', value: 23.5),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Early progress'), findsOneWidget);
    expect(
      find.text('Add 2 more entries for a reliable trend line.'),
      findsOneWidget,
    );
    expect(find.text('Tap the chart to inspect a check-in.'), findsNothing);
  });

  testWidgets('fits a narrow phone with large text', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(375, 667);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetPhysicalSize);

    await tester.pumpWidget(
      MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: const TextScaler.linear(1.8)),
          child: child!,
        ),
        home: const Scaffold(
          body: SingleChildScrollView(
            padding: EdgeInsets.all(16),
            child: MetricTrendChart(
              title: 'Weight trend over time',
              subtitle: 'Dates are spaced by the time between check-ins',
              unit: ' kg',
              accentColor: Colors.blue,
              points: [
                MetricChartPoint(label: '1 Aug', value: 82),
                MetricChartPoint(label: '8 Aug', value: 81.5),
                MetricChartPoint(label: '18 Aug', value: 81),
                MetricChartPoint(label: '30 Aug', value: 80.5),
              ],
            ),
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Weight trend over time'), findsOneWidget);
  });

  testWidgets('fits landscape in dark mode', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(667, 375);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetPhysicalSize);

    await tester.pumpWidget(
      MaterialApp(
        themeMode: ThemeMode.dark,
        darkTheme: ThemeData.dark(),
        home: const Scaffold(
          body: SingleChildScrollView(
            padding: EdgeInsets.all(16),
            child: MetricTrendChart(
              title: 'Waist trend',
              unit: ' cm',
              accentColor: Color(0xFFC58BF2),
              points: [
                MetricChartPoint(label: '1 Aug', value: 88),
                MetricChartPoint(label: '8 Aug', value: 87.5),
                MetricChartPoint(label: '18 Aug', value: 87),
                MetricChartPoint(label: '30 Aug', value: 86.5),
              ],
            ),
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Waist trend'), findsOneWidget);
  });
}
