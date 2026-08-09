import 'package:flutter/material.dart';

Future<void> showDietPlanSummarySheet(
  BuildContext context, {
  required Map<String, dynamic> plan,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.transparent,
    builder: (context) => DraggableScrollableSheet(
      initialChildSize: 0.82,
      minChildSize: 0.55,
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
            DietPlanSummaryView(plan: plan),
          ],
        ),
      ),
    ),
  );
}

class DietPlanSummaryView extends StatelessWidget {
  const DietPlanSummaryView({super.key, required this.plan});

  final Map<String, dynamic> plan;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final meals = _maps(plan['meals']);
    final member = _map(plan['member']);
    final trainer = _map(plan['trainer']);
    final guidance = <MapEntry<String, String>>[
      MapEntry('Goal', _text(plan['goal'])),
      MapEntry('Dietary preferences', _text(plan['dietary_preferences'])),
      MapEntry(
        'Allergies and restrictions',
        _text(plan['allergies_and_restrictions']),
      ),
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
                    _text(plan['name'], fallback: 'Diet plan'),
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    meals.isEmpty
                        ? 'No meals added yet'
                        : '${meals.length} meal${meals.length == 1 ? '' : 's'} with complete food details',
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
            if (_text(plan['status']).isNotEmpty)
              _SummaryChip(
                icon: Icons.circle_outlined,
                label: _titleCase(_text(plan['status'])),
              ),
            if (plan['daily_calorie_target'] != null)
              _SummaryChip(
                icon: Icons.local_fire_department_outlined,
                label: '${_number(plan['daily_calorie_target'])} kcal/day',
              ),
            if (plan['protein_target_g'] != null)
              _SummaryChip(
                icon: Icons.fitness_center_outlined,
                label: 'Protein ${_number(plan['protein_target_g'])}g',
              ),
            if (plan['carbs_target_g'] != null)
              _SummaryChip(
                icon: Icons.grain_rounded,
                label: 'Carbs ${_number(plan['carbs_target_g'])}g',
              ),
            if (plan['fats_target_g'] != null)
              _SummaryChip(
                icon: Icons.water_drop_outlined,
                label: 'Fats ${_number(plan['fats_target_g'])}g',
              ),
          ],
        ),
        if (_text(plan['starts_on']).isNotEmpty ||
            _text(plan['ends_on']).isNotEmpty ||
            _text(plan['assigned_at']).isNotEmpty ||
            member.isNotEmpty ||
            trainer.isNotEmpty) ...[
          const SizedBox(height: 18),
          _SummarySection(
            title: 'Assignment',
            icon: Icons.assignment_turned_in_outlined,
            children: [
              if (member.isNotEmpty)
                _SummaryLine(
                  label: 'Member',
                  value: _text(member['name'], fallback: 'Assigned member'),
                ),
              if (trainer.isNotEmpty)
                _SummaryLine(
                  label: 'Trainer',
                  value: _text(trainer['name'], fallback: 'Assigned trainer'),
                ),
              if (_text(plan['starts_on']).isNotEmpty)
                _SummaryLine(label: 'Starts', value: _text(plan['starts_on'])),
              if (_text(plan['ends_on']).isNotEmpty)
                _SummaryLine(label: 'Ends', value: _text(plan['ends_on'])),
              if (_text(plan['assigned_at']).isNotEmpty)
                _SummaryLine(
                  label: 'Assigned',
                  value: _friendlyDate(_text(plan['assigned_at'])),
                ),
            ],
          ),
        ],
        if (guidance.isNotEmpty) ...[
          const SizedBox(height: 14),
          _SummarySection(
            title: 'Plan guidance',
            icon: Icons.notes_rounded,
            children: guidance
                .map(
                  (entry) => _SummaryLine(
                    label: entry.key,
                    value: entry.value,
                    stacked: true,
                  ),
                )
                .toList(),
          ),
        ],
        const SizedBox(height: 20),
        Text(
          'Meals and foods',
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 10),
        if (meals.isEmpty)
          const _EmptyMeals()
        else
          ...meals.indexed.map(
            (entry) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _MealSummaryCard(index: entry.$1, meal: entry.$2),
            ),
          ),
      ],
    );
  }
}

