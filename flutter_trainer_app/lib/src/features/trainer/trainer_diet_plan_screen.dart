import 'package:flutter/material.dart';
import 'package:gym_flutter_core/diet_plan_form_codec.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/confirmation_dialog.dart';
import '../../../core/widgets/loading_state.dart';
import '../../../core/widgets/premium_card.dart';
import 'trainer_repository.dart';

class TrainerDietPlanScreen extends StatefulWidget {
  const TrainerDietPlanScreen({
    super.key,
    required this.repository,
    required this.members,
    required this.contextData,
  });

  final TrainerRepository repository;
  final List<Map<String, dynamic>> members;
  final Map<String, dynamic> contextData;

  @override
  State<TrainerDietPlanScreen> createState() => _TrainerDietPlanScreenState();
}

class _TrainerDietPlanScreenState extends State<TrainerDietPlanScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _goalController = TextEditingController();
  final _calorieController = TextEditingController();
  final _proteinController = TextEditingController();
  final _carbsController = TextEditingController();
  final _fatsController = TextEditingController();
  final _mealControllers = List.generate(3, (_) => TextEditingController());

  bool _loading = true;
  bool _saving = false;
  String? _error;
  int? _memberId;
  int? _templateId;
  List<Map<String, dynamic>> _plans = const [];
  List<Map<String, dynamic>> _templates = const [];

  @override
  void initState() {
    super.initState();
    if (widget.members.isNotEmpty) {
      _memberId = (widget.members.first['member_id'] as num?)?.toInt();
    }
    _mealControllers[0].text = 'Breakfast';
    _mealControllers[1].text = 'Lunch';
    _mealControllers[2].text = 'Dinner';
    _load();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _goalController.dispose();
    _calorieController.dispose();
    _proteinController.dispose();
    _carbsController.dispose();
    _fatsController.dispose();
    for (final controller in _mealControllers) {
      controller.dispose();
    }
    super.dispose();
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
        _templates =
            (templatesResponse['data'] as List<dynamic>? ?? const [])
                .map((item) => Map<String, dynamic>.from(item as Map))
                .toList();
      } catch (_) {
        _templates = const [];
      }
    } catch (error) {
      _error = error.toString();
    }
    if (mounted) setState(() => _loading = false);
  }

  int? _integer(String value) => int.tryParse(value.trim());
  double? _decimal(String value) => double.tryParse(value.trim());
  String? _time(dynamic value) {
    final text = value?.toString();
    if (text == null || text.isEmpty) return null;
    return text.length >= 5 ? text.substring(0, 5) : text;
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _memberId == null) return;
    final profile = Map<String, dynamic>.from(
      widget.contextData['trainer_profile'] as Map? ?? const {},
    );
    final gymId = (profile['gym_id'] as num?)?.toInt();
    if (gymId == null) {
      setState(() => _error = 'Your gym assignment is unavailable.');
      return;
    }

    setState(() => _saving = true);
    try {
      if (_templateId != null) {
        await widget.repository.assignDietTemplate(_templateId!, {
          'member_ids': [_memberId],
          if (_nameController.text.trim().isNotEmpty)
            'name': _nameController.text.trim(),
        });
      } else {
        await widget.repository.createDietPlan({
          'gym_id': gymId,
          'branch_id': (profile['branch_id'] as num?)?.toInt(),
          'member_ids': [_memberId],
          'name': _nameController.text.trim(),
          'goal': _goalController.text.trim(),
          'daily_calorie_target': _integer(_calorieController.text),
          'protein_target_g': _decimal(_proteinController.text),
          'carbs_target_g': _decimal(_carbsController.text),
          'fats_target_g': _decimal(_fatsController.text),
          'meals': _mealControllers
              .asMap()
              .entries
              .where((entry) => entry.value.text.trim().isNotEmpty)
              .map(
                (entry) => {
                  'name': entry.value.text.trim(),
                  'meal_type': ['breakfast', 'lunch', 'dinner'][entry.key],
                  'items': <Map<String, dynamic>>[],
                },
              )
              .toList(),
        });
      }
      _nameController.clear();
      _goalController.clear();
      _calorieController.clear();
      _proteinController.clear();
      _carbsController.clear();
      _fatsController.clear();
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Diet plan assigned.')));
      }
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _delete(Map<String, dynamic> plan) async {
    final id = (plan['id'] as num?)?.toInt();
    if (id == null) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => const ConfirmationDialog(
        title: 'Delete diet plan?',
        message: 'The member will no longer be able to use this plan.',
        confirmLabel: 'Delete',
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.repository.deleteDietPlan(id);
      await _load();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not delete plan: $error')),
        );
      }
    }
  }

  Future<void> _edit(Map<String, dynamic> plan) async {
    final planId = (plan['id'] as num?)?.toInt();
    if (planId == null) return;
    final name = TextEditingController(text: plan['name']?.toString() ?? '');
    final goal = TextEditingController(text: plan['goal']?.toString() ?? '');
    final meals = (plan['meals'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final foodLines = meals
        .map(
          (meal) => TextEditingController(
            text: DietPlanFormCodec.itemsToLines(
              meal['items'] as List<dynamic>?,
            ),
          ),
        )
        .toList();
    var saving = false;
    try {
      final changed = await showModalBottomSheet<bool>(
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
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Edit diet plan',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    TextField(
                      controller: name,
                      decoration: const InputDecoration(labelText: 'Plan name'),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    TextField(
                      controller: goal,
                      decoration: const InputDecoration(labelText: 'Goal'),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    ...meals.asMap().entries.map(
                      (entry) => Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.md),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              entry.value['name']?.toString() ?? 'Meal',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 4),
                            TextField(
                              controller: foodLines[entry.key],
                              minLines: 3,
                              maxLines: 8,
                              decoration: const InputDecoration(
                                labelText: 'Food products',
                                helperText:
                                    'One per line: name | quantity | kcal | protein | carbs | fats | notes',
                                alignLabelWithHint: true,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    GradientButton(
                      label: saving ? 'Saving...' : 'Save changes',
                      expanded: true,
                      onPressed: saving
                          ? null
                          : () async {
                              if (name.text.trim().isEmpty) return;
                              setSheetState(() => saving = true);
                              try {
                                await widget.repository.updateDietPlan(planId, {
                                  'name': name.text.trim(),
                                  'goal': goal.text.trim(),
                                  'daily_calorie_target':
                                      plan['daily_calorie_target'],
                                  'protein_target_g': plan['protein_target_g'],
                                  'carbs_target_g': plan['carbs_target_g'],
                                  'fats_target_g': plan['fats_target_g'],
                                  'dietary_preferences':
                                      plan['dietary_preferences'],
                                  'allergies_and_restrictions':
                                      plan['allergies_and_restrictions'],
                                  'notes': plan['notes'],
                                  'status': plan['status'],
                                  'starts_on': plan['starts_on'],
                                  'ends_on': plan['ends_on'],
                                  'meals': meals.asMap().entries.map((entry) {
                                    final meal = entry.value;
                                    return {
                                      'name': meal['name'],
                                      'meal_type': meal['meal_type'],
                                      'scheduled_time': _time(
                                        meal['scheduled_time'],
                                      ),
                                      'calories': meal['calories'],
                                      'protein_g': meal['protein_g'],
                                      'carbs_g': meal['carbs_g'],
                                      'fats_g': meal['fats_g'],
                                      'notes': meal['notes'],
                                      'items': DietPlanFormCodec.linesToItems(
                                        foodLines[entry.key].text,
                                      ),
                                    };
                                  }).toList(),
                                });
                                if (context.mounted) {
                                  Navigator.of(context).pop(true);
                                }
                              } catch (error) {
                                if (context.mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(content: Text(error.toString())),
                                  );
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
      );
      if (changed == true) await _load();
    } finally {
      name.dispose();
      goal.dispose();
      for (final controller in foodLines) {
        controller.dispose();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Diet Plans',
      actions: [
        IconButton(
          tooltip: 'Refresh',
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
      body: _loading
          ? const LoadingState(label: 'Loading diet plans...')
          : _error != null
          ? ErrorStateView(message: _error!, onRetry: _load)
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                _buildComposer(context),
                const SizedBox(height: AppSpacing.xl),
                Text(
                  'Assigned plans',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: AppSpacing.sm),
                if (_plans.isEmpty)
                  const EmptyStateView(
                    title: 'No diet plans yet',
                    message:
                        'Create a practical nutrition plan for an assigned member.',
                    icon: Icons.restaurant_menu_rounded,
                  )
                else
                  ..._plans.map(_buildPlanCard),
              ],
            ),
    );
  }

  Widget _buildComposer(BuildContext context) {
    return PremiumCard(
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Create diet plan',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 6),
            Text(
              'Assign daily targets and meal slots to one of your members.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: AppSpacing.lg),
            DropdownButtonFormField<int>(
              initialValue: _memberId,
              isExpanded: true,
              items: widget.members.map((assignment) {
                final member = Map<String, dynamic>.from(
                  assignment['member'] as Map? ?? const {},
                );
                return DropdownMenuItem(
                  value: (assignment['member_id'] as num?)?.toInt(),
                  child: Text(member['name']?.toString() ?? 'Member'),
                );
              }).toList(),
              onChanged: (value) => setState(() => _memberId = value),
              decoration: const InputDecoration(labelText: 'Member'),
              validator: (value) => value == null ? 'Choose a member' : null,
            ),
            const SizedBox(height: AppSpacing.md),
            DropdownButtonFormField<int?>(
              initialValue: _templateId,
              isExpanded: true,
              items: [
                const DropdownMenuItem<int?>(
                  value: null,
                  child: Text('Custom plan'),
                ),
                ..._templates.map(
                  (template) => DropdownMenuItem<int?>(
                    value: (template['id'] as num?)?.toInt(),
                    child: Text(
                      template['name']?.toString() ?? 'Global template',
                    ),
                  ),
                ),
              ],
              onChanged: (value) => setState(() => _templateId = value),
              decoration: const InputDecoration(
                labelText: 'Start from global template',
              ),
            ),
            if (_templateId != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                'The complete template, including all meal products and nutrition, will be copied into this member’s gym-scoped plan.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'Plan name',
                hintText: 'Lean muscle nutrition',
              ),
              validator: (value) =>
                  _templateId == null && (value == null || value.trim().isEmpty)
                  ? 'Plan name is required'
                  : null,
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              controller: _goalController,
              decoration: const InputDecoration(labelText: 'Goal'),
            ),
            const SizedBox(height: AppSpacing.md),
            _targetFields(),
            const SizedBox(height: AppSpacing.lg),
            Text('Meal slots', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: AppSpacing.sm),
            ..._mealControllers.map(
              (controller) => Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                child: TextFormField(
                  controller: controller,
                  decoration: const InputDecoration(labelText: 'Meal name'),
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            GradientButton(
              label: _saving ? 'Assigning...' : 'Assign diet plan',
              icon: Icons.restaurant_menu_rounded,
              expanded: true,
              onPressed: _saving ? null : _save,
            ),
          ],
        ),
      ),
    );
  }

  Widget _targetFields() {
    Widget field(TextEditingController controller, String label) => Expanded(
      child: TextFormField(
        controller: controller,
        keyboardType: TextInputType.number,
        decoration: InputDecoration(labelText: label),
      ),
    );
    return Column(
      children: [
        Row(
          children: [
            field(_calorieController, 'Calories'),
            const SizedBox(width: AppSpacing.sm),
            field(_proteinController, 'Protein g'),
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
        Row(
          children: [
            field(_carbsController, 'Carbs g'),
            const SizedBox(width: AppSpacing.sm),
            field(_fatsController, 'Fats g'),
          ],
        ),
      ],
    );
  }

  Widget _buildPlanCard(Map<String, dynamic> plan) {
    final member = Map<String, dynamic>.from(
      plan['member'] as Map? ?? const {},
    );
    final mealCount = (plan['meals'] as List<dynamic>? ?? const []).length;
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: PremiumCard(
        child: Row(
          children: [
            const Icon(Icons.restaurant_rounded, color: AppColors.primary),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    plan['name']?.toString() ?? 'Diet plan',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${member['name'] ?? 'Member'} · ${plan['daily_calorie_target'] ?? '--'} kcal · $mealCount meals',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'Edit plan and foods',
              onPressed: () => _edit(plan),
              icon: const Icon(Icons.edit_outlined),
            ),
            IconButton(
              tooltip: 'Delete plan',
              onPressed: () => _delete(plan),
              icon: const Icon(
                Icons.delete_outline_rounded,
                color: AppColors.error,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
