import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'premium_card.dart';

class SkeletonLoader extends StatefulWidget {
  const SkeletonLoader({super.key, this.lines = 3, this.showAvatar = false});

  final int lines;
  final bool showAvatar;

  @override
  State<SkeletonLoader> createState() => _SkeletonLoaderState();
}

class _SkeletonLoaderState extends State<SkeletonLoader>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1400),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        final alpha = 0.08 + (_controller.value * 0.10);
        return _SkeletonScope(
          alpha: alpha,
          child: PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (widget.showAvatar) ...[
                  const SkeletonCircle(size: 52),
                  const SizedBox(height: AppSpacing.md),
                ],
                for (var index = 0; index < widget.lines; index++) ...[
                  SkeletonLine(
                    widthFactor: index == widget.lines - 1 ? 0.42 : 1,
                  ),
                  if (index != widget.lines - 1)
                    const SizedBox(height: AppSpacing.sm),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}

class _SkeletonScope extends InheritedWidget {
  const _SkeletonScope({required this.alpha, required super.child});

  final double alpha;

  static double alphaOf(BuildContext context) {
    return context
            .dependOnInheritedWidgetOfExactType<_SkeletonScope>()
            ?.alpha ??
        0.12;
  }

  @override
  bool updateShouldNotify(_SkeletonScope oldWidget) => alpha != oldWidget.alpha;
}

class SkeletonPulse extends StatefulWidget {
  const SkeletonPulse({super.key, required this.child});

  final Widget child;

  @override
  State<SkeletonPulse> createState() => _SkeletonPulseState();
}

class _SkeletonPulseState extends State<SkeletonPulse>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1400),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) => _SkeletonScope(
        alpha: 0.08 + (_controller.value * 0.10),
        child: widget.child,
      ),
    );
  }
}

class SkeletonBox extends StatelessWidget {
  const SkeletonBox({
    super.key,
    this.height = 16,
    this.width,
    this.radius = 999,
  });

  final double height;
  final double? width;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final alpha = _SkeletonScope.alphaOf(context);

    return Container(
      height: height,
      width: width,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(radius),
        color: AppColors.surfaceSoft.withValues(alpha: alpha),
        border: Border.all(
          color: AppColors.strokeStrong.withValues(alpha: alpha * 0.9),
        ),
      ),
    );
  }
}

class SkeletonLine extends StatelessWidget {
  const SkeletonLine({super.key, this.widthFactor = 1, this.height = 14});

  final double widthFactor;
  final double height;

  @override
  Widget build(BuildContext context) {
    return FractionallySizedBox(
      widthFactor: widthFactor.clamp(0.1, 1),
      alignment: Alignment.centerLeft,
      child: SkeletonBox(height: height),
    );
  }
}

class SkeletonCircle extends StatelessWidget {
  const SkeletonCircle({super.key, this.size = 44});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SkeletonBox(height: size, width: size, radius: size);
  }
}

class SkeletonDashboardGrid extends StatelessWidget {
  const SkeletonDashboardGrid({super.key, this.count = 4});

  final int count;

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: AppSpacing.md,
        mainAxisSpacing: AppSpacing.md,
        childAspectRatio: 1.18,
      ),
      itemCount: count,
      itemBuilder: (context, index) => PremiumCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
            SkeletonCircle(size: 34),
            SizedBox(height: AppSpacing.lg),
            SkeletonLine(widthFactor: 0.72, height: 13),
            SizedBox(height: AppSpacing.sm),
            SkeletonLine(widthFactor: 0.44, height: 28),
            SizedBox(height: AppSpacing.sm),
            SkeletonLine(widthFactor: 0.88, height: 12),
          ],
        ),
      ),
    );
  }
}

class SkeletonListCard extends StatelessWidget {
  const SkeletonListCard({
    super.key,
    this.showAvatar = true,
    this.trailingBadge = true,
    this.lines = 3,
  });

