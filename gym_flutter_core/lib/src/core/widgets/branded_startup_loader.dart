import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'atlas_brand_lockup.dart';

/// A quiet, branded startup state shared by the Member and Trainer apps.
class BrandedStartupLoader extends StatefulWidget {
  const BrandedStartupLoader({super.key, this.audience = 'Member'});

  final String audience;

  @override
  State<BrandedStartupLoader> createState() => _BrandedStartupLoaderState();
}

class _BrandedStartupLoaderState extends State<BrandedStartupLoader>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller
        ..stop()
        ..value = 0.35;
    } else if (!_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final disableAnimations = MediaQuery.disableAnimationsOf(context);
    final productName = widget.audience.toLowerCase() == 'trainer'
        ? 'Gym Atlas Coach'
        : 'Gym Atlas';

    return Scaffold(
      backgroundColor: const Color(0xFFF7F9FF),
      body: Semantics(
        label: '$productName is getting ready',
        liveRegion: true,
        container: true,
        child: ExcludeSemantics(
          child: Stack(
            fit: StackFit.expand,
            children: <Widget>[
              const _LoaderBackground(),
              SafeArea(
                minimum: const EdgeInsets.symmetric(horizontal: 24),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 380),
                    child: disableAnimations
                        ? _LoaderContent(
                            audience: widget.audience,
                            progress: 0.35,
                          )
                        : AnimatedBuilder(
                            key: const ValueKey<String>(
                              'startup-loader-animation',
                            ),
                            animation: _controller,
                            builder: (context, child) => _LoaderContent(
                              audience: widget.audience,
                              progress: _controller.value,
                            ),
                          ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LoaderBackground extends StatelessWidget {
  const _LoaderBackground();

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            Color(0xFFF4F7FF),
            Color(0xFFFFFFFF),
            Color(0xFFF8F7FF),
          ],
        ),
      ),
      child: CustomPaint(painter: const _BackgroundPainter()),
    );
  }
}

class _LoaderContent extends StatelessWidget {
  const _LoaderContent({required this.audience, required this.progress});

  final String audience;
  final double progress;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final pulse = (math.sin(progress * math.pi * 2) + 1) / 2;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        SizedBox.square(
          dimension: 138,
          child: Stack(
            alignment: Alignment.center,
            children: <Widget>[
              Opacity(
                opacity: 0.34 - (pulse * 0.12),
                child: Transform.scale(
                  scale: 0.78 + (pulse * 0.20),
                  child: const DecoratedBox(
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Color(0x33465FFF),
                    ),
                    child: SizedBox.expand(),
                  ),
                ),
              ),
              Transform.rotate(
                angle: progress * math.pi * 2,
                child: CustomPaint(
                  size: const Size.square(116),
                  painter: const _ProgressArcPainter(),
                ),
              ),
              Transform.scale(
                scale: 0.98 + (pulse * 0.025),
                child: const AtlasBrandMark(size: 78),
              ),
            ],
          ),
        ),
        const SizedBox(height: 24),
        AtlasBrandLockup(audience: audience, markSize: 42),
        const SizedBox(height: 30),
        Text(
          'Getting things ready',
          textAlign: TextAlign.center,
          style: theme.textTheme.titleLarge?.copyWith(
            color: const Color(0xFF101828),
            fontWeight: FontWeight.w800,
            letterSpacing: -0.35,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'Preparing your training space',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyMedium?.copyWith(
            color: const Color(0xFF667085),
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 24),
        _ActivityIndicator(progress: progress),
      ],
    );
  }
}

class _ActivityIndicator extends StatelessWidget {
  const _ActivityIndicator({required this.progress});

  final double progress;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 66,
      height: 8,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: List<Widget>.generate(3, (index) {
          final wave =
              (math.sin((progress * math.pi * 2) - (index * 0.85)) + 1) / 2;
          return Container(
            width: 16 + (wave * 4),
            height: 5,
            decoration: BoxDecoration(
              color: Color.lerp(
                const Color(0xFFD9DEFF),
                const Color(0xFF465FFF),
                wave,
              ),
              borderRadius: BorderRadius.circular(999),
            ),
          );
        }),
      ),
    );
  }
}

class _ProgressArcPainter extends CustomPainter {
  const _ProgressArcPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final radius = (size.shortestSide / 2) - 4;
    final rect = Rect.fromCircle(center: center, radius: radius);
    final paint = Paint()
      ..shader = const SweepGradient(
        colors: <Color>[
          Color(0x00465FFF),
          Color(0x55465FFF),
          Color(0xFF465FFF),
          Color(0x0034D5F2),
        ],
        stops: <double>[0, 0.42, 0.78, 1],
      ).createShader(rect)
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 3;

    canvas.drawArc(rect, -math.pi / 2, math.pi * 1.45, false, paint);
  }

  @override
  bool shouldRepaint(covariant _ProgressArcPainter oldDelegate) => false;
}

class _BackgroundPainter extends CustomPainter {
  const _BackgroundPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final blue = Paint()
      ..shader =
          RadialGradient(
            colors: <Color>[
              const Color(0xFF465FFF).withValues(alpha: 0.08),
              Colors.transparent,
            ],
          ).createShader(
            Rect.fromCircle(
              center: Offset(size.width * 0.88, size.height * 0.14),
              radius: size.shortestSide * 0.52,
            ),
          );
    final cyan = Paint()
      ..shader =
          RadialGradient(
            colors: <Color>[
              const Color(0xFF34D5F2).withValues(alpha: 0.055),
              Colors.transparent,
            ],
          ).createShader(
            Rect.fromCircle(
              center: Offset(size.width * 0.08, size.height * 0.86),
              radius: size.shortestSide * 0.46,
            ),
          );

    canvas.drawRect(Offset.zero & size, blue);
    canvas.drawRect(Offset.zero & size, cyan);
  }

  @override
  bool shouldRepaint(covariant _BackgroundPainter oldDelegate) => false;
}
