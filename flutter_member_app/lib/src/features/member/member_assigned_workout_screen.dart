import 'package:flutter/material.dart';
import 'package:gym_flutter_core/guides.dart';
import 'package:intl/intl.dart';

import '../../core/user_facing_error.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/loading_state.dart';
import '../../../core/widgets/workout_reference_widgets.dart';
import '../../core/pagination.dart';
import 'member_repository.dart';

class MemberAssignedWorkoutScreen extends StatefulWidget {
  const MemberAssignedWorkoutScreen({
    super.key,
    required this.repository,
    required this.initialPlans,
    required this.onStartAssignedWorkout,
    required this.onOpenWorkoutBook,
  });

  final MemberRepository repository;
  final List<Map<String, dynamic>> initialPlans;
  final ValueChanged<int?> onStartAssignedWorkout;
  final VoidCallback onOpenWorkoutBook;

  @override
  State<MemberAssignedWorkoutScreen> createState() =>
      _MemberAssignedWorkoutScreenState();
}

class _MemberAssignedWorkoutScreenState
    extends State<MemberAssignedWorkoutScreen> {
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  List<Map<String, dynamic>> _plans = const [];
  int? _selectedPlanId;
  int _selectedDayIndex = 0;
  ApiPagination _planPage = const ApiPagination.singlePage();

  @override
  void initState() {
    super.initState();
    _plans = widget.initialPlans
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    _selectedPlanId = (_preferredPlan(_plans)['id'] as num?)?.toInt();
    _selectedDayIndex = _preferredDayIndex(_activePlan(_plans));
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await widget.repository.fetchWorkoutPlans();
      _plans = apiPageItems(response);
      _planPage = ApiPagination.fromResponse(response);
      if (!_plans.any(
        (plan) => (plan['id'] as num?)?.toInt() == _selectedPlanId,
      )) {
        _selectedPlanId = (_preferredPlan(_plans)['id'] as num?)?.toInt();
      }
      await _hydrateActivePlanDetail();
      _selectedDayIndex = _preferredDayIndex(_activePlan(_plans));
    } catch (exception) {
      _error = userFacingError(exception);
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_planPage.hasMore) return;
    setState(() => _loadingMore = true);
    try {
      final response = await widget.repository.fetchWorkoutPlans(
        page: _planPage.nextPage,
      );
      _plans = mergeApiPageItems(_plans, apiPageItems(response));
      _planPage = ApiPagination.fromResponse(response);
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userFacingError(exception))));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _hydrateActivePlanDetail() async {
    final activePlan = _activePlan(_plans);
    final planId = (activePlan['id'] as num?)?.toInt();
    if (planId == null) {
      return;
    }

    final response = await widget.repository.fetchWorkoutPlan(planId);
    final detail = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    if (detail.isEmpty) {
      return;
    }

    final index = _plans.indexWhere(
      (plan) => (plan['id'] as num?)?.toInt() == planId,
    );
    if (index >= 0) {
      _plans[index] = detail;
    }
  }

  @override
  Widget build(BuildContext context) {
    final activePlan = _activePlan(_plans);
    final days = _planDays(activePlan);
    final selectedDay = days.isEmpty
        ? const <String, dynamic>{}
        : days[_selectedDayIndex.clamp(0, days.length - 1)];
    final exercises = _dayExercises(selectedDay);
    final todayDay = _bestWorkoutDay(activePlan);
    final status = activePlan['status']?.toString() ?? 'inactive';
    final goal = activePlan['goal']?.toString() ?? 'Goal pending';
    final planName = activePlan['name']?.toString() ?? 'Assigned workout';
    final notes = <String>[
      activePlan['notes']?.toString() ?? '',
      selectedDay['notes']?.toString() ?? '',
    ].where((item) => item.trim().isNotEmpty).toList();

    return AppGradientScaffold(
      title: 'Assigned Workout',
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.md,
              AppSpacing.lg,
              0,
            ),
            child: _AssignedWorkoutPageHeader(
              onBack: () => Navigator.of(context).maybePop(),
              onOpenBook: widget.onOpenWorkoutBook,
            ),
          ),
          Expanded(
            child: _loading && _plans.isEmpty
                ? const LoadingState(label: 'Loading your assigned workout...')
                : _error != null && _plans.isEmpty
                ? ErrorStateView(message: _error!, onRetry: _load)
                : RefreshIndicator(
                    onRefresh: _load,
                    child: ListView(
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      children: [
                        if (activePlan.isEmpty)
                          EmptyStateView(
                            title: 'No assigned workout yet',
                            message:
                                'Your trainer has not assigned a workout plan yet. You can still open the workout tracker and start a free session.',
                            icon: Icons.fitness_center_rounded,
                            action: GradientButton(
                              label: 'Open Workout Book',
                              icon: Icons.menu_book_rounded,
                              expanded: true,
                              onPressed: widget.onOpenWorkoutBook,
                            ),
                          )
                        else ...[
                          if (_plans.length > 1) ...[
                            DropdownButtonFormField<int>(
                              key: ValueKey(_selectedPlanId),
                              initialValue: _selectedPlanId,
                              isExpanded: true,
                              decoration: const InputDecoration(
                                labelText: 'Viewing assigned plan',
                              ),
                              items: _plans
                                  .map(
                                    (plan) => DropdownMenuItem<int>(
                                      value: (plan['id'] as num?)?.toInt(),
                                      child: Text(
                                        '${plan['name']?.toString() ?? 'Workout plan'} · ${_planSourceLabel(plan)}',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) async {
                                setState(() {
                                  _selectedPlanId = value;
                                  _selectedDayIndex = 0;
                                });
                                await _hydrateActivePlanDetail();
                                if (mounted) setState(() {});
                              },
                            ),
                            const SizedBox(height: AppSpacing.lg),
                          ],
                          Align(
                            alignment: Alignment.centerLeft,
                            child: Chip(
                              avatar: Icon(
                                activePlan['independent_trainer_member_relationship_id'] !=
                                        null
                                    ? Icons.verified_user_outlined
                                    : Icons.apartment_rounded,
                                size: 17,
                              ),
                              label: Text(_planSourceLabel(activePlan)),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          RevealOnBuild(
                            child: GuideTarget(
                              id: 'member_assigned_workout_v1/overview',
                              child: _AssignedWorkoutHero(
                                title: planName,
                                subtitle: goal,
                                days: days.length,
                                exercises: _countExercises(days),
                                status: _titleCase(status),
                                todayLabel: todayDay.isEmpty
                                    ? 'Weekly plan ready'
                                    : 'Today: ${_dayLabel(todayDay)}',
                                onStart: () => widget.onStartAssignedWorkout(
                                  (activePlan['id'] as num?)?.toInt(),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.lg),
                          RevealOnBuild(
                            delay: const Duration(milliseconds: 70),
                            child: LayoutBuilder(
                              builder: (context, constraints) {
                                final textScale = MediaQuery.textScalerOf(
                                  context,
                                ).scale(1);
                                final stackTiles =
                                    constraints.maxWidth < 340 ||
                                    textScale > 1.35;
                                final tiles = <Widget>[
                                  _AssignedMetricTile(
                                    label: 'Workout days',
                                    value: '${days.length}',
                                    caption: 'Weekly sessions',
                                    icon: Icons.calendar_month_rounded,
                                    gradient: const [
                                      Color(0xFF9DCEFF),
                                      Color(0xFF92A3FD),
                                    ],
                                  ),
                                  _AssignedMetricTile(
                                    label: 'Selected day',
                                    value: days.isEmpty
                                        ? '--'
                                        : _dayLabel(selectedDay),
                                    caption:
                                        selectedDay['focus']?.toString() ??
                                        'Focus pending',
                                    icon: Icons.today_rounded,
                                    gradient: const [
                                      Color(0xFFEEA4CE),
                                      Color(0xFFC58BF2),
                                    ],
                                  ),
                                ];
                                if (stackTiles) {
                                  return Column(
                                    children: [
                                      tiles.first,
                                      const SizedBox(height: AppSpacing.md),
                                      tiles.last,
                                    ],
                                  );
                                }
                                return Row(
                                  children: [
                                    Expanded(child: tiles.first),
                                    const SizedBox(width: AppSpacing.md),
                                    Expanded(child: tiles.last),
                                  ],
                                );
                              },
                            ),
                          ),
                          const SizedBox(height: AppSpacing.lg),
                          RevealOnBuild(
                            delay: const Duration(milliseconds: 120),
                            child: GuideTarget(
                              id: 'member_assigned_workout_v1/schedule',
                              child: WorkoutReferenceSection(
                                title: 'Daily workout schedule',
                                subtitle:
                                    'Choose the workout day you want to review before starting the session.',
                                child: days.isEmpty
                                    ? const EmptyStateView(
                                        title: 'No workout days configured',
                                        message:
                                            'Your trainer has not added workout-day blocks yet.',
                                        icon: Icons.event_busy_rounded,
                                      )
                                    : Column(
                                        children: days.asMap().entries.map((
                                          entry,
                                        ) {
                                          final selected =
                                              entry.key == _selectedDayIndex;
                                          return Padding(
                                            padding: EdgeInsets.only(
                                              bottom:
                                                  entry.key == days.length - 1
                                                  ? 0
                                                  : AppSpacing.sm,
                                            ),
                                            child: WorkoutReferenceScheduleRow(
                                              onTap: () => setState(
                                                () => _selectedDayIndex =
                                                    entry.key,
                                              ),
                                              title: _dayLabel(entry.value),
                                              subtitle:
                                                  entry.value['focus']
                                                      ?.toString() ??
                                                  '${_dayExercises(entry.value).length} exercises configured',
                                              icon: selected
                                                  ? Icons
                                                        .play_circle_fill_rounded
                                                  : Icons
                                                        .calendar_today_rounded,
                                              trailing: StatusBadge(
                                                label: selected
                                                    ? 'Selected'
                                                    : 'Review',
                                                color: selected
                                                    ? AppColors.primary
                                                    : AppColors.textSecondary,
                                              ),
                                            ),
                                          );
                                        }).toList(),
                                      ),
                              ),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.lg),
                          RevealOnBuild(
                            delay: const Duration(milliseconds: 170),
                            child: WorkoutReferenceFocusCard(
                              title: todayDay.isEmpty
                                  ? 'Weekly plan overview'
                                  : '${_dayLabel(selectedDay)} focus',
                              subtitle: notes.isEmpty
                                  ? (todayDay.isEmpty
                                        ? 'Your weekly plan is ready. Select a workout day to review the assigned exercises.'
                                        : selectedDay['focus']?.toString() ??
                                              'Trainer focus will appear here.')
                                  : notes.first,
                              icon: todayDay.isEmpty
                                  ? Icons.route_rounded
                                  : Icons.fitness_center_rounded,
                            ),
                          ),
                          const SizedBox(height: AppSpacing.lg),
                          RevealOnBuild(
                            delay: const Duration(milliseconds: 220),
                            child: GuideTarget(
                              id: 'member_assigned_workout_v1/exercises',
                              child: WorkoutReferenceExerciseSection(
                                title: 'Exercises',
                                countLabel: '${exercises.length} blocks',
                                children: [
                                  if (exercises.isEmpty)
                                    const EmptyStateView(
                                      title: 'No exercises on this day yet',
                                      message:
                                          'This workout day is available, but your trainer has not attached exercises yet.',
                                      icon: Icons.playlist_remove_rounded,
                                    )
                                  else
                                    ...exercises.asMap().entries.map((entry) {
                                      final exercise = entry.value;
                                      final detail = Map<String, dynamic>.from(
                                        exercise['exercise'] as Map? ??
                                            const {},
                                      );
                                      final targetWeight =
                                          (exercise['target_weight'] as num?)
                                              ?.toDouble();

                                      return Padding(
                                        padding: EdgeInsets.only(
                                          bottom:
                                              entry.key == exercises.length - 1
                                              ? 0
                                              : AppSpacing.sm,
                                        ),
                                        child: WorkoutReferenceScheduleRow(
                                          title:
                                              detail['name']?.toString() ??
                                              exercise['name']?.toString() ??
                                              'Exercise',
                                          subtitle:
                                              '${detail['muscle_group']?.toString() ?? 'General'} • ${exercise['reps'] ?? '--'} reps • Rest ${exercise['rest_seconds'] ?? '--'}s',
                                          icon: Icons.fitness_center_rounded,
                                          trailing: StatusBadge(
                                            label: targetWeight == null
                                                ? 'Sets ${exercise['sets'] ?? '--'}'
                                                : '${targetWeight.toStringAsFixed(0)} kg',
                                            color: targetWeight == null
                                                ? const Color(0xFF22D3EE)
                                                : const Color(0xFF34D399),
                                          ),
                                        ),
                                      );
                                    }),
                                ],
                              ),
                            ),
                          ),
                        ],
                        if (_planPage.hasMore) ...[
                          const SizedBox(height: AppSpacing.md),
                          Center(
                            child: OutlinedButton.icon(
                              onPressed: _loadingMore ? null : _loadMore,
                              icon: _loadingMore
                                  ? const SizedBox.square(
                                      dimension: 16,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(Icons.expand_more_rounded),
                              label: Text(
                                _loadingMore ? 'Loading...' : 'Load more plans',
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Map<String, dynamic> _activePlan(List<Map<String, dynamic>> plans) {
    return plans.firstWhere(
      (plan) => (plan['id'] as num?)?.toInt() == _selectedPlanId,
      orElse: () => _preferredPlan(plans),
    );
  }

  Map<String, dynamic> _preferredPlan(List<Map<String, dynamic>> plans) {
    return plans.firstWhere(
      (plan) => (plan['status']?.toString() ?? '').toLowerCase() == 'active',
      orElse: () => plans.firstOrNull ?? const <String, dynamic>{},
    );
  }

  List<Map<String, dynamic>> _planDays(Map<String, dynamic> plan) {
    return (plan['days'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList()
      ..sort(
        (left, right) => ((left['day_number'] as num?)?.toInt() ?? 999)
            .compareTo(((right['day_number'] as num?)?.toInt() ?? 999)),
      );
  }

  List<Map<String, dynamic>> _dayExercises(Map<String, dynamic> day) {
    return (day['exercises'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Map<String, dynamic> _bestWorkoutDay(Map<String, dynamic> plan) {
    final days = _planDays(plan);
    if (days.isEmpty) {
      return const {};
    }

    final weekday = DateTime.now().weekday;
    for (final day in days) {
      if (((day['day_number'] as num?)?.toInt() ?? 0) == weekday) {
        return day;
      }

      final label = (day['label']?.toString() ?? '').toLowerCase();
      if (_labelMatchesWeekday(label, weekday)) {
        return day;
      }
    }

    for (final day in days) {
      final dayNumber = (day['day_number'] as num?)?.toInt();
      if (dayNumber != null && dayNumber > weekday) {
        return day;
      }
    }

    return days.first;
  }

  int _preferredDayIndex(Map<String, dynamic> plan) {
    final days = _planDays(plan);
    if (days.isEmpty) {
      return 0;
    }

    final preferred = _bestWorkoutDay(plan);
    final index = days.indexWhere(
      (day) => day['id']?.toString() == preferred['id']?.toString(),
    );
    return index < 0 ? 0 : index;
  }

  bool _labelMatchesWeekday(String label, int weekday) {
    if (label.isEmpty) {
      return false;
    }

    final weekdayName = DateFormat(
      'EEEE',
    ).format(DateTime(2026, 1, weekday + 4)).toLowerCase();
    final shortWeekday = DateFormat(
      'EEE',
    ).format(DateTime(2026, 1, weekday + 4)).toLowerCase();
    return label.contains(weekdayName) || label.contains(shortWeekday);
  }

  String _dayLabel(Map<String, dynamic> day) {
    final label = day['label']?.toString();
    if (label != null && label.trim().isNotEmpty) {
      return label;
    }

    final dayNumber = (day['day_number'] as num?)?.toInt();
    if (dayNumber != null && dayNumber >= 1 && dayNumber <= 7) {
      return DateFormat('EEEE').format(DateTime(2026, 1, dayNumber + 4));
    }

    return 'Workout day';
  }

  int _countExercises(List<Map<String, dynamic>> days) {
    return days.fold<int>(0, (sum, day) => sum + _dayExercises(day).length);
  }

  String _titleCase(String value) {
    if (value.isEmpty) {
      return 'Unknown';
    }

    return value
        .split('_')
        .map(
          (part) => part.isEmpty
              ? part
              : '${part[0].toUpperCase()}${part.substring(1)}',
        )
        .join(' ');
  }
}

class _AssignedWorkoutPageHeader extends StatelessWidget {
  const _AssignedWorkoutPageHeader({
    required this.onBack,
    required this.onOpenBook,
  });

  final VoidCallback onBack;
  final VoidCallback onOpenBook;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Assigned workout',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.6,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                'Review your plan and start when you are ready.',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            MemberHeaderActionButton(
              icon: Icons.arrow_back_rounded,
              onTap: onBack,
              tooltip: 'Back to training',
            ),
            MemberHeaderActionButton(
              icon: Icons.menu_book_rounded,
              onTap: onOpenBook,
              tooltip: 'Workout book',
            ),
          ],
        ),
      ],
    );
  }
}

class _AssignedWorkoutHero extends StatelessWidget {
  const _AssignedWorkoutHero({
    required this.title,
    required this.subtitle,
    required this.days,
    required this.exercises,
    required this.status,
    required this.todayLabel,
    required this.onStart,
  });

  final String title;
  final String subtitle;
  final int days;
  final int exercises;
  final String status;
  final String todayLabel;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        gradient: const LinearGradient(
          colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.24),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -28,
            top: -18,
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.15),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.5,
                          ),
                    ),
                  ),
                  const _AssignedHeroMark(),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                subtitle,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Colors.white.withValues(alpha: 0.86),
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 18),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _AssignedHeroPill(label: status),
                  _AssignedHeroPill(label: todayLabel),
                  _AssignedHeroPill(label: '$exercises exercises'),
                ],
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: onStart,
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                    backgroundColor: Colors.white,
                    foregroundColor: AppColors.primary,
                  ),
                  icon: const Icon(Icons.play_arrow_rounded),
                  label: const Text('Start assigned workout'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AssignedHeroMark extends StatelessWidget {
  const _AssignedHeroMark();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 74,
      height: 74,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white.withValues(alpha: 0.18),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.32),
                width: 2,
              ),
            ),
          ),
          const Icon(Icons.route_rounded, color: Colors.white, size: 30),
        ],
      ),
    );
  }
}

class _AssignedHeroPill extends StatelessWidget {
  const _AssignedHeroPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.22),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: Colors.white,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _AssignedMetricTile extends StatelessWidget {
  const _AssignedMetricTile({
    required this.label,
    required this.value,
    required this.caption,
    required this.icon,
    required this.gradient,
  });

  final String label;
  final String value;
  final String caption;
  final IconData icon;
  final List<Color> gradient;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 2)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(colors: gradient),
            ),
            child: Icon(icon, color: Colors.white, size: 20),
          ),
          const SizedBox(height: 12),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            caption,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(
              context,
            ).textTheme.labelSmall?.copyWith(color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}

String _planSourceLabel(Map<String, dynamic> plan) {
  final scope = plan['coaching_scope']?.toString().toLowerCase();
  if (scope == 'independent' ||
      plan['independent_trainer_member_relationship_id'] != null) {
    return 'Independent trainer plan';
  }
  if (scope == 'personal' || plan['trainer_id'] == null) {
    return plan['plan_origin'] == 'catalog_adopted'
        ? 'Workout Book plan'
        : 'Your personal plan';
  }

  return 'Gym trainer plan';
}
