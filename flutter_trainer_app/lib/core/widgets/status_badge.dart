import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_radii.dart';

class StatusBadge extends StatelessWidget {
  const StatusBadge({super.key, required this.label, this.color, this.icon});

  final String label;
  final Color? color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final accent = color ?? AppColors.statusColor(label);
    final labelText = Text(
      label,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      softWrap: false,
      style: Theme.of(context).textTheme.labelLarge?.copyWith(color: accent),
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        final bounded = constraints.maxWidth.isFinite;
        final rowChildren = <Widget>[
          if (icon != null) ...[
            Icon(icon, size: 14, color: accent),
            const SizedBox(width: 6),
          ],
          if (bounded) Expanded(child: labelText) else labelText,
        ];

        return Container(
          constraints: bounded
              ? BoxConstraints(maxWidth: constraints.maxWidth)
              : const BoxConstraints(),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.pill),
            color: accent.withValues(alpha: 0.14),
            border: Border.all(color: accent.withValues(alpha: 0.35)),
          ),
          child: Row(
            mainAxisSize: bounded ? MainAxisSize.max : MainAxisSize.min,
            children: rowChildren,
          ),
        );
      },
    );
  }
}
