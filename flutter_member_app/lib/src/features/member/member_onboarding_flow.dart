import 'package:flutter/material.dart';
import 'package:timelines_plus/timelines_plus.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/empty_state.dart';
import '../../../core/widgets/premium_card.dart';
import 'member_repository.dart';

class MemberOnboardingFlow extends StatefulWidget {
  const MemberOnboardingFlow({
    super.key,
    required this.repository,
    required this.profile,
    required this.publicGyms,
    required this.trainerConnection,
    required this.onFinished,
  });

  final MemberRepository repository;
  final Map<String, dynamic> profile;
  final List<Map<String, dynamic>> publicGyms;
  final Map<String, dynamic> trainerConnection;
  final Future<void> Function() onFinished;

  @override
  State<MemberOnboardingFlow> createState() => _MemberOnboardingFlowState();
}

class _MemberOnboardingFlowState extends State<MemberOnboardingFlow> {
  static const _experienceOptions = [
    'Beginner',
    'Intermediate',
    'Advanced',
  ];

  static const _totalSteps = 5;

  int _step = 1;
  bool _saving = false;
  late final TextEditingController _heightController;
  late final TextEditingController _weightController;
  late final TextEditingController _injuryController;
  late final TextEditingController _medicalController;
  late final List<Map<String, dynamic>> _availableGoals;
  late final Set<int> _selectedGoalIds;
  String? _experience;

