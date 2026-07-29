import 'package:flutter/material.dart';
import 'package:gym_flutter_core/diet_plan_meals_editor.dart';

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
    this.initialMemberId,
  });

  final TrainerRepository repository;
  final List<Map<String, dynamic>> members;
  final int? initialMemberId;

  @override
  State<TrainerDietPlanScreen> createState() => _TrainerDietPlanScreenState();
}

class _TrainerDietPlanScreenState extends State<TrainerDietPlanScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _startsOnController = TextEditingController();
  final _endsOnController = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  int _mealEditorRevision = 0;
  String? _error;
  int? _memberId;
  int? _templateId;
  List<Map<String, dynamic>> _plans = const [];
  List<Map<String, dynamic>> _templates = const [];
  Map<String, dynamic> _draftDetails = {'status': 'active'};
  List<Map<String, dynamic>> _draftMeals = _defaultMeals();

  @override
  void initState() {
    super.initState();
    final hasInitialMember =
        widget.initialMemberId != null &&
        widget.members.any(
          (item) =>
              (item['member_id'] as num?)?.toInt() == widget.initialMemberId,
        );
    if (hasInitialMember) {
      _memberId = widget.initialMemberId;
    } else if (widget.members.isNotEmpty) {
      _memberId = (widget.members.first['member_id'] as num?)?.toInt();
    }
    _load();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _startsOnController.dispose();
    _endsOnController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final plansResponse = await widget.repository.fetchDietPlans(
        memberId: widget.initialMemberId,
      );
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
    } catch (error) {
      _error = error.toString();
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _memberId == null) return;

    setState(() => _saving = true);
    try {
      if (_templateId != null) {
        await widget.repository.assignDietTemplate(_templateId!, {
          'member_ids': [_memberId],
          if (_nameController.text.trim().isNotEmpty)
            'name': _nameController.text.trim(),
          if (_startsOnController.text.trim().isNotEmpty)
            'starts_on': _startsOnController.text.trim(),
          if (_endsOnController.text.trim().isNotEmpty)
            'ends_on': _endsOnController.text.trim(),
        });
      } else {
        await widget.repository.createDietPlan({
          'member_ids': [_memberId],
          ..._draftDetails,
          'meals': _draftMeals,
        });
      }
      _nameController.clear();
      _startsOnController.clear();
      _endsOnController.clear();
      setState(() {
        _templateId = null;
        _draftDetails = {'status': 'active'};
        _draftMeals = _defaultMeals();
        _mealEditorRevision++;
      });
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
    final formKey = GlobalKey<FormState>();
    var editedDetails = Map<String, dynamic>.from(plan);
    var editedMeals = (plan['meals'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    var saving = false;
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
              child: Form(
                key: formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Edit diet plan',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    DietPlanDetailsEditor(
                      initialPlan: editedDetails,
                      includeStatus: true,
                      onChanged: (value) => editedDetails = value,
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    DietPlanMealsEditor(
                      initialMeals: editedMeals,
                      onChanged: (value) => editedMeals = value,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    GradientButton(
                      label: saving ? 'Saving...' : 'Save changes',
                      expanded: true,
                      onPressed: saving
                          ? null
                          : () async {
                              if (!formKey.currentState!.validate()) return;
                              setSheetState(() => saving = true);
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
    if (changed == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Diet Builder',
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
                  widget.initialMemberId == null
                      ? 'Assigned plans'
                      : 'Member diet plans',
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
              'Create and assign diet plan',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 6),
            Text(
              'Build nutrition targets, meal timings and food portions, or start from a global template.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: AppSpacing.lg),
            if (widget.members.isEmpty) ...[
              const EmptyStateView(
                title: 'No assigned members',
                message:
                    'A gym owner must assign a member to you before you can create a diet plan.',
                icon: Icons.group_off_outlined,
              ),
              const SizedBox(height: AppSpacing.md),
            ],
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
            if (_templateId == null) ...[
              DietPlanDetailsEditor(
                key: ValueKey('details-$_mealEditorRevision'),
                initialPlan: _draftDetails,
                onChanged: (value) => _draftDetails = value,
              ),
              const SizedBox(height: AppSpacing.lg),
              DietPlanMealsEditor(
                key: ValueKey('meals-$_mealEditorRevision'),
                initialMeals: _draftMeals,
                onChanged: (value) => _draftMeals = value,
              ),
              const SizedBox(height: AppSpacing.sm),
            ] else ...[
              TextFormField(
                controller: _nameController,
                decoration: const InputDecoration(
                  labelText: 'Custom plan name (optional)',
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _startsOnController,
                      keyboardType: TextInputType.datetime,
                      decoration: const InputDecoration(
                        labelText: 'Starts on',
                        hintText: 'YYYY-MM-DD',
                      ),
                      validator: _dateValidator,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: TextFormField(
                      controller: _endsOnController,
                      keyboardType: TextInputType.datetime,
                      decoration: const InputDecoration(
                        labelText: 'Ends on',
                        hintText: 'YYYY-MM-DD',
                      ),
                      validator: _dateValidator,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
            ],
            GradientButton(
              label: _saving ? 'Assigning...' : 'Assign diet plan',
              icon: Icons.restaurant_menu_rounded,
              expanded: true,
              onPressed: _saving || widget.members.isEmpty ? null : _save,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPlanCard(Map<String, dynamic> plan) {
    final member = Map<String, dynamic>.from(
      plan['member'] as Map? ?? const {},
    );
    final mealCount = (plan['meals'] as List<dynamic>? ?? const []).length;
    final status = plan['status']?.toString() ?? 'active';
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: PremiumCard(
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: () => _view(plan),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.10),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(
                      Icons.restaurant_rounded,
                      color: AppColors.primary,
                    ),
                  ),
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
                          member['name']?.toString() ?? 'Assigned member',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: _statusColor(status).withValues(alpha: 0.10),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      _titleCase(status),
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: _statusColor(status),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _PlanMetric(
                    icon: Icons.local_fire_department_outlined,
                    label: '${plan['daily_calorie_target'] ?? '--'} kcal',
                  ),
                  _PlanMetric(
                    icon: Icons.restaurant_menu_rounded,
                    label: '$mealCount meals',
                  ),
                  _PlanMetric(
                    icon: Icons.calendar_month_outlined,
                    label: _scheduleLabel(plan),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Tap to review meals and foods',
                      style: Theme.of(context).textTheme.bodySmall,
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
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _view(Map<String, dynamic> plan) {
    final meals = (plan['meals'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final member = Map<String, dynamic>.from(
      plan['member'] as Map? ?? const {},
    );

    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => DraggableScrollableSheet(
        initialChildSize: 0.86,
        minChildSize: 0.55,
        maxChildSize: 0.96,
        expand: false,
        builder: (context, scrollController) => PremiumCard(
          child: ListView(
            controller: scrollController,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          plan['name']?.toString() ?? 'Diet plan',
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          member['name']?.toString() ?? 'Assigned member',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close',
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _PlanMetric(
                    icon: Icons.local_fire_department_outlined,
                    label: '${plan['daily_calorie_target'] ?? '--'} kcal',
                  ),
                  _PlanMetric(
                    icon: Icons.fitness_center_outlined,
                    label: _macroLabel('P', plan['protein_target_g']),
                  ),
                  _PlanMetric(
                    icon: Icons.grain_rounded,
                    label: _macroLabel('C', plan['carbs_target_g']),
                  ),
                  _PlanMetric(
                    icon: Icons.water_drop_outlined,
                    label: _macroLabel('F', plan['fats_target_g']),
                  ),
                ],
              ),
              if (_hasText(plan['goal'])) ...[
                const SizedBox(height: AppSpacing.md),
                _PlanNote(label: 'Goal', value: plan['goal'].toString()),
              ],
              if (_hasText(plan['dietary_preferences'])) ...[
                const SizedBox(height: AppSpacing.sm),
                _PlanNote(
                  label: 'Dietary preferences',
                  value: plan['dietary_preferences'].toString(),
                ),
              ],
              if (_hasText(plan['allergies_and_restrictions'])) ...[
                const SizedBox(height: AppSpacing.sm),
                _PlanNote(
                  label: 'Allergies and restrictions',
                  value: plan['allergies_and_restrictions'].toString(),
                  warning: true,
                ),
              ],
              const SizedBox(height: AppSpacing.lg),
              Text(
                'Meals and food portions',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: AppSpacing.sm),
              if (meals.isEmpty)
                const Text('No meals have been added to this plan.')
              else
                ...meals.map(_buildMealSummary),
              const SizedBox(height: AppSpacing.lg),
              GradientButton(
                label: 'Edit plan',
                icon: Icons.edit_outlined,
                expanded: true,
                onPressed: () {
                  Navigator.of(context).pop();
                  _edit(plan);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMealSummary(Map<String, dynamic> meal) {
    final items = (meal['items'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    return Card(
      elevation: 0,
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: ExpansionTile(
        title: Text(meal['name']?.toString() ?? 'Meal'),
        subtitle: Text(
          '${_mealTime(meal)} · ${items.length} food${items.length == 1 ? '' : 's'}',
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
        children: [
          if (items.isEmpty)
            const Align(
              alignment: Alignment.centerLeft,
              child: Text('No food portions added.'),
            )
          else
            ...items.map(
              (item) => ListTile(
                contentPadding: EdgeInsets.zero,
                dense: true,
                leading: const Icon(Icons.circle, size: 8),
                title: Text(item['name']?.toString() ?? 'Food'),
                subtitle: Text(
                  [
                        item['quantity']?.toString(),
                        item['calories'] == null
                            ? null
                            : '${item['calories']} kcal',
                      ]
                      .whereType<String>()
                      .where((value) => value.isNotEmpty)
                      .join(' · '),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

List<Map<String, dynamic>> _defaultMeals() => const [
  {'name': 'Breakfast', 'meal_type': 'breakfast', 'items': []},
  {'name': 'Lunch', 'meal_type': 'lunch', 'items': []},
  {'name': 'Dinner', 'meal_type': 'dinner', 'items': []},
].map((meal) => Map<String, dynamic>.from(meal)).toList();

String? _dateValidator(String? value) {
  final text = value?.trim() ?? '';
  if (text.isEmpty) return null;
  return RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(text)
      ? null
      : 'Use YYYY-MM-DD';
}

bool _hasText(dynamic value) => value?.toString().trim().isNotEmpty == true;

String _macroLabel(String label, dynamic value) => '$label ${value ?? '--'}g';

String _scheduleLabel(Map<String, dynamic> plan) {
  final starts = plan['starts_on']?.toString();
  final ends = plan['ends_on']?.toString();
  if (_hasText(starts) && _hasText(ends)) return '$starts → $ends';
  if (_hasText(starts)) return 'From $starts';
  return 'Ongoing';
}

String _mealTime(Map<String, dynamic> meal) {
  final time = meal['scheduled_time']?.toString();
  if (_hasText(time)) return time!.length >= 5 ? time.substring(0, 5) : time;
  return _titleCase(meal['meal_type']?.toString() ?? 'Flexible');
}

String _titleCase(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .where((part) => part.isNotEmpty)
    .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
    .join(' ');

Color _statusColor(String status) =>
    status.toLowerCase() == 'active' ? AppColors.success : AppColors.textMuted;

class _PlanMetric extends StatelessWidget {
  const _PlanMetric({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: AppColors.primary),
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

class _PlanNote extends StatelessWidget {
  const _PlanNote({
    required this.label,
    required this.value,
    this.warning = false,
  });

  final String label;
  final String value;
  final bool warning;

  @override
  Widget build(BuildContext context) {
    final color = warning ? AppColors.warning : AppColors.primary;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: color,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(value, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}
