import 'dart:math' as math;
import 'dart:async';

import 'package:flutter/material.dart';

/// Keeps the branded animation visible briefly on every cold app launch.
/// Startup work continues in the child while the splash is on screen.
class BrandedStartupSplash extends StatefulWidget {
  const BrandedStartupSplash({
    super.key,
    required this.audience,
    required this.child,
    this.minimumDuration = const Duration(milliseconds: 1400),
  });

  final String audience;
  final Widget child;
  final Duration minimumDuration;

  @override
  State<BrandedStartupSplash> createState() => _BrandedStartupSplashState();
}

class _BrandedStartupSplashState extends State<BrandedStartupSplash> {
  Timer? _timer;
  bool _showSplash = true;

  @override
  void initState() {
    super.initState();
    _timer = Timer(widget.minimumDuration, () {
      if (mounted) setState(() => _showSplash = false);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedSwitcher(
      duration: MediaQuery.disableAnimationsOf(context)
          ? Duration.zero
          : const Duration(milliseconds: 280),
      child: _showSplash
          ? BrandedStartupLoader(
              key: const ValueKey<String>('startup-splash'),
              audience: widget.audience,
            )
          : KeyedSubtree(
              key: const ValueKey<String>('startup-content'),
              child: widget.child,
            ),
    );
  }
}

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
      backgroundColor: const Color(0xFF0F172A),
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
            Color(0xFF0F172A),
            Color(0xFF132044),
            Color(0xFF0F172A),
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
                scale: 0.96 + (pulse * 0.04),
                child: Image.asset(
                  'assets/branding/gym_atlas_mark.png',
                  package: 'gym_flutter_core',
                  width: 112,
                  height: 112,
                  fit: BoxFit.contain,
                  filterQuality: FilterQuality.high,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 24),
        Text(
          'GYM ATLAS',
          textAlign: TextAlign.center,
          style: theme.textTheme.headlineSmall?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w900,
            letterSpacing: 0.8,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          audience.toLowerCase() == 'trainer'
              ? 'COACH'
              : 'DISCIPLINE IN MOTION',
          textAlign: TextAlign.center,
          style: theme.textTheme.labelSmall?.copyWith(
            color: const Color(0xFF9EADFF),
            fontWeight: FontWeight.w800,
            letterSpacing: audience.toLowerCase() == 'trainer' ? 3.2 : 2.2,
          ),
        ),
        const SizedBox(height: 28),
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
                const Color(0xFF526183),
                const Color(0xFFA8B6FF),
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
          Color(0x885D75FF),
          Color(0xFFA8B6FF),
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
              const Color(0xFF465FFF).withValues(alpha: 0.13),
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
              const Color(0xFF34D5F2).withValues(alpha: 0.075),
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
