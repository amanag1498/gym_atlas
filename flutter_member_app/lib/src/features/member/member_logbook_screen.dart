import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import 'member_repository.dart';

class MemberLogbookScreen extends StatefulWidget {
  const MemberLogbookScreen({
    super.key,
    required this.repository,
    this.memberName = 'Athlete',
  });

  final MemberRepository repository;
  final String memberName;

  @override
  State<MemberLogbookScreen> createState() => _MemberLogbookScreenState();
}

class _MemberLogbookScreenState extends State<MemberLogbookScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _history = const [];
  Map<String, dynamic> _summary = const {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final results = await Future.wait<Map<String, dynamic>>([
        widget.repository.fetchWorkoutHistory(),
        widget.repository.fetchPersonalRecords(),
      ]);

      _history = (results[0]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _summary = Map<String, dynamic>.from(
        results[1]['data'] as Map? ?? const {},
      );
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final records = (_summary['personal_records'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final firstName = firstNameFromFullName(widget.memberName);

    return DefaultTabController(
      length: 3,
      child: AppGradientScaffold(
        title: 'Logbook',
        body: _loading
            ? const _LogbookSkeleton()
            : _error != null
            ? ErrorStateView(message: _error!, onRetry: _load)
            : Column(
                children: [
                  Builder(
                    builder: (context) {
                      final controller = DefaultTabController.of(context);
                      return Padding(
                        padding: const EdgeInsets.fromLTRB(
                          AppSpacing.lg,
                          AppSpacing.md,
                          AppSpacing.lg,
                          0,
                        ),
                        child: MemberPageGreetingHeader(
                          firstName: firstName,
                          subtitle:
                              'Strength tracking, records, and workout history.',
                          actions: [
                            MemberHeaderActionButton(
                              icon: Icons.arrow_back_rounded,
                              onTap: () => Navigator.of(context).maybePop(),
                            ),
                            MemberHeaderActionButton(
                              icon: Icons.emoji_events_rounded,
                              onTap: () => controller.animateTo(2),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.lg,
                      AppSpacing.md,
                      AppSpacing.lg,
                      0,
                    ),
                    child: const _LogbookTabSlider(),
                  ),
                  Expanded(
                    child: TabBarView(
                      children: [
                        _LogbookOverviewTab(
                          history: _history,
                          records: records,
                          onOpenWorkout: (session) =>
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => WorkoutHistoryDetailScreen(
                                    repository: widget.repository,
                                    session: session,
                                  ),
                                ),
                              ),
                          onOpenExercise: (record) =>
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => ExerciseHistoryScreen(
                                    repository: widget.repository,
                                    exerciseId:
                                        (record['exercise_id'] as num?)
                                            ?.toInt() ??
                                        0,
                                    exerciseName: _exerciseName(record),
                                  ),
                                ),
                              ),
                        ),
                        _WorkoutHistoryTab(
                          history: _history,
                          repository: widget.repository,
                        ),
                        _PersonalRecordsTab(
                          records: records,
                          repository: widget.repository,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}

class _LogbookTopBar extends StatelessWidget {
  const _LogbookTopBar({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        InkWell(
          onTap: () => Navigator.of(context).maybePop(),
          borderRadius: BorderRadius.circular(16),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.stroke),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 10,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: const Icon(
              Icons.arrow_back_rounded,
              color: AppColors.textPrimary,
              size: 20,
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _LogbookTabSlider extends StatefulWidget {
  const _LogbookTabSlider();

  @override
  State<_LogbookTabSlider> createState() => _LogbookTabSliderState();
}

class _LogbookTabSliderState extends State<_LogbookTabSlider> {
  static const _items = [
    (label: 'Overview', icon: Icons.dashboard_customize_rounded),
    (label: 'History', icon: Icons.history_rounded),
    (label: 'Records', icon: Icons.emoji_events_rounded),
  ];

  TabController? _controller;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final nextController = DefaultTabController.maybeOf(context);
    if (_controller == nextController) {
      return;
    }
    _controller?.removeListener(_handleTabChange);
    _controller = nextController;
    _controller?.addListener(_handleTabChange);
  }

  @override
  void dispose() {
    _controller?.removeListener(_handleTabChange);
    super.dispose();
  }

  void _handleTabChange() {
    if (mounted) {
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final controller = _controller;
    final activeIndex = controller?.index ?? 0;

    return Container(
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: AppColors.surfaceOverlay,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        children: [
          for (var index = 0; index < _items.length; index++)
            Expanded(
              child: _LogbookTabPill(
                label: _items[index].label,
                icon: _items[index].icon,
                active: activeIndex == index,
                onTap: () => controller?.animateTo(index),
              ),
            ),
        ],
      ),
    );
  }
}

class _LogbookTabPill extends StatelessWidget {
  const _LogbookTabPill({
    required this.label,
    required this.icon,
    required this.active,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOutCubic,
        margin: const EdgeInsets.symmetric(horizontal: 2),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 11),
        decoration: BoxDecoration(
          color: active ? AppColors.primary : Colors.transparent,
          borderRadius: BorderRadius.circular(999),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 17,
              color: active ? Colors.white : AppColors.textSecondary,
            ),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: active ? Colors.white : AppColors.textSecondary,
                  fontWeight: active ? FontWeight.w800 : FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LogbookOverviewTab extends StatelessWidget {
  const _LogbookOverviewTab({
    required this.history,
    required this.records,
    required this.onOpenWorkout,
    required this.onOpenExercise,
  });

  final List<Map<String, dynamic>> history;
  final List<Map<String, dynamic>> records;
  final ValueChanged<Map<String, dynamic>> onOpenWorkout;
  final ValueChanged<Map<String, dynamic>> onOpenExercise;

  @override
  Widget build(BuildContext context) {
    final recentWorkouts = history.take(3).toList();
    final totalVolume = history.fold<double>(
      0,
      (sum, item) => sum + _asDouble(item['total_volume']),
    );
    final bestRecord = records.isEmpty ? null : records.first;
    final lastWorkout = history.isEmpty ? null : history.first;

    if (history.isEmpty && records.isEmpty) {
      return const Center(
        child: _LogbookEmptyPanel(
          title: 'Your logbook is empty',
          message: 'Complete your first workout to unlock trends and PRs.',
          icon: Icons.menu_book_rounded,
          gradient: [Color(0xFFDBEAFE), AppColors.primary],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        _LogbookMomentumPanel(
          workoutCount: history.length,
          totalVolume: _formatKg(totalVolume),
          recordCount: records.length,
          trend: _historyTrend(history),
          latestDate: lastWorkout == null
              ? 'No dates yet'
              : _formatDate(lastWorkout['session_date']),
          topLift: bestRecord == null ? 'No PR yet' : _exerciseName(bestRecord),
        ),
        const SizedBox(height: AppSpacing.lg),
        if (records.isNotEmpty) ...[
          _LogbookSectionTitle(
            title: 'Personal record highlights',
            action: '${records.length} PRs',
          ),
          const SizedBox(height: AppSpacing.md),
          ...records
              .take(3)
              .map(
                (record) => Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                  child: _LogbookRecordRow(
                    record: record,
                    onTap: () => onOpenExercise(record),
                  ),
                ),
              ),
          const SizedBox(height: AppSpacing.lg),
        ],
        const _LogbookSectionTitle(title: 'Recent workouts', action: 'Latest'),
        const SizedBox(height: AppSpacing.md),
        ...recentWorkouts.map(
          (session) => Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: _LogbookWorkoutRow(
              session: session,
              onTap: () => onOpenWorkout(session),
            ),
          ),
        ),
      ],
    );
  }
}

class _LogbookMomentumPanel extends StatelessWidget {
  const _LogbookMomentumPanel({
    required this.workoutCount,
    required this.totalVolume,
    required this.recordCount,
    required this.trend,
    required this.latestDate,
    required this.topLift,
  });

  final int workoutCount;
  final String totalVolume;
  final int recordCount;
  final String trend;
  final String latestDate;
  final String topLift;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Training momentum',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  trend,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _LogbookMomentumStat(
                  value: '$workoutCount',
                  label: 'sessions',
                  icon: Icons.history_rounded,
                  color: AppColors.primaryBright,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _LogbookMomentumStat(
                  value: totalVolume,
                  label: 'volume',
                  icon: Icons.bar_chart_rounded,
                  color: AppColors.primaryBright,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _LogbookMomentumStat(
                  value: '$recordCount',
                  label: 'records',
                  icon: Icons.emoji_events_rounded,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _LogbookMomentumStat(
                  value: latestDate,
                  label: topLift,
                  icon: Icons.calendar_month_rounded,
                  color: AppColors.textSecondary,
                  compactValue: true,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _LogbookMomentumStat extends StatelessWidget {
  const _LogbookMomentumStat({
    required this.value,
    required this.label,
    required this.icon,
    required this.color,
    this.compactValue = false,
  });

  final String value;
  final String label;
  final IconData icon;
  final Color color;
  final bool compactValue;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(height: 10),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
              fontSize: compactValue ? 14 : null,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _LogbookSectionTitle extends StatelessWidget {
  const _LogbookSectionTitle({required this.title, required this.action});

  final String title;
  final String action;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        Text(
          action,
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
            color: AppColors.textSecondary,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _LogbookRecordRow extends StatelessWidget {
  const _LogbookRecordRow({required this.record, required this.onTap});

  final Map<String, dynamic> record;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return _LogbookGradientRow(
      icon: Icons.emoji_events_rounded,
      gradient: const [Color(0xFFDBEAFE), Color(0xFF93C5FD)],
      title: _exerciseName(record),
      subtitle:
          '${_formatKg(_asDouble(record['best_weight']))} • ${record['best_reps'] ?? 0} reps • ${_formatKg(_asDouble(record['best_volume']))} volume',
      badge: 'PR',
      onTap: onTap,
    );
  }
}

class _LogbookWorkoutRow extends StatelessWidget {
  const _LogbookWorkoutRow({
    required this.session,
    required this.onTap,
    this.showPr = false,
    this.subtitle,
  });

  final Map<String, dynamic> session;
  final VoidCallback onTap;
  final bool showPr;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    return _LogbookGradientRow(
      icon: Icons.fitness_center_rounded,
      gradient: const [Color(0xFFDBEAFE), Color(0xFF93C5FD)],
      title: _formatDate(session['session_date']),
      subtitle:
          subtitle ??
          '${_sessionExerciseCount(session)} exercises • ${_formatKg(_asDouble(session['total_volume']))}',
      badge: showPr
          ? 'PR'
          : _titleCase(session['status']?.toString() ?? 'completed'),
      onTap: onTap,
    );
  }
}

class _LogbookGradientRow extends StatelessWidget {
  const _LogbookGradientRow({
    required this.icon,
    required this.gradient,
    required this.title,
    required this.subtitle,
    required this.badge,
    required this.onTap,
  });

  final IconData icon;
  final List<Color> gradient;
  final String title;
  final String subtitle;
  final String badge;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = gradient.last;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: PremiumCard(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(16),
                color: AppColors.surfaceSoft,
                border: Border.all(color: AppColors.stroke),
              ),
              child: Icon(icon, color: accent),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            StatusBadge(label: badge, color: accent),
          ],
        ),
      ),
    );
  }
}

class _LogbookDetailSummaryCard extends StatelessWidget {
  const _LogbookDetailSummaryCard({
    required this.title,
    required this.subtitle,
    required this.badge,
    required this.icon,
  });

  final String title;
  final String subtitle;
  final String badge;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(18),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Icon(icon, color: AppColors.primaryBright, size: 24),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          StatusBadge(label: badge, color: AppColors.primaryBright),
        ],
      ),
    );
  }
}

class _LogbookDetailStatCard extends StatelessWidget {
  const _LogbookDetailStatCard({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Icon(icon, color: AppColors.primaryBright, size: 18),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  value,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w700,
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

class _LogbookExerciseDetailRow extends StatelessWidget {
  const _LogbookExerciseDetailRow({
    required this.exercise,
    required this.onTap,
  });

  final Map<String, dynamic> exercise;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final sets = (exercise['sets'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final preview = sets
        .take(2)
        .map((set) {
          return 'Set ${(set['set_number'] as num?)?.toInt() ?? 1}: ${_asDouble(set['weight']).toStringAsFixed(0)} kg x ${(set['reps'] as num?)?.toInt() ?? 0}';
        })
        .join(' • ');

    return _LogbookGradientRow(
      icon: Icons.fitness_center_rounded,
      gradient: const [AppColors.surfaceSoft, AppColors.primaryBright],
      title: _exerciseName(exercise),
      subtitle: preview.isEmpty ? 'No set details saved' : preview,
      badge: '${sets.length} sets',
      onTap: onTap,
    );
  }
}

class _LogbookEmptyPanel extends StatelessWidget {
  const _LogbookEmptyPanel({
    required this.title,
    required this.message,
    required this.icon,
    required this.gradient,
  });

  final String title;
  final String message;
  final IconData icon;
  final List<Color> gradient;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: PremiumCard(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(22),
                color: gradient.first.withValues(alpha: 0.14),
              ),
              child: Icon(icon, color: gradient.last, size: 30),
            ),
            const SizedBox(height: 16),
            Text(
              title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
                height: 1.35,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WorkoutHistoryTab extends StatelessWidget {
  const _WorkoutHistoryTab({required this.history, required this.repository});

  final List<Map<String, dynamic>> history;
  final MemberRepository repository;

  @override
  Widget build(BuildContext context) {
    if (history.isEmpty) {
      return const Center(
        child: _LogbookEmptyPanel(
          title: 'No workout history yet',
          message: 'Completed sessions will appear here with volume summaries.',
          icon: Icons.history_toggle_off_rounded,
          gradient: [Color(0xFFDBEAFE), AppColors.primary],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        _LogbookSectionTitle(
          title: 'Workout history',
          action: '${history.length} sessions',
        ),
        const SizedBox(height: AppSpacing.md),
        ...history.asMap().entries.map((entry) {
          final index = entry.key;
          final session = entry.value;
          return Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: RevealOnBuild(
              delay: Duration(milliseconds: 35 * index),
              child: _LogbookWorkoutRow(
                session: session,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => WorkoutHistoryDetailScreen(
                      repository: repository,
                      session: session,
                    ),
                  ),
                ),
                showPr: _sessionHasPrBadge(session),
                subtitle: _sessionFocusLine(session),
              ),
            ),
          );
        }),
      ],
    );
  }
}

class _PersonalRecordsTab extends StatelessWidget {
  const _PersonalRecordsTab({required this.records, required this.repository});

  final List<Map<String, dynamic>> records;
  final MemberRepository repository;

  @override
  Widget build(BuildContext context) {
    if (records.isEmpty) {
      return const Center(
        child: _LogbookEmptyPanel(
          title: 'No PRs yet',
          message:
              'Best weights, reps, and volume records unlock after workouts.',
          icon: Icons.emoji_events_outlined,
          gradient: [Color(0xFFDBEAFE), AppColors.primary],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        _LogbookSectionTitle(
          title: 'Personal records',
          action: '${records.length} PRs',
        ),
        const SizedBox(height: AppSpacing.md),
        ...records.asMap().entries.map((entry) {
          final index = entry.key;
          final record = entry.value;
          return Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: RevealOnBuild(
              delay: Duration(milliseconds: 35 * index),
              child: _LogbookRecordRow(
                record: record,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => ExerciseHistoryScreen(
                      repository: repository,
                      exerciseId: (record['exercise_id'] as num?)?.toInt() ?? 0,
                      exerciseName: _exerciseName(record),
                    ),
                  ),
                ),
              ),
            ),
          );
        }),
      ],
    );
  }
}

class WorkoutHistoryDetailScreen extends StatelessWidget {
  const WorkoutHistoryDetailScreen({
    super.key,
    required this.repository,
    required this.session,
  });

  final MemberRepository repository;
  final Map<String, dynamic> session;

  @override
  Widget build(BuildContext context) {
    final exercises = (session['exercises'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();

    return AppGradientScaffold(
      title: 'Workout History',
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.sm,
                AppSpacing.lg,
                0,
              ),
              child: _LogbookTopBar(
                title: 'Workout History',
                subtitle: 'Session summary and exercise breakdown.',
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.lg,
                  AppSpacing.md,
                  AppSpacing.lg,
                  AppSpacing.xl,
                ),
                children: [
                  _LogbookDetailSummaryCard(
                    title: _formatDate(session['session_date']),
                    subtitle:
                        '${_sessionExerciseCount(session)} exercises • ${_formatKg(_asDouble(session['total_volume']))}',
                    badge: _titleCase(
                      session['status']?.toString() ?? 'completed',
                    ),
                    icon: Icons.fitness_center_rounded,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Row(
                    children: [
                      Expanded(
                        child: _LogbookDetailStatCard(
                          label: 'Exercises',
                          value: '${_sessionExerciseCount(session)}',
                          icon: Icons.format_list_bulleted_rounded,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: _LogbookDetailStatCard(
                          label: 'Volume',
                          value: _formatKg(_asDouble(session['total_volume'])),
                          icon: Icons.bar_chart_rounded,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  if (exercises.isEmpty)
                    const EmptyStateView(
                      title: 'No exercise entries',
                      message:
                          'This workout summary did not include exercise-level details.',
                      icon: Icons.playlist_remove_rounded,
                    )
                  else
                    ...exercises.map(
                      (exercise) => Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: _LogbookExerciseDetailRow(
                          exercise: exercise,
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => ExerciseHistoryScreen(
                                repository: repository,
                                exerciseId:
                                    (exercise['exercise_id'] as num?)
                                        ?.toInt() ??
                                    0,
                                exerciseName: _exerciseName(exercise),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class ExerciseHistoryScreen extends StatefulWidget {
  const ExerciseHistoryScreen({
    super.key,
    required this.repository,
    required this.exerciseId,
    required this.exerciseName,
  });

  final MemberRepository repository;
  final int exerciseId;
  final String exerciseName;

  @override
  State<ExerciseHistoryScreen> createState() => _ExerciseHistoryScreenState();
}

class _ExerciseHistoryScreenState extends State<ExerciseHistoryScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _record;
  List<Map<String, dynamic>> _history = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await widget.repository.fetchExerciseHistory(
        widget.exerciseId,
      );
      final data = Map<String, dynamic>.from(
        response['data'] as Map? ?? const {},
      );
      _record = data['personal_record'] is Map
          ? Map<String, dynamic>.from(data['personal_record'] as Map)
          : null;
      _history = (data['history'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Exercise History',
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.sm,
                AppSpacing.lg,
                0,
              ),
              child: _LogbookTopBar(
                title: 'Exercise History',
                subtitle: 'Records and past sessions for this lift.',
              ),
            ),
            Expanded(
              child: _loading
                  ? const _LogbookSkeleton()
                  : _error != null
                  ? ErrorStateView(message: _error!, onRetry: _load)
                  : ListView(
                      padding: const EdgeInsets.fromLTRB(
                        AppSpacing.lg,
                        AppSpacing.md,
                        AppSpacing.lg,
                        AppSpacing.xl,
                      ),
                      children: [
                        _LogbookDetailSummaryCard(
                          title: widget.exerciseName,
                          subtitle: _record == null
                              ? 'No PR recorded yet for this exercise.'
                              : 'Best ${_formatKg(_asDouble(_record!['best_weight']))} • ${_record!['best_reps'] ?? 0} reps',
                          badge: _record == null ? 'Tracking' : 'PR',
                          icon: Icons.emoji_events_rounded,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Row(
                          children: [
                            Expanded(
                              child: _LogbookDetailStatCard(
                                label: 'Sessions',
                                value: '${_history.length}',
                                icon: Icons.history_rounded,
                              ),
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Expanded(
                              child: _LogbookDetailStatCard(
                                label: 'Trend',
                                value: _exerciseTrend(
                                  _history,
                                  widget.exerciseId,
                                ),
                                icon: Icons.insights_rounded,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Row(
                          children: [
                            Expanded(
                              child: _LogbookDetailStatCard(
                                label: 'Latest',
                                value: _history.isEmpty
                                    ? 'No history'
                                    : _formatDate(
                                        _history.first['session_date'],
                                      ),
                                icon: Icons.calendar_month_rounded,
                              ),
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Expanded(
                              child: _LogbookDetailStatCard(
                                label: 'Top lift',
                                value: _record == null
                                    ? 'No PR yet'
                                    : _formatKg(
                                        _asDouble(_record!['best_weight']),
                                      ),
                                icon: Icons.workspace_premium_rounded,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        if (_history.isEmpty)
                          const EmptyStateView(
                            title: 'No exercise history yet',
                            message:
                                'Past sessions for this exercise will appear here after more completed workouts.',
                            icon: Icons.timeline_rounded,
                          )
                        else
                          ..._history.map(
                            (session) => Padding(
                              padding: const EdgeInsets.only(
                                bottom: AppSpacing.sm,
                              ),
                              child: _LogbookGradientRow(
                                icon: Icons.timeline_rounded,
                                gradient: const [
                                  AppColors.surfaceSoft,
                                  AppColors.primaryBright,
                                ],
                                title: _formatDate(session['session_date']),
                                subtitle:
                                    '${_exerciseSessionVolume(session, widget.exerciseId)} • ${_exerciseSessionSets(session, widget.exerciseId)} sets',
                                badge: 'History',
                                onTap: () {},
                              ),
                            ),
                          ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LogbookSkeleton extends StatelessWidget {
  const _LogbookSkeleton();

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: const [
          SkeletonProfileHeader(),
          SizedBox(height: AppSpacing.lg),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonHistoryList(items: 4),
        ],
      ),
    );
  }
}

String _exerciseName(Map<String, dynamic> source) {
  final exercise = Map<String, dynamic>.from(
    source['exercise'] as Map? ?? const {},
  );
  return exercise['name']?.toString() ?? 'Exercise';
}

double _asDouble(Object? value) {
  if (value is num) {
    return value.toDouble();
  }
  return double.tryParse('$value') ?? 0;
}

String _formatKg(double value) {
  return '${value.toStringAsFixed(0)} kg';
}

String _formatDate(Object? value) {
  final text = value?.toString() ?? '';
  final date = DateTime.tryParse(text);
  if (date == null) {
    return text.isEmpty ? 'Unknown date' : text;
  }
  return DateFormat('dd MMM yyyy').format(date.toLocal());
}

String _titleCase(String value) {
  if (value.isEmpty) {
    return 'Unknown';
  }
  return value
      .split('_')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

int _sessionExerciseCount(Map<String, dynamic> session) {
  return (session['exercises'] as List<dynamic>? ?? const []).length;
}

String _sessionFocusLine(Map<String, dynamic> session) {
  final exercises = (session['exercises'] as List<dynamic>? ?? const [])
      .map((item) => Map<String, dynamic>.from(item as Map))
      .toList();
  if (exercises.isEmpty) {
    return 'No exercise details available';
  }
  final names = exercises.take(2).map(_exerciseName).toList();
  return names.join(' • ');
}

bool _sessionHasPrBadge(Map<String, dynamic> session) {
  final exercises = (session['exercises'] as List<dynamic>? ?? const []);
  return exercises.length >= 3 || _asDouble(session['total_volume']) >= 1000;
}

String _historyTrend(List<Map<String, dynamic>> history) {
  if (history.length < 2) {
    return 'Getting started';
  }
  final latest = _asDouble(history[0]['total_volume']);
  final previous = _asDouble(history[1]['total_volume']);
  if (latest > previous) {
    return 'Uptrend';
  }
  if (latest < previous) {
    return 'Reset week';
  }
  return 'Steady';
}

String _exerciseTrend(List<Map<String, dynamic>> history, int exerciseId) {
  if (history.length < 2) {
    return 'Building history';
  }
  final latest = _exerciseVolumeForSession(history[0], exerciseId);
  final previous = _exerciseVolumeForSession(history[1], exerciseId);
  if (latest > previous) {
    return 'Stronger';
  }
  if (latest < previous) {
    return 'Dialing in';
  }
  return 'Stable';
}

double _exerciseVolumeForSession(Map<String, dynamic> session, int exerciseId) {
  final exercises = (session['exercises'] as List<dynamic>? ?? const [])
      .map((item) => Map<String, dynamic>.from(item as Map))
      .toList();
  for (final exercise in exercises) {
    if ((exercise['exercise_id'] as num?)?.toInt() != exerciseId) {
      continue;
    }
    final sets = (exercise['sets'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    return sets.fold<double>(
      0,
      (sum, set) =>
          sum +
          (_asDouble(set['weight']) * ((set['reps'] as num?)?.toInt() ?? 0)),
    );
  }
  return 0;
}

String _exerciseSessionVolume(Map<String, dynamic> session, int exerciseId) {
  return _formatKg(_exerciseVolumeForSession(session, exerciseId));
}

String _exerciseSessionSets(Map<String, dynamic> session, int exerciseId) {
  final exercises = (session['exercises'] as List<dynamic>? ?? const [])
      .map((item) => Map<String, dynamic>.from(item as Map))
      .toList();
  for (final exercise in exercises) {
    if ((exercise['exercise_id'] as num?)?.toInt() == exerciseId) {
      return '${(exercise['sets'] as List<dynamic>? ?? const []).length}';
    }
  }
  return '0';
}
