import 'package:flutter/material.dart';
import 'package:gym_flutter_core/diet_plan_meals_editor.dart';

import '../../core/models/session_models.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/widgets/common_widgets.dart';
import 'admin_repository.dart';

class DietPlansWorkspace extends StatefulWidget {
  const DietPlansWorkspace({
    super.key,
    required this.appUser,
    required this.repository,
  });

  final AppUser appUser;
  final AdminRepository repository;

  @override
  State<DietPlansWorkspace> createState() => _DietPlansWorkspaceState();
}

class _DietPlansWorkspaceState extends State<DietPlansWorkspace> {
  bool _loading = true;
  String? _error;
  int? _gymId;
  int? _branchId;
  List<Map<String, dynamic>> _records = const [];
  List<Map<String, dynamic>> _templates = const [];
  List<Map<String, dynamic>> _members = const [];
  int _page = 1;
  int _lastPage = 1;

  bool get _hasMore => _page < _lastPage;

  bool get _isPlatform => widget.appUser.activeRole == 'platform_admin';

  bool get _canManage =>
      _isPlatform ||
      widget.appUser.activeRole == 'gym_owner' ||
      widget.appUser.activeRole == 'branch_manager' ||
      widget.appUser.hasPermission('diet_plan.manage');

  List<Map<String, dynamic>> get _gyms => widget.appUser.gyms;

  List<Map<String, dynamic>> get _branches {
    final branches = <Map<String, dynamic>>[];
    for (final gym in _gyms) {
      for (final raw in gym['branches'] as List<dynamic>? ?? const []) {
        final branch = Map<String, dynamic>.from(raw as Map);
        branch['gym_id'] ??= gym['id'];
        branches.add(branch);
      }
    }
    if (branches.isEmpty) {
      branches.addAll(widget.appUser.branches);
    }
    return branches
        .where(
          (branch) =>
              _gymId == null || (branch['gym_id'] as num?)?.toInt() == _gymId,
        )
        .toList();
  }

  @override
  void initState() {
    super.initState();
    if (!_isPlatform && _gyms.isNotEmpty) {
      _gymId = (_gyms.first['id'] as num?)?.toInt();
      final branches = _branches;
      if (branches.length == 1) {
        _branchId = (branches.first['id'] as num?)?.toInt();
      }
    }
    _load();
  }

