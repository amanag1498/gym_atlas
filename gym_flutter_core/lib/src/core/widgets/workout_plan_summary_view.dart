import 'package:flutter/material.dart';

Future<void> showWorkoutPlanSummarySheet(
  BuildContext context, {
  required Map<String, dynamic> plan,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.transparent,
    builder: (context) => DraggableScrollableSheet(
      initialChildSize: 0.86,
      minChildSize: 0.58,
      maxChildSize: 0.96,
      expand: false,
      builder: (context, controller) => Container(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
        ),
        child: ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 28),
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.outlineVariant,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
            ),
            const SizedBox(height: 18),
            WorkoutPlanSummaryView(plan: plan),
          ],
        ),
      ),
    ),
  );
}

class WorkoutPlanSummaryView extends StatelessWidget {
  const WorkoutPlanSummaryView({super.key, required this.plan});

  final Map<String, dynamic> plan;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final days = _maps(plan['days']);
    final totalExercises = days.fold<int>(
      0,
      (total, day) => total + _maps(day['exercises']).length,
    );
    final guidance = <MapEntry<String, String>>[
      MapEntry('Goal', _text(plan['goal'])),
      MapEntry('Difficulty', _titleCase(_text(plan['difficulty']))),
      MapEntry('Plan notes', _text(plan['notes'])),
    ].where((entry) => entry.value.isNotEmpty).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _text(plan['name'], fallback: 'Workout plan'),
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    days.isEmpty
                        ? 'No workout days added yet'
                        : '${days.length} training day${days.length == 1 ? '' : 's'} with $totalExercises exercise${totalExercises == 1 ? '' : 's'}',
                    style: theme.textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'Close',
              onPressed: () => Navigator.of(context).maybePop(),
              icon: const Icon(Icons.close_rounded),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            _SummaryChip(
              icon: Icons.calendar_view_week_rounded,
              label: '${days.length} days',
            ),
            _SummaryChip(
              icon: Icons.fitness_center_rounded,
              label: '$totalExercises exercises',
            ),
            if (plan['duration_weeks'] != null)
              _SummaryChip(
                icon: Icons.date_range_rounded,
                label: '${_number(plan['duration_weeks'])} weeks',
              ),
            if (plan['estimated_session_minutes'] != null)
              _SummaryChip(
                icon: Icons.timer_outlined,
                label:
                    '${_number(plan['estimated_session_minutes'])} min/session',
              ),
          ],
        ),
        if (guidance.isNotEmpty) ...[
          const SizedBox(height: 16),
          _SummarySection(
            title: 'Plan guidance',
            icon: Icons.notes_rounded,
            children: guidance
                .map(
                  (entry) => _SummaryLine(label: entry.key, value: entry.value),
                )
                .toList(),
          ),
        ],
        const SizedBox(height: 20),
        Text(
          'Training days',
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 10),
        if (days.isEmpty)
          const _EmptyDays()
        else
          ...days.indexed.map(
            (entry) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _WorkoutDayCard(index: entry.$1, day: entry.$2),
            ),
          ),
      ],
    );
  }
}

class _WorkoutDayCard extends StatelessWidget {
  const _WorkoutDayCard({required this.index, required this.day});

  final int index;
  final Map<String, dynamic> day;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final exercises = _maps(day['exercises']);
    final focus = _text(day['focus']);
    final notes = _text(day['notes']);

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: colors.primaryContainer,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Text(
                  '${day['day_number'] ?? index + 1}',
                  style: TextStyle(
                    color: colors.onPrimaryContainer,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _text(day['label'], fallback: 'Workout day ${index + 1}'),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (focus.isNotEmpty)
                      Text(focus, style: theme.textTheme.bodySmall),
                  ],
                ),
              ),
              Text(
                '${exercises.length} items',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: colors.primary,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          if (notes.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(notes, style: theme.textTheme.bodyMedium),
          ],
          const SizedBox(height: 12),
          if (exercises.isEmpty)
            const Text('No exercises added')
          else
            ...exercises.indexed.map(
              (entry) => _ExerciseSummary(index: entry.$1, exercise: entry.$2),
            ),
        ],
      ),
    );
  }
}

class _ExerciseSummary extends StatelessWidget {
  const _ExerciseSummary({required this.index, required this.exercise});

  final int index;
  final Map<String, dynamic> exercise;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final exerciseDetail = _map(exercise['exercise']);
    final metrics = <String>[
      if (exercise['sets'] != null) '${_number(exercise['sets'])} sets',
      if (_text(exercise['reps']).isNotEmpty) '${_text(exercise['reps'])} reps',
      if (exercise['target_weight'] != null)
        '${_number(exercise['target_weight'])} target weight',
      if (exercise['rest_seconds'] != null)
        '${_number(exercise['rest_seconds'])} sec rest',
    ];

    return Container(
      margin: const EdgeInsets.only(bottom: 9),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${index + 1}',
            style: theme.textTheme.labelLarge?.copyWith(
              color: colors.primary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(
                    exerciseDetail['name'] ?? exercise['exercise_name'],
                    fallback: 'Exercise',
                  ),
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (metrics.isNotEmpty)
                  Text(metrics.join(' • '), style: theme.textTheme.bodySmall),
                if (_text(exercise['notes']).isNotEmpty)
                  Text(
                    _text(exercise['notes']),
                    style: theme.textTheme.bodySmall,
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SummarySection extends StatelessWidget {
  const _SummarySection({
    required this.title,
    required this.icon,
    required this.children,
  });

  final String title;
  final IconData icon;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 18, color: colors.primary),
              const SizedBox(width: 8),
              Text(
                title,
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ...children,
        ],
      ),
    );
  }
}

class _SummaryLine extends StatelessWidget {
  const _SummaryLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 2),
        Text(value, style: Theme.of(context).textTheme.bodyMedium),
      ],
    ),
  );
}

class _SummaryChip extends StatelessWidget {
  const _SummaryChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(99),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: colors.primary),
          const SizedBox(width: 5),
          Text(
            label,
            style: Theme.of(
              context,
            ).textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}

class _EmptyDays extends StatelessWidget {
  const _EmptyDays();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(18),
    ),
    child: const Text('No workout-day details are available for this plan.'),
  );
}

List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : const [];

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

String _text(dynamic value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

String _number(dynamic value) {
  final parsed = num.tryParse(value?.toString() ?? '');
  if (parsed == null) return '--';
  return parsed % 1 == 0
      ? parsed.toInt().toString()
      : parsed.toStringAsFixed(1);
}

String _titleCase(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .where((part) => part.isNotEmpty)
    .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
    .join(' ');