class _MealSummaryCard extends StatelessWidget {
  const _MealSummaryCard({required this.index, required this.meal});

  final int index;
  final Map<String, dynamic> meal;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final items = _maps(meal['items']);
    final details = <String>[
      if (_text(meal['scheduled_time']).isNotEmpty)
        _text(meal['scheduled_time']),
      if (meal['calories'] != null) '${_number(meal['calories'])} kcal',
      if (meal['protein_g'] != null) 'P ${_number(meal['protein_g'])}g',
      if (meal['carbs_g'] != null) 'C ${_number(meal['carbs_g'])}g',
      if (meal['fats_g'] != null) 'F ${_number(meal['fats_g'])}g',
    ];
    final colors = theme.colorScheme;

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
                width: 38,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: colors.primaryContainer,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Text(
                  '${index + 1}',
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
                      _text(meal['name'], fallback: 'Meal ${index + 1}'),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (_text(meal['meal_type']).isNotEmpty)
                      Text(
                        _titleCase(_text(meal['meal_type'])),
                        style: theme.textTheme.bodySmall,
                      ),
                  ],
                ),
              ),
            ],
          ),
          if (details.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(details.join(' • '), style: theme.textTheme.bodySmall),
          ],
          if (_text(meal['notes']).isNotEmpty) ...[
            const SizedBox(height: 9),
            Text(_text(meal['notes']), style: theme.textTheme.bodyMedium),
          ],
          const SizedBox(height: 12),
          Text(
            items.isEmpty
                ? 'No food items added'
                : '${items.length} food item${items.length == 1 ? '' : 's'}',
            style: theme.textTheme.labelLarge?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          if (items.isNotEmpty) ...[
            const SizedBox(height: 7),
            ...items.map((item) => _FoodItemSummary(item: item)),
          ],
        ],
      ),
    );
  }
}

class _FoodItemSummary extends StatelessWidget {
  const _FoodItemSummary({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final values = <String>[
      if (_text(item['quantity']).isNotEmpty) _text(item['quantity']),
      if (item['calories'] != null) '${_number(item['calories'])} kcal',
      if (item['protein_g'] != null) 'P ${_number(item['protein_g'])}g',
      if (item['carbs_g'] != null) 'C ${_number(item['carbs_g'])}g',
      if (item['fats_g'] != null) 'F ${_number(item['fats_g'])}g',
      if (item['fiber_g'] != null) 'Fiber ${_number(item['fiber_g'])}g',
    ];

    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Icon(
              Icons.circle,
              size: 7,
              color: theme.colorScheme.primary,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(item['name'], fallback: 'Food item'),
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (values.isNotEmpty)
                  Text(values.join(' • '), style: theme.textTheme.bodySmall),
                if (_text(item['notes']).isNotEmpty)
                  Text(_text(item['notes']), style: theme.textTheme.bodySmall),
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
  const _SummaryLine({
    required this.label,
    required this.value,
    this.stacked = false,
  });

  final String label;
  final String value;
  final bool stacked;

  @override
  Widget build(BuildContext context) {
    final labelWidget = Text(
      label,
      style: Theme.of(
        context,
      ).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w800),
    );
    final valueWidget = Text(
      value,
      style: Theme.of(context).textTheme.bodyMedium,
    );
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: stacked
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [labelWidget, const SizedBox(height: 2), valueWidget],
            )
          : Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(width: 86, child: labelWidget),
                Expanded(child: valueWidget),
              ],
            ),
    );
  }
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

class _EmptyMeals extends StatelessWidget {
  const _EmptyMeals();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(18),
    ),
    child: const Text('No meal details are available for this plan.'),
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

String _friendlyDate(String value) =>
    value.contains('T') ? value.split('T').first : value;
