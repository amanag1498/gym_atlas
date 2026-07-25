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
    if (widget.members.isNotEmpty) {
      _memberId = (widget.members.first['member_id'] as num?)?.toInt();
    }
    _load();
  }

  @override
  void dispose() {
    _nameController.dispose();
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
          ..._draftDetails,
          'meals': _draftMeals,
        });
      }
      _nameController.clear();
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
            ],
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

List<Map<String, dynamic>> _defaultMeals() => const [
  {'name': 'Breakfast', 'meal_type': 'breakfast', 'items': []},
  {'name': 'Lunch', 'meal_type': 'lunch', 'items': []},
  {'name': 'Dinner', 'meal_type': 'dinner', 'items': []},
].map((meal) => Map<String, dynamic>.from(meal)).toList();
