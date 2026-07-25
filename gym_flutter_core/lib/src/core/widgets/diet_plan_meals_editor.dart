import 'package:flutter/material.dart';

class DietPlanDetailsEditor extends StatefulWidget {
  const DietPlanDetailsEditor({
    super.key,
    required this.initialPlan,
    required this.onChanged,
    this.includeStatus = false,
    this.includeSchedule = true,
    this.enabled = true,
  });

  final Map<String, dynamic> initialPlan;
  final ValueChanged<Map<String, dynamic>> onChanged;
  final bool includeStatus;
  final bool includeSchedule;
  final bool enabled;

  @override
  State<DietPlanDetailsEditor> createState() => _DietPlanDetailsEditorState();
}

class _DietPlanDetailsEditorState extends State<DietPlanDetailsEditor> {
  late final _PlanDraft _draft;

  @override
  void initState() {
    super.initState();
    _draft = _PlanDraft.fromMap(widget.initialPlan);
  }

  @override
  void dispose() {
    _draft.dispose();
    super.dispose();
  }

  void _emit([String? _]) {
    final payload = _draft.toPayload();
    if (!widget.includeSchedule) {
      payload.remove('starts_on');
      payload.remove('ends_on');
    }
    widget.onChanged(payload);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextFormField(
          controller: _draft.name,
          enabled: widget.enabled,
          decoration: const InputDecoration(labelText: 'Plan name'),
          validator: (value) => value == null || value.trim().isEmpty
              ? 'Plan name is required'
              : null,
          onChanged: _emit,
        ),
        const SizedBox(height: 10),
        TextFormField(
          controller: _draft.goal,
          enabled: widget.enabled,
          decoration: const InputDecoration(labelText: 'Goal'),
          onChanged: _emit,
        ),
        const SizedBox(height: 10),
        _twoColumns(
          _numberField(_draft.calories, 'Daily calories', integer: true),
          _numberField(_draft.protein, 'Protein g'),
        ),
        const SizedBox(height: 10),
        _twoColumns(
          _numberField(_draft.carbs, 'Carbs g'),
          _numberField(_draft.fats, 'Fats g'),
        ),
        const SizedBox(height: 10),
        if (widget.includeSchedule) ...[
          _twoColumns(
            _dateField(_draft.startsOn, 'Starts on'),
            _dateField(_draft.endsOn, 'Ends on'),
          ),
          const SizedBox(height: 10),
        ],
        TextFormField(
          controller: _draft.preferences,
          enabled: widget.enabled,
          maxLines: 2,
          decoration: const InputDecoration(labelText: 'Dietary preferences'),
          onChanged: _emit,
        ),
        const SizedBox(height: 10),
        TextFormField(
          controller: _draft.allergies,
          enabled: widget.enabled,
          maxLines: 2,
          decoration: const InputDecoration(
            labelText: 'Allergies and restrictions',
          ),
          onChanged: _emit,
        ),
        const SizedBox(height: 10),
        TextFormField(
          controller: _draft.notes,
          enabled: widget.enabled,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Plan notes'),
          onChanged: _emit,
        ),
        if (widget.includeStatus) ...[
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: _draft.status,
            items: const [
              DropdownMenuItem(value: 'active', child: Text('Active')),
              DropdownMenuItem(value: 'inactive', child: Text('Inactive')),
            ],
            onChanged: widget.enabled
                ? (value) {
                    _draft.status = value ?? 'active';
                    _emit();
                  }
                : null,
            decoration: const InputDecoration(labelText: 'Status'),
          ),
        ],
      ],
    );
  }

  Widget _numberField(
    TextEditingController controller,
    String label, {
    bool integer = false,
  }) {
    return TextFormField(
      controller: controller,
      enabled: widget.enabled,
      keyboardType: TextInputType.numberWithOptions(decimal: !integer),
      decoration: InputDecoration(labelText: label),
      validator: (value) => _numberError(value, integer: integer),
      onChanged: _emit,
    );
  }

  Widget _dateField(TextEditingController controller, String label) {
    return TextFormField(
      controller: controller,
      enabled: widget.enabled,
      keyboardType: TextInputType.datetime,
      decoration: InputDecoration(labelText: label, hintText: 'YYYY-MM-DD'),
      validator: (value) {
        final text = value?.trim() ?? '';
        if (text.isEmpty) return null;
        return RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(text)
            ? null
            : 'Use YYYY-MM-DD';
      },
      onChanged: _emit,
    );
  }

  Widget _twoColumns(Widget first, Widget second) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: first),
        const SizedBox(width: 10),
        Expanded(child: second),
      ],
    );
  }
}

