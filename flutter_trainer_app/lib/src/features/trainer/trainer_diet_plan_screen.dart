import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:gym_flutter_core/diet_plan_meals_editor.dart';
import 'package:gym_flutter_core/diet_plan_summary_view.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/confirmation_dialog.dart';
import '../../../core/widgets/loading_state.dart';
import '../../core/pagination.dart';
import 'trainer_repository.dart';

class TrainerDietPlanScreen extends StatefulWidget {
  const TrainerDietPlanScreen({
    super.key,
    required this.repository,
    this.members = const [],
    this.preselectedMemberId,
    this.preselectedRelationshipId,
    this.embedded = false,
    this.plannerNavigation,
  });

  final TrainerRepository repository;
  final List<Map<String, dynamic>> members;
  final int? preselectedMemberId;
  final int? preselectedRelationshipId;
  final bool embedded;
  final Widget? plannerNavigation;

  @override
  State<TrainerDietPlanScreen> createState() => _TrainerDietPlanScreenState();
}

class _TrainerDietPlanScreenState extends State<TrainerDietPlanScreen> {
  final _formKey = GlobalKey<FormState>();

  bool _loading = true;
  bool _saving = false;
  bool _loadingMore = false;
  int _selectedTab = 0;
  int _editorRevision = 0;
  String? _error;
  List<Map<String, dynamic>> _templates = const [];
  List<Map<String, dynamic>> _foodCatalog = const [];
  bool _foodCatalogAvailable = false;
  ApiPagination _templatePage = const ApiPagination.singlePage();
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
      _templates = apiPageItems(response);
      _templatePage = ApiPagination.fromResponse(response);
      try {
        _foodCatalog = await _fetchInitialFoods();
        _foodCatalogAvailable = true;
      } catch (_) {
        // Older backends do not expose the catalog; custom foods remain usable.
        _foodCatalog = const [];
        _foodCatalogAvailable = false;
      }
    } catch (error) {
      _error = _dietErrorMessage(error);
    }
    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<List<Map<String, dynamic>>> _fetchInitialFoods() async =>
      apiPageItems(await widget.repository.fetchFoodCatalog());

  Future<List<Map<String, dynamic>>> _searchFoods(String query) async =>
      apiPageItems(await widget.repository.fetchFoodCatalog(search: query));

  Future<void> _loadMoreTemplates() async {
    if (_loadingMore || !_templatePage.hasMore) return;
    setState(() => _loadingMore = true);
    try {
      final response = await widget.repository.fetchDietTemplates(
        page: _templatePage.nextPage,
      );
      _templates = mergeApiPageItems(_templates, apiPageItems(response));
      _templatePage = ApiPagination.fromResponse(response);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(_dietErrorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _save({bool assignAfterSave = false}) async {
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
      final independentMembers = widget.members
          .where((assignment) => _assignmentRelationshipId(assignment) != null)
          .toList();
      Map<String, dynamic>? independentTarget;
      if (widget.preselectedRelationshipId != null) {
        independentTarget = independentMembers.firstWhere(
          (assignment) =>
              _assignmentRelationshipId(assignment) ==
              widget.preselectedRelationshipId,
          orElse: () => const <String, dynamic>{},
        );
        if (independentTarget.isEmpty) {
          throw Exception(
            'This independent coaching relationship is no longer available.',
          );
        }
      } else if (independentMembers.length == 1 &&
          widget.members.every(
            (assignment) => _assignmentRelationshipId(assignment) != null,
          )) {
        independentTarget = independentMembers.first;
      }
      final payload = <String, dynamic>{
        ..._draftDetails,
        'name': name,
        'status': 'active',
        'meals': _draftMeals,
      };
      final relationshipId = independentTarget == null
          ? null
          : _assignmentRelationshipId(independentTarget);
      final memberId = independentTarget == null
          ? null
          : _assignmentMemberId(independentTarget);
      final response = relationshipId != null && memberId != null
          ? await widget.repository.createDietPlan({
              ...payload,
              'member_ids': <int>[memberId],
              'independent_trainer_member_relationship_id': relationshipId,
            })
          : await widget.repository.createDietTemplate(payload);
      final created = response['data'] is Map
          ? Map<String, dynamic>.from(response['data'] as Map)
          : <String, dynamic>{};
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
          SnackBar(
            content: Text(
              relationshipId != null
                  ? 'Independent diet plan assigned to the member.'
                  : 'Diet plan saved to your library.',
            ),
          ),
        );
        if (assignAfterSave && relationshipId == null && created.isNotEmpty) {
          await _assign(created);
        }
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
                    foodCatalog: _foodCatalog,
                    onSearchFoodCatalog: _foodCatalogAvailable
                        ? _searchFoods
                        : null,
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

  Future<void> _assign(Map<String, dynamic> template) async {
    final templateId = (template['id'] as num?)?.toInt();
    final eligibleMembers = widget.members
        .where((assignment) => _assignmentMemberId(assignment) != null)
        .toList();
    if (templateId == null) {
      return;
    }
    if (eligibleMembers.isEmpty) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No linked members are available for assignment.'),
          ),
        );
      }
      return;
    }

    final selectedAssignmentKeys = <String>{};
    if (widget.preselectedMemberId != null &&
        eligibleMembers.any((assignment) {
          if (_assignmentMemberId(assignment) != widget.preselectedMemberId) {
            return false;
          }
          final relationshipId = _assignmentRelationshipId(assignment);
          return widget.preselectedRelationshipId == null
              ? relationshipId == null
              : relationshipId == widget.preselectedRelationshipId;
        })) {
      final assignment = eligibleMembers.firstWhere((assignment) {
        if (_assignmentMemberId(assignment) != widget.preselectedMemberId) {
          return false;
        }
        final relationshipId = _assignmentRelationshipId(assignment);
        return widget.preselectedRelationshipId == null
            ? relationshipId == null
            : relationshipId == widget.preselectedRelationshipId;
      });
      selectedAssignmentKeys.add(_assignmentKey(assignment));
    }
    var customName = '';
    DateTime? startsOn;
    DateTime? endsOn;
    var assigning = false;

    final assigned = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => DraggableScrollableSheet(
          initialChildSize: 0.82,
          minChildSize: 0.58,
          maxChildSize: 0.96,
          expand: false,
          builder: (context, controller) => Container(
            decoration: const BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: ListView(
              controller: controller,
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 28),
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
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Assign diet plan',
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            template['name']?.toString() ?? 'Diet plan',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                TextFormField(
                  initialValue: customName,
                  onChanged: (value) => customName = value.trim(),
                  decoration: const InputDecoration(
                    labelText: 'Custom plan name (optional)',
                    hintText: 'Keep the library plan name',
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  'Linked members',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                ...eligibleMembers.map((assignment) {
                  final member = _assignmentMember(assignment);
                  final assignmentKey = _assignmentKey(assignment);
                  final selected = selectedAssignmentKeys.contains(
                    assignmentKey,
                  );
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 7),
                    child: CheckboxListTile(
                      value: selected,
                      onChanged: assigning
                          ? null
                          : (value) => setSheetState(() {
                              if (value == true) {
                                if (_assignmentRelationshipId(assignment) !=
                                    null) {
                                  // Independent coaching assignments are
                                  // consent-scoped and must be created one
                                  // relationship at a time.
                                  selectedAssignmentKeys
                                    ..clear()
                                    ..add(assignmentKey);
                                } else {
                                  selectedAssignmentKeys.removeWhere(
                                    (key) => key.startsWith('independent:'),
                                  );
                                  selectedAssignmentKeys.add(assignmentKey);
                                }
                              } else {
                                selectedAssignmentKeys.remove(assignmentKey);
                              }
                            }),
                      title: Text(
                        member['name']?.toString() ?? 'Assigned member',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      subtitle: Text(_assignmentScopeLabel(assignment, member)),
                      secondary: const CircleAvatar(
                        child: Icon(Icons.person_outline_rounded),
                      ),
                      controlAffinity: ListTileControlAffinity.trailing,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                        side: const BorderSide(color: AppColors.stroke),
                      ),
                      tileColor: selected
                          ? AppColors.primary.withValues(alpha: 0.06)
                          : AppColors.surfaceSoft,
                    ),
                  );
                }),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: assigning
                            ? null
                            : () async {
                                final value = await showDatePicker(
                                  context: context,
                                  initialDate: startsOn ?? DateTime.now(),
                                  firstDate: DateTime.now().subtract(
                                    const Duration(days: 365),
                                  ),
                                  lastDate: DateTime.now().add(
                                    const Duration(days: 3650),
                                  ),
                                );
                                if (value != null) {
                                  setSheetState(() => startsOn = value);
                                }
                              },
                        icon: const Icon(Icons.event_rounded),
                        label: Text(
                          startsOn == null
                              ? 'Start date'
                              : _dateValue(startsOn!),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: assigning
                            ? null
                            : () async {
                                final value = await showDatePicker(
                                  context: context,
                                  initialDate:
                                      endsOn ?? startsOn ?? DateTime.now(),
                                  firstDate:
                                      startsOn ??
                                      DateTime.now().subtract(
                                        const Duration(days: 365),
                                      ),
                                  lastDate: DateTime.now().add(
                                    const Duration(days: 3650),
                                  ),
                                );
                                if (value != null) {
                                  setSheetState(() => endsOn = value);
                                }
                              },
                        icon: const Icon(Icons.event_available_rounded),
                        label: Text(
                          endsOn == null ? 'End date' : _dateValue(endsOn!),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                GradientButton(
                  label: assigning
                      ? 'Assigning...'
                      : 'Assign to ${selectedAssignmentKeys.length} member${selectedAssignmentKeys.length == 1 ? '' : 's'}',
                  icon: Icons.assignment_turned_in_rounded,
                  expanded: true,
                  onPressed: assigning || selectedAssignmentKeys.isEmpty
                      ? null
                      : () async {
                          setSheetState(() => assigning = true);
                          try {
                            final selectedAssignments = eligibleMembers
                                .where(
                                  (assignment) => selectedAssignmentKeys
                                      .contains(_assignmentKey(assignment)),
                                )
                                .toList();
                            final selectedAssignment =
                                selectedAssignments.first;
                            final relationshipId = _assignmentRelationshipId(
                              selectedAssignment,
                            );
                            final gymId = _intValue(
                              selectedAssignment['gym_id'],
                            );
                            final branchId = _intValue(
                              selectedAssignment['branch_id'],
                            );
                            await widget.repository.assignDietTemplate(
                              templateId,
                              {
                                'member_ids': selectedAssignments
                                    .map(_assignmentMemberId)
                                    .whereType<int>()
                                    .toSet()
                                    .toList(),
                                if (relationshipId != null)
                                  'independent_trainer_member_relationship_id':
                                      relationshipId,
                                if (gymId != null) 'gym_id': gymId,
                                if (branchId != null) 'branch_id': branchId,
                                if (customName.isNotEmpty) 'name': customName,
                                if (startsOn != null)
                                  'starts_on': _dateValue(startsOn!),
                                if (endsOn != null)
                                  'ends_on': _dateValue(endsOn!),
                              },
                            );
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
                              setSheetState(() => assigning = false);
                            }
                          }
                        },
                ),
              ],
            ),
          ),
        ),
      ),
    );

    if (assigned == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Diet plan assigned to ${selectedAssignmentKeys.length} member${selectedAssignmentKeys.length == 1 ? '' : 's'}.',
          ),
        ),
      );
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
              foodCatalog: _foodCatalog,
              onSearchFoodCatalog: _foodCatalogAvailable ? _searchFoods : null,
              onChanged: (value) => _draftMeals = value,
            ),
          ),
          const SizedBox(height: 16),
          if (widget.members.isNotEmpty) ...[
            GradientButton(
              label: _saving ? 'Saving plan...' : 'Save and assign to members',
              icon: Icons.assignment_turned_in_rounded,
              expanded: true,
              onPressed: _saving ? null : () => _save(assignAfterSave: true),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: TextButton.icon(
                onPressed: _saving ? null : _save,
                icon: const Icon(Icons.library_add_check_rounded),
                label: const Text('Save to library only'),
              ),
            ),
          ] else
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
      itemCount: _templates.length + (_templatePage.hasMore ? 1 : 0),
      separatorBuilder: (_, __) => const SizedBox(height: 9),
      itemBuilder: (context, index) {
        if (index == _templates.length) {
          return Center(
            child: OutlinedButton.icon(
              onPressed: _loadingMore ? null : _loadMoreTemplates,
              icon: _loadingMore
                  ? const SizedBox.square(
                      dimension: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.expand_more_rounded),
              label: Text(_loadingMore ? 'Loading...' : 'Load more diet plans'),
            ),
          );
        }
        final template = _templates[index];
        return _DietLibraryCard(
          template: template,
          onOpen: () => _openPreview(template),
          onAssign: () => _assign(template),
          onEdit: template['is_owned'] == true ? () => _edit(template) : null,
          onDelete: template['is_owned'] == true
              ? () => _delete(template)
              : null,
        );
      },
    );
  }

  Future<void> _openPreview(Map<String, dynamic> template) =>
      showDietPlanSummarySheet(context, plan: template);
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
    required this.onAssign,
    this.onEdit,
    this.onDelete,
  });

  final Map<String, dynamic> template;
  final VoidCallback onOpen;
  final VoidCallback onAssign;
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
          child: Column(
            children: [
              Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: AppColors.surfaceSoft,
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: Icon(
                      owned
                          ? Icons.restaurant_menu_rounded
                          : Icons.public_rounded,
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
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${template['daily_calorie_target'] ?? '--'} kcal • ${meals.length} meals • ${owned ? 'Your plan' : 'Atlas library'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: AppColors.textSecondary),
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
                        PopupMenuItem(
                          value: 'delete',
                          child: Text('Delete plan'),
                        ),
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
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: onAssign,
                  icon: const Icon(Icons.assignment_ind_outlined),
                  label: const Text('Assign to members'),
                ),
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

List<Map<String, dynamic>> _defaultMeals() => [
  <String, dynamic>{'name': 'Meal 1', 'meal_type': 'meal_1', 'items': []},
];

int? _assignmentMemberId(Map<String, dynamic> assignment) =>
    (assignment['member_id'] as num?)?.toInt() ??
    (_assignmentMember(assignment)['id'] as num?)?.toInt();

int? _assignmentRelationshipId(Map<String, dynamic> assignment) =>
    (assignment['relationship_id'] as num?)?.toInt() ??
    (assignment['independent_trainer_member_relationship_id'] as num?)?.toInt();

String _assignmentKey(Map<String, dynamic> assignment) {
  final relationshipId = _assignmentRelationshipId(assignment);
  if (relationshipId != null) {
    return 'independent:$relationshipId';
  }
  return 'gym:${_intValue(assignment['gym_id']) ?? 0}:'
      '${_intValue(assignment['branch_id']) ?? 0}:'
      '${_assignmentMemberId(assignment) ?? 0}';
}

String _assignmentScopeLabel(
  Map<String, dynamic> assignment,
  Map<String, dynamic> member,
) {
  final email = member['email']?.toString().trim() ?? '';
  final relationshipId = _assignmentRelationshipId(assignment);
  final scope = relationshipId != null
      ? 'Independent coaching'
      : 'Gym ${_intValue(assignment['gym_id']) ?? '--'}'
            ' · Branch ${_intValue(assignment['branch_id']) ?? '--'}';
  return email.isEmpty ? scope : '$scope · $email';
}

int? _intValue(dynamic value) {
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}

Map<String, dynamic> _assignmentMember(Map<String, dynamic> assignment) {
  final member = assignment['member'];
  return member is Map ? Map<String, dynamic>.from(member) : assignment;
}

String _dateValue(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

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
