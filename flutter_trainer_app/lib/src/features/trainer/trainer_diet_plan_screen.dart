import 'package:flutter/material.dart';

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
  List<Map<String, dynamic>> _plans = const [];

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
      final response = await widget.repository.fetchDietPlans();
      _plans = (response['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (error) {
      _error = error.toString();
    }
    if (mounted) setState(() => _loading = false);
  }

  int? _integer(String value) => int.tryParse(value.trim());
  double? _decimal(String value) => double.tryParse(value.trim());

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
            TextFormField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'Plan name',
                hintText: 'Lean muscle nutrition',
              ),
              validator: (value) => value == null || value.trim().isEmpty
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
