import 'dart:math' as math;

import 'package:flutter/material.dart';

/// A silent, branded startup state shared by the Member and Trainer apps.
class BrandedStartupLoader extends StatefulWidget {
  const BrandedStartupLoader({super.key});

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
      duration: const Duration(milliseconds: 2200),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final disableAnimations = MediaQuery.disableAnimationsOf(context);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFF),
      body: Semantics(
        label: 'Loading',
        container: true,
        child: ExcludeSemantics(
          child: DecoratedBox(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: <Color>[Color(0xFFF7F9FF), Colors.white],
              ),
            ),
            child: Center(
              child: RepaintBoundary(
                child: disableAnimations
                    ? const _LoaderFrame(progress: 0.18)
                    : AnimatedBuilder(
                        animation: _controller,
                        builder: (context, child) =>
                            _LoaderFrame(progress: _controller.value),
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _LoaderFrame extends StatelessWidget {
  const _LoaderFrame({required this.progress});

  final double progress;

  @override
  Widget build(BuildContext context) {
    final pulse = (math.sin(progress * math.pi * 2) + 1) / 2;

    return SizedBox.square(
      dimension: 156,
      child: Stack(
        alignment: Alignment.center,
        children: <Widget>[
          Transform.rotate(
            angle: progress * math.pi * 2,
            child: CustomPaint(
              size: const Size.square(156),
              painter: const _OrbitPainter(),
            ),
          ),
          Transform.scale(
            scale: 0.97 + (pulse * 0.045),
            child: Container(
              width: 76,
              height: 76,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(23),
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: <Color>[Color(0xFF465FFF), Color(0xFF3641F5)],
                ),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: const Color(
                      0xFF465FFF,
                    ).withValues(alpha: 0.18 + (pulse * 0.12)),
                    blurRadius: 26 + (pulse * 12),
                    spreadRadius: 1 + (pulse * 2),
                    offset: const Offset(0, 14),
                  ),
                ],
              ),
              child: Stack(
                alignment: Alignment.center,
                children: <Widget>[
                  const Icon(
                    Icons.fitness_center_rounded,
                    size: 29,
                    color: Colors.white,
                  ),
                  Positioned(
                    right: 13,
                    bottom: 12,
                    child: Container(
                      width: 12,
                      height: 12,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.white,
                        boxShadow: <BoxShadow>[
                          BoxShadow(
                            color: Colors.white.withValues(alpha: 0.48),
                            blurRadius: 8,
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OrbitPainter extends CustomPainter {
  const _OrbitPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final radius = (size.shortestSide / 2) - 8;
    final track = Paint()
      ..color = const Color(0xFF465FFF).withValues(alpha: 0.08)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    final orbit = Paint()
      ..shader = const SweepGradient(
        colors: <Color>[
          Colors.transparent,
          Color(0x33465FFF),
          Color(0xFF465FFF),
          Color(0x007C3AED),
        ],
        stops: <double>[0, 0.42, 0.76, 1],
      ).createShader(Rect.fromCircle(center: center, radius: radius))
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 3.2;

    canvas.drawCircle(center, radius, track);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -math.pi / 2,
      math.pi * 1.55,
      false,
      orbit,
    );
    canvas.drawCircle(
      Offset(center.dx, center.dy - radius),
      4,
      Paint()..color = const Color(0xFF465FFF),
    );
  }

  @override
  bool shouldRepaint(covariant _OrbitPainter oldDelegate) => false;
}