class DietPlanMealsEditor extends StatefulWidget {
  const DietPlanMealsEditor({
    super.key,
    required this.initialMeals,
    required this.onChanged,
    this.enabled = true,
  });

  final List<Map<String, dynamic>> initialMeals;
  final ValueChanged<List<Map<String, dynamic>>> onChanged;
  final bool enabled;

  @override
  State<DietPlanMealsEditor> createState() => _DietPlanMealsEditorState();
}

class _DietPlanMealsEditorState extends State<DietPlanMealsEditor> {
  late final List<_MealDraft> _meals;

  @override
  void initState() {
    super.initState();
    _meals = widget.initialMeals.isEmpty
        ? [_MealDraft.empty()]
        : widget.initialMeals.map(_MealDraft.fromMap).toList();
  }

  @override
  void dispose() {
    for (final meal in _meals) {
      meal.dispose();
    }
    super.dispose();
  }

  void _emit() => widget.onChanged(
    _meals.map((meal) => meal.toPayload()).toList(growable: false),
  );

  void _addMeal() {
    setState(() => _meals.add(_MealDraft.empty()));
    _emit();
  }

  void _removeMeal(int index) {
    if (_meals.length == 1) return;
    final removed = _meals.removeAt(index);
    removed.dispose();
    setState(() {});
    _emit();
  }

  void _addItem(_MealDraft meal) {
    setState(() => meal.items.add(_FoodDraft.empty()));
    _emit();
  }

  void _removeItem(_MealDraft meal, int index) {
    final removed = meal.items.removeAt(index);
    removed.dispose();
    setState(() {});
    _emit();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Meals and products',
                    style: theme.textTheme.titleMedium,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Add meal timings, nutrition and repeatable food lines.',
                    style: theme.textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            OutlinedButton.icon(
              onPressed: widget.enabled ? _addMeal : null,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Meal'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        ..._meals.asMap().entries.map(
          (entry) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _mealCard(entry.key, entry.value),
          ),
        ),
      ],
    );
  }

  Widget _mealCard(int index, _MealDraft meal) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      elevation: 0,
      margin: EdgeInsets.zero,
      color: colors.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: colors.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        initiallyExpanded: index == 0,
        title: Text(
          meal.name.text.trim().isEmpty
              ? 'Meal ${index + 1}'
              : meal.name.text.trim(),
        ),
        subtitle: Text(
          '${meal.items.length} product${meal.items.length == 1 ? '' : 's'}',
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        children: [
          Row(
            children: [
              Expanded(
                child: _textField(
                  meal.name,
                  'Meal name',
                  required: true,
                  onChanged: (_) {
                    setState(() {});
                    _emit();
                  },
                ),
              ),
              IconButton(
                tooltip: 'Remove meal',
                onPressed: widget.enabled && _meals.length > 1
                    ? () => _removeMeal(index)
                    : null,
                icon: const Icon(Icons.delete_outline_rounded),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _twoColumns(
            _textField(meal.type, 'Meal type', onChanged: (_) => _emit()),
            _textField(
              meal.time,
              'Time (HH:mm)',
              keyboardType: TextInputType.datetime,
              validator: _timeValidator,
              onChanged: (_) => _emit(),
            ),
          ),
          const SizedBox(height: 10),
          _nutritionGrid(
            calories: meal.calories,
            protein: meal.protein,
            carbs: meal.carbs,
            fats: meal.fats,
          ),
          const SizedBox(height: 10),
          _textField(
            meal.notes,
            'Meal notes or substitutions',
            maxLines: 2,
            onChanged: (_) => _emit(),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Food and products',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
              ),
              TextButton.icon(
                onPressed: widget.enabled ? () => _addItem(meal) : null,
                icon: const Icon(Icons.add_rounded),
                label: const Text('Add product'),
              ),
            ],
          ),
          if (meal.items.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                'No products yet. Add individual foods, supplements or prepared dishes.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            )
          else
            ...meal.items.asMap().entries.map(
              (entry) => Padding(
                padding: const EdgeInsets.only(top: 10),
                child: _itemCard(meal, entry.key, entry.value),
              ),
            ),
        ],
      ),
    );
  }

  Widget _itemCard(_MealDraft meal, int index, _FoodDraft item) {
    final colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(
                  child: _textField(
                    item.name,
                    'Food or product',
                    required: true,
                    onChanged: (_) => _emit(),
                  ),
                ),
                IconButton(
                  tooltip: 'Remove product',
                  onPressed: widget.enabled
                      ? () => _removeItem(meal, index)
                      : null,
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const SizedBox(height: 10),
            _textField(
              item.quantity,
              'Quantity or serving',
              onChanged: (_) => _emit(),
            ),
            const SizedBox(height: 10),
            _nutritionGrid(
              calories: item.calories,
              protein: item.protein,
              carbs: item.carbs,
              fats: item.fats,
            ),
            const SizedBox(height: 10),
            _textField(
              item.notes,
              'Preparation or product notes',
              maxLines: 2,
              onChanged: (_) => _emit(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _nutritionGrid({
    required TextEditingController calories,
    required TextEditingController protein,
    required TextEditingController carbs,
    required TextEditingController fats,
  }) {
    return Column(
      children: [
        _twoColumns(
          _numberField(calories, 'Calories', integer: true),
          _numberField(protein, 'Protein g'),
        ),
        const SizedBox(height: 10),
        _twoColumns(
          _numberField(carbs, 'Carbs g'),
          _numberField(fats, 'Fats g'),
        ),
      ],
    );
  }

  Widget _twoColumns(Widget first, Widget second) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: first),
        const SizedBox(width: 10),
        Expanded(child: second),
      ],
    );
  }

  Widget _numberField(
    TextEditingController controller,
    String label, {
    bool integer = false,
  }) {
    return _textField(
      controller,
      label,
      keyboardType: TextInputType.numberWithOptions(decimal: !integer),
      validator: (value) => _numberValidator(value, integer: integer),
      onChanged: (_) => _emit(),
    );
  }

  Widget _textField(
    TextEditingController controller,
    String label, {
    bool required = false,
    int maxLines = 1,
    TextInputType? keyboardType,
    String? Function(String?)? validator,
    ValueChanged<String>? onChanged,
  }) {
    return TextFormField(
      controller: controller,
      enabled: widget.enabled,
      maxLines: maxLines,
      keyboardType: keyboardType,
      decoration: InputDecoration(labelText: label),
      validator:
          validator ??
          (required
              ? (value) => value == null || value.trim().isEmpty
                    ? '$label is required'
                    : null
              : null),
      onChanged: onChanged,
    );
  }

  String? _numberValidator(String? value, {required bool integer}) {
    return _numberError(value, integer: integer);
  }

  String? _timeValidator(String? value) {
    final text = value?.trim() ?? '';
    if (text.isEmpty) return null;
    return RegExp(r'^(?:[01]\d|2[0-3]):[0-5]\d$').hasMatch(text)
        ? null
        : 'Use HH:mm';
  }
}

