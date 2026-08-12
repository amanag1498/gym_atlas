import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gym_flutter_core/metric_trend_chart.dart';

void main() {
  testWidgets('renders a labelled trend and latest value', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: MetricTrendChart(
            title: 'Weight trend',
            subtitle: 'Recent check-ins',
            unit: ' kg',
            accentColor: Colors.blue,
            points: [
              MetricChartPoint(label: '1 Aug', value: 82),
              MetricChartPoint(label: '8 Aug', value: 80.5),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Weight trend'), findsOneWidget);
    expect(find.text('80.5 kg'), findsOneWidget);
    expect(find.text('-1.5 kg'), findsOneWidget);
    expect(find.text('1 Aug'), findsOneWidget);
    expect(find.text('8 Aug'), findsOneWidget);
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

    expect(
      find.text('Add at least two entries to see a trend.'),
      findsOneWidget,
    );
    expect(find.text('88 cm'), findsOneWidget);
  });
}
