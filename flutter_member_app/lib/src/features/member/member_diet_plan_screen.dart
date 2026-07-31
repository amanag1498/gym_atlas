import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:gym_flutter_core/diet_plan_meals_editor.dart';
import 'package:gym_flutter_core/diet_plan_summary_view.dart';

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
      _error = _dietErrorMessage(error);
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
                            SnackBar(content: Text(_dietErrorMessage(error))),
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

  Future<void> _openCreateStudio() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => _MemberDietCreationStudio(
          repository: widget.repository,
          templates: _templates,
        ),
      ),
    );
    if (created == true) {
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final plan = _activePlan();
    final meals = _meals(plan);
    return AppGradientScaffold(
      title: 'Diet Plan',
      actions: [
        IconButton(
          tooltip: 'Create personal diet plan',
          onPressed: _loading ? null : _openCreateStudio,
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
                  Align(
                    alignment: Alignment.centerRight,
                    child: OutlinedButton.icon(
                      onPressed: _openCreateStudio,
                      icon: const Icon(Icons.add_rounded),
                      label: const Text('Create meal plan'),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  if (plan.isEmpty)
                    EmptyStateView(
                      title: 'No diet plan yet',
                      message:
                          'Build meals around your own schedule, or wait for a trainer-assigned plan.',
                      icon: Icons.restaurant_menu_rounded,
                      action: GradientButton(
                        label: 'Create your first meal plan',
                        icon: Icons.add_rounded,
                        expanded: true,
                        onPressed: _openCreateStudio,
                      ),
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
                    const SizedBox(height: AppSpacing.sm),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: () =>
                            showDietPlanSummarySheet(context, plan: plan),
                        icon: const Icon(Icons.visibility_outlined),
                        label: const Text('See full plan'),
                      ),
                    ),
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

class _MemberDietCreationStudio extends StatefulWidget {
  const _MemberDietCreationStudio({
    required this.repository,
    required this.templates,
  });

  final MemberRepository repository;
  final List<Map<String, dynamic>> templates;

  @override
  State<_MemberDietCreationStudio> createState() =>
      _MemberDietCreationStudioState();
}

class _MemberDietCreationStudioState extends State<_MemberDietCreationStudio> {
  final _formKey = GlobalKey<FormState>();
  int _selectedTab = 0;
  int _editorRevision = 0;
  bool _saving = false;
  String? _error;
  Map<String, dynamic> _draftDetails = <String, dynamic>{'status': 'active'};
  List<Map<String, dynamic>> _draftMeals = _studioDefaultMeals();

  Future<void> _saveCustomPlan() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    final name = _draftDetails['name']?.toString().trim() ?? '';
    if (name.isEmpty) {
      setState(() => _error = 'Add a name for this diet plan.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    var succeeded = false;
    try {
      await widget.repository.createDietPlan({
        ..._draftDetails,
        'name': name,
        'status': 'active',
        'meals': _draftMeals,
      });
      succeeded = true;
      if (!mounted) {
        return;
      }
      Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) {
        setState(() => _error = _dietErrorMessage(error));
      }
    } finally {
      if (mounted && !succeeded) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _adoptTemplate(Map<String, dynamic> template) async {
    final templateId = (template['id'] as num?)?.toInt();
    if (templateId == null) {
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    var succeeded = false;
    try {
      await widget.repository.adoptDietTemplate(templateId);
      succeeded = true;
      if (!mounted) {
        return;
      }
      Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) {
        setState(() => _error = _dietErrorMessage(error));
      }
    } finally {
      if (mounted && !succeeded) {
        setState(() => _saving = false);
      }
    }
  }

  void _resetBuilder() {
    setState(() {
      _draftDetails = <String, dynamic>{'status': 'active'};
      _draftMeals = _studioDefaultMeals();
      _editorRevision++;
      _error = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Diet Studio',
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.sm,
                AppSpacing.lg,
                AppSpacing.md,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Create your diet plan',
                              style: Theme.of(context).textTheme.headlineSmall
                                  ?.copyWith(
                                    color: AppColors.textPrimary,
                                    fontWeight: FontWeight.w900,
                                  ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              'Build meal by meal or start from an Atlas template.',
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(color: AppColors.textSecondary),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        tooltip: 'Reset builder',
                        onPressed: _saving ? null : _resetBuilder,
                        icon: const Icon(Icons.restart_alt_rounded),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _MemberDietStudioTabs(
                    selectedIndex: _selectedTab,
                    onChanged: (index) => setState(() {
                      _selectedTab = index;
                      _error = null;
                    }),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 10),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.error.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Text(
                        _error!,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.error,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            Expanded(
              child: _selectedTab == 0 ? _buildCustomPlan() : _buildTemplates(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCustomPlan() {
    return Form(
      key: _formKey,
      child: ListView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg,
          2,
          AppSpacing.lg,
          AppSpacing.xl,
        ),
        children: [
          _MemberDietBuilderSection(
            title: 'Plan details',
            subtitle: 'Set your goal, nutrition targets, and preferences.',
            icon: Icons.tune_rounded,
            child: DietPlanDetailsEditor(
              key: ValueKey('member-diet-details-$_editorRevision'),
              initialPlan: _draftDetails,
              onChanged: (value) => _draftDetails = value,
            ),
          ),
          const SizedBox(height: 14),
          _MemberDietBuilderSection(
            title: 'Meals',
            subtitle: 'Add any meals, foods, portions, timings, and macros.',
            icon: Icons.restaurant_menu_rounded,
            child: DietPlanMealsEditor(
              key: ValueKey('member-diet-meals-$_editorRevision'),
              initialMeals: _draftMeals,
              onChanged: (value) => _draftMeals = value,
            ),
          ),
          const SizedBox(height: 16),
          GradientButton(
            label: _saving ? 'Creating diet plan...' : 'Save to my diet plans',
            icon: Icons.library_add_check_rounded,
            expanded: true,
            onPressed: _saving ? null : _saveCustomPlan,
          ),
        ],
      ),
    );
  }

  Widget _buildTemplates() {
    if (widget.templates.isEmpty) {
      return const EmptyStateView(
        title: 'No templates available',
        message: 'Build a custom meal plan while Atlas templates are prepared.',
        icon: Icons.restaurant_menu_rounded,
      );
    }
    return ListView.separated(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        2,
        AppSpacing.lg,
        AppSpacing.xl,
      ),
      itemCount: widget.templates.length,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final template = widget.templates[index];
        final meals = (template['meals'] as List<dynamic>? ?? const []);
        return PremiumCard(
          padding: const EdgeInsets.all(14),
          child: Column(
            children: [
              InkWell(
                onTap: () => showDietPlanSummarySheet(context, plan: template),
                borderRadius: BorderRadius.circular(16),
                child: Row(
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: AppColors.surfaceSoft,
                        borderRadius: BorderRadius.circular(15),
                      ),
                      child: const Icon(
                        Icons.public_rounded,
                        color: AppColors.primary,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            template['name']?.toString() ?? 'Diet template',
                            style: Theme.of(context).textTheme.titleSmall
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${template['daily_calorie_target'] ?? '--'} kcal • ${meals.length} meals',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(color: AppColors.textSecondary),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _saving ? null : () => _adoptTemplate(template),
                  icon: const Icon(Icons.add_task_rounded),
                  label: const Text('Add to my diet plans'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _MemberDietStudioTabs extends StatelessWidget {
  const _MemberDietStudioTabs({
    required this.selectedIndex,
    required this.onChanged,
  });

  final int selectedIndex;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        children: [
          Expanded(
            child: _MemberDietStudioTab(
              label: 'Build plan',
              icon: Icons.restaurant_menu_rounded,
              selected: selectedIndex == 0,
              onTap: () => onChanged(0),
            ),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: _MemberDietStudioTab(
              label: 'Templates',
              icon: Icons.library_books_rounded,
              selected: selectedIndex == 1,
              onTap: () => onChanged(1),
            ),
          ),
        ],
      ),
    );
  }
}

class _MemberDietStudioTab extends StatelessWidget {
  const _MemberDietStudioTab({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? AppColors.surface : Colors.transparent,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 11),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 18,
                color: selected ? AppColors.primary : AppColors.textMuted,
              ),
              const SizedBox(width: 7),
              Text(
                label,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: selected
                      ? AppColors.textPrimary
                      : AppColors.textSecondary,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MemberDietBuilderSection extends StatelessWidget {
  const _MemberDietBuilderSection({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.child,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: Icon(icon, color: AppColors.primary, size: 20),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }
}

List<Map<String, dynamic>> _studioDefaultMeals() => [
  <String, dynamic>{'name': 'Meal 1', 'meal_type': 'meal_1', 'items': []},
];

String _dietErrorMessage(Object error) {
  if (error is DioException) {
    final body = error.response?.data;
    if (body is Map) {
      final errors = body['errors'];
      if (errors is Map) {
        final messages = errors.values
            .expand((value) => value is List ? value : <dynamic>[value])
            .map((value) => value.toString())
            .where((value) => value.trim().isNotEmpty)
            .toSet()
            .toList();
        if (messages.isNotEmpty) return messages.join('\n');
      }
      final message = body['message']?.toString();
      if (message != null && message.trim().isNotEmpty) return message;
    }
  }
  return 'Could not save the diet plan. Please review the meals and try again.';
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
