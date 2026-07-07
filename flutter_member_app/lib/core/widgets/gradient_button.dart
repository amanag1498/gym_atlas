import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import 'atlas_icon.dart';

enum GradientButtonVariant {
  primary,
  secondary,
  danger,
}

class GradientButton extends StatefulWidget {
  const GradientButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.expanded = false,
    this.loading = false,
    this.variant = GradientButtonVariant.primary,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;
  final bool loading;
  final GradientButtonVariant variant;

  @override
  State<GradientButton> createState() => _GradientButtonState();
}

class _GradientButtonState extends State<GradientButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final disabled = widget.onPressed == null || widget.loading;
    final variant = widget.variant;
    final primary = variant == GradientButtonVariant.primary;
    final radius = BorderRadius.circular(12);

    final child = AnimatedScale(
      duration: const Duration(milliseconds: 120),
      curve: Curves.easeOutCubic,
      scale: _pressed ? 0.985 : 1,
      child: AnimatedSlide(
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOutCubic,
        offset: _pressed ? const Offset(0, 0.02) : Offset.zero,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          curve: Curves.easeOutCubic,
          constraints: const BoxConstraints(minHeight: 40),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: _buildDecoration(
            variant: variant,
            disabled: disabled,
            radius: radius,
          ),
          child: Row(
            mainAxisSize: widget.expanded ? MainAxisSize.max : MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (widget.loading) ...[
                SizedBox.square(
                  dimension: 14,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(
                      primary || variant == GradientButtonVariant.danger
                          ? Colors.white
                          : AppColors.textPrimary,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
              ] else if (widget.icon != null) ...[
                Icon(
                  widget.icon,
                  size: 16,
                  color: _foregroundColor(variant, disabled),
                ),
                const SizedBox(width: 8),
              ],
              Flexible(
                child: Text(
                  widget.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: _foregroundColor(variant, disabled),
                        fontWeight: FontWeight.w500,
                        height: 1.0,
                      ),
                ),
              ),
              if (primary && widget.icon == null && !widget.loading) ...[
                const SizedBox(width: 8),
                Icon(
                  AtlasIcons.chevronRight,
                  size: 16,
                  color: _foregroundColor(variant, disabled),
                ),
              ],
            ],
          ),
        ),
      ),
    );

    final button = Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: radius,
        onTap: disabled ? null : widget.onPressed,
        onHighlightChanged: disabled
            ? null
            : (value) => setState(() => _pressed = value),
        child: child,
      ),
    );

    return widget.expanded ? SizedBox(width: double.infinity, child: button) : button;
  }

  BoxDecoration _buildDecoration({
    required GradientButtonVariant variant,
    required bool disabled,
    required BorderRadius radius,
  }) {
    final primary = variant == GradientButtonVariant.primary;
    final secondary = variant == GradientButtonVariant.secondary;

    return BoxDecoration(
      gradient: disabled || !primary
          ? null
          : const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                AppColors.primary,
                AppColors.primaryBright,
              ],
            ),
      color: disabled
          ? AppColors.surfaceSoft
          : primary
              ? null
              : secondary
                  ? AppColors.surface
                  : AppColors.error.withValues(alpha: 0.92),
      borderRadius: radius,
      border: Border.all(
        color: disabled
            ? AppColors.stroke
            : primary
                ? Colors.transparent
                : secondary
                    ? AppColors.strokeStrong
                    : AppColors.error.withValues(alpha: 0.94),
      ),
      boxShadow: disabled
          ? const []
          : [
              BoxShadow(
                color: primary
                    ? AppColors.primary.withValues(alpha: _pressed ? 0.16 : 0.22)
                    : AppColors.shadow.withValues(alpha: _pressed ? 0.04 : 0.07),
                blurRadius: _pressed ? 10 : 18,
                offset: Offset(0, _pressed ? 6 : 10),
              ),
            ],
    );
  }

  Color _foregroundColor(GradientButtonVariant variant, bool disabled) {
    if (disabled) {
      return AppColors.textMuted;
    }

    switch (variant) {
      case GradientButtonVariant.primary:
      case GradientButtonVariant.danger:
        return Colors.white;
      case GradientButtonVariant.secondary:
        return AppColors.textSecondary;
    }
  }
}
