import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:gym_flutter_core/diet_plan_meals_editor.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/confirmation_dialog.dart';
import '../../../core/widgets/loading_state.dart';
import 'trainer_repository.dart';

class TrainerDietPlanScreen extends StatefulWidget {
  const TrainerDietPlanScreen({
    super.key,
    required this.repository,
    this.embedded = false,
    this.plannerNavigation,
  });

  final TrainerRepository repository;
  final bool embedded;
  final Widget? plannerNavigation;

  @override
  State<TrainerDietPlanScreen> createState() => _TrainerDietPlanScreenState();
}

class _TrainerDietPlanScreenState extends State<TrainerDietPlanScreen> {
  final _formKey = GlobalKey<FormState>();

  bool _loading = true;
  bool _saving = false;
  int _selectedTab = 0;
  int _editorRevision = 0;
  String? _error;
  List<Map<String, dynamic>> _templates = const [];
  Map<String, dynamic> _draftDetails = {'status': 'active'};
  List<Map<String, dynamic>> _draftMeals = _defaultMeals();

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
      final response = await widget.repository.fetchDietTemplates();
      _templates = (response['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (error) {
      _error = _dietErrorMessage(error);
    }
    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
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
    try {
      await widget.repository.createDietTemplate({
        ..._draftDetails,
        'name': name,
        'status': 'active',
        'meals': _draftMeals,
      });
      if (!mounted) {
        return;
      }
      setState(() {
        _draftDetails = {'status': 'active'};
        _draftMeals = _defaultMeals();
        _editorRevision++;
        _selectedTab = 1;
      });
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Diet plan saved to your library.')),
        );
      }
    } catch (error) {
      if (mounted) {
        setState(() => _error = _dietErrorMessage(error));
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _edit(Map<String, dynamic> template) async {
    if (template['is_owned'] != true) {
      return;
    }
    final id = (template['id'] as num?)?.toInt();
    if (id == null) {
      return;
    }
    var details = Map<String, dynamic>.from(template);
    var meals = _mapList(template['meals']);
    var saving = false;
    final formKey = GlobalKey<FormState>();
    final changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Container(
          margin: const EdgeInsets.all(12),
          padding: EdgeInsets.fromLTRB(
            AppSpacing.lg,
            12,
            AppSpacing.lg,
            MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg,
          ),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(28),
            border: Border.all(color: AppColors.stroke),
          ),
          child: SingleChildScrollView(
            child: Form(
              key: formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 42,
                      height: 4,
                      decoration: BoxDecoration(
                        color: AppColors.strokeStrong,
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Edit diet plan',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.of(context).pop(),
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.md),
                  DietPlanDetailsEditor(
                    initialPlan: details,
                    includeStatus: true,
                    onChanged: (value) => details = value,
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  DietPlanMealsEditor(
                    initialMeals: meals,
                    onChanged: (value) => meals = value,
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  GradientButton(
                    label: saving ? 'Saving changes...' : 'Save changes',
                    icon: Icons.check_rounded,
                    expanded: true,
                    onPressed: saving
                        ? null
                        : () async {
                            if (!(formKey.currentState?.validate() ?? false)) {
                              return;
                            }
                            setSheetState(() => saving = true);
                            try {
                              await widget.repository.updateDietTemplate(id, {
                                ...details,
                                'status': details['status'] ?? 'active',
                                'meals': meals,
                              });
                              if (context.mounted) {
                                Navigator.of(context).pop(true);
                              }
                            } catch (error) {
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text(_dietErrorMessage(error)),
                                  ),
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
    if (changed == true) {
      await _load();
    }
  }

  Future<void> _delete(Map<String, dynamic> template) async {
    if (template['is_owned'] != true) {
      return;
    }
    final id = (template['id'] as num?)?.toInt();
    if (id == null) {
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => ConfirmationDialog(
        title: 'Delete diet plan?',
        message:
            'This removes "${template['name'] ?? 'this plan'}" from your library.',
        confirmLabel: 'Delete',
      ),
    );
    if (confirmed != true) {
      return;
    }
    try {
      await widget.repository.deleteDietTemplate(id);
      await _load();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(_dietErrorMessage(error))));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final content = Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
          child: Column(
            children: [
              _DietBuilderHeader(
                templateCount: _templates.length,
                loading: _loading,
                showBack: !widget.embedded,
                onBack: () => Navigator.of(context).maybePop(),
                onRefresh: _load,
              ),
              if (widget.plannerNavigation != null) ...[
                const SizedBox(height: 16),
                widget.plannerNavigation!,
              ],
              const SizedBox(height: 16),
              _DietBuilderTabs(
                selectedIndex: _selectedTab,
                onChanged: (index) => setState(() => _selectedTab = index),
              ),
              if (_error != null) ...[
                const SizedBox(height: 10),
                _DietErrorBanner(message: _error!),
              ],
            ],
          ),
        ),
        Expanded(
          child: _loading && _templates.isEmpty
              ? const LoadingState(label: 'Loading diet studio...')
              : _selectedTab == 0
              ? _buildPlanEditor()
              : _buildLibrary(),
        ),
      ],
    );

    if (widget.embedded) {
      return content;
    }
    return AppGradientScaffold(title: 'Diet Studio', body: content);
  }

  Widget _buildPlanEditor() {
    return Form(
      key: _formKey,
      child: ListView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(18, 2, 18, 32),
        children: [
          _DietBuilderSection(
            title: 'Plan details',
            subtitle: 'Set the goal, nutrition targets, and preferences.',
            icon: Icons.tune_rounded,
            child: DietPlanDetailsEditor(
              key: ValueKey('details-$_editorRevision'),
              initialPlan: _draftDetails,
              onChanged: (value) => _draftDetails = value,
            ),
          ),
          const SizedBox(height: 14),
          _DietBuilderSection(
            title: 'Meals',
            subtitle: 'Build meal timings, foods, portions, and macros.',
            icon: Icons.restaurant_menu_rounded,
            child: DietPlanMealsEditor(
              key: ValueKey('meals-$_editorRevision'),
              initialMeals: _draftMeals,
              onChanged: (value) => _draftMeals = value,
            ),
          ),
          const SizedBox(height: 16),
          GradientButton(
            label: _saving ? 'Saving plan...' : 'Save to diet library',
            icon: Icons.library_add_check_rounded,
            expanded: true,
            onPressed: _saving ? null : _save,
          ),
        ],
      ),
    );
  }

  Widget _buildLibrary() {
    if (_error != null && _templates.isEmpty) {
      return ErrorStateView(message: _error!, onRetry: _load);
    }
    if (_templates.isEmpty) {
      return const EmptyStateView(
        title: 'Your diet library is empty',
        message: 'Create a reusable diet plan to see it here.',
        icon: Icons.restaurant_menu_rounded,
      );
    }
    return ListView.separated(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(18, 2, 18, 32),
      itemCount: _templates.length,
      separatorBuilder: (_, __) => const SizedBox(height: 9),
      itemBuilder: (context, index) {
        final template = _templates[index];
        return _DietLibraryCard(
          template: template,
          onOpen: () => _openPreview(template),
          onEdit: template['is_owned'] == true ? () => _edit(template) : null,
          onDelete: template['is_owned'] == true
              ? () => _delete(template)
              : null,
        );
      },
    );
  }

  Future<void> _openPreview(Map<String, dynamic> template) {
    final meals = _mapList(template['meals']);
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (context) => DraggableScrollableSheet(
        initialChildSize: 0.78,
        minChildSize: 0.5,
        maxChildSize: 0.94,
        expand: false,
        builder: (context, controller) => Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
          decoration: const BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
          ),
          child: ListView(
            controller: controller,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.strokeStrong,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text(
                template['name']?.toString() ?? 'Diet plan',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                template['goal']?.toString() ?? 'Flexible nutrition plan',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _DietMetric(
                    icon: Icons.local_fire_department_outlined,
                    label: '${template['daily_calorie_target'] ?? '--'} kcal',
                  ),
                  _DietMetric(
                    icon: Icons.restaurant_menu_rounded,
                    label: '${meals.length} meals',
                  ),
                  _DietMetric(
                    icon: Icons.fitness_center_outlined,
                    label: 'P ${template['protein_target_g'] ?? '--'}g',
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Text(
                'Meals',
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 10),
              ...meals.map(
                (meal) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceSoft,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: AppColors.stroke),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.restaurant_outlined,
                          color: AppColors.primary,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                meal['name']?.toString() ?? 'Meal',
                                style: const TextStyle(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                '${_mapList(meal['items']).length} food items',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DietBuilderHeader extends StatelessWidget {
  const _DietBuilderHeader({
    required this.templateCount,
    required this.loading,
    required this.showBack,
    required this.onBack,
    required this.onRefresh,
  });

  final int templateCount;
  final bool loading;
  final bool showBack;
  final VoidCallback onBack;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        if (showBack) ...[
          _DietSquareButton(icon: Icons.arrow_back_rounded, onTap: onBack),
          const SizedBox(width: 12),
        ],
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Diet studio',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                '$templateCount reusable plans',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        _DietSquareButton(
          icon: loading ? Icons.sync_rounded : Icons.refresh_rounded,
          onTap: loading ? () {} : () => onRefresh(),
        ),
      ],
    );
  }
}

class _DietSquareButton extends StatelessWidget {
  const _DietSquareButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: AppColors.stroke),
          ),
          child: Icon(icon, color: AppColors.textSecondary, size: 21),
        ),
      ),
    );
  }
}

