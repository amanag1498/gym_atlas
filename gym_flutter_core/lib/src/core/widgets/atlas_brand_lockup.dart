import 'package:flutter/material.dart';

/// Canonical Gym Atlas branding rendered without a runtime image dependency.
class AtlasBrandLockup extends StatelessWidget {
  const AtlasBrandLockup({
    super.key,
    required this.audience,
    this.markSize = 64,
  });

  final String audience;
  final double markSize;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final productName = audience.toLowerCase() == 'trainer'
        ? 'Gym Atlas Coach'
        : 'Gym Atlas';

    return Semantics(
      container: true,
      label: '$productName app',
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          AtlasBrandMark(size: markSize),
          const SizedBox(width: 15),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'GYM',
                style: theme.textTheme.labelMedium?.copyWith(
                  color: const Color(0xFF667085),
                  fontWeight: FontWeight.w800,
                  letterSpacing: 4.2,
                  height: 1,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                'ATLAS',
                style: theme.textTheme.headlineSmall?.copyWith(
                  color: const Color(0xFF101828),
                  fontWeight: FontWeight.w900,
                  letterSpacing: 0.8,
                  height: 1,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class AtlasBrandMark extends StatelessWidget {
  const AtlasBrandMark({super.key, this.size = 64});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * 0.28),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF07152F), Color(0xFF0C1D3D)],
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF1D4ED8).withValues(alpha: 0.24),
            blurRadius: size * 0.42,
            offset: Offset(0, size * 0.18),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: CustomPaint(painter: const _AtlasMarkPainter()),
    );
  }
}

class _AtlasMarkPainter extends CustomPainter {
  const _AtlasMarkPainter();

  @override
  void paint(Canvas canvas, Size size) {
    Offset point(double x, double y) => Offset(size.width * x, size.height * y);
    final bounds = Offset.zero & size;
    final atlasBlue = Paint()
      ..shader = const LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [Color(0xFF4B7BFF), Color(0xFF2E5BFF)],
      ).createShader(bounds);

    final left = Path()
      ..moveTo(size.width * 0.12, size.height * 0.82)
      ..lineTo(size.width * 0.45, size.height * 0.18)
      ..quadraticBezierTo(
        size.width * 0.50,
        size.height * 0.10,
        size.width * 0.55,
        size.height * 0.18,
      )
      ..lineTo(size.width * 0.72, size.height * 0.52)
      ..lineTo(size.width * 0.59, size.height * 0.58)
      ..lineTo(size.width * 0.50, size.height * 0.40)
      ..lineTo(size.width * 0.31, size.height * 0.75)
      ..lineTo(size.width * 0.18, size.height * 0.82)
      ..close();
    canvas.drawPath(left, atlasBlue);

    final right = Path()
      ..moveTo(size.width * 0.74, size.height * 0.53)
      ..lineTo(size.width * 0.89, size.height * 0.82)
      ..lineTo(size.width * 0.66, size.height * 0.82)
      ..lineTo(size.width * 0.60, size.height * 0.70)
      ..close();
    canvas.drawPath(right, atlasBlue);

    final swoosh = Path()
      ..moveTo(size.width * 0.31, size.height * 0.74)
      ..cubicTo(
        size.width * 0.43,
        size.height * 0.61,
        size.width * 0.58,
        size.height * 0.56,
        size.width * 0.71,
        size.height * 0.59,
      )
      ..cubicTo(
        size.width * 0.56,
        size.height * 0.62,
        size.width * 0.43,
        size.height * 0.69,
        size.width * 0.31,
        size.height * 0.80,
      )
      ..close();
    canvas.drawPath(swoosh, atlasBlue);

    final arrow = Path()
      ..moveTo(point(0.54, 0.56).dx, point(0.54, 0.56).dy)
      ..lineTo(size.width * 0.75, size.height * 0.51)
      ..lineTo(size.width * 0.69, size.height * 0.65)
      ..close();
    canvas.drawPath(arrow, Paint()..color = const Color(0xFF34D5F2));
  }

  @override
  bool shouldRepaint(covariant _AtlasMarkPainter oldDelegate) => false;
}
