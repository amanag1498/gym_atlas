import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/premium_card.dart';
import 'member_repository.dart';

class SharedWorkoutPlanScreen extends StatefulWidget {
  const SharedWorkoutPlanScreen({
    super.key,
    required this.token,
    required this.repository,
  });

  final String token;
  final MemberRepository repository;

  @override
  State<SharedWorkoutPlanScreen> createState() =>
      _SharedWorkoutPlanScreenState();
}

class _SharedWorkoutPlanScreenState extends State<SharedWorkoutPlanScreen> {
  bool _loading = true;
  bool _saving = false;
  bool _saved = false;
  String? _error;
  Map<String, dynamic> _share = const {};

  Map<String, dynamic> get _snapshot => Map<String, dynamic>.from(
    _share['snapshot'] as Map? ?? const <String, dynamic>{},
  );

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
      final response = await widget.repository.fetchWorkoutPlanShare(
        widget.token,
      );
      if (!mounted) return;
      setState(() {
        _share = Map<String, dynamic>.from(
          response['data'] as Map? ?? const <String, dynamic>{},
        );
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error =
            'The link may have expired or been revoked. Ask the sender to share the plan again.';
      });
    }
  }

  Future<void> _savePlan() async {
    if (_saving || _saved) return;
    setState(() => _saving = true);
    try {
      await widget.repository.adoptWorkoutPlanShare(widget.token);
      if (!mounted) return;
      setState(() {
        _saving = false;
        _saved = true;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Workout plan saved to your workouts.')),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'We could not save this plan. Check your connection and try again.',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.background,
        surfaceTintColor: Colors.transparent,
        title: const Text('Shared workout'),
      ),
      body: SafeArea(
        top: false,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
            ? _ErrorState(message: _error!, onRetry: _load)
            : _PlanPreview(share: _share, snapshot: _snapshot, saved: _saved),
      ),
      bottomNavigationBar: _loading || _error != null
          ? null
          : SafeArea(
              top: false,
              minimum: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.sm,
                AppSpacing.lg,
                AppSpacing.md,
              ),
              child: SizedBox(
                height: 52,
                child: FilledButton.icon(
                  onPressed: _saved
                      ? () => context.go('/home?section=workout')
                      : _savePlan,
                  icon: _saving
                      ? const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Icon(
                          _saved
                              ? Icons.fitness_center_rounded
                              : Icons.add_rounded,
                        ),
                  label: Text(
                    _saving
                        ? 'Saving plan...'
                        : _saved
                        ? 'Open my workouts'
                        : 'Save to my workouts',
                  ),
                ),
              ),
            ),
    );
  }
}

class _PlanPreview extends StatelessWidget {
  const _PlanPreview({
    required this.share,
    required this.snapshot,
    required this.saved,
  });

  final Map<String, dynamic> share;
  final Map<String, dynamic> snapshot;
  final bool saved;

  @override
  Widget build(BuildContext context) {
    final sharedBy = Map<String, dynamic>.from(
      share['shared_by'] as Map? ?? const <String, dynamic>{},
    );
    final days = (snapshot['days'] as List? ?? const [])
        .whereType<Map>()
        .map((day) => Map<String, dynamic>.from(day))
        .toList();
    final exerciseCount = days.fold<int>(
      0,
      (count, day) => count + (day['exercises'] as List? ?? const []).length,
    );

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.sm,
        AppSpacing.lg,
        AppSpacing.xxl,
      ),
      children: [
        Text(
          snapshot['name']?.toString() ?? 'Workout plan',
          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.w900,
            letterSpacing: -0.8,
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        Text(
          'Shared by ${sharedBy['name']?.toString().trim().isNotEmpty == true ? sharedBy['name'] : 'an Atlas member'}',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: AppColors.textSecondary,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: AppSpacing.xs,
                runSpacing: AppSpacing.xs,
                children: [
                  _PlanFact(label: '${days.length} training days'),
                  _PlanFact(label: '$exerciseCount exercises'),
                  if (snapshot['duration_weeks'] != null)
                    _PlanFact(label: '${snapshot['duration_weeks']} weeks'),
                  if (snapshot['difficulty'] != null)
                    _PlanFact(label: snapshot['difficulty'].toString()),
                ],
              ),
              if (snapshot['goal']?.toString().trim().isNotEmpty == true) ...[
                const SizedBox(height: AppSpacing.md),
                Text(
                  'Goal: ${snapshot['goal']}',
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.sm),
              Text(
                saved
                    ? 'A personal copy is now in your workouts. The sender’s plan stays unchanged.'
                    : 'Review the plan below. Saving creates your own copy and does not change the sender’s plan.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: saved ? AppColors.success : AppColors.textSecondary,
                  height: 1.45,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
        Text(
          'Plan overview',
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        if (days.isEmpty)
          const PremiumCard(
            child: Text('This plan does not have any training days yet.'),
          )
        else
          ...days.map((day) => _WorkoutDayCard(day: day)),
      ],
    );
  }
}

class _PlanFact extends StatelessWidget {
  const _PlanFact({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        child: Text(
          label,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: AppColors.textSecondary,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

class _WorkoutDayCard extends StatelessWidget {
  const _WorkoutDayCard({required this.day});

  final Map<String, dynamic> day;

  @override
  Widget build(BuildContext context) {
    final exercises = (day['exercises'] as List? ?? const [])
        .whereType<Map>()
        .map((exercise) => Map<String, dynamic>.from(exercise))
        .toList();

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: PremiumCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'DAY ${day['day_number'] ?? ''}',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: AppColors.primary,
                fontWeight: FontWeight.w900,
                letterSpacing: 1.1,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              day['label']?.toString().trim().isNotEmpty == true
                  ? day['label'].toString()
                  : day['focus']?.toString().trim().isNotEmpty == true
                  ? day['focus'].toString()
                  : 'Training day',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            ...exercises.map((exercise) {
              final reps = exercise['reps']?.toString();
              final sets = exercise['sets']?.toString() ?? '—';
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 7),
                child: Row(
                  children: [
                    const Icon(
                      Icons.fitness_center_rounded,
                      size: 20,
                      color: AppColors.primary,
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: Text(
                        exercise['exercise_name']?.toString() ?? 'Exercise',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    Text(
                      reps == null || reps.isEmpty
                          ? '$sets sets'
                          : '$sets × $reps',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textMuted,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              );
            }),
          ],
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.link_off_rounded,
              size: 48,
              color: AppColors.textMuted,
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              'This workout link is unavailable',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: AppSpacing.lg),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}