  @override
  void initState() {
    super.initState();
    _step = ((widget.profile['member_onboarding_step'] as num?)?.toInt() ?? 1)
        .clamp(1, _totalSteps);
    _availableGoals =
        (widget.profile['available_fitness_goals'] as List<dynamic>? ?? const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
    _selectedGoalIds =
        (widget.profile['fitness_goals'] as List<dynamic>? ?? const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .map((item) => (item['id'] as num?)?.toInt())
            .whereType<int>()
            .toSet();
    _experience = widget.profile['experience_level']?.toString();
    _heightController = TextEditingController(
      text: widget.profile['height_cm']?.toString() ?? '',
    );
    _weightController = TextEditingController(
      text: widget.profile['weight_kg']?.toString() ?? '',
    );
    _injuryController = TextEditingController(
      text: widget.profile['injuries_limitations']?.toString() ?? '',
    );
    _medicalController = TextEditingController(
      text: widget.profile['medical_notes']?.toString() ?? '',
    );
  }

  @override
  void dispose() {
    _heightController.dispose();
    _weightController.dispose();
    _injuryController.dispose();
    _medicalController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final currentGym = Map<String, dynamic>.from(
      widget.profile['current_gym'] as Map? ?? const {},
    );
    final assignedTrainer = Map<String, dynamic>.from(
      widget.profile['assigned_trainer'] as Map? ?? const {},
    );

    return SafeArea(
      child: RefreshIndicator(
        onRefresh: widget.onFinished,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.md,
            AppSpacing.md,
            AppSpacing.md,
            AppSpacing.lg,
          ),
          children: [
            _OnboardingHero(
              step: _step,
              totalSteps: _totalSteps,
            ),
            const SizedBox(height: AppSpacing.md),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 240),
              transitionBuilder: (child, animation) {
                final curved = CurvedAnimation(
                  parent: animation,
                  curve: Curves.easeOutCubic,
                );
                return FadeTransition(
                  opacity: curved,
                  child: SlideTransition(
                    position: Tween<Offset>(
                      begin: const Offset(0.04, 0.015),
                      end: Offset.zero,
                    ).animate(curved),
            child: child,
                  ),
                );
              },
              child: PremiumCard(
                key: ValueKey<int>(_step),
                child: _buildStep(
                  context,
                  currentGym: currentGym,
                  assignedTrainer: assignedTrainer,
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Row(
              children: [
                if (_step > 1)
                  Expanded(
                    child: GradientButton(
                      label: 'Back',
                      icon: Icons.arrow_back_rounded,
                      onPressed: _saving ? null : _goBack,
                      expanded: true,
                      variant: GradientButtonVariant.secondary,
                    ),
                  ),
                if (_step > 1) const SizedBox(width: AppSpacing.md),
                Expanded(
                  flex: 2,
                  child: GradientButton(
                    label: _step == _totalSteps
                        ? 'Complete Setup'
                        : _primaryLabel(_step),
                    icon: _step == _totalSteps
                        ? Icons.check_rounded
                        : Icons.arrow_forward_rounded,
                    onPressed: _saving ? null : _handlePrimaryAction,
                    loading: _saving,
                    expanded: true,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStep(
    BuildContext context, {
    required Map<String, dynamic> currentGym,
    required Map<String, dynamic> assignedTrainer,
  }) {
    switch (_step) {
      case 1:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    AppColors.primaryBright.withValues(alpha: 0.30),
                    AppColors.primary.withValues(alpha: 0.22),
                    AppColors.accentPurple.withValues(alpha: 0.18),
                  ],
                ),
                borderRadius: BorderRadius.circular(28),
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.55),
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.10),
                    blurRadius: 24,
                    offset: const Offset(0, 12),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const BrandMark(size: 54),
                      const SizedBox(width: AppSpacing.sm),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 7,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.72),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          'MEMBER SETUP',
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.6,
                              ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    'Set up your training identity in five focused steps.',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: AppColors.textPrimary,
                        ),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    'Your goals, body baseline, and recovery context shape a dashboard that feels personal from day one.',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Row(
                    children: [
                      _WelcomeSignal(
                        icon: Icons.auto_awesome_rounded,
                        label: 'Personalized',
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      _WelcomeSignal(
                        icon: Icons.insights_rounded,
                        label: 'Progress-ready',
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            const _SetupHighlights(),
          ],
        );
      case 2:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (_availableGoals.isEmpty)
              const EmptyState(
                title: 'No goals available yet',
                message:
                    'Ask the platform admin to create at least one active fitness goal in the platform catalog.',
                icon: Icons.flag_circle_rounded,
              )
            else
              LayoutBuilder(
                builder: (context, constraints) {
                  const spacing = AppSpacing.sm;
                  final columns = constraints.maxWidth >= 560 ? 3 : 2;
                  final itemWidth =
                      (constraints.maxWidth - (spacing * (columns - 1))) / columns;
                  final tileHeight = columns == 3 ? 126.0 : 132.0;

                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (_selectedGoalIds.isEmpty)
                        const _GoalSelectionPromptCard()
                      else
                        _ReviewPill(
                          label: '${_selectedGoalIds.length} goals selected',
                        ),
                      const SizedBox(height: AppSpacing.sm),
                      GridView.builder(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: _availableGoals.length,
                        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: columns,
                          crossAxisSpacing: spacing,
                          mainAxisSpacing: spacing,
                          mainAxisExtent: tileHeight,
                        ),
                        itemBuilder: (context, index) {
                          final goal = _availableGoals[index];
                          return _GoalSelectionCard(
                            width: itemWidth,
                            goal: goal,
                            selected: _selectedGoalIds
                                .contains((goal['id'] as num?)?.toInt()),
                            onTap: () =>
                                _toggleGoal((goal['id'] as num?)?.toInt()),
                          );
                        },
                      ),
                    ],
                  );
                },
              ),
          ],
        );
      case 3:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _ChoiceStep(
              options: _experienceOptions,
              selected: _experience,
              onSelect: (value) => setState(() => _experience = value),
            ),
            const SizedBox(height: AppSpacing.md),
            _MetricTile(
              label: 'Height (cm)',
              icon: Icons.height_rounded,
              child: TextField(
                controller: _heightController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  hintText: '173',
                  border: InputBorder.none,
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            _MetricTile(
              label: 'Weight (kg)',
              icon: Icons.monitor_weight_rounded,
              child: TextField(
                controller: _weightController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  hintText: '80',
                  border: InputBorder.none,
                ),
              ),
            ),
          ],
        );
      case 4:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _LabeledTextarea(
              title: 'Injuries or limitations',
              icon: Icons.healing_rounded,
              controller: _injuryController,
              hintText: 'Share anything that affects movement, loading, or pain.',
            ),
            const SizedBox(height: AppSpacing.md),
            _LabeledTextarea(
              title: 'Medical notes',
              icon: Icons.medical_information_rounded,
              controller: _medicalController,
              hintText: 'Medication, conditions, or context your plan should respect.',
            ),
          ],
        );
      case 5:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (currentGym.isNotEmpty)
              _InfoSurface(
                icon: Icons.storefront_rounded,
                title: currentGym['name']?.toString() ?? 'Current gym',
                subtitle: 'Your profile is already linked to this gym.',
              )
            else
              const _InfoSurface(
                icon: Icons.travel_explore_rounded,
                title: 'Independent member mode',
                subtitle:
                    'You can continue without a gym assignment and explore public listings first.',
              ),
            if (widget.publicGyms.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.lg),
              Text(
                'Suggested gyms',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
              ),
              const SizedBox(height: AppSpacing.sm),
              ...widget.publicGyms.take(3).map(
                (gym) => Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                  child: _InfoSurface(
                    icon: Icons.location_city_rounded,
                    title: gym['name']?.toString() ?? 'Gym',
                    subtitle: [
                      gym['city']?.toString(),
                      gym['state']?.toString(),
                    ].where((value) => value != null && value.isNotEmpty).join(', '),
                  ),
                ),
              ),
            ],
            const SizedBox(height: AppSpacing.lg),
            _InfoSurface(
              icon: assignedTrainer.isNotEmpty
                  ? Icons.person_pin_circle_rounded
                  : Icons.person_off_rounded,
              title: assignedTrainer.isNotEmpty
                  ? assignedTrainer['name']?.toString() ?? 'Assigned trainer'
                  : 'Trainer not assigned yet',
              subtitle: assignedTrainer.isNotEmpty
                  ? assignedTrainer['bio']?.toString() ?? 'Ready to guide your plan.'
                  : 'You can still use workouts, progress, and discovery while waiting for trainer assignment.',
              highlighted: assignedTrainer.isNotEmpty,
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              'Complete setup to unlock the member dashboard, progress tracking, and discovery flow with your saved context.',
              style: Theme.of(context).textTheme.bodyLarge,
            ),
          ],
        );
      default:
        return const SizedBox.shrink();
    }
  }

  void _toggleGoal(int? id) {
    if (id == null) {
      return;
    }

    setState(() {
      if (_selectedGoalIds.contains(id)) {
        _selectedGoalIds.remove(id);
      } else {
        _selectedGoalIds.add(id);
      }
    });
  }

  void _goBack() {
    FocusScope.of(context).unfocus();
    setState(() => _step = (_step - 1).clamp(1, _totalSteps));
  }

  Future<void> _handlePrimaryAction() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
    });

    try {
      switch (_step) {
        case 1:
          await _persist({'member_onboarding_step': 2});
          break;
        case 2:
          if (_selectedGoalIds.isEmpty) {
            throw Exception('Select at least one fitness goal to continue.');
          }
          await _persist({
            'fitness_goal_ids': _selectedGoalIds.toList()..sort(),
            'member_onboarding_step': 3,
          });
          break;
        case 3:
          if (_experience == null || _experience!.isEmpty) {
            throw Exception('Select an experience level to continue.');
          }
          await _persist({
            'experience_level': _experience,
            'height_cm': double.tryParse(_heightController.text.trim()),
            'weight_kg': double.tryParse(_weightController.text.trim()),
            'member_onboarding_step': 4,
          });
          break;
        case 4:
          await _persist({
            'injury_notes': _nullableText(_injuryController.text),
            'medical_notes': _nullableText(_medicalController.text),
            'member_onboarding_step': 5,
          });
          break;
        case 5:
          await _persist({
            'member_onboarding_step': 5,
            'member_onboarding_completed': true,
          });
          await widget.onFinished();
          return;
      }
      if (mounted) {
        setState(() => _step = (_step + 1).clamp(1, _totalSteps));
      }
    } catch (exception) {
      if (mounted) {
        _showErrorDialog(
          exception.toString().replaceFirst('Exception: ', ''),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _showErrorDialog(String message) {
    return showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: const EdgeInsets.symmetric(horizontal: 24),
          child: PremiumCard(
            padding: const EdgeInsets.all(18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [
                            AppColors.accentNeon.withValues(alpha: 0.84),
                            AppColors.accentPurple.withValues(alpha: 0.84),
                          ],
                        ),
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.accentPurple.withValues(alpha: 0.12),
                            blurRadius: 12,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      alignment: Alignment.center,
                      child: const Icon(
                        Icons.info_outline_rounded,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 9,
                              vertical: 5,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.accentNeon.withValues(alpha: 0.10),
                              borderRadius: BorderRadius.circular(999),
                              border: Border.all(
                                color: AppColors.accentNeon.withValues(alpha: 0.16),
                              ),
                            ),
                            child: Text(
                              'CHECK INPUT',
                              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                    color: AppColors.textPrimary,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 0.35,
                                  ),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'We could not continue',
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(13),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceStrong,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: AppColors.strokeStrong),
                  ),
                  child: Text(
                    message,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: AppColors.textSecondary,
                          height: 1.3,
                        ),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: GradientButton(
                        label: 'Dismiss',
                        onPressed: () => Navigator.of(dialogContext).pop(),
                        expanded: true,
                        variant: GradientButtonVariant.secondary,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: GradientButton(
                        label: 'Try Again',
                        icon: Icons.refresh_rounded,
                        onPressed: () {
                          Navigator.of(dialogContext).pop();
                          _handlePrimaryAction();
                        },
                        expanded: true,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _persist(Map<String, dynamic> payload) async {
    final cleaned = <String, dynamic>{};
    payload.forEach((key, value) {
      if (value == null) {
        return;
      }
      if (value is List && value.isEmpty) {
        return;
      }
      cleaned[key] = value;
    });
    await widget.repository.updateProfile(cleaned);
  }
}

class _OnboardingHero extends StatelessWidget {
  const _OnboardingHero({
    required this.step,
    required this.totalSteps,
  });

  final int step;
  final int totalSteps;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 76,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final itemExtent = (constraints.maxWidth / totalSteps).toDouble();

          return FixedTimeline.tileBuilder(
            theme: TimelineThemeData(
              direction: Axis.horizontal,
              nodePosition: 0.22,
              connectorTheme: const ConnectorThemeData(
                thickness: 3,
                color: AppColors.strokeStrong,
              ),
              indicatorTheme: const IndicatorThemeData(
                position: 0.22,
                size: 24,
              ),
            ),
            builder: TimelineTileBuilder.connected(
              connectionDirection: ConnectionDirection.before,
              itemExtentBuilder: (_, __) => itemExtent,
              contentsBuilder: (context, index) => Padding(
                padding: const EdgeInsets.only(top: 12),
                child: _AnimatedStepLabel(
                  label: _stepTitle(index + 1),
                  active: index + 1 == step,
                ),
              ),
              connectorBuilder: (_, index, __) {
                final complete = index < step - 1;
                return _AnimatedTimelineConnector(
                  complete: complete,
                );
              },
              indicatorBuilder: (_, index) {
                final itemStep = index + 1;
                final active = itemStep == step;
                final complete = itemStep < step;
                return _AnimatedStepIndicator(
                  stepNumber: itemStep,
                  active: active,
                  complete: complete,
                );
              },
              itemCount: totalSteps,
            ),
          );
        },
      ),
    );
  }
}

