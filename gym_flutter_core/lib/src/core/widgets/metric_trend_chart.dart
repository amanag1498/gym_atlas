import 'dart:math' as math;

import 'package:flutter/material.dart';

class MetricChartPoint {
  const MetricChartPoint({
    required this.label,
    required this.value,
    this.timestamp,
  });

  final String label;
  final double value;
  final DateTime? timestamp;
}

class MetricTrendChart extends StatefulWidget {
  const MetricTrendChart({
    super.key,
    required this.title,
    required this.points,
    required this.accentColor,
    this.subtitle,
    this.unit = '',
    this.emptyMessage = 'Add at least two entries to see a trend.',
    this.goalValue,
    this.goalLabel = 'Goal',
  });

  final String title;
  final String? subtitle;
  final List<MetricChartPoint> points;
  final Color accentColor;
  final String unit;
  final String emptyMessage;
  final double? goalValue;
  final String goalLabel;

  @override
  State<MetricTrendChart> createState() => _MetricTrendChartState();
}

class _MetricTrendChartState extends State<MetricTrendChart> {
  static const _minimumChartPoints = 4;
  int? _selectedIndex;

  @override
  void initState() {
    super.initState();
    _selectedIndex = widget.points.isEmpty ? null : widget.points.length - 1;
  }

