import 'package:flutter/material.dart';
import 'package:gym_flutter_core/diet_plan_meals_editor.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/loading_state.dart';
import '../../../core/widgets/premium_card.dart';
import 'member_repository.dart';

class MemberDietPlanScreen extends StatefulWidget {
  const MemberDietPlanScreen({super.key, required this.repository});
  final MemberRepository repository;

  @override
  State<MemberDietPlanScreen> createState() => _MemberDietPlanScreenState();
}

class _MemberDietPlanScreenState extends State<MemberDietPlanScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _plans = const [];
  List<Map<String, dynamic>> _templates = const [];
  int? _selectedPlanId;
  final Set<int> _completedMealIds = <int>{};

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
      final plansResponse = await widget.repository.fetchDietPlans();
      _plans = (plansResponse['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      try {
        final templatesResponse = await widget.repository.fetchDietTemplates();
        _templates = (templatesResponse['data'] as List<dynamic>? ?? const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
      } catch (_) {
        _templates = const [];
      }
      _selectedPlanId ??= (_plans.isNotEmpty
          ? (_plans.first['id'] as num?)?.toInt()
          : null);
      _completedMealIds
        ..clear()
        ..addAll(
          _meals(_activePlan())
              .where((meal) => meal['completed_for'] != null)
              .map((meal) => (meal['id'] as num).toInt()),
        );
    } catch (error) {
      _error = error.toString();
    }
    if (mounted) setState(() => _loading = false);
  }

  Map<String, dynamic> _activePlan() => _plans.firstWhere(
    (plan) => (plan['id'] as num?)?.toInt() == _selectedPlanId,
    orElse: () => _plans.firstWhere(
      (plan) => plan['status']?.toString() == 'active',
      orElse: () => _plans.isEmpty ? <String, dynamic>{} : _plans.first,
    ),
  );

  Future<void> _deletePersonalPlan(Map<String, dynamic> plan) async {
    final id = (plan['id'] as num?)?.toInt();
    if (id == null) return;
    await widget.repository.deleteDietPlan(id);
    _selectedPlanId = null;
    await _load();
  }

  Future<void> _editPersonalPlan(Map<String, dynamic> plan) async {
    final planId = (plan['id'] as num?)?.toInt();
    if (planId == null || plan['is_member_owned'] != true) return;
    final formKey = GlobalKey<FormState>();
    var editedDetails = Map<String, dynamic>.from(plan);
    var editedMeals = _meals(plan);
    final changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          left: AppSpacing.lg,
          right: AppSpacing.lg,
          bottom: MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg,
        ),
        child: PremiumCard(
          child: SingleChildScrollView(
            child: Form(
              key: formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Edit personal plan',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  DietPlanDetailsEditor(
                    initialPlan: editedDetails,
                    onChanged: (value) => editedDetails = value,
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  DietPlanMealsEditor(
                    initialMeals: editedMeals,
                    onChanged: (value) => editedMeals = value,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  GradientButton(
                    label: 'Save changes',
                    expanded: true,
                    onPressed: () async {
                      if (!formKey.currentState!.validate()) return;
                      try {
                        await widget.repository.updateDietPlan(planId, {
                          ...editedDetails,
                          'meals': editedMeals,
                        });
                        if (context.mounted) {
                          Navigator.of(context).pop(true);
                        }
                      } catch (error) {
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text(error.toString())),
                          );
                        }
                      }
                    },
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    if (changed == true) await _load();
  }

  List<Map<String, dynamic>> _meals(Map<String, dynamic> plan) =>
      (plan['meals'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();

  Future<void> _toggleMeal(
    Map<String, dynamic> plan,
    Map<String, dynamic> meal,
  ) async {
    final mealId = (meal['id'] as num?)?.toInt();
    final planId = (plan['id'] as num?)?.toInt();
    if (mealId == null || planId == null) return;
    final completed = !_completedMealIds.contains(mealId);
    setState(
      () => completed
          ? _completedMealIds.add(mealId)
          : _completedMealIds.remove(mealId),
    );
    try {
      await widget.repository.updateDietMealLog(
        planId,
        mealId,
        completed: completed,
      );
    } catch (_) {
      if (mounted) {
        setState(
          () => completed
              ? _completedMealIds.remove(mealId)
              : _completedMealIds.add(mealId),
        );
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Could not update this meal. Try again.'),
          ),
        );
      }
    }
  }

  Future<void> _openCreatePlanSheet() async {
    final formKey = GlobalKey<FormState>();
    final name = TextEditingController();
    var draftDetails = <String, dynamic>{'status': 'active'};
    var draftMeals = _defaultMeals();
    int? templateId;
    var saving = false;
    try {
      final created = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: Colors.transparent,
        builder: (sheetContext) => StatefulBuilder(
          builder: (context, setSheetState) => Padding(
            padding: EdgeInsets.only(
              left: AppSpacing.lg,
              right: AppSpacing.lg,
              bottom: MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg,
            ),
            child: PremiumCard(
              child: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Create personal diet plan',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'This is private to you. Trainer and gym-assigned plans remain unchanged.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      DropdownButtonFormField<int?>(
                        initialValue: templateId,
                        isExpanded: true,
                        items: [
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('Build a custom personal plan'),
                          ),
                          ..._templates.map(
                            (template) => DropdownMenuItem<int?>(
                              value: (template['id'] as num?)?.toInt(),
                              child: Text(
                                template['name']?.toString() ??
                                    'Global template',
                              ),
                            ),
                          ),
                        ],
                        onChanged: (value) =>
                            setSheetState(() => templateId = value),
                        decoration: const InputDecoration(
                          labelText: 'Start from global template',
                        ),
                      ),
                      if (templateId != null) ...[
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          'All meals and food products will be copied into your editable personal plan.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                      const SizedBox(height: AppSpacing.md),
                      if (templateId == null) ...[
                        DietPlanDetailsEditor(
                          initialPlan: draftDetails,
                          onChanged: (value) => draftDetails = value,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        DietPlanMealsEditor(
                          initialMeals: draftMeals,
                          onChanged: (value) => draftMeals = value,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                      ] else ...[
                        TextFormField(
                          controller: name,
                          decoration: const InputDecoration(
                            labelText: 'Custom plan name (optional)',
                          ),
                        ),
                        const SizedBox(height: AppSpacing.lg),
                      ],
                      GradientButton(
                        label: saving ? 'Creating...' : 'Create diet plan',
                        expanded: true,
                        icon: Icons.add_rounded,
                        onPressed: saving
                            ? null
                            : () async {
                                if (!formKey.currentState!.validate()) return;
                                setSheetState(() => saving = true);
                                try {
                                  if (templateId != null) {
                                    await widget.repository.adoptDietTemplate(
                                      templateId!,
                                      name: name.text,
                                    );
                                  } else {
                                    await widget.repository.createDietPlan({
                                      ...draftDetails,
                                      'meals': draftMeals,
                                    });
                                  }
                                  if (context.mounted) {
                                    Navigator.of(context).pop(true);
                                  }
                                } catch (error) {
                                  if (context.mounted) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(content: Text(error.toString())),
                                    );
                                  }
                                } finally {
                                  if (context.mounted) {
                                    setSheetState(() => saving = false);
                                  }
                                }
                              },
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      if (created == true) await _load();
    } finally {
      name.dispose();
    }
  }

  List<Map<String, dynamic>> _defaultMeals() => const [
    {'name': 'Breakfast', 'meal_type': 'breakfast', 'items': []},
    {'name': 'Lunch', 'meal_type': 'lunch', 'items': []},
    {'name': 'Dinner', 'meal_type': 'dinner', 'items': []},
  ].map((meal) => Map<String, dynamic>.from(meal)).toList();

  @override
  Widget build(BuildContext context) {
    final plan = _activePlan();
    final meals = _meals(plan);
    return AppGradientScaffold(
      title: 'Diet Plan',
      actions: [
        IconButton(
          tooltip: 'Create personal diet plan',
          onPressed: _loading ? null : _openCreatePlanSheet,
          icon: const Icon(Icons.add_rounded),
        ),
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
      body: _loading
          ? const LoadingState(label: 'Loading your diet plan...')
          : _error != null
          ? ErrorStateView(message: _error!, onRetry: _load)
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  if (plan.isEmpty)
                    EmptyStateView(
                      title: 'No diet plan yet',
                      message:
                          'Create your own plan or wait for one from your trainer.',
                      icon: Icons.restaurant_menu_rounded,
                    )
                  else ...[
                    if (_plans.length > 1) ...[
                      DropdownButtonFormField<int>(
                        key: ValueKey((plan['id'] as num?)?.toInt()),
                        initialValue: (plan['id'] as num?)?.toInt(),
                        isExpanded: true,
                        decoration: const InputDecoration(
                          labelText: 'Viewing plan',
                        ),
                        items: _plans
                            .map(
                              (item) => DropdownMenuItem(
                                value: (item['id'] as num?)?.toInt(),
                                child: Text(
                                  item['name']?.toString() ?? 'Diet plan',
                                ),
                              ),
                            )
                            .toList(),
                        onChanged: (value) =>
                            setState(() => _selectedPlanId = value),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                    ],
                    _Hero(plan: plan),
                    if (plan['is_member_owned'] == true)
                      Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          TextButton.icon(
                            onPressed: () => _editPersonalPlan(plan),
                            icon: const Icon(Icons.edit_outlined),
                            label: const Text('Edit foods'),
                          ),
                          TextButton.icon(
                            onPressed: () => _deletePersonalPlan(plan),
                            icon: const Icon(
                              Icons.delete_outline_rounded,
                              color: AppColors.error,
                            ),
                            label: const Text('Delete'),
                          ),
                        ],
                      ),
                    const SizedBox(height: AppSpacing.lg),
                    if (_hasGuidance(plan)) ...[
                      _Guidance(plan: plan),
                      const SizedBox(height: AppSpacing.lg),
                    ],
                    Row(
                      children: [
                        _Macro(
                          label: 'Protein',
                          value: '${plan['protein_target_g'] ?? 0}g',
                          color: AppColors.primary,
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        _Macro(
                          label: 'Carbs',
                          value: '${plan['carbs_target_g'] ?? 0}g',
                          color: AppColors.info,
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        _Macro(
                          label: 'Fats',
                          value: '${plan['fats_target_g'] ?? 0}g',
                          color: AppColors.accent,
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Today\'s meals',
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                        ),
                        Text(
                          '${_completedMealIds.length}/${meals.length} done',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: AppColors.success,
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    ...meals.map((meal) {
                      final done = _completedMealIds.contains(
                        (meal['id'] as num?)?.toInt(),
                      );
                      final items =
                          (meal['items'] as List<dynamic>? ?? const [])
                              .map(
                                (item) =>
                                    Map<String, dynamic>.from(item as Map),
                              )
                              .toList();
                      return Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: PremiumCard(
                          padding: const EdgeInsets.all(AppSpacing.md),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Icon(
                                    done
                                        ? Icons.check_circle_rounded
                                        : Icons.restaurant_rounded,
                                    color: done
                                        ? AppColors.success
                                        : AppColors.primary,
                                  ),
                                  const SizedBox(width: AppSpacing.sm),
                                  Expanded(
                                    child: Text(
                                      meal['name']?.toString() ?? 'Meal',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.titleMedium,
                                    ),
                                  ),
                                  Checkbox(
                                    value: done,
                                    onChanged: (_) => _toggleMeal(plan, meal),
                                  ),
                                ],
                              ),
                              if (meal['scheduled_time'] != null)
                                Text(
                                  meal['scheduled_time'].toString(),
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ...items.map(
                                (item) => Padding(
                                  padding: const EdgeInsets.only(top: 6),
                                  child: Text(
                                    '• ${item['name']}${item['quantity'] != null ? ' — ${item['quantity']}' : ''}',
                                  ),
                                ),
                              ),
                              if (meal['notes']?.toString().isNotEmpty ?? false)
                                Padding(
                                  padding: const EdgeInsets.only(top: 8),
                                  child: Text(meal['notes'].toString()),
                                ),
                            ],
                          ),
                        ),
                      );
                    }),
                  ],
                ],
              ),
            ),
    );
  }
}

bool _hasGuidance(Map<String, dynamic> plan) => [
  'dietary_preferences',
  'allergies_and_restrictions',
  'notes',
].any((key) => plan[key]?.toString().trim().isNotEmpty ?? false);

class _Guidance extends StatelessWidget {
  const _Guidance({required this.plan});

  final Map<String, dynamic> plan;

  @override
  Widget build(BuildContext context) {
    final entries = <MapEntry<String, String>>[
      MapEntry('Preferences', plan['dietary_preferences']?.toString() ?? ''),
      MapEntry(
        'Restrictions',
        plan['allergies_and_restrictions']?.toString() ?? '',
      ),
      MapEntry('Coach notes', plan['notes']?.toString() ?? ''),
    ].where((entry) => entry.value.trim().isNotEmpty).toList();
    return PremiumCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Plan guidance', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: AppSpacing.sm),
          ...entries.map(
            (entry) => Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.sm),
              child: RichText(
                text: TextSpan(
                  style: Theme.of(context).textTheme.bodyMedium,
                  children: [
                    TextSpan(
                      text: '${entry.key}: ',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    TextSpan(text: entry.value),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.plan});
  final Map<String, dynamic> plan;
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: AppColors.primary,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            plan['name']?.toString() ?? 'Your nutrition plan',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(color: Colors.white),
          ),
          const SizedBox(height: 6),
          Text(
            plan['goal']?.toString() ?? 'Daily nutrition targets',
            style: const TextStyle(color: Colors.white70),
          ),
          const SizedBox(height: 18),
          Text(
            '${plan['daily_calorie_target'] ?? '--'} kcal / day',
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    ),
  );
}

class _Macro extends StatelessWidget {
  const _Macro({required this.label, required this.value, required this.color});
  final String label, value;
  final Color color;
  @override
  Widget build(BuildContext context) => Expanded(
    child: PremiumCard(
      padding: const EdgeInsets.all(AppSpacing.sm),
      child: Padding(
        padding: EdgeInsets.zero,
        child: Column(
          children: [
            Text(
              value,
              style: TextStyle(fontWeight: FontWeight.bold, color: color),
            ),
            Text(label, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    ),
  );
}
