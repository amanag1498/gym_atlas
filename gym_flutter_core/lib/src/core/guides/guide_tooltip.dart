import 'dart:ui';

import 'package:flutter/material.dart';

import 'guide_definitions.dart';

/// Guide surfaces intentionally contrast with the light app underneath them.
abstract final class GuideColors {
  static const surface = Color(0xD911141D);
  static const surfaceHighlight = Color(0xB52C2A25);
  static const border = Color(0x52FFFFFF);
  static const text = Color(0xFFFCFBF7);
  static const secondary = Color(0xFFD8D2C5);
  static const accent = Color(0xFFE8D6B1);
  static const accentDeep = Color(0xFFB99053);
  static const spotlight = Color(0xFFFFF3D4);
  static const scrim = Color(0xFF080706);
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
      minimumSize: const Size(48, 48),
      padding: const EdgeInsets.symmetric(horizontal: 10),
      textStyle: textTheme.labelMedium?.copyWith(
        fontSize: 13,
        fontWeight: FontWeight.w700,
      ),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
    );

    return ClipRRect(
      borderRadius: BorderRadius.circular(26),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: GuideColors.surface,
            borderRadius: BorderRadius.circular(26),
            border: Border.all(color: GuideColors.border),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: .35),
                blurRadius: 30,
                offset: const Offset(0, 16),
              ),
              BoxShadow(
                color: GuideColors.accent.withValues(alpha: .10),
                blurRadius: 24,
                offset: const Offset(0, 0),
              ),
            ],
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                Colors.white.withValues(alpha: .12),
                GuideColors.surfaceHighlight,
                Colors.white.withValues(alpha: .05),
              ],
              stops: const [0, .52, 1],
            ),
          ),
          child: Material(
            type: MaterialType.transparency,
            child: FocusTraversalGroup(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Flexible(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(18, 16, 18, 12),
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
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  Container(
                                    width: 34,
                                    height: 34,
                                    decoration: BoxDecoration(
                                      color: Colors.white.withValues(
                                        alpha: .08,
                                      ),
                                      borderRadius: BorderRadius.circular(999),
                                      border: Border.all(
                                        color: Colors.white.withValues(
                                          alpha: .14,
                                        ),
                                      ),
                                    ),
                                    child: Icon(
                                      _icon,
                                      size: 18,
                                      color: GuideColors.spotlight,
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          guide.title,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: textTheme.labelMedium
                                              ?.copyWith(
                                                color: GuideColors.secondary,
                                                fontSize: 11,
                                                letterSpacing: .3,
                                                fontWeight: FontWeight.w700,
                                                height: 1.25,
                                              ),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          '${index + 1} / ${guide.steps.length}',
                                          style: textTheme.labelSmall?.copyWith(
                                            color: GuideColors.spotlight,
                                            fontSize: 12,
                                            fontWeight: FontWeight.w800,
                                            height: 1.2,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  for (var i = 0; i < guide.steps.length; i++)
                                    Expanded(
                                      child: Padding(
                                        padding: EdgeInsets.only(
                                          right: i == guide.steps.length - 1
                                              ? 0
                                              : 4,
                                        ),
                                        child: Container(
                                          height: 2,
                                          decoration: BoxDecoration(
                                            color: i <= index
                                                ? GuideColors.accent
                                                : Colors.white.withValues(
                                                    alpha: .14,
                                                  ),
                                            borderRadius: BorderRadius.circular(
                                              2,
                                            ),
                                          ),
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 14),
                              Text(
                                step.title,
                                style: textTheme.titleMedium?.copyWith(
                                  color: GuideColors.text,
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  height: 1.18,
                                ),
                              ),
                              const SizedBox(height: 7),
                              Text(
                                step.description,
                                style: textTheme.bodyMedium?.copyWith(
                                  color: GuideColors.secondary,
                                  fontSize: 13,
                                  height: 1.42,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 4, 10, 10),
                    child: Wrap(
                      alignment: WrapAlignment.spaceBetween,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 2,
                      runSpacing: 6,
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
                            borderRadius: BorderRadius.circular(999),
                            gradient: LinearGradient(
                              colors: onNext == null
                                  ? [
                                      Colors.white.withValues(alpha: .10),
                                      Colors.white.withValues(alpha: .06),
                                    ]
                                  : [
                                      GuideColors.accent,
                                      GuideColors.accentDeep,
                                    ],
                            ),
                            boxShadow: onNext == null
                                ? []
                                : [
                                    BoxShadow(
                                      color: GuideColors.accent.withValues(
                                        alpha: .24,
                                      ),
                                      blurRadius: 16,
                                      offset: const Offset(0, 6),
                                    ),
                                  ],
                          ),
                          child: FilledButton.icon(
                            style: FilledButton.styleFrom(
                              foregroundColor: const Color(0xFF17120A),
                              disabledForegroundColor: GuideColors.secondary,
                              backgroundColor: Colors.transparent,
                              disabledBackgroundColor: Colors.transparent,
                              shadowColor: Colors.transparent,
                              minimumSize: const Size(96, 48),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 15,
                              ),
                              textStyle: textTheme.labelMedium?.copyWith(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                              ),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(999),
                              ),
                            ),
                            autofocus: true,
                            onPressed: onNext,
                            iconAlignment: IconAlignment.end,
                            icon: Icon(
                              index == guide.steps.length - 1
                                  ? Icons.check_rounded
                                  : Icons.arrow_forward_rounded,
                              size: 17,
                            ),
                            label: Text(
                              index == guide.steps.length - 1
                                  ? 'Finish'
                                  : 'Next',
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
        ),
      ),
    );
  }
}
