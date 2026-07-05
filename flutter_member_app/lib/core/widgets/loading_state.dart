import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';
import 'skeleton_loader.dart';

class LoadingState extends StatelessWidget {
  const LoadingState({super.key, this.label});

  final String? label;

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          const SkeletonProfileHeader(),
          const SizedBox(height: AppSpacing.lg),
          const SkeletonDashboardGrid(),
          const SizedBox(height: AppSpacing.lg),
          const SkeletonWorkoutCard(),
          const SizedBox(height: AppSpacing.md),
          const SkeletonHistoryList(items: 3),
          if (label != null) ...[
            const SizedBox(height: AppSpacing.lg),
            Text(
              label!,
              style: Theme.of(context).textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
    );
  }
}

class LoadingSkeleton extends StatelessWidget {
  const LoadingSkeleton({
    super.key,
    this.lines = 3,
    this.showAvatar = false,
  });

  final int lines;
  final bool showAvatar;

  @override
  Widget build(BuildContext context) {
    return SkeletonLoader(lines: lines, showAvatar: showAvatar);
  }
}
