import 'dart:math' as math;

import 'package:flutter/material.dart';

class MetricChartPoint {
  const MetricChartPoint({required this.label, required this.value});

  final String label;
  final double value;
}

class MetricTrendChart extends StatelessWidget {
  const MetricTrendChart({
    super.key,
    required this.title,
    required this.points,
    required this.accentColor,
    this.subtitle,
    this.unit = '',
    this.emptyMessage = 'Add at least two entries to see a trend.',
  });

  final String title;
  final String? subtitle;
  final List<MetricChartPoint> points;
  final Color accentColor;
  final String unit;
  final String emptyMessage;

  @override
  Widget build(BuildContext context) {
    final values = points.map((point) => point.value).toList();
    final latest = values.isEmpty ? null : values.last;
    final change = values.length < 2 ? null : values.last - values.first;

    return Semantics(
      container: true,
      label: _semanticLabel(),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(
            color: Theme.of(context).dividerColor.withValues(alpha: 0.55),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      if (subtitle != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          subtitle!,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ],
                  ),
                ),
                if (latest != null)
                  _MetricDelta(
                    value: _format(latest),
                    change: change,
                    unit: unit,
                    color: accentColor,
                  ),
              ],
            ),
            const SizedBox(height: 18),
            if (points.length < 2)
              SizedBox(
                height: 148,
                child: Center(
                  child: Text(
                    emptyMessage,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              )
            else ...[
              SizedBox(
                height: 148,
                width: double.infinity,
                child: CustomPaint(
                  painter: _MetricTrendPainter(
                    values: values,
                    color: accentColor,
                    gridColor: Theme.of(
                      context,
                    ).dividerColor.withValues(alpha: 0.35),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children:
                    [
                      Text(points.first.label),
                      Text('${points.length} entries'),
                      Text(points.last.label),
                    ].map((text) {
                      return DefaultTextStyle.merge(
                        style: Theme.of(context).textTheme.labelSmall,
                        child: text,
                      );
                    }).toList(),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _semanticLabel() {
    if (points.isEmpty) return '$title. No trend data.';
    return '$title. ${points.map((point) => '${point.label}: ${_format(point.value)}$unit').join(', ')}.';
  }

  String _format(double value) => value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(1);
}

class _MetricDelta extends StatelessWidget {
  const _MetricDelta({
    required this.value,
    required this.change,
    required this.unit,
    required this.color,
  });

  final String value;
  final double? change;
  final String unit;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final delta = change;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Text(
          '$value$unit',
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: color,
            fontWeight: FontWeight.w900,
          ),
        ),
        if (delta != null)
          Text(
            '${delta > 0 ? '+' : ''}${delta.toStringAsFixed(1)}$unit',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: delta == 0 ? null : color,
              fontWeight: FontWeight.w800,
            ),
          ),
      ],
    );
  }
}

class _MetricTrendPainter extends CustomPainter {
  const _MetricTrendPainter({
    required this.values,
    required this.color,
    required this.gridColor,
  });

  final List<double> values;
  final Color color;
  final Color gridColor;

  @override
  void paint(Canvas canvas, Size size) {
    const inset = 8.0;
    final chart = Rect.fromLTWH(
      inset,
      inset,
      math.max(0, size.width - inset * 2),
      math.max(0, size.height - inset * 2),
    );
    final gridPaint = Paint()
      ..color = gridColor
      ..strokeWidth = 1;
    for (var index = 0; index < 4; index++) {
      final y = chart.top + chart.height * index / 3;
      canvas.drawLine(Offset(chart.left, y), Offset(chart.right, y), gridPaint);
    }

    final minimum = values.reduce(math.min);
    final maximum = values.reduce(math.max);
    final spread = maximum - minimum;
    final padding = spread == 0
        ? math.max(1.0, maximum.abs() * 0.05)
        : spread * 0.12;
    final lower = minimum - padding;
    final upper = maximum + padding;
    final range = upper - lower;
    final path = Path();
    final fillPath = Path();
    final points = <Offset>[];

    for (var index = 0; index < values.length; index++) {
      final x = chart.left + chart.width * index / (values.length - 1);
      final y = chart.bottom - chart.height * ((values[index] - lower) / range);
      final point = Offset(x, y);
      points.add(point);
      index == 0 ? path.moveTo(x, y) : path.lineTo(x, y);
    }

    fillPath
      ..moveTo(points.first.dx, chart.bottom)
      ..addPath(path, Offset.zero)
      ..lineTo(points.last.dx, chart.bottom)
      ..close();
    canvas.drawPath(
      fillPath,
      Paint()
        ..shader = LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            color.withValues(alpha: 0.28),
            color.withValues(alpha: 0.01),
          ],
        ).createShader(chart),
    );
    canvas.drawPath(
      path,
      Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );
    final pointPaint = Paint()..color = color;
    for (final point in points) {
      canvas.drawCircle(point, 3.5, pointPaint);
    }
  }

  @override
  bool shouldRepaint(covariant _MetricTrendPainter oldDelegate) =>
      oldDelegate.values != values ||
      oldDelegate.color != color ||
      oldDelegate.gridColor != gridColor;
}