  @override
  void didUpdateWidget(covariant MetricTrendChart oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.points.length != widget.points.length ||
        (_selectedIndex != null && _selectedIndex! >= widget.points.length)) {
      _selectedIndex = widget.points.isEmpty ? null : widget.points.length - 1;
    }
  }

  @override
  Widget build(BuildContext context) {
    final values = widget.points.map((point) => point.value).toList();
    final latest = values.isEmpty ? null : values.last;
    final change = values.length < 2 ? null : values.last - values.first;
    final scale = values.isEmpty
        ? null
        : _MetricScale.from(values, goalValue: widget.goalValue);
    final chartColor = _ensureContrast(
      widget.accentColor,
      Theme.of(context).colorScheme.surface,
      3,
    );

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
            LayoutBuilder(
              builder: (context, constraints) {
                final largeText =
                    MediaQuery.textScalerOf(context).scale(1) > 1.3;
                final stackHeader = constraints.maxWidth < 340 || largeText;
                final copy = _MetricHeaderCopy(
                  title: widget.title,
                  subtitle: widget.subtitle,
                );
                if (latest == null) return copy;
                final delta = _MetricDelta(
                  value: _format(latest),
                  change: change,
                  unit: widget.unit,
                  color: chartColor,
                  alignEnd: !stackHeader,
                );
                if (stackHeader) {
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [copy, const SizedBox(height: 12), delta],
                  );
                }
                return Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: copy),
                    const SizedBox(width: 12),
                    delta,
                  ],
                );
              },
            ),
            const SizedBox(height: 18),
            if (widget.points.isEmpty)
              _MetricEmptyState(message: widget.emptyMessage)
            else if (widget.points.length < _minimumChartPoints)
              _MetricSparseState(
                points: widget.points,
                unit: widget.unit,
                accentColor: chartColor,
                remaining: _minimumChartPoints - widget.points.length,
              )
            else ...[
              _SelectedMetricPoint(
                point:
                    widget.points[_selectedIndex ?? widget.points.length - 1],
                unit: widget.unit,
                color: chartColor,
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 172,
                width: double.infinity,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    SizedBox(
                      width: 48,
                      child: _MetricAxis(
                        upper: scale!.upper,
                        middle: scale.middle,
                        lower: scale.lower,
                        unit: widget.unit,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: LayoutBuilder(
                        builder: (context, constraints) {
                          final positions = _xPositions(widget.points);
                          return GestureDetector(
                            behavior: HitTestBehavior.opaque,
                            onTapDown: (details) {
                              final normalized = constraints.maxWidth <= 0
                                  ? 0.0
                                  : (details.localPosition.dx /
                                            constraints.maxWidth)
                                        .clamp(0.0, 1.0);
                              var closestIndex = 0;
                              var closestDistance = double.infinity;
                              for (
                                var index = 0;
                                index < positions.length;
                                index++
                              ) {
                                final distance = (positions[index] - normalized)
                                    .abs();
                                if (distance < closestDistance) {
                                  closestDistance = distance;
                                  closestIndex = index;
                                }
                              }
                              setState(() => _selectedIndex = closestIndex);
                            },
                            child: ExcludeSemantics(
                              child: CustomPaint(
                                painter: _MetricTrendPainter(
                                  values: values,
                                  xPositions: positions,
                                  lower: scale.lower,
                                  upper: scale.upper,
                                  goalValue: widget.goalValue,
                                  color: chartColor,
                                  gridColor: Theme.of(
                                    context,
                                  ).dividerColor.withValues(alpha: 0.35),
                                  selectedIndex: _selectedIndex,
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              if (widget.goalValue != null) ...[
                Row(
                  children: [
                    SizedBox(
                      width: 18,
                      child: CustomPaint(
                        painter: _DashedLegendPainter(color: chartColor),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      '${widget.goalLabel}: ${_format(widget.goalValue!)}${widget.unit}',
                      style: Theme.of(context).textTheme.labelSmall,
                    ),
                  ],
                ),
                const SizedBox(height: 8),
              ],
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children:
                    [
                      Flexible(
                        child: Text(
                          widget.points.first.label,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        child: Text('${widget.points.length} entries'),
                      ),
                      Flexible(
                        child: Text(
                          widget.points.last.label,
                          textAlign: TextAlign.end,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ].map((text) {
                      return DefaultTextStyle.merge(
                        style: Theme.of(context).textTheme.labelSmall,
                        child: text,
                      );
                    }).toList(),
              ),
              const SizedBox(height: 8),
              Text(
                'Tap the chart to inspect a check-in.',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _semanticLabel() {
    if (widget.points.isEmpty) return '${widget.title}. No trend data.';
    return '${widget.title}. ${widget.points.map((point) => '${point.label}: ${_format(point.value)}${widget.unit}').join(', ')}.${widget.goalValue == null ? '' : ' ${widget.goalLabel}: ${_format(widget.goalValue!)}${widget.unit}.'}';
  }
}

class _MetricDelta extends StatelessWidget {
  const _MetricDelta({
    required this.value,
    required this.change,
    required this.unit,
    required this.color,
    this.alignEnd = true,
  });

  final String value;
  final double? change;
  final String unit;
  final Color color;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    final delta = change;
    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
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
            '${delta > 0 ? '+' : ''}${_format(delta)}$unit overall',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w800,
            ),
          ),
      ],
    );
  }
}

class _MetricHeaderCopy extends StatelessWidget {
  const _MetricHeaderCopy({required this.title, required this.subtitle});

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 4),
          Text(subtitle!, style: Theme.of(context).textTheme.bodySmall),
        ],
      ],
    );
  }
}

class _MetricEmptyState extends StatelessWidget {
  const _MetricEmptyState({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 132,
      child: Center(
        child: Text(
          message,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ),
    );
  }
}

class _MetricSparseState extends StatelessWidget {
  const _MetricSparseState({
    required this.points,
    required this.unit,
    required this.accentColor,
    required this.remaining,
  });

  final List<MetricChartPoint> points;
  final String unit;
  final Color accentColor;
  final int remaining;

  @override
  Widget build(BuildContext context) {
    final first = points.first;
    final latest = points.last;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: accentColor.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: accentColor.withValues(alpha: 0.22)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            points.length == 1 ? 'First check-in saved' : 'Early progress',
            style: Theme.of(
              context,
            ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _SparseValue(
                  label: first.label,
                  value: '${_format(first.value)}$unit',
                ),
              ),
              if (points.length > 1) ...[
                Icon(
                  Icons.arrow_forward_rounded,
                  size: 18,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _SparseValue(
                    label: latest.label,
                    value: '${_format(latest.value)}$unit',
                    alignEnd: true,
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 12),
          Text(
            'Add $remaining more ${remaining == 1 ? 'entry' : 'entries'} for a reliable trend line.',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }
}

class _SparseValue extends StatelessWidget {
  const _SparseValue({
    required this.label,
    required this.value,
    this.alignEnd = false,
  });

  final String label;
  final String value;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 2),
        Text(label, style: Theme.of(context).textTheme.labelSmall),
      ],
    );
  }
}

class _SelectedMetricPoint extends StatelessWidget {
  const _SelectedMetricPoint({
    required this.point,
    required this.unit,
    required this.color,
  });

  final MetricChartPoint point;
  final String unit;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      label: '${point.label}, ${_format(point.value)}$unit',
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.10),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: color.withValues(alpha: 0.24)),
        ),
        child: Text(
          '${point.label}  •  ${_format(point.value)}$unit',
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurface,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}

class _MetricAxis extends StatelessWidget {
  const _MetricAxis({
    required this.upper,
    required this.middle,
    required this.lower,
    required this.unit,
  });

  final double upper;
  final double middle;
  final double lower;
  final String unit;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.labelSmall?.copyWith(
      color: Theme.of(context).colorScheme.onSurfaceVariant,
      fontSize: 10,
    );
    return Column(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        _AxisLabel(text: _axisFormat(upper, unit), style: style),
        _AxisLabel(text: _axisFormat(middle, unit), style: style),
        _AxisLabel(text: _axisFormat(lower, unit), style: style),
      ],
    );
  }
}