class _AnimatedStepLabel extends StatelessWidget {
  const _AnimatedStepLabel({
    required this.label,
    required this.active,
  });

  final String label;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return AnimatedDefaultTextStyle(
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
      textAlign: TextAlign.center,
      style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: active ? AppColors.textPrimary : AppColors.textSecondary,
            fontWeight: active ? FontWeight.w800 : FontWeight.w600,
            height: 1.15,
          ) ??
          const TextStyle(),
      child: Text(
        label,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
    );
  }
}

class _AnimatedTimelineConnector extends StatelessWidget {
  const _AnimatedTimelineConnector({
    required this.complete,
  });

  final bool complete;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOutCubic,
        height: 3,
        decoration: BoxDecoration(
          color: complete ? AppColors.primary : AppColors.strokeStrong,
          borderRadius: BorderRadius.circular(999),
          boxShadow: complete
              ? [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.18),
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  ),
                ]
              : const [],
        ),
      ),
    );
  }
}

class _AnimatedStepIndicator extends StatelessWidget {
  const _AnimatedStepIndicator({
    required this.stepNumber,
    required this.active,
    required this.complete,
  });

  final int stepNumber;
  final bool active;
  final bool complete;

  @override
  Widget build(BuildContext context) {
    final highlighted = active || complete;

    return AnimatedScale(
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutBack,
      scale: active ? 1.12 : 1,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 260),
        curve: Curves.easeOutCubic,
        width: active ? 28 : 22,
        height: active ? 28 : 22,
        decoration: BoxDecoration(
          gradient: highlighted
              ? const LinearGradient(
                  colors: [AppColors.primaryBright, AppColors.primary],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
              : null,
          color: highlighted ? null : Colors.white,
          shape: BoxShape.circle,
          border: Border.all(
            color: highlighted ? Colors.transparent : AppColors.strokeStrong,
            width: 1.5,
          ),
          boxShadow: highlighted
              ? [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: active ? 0.26 : 0.16),
                    blurRadius: active ? 18 : 10,
                    offset: const Offset(0, 6),
                  ),
                ]
              : const [],
        ),
        alignment: Alignment.center,
        child: AnimatedDefaultTextStyle(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: highlighted ? Colors.white : AppColors.textSecondary,
                fontWeight: FontWeight.w800,
              ) ??
              const TextStyle(),
          child: Text('$stepNumber'),
        ),
      ),
    );
  }
}

