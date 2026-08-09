import 'dart:async';

import 'package:flutter/material.dart';

typedef FoodCatalogSearch =
    Future<List<Map<String, dynamic>>> Function(String query);

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
          decoration: const InputDecoration(
            labelText: 'Goal (optional)',
            hintText: 'For example: Muscle gain',
          ),
          onChanged: _emit,
        ),
        const SizedBox(height: 10),
        ExpansionTile(
          tilePadding: EdgeInsets.zero,
          childrenPadding: EdgeInsets.zero,
          title: const Text('Optional plan details'),
          subtitle: const Text('Targets, dates, preferences and notes'),
          children: [
            _twoColumns(
              _numberField(_draft.calories, 'Daily calories', integer: true),
              _numberField(_draft.protein, 'Protein g'),
            ),
            const SizedBox(height: 10),
            _twoColumns(
              _numberField(_draft.carbs, 'Carbs g'),
              _numberField(_draft.fats, 'Fats g'),
            ),
            if (widget.includeSchedule) ...[
              const SizedBox(height: 10),
              _twoColumns(
                _dateField(_draft.startsOn, 'Starts on'),
                _dateField(_draft.endsOn, 'Ends on'),
              ),
            ],
            const SizedBox(height: 10),
            TextFormField(
              controller: _draft.preferences,
              enabled: widget.enabled,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'Dietary preferences',
              ),
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
          ],
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
    this.foodCatalog = const [],
    this.onSearchFoodCatalog,
  });

  final List<Map<String, dynamic>> initialMeals;
  final ValueChanged<List<Map<String, dynamic>>> onChanged;
  final bool enabled;
  final List<Map<String, dynamic>> foodCatalog;
  final FoodCatalogSearch? onSearchFoodCatalog;

  @override
  State<DietPlanMealsEditor> createState() => _DietPlanMealsEditorState();
}

class _DietPlanMealsEditorState extends State<DietPlanMealsEditor> {
  late final List<_MealDraft> _meals;
  Timer? _foodSearchDebounce;

  @override
  void initState() {
    super.initState();
    _meals = widget.initialMeals.isEmpty
        ? [_MealDraft.empty(name: 'Meal 1')]
        : widget.initialMeals.map(_MealDraft.fromMap).toList();
  }

  @override
  void dispose() {
    _foodSearchDebounce?.cancel();
    for (final meal in _meals) {
      meal.dispose();
    }
    super.dispose();
  }

  void _emit() => widget.onChanged(
    _meals.map((meal) => meal.toPayload()).toList(growable: false),
  );

