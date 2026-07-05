import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'premium_card.dart';
import 'status_badge.dart';

class ClientCard extends StatelessWidget {
  const ClientCard({
    super.key,
    required this.name,
    this.goal,
    this.branch,
    this.status,
    this.avatarUrl,
    this.subtitle,
    this.trailing,
    this.onTap,
  });

  final String name;
  final String? goal;
  final String? branch;
  final String? status;
  final String? avatarUrl;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final resolvedStatus = (status ?? '').trim();

    return PremiumCard(
      onTap: onTap,
      glowColor: AppColors.accentNeon,
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 26,
            backgroundColor: AppColors.stroke,
            backgroundImage: (avatarUrl ?? '').trim().isNotEmpty
                ? NetworkImage(avatarUrl!)
                : null,
            child: (avatarUrl ?? '').trim().isNotEmpty
                ? null
                : const Icon(Icons.person_rounded),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                if ((subtitle ?? '').trim().isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    subtitle!,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
                const SizedBox(height: AppSpacing.sm),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (resolvedStatus.isNotEmpty)
                      StatusBadge(label: resolvedStatus),
                    if ((goal ?? '').trim().isNotEmpty)
                      StatusBadge(
                        label: goal!,
                        color: AppColors.accentPurple,
                      ),
                    if ((branch ?? '').trim().isNotEmpty)
                      StatusBadge(
                        label: branch!,
                        color: AppColors.primaryBright,
                      ),
                  ],
                ),
              ],
            ),
          ),
          if (trailing != null) ...[
            const SizedBox(width: AppSpacing.sm),
            trailing!,
          ],
        ],
      ),
    );
  }
}