class _ChoiceStep extends StatelessWidget {
  const _ChoiceStep({
    required this.options,
    required this.selected,
    required this.onSelect,
  });

  final List<String> options;
  final String? selected;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        const spacing = AppSpacing.sm;
        final columns = constraints.maxWidth >= 420 ? 3 : 2;
        final itemWidth =
            (constraints.maxWidth - (spacing * (columns - 1))) / columns;

        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: options.map((option) {
            final isSelected = selected == option;

            return GestureDetector(
              onTap: () => onSelect(option),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                width: itemWidth,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(22),
                  gradient: isSelected
                      ? const LinearGradient(
                          colors: [AppColors.primaryBright, AppColors.primary],
                        )
                      : null,
                  color: isSelected ? null : AppColors.surfaceStrong,
                  border: Border.all(
                    color: isSelected ? Colors.transparent : AppColors.strokeStrong,
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      _experienceIcon(option),
                      color: isSelected ? Colors.white : AppColors.primaryBright,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      option,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            color: isSelected ? Colors.white : AppColors.textPrimary,
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                  ],
                ),
              ),
            );
          }).toList(),
        );
      },
    );
  }
}

class _GoalSelectionCard extends StatelessWidget {
  const _GoalSelectionCard({
    required this.width,
    required this.goal,
    required this.selected,
    required this.onTap,
  });

