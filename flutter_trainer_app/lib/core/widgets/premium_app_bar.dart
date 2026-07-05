import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

class PremiumAppBar extends StatelessWidget implements PreferredSizeWidget {
  const PremiumAppBar({
    super.key,
    required this.title,
    this.subtitle,
    this.actions,
  });

  final String title;
  final String? subtitle;
  final List<Widget>? actions;

  @override
  Size get preferredSize => const Size.fromHeight(92);

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surfaceOverlay.withValues(alpha: 0.88),
        border: Border(
          bottom: BorderSide(
            color: AppColors.strokeStrong.withValues(alpha: 0.72),
          ),
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.4),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: AppBar(
        toolbarHeight: preferredSize.height,
        titleSpacing: AppSpacing.lg,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Text(title, style: Theme.of(context).textTheme.headlineSmall),
            if (subtitle != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  subtitle!,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
          ],
        ),
        actions: actions,
      ),
    );
  }
}
