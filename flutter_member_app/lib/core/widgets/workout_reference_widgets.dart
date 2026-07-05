import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'common_widgets.dart';
import 'premium_card.dart';

class WorkoutReferenceHeroCard extends StatelessWidget {
  const WorkoutReferenceHeroCard({
    super.key,
    required this.title,
    required this.subtitle,
    required this.metricValue,
    required this.metricLabel,
    this.chips = const <Widget>[],
    this.footer,
  });

  final String title;
  final String subtitle;
  final String metricValue;
  final String metricLabel;
  final List<Widget> chips;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: <Color>[
          AppColors.primary.withValues(alpha: 0.24),
          AppColors.primaryBright.withValues(alpha: 0.18),
          const Color(0xFF0E1622),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: Theme.of(context).textTheme.headlineMedium,
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      subtitle,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              _WorkoutMetricOrb(
                value: metricValue,
                label: metricLabel,
              ),
            ],
          ),
          if (chips.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.sm,
              children: chips,
            ),
          ],
          if (footer != null) ...[
            const SizedBox(height: AppSpacing.lg),
            footer!,
          ],
        ],
      ),
    );
  }
}

class WorkoutReferenceSection extends StatelessWidget {
  const WorkoutReferenceSection({
    super.key,
    required this.title,
    this.trailing,
    this.subtitle,
    required this.child,
  });

  final String title;
  final Widget? trailing;
  final String? subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge),
                    if ((subtitle ?? '').trim().isNotEmpty) ...[
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        subtitle!,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ],
                ),
              ),
              if (trailing != null) trailing!,
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          child,
        ],
      ),
    );
  }
}

class WorkoutReferenceScheduleRow extends StatelessWidget {
  const WorkoutReferenceScheduleRow({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    this.trailing,
    this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      onTap: onTap,
      padding: const EdgeInsets.all(AppSpacing.md),
      borderRadius: 20,
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              gradient: LinearGradient(
                colors: <Color>[
                  AppColors.primaryBright.withValues(alpha: 0.24),
                  AppColors.primary.withValues(alpha: 0.18),
                ],
              ),
            ),
            child: Icon(icon, color: AppColors.primary),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 4),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}

class WorkoutReferenceFocusCard extends StatelessWidget {
  const WorkoutReferenceFocusCard({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    this.actionLabel,
    this.onPressed,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final String? actionLabel;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: LinearGradient(
          colors: <Color>[
            AppColors.primaryBright.withValues(alpha: 0.22),
            AppColors.primary.withValues(alpha: 0.18),
          ],
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: AppSpacing.xs),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
                if ((actionLabel ?? '').trim().isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.md),
                  SizedBox(
                    height: 32,
                    child: TextButton(
                      onPressed: onPressed,
                      style: TextButton.styleFrom(
                        backgroundColor: Colors.white,
                        foregroundColor: AppColors.primary,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: Text(actionLabel!),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.58),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 42, color: AppColors.textPrimary),
          ),
        ],
      ),
    );
  }
}

class WorkoutReferenceSummaryTile extends StatelessWidget {
  const WorkoutReferenceSummaryTile({
    super.key,
    required this.label,
    required this.value,
    required this.caption,
    required this.icon,
  });

  final String label;
  final String value;
  final String caption;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.94),
        borderRadius: BorderRadius.circular(22),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x141D1617),
            blurRadius: 16,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AppColors.primary),
          const SizedBox(height: AppSpacing.md),
          Text(value, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(label, style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 2),
          Text(caption, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

class WorkoutReferenceExerciseSection extends StatelessWidget {
  const WorkoutReferenceExerciseSection({
    super.key,
    required this.title,
    required this.countLabel,
    required this.children,
  });

  final String title;
  final String countLabel;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return WorkoutReferenceSection(
      title: title,
      trailing: Text(
        countLabel,
        style: Theme.of(context).textTheme.bodySmall,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: children,
      ),
    );
  }
}

class _WorkoutMetricOrb extends StatelessWidget {
  const _WorkoutMetricOrb({
    required this.value,
    required this.label,
  });

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 94,
      height: 94,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white.withValues(alpha: 0.28), width: 8),
      ),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(value, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 2),
            Text(label, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}
