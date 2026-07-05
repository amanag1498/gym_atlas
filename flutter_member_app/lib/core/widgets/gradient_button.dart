import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_radii.dart';

enum GradientButtonVariant {
  primary,
  secondary,
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
    final primary = widget.variant == GradientButtonVariant.primary;
    final radius = BorderRadius.circular(AppRadii.md);

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
          constraints: const BoxConstraints(minHeight: 58),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: _buildDecoration(primary: primary, disabled: disabled, radius: radius),
          child: Stack(
            alignment: Alignment.center,
            children: [
              Padding(
                padding: EdgeInsets.symmetric(
                  horizontal: widget.icon != null || widget.loading ? 44 : 14,
                ),
                child: Text(
                  widget.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: disabled
                            ? AppColors.textMuted
                            : primary
                                ? Colors.white
                                : AppColors.primary,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.05,
                        height: 1.0,
                      ),
                ),
              ),
              if (widget.loading)
                Align(
                  alignment: Alignment.centerRight,
                  child: _ButtonOrb(
                    primary: primary,
                    disabled: disabled,
                    child: SizedBox.square(
                      dimension: 16,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        valueColor: AlwaysStoppedAnimation<Color>(
                          primary ? Colors.white : AppColors.primary,
                        ),
                      ),
                    ),
                  ),
                )
              else ...[
                if (!primary && widget.icon != null)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: _ButtonOrb(
                      primary: primary,
                      disabled: disabled,
                      child: Icon(
                        widget.icon,
                        size: 16,
                        color: disabled ? AppColors.textMuted : AppColors.primary,
                      ),
                    ),
                  ),
                if (!primary && widget.icon != null)
                  const Align(
                    alignment: Alignment.centerRight,
                    child: SizedBox(width: 40, height: 40),
                  ),
                if (primary)
                  Align(
                    alignment: Alignment.centerRight,
                    child: _ButtonOrb(
                      primary: true,
                      disabled: disabled,
                      child: Icon(
                        widget.icon ?? Icons.arrow_forward_rounded,
                        size: 16,
                        color: Colors.white,
                      ),
                    ),
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
    required bool primary,
    required bool disabled,
    required BorderRadius radius,
  }) {
    return BoxDecoration(
      gradient: disabled
          ? null
          : primary
              ? const LinearGradient(
                  begin: Alignment.centerLeft,
                  end: Alignment.centerRight,
                  colors: [
                    AppColors.primaryBright,
                    AppColors.primary,
                  ],
                )
              : LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    AppColors.primaryBright.withValues(alpha: 0.18),
                    AppColors.surfaceStrong,
                  ],
                ),
      color: disabled
          ? AppColors.surfaceSoft
          : primary
              ? null
              : AppColors.surfaceStrong,
      borderRadius: radius,
      border: Border.all(
        color: disabled
            ? AppColors.stroke
            : primary
                ? Colors.white.withValues(alpha: 0.16)
                : AppColors.primary.withValues(alpha: 0.34),
      ),
      boxShadow: disabled
          ? const []
          : [
              BoxShadow(
                color: primary
                    ? AppColors.primary.withValues(alpha: _pressed ? 0.16 : 0.24)
                    : AppColors.primary.withValues(alpha: _pressed ? 0.06 : 0.10),
                blurRadius: _pressed ? 12 : 20,
                offset: Offset(0, _pressed ? 5 : 10),
              ),
              if (primary)
                BoxShadow(
                  color: AppColors.primaryBright.withValues(alpha: _pressed ? 0.08 : 0.14),
                  blurRadius: _pressed ? 10 : 18,
                  offset: const Offset(0, 3),
                ),
            ],
    );
  }
}

class _ButtonOrb extends StatelessWidget {
  const _ButtonOrb({
    required this.primary,
    required this.disabled,
    required this.child,
  });

  final bool primary;
  final bool disabled;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOutCubic,
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: disabled
            ? Colors.white.withValues(alpha: 0.44)
            : primary
                ? Colors.white.withValues(alpha: 0.18)
                : Colors.white.withValues(alpha: 0.75),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: disabled
              ? AppColors.stroke
              : primary
                  ? Colors.white.withValues(alpha: 0.18)
                  : AppColors.primary.withValues(alpha: 0.22),
        ),
      ),
      alignment: Alignment.center,
      child: child,
    );
  }
}
