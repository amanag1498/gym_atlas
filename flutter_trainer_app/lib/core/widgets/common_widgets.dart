import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../theme/app_colors.dart';
import '../theme/app_gradients.dart';
import '../theme/app_spacing.dart';
import 'animated_page_wrapper.dart';
import 'empty_state.dart';
import 'error_state.dart';
import 'gradient_button.dart';
import 'loading_state.dart';
import 'premium_card.dart';
import 'stat_card.dart';

export 'gradient_button.dart';
export 'pulse_glow.dart';
export 'quick_action_button.dart';
export 'reveal_on_build.dart';
export 'skeleton_loader.dart';
export 'stat_card.dart';
export 'status_badge.dart';
export 'client_card.dart';
export 'task_card.dart';

class AppGradientScaffold extends StatelessWidget {
  const AppGradientScaffold({
    super.key,
    required this.title,
    required this.body,
    this.subtitle,
    this.actions,
    this.floatingActionButton,
    this.bottomNavigationBar,
  });

  final String title;
  final Widget body;
  final String? subtitle;
  final List<Widget>? actions;
  final Widget? floatingActionButton;
  final Widget? bottomNavigationBar;

  @override
  Widget build(BuildContext context) {
    return AnimatedPageWrapper(
      floatingActionButton: floatingActionButton,
      bottomNavigationBar: bottomNavigationBar,
      child: body,
    );
  }
}

class SectionCard extends StatelessWidget {
  const SectionCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
  });

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(padding: padding, child: child);
  }
}

class MetricTile extends StatelessWidget {
  const MetricTile({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    if (color != null) {
      return Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[
              color!.withValues(alpha: 0.22),
              AppColors.surfaceStrong,
            ],
          ),
          border: Border.all(color: AppColors.stroke),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color),
            const SizedBox(height: AppSpacing.md),
            Text(value, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: AppSpacing.xs),
            Text(label, style: Theme.of(context).textTheme.bodyMedium),
          ],
        ),
      );
    }
    return StatCard(label: label, value: value, icon: icon);
  }
}

class GlassCard extends StatelessWidget {
  const GlassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
    this.gradient,
    this.onTap,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Gradient? gradient;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final widget = gradient == null
        ? PremiumCard(padding: padding, child: child)
        : Container(
            decoration: BoxDecoration(
              gradient: gradient,
              borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
              border: Border.all(color: AppColors.stroke),
              boxShadow: const <BoxShadow>[
                BoxShadow(
                  color: Color(0x33000000),
                  blurRadius: 24,
                  offset: Offset(0, 16),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
              child: Padding(padding: padding, child: child),
            ),
          );

    if (onTap == null) {
      return widget;
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
      child: widget,
    );
  }
}

class AppNetworkImage extends StatelessWidget {
  const AppNetworkImage({
    super.key,
    required this.imageUrl,
    required this.height,
    this.width,
    this.memoryBytes,
    this.borderRadius = AppSpacing.radiusLg,
    this.fit = BoxFit.cover,
    this.fallbackIcon = Icons.image_outlined,
  });

  final String? imageUrl;
  final double height;
  final double? width;
  final Uint8List? memoryBytes;
  final double borderRadius;
  final BoxFit fit;
  final IconData fallbackIcon;

  @override
  Widget build(BuildContext context) {
    final url = imageUrl?.trim();
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: SizedBox(
        height: height,
        width: width,
        child: memoryBytes != null
            ? Image.memory(
                memoryBytes!,
                key: ValueKey<Uint8List>(memoryBytes!),
                fit: fit,
                gaplessPlayback: true,
              )
            : url == null || url.isEmpty
            ? _ImageFallback(icon: fallbackIcon)
            : Image.network(
                key: ValueKey<String>(url),
                url,
                fit: fit,
                gaplessPlayback: true,
                errorBuilder: (_, __, ___) =>
                    _ImageFallback(icon: fallbackIcon),
                loadingBuilder: (context, child, progress) {
                  if (progress == null) {
                    return child;
                  }
                  return _ImageFallback(icon: fallbackIcon);
                },
              ),
      ),
    );
  }
}

