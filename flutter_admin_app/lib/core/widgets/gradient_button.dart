import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_gradients.dart';
import '../theme/app_spacing.dart';

class GradientButton extends StatefulWidget {
  const GradientButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.expanded = false,
    this.loading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool expanded;
  final bool loading;

  @override
  State<GradientButton> createState() => _GradientButtonState();
}

class _GradientButtonState extends State<GradientButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final child = DecoratedBox(
      decoration: BoxDecoration(
        gradient: widget.onPressed == null ? null : AppGradients.primaryButton,
        color: widget.onPressed == null ? AppColors.surfaceMuted : null,
        borderRadius: BorderRadius.circular(25),
        boxShadow: widget.onPressed == null
            ? null
            : <BoxShadow>[
                BoxShadow(
                  color: AppColors.primary.withValues(
                    alpha: _pressed ? 0.18 : 0.28,
                  ),
                  blurRadius: _pressed ? 10 : 22,
                  offset: Offset(0, _pressed ? 5 : 12),
                ),
              ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(25),
          onHighlightChanged: (value) => setState(() => _pressed = value),
          onTap: widget.onPressed,
          child: AnimatedScale(
            duration: const Duration(milliseconds: 120),
            scale: _pressed ? 0.975 : 1,
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.lg,
                vertical: AppSpacing.md,
              ),
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 180),
                transitionBuilder: (child, animation) => FadeTransition(
                  opacity: animation,
                  child: ScaleTransition(scale: animation, child: child),
                ),
                child: Row(
                  key: ValueKey<String>(
                    '${widget.label}_${widget.loading}_${widget.icon}',
                  ),
                  mainAxisAlignment: MainAxisAlignment.center,
                  mainAxisSize: widget.expanded
                      ? MainAxisSize.max
                      : MainAxisSize.min,
                  children: <Widget>[
                    if (widget.loading)
                      const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation<Color>(
                            Colors.white,
                          ),
                        ),
                      )
                    else if (widget.icon != null) ...<Widget>[
                      Icon(widget.icon, color: Colors.white),
                      const SizedBox(width: AppSpacing.sm),
                    ],
                    Text(
                      widget.label,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );

    return widget.expanded
        ? SizedBox(width: double.infinity, child: child)
        : child;
  }
}
