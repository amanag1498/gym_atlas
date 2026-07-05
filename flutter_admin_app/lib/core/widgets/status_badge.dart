import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_radii.dart';

class StatusBadge extends StatelessWidget {
  const StatusBadge({
    super.key,
    required this.label,
    this.color,
    this.icon,
  });

  final String label;
  final Color? color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final accent = color ?? _statusColorForLabel(label);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadii.pill),
        color: accent.withValues(alpha: 0.14),
        border: Border.all(color: accent.withValues(alpha: 0.35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 14, color: accent),
            const SizedBox(width: 6),
          ],
          Text(
            label,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppColors.textPrimary,
            ),
          ),
        ],
      ),
    );
  }
}

Color _statusColorForLabel(String label) {
  final normalized = label.trim().toLowerCase().replaceAll('_', ' ');

  if ({
    'active',
    'paid',
    'accepted',
    'completed',
    'converted',
    'open',
  }.contains(normalized)) {
    return AppColors.success;
  }

  if ({
    'inactive',
    'expired',
    'cancelled',
    'overdue',
    'rejected',
    'closed',
    'private',
  }.contains(normalized)) {
    return AppColors.error;
  }

  if ({
    'expiring soon',
    'due',
    'partial',
    'unpaid',
    'frozen',
    'trial',
  }.contains(normalized)) {
    return AppColors.warning;
  }

  if ({
    'pending',
    'unverified',
  }.contains(normalized)) {
    return AppColors.primary;
  }

  if ({
    'verified',
    'public',
  }.contains(normalized)) {
    return AppColors.primaryBright;
  }

  if (normalized == 'featured') {
    return const Color(0xFFA78BFA);
  }

  if (normalized == 'promoted') {
    return const Color(0xFFF5C451);
  }

  return AppColors.primary;
}