  final double width;
  final Map<String, dynamic> goal;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final compact = width < 170;

    return GestureDetector(
      onTap: onTap,
      child: AnimatedScale(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOutBack,
        scale: selected ? 1.03 : 1,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOutCubic,
          width: width,
          constraints: BoxConstraints(minHeight: compact ? 112 : 120),
          padding: EdgeInsets.all(compact ? 9 : 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(26),
            gradient: selected
                ? const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [AppColors.primaryBright, AppColors.primary],
                  )
                : LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      AppColors.primaryBright.withValues(alpha: 0.16),
                      AppColors.accentPurple.withValues(alpha: 0.10),
                    ],
                  ),
            border: Border.all(
              color: selected
                  ? Colors.white.withValues(alpha: 0.18)
                  : AppColors.strokeStrong,
            ),
            boxShadow: [
              BoxShadow(
                color: (selected ? AppColors.primaryBright : AppColors.shadow)
                    .withValues(alpha: selected ? 0.22 : 0.10),
                blurRadius: selected ? 24 : 16,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                    width: compact ? 30 : 34,
                    height: compact ? 30 : 34,
                        decoration: BoxDecoration(
                          color: selected
                              ? Colors.white.withValues(alpha: 0.18)
                              : Colors.white.withValues(alpha: 0.74),
                      borderRadius: BorderRadius.circular(compact ? 12 : 14),
                    ),
                    alignment: Alignment.center,
                    child: Icon(
                      _goalIcon(goal),
                      size: compact ? 18 : 22,
                      color: selected ? Colors.white : AppColors.primaryBright,
                    ),
                  ),
                  const Spacer(),
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    width: compact ? 18 : 20,
                    height: compact ? 18 : 20,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: selected
                          ? Colors.white.withValues(alpha: 0.22)
                          : Colors.white.withValues(alpha: 0.74),
                    ),
                    alignment: Alignment.center,
                    child: Icon(
                      selected ? Icons.check_rounded : Icons.add_rounded,
                      size: compact ? 12 : 14,
                      color: selected ? Colors.white : AppColors.textSecondary,
                    ),
                  ),
                ],
                  ),
                  SizedBox(height: compact ? 5 : AppSpacing.sm),
                  Text(
                    goal['name']?.toString() ?? 'Goal',
                    maxLines: compact ? 2 : 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: selected ? Colors.white : AppColors.textPrimary,
                          fontWeight: FontWeight.w800,
                          fontSize: compact ? 13 : null,
                          height: compact ? 1.05 : null,
                        ),
                  ),
                  if (!compact) ...[
                    const SizedBox(height: 4),
                    Container(
                      width: 34,
                      height: 3,
                      decoration: BoxDecoration(
                        color: selected
                            ? Colors.white.withValues(alpha: 0.90)
                            : AppColors.primaryBright,
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      goal['description']?.toString().trim().isNotEmpty == true
                          ? goal['description'].toString()
                          : 'Member-facing goal option',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            height: 1.1,
                            color: selected
                                ? Colors.white.withValues(alpha: 0.88)
                                : AppColors.textSecondary,
                          ),
                    ),
                  ],
            ],
          ),
        ),
      ),
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({
    required this.label,
    required this.icon,
    required this.child,
  });

  final String label;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceStrong,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: AppColors.primaryBright.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(16),
            ),
            alignment: Alignment.center,
            child: Icon(icon, color: AppColors.primaryBright),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                ),
                child,
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LabeledTextarea extends StatelessWidget {
  const _LabeledTextarea({
    required this.title,
    required this.icon,
    required this.controller,
    required this.hintText,
  });

  final String title;
  final IconData icon;
  final TextEditingController controller;
  final String hintText;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceStrong,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: AppColors.primaryBright),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          TextField(
            controller: controller,
            minLines: 2,
            maxLines: 3,
            decoration: InputDecoration(
              hintText: hintText,
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoSurface extends StatelessWidget {
  const _InfoSurface({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.highlighted = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool highlighted;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: highlighted
            ? LinearGradient(
                colors: [
                  AppColors.primaryBright.withValues(alpha: 0.20),
                  AppColors.primary.withValues(alpha: 0.12),
                ],
              )
            : null,
        color: highlighted ? null : AppColors.surfaceStrong,
        border: Border.all(
          color: highlighted
              ? AppColors.primaryBright.withValues(alpha: 0.24)
              : AppColors.strokeStrong,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: highlighted
                  ? Colors.white.withValues(alpha: 0.16)
                  : AppColors.primaryBright.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(16),
            ),
            alignment: Alignment.center,
            child: Icon(
              icon,
              color: highlighted ? AppColors.primary : AppColors.primaryBright,
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ReviewPill extends StatelessWidget {
  const _ReviewPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.primaryBright.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: AppColors.primaryBright.withValues(alpha: 0.22),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.check_circle_outline_rounded,
            color: AppColors.primaryBright,
            size: 18,
          ),
          const SizedBox(width: AppSpacing.xs),
          Flexible(
            child: Text(
              label,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

class _GoalSelectionPromptCard extends StatelessWidget {
  const _GoalSelectionPromptCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceStrong,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.strokeStrong),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.06),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: AppColors.primaryBright.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(16),
            ),
            alignment: Alignment.center,
            child: const Icon(
              Icons.tips_and_updates_rounded,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Choose your focus',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Select one or more goals to continue with onboarding.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SetupHighlights extends StatelessWidget {
  const _SetupHighlights();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        const spacing = AppSpacing.sm;
        final columns = constraints.maxWidth >= 560 ? 4 : 2;
        final itemWidth =
            (constraints.maxWidth - (spacing * (columns - 1))) / columns;

        return Wrap(
          alignment: WrapAlignment.center,
          runAlignment: WrapAlignment.center,
          spacing: spacing,
          runSpacing: spacing,
          children: [
            _HighlightTile(
              width: itemWidth,
              icon: Icons.flag_circle_rounded,
              title: 'Goal-driven setup',
              subtitle: 'Select multiple results you care about.',
            ),
            _HighlightTile(
              width: itemWidth,
              icon: Icons.bar_chart_rounded,
              title: 'Progress ready',
              subtitle: 'Your metrics feed progress modules later.',
            ),
            _HighlightTile(
              width: itemWidth,
              icon: Icons.verified_user_rounded,
              title: 'Safer planning',
              subtitle: 'Health notes improve training context.',
            ),
            _HighlightTile(
              width: itemWidth,
              icon: Icons.explore_rounded,
              title: 'Gym discovery',
              subtitle: 'Start independent and explore gyms later.',
            ),
          ],
        );
      },
    );
  }
}

class _HighlightTile extends StatelessWidget {
  const _HighlightTile({
    required this.width,
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final double width;
  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: width,
      child: PremiumCard(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: AppColors.primaryBright),
            const SizedBox(height: AppSpacing.xs),
            Text(
              title,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 2),
            Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

class _WelcomeSignal extends StatelessWidget {
  const _WelcomeSignal({
    required this.icon,
    required this.label,
  });

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.66),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.5)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: AppColors.primary),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
          ),
        ],
      ),
    );
  }
}