class _AxisLabel extends StatelessWidget {
  const _AxisLabel({required this.text, required this.style});

  final String text;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: Alignment.centerRight,
      child: Text(text, maxLines: 1, softWrap: false, style: style),
    );
  }
}

class _MetricScale {
  const _MetricScale({required this.lower, required this.upper});

  factory _MetricScale.from(List<double> values, {double? goalValue}) {
    final allValues = [...values, if (goalValue != null) goalValue];
    final minimum = allValues.reduce(math.min);
    final maximum = allValues.reduce(math.max);
    final spread = maximum - minimum;
    final padding = spread == 0
        ? math.max(1.0, maximum.abs() * 0.05)
        : spread * 0.14;
    return _MetricScale(lower: minimum - padding, upper: maximum + padding);
  }

  final double lower;
  final double upper;

  double get middle => lower + (upper - lower) / 2;
}

class _MetricTrendPainter extends CustomPainter {
  const _MetricTrendPainter({
    required this.values,
    required this.xPositions,
    required this.lower,
    required this.upper,
    required this.color,
    required this.gridColor,
    required this.selectedIndex,
    this.goalValue,
  });

  final List<double> values;
  final List<double> xPositions;
  final double lower;
  final double upper;
  final Color color;
  final Color gridColor;
  final int? selectedIndex;
  final double? goalValue;

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
    for (var index = 0; index < 3; index++) {
      final y = chart.top + chart.height * index / 2;
      canvas.drawLine(Offset(chart.left, y), Offset(chart.right, y), gridPaint);
    }

    final range = upper - lower;
    final path = Path();
    final fillPath = Path();
    final points = <Offset>[];

