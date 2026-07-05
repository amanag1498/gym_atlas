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
  Size get preferredSize => Size.fromHeight(subtitle == null ? 86 : 102);

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final canPop = Navigator.of(context).canPop();

    return Container(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.md,
        AppSpacing.lg,
        AppSpacing.sm,
      ),
      child: Column(
        children: [
          Row(
            children: [
              if (leading != null)
                leading!
              else if (canPop)
                _AppBarIconButton(
                  icon: Icons.arrow_back_ios_new_rounded,
                  onTap: () => Navigator.of(context).maybePop(),
                )
              else
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(18),
                    gradient: const LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [AppColors.primaryBright, AppColors.primary],
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.primaryBright.withValues(alpha: 0.28),
                        blurRadius: 24,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.fitness_center_rounded,
                    color: Colors.white,
                    size: 22,
                  ),
                ),
              const Spacer(),
              if (actions != null && actions!.isNotEmpty)
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: actions!.map(_wrapAction).toList(),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(AppSpacing.lg),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(28),
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Colors.white.withValues(alpha: 0.82),
                  Colors.white.withValues(alpha: 0.64),
                ],
              ),
              border: Border.all(color: Colors.white.withValues(alpha: 0.72)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.05),
                  blurRadius: 20,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    subtitle!,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.textSecondary,
                      height: 1.35,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _wrapAction(Widget action) {
    if (action is IconButton) {
      return _AppBarIconButton(
        icon: (action.icon as Icon?)?.icon ?? Icons.circle,
        onTap: action.onPressed,
      );
    }

    return action;
  }
}

class _AppBarIconButton extends StatelessWidget {
  const _AppBarIconButton({
    required this.icon,
    required this.onTap,
  });

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        width: 46,
        height: 46,
        decoration: BoxDecoration(
          color: AppColors.surfaceStrong,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.stroke),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 12,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Icon(icon, color: AppColors.textPrimary, size: 20),
      ),
    );
  }
}
