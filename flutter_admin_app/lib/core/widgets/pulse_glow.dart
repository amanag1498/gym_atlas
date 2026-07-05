import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class PulseGlow extends StatefulWidget {
  const PulseGlow({
    super.key,
    required this.child,
    this.enabled = true,
    this.baseScale = 1,
    this.pulseScale = 1.018,
    this.glowColor = AppColors.primary,
    this.duration = const Duration(milliseconds: 2200),
  });

  final Widget child;
  final bool enabled;
  final double baseScale;
  final double pulseScale;
  final Color glowColor;
  final Duration duration;

  @override
  State<PulseGlow> createState() => _PulseGlowState();
}

class _PulseGlowState extends State<PulseGlow>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: widget.duration);
    if (widget.enabled) {
      _controller.repeat(reverse: true);
    }
  }

  @override
  void didUpdateWidget(covariant PulseGlow oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.enabled && !_controller.isAnimating) {
      _controller.repeat(reverse: true);
    } else if (!widget.enabled && _controller.isAnimating) {
      _controller.stop();
      _controller.value = 0;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.enabled) {
      return widget.child;
    }

    return AnimatedBuilder(
      animation: CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
      builder: (context, child) {
        final t = _controller.value;
        final scale = widget.baseScale + ((widget.pulseScale - widget.baseScale) * t);

        return Transform.scale(
          scale: scale,
          child: DecoratedBox(
            decoration: BoxDecoration(
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: widget.glowColor.withValues(alpha: 0.04 + (0.10 * t)),
                  blurRadius: 18 + (12 * t),
                  spreadRadius: 0.5 + (1.2 * t),
                ),
              ],
            ),
            child: child,
          ),
        );
      },
      child: widget.child,
    );
  }
}