  Future<void> _addMeal() async {
    var draftName = 'Meal ${_meals.length + 1}';
    final name = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Add meal'),
        content: TextFormField(
          initialValue: draftName,
          autofocus: true,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(
            labelText: 'Meal name',
            hintText: 'For example: Pre-workout meal',
          ),
          onChanged: (value) => draftName = value,
          onFieldSubmitted: (value) =>
              Navigator.of(dialogContext).pop(value.trim()),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(draftName.trim()),
            child: const Text('Add'),
          ),
        ],
      ),
    );
    if (name == null || name.isEmpty || !mounted) return;
    setState(() => _meals.add(_MealDraft.empty(name: name)));
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

  Future<void> _chooseCatalogFood(_MealDraft meal) async {
    var search = '';
    var remoteFoods = widget.foodCatalog;
    var loading = false;
    final selected = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          final query = search.trim().toLowerCase();
          final foods = widget.onSearchFoodCatalog != null
              ? remoteFoods
              : widget.foodCatalog.where((food) {
                  if (query.isEmpty) return true;
                  return '${food['name'] ?? ''} ${food['category'] ?? ''}'
                      .toLowerCase()
                      .contains(query);
                }).toList();
          return SizedBox(
            height: MediaQuery.sizeOf(context).height * 0.78,
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                  child: TextField(
                    autofocus: true,
                    decoration: const InputDecoration(
                      labelText: 'Search food catalog',
                      prefixIcon: Icon(Icons.search_rounded),
                    ),
                    onChanged: (value) {
                      setSheetState(() {
                        search = value;
                        loading = widget.onSearchFoodCatalog != null;
                      });
                      _foodSearchDebounce?.cancel();
                      if (widget.onSearchFoodCatalog == null) return;
                      _foodSearchDebounce = Timer(
                        const Duration(milliseconds: 350),
                        () async {
                          try {
                            final results = await widget.onSearchFoodCatalog!(
                              value,
                            );
                            if (!sheetContext.mounted || search != value) {
                              return;
                            }
                            setSheetState(() {
                              remoteFoods = results;
                              loading = false;
                            });
                          } catch (_) {
                            if (!sheetContext.mounted || search != value) {
                              return;
                            }
                            setSheetState(() {
                              remoteFoods = const [];
                              loading = false;
                            });
                          }
                        },
                      );
                    },
                  ),
                ),
                if (loading) const LinearProgressIndicator(minHeight: 2),
                Expanded(
                  child: foods.isEmpty
                      ? const Center(child: Text('No matching catalog food'))
                      : ListView.separated(
                          padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
                          itemCount: foods.length,
                          separatorBuilder: (_, _) => const Divider(height: 1),
                          itemBuilder: (context, index) {
                            final food = foods[index];
                            final category = food['category']?.toString() ?? '';
                            final calories = food['calories'];
                            final tags =
                                (food['dietary_tags'] as List<dynamic>? ??
                                        const [])
                                    .map((tag) => tag.toString())
                                    .where((tag) => tag.isNotEmpty)
                                    .toList();
                            final allergens =
                                (food['allergens'] as List<dynamic>? ??
                                        const [])
                                    .map((allergen) => allergen.toString())
                                    .where((allergen) => allergen.isNotEmpty)
                                    .toList();
                            return ListTile(
                              leading: const CircleAvatar(
                                child: Icon(Icons.restaurant_rounded),
                              ),
                              title: Text(food['name']?.toString() ?? 'Food'),
                              subtitle: Text(
                                [
                                  if (category.isNotEmpty) category,
                                  if (food['default_quantity'] != null)
                                    food['default_quantity'].toString(),
                                  if (calories != null) '$calories kcal',
                                  if (tags.isNotEmpty) tags.join(', '),
                                  if (allergens.isNotEmpty)
                                    'Contains ${allergens.join(', ')}',
                                ].join(' · '),
                              ),
                              onTap: () => Navigator.of(sheetContext).pop(food),
                            );
                          },
                        ),
                ),
              ],
            ),
          );
        },
      ),
    );
    if (selected == null || !mounted) return;
    setState(() => meal.items.add(_FoodDraft.fromCatalog(selected)));
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
                  Text('Build meals', style: theme.textTheme.titleMedium),
                  const SizedBox(height: 2),
                  Text(
                    'Name each meal your way, then add its foods and servings.',
                    style: theme.textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            OutlinedButton.icon(
              onPressed: widget.enabled ? _addMeal : null,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Add meal'),
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
        initiallyExpanded: true,
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
          _textField(
            meal.time,
            'Meal time (optional)',
            keyboardType: TextInputType.datetime,
            validator: _timeValidator,
            onChanged: (_) => _emit(),
          ),
          const SizedBox(height: 10),
          ExpansionTile(
            tilePadding: EdgeInsets.zero,
            childrenPadding: EdgeInsets.zero,
            title: const Text('Optional meal details'),
            subtitle: const Text('Macros, calories and coach notes'),
            children: [
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
            ],
          ),
          const SizedBox(height: 14),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Food and products',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 4),
              Wrap(
                spacing: 6,
                runSpacing: 4,
                children: [
                  if (widget.foodCatalog.isNotEmpty ||
                      widget.onSearchFoodCatalog != null)
                    TextButton.icon(
                      onPressed: widget.enabled
                          ? () => _chooseCatalogFood(meal)
                          : null,
                      icon: const Icon(Icons.menu_book_rounded),
                      label: const Text('Choose catalog food'),
                    ),
                  TextButton.icon(
                    onPressed: widget.enabled ? () => _addItem(meal) : null,
                    icon: const Icon(Icons.add_rounded),
                    label: const Text('Add custom food'),
                  ),
                ],
              ),
            ],
          ),
          if (meal.items.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                'Add foods, supplements or prepared dishes for this meal.',
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
            if (item.catalogItemId != null)
              Align(
                alignment: Alignment.centerLeft,
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Chip(
                    visualDensity: VisualDensity.compact,
                    avatar: const Icon(Icons.verified_rounded, size: 16),
                    label: const Text('From food catalog'),
                    deleteIcon: const Icon(Icons.link_off_rounded, size: 17),
                    onDeleted: widget.enabled
                        ? () {
                            setState(() => item.catalogItemId = null);
                            _emit();
                          }
                        : null,
                  ),
                ),
              ),
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
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              childrenPadding: EdgeInsets.zero,
              title: const Text('Optional nutrition'),
              children: [
                _nutritionGrid(
                  calories: item.calories,
                  protein: item.protein,
                  carbs: item.carbs,
                  fats: item.fats,
                  fiber: item.fiber,
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
    TextEditingController? fiber,
  }) {
    return Column(
      children: [
        _twoColumns(
          _numberField(calories, 'Calories', integer: true),
          _numberField(protein, 'Protein g'),
        ),
        if (fiber != null) ...[
          const SizedBox(height: 10),
          _numberField(fiber, 'Fiber g'),
        ],
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

  factory _MealDraft.empty({required String name}) =>
      _MealDraft.fromMap({'name': name});

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
    'meal_type': _nullableText(type) ?? _mealType(name.text),
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
    required this.catalogItemId,
    required this.name,
    required this.quantity,
    required this.calories,
    required this.protein,
    required this.carbs,
    required this.fats,
    required this.fiber,
    required this.notes,
  });

  factory _FoodDraft.empty() => _FoodDraft.fromMap(const {});

  factory _FoodDraft.fromMap(Map<String, dynamic> item) {
    return _FoodDraft(
      id: (item['id'] as num?)?.toInt(),
      catalogItemId: (item['food_catalog_item_id'] as num?)?.toInt(),
      name: _controller(item['name']),
      quantity: _controller(item['quantity']),
      calories: _controller(item['calories']),
      protein: _controller(item['protein_g']),
      carbs: _controller(item['carbs_g']),
      fats: _controller(item['fats_g']),
      fiber: _controller(item['fiber_g']),
      notes: _controller(item['notes']),
    );
  }

  factory _FoodDraft.fromCatalog(Map<String, dynamic> food) {
    return _FoodDraft.fromMap({
      'food_catalog_item_id': food['id'],
      'name': food['name'],
      'quantity': food['default_quantity'],
      'calories': food['calories'],
      'protein_g': food['protein_g'],
      'carbs_g': food['carbs_g'],
      'fats_g': food['fats_g'],
      'fiber_g': food['fiber_g'],
      'notes': food['notes'],
    });
  }

  final int? id;
  int? catalogItemId;
  final TextEditingController name;
  final TextEditingController quantity;
  final TextEditingController calories;
  final TextEditingController protein;
  final TextEditingController carbs;
  final TextEditingController fats;
  final TextEditingController fiber;
  final TextEditingController notes;

  Map<String, dynamic> toPayload() => {
    if (id != null) 'id': id,
    if (catalogItemId != null) 'food_catalog_item_id': catalogItemId,
    'name': name.text.trim(),
    'quantity': _nullableText(quantity),
    'calories': _integer(calories),
    'protein_g': _decimal(protein),
    'carbs_g': _decimal(carbs),
    'fats_g': _decimal(fats),
    'fiber_g': _decimal(fiber),
    'notes': _nullableText(notes),
  };

  void dispose() {
    name.dispose();
    quantity.dispose();
    calories.dispose();
    protein.dispose();
    carbs.dispose();
    fats.dispose();
    fiber.dispose();
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

String _mealType(String name) {
  final normalized = name
      .trim()
      .toLowerCase()
      .replaceAll(RegExp(r'[^a-z0-9]+'), '_')
      .replaceAll(RegExp(r'^_+|_+$'), '');
  return normalized.isEmpty ? 'meal' : normalized;
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
