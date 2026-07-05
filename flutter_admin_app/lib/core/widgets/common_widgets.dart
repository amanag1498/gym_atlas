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

export 'admin_list_tile.dart';
export 'animated_page_wrapper.dart';
export 'confirmation_dialog.dart';
export 'empty_state.dart';
export 'error_state.dart';
export 'filter_chip_bar.dart';
export 'gradient_button.dart';
export 'loading_state.dart';
export 'loading_state.dart' show LoadingSkeleton, LoadingState;
export 'permission_gate.dart';
export 'premium_app_bar.dart';
export 'premium_card.dart';
export 'pulse_glow.dart';
export 'quick_action_card.dart';
export 'quick_action_button.dart';
export 'reveal_on_build.dart';
export 'skeleton_loader.dart';
export 'stat_card.dart';
export 'status_badge.dart';

class FitModalSurface extends StatelessWidget {
  const FitModalSurface({
    super.key,
    required this.title,
    required this.child,
    this.subtitle,
    this.icon = Icons.admin_panel_settings_rounded,
    this.isDialog = false,
    this.showClose = true,
    this.padding = const EdgeInsets.fromLTRB(20, 16, 20, 20),
  });

  final String title;
  final String? subtitle;
  final IconData icon;
  final Widget child;
  final bool isDialog;
  final bool showClose;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final radius = isDialog
        ? BorderRadius.circular(30)
        : const BorderRadius.vertical(top: Radius.circular(30));

    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: radius,
        border: Border.all(color: AppColors.strokeStrong),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.12),
            blurRadius: 34,
            offset: const Offset(0, -8),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
          ),
          child: Padding(
            padding: padding,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 42,
                    height: 5,
                    decoration: BoxDecoration(
                      color: AppColors.strokeStrong,
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        gradient: AppGradients.primaryButton,
                        borderRadius: BorderRadius.circular(
                          AppSpacing.radiusMd,
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primary.withValues(alpha: 0.24),
                            blurRadius: 18,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: Icon(icon, color: Colors.white, size: 24),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            title,
                            style: Theme.of(context).textTheme.headlineSmall
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          if (subtitle != null) ...[
                            const SizedBox(height: 4),
                            Text(
                              subtitle!,
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ],
                        ],
                      ),
                    ),
                    if (showClose)
                      IconButton.filledTonal(
                        onPressed: () => Navigator.of(context).maybePop(),
                        icon: const Icon(Icons.close_rounded),
                      ),
                  ],
                ),
                const SizedBox(height: 18),
                Flexible(child: child),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class AppGradientScaffold extends StatelessWidget {
  const AppGradientScaffold({
    super.key,
    required this.child,
    this.appBar,
    this.floatingActionButton,
  });

  final Widget child;
  final PreferredSizeWidget? appBar;
  final Widget? floatingActionButton;

  @override
  Widget build(BuildContext context) {
    return AnimatedPageWrapper(
      appBar: appBar,
      floatingActionButton: floatingActionButton,
      child: child,
    );
  }
}

class AsyncStateView extends StatelessWidget {
  const AsyncStateView({
    super.key,
    required this.isLoading,
    required this.error,
    required this.onRetry,
    required this.child,
    this.emptyTitle,
    this.emptyMessage,
    this.emptyIcon,
    this.emptyAction,
    this.isEmpty = false,
    this.loadingChild,
  });

  final bool isLoading;
  final String? error;
  final VoidCallback onRetry;
  final Widget child;
  final String? emptyTitle;
  final String? emptyMessage;
  final IconData? emptyIcon;
  final Widget? emptyAction;
  final bool isEmpty;
  final Widget? loadingChild;

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return loadingChild ??
          const LoadingState(label: 'Syncing the latest gym operations data.');
    }

    if (error != null) {
      return ErrorState(message: error!, onRetry: onRetry);
    }

    if (isEmpty) {
      return EmptyState(
        title: emptyTitle ?? 'Nothing here yet',
        message: emptyMessage ?? 'No data available yet.',
        icon: emptyIcon ?? Icons.inbox_rounded,
        action: emptyAction,
      );
    }

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 240),
      transitionBuilder: (child, animation) {
        final curved = CurvedAnimation(
          parent: animation,
          curve: Curves.easeOutCubic,
          reverseCurve: Curves.easeInCubic,
        );

        return FadeTransition(
          opacity: curved,
          child: SlideTransition(
            position: Tween<Offset>(
              begin: const Offset(0, 0.025),
              end: Offset.zero,
            ).animate(curved),
            child: child,
          ),
        );
      },
      child: KeyedSubtree(
        key: ValueKey<String>('ready-${emptyMessage ?? 'content'}'),
        child: child,
      ),
    );
  }
}

class DashboardStatCard extends StatelessWidget {
  const DashboardStatCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return StatCard(label: label, value: value, icon: icon);
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

class AppPrimaryButton extends StatelessWidget {
  const AppPrimaryButton({
    super.key,
    required this.label,
    this.icon,
    this.onPressed,
    this.loading = false,
  });

  final String label;
  final IconData? icon;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return GradientButton(
      label: label,
      icon: icon,
      onPressed: onPressed,
      loading: loading,
      expanded: true,
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
            color: Colors.white,
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
                          'Workspace includes',
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
                          color: AppColors.error,
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.xl),
                  AppPrimaryButton(
                    label: buttonLabel,
                    icon: Icons.login_rounded,
                    loading: loading,
                    onPressed: onPressed,
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
