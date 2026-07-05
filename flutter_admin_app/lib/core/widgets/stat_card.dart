import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'premium_card.dart';

class StatCard extends StatelessWidget {
  const StatCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.caption,
    this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final String? caption;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final accent = color ?? AppColors.primaryBright;
    return PremiumCard(
      glowColor: accent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: <Color>[
                  accent.withValues(alpha: 0.18),
                  AppColors.surfaceSoft,
                ],
              ),
              borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
            ),
            child: Icon(icon, color: accent),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(
            value,
            style: Theme.of(
              context,
            ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: AppSpacing.xs),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  label,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
            ],
          ),
          if ((caption ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(caption!, style: Theme.of(context).textTheme.bodySmall),
          ],
        ],
      ),
    );
  }
}