  final bool showAvatar;
  final bool trailingBadge;
  final int lines;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (showAvatar) ...[
            const SkeletonCircle(size: 52),
            const SizedBox(width: AppSpacing.md),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SkeletonLine(widthFactor: 0.56),
                          SizedBox(height: AppSpacing.sm),
                          SkeletonLine(widthFactor: 0.78, height: 12),
                        ],
                      ),
                    ),
                    if (trailingBadge) ...[
                      const SizedBox(width: AppSpacing.md),
                      const SkeletonBox(height: 28, width: 76),
                    ],
                  ],
                ),
                const SizedBox(height: AppSpacing.md),
                for (var index = 0; index < lines; index++) ...[
                  SkeletonLine(widthFactor: index == lines - 1 ? 0.48 : 1),
                  if (index != lines - 1) const SizedBox(height: AppSpacing.sm),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class SkeletonDiscoveryCard extends StatelessWidget {
  const SkeletonDiscoveryCard({super.key});

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SkeletonBox(height: 180, radius: AppSpacing.radiusLg),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SkeletonLine(widthFactor: 0.62),
                const SizedBox(height: AppSpacing.sm),
                const SkeletonLine(widthFactor: 0.38, height: 12),
                const SizedBox(height: AppSpacing.md),
                Wrap(
                  spacing: AppSpacing.sm,
                  runSpacing: AppSpacing.sm,
                  children: const [
                    SkeletonBox(height: 28, width: 72),
                    SkeletonBox(height: 28, width: 92),
                    SkeletonBox(height: 28, width: 66),
                  ],
                ),
                const SizedBox(height: AppSpacing.lg),
                const SkeletonBox(height: 44, radius: 16),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class SkeletonWorkoutCard extends StatelessWidget {
  const SkeletonWorkoutCard({super.key});

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          SkeletonLine(widthFactor: 0.54),
          SizedBox(height: AppSpacing.sm),
          SkeletonLine(widthFactor: 0.3, height: 12),
          SizedBox(height: AppSpacing.lg),
          SkeletonBox(height: 52, radius: 18),
          SizedBox(height: AppSpacing.lg),
          SkeletonLine(widthFactor: 0.76),
          SizedBox(height: AppSpacing.sm),
          SkeletonLine(widthFactor: 0.92),
          SizedBox(height: AppSpacing.sm),
          SkeletonLine(widthFactor: 0.48),
        ],
      ),
    );
  }
}

class SkeletonProfileHeader extends StatelessWidget {
  const SkeletonProfileHeader({super.key});

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SkeletonCircle(size: 68),
          const SizedBox(width: AppSpacing.md),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SkeletonLine(widthFactor: 0.46, height: 20),
                SizedBox(height: AppSpacing.sm),
                SkeletonLine(widthFactor: 0.68, height: 12),
                SizedBox(height: AppSpacing.md),
                SkeletonLine(widthFactor: 0.8),
                SizedBox(height: AppSpacing.sm),
                SkeletonLine(widthFactor: 0.5),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class SkeletonHistoryList extends StatelessWidget {
  const SkeletonHistoryList({super.key, this.items = 4});

  final int items;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: List.generate(items, (index) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: index == items - 1 ? 0 : AppSpacing.md,
          ),
          child: const SkeletonListCard(
            showAvatar: false,
            trailingBadge: false,
            lines: 2,
          ),
        );
      }),
    );
  }
}

class SkeletonNotificationsList extends StatelessWidget {
  const SkeletonNotificationsList({super.key, this.items = 5});

  final int items;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: List.generate(items, (index) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: index == items - 1 ? 0 : AppSpacing.md,
          ),
          child: const SkeletonListCard(
            showAvatar: false,
            trailingBadge: true,
            lines: 3,
          ),
        );
      }),
    );
  }
}

class SkeletonReportsTable extends StatelessWidget {
  const SkeletonReportsTable({super.key, this.rows = 5, this.columns = 4});

  final int rows;
  final int columns;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Column(
        children: [
          for (var row = 0; row < rows; row++) ...[
            Row(
              children: List.generate(columns, (column) {
                return Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(
                      right: column == columns - 1 ? 0 : AppSpacing.sm,
                    ),
                    child: SkeletonBox(height: row == 0 ? 16 : 14, radius: 10),
                  ),
                );
              }),
            ),
            if (row != rows - 1) const SizedBox(height: AppSpacing.md),
          ],
        ],
      ),
    );
  }
}