class _DietBuilderTabs extends StatelessWidget {
  const _DietBuilderTabs({
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
            child: _DietTabButton(
              label: 'Plans',
              icon: Icons.restaurant_menu_rounded,
              selected: selectedIndex == 0,
              onTap: () => onChanged(0),
            ),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: _DietTabButton(
              label: 'Library',
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

class _DietTabButton extends StatelessWidget {
  const _DietTabButton({
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

class _DietBuilderSection extends StatelessWidget {
  const _DietBuilderSection({
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
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.stroke),
      ),
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

class _DietLibraryCard extends StatelessWidget {
  const _DietLibraryCard({
    required this.template,
    required this.onOpen,
    this.onEdit,
    this.onDelete,
  });

  final Map<String, dynamic> template;
  final VoidCallback onOpen;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final meals = _mapList(template['meals']);
    final owned = template['is_owned'] == true;
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: AppColors.stroke),
          ),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(
                  owned ? Icons.restaurant_menu_rounded : Icons.public_rounded,
                  color: AppColors.primary,
                  size: 21,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      template['name']?.toString() ?? 'Diet plan',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${template['daily_calorie_target'] ?? '--'} kcal • ${meals.length} meals • ${owned ? 'Your plan' : 'Atlas library'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              if (owned)
                PopupMenuButton<String>(
                  tooltip: 'Plan options',
                  onSelected: (value) {
                    if (value == 'edit') {
                      onEdit?.call();
                    } else if (value == 'delete') {
                      onDelete?.call();
                    }
                  },
                  itemBuilder: (_) => const [
                    PopupMenuItem(value: 'edit', child: Text('Edit plan')),
                    PopupMenuItem(value: 'delete', child: Text('Delete plan')),
                  ],
                  icon: const Icon(
                    Icons.more_horiz_rounded,
                    color: AppColors.textMuted,
                  ),
                )
              else
                const Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.textMuted,
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DietErrorBanner extends StatelessWidget {
  const _DietErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.error.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.14)),
      ),
      child: Text(
        message,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: AppColors.error,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _DietMetric extends StatelessWidget {
  const _DietMetric({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(99),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: AppColors.primary),
          const SizedBox(width: 5),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

List<Map<String, dynamic>> _defaultMeals() => [
  <String, dynamic>{'name': 'Meal 1', 'meal_type': 'meal_1', 'items': []},
];

List<Map<String, dynamic>> _mapList(dynamic value) {
  if (value is! List) {
    return const [];
  }
  return value
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList();
}

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
