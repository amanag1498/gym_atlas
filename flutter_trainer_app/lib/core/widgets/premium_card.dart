import 'dart:ui';

import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_radii.dart';
import '../theme/app_spacing.dart';

class PremiumCard extends StatefulWidget {
  const PremiumCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
    this.onTap,
    this.borderRadius,
    this.glowColor,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final double? borderRadius;
  final Color? glowColor;

  @override
  State<PremiumCard> createState() => _PremiumCardState();
}

class _PremiumCardState extends State<PremiumCard> {
  bool _hovered = false;
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final interactive = widget.onTap != null;
    final radius = widget.borderRadius ?? AppRadii.lg;
    final glow = widget.glowColor ?? AppColors.primary;

    final card = AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      curve: Curves.easeOutCubic,
      transform: Matrix4.translationValues(
        0,
        interactive && _hovered ? -3.0 : 0.0,
        0,
      ),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.surfaceStrong.withValues(alpha: 0.96),
            AppColors.surface.withValues(alpha: 0.90),
            AppColors.backgroundAlt.withValues(alpha: 0.92),
          ],
        ),
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(
          color: interactive && _hovered
              ? AppColors.strokeStrong
              : AppColors.stroke,
        ),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: AppColors.shadow,
            blurRadius: interactive && _hovered ? 38 : 28,
            offset: Offset(0, interactive && _hovered ? 22 : 16),
          ),
          BoxShadow(
            color: glow.withValues(alpha: interactive && _hovered ? 0.12 : 0.06),
            blurRadius: interactive && _hovered ? 22 : 14,
            spreadRadius: interactive && _hovered ? 1.0 : 0.0,
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(radius),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  AppColors.cardHighlight.withValues(
                    alpha: interactive && _hovered ? 0.09 : 0.06,
                  ),
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
      duration: const Duration(milliseconds: 140),
      curve: Curves.easeOutCubic,
      scale: _pressed ? 0.988 : 1,
      child: card,
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