    for (var index = 0; index < values.length; index++) {
      final x = chart.left + chart.width * xPositions[index];
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
            color.withValues(alpha: 0.24),
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

    if (goalValue != null) {
      final goalY =
          chart.bottom - chart.height * ((goalValue! - lower) / range);
      _drawDashedLine(
        canvas,
        Offset(chart.left, goalY),
        Offset(chart.right, goalY),
        Paint()
          ..color = color.withValues(alpha: 0.70)
          ..strokeWidth = 1.5,
      );
    }

    final pointPaint = Paint()..color = color;
    for (final point in points) {
      canvas.drawCircle(point, 3.5, pointPaint);
    }

    final selected = selectedIndex;
    if (selected != null && selected >= 0 && selected < points.length) {
      final point = points[selected];
      canvas.drawLine(
        Offset(point.dx, chart.top),
        Offset(point.dx, chart.bottom),
        Paint()
          ..color = color.withValues(alpha: 0.24)
          ..strokeWidth = 1,
      );
      canvas.drawCircle(
        point,
        7,
        Paint()..color = color.withValues(alpha: 0.20),
      );
      canvas.drawCircle(point, 4.5, Paint()..color = color);
    }
  }

  @override
  bool shouldRepaint(covariant _MetricTrendPainter oldDelegate) =>
      oldDelegate.values != values ||
      oldDelegate.xPositions != xPositions ||
      oldDelegate.lower != lower ||
      oldDelegate.upper != upper ||
      oldDelegate.goalValue != goalValue ||
      oldDelegate.color != color ||
      oldDelegate.gridColor != gridColor ||
      oldDelegate.selectedIndex != selectedIndex;
}

class _DashedLegendPainter extends CustomPainter {
  const _DashedLegendPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    _drawDashedLine(
      canvas,
      Offset(0, size.height / 2),
      Offset(size.width, size.height / 2),
      Paint()
        ..color = color.withValues(alpha: 0.70)
        ..strokeWidth = 1.5,
    );
  }

  @override
  bool shouldRepaint(covariant _DashedLegendPainter oldDelegate) =>
      oldDelegate.color != color;
}

List<double> _xPositions(List<MetricChartPoint> points) {
  if (points.length < 2) return const [0];
  final timestamps = points.map((point) => point.timestamp).toList();
  if (timestamps.any((timestamp) => timestamp == null)) {
    return List.generate(points.length, (index) => index / (points.length - 1));
  }
  final start = timestamps.first!.millisecondsSinceEpoch;
  final end = timestamps.last!.millisecondsSinceEpoch;
  if (start == end) {
    return List.generate(points.length, (index) => index / (points.length - 1));
  }
  return timestamps
      .map(
        (timestamp) =>
            (timestamp!.millisecondsSinceEpoch - start) / (end - start),
      )
      .toList();
}

void _drawDashedLine(Canvas canvas, Offset start, Offset end, Paint paint) {
  const dash = 5.0;
  const gap = 4.0;
  final distance = (end - start).distance;
  if (distance == 0) return;
  final direction = (end - start) / distance;
  var travelled = 0.0;
  while (travelled < distance) {
    final dashEnd = math.min(travelled + dash, distance);
    canvas.drawLine(
      start + direction * travelled,
      start + direction * dashEnd,
      paint,
    );
    travelled += dash + gap;
  }
}

String _format(double value) => value == value.roundToDouble()
    ? value.toStringAsFixed(0)
    : value.toStringAsFixed(1);

String _axisFormat(double value, String unit) {
  final compactUnit = unit.trim();
  final suffix =
      compactUnit == 'kg' || compactUnit == 'cm' || compactUnit == '%'
      ? compactUnit
      : '';
  return '${_format(value)}$suffix';
}

Color _ensureContrast(Color foreground, Color background, double minimum) {
  if (_contrastRatio(foreground, background) >= minimum) return foreground;
  final target = background.computeLuminance() > 0.5
      ? Colors.black
      : Colors.white;
  for (var step = 1; step <= 10; step++) {
    final candidate = Color.lerp(foreground, target, step / 10)!;
    if (_contrastRatio(candidate, background) >= minimum) return candidate;
  }
  return target;
}

double _contrastRatio(Color first, Color second) {
  final lighter = math.max(first.computeLuminance(), second.computeLuminance());
  final darker = math.min(first.computeLuminance(), second.computeLuminance());
  return (lighter + 0.05) / (darker + 0.05);
}