  Future<void> _load({bool reset = true}) async {
    setState(() {
      _loading = true;
      _error = null;
      if (reset) {
        _page = 1;
        _lastPage = 1;
      }
    });
    try {
      PaginatedResponse<Map<String, dynamic>> response;
      if (_isPlatform) {
        response = await widget.repository.fetchPlatformDietTemplates(
          page: _page,
        );
      } else {
        final gymId = _gymId;
        if (gymId == null) {
          throw StateError('Choose a gym to open its diet workspace.');
        }
        final results = await Future.wait([
          widget.repository.fetchGymDietPlans(
            gymId: gymId,
            branchId: _branchId,
            page: _page,
          ),
          widget.repository.fetchGymDietTemplates(
            gymId: gymId,
            branchId: _branchId,
          ),
          widget.repository.fetchGymDietMembers(
            gymId: gymId,
            branchId: _branchId,
          ),
        ]);
        response = results[0] as PaginatedResponse<Map<String, dynamic>>;
        _templates = results[1] as List<Map<String, dynamic>>;
        _members = results[2] as List<Map<String, dynamic>>;
      }
      if (!mounted) return;
      setState(() {
        _records = reset ? response.items : [..._records, ...response.items];
        _page = response.currentPage;
        _lastPage = response.lastPage;
      });
    } catch (error) {
      _error = error.toString();
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _openEditor([Map<String, dynamic>? record]) async {
    final formKey = GlobalKey<FormState>();
    var details = record == null
        ? <String, dynamic>{'status': 'active'}
        : Map<String, dynamic>.from(record);
    var meals = record == null
        ? _defaultMeals()
        : (record['meals'] as List<dynamic>? ?? const [])
              .map((meal) => Map<String, dynamic>.from(meal as Map))
              .toList();
    var memberId = _isPlatform ? null : (record?['member_id'] as num?)?.toInt();
    int? templateId;
    final templateName = TextEditingController();
    var saving = false;
    try {
      final changed = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: Colors.transparent,
        builder: (sheetContext) => StatefulBuilder(
          builder: (context, setSheetState) => FitModalSurface(
            title: record == null
                ? (_isPlatform
                      ? 'Create global diet template'
                      : 'Assign diet plan')
                : (_isPlatform ? 'Edit global template' : 'Edit diet plan'),
            subtitle: _isPlatform
                ? 'Global templates are reusable across every gym.'
                : 'Plans stay inside the selected gym and member scope.',
            icon: Icons.restaurant_menu_rounded,
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.sizeOf(context).height * 0.78,
              ),
              child: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (!_isPlatform) ...[
                        DropdownButtonFormField<int>(
                          initialValue: memberId,
                          isExpanded: true,
                          items: _members
                              .map(
                                (member) => DropdownMenuItem<int>(
                                  value: (member['id'] as num?)?.toInt(),
                                  child: Text(
                                    member['name']?.toString() ?? 'Member',
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: record == null
                              ? (value) => setSheetState(() => memberId = value)
                              : null,
                          validator: (value) =>
                              value == null ? 'Choose a member' : null,
                          decoration: const InputDecoration(
                            labelText: 'Member',
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        if (record == null)
                          DropdownButtonFormField<int?>(
                            initialValue: templateId,
                            isExpanded: true,
                            items: [
                              const DropdownMenuItem<int?>(
                                value: null,
                                child: Text('Build a custom plan'),
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
                        if (record == null)
                          const SizedBox(height: AppSpacing.md),
                      ],
                      if (!_isPlatform &&
                          record == null &&
                          templateId != null) ...[
                        TextFormField(
                          controller: templateName,
                          decoration: const InputDecoration(
                            labelText: 'Custom plan name (optional)',
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Text(
                          'The full template and every product line will be copied into this member’s gym-scoped plan.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ] else ...[
                        DietPlanDetailsEditor(
                          initialPlan: details,
                          includeStatus: _isPlatform || record != null,
                          includeSchedule: !_isPlatform,
                          onChanged: (value) => details = value,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        DietPlanMealsEditor(
                          initialMeals: meals,
                          onChanged: (value) => meals = value,
                        ),
                      ],
                      const SizedBox(height: AppSpacing.lg),
                      GradientButton(
                        label: saving ? 'Saving...' : 'Save',
                        loading: saving,
                        expanded: true,
                        onPressed: saving
                            ? null
                            : () async {
                                if (!formKey.currentState!.validate()) return;
                                setSheetState(() => saving = true);
                                try {
                                  await _saveRecord(
                                    record: record,
                                    memberId: memberId,
                                    templateId: templateId,
                                    templateName: templateName.text,
                                    details: details,
                                    meals: meals,
                                  );
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
    } finally {
      templateName.dispose();
    }
  }

  Future<void> _saveRecord({
    required Map<String, dynamic>? record,
    required int? memberId,
    required int? templateId,
    required String templateName,
    required Map<String, dynamic> details,
    required List<Map<String, dynamic>> meals,
  }) async {
    if (_isPlatform) {
      final payload = {
        ...details,
        'status': details['status'] ?? 'active',
        'meals': meals,
      };
      final id = (record?['id'] as num?)?.toInt();
      if (id == null) {
        await widget.repository.createPlatformDietTemplate(payload);
      } else {
        await widget.repository.updatePlatformDietTemplate(id, payload);
      }
      return;
    }

    final gymId = _gymId;
    if (gymId == null || memberId == null) {
      throw StateError('Gym and member are required.');
    }
    final id = (record?['id'] as num?)?.toInt();
    if (id != null) {
      await widget.repository.updateGymDietPlan(id, {
        ...details,
        'meals': meals,
      });
      return;
    }

    if (templateId != null) {
      await widget.repository.assignGymDietTemplate(templateId, {
        'gym_id': gymId,
        'branch_id': _branchId,
        'member_ids': [memberId],
        if (templateName.trim().isNotEmpty) 'name': templateName.trim(),
      });
      return;
    }

    await widget.repository.createGymDietPlan({
      'gym_id': gymId,
      'branch_id': _branchId,
      'member_ids': [memberId],
      ...details,
      'meals': meals,
    });
  }

  Future<void> _deletePlan(Map<String, dynamic> plan) async {
    final id = (plan['id'] as num?)?.toInt();
    if (id == null || _isPlatform) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => const ConfirmationDialog(
        title: 'Delete diet plan?',
        message:
            'The assigned member will immediately lose access to this plan.',
        confirmLabel: 'Delete',
      ),
    );
    if (confirmed != true) return;
    await widget.repository.deleteGymDietPlan(id);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const LoadingState(label: 'Loading diet workspace...');
    }
    if (_error != null) {
      return ErrorState(message: _error!, onRetry: _load);
    }

    return Column(
      children: [
        if (!_isPlatform && _gyms.length > 1) _scopeSelectors(),
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.md),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  _isPlatform ? 'Global templates' : 'Assigned member plans',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              if (_canManage)
                GradientButton(
                  label: _isPlatform ? 'New template' : 'Assign plan',
                  icon: Icons.add_rounded,
                  onPressed: () => _openEditor(),
                ),
            ],
          ),
        ),
        Expanded(
          child: _records.isEmpty
              ? EmptyState(
                  title: _isPlatform
                      ? 'No global diet templates'
                      : 'No assigned diet plans',
                  message: _isPlatform
                      ? 'Create the first reusable nutrition blueprint.'
                      : 'Assign a complete nutrition plan to a gym member.',
                  icon: Icons.restaurant_menu_rounded,
                )
              : RefreshIndicator(
                  onRefresh: () => _load(),
                  child: ListView.separated(
                    padding: const EdgeInsets.only(bottom: AppSpacing.xl),
                    itemCount: _records.length + (_hasMore ? 1 : 0),
                    separatorBuilder: (_, _) =>
                        const SizedBox(height: AppSpacing.sm),
                    itemBuilder: (context, index) {
                      if (index == _records.length) {
                        return Center(
                          child: OutlinedButton.icon(
                            onPressed: _loading
                                ? null
                                : () {
                                    setState(() => _page += 1);
                                    _load(reset: false);
                                  },
                            icon: const Icon(Icons.expand_more_rounded),
                            label: const Text('Load more'),
                          ),
                        );
                      }
                      return _recordCard(_records[index]);
                    },
                  ),
                ),
        ),
      ],
    );
  }

  Widget _scopeSelectors() {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Row(
        children: [
          Expanded(
            child: DropdownButtonFormField<int>(
              initialValue: _gymId,
              items: _gyms
                  .map(
                    (gym) => DropdownMenuItem<int>(
                      value: (gym['id'] as num?)?.toInt(),
                      child: Text(gym['name']?.toString() ?? 'Gym'),
                    ),
                  )
                  .toList(),
              onChanged: (value) {
                setState(() {
                  _gymId = value;
                  _branchId = null;
                });
                _load();
              },
              decoration: const InputDecoration(labelText: 'Gym'),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: DropdownButtonFormField<int?>(
              initialValue: _branchId,
              items: [
                const DropdownMenuItem<int?>(
                  value: null,
                  child: Text('All branches'),
                ),
                ..._branches.map(
                  (branch) => DropdownMenuItem<int?>(
                    value: (branch['id'] as num?)?.toInt(),
                    child: Text(branch['name']?.toString() ?? 'Branch'),
                  ),
                ),
              ],
              onChanged: (value) {
                setState(() => _branchId = value);
                _load();
              },
              decoration: const InputDecoration(labelText: 'Branch'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _recordCard(Map<String, dynamic> record) {
    final member = Map<String, dynamic>.from(
      record['member'] as Map? ?? const {},
    );
    final meals = record['meals'] as List<dynamic>? ?? const [];
    final status = record['status']?.toString() ?? 'active';
    return PremiumCard(
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.restaurant_rounded,
              color: AppColors.primaryBright,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  record['name']?.toString() ?? 'Diet plan',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 3),
                Text(
                  [
                    if (!_isPlatform) member['name'] ?? 'Member',
                    '${record['daily_calorie_target'] ?? '--'} kcal',
                    '${meals.length} meals',
                    status,
                  ].join(' · '),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          if (_canManage)
            IconButton(
              tooltip: 'Edit',
              onPressed: () => _openEditor(record),
              icon: const Icon(Icons.edit_outlined),
            ),
          if (_canManage && !_isPlatform)
            IconButton(
              tooltip: 'Delete',
              onPressed: () => _deletePlan(record),
              icon: const Icon(
                Icons.delete_outline_rounded,
                color: AppColors.error,
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
