import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

class PremiumAppBar extends StatelessWidget implements PreferredSizeWidget {
  const PremiumAppBar({
    super.key,
    required this.title,
    this.subtitle,
    this.actions,
    this.leading,
  });

  final String title;
  final String? subtitle;
  final List<Widget>? actions;
  final Widget? leading;

  @override
  Size get preferredSize => const Size.fromHeight(96);

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.94),
        border: Border(
          bottom: BorderSide(
            color: AppColors.strokeStrong.withValues(alpha: 0.78),
          ),
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow,
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: AppBar(
        automaticallyImplyLeading: leading == null,
        leading: leading,
        toolbarHeight: preferredSize.height,
        titleSpacing: AppSpacing.lg,
        backgroundColor: Colors.transparent,
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