class _PlanDraft {
  _PlanDraft({
    required this.name,
    required this.goal,
    required this.calories,
    required this.protein,
    required this.carbs,
    required this.fats,
    required this.preferences,
    required this.allergies,
    required this.notes,
    required this.startsOn,
    required this.endsOn,
    required this.status,
  });

  factory _PlanDraft.fromMap(Map<String, dynamic> plan) {
    return _PlanDraft(
      name: _controller(plan['name']),
      goal: _controller(plan['goal']),
      calories: _controller(plan['daily_calorie_target']),
      protein: _controller(plan['protein_target_g']),
      carbs: _controller(plan['carbs_target_g']),
      fats: _controller(plan['fats_target_g']),
      preferences: _controller(plan['dietary_preferences']),
      allergies: _controller(plan['allergies_and_restrictions']),
      notes: _controller(plan['notes']),
      startsOn: _controller(plan['starts_on']),
      endsOn: _controller(plan['ends_on']),
      status: plan['status']?.toString() ?? 'active',
    );
  }

  final TextEditingController name;
  final TextEditingController goal;
  final TextEditingController calories;
  final TextEditingController protein;
  final TextEditingController carbs;
  final TextEditingController fats;
  final TextEditingController preferences;
  final TextEditingController allergies;
  final TextEditingController notes;
  final TextEditingController startsOn;
  final TextEditingController endsOn;
  String status;

  Map<String, dynamic> toPayload() => {
    'name': name.text.trim(),
    'goal': _nullableText(goal),
    'daily_calorie_target': _integer(calories),
    'protein_target_g': _decimal(protein),
    'carbs_target_g': _decimal(carbs),
    'fats_target_g': _decimal(fats),
    'dietary_preferences': _nullableText(preferences),
    'allergies_and_restrictions': _nullableText(allergies),
    'notes': _nullableText(notes),
    'starts_on': _nullableText(startsOn),
    'ends_on': _nullableText(endsOn),
    'status': status,
  };

  void dispose() {
    name.dispose();
    goal.dispose();
    calories.dispose();
    protein.dispose();
    carbs.dispose();
    fats.dispose();
    preferences.dispose();
    allergies.dispose();
    notes.dispose();
    startsOn.dispose();
    endsOn.dispose();
  }
}

