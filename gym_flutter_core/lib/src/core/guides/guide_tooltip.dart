import 'package:flutter/material.dart';

import 'guide_definitions.dart';

/// Guide surfaces intentionally contrast with the light app underneath them.
abstract final class GuideColors {
  static const surface = Color(0xFF0C1427);
  static const surfaceHighlight = Color(0xFF192B4D);
  static const border = Color(0xFF384E76);
  static const text = Color(0xFFF5F8FF);
  static const secondary = Color(0xFFBAC9E3);
  static const accent = Color(0xFF465FFF);
  static const accentDeep = Color(0xFF3641F5);
  static const spotlight = Color(0xFFACC8FF);
  static const scrim = Color(0xFF020817);
}

/// Presentation only: the controller owns navigation, persistence and analytics.
class GuideTooltip extends StatelessWidget {
  const GuideTooltip({
    super.key,
    required this.guide,
    required this.index,
    required this.onSkip,
    required this.onBack,
    required this.onNext,
  });

  final GuideDefinition guide;
  final int index;
  final VoidCallback onSkip;
  final VoidCallback? onBack;
  final VoidCallback? onNext;

  IconData get _icon {
    if (guide.id.contains('rest')) return Icons.timer_outlined;
    if (guide.id.contains('metrics')) return Icons.insights_rounded;
    if (guide.id.contains('notifications')) return Icons.notifications_outlined;
    if (guide.id.contains('workout') || guide.id.contains('builder')) {
      return Icons.fitness_center_rounded;
    }
    if (guide.id.contains('assignment') || guide.id.contains('member_v')) {
      return Icons.people_outline_rounded;
    }
    return Icons.explore_outlined;
  }

  @override
  Widget build(BuildContext context) {
    final step = guide.steps[index];
    final textTheme = Theme.of(context).textTheme;
    final quietButtonStyle = TextButton.styleFrom(
      foregroundColor: GuideColors.secondary,
      disabledForegroundColor: GuideColors.secondary.withValues(alpha: .45),
      minimumSize: const Size(48, 52),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      textStyle: textTheme.labelLarge?.copyWith(
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    );
    return Card(
      margin: EdgeInsets.zero,
      elevation: 24,
      shadowColor: GuideColors.scrim.withValues(alpha: .55),
      color: GuideColors.surface,
      surfaceTintColor: Colors.transparent,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(28),
        side: const BorderSide(color: GuideColors.border),
      ),
      child: Ink(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [GuideColors.surfaceHighlight, GuideColors.surface],
            stops: [0, .8],
          ),
        ),
        child: FocusTraversalGroup(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(24, 24, 24, 22),
                  child: Semantics(
                    liveRegion: true,
                    namesRoute: true,
                    label:
                        '${index + 1} of ${guide.steps.length}. ${step.title}. ${step.description}',
                    child: ExcludeSemantics(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                width: 40,
                                height: 40,
                                decoration: BoxDecoration(
                                  color: GuideColors.spotlight.withValues(
                                    alpha: .09,
                                  ),
                                  borderRadius: BorderRadius.circular(13),
                                  border: Border.all(
                                    color: GuideColors.spotlight.withValues(
                                      alpha: .18,
                                    ),
                                  ),
                                ),
                                child: Icon(
                                  _icon,
                                  size: 22,
                                  color: GuideColors.spotlight,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      guide.title,
                                      style: textTheme.labelMedium?.copyWith(
                                        color: GuideColors.secondary,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w500,
                                        height: 1.4,
                                      ),
                                    ),
                                    const SizedBox(height: 3),
                                    Text(
                                      '${index + 1} of ${guide.steps.length}',
                                      style: textTheme.labelLarge?.copyWith(
                                        color: GuideColors.spotlight,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w700,
                                        height: 1.3,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 18),
                          Row(
                            children: [
                              for (var i = 0; i < guide.steps.length; i++)
                                Expanded(
                                  child: Padding(
                                    padding: EdgeInsets.only(
                                      right: i == guide.steps.length - 1
                                          ? 0
                                          : 5,
                                    ),
                                    child: Container(
                                      height: 3,
                                      decoration: BoxDecoration(
                                        color: i <= index
                                            ? GuideColors.spotlight
                                            : GuideColors.spotlight.withValues(
                                                alpha: .14,
                                              ),
                                        borderRadius: BorderRadius.circular(2),
                                      ),
                                    ),
                                  ),
                                ),
                            ],
                          ),
                          const SizedBox(height: 22),
                          Text(
                            step.title,
                            style: textTheme.headlineSmall?.copyWith(
                              color: GuideColors.text,
                              fontSize: 24,
                              fontWeight: FontWeight.w700,
                              height: 1.2,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            step.description,
                            style: textTheme.bodyLarge?.copyWith(
                              color: GuideColors.secondary,
                              fontSize: 15,
                              height: 1.55,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              Divider(
                height: 1,
                thickness: 1,
                color: GuideColors.spotlight.withValues(alpha: .12),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 12, 16, 16),
                child: Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 4,
                  runSpacing: 8,
                  children: [
                    TextButton(
                      style: quietButtonStyle,
                      onPressed: onSkip,
                      child: const Text('Skip'),
                    ),
                    if (index > 0)
                      TextButton(
                        style: quietButtonStyle,
                        onPressed: onBack,
                        child: const Text('Back'),
                      ),
                    DecoratedBox(
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(16),
                        gradient: LinearGradient(
                          colors: onNext == null
                              ? [
                                  GuideColors.border,
                                  GuideColors.surfaceHighlight,
                                ]
                              : [GuideColors.accent, GuideColors.accentDeep],
                        ),
                        boxShadow: onNext == null
                            ? []
                            : [
                                BoxShadow(
                                  color: GuideColors.accent.withValues(
                                    alpha: .22,
                                  ),
                                  blurRadius: 18,
                                  offset: const Offset(0, 5),
                                ),
                              ],
                      ),
                      child: FilledButton.icon(
                        style: FilledButton.styleFrom(
                          foregroundColor: GuideColors.text,
                          disabledForegroundColor: GuideColors.secondary,
                          backgroundColor: Colors.transparent,
                          disabledBackgroundColor: Colors.transparent,
                          shadowColor: Colors.transparent,
                          minimumSize: const Size(112, 52),
                          padding: const EdgeInsets.symmetric(horizontal: 18),
                          textStyle: textTheme.labelLarge?.copyWith(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        autofocus: true,
                        onPressed: onNext,
                        iconAlignment: IconAlignment.end,
                        icon: Icon(
                          index == guide.steps.length - 1
                              ? Icons.check_rounded
                              : Icons.arrow_forward_rounded,
                          size: 18,
                        ),
                        label: Text(
                          index == guide.steps.length - 1 ? 'Finish' : 'Next',
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
