import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'gradient_button.dart';
import 'premium_card.dart';
import 'status_badge.dart';

class TaskCard extends StatelessWidget {
  const TaskCard({
    super.key,
    required this.title,
    required this.description,
    this.status,
    this.dueLabel,
    this.icon = Icons.task_alt_rounded,
    this.onTap,
    this.actionLabel,
    this.onActionPressed,
  });

  final String title;
  final String description;
  final String? status;
  final String? dueLabel;
  final IconData icon;
  final VoidCallback? onTap;
  final String? actionLabel;
  final VoidCallback? onActionPressed;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      onTap: onTap,
      glowColor: AppColors.primaryBright,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(icon, color: AppColors.primaryBright),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      description,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if ((status ?? '').trim().isNotEmpty) StatusBadge(label: status!),
              if ((dueLabel ?? '').trim().isNotEmpty)
                StatusBadge(
                  label: dueLabel!,
                  color: AppColors.warning,
                ),
            ],
          ),
          if ((actionLabel ?? '').trim().isNotEmpty &&
              onActionPressed != null) ...[
            const SizedBox(height: AppSpacing.md),
            GradientButton(
              label: actionLabel!,
              icon: Icons.arrow_forward_rounded,
              expanded: true,
              onPressed: onActionPressed,
            ),
          ],
        ],
      ),
    );
  }
}