class _MealDraft {
  _MealDraft({
    required this.id,
    required this.name,
    required this.type,
    required this.time,
    required this.calories,
    required this.protein,
    required this.carbs,
    required this.fats,
    required this.notes,
    required this.items,
  });

  factory _MealDraft.empty() => _MealDraft.fromMap(const {});

  factory _MealDraft.fromMap(Map<String, dynamic> meal) {
    return _MealDraft(
      id: (meal['id'] as num?)?.toInt(),
      name: _controller(meal['name']),
      type: _controller(meal['meal_type']),
      time: _controller(_time(meal['scheduled_time'])),
      calories: _controller(meal['calories']),
      protein: _controller(meal['protein_g']),
      carbs: _controller(meal['carbs_g']),
      fats: _controller(meal['fats_g']),
      notes: _controller(meal['notes']),
      items: (meal['items'] as List<dynamic>? ?? const [])
          .map(
            (item) =>
                _FoodDraft.fromMap(Map<String, dynamic>.from(item as Map)),
          )
          .toList(),
    );
  }

  final int? id;
  final TextEditingController name;
  final TextEditingController type;
  final TextEditingController time;
  final TextEditingController calories;
  final TextEditingController protein;
  final TextEditingController carbs;
  final TextEditingController fats;
  final TextEditingController notes;
  final List<_FoodDraft> items;

  Map<String, dynamic> toPayload() => {
    if (id != null) 'id': id,
    'name': name.text.trim(),
    'meal_type': _nullableText(type),
    'scheduled_time': _nullableText(time),
    'calories': _integer(calories),
    'protein_g': _decimal(protein),
    'carbs_g': _decimal(carbs),
    'fats_g': _decimal(fats),
    'notes': _nullableText(notes),
    'items': items.map((item) => item.toPayload()).toList(growable: false),
  };

  void dispose() {
    name.dispose();
    type.dispose();
    time.dispose();
    calories.dispose();
    protein.dispose();
    carbs.dispose();
    fats.dispose();
    notes.dispose();
    for (final item in items) {
      item.dispose();
    }
  }
}

class _FoodDraft {
  _FoodDraft({
    required this.id,
    required this.name,
    required this.quantity,
    required this.calories,
    required this.protein,
    required this.carbs,
    required this.fats,
    required this.notes,
  });

  factory _FoodDraft.empty() => _FoodDraft.fromMap(const {});

  factory _FoodDraft.fromMap(Map<String, dynamic> item) {
    return _FoodDraft(
      id: (item['id'] as num?)?.toInt(),
      name: _controller(item['name']),
      quantity: _controller(item['quantity']),
      calories: _controller(item['calories']),
      protein: _controller(item['protein_g']),
      carbs: _controller(item['carbs_g']),
      fats: _controller(item['fats_g']),
      notes: _controller(item['notes']),
    );
  }

  final int? id;
  final TextEditingController name;
  final TextEditingController quantity;
  final TextEditingController calories;
  final TextEditingController protein;
  final TextEditingController carbs;
  final TextEditingController fats;
  final TextEditingController notes;

  Map<String, dynamic> toPayload() => {
    if (id != null) 'id': id,
    'name': name.text.trim(),
    'quantity': _nullableText(quantity),
    'calories': _integer(calories),
    'protein_g': _decimal(protein),
    'carbs_g': _decimal(carbs),
    'fats_g': _decimal(fats),
    'notes': _nullableText(notes),
  };

  void dispose() {
    name.dispose();
    quantity.dispose();
    calories.dispose();
    protein.dispose();
    carbs.dispose();
    fats.dispose();
    notes.dispose();
  }
}

TextEditingController _controller(dynamic value) =>
    TextEditingController(text: value?.toString() ?? '');

String? _nullableText(TextEditingController controller) {
  final text = controller.text.trim();
  return text.isEmpty ? null : text;
}

int? _integer(TextEditingController controller) =>
    num.tryParse(controller.text.trim())?.round();

num? _decimal(TextEditingController controller) =>
    num.tryParse(controller.text.trim());

String? _time(dynamic value) {
  final text = value?.toString();
  if (text == null || text.isEmpty) return null;
  return text.length >= 5 ? text.substring(0, 5) : text;
}

String? _numberError(String? value, {required bool integer}) {
  final text = value?.trim() ?? '';
  if (text.isEmpty) return null;
  final number = num.tryParse(text);
  if (number == null || number < 0) return 'Enter a valid value';
  if (integer && number != number.roundToDouble()) {
    return 'Use a whole number';
  }
  return null;
}
