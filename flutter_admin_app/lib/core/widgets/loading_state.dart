import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';
import 'skeleton_loader.dart';

class LoadingSkeleton extends StatelessWidget {
  const LoadingSkeleton({
    super.key,
    this.label,
    this.child,
  });

  final String? label;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: child ??
          ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              const SkeletonDashboardGrid(),
              const SizedBox(height: AppSpacing.lg),
              const SkeletonReportsTable(rows: 4, columns: 5),
              const SizedBox(height: AppSpacing.lg),
              const SkeletonNotificationsList(items: 4),
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

class LoadingState extends StatelessWidget {
  const LoadingState({super.key, this.label});

  final String? label;

  @override
  Widget build(BuildContext context) {
    return LoadingSkeleton(label: label);
  }
}
