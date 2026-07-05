import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'premium_card.dart';

class QuickActionCard extends StatelessWidget {
  const QuickActionCard({
    super.key,
    required this.title,
    required this.description,
    required this.icon,
    this.color,
    this.onTap,
  });

  final String title;
  final String description;
  final IconData icon;
  final Color? color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final accent = color ?? AppColors.primaryBright;
    return PremiumCard(
      onTap: onTap,
      glowColor: accent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
              color: accent.withValues(alpha: 0.15),
              border: Border.all(color: accent.withValues(alpha: 0.34)),
            ),
            child: Icon(icon, color: accent),
          ),
          const SizedBox(height: AppSpacing.md),
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: AppSpacing.xs),
          Text(description, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}