class _ImageFallback extends StatelessWidget {
  const _ImageFallback({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.surfaceStrong,
      child: Center(
        child: Icon(icon, size: 34, color: AppColors.textSecondary),
      ),
    );
  }
}

class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.size = 72});

  final double size;

  @override
  Widget build(BuildContext context) {
    final accent = Theme.of(context).colorScheme.secondary;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[Theme.of(context).colorScheme.primary, accent],
        ),
        boxShadow: [
          BoxShadow(
            color: accent.withValues(alpha: 0.26),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          Icon(
            Icons.fitness_center_rounded,
            size: size * 0.34,
            color: const Color(0xFF061019),
          ),
          Positioned(
            right: size * 0.18,
            bottom: size * 0.16,
            child: Container(
              width: size * 0.18,
              height: size * 0.18,
              decoration: const BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class AuthPanel extends StatelessWidget {
  const AuthPanel({
    super.key,
    required this.panelLabel,
    required this.title,
    required this.description,
    required this.highlights,
    required this.buttonLabel,
    required this.onPressed,
    this.loading = false,
    this.error,
  });

  final String panelLabel;
  final String title;
  final String description;
  final List<String> highlights;
  final String buttonLabel;
  final VoidCallback? onPressed;
  final bool loading;
  final String? error;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: SectionCard(
              padding: const EdgeInsets.all(AppSpacing.xl),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.sm,
                      vertical: AppSpacing.xs,
                    ),
                    decoration: BoxDecoration(
                      gradient: AppGradients.statAccent,
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: AppColors.stroke),
                    ),
                    child: Text(
                      panelLabel.toUpperCase(),
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        fontSize: 11,
                        letterSpacing: 1.3,
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  const BrandMark(size: 80),
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    title,
                    style: Theme.of(context).textTheme.displaySmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    description,
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          AppColors.primary.withValues(alpha: 0.14),
                          AppColors.surfaceStrong.withValues(alpha: 0.84),
                        ],
                      ),
                      borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
                      border: Border.all(color: AppColors.stroke),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Inside the app',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        for (final item in highlights) ...[
                          _AuthHighlight(text: item),
                          if (item != highlights.last)
                            const SizedBox(height: AppSpacing.sm),
                        ],
                      ],
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: AppSpacing.lg),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(AppSpacing.md),
                      decoration: BoxDecoration(
                        color: AppColors.error.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(
                          AppSpacing.radiusMd,
                        ),
                        border: Border.all(
                          color: AppColors.error.withValues(alpha: 0.22),
                        ),
                      ),
                      child: Text(
                        error!,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: const Color(0xFFFFD4D9),
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.xl),
                  GradientButton(
                    label: buttonLabel,
                    icon: Icons.login_rounded,
                    onPressed: onPressed,
                    loading: loading,
                    expanded: true,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _AuthHighlight extends StatelessWidget {
  const _AuthHighlight({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 10,
          height: 10,
          margin: const EdgeInsets.only(top: 5),
          decoration: const BoxDecoration(
            gradient: AppGradients.primaryButton,
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text(text, style: Theme.of(context).textTheme.bodyMedium),
        ),
      ],
    );
  }
}

typedef LoadingStateView = LoadingState;
typedef ErrorStateView = ErrorState;
typedef EmptyStateView = EmptyState;

String prettyDate(dynamic raw) {
  final date = DateTime.tryParse(raw?.toString() ?? '');
  if (date == null) {
    return '--';
  }
  return DateFormat('dd MMM yyyy').format(date);
}

String prettyDateTime(dynamic raw) {
  final date = DateTime.tryParse(raw?.toString() ?? '');
  if (date == null) {
    return '--';
  }
  return DateFormat('dd MMM, hh:mm a').format(date);
}
