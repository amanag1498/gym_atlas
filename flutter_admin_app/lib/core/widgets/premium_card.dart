import 'dart:ui';

import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

class PremiumCard extends StatefulWidget {
  const PremiumCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
    this.onTap,
    this.glowColor,
    this.borderRadius,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final Color? glowColor;
  final double? borderRadius;

  @override
  State<PremiumCard> createState() => _PremiumCardState();
}

class _PremiumCardState extends State<PremiumCard> {
  bool _hovered = false;
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final interactive = widget.onTap != null;
    final radius = widget.borderRadius ?? AppSpacing.radiusLg;
    final glow = widget.glowColor ?? AppColors.primaryBright;
    final card = Container(
      transform: Matrix4.translationValues(
        0,
        interactive && _hovered ? -2.0 : 0.0,
        0,
      ),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.96),
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: AppColors.strokeStrong),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: AppColors.shadow,
            blurRadius: interactive && _hovered ? 28 : 22,
            offset: Offset(0, interactive && _hovered ? 16 : 12),
          ),
          BoxShadow(
            color: glow.withValues(
              alpha: interactive && _hovered ? 0.16 : 0.06,
            ),
            blurRadius: interactive && _hovered ? 24 : 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(radius),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 14, sigmaY: 14),
          child: Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  glow.withValues(alpha: interactive && _hovered ? 0.08 : 0.03),
                  Colors.transparent,
                ],
              ),
            ),
            child: Padding(padding: widget.padding, child: widget.child),
          ),
        ),
      ),
    );

    final animatedCard = AnimatedScale(
      duration: const Duration(milliseconds: 160),
      curve: Curves.easeOutCubic,
      scale: _pressed ? 0.988 : 1,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOutCubic,
        child: card,
      ),
    );

    if (!interactive) {
      return animatedCard;
    }

    return MouseRegion(
      onEnter: (_) => setState(() => _hovered = true),
      onExit: (_) => setState(() {
        _hovered = false;
        _pressed = false;
      }),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: widget.onTap,
          onHighlightChanged: (value) => setState(() => _pressed = value),
          borderRadius: BorderRadius.circular(radius),
          child: animatedCard,
        ),
      ),
    );
  }
}