String _stepTitle(int step) {
  switch (step) {
    case 1:
      return 'Welcome';
    case 2:
      return 'Goals';
    case 3:
      return 'Profile';
    case 4:
      return 'Health';
    case 5:
      return 'Finish';
    default:
      return 'Profile';
  }
}

String _primaryLabel(int step) {
  switch (step) {
    case 2:
      return 'Save Goals';
    case 3:
      return 'Save Profile';
    case 4:
      return 'Save Notes';
    case 5:
      return 'Complete Setup';
    default:
      return 'Continue';
  }
}


IconData _goalIcon(Map<String, dynamic> goal) {
  final source = '${goal['name'] ?? ''} ${goal['icon'] ?? ''}'.toLowerCase();

  if (source.contains('fat') || source.contains('fire')) {
    return Icons.local_fire_department_rounded;
  }
  if (source.contains('muscle') || source.contains('strength')) {
    return Icons.fitness_center_rounded;
  }
  if (source.contains('endur') || source.contains('run')) {
    return Icons.directions_run_rounded;
  }
  if (source.contains('mobil') || source.contains('recover')) {
    return Icons.self_improvement_rounded;
  }

  return Icons.flag_rounded;
}

IconData _experienceIcon(String value) {
  switch (value.toLowerCase()) {
    case 'beginner':
      return Icons.rocket_launch_rounded;
    case 'advanced':
      return Icons.auto_graph_rounded;
    default:
      return Icons.trending_up_rounded;
  }
}

String? _nullableText(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}
