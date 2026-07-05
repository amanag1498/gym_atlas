import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/models/session_models.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/widgets/common_widgets.dart';
import '../auth/session_controller.dart';
import 'admin_repository.dart';

class PlatformWorkoutBooksScreen extends StatelessWidget {
  const PlatformWorkoutBooksScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionController>();
    final user = session.user;

    if (user == null) {
      return const SizedBox.shrink();
    }

    final repository = AdminRepository(session.authenticatedClient);

    return AppGradientScaffold(
      appBar: PremiumAppBar(
        title: 'Workout Books',
        subtitle: 'PLATFORM ADMIN',
        leading: IconButton(
          onPressed: () => context.go('/home'),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        actions: [
          TextButton(
            onPressed: () => context.go('/home'),
            child: const Text('Dashboard'),
          ),
          TextButton(
            onPressed: () => context.read<SessionController>().logout(),
            child: const Text('Logout'),
          ),
        ],
      ),
      child: user.isPlatformAdmin
          ? PlatformWorkoutBooksWorkspace(
              appUser: user,
              repository: repository,
              embedded: false,
            )
          : const Center(
              child: EmptyState(
                title: 'Platform access required',
                message:
                    'Workout book management is available only for platform admin accounts.',
                icon: Icons.lock_outline_rounded,
              ),
            ),
    );
  }
}

class PlatformWorkoutBooksWorkspace extends StatefulWidget {
  const PlatformWorkoutBooksWorkspace({
    super.key,
    required this.appUser,
    required this.repository,
    this.embedded = true,
  });

  final AppUser appUser;
  final AdminRepository repository;
  final bool embedded;

  @override
  State<PlatformWorkoutBooksWorkspace> createState() =>
      _PlatformWorkoutBooksWorkspaceState();
}

class _PlatformWorkoutBooksWorkspaceState
    extends State<PlatformWorkoutBooksWorkspace> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _books = const [];
  final TextEditingController _searchController = TextEditingController();
  String? _statusFilter;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      _books = await widget.repository.fetchPlatformWorkoutBooks(
        search: _searchController.text.trim().isEmpty
            ? null
            : _searchController.text.trim(),
        status: _statusFilter,
      );
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<T?> _showResponsiveOverlay<T>({
    required WidgetBuilder builder,
    double dialogWidth = 980,
  }) {
    final width = MediaQuery.sizeOf(context).width;
    if (width >= 960) {
      return showDialog<T>(
        context: context,
        barrierDismissible: false,
        builder: (context) => Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: const EdgeInsets.symmetric(
            horizontal: 32,
            vertical: 24,
          ),
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: dialogWidth),
            child: builder(context),
          ),
        ),
      );
    }

    return showModalBottomSheet<T>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: builder,
    );
  }

  Future<void> _openForm({Map<String, dynamic>? prefill}) async {
    final changed = await _showResponsiveOverlay<bool>(
      dialogWidth: 1040,
      builder: (context) => WorkoutBookEditorDialog(
        repository: widget.repository,
        prefill: prefill,
      ),
    );

    if (changed == true && mounted) {
      await _load();
    }
  }

  Future<void> _showDetail(Map<String, dynamic> book) async {
    final id = (book['id'] as num?)?.toInt();
    if (id == null) {
      return;
    }

    final detail = await widget.repository.fetchPlatformWorkoutBookDetail(id);
    if (!mounted) {
      return;
    }

    await _showResponsiveOverlay<void>(
      dialogWidth: 920,
      builder: (context) => _WorkoutBookDetailDialog(detail: detail),
    );
  }

  Future<void> _delete(Map<String, dynamic> book) async {
    final id = (book['id'] as num?)?.toInt();
    if (id == null) {
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => ConfirmationDialog(
        title: 'Delete workout book?',
        message:
            'Delete ${book['name']?.toString() ?? 'this workout book'} and all plans inside it?',
        confirmLabel: 'Delete',
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await widget.repository.deletePlatformWorkoutBook(id);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Workout book deleted.')));
      await _load();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    final featuredCount = _books
        .where((book) => book['is_featured'] == true)
        .length;
    final totalPlans = _books.fold<int>(
      0,
      (sum, book) => sum + ((book['plans_count'] as num?)?.toInt() ?? 0),
    );

    return AsyncStateView(
      isLoading: _loading,
      error: _error,
      onRetry: _load,
      loadingChild: const LoadingState(label: 'Loading workout books...'),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final wide = constraints.maxWidth >= 1180;
          final medium = constraints.maxWidth >= 760;
          final content = <Widget>[
            PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    alignment: WrapAlignment.spaceBetween,
                    runSpacing: 16,
                    spacing: 16,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      ConstrainedBox(
                        constraints: BoxConstraints(
                          maxWidth: wide ? 620 : constraints.maxWidth,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 8,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.primary.withValues(
                                  alpha: 0.14,
                                ),
                                borderRadius: BorderRadius.circular(999),
                                border: Border.all(
                                  color: AppColors.primary.withValues(
                                    alpha: 0.28,
                                  ),
                                ),
                              ),
                              child: Text(
                                'PLATFORM TRAINING CATALOG',
                                style: Theme.of(context).textTheme.labelMedium
                                    ?.copyWith(
                                      letterSpacing: 0.8,
                                      fontWeight: FontWeight.w800,
                                      color: AppColors.primaryBright,
                                    ),
                              ),
                            ),
                            const SizedBox(height: 14),
                            Text(
                              'Control the master workout library members discover across the platform.',
                              style: Theme.of(context).textTheme.headlineSmall,
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'Build structured books, inspect nested plans, and publish clean catalog programs for different goals and experience levels.',
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ],
                        ),
                      ),
                      SizedBox(
                        width: medium ? 220 : double.infinity,
                        child: GradientButton(
                          label: 'Create Workout Book',
                          icon: Icons.add_rounded,
                          expanded: true,
                          onPressed: _openForm,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      _MetricTile(
                        label: 'Books',
                        value: '${_books.length}',
                        icon: Icons.menu_book_rounded,
                      ),
                      _MetricTile(
                        label: 'Featured',
                        value: '$featuredCount',
                        icon: Icons.workspace_premium_rounded,
                      ),
                      _MetricTile(
                        label: 'Plans',
                        value: '$totalPlans',
                        icon: Icons.route_rounded,
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      SizedBox(
                        width: wide
                            ? 420
                            : (medium ? 320 : constraints.maxWidth),
                        child: TextField(
                          controller: _searchController,
                          decoration: InputDecoration(
                            labelText: 'Search workout books',
                            suffixIcon: IconButton(
                              onPressed: _load,
                              icon: const Icon(Icons.search_rounded),
                            ),
                          ),
                          onSubmitted: (_) => _load(),
                        ),
                      ),
                      SizedBox(
                        width: 220,
                        child: DropdownButtonFormField<String?>(
                          initialValue: _statusFilter,
                          decoration: const InputDecoration(
                            labelText: 'Status',
                          ),
                          items: const [
                            DropdownMenuItem<String?>(
                              value: null,
                              child: Text('All'),
                            ),
                            DropdownMenuItem(
                              value: 'active',
                              child: Text('Active'),
                            ),
                            DropdownMenuItem(
                              value: 'inactive',
                              child: Text('Inactive'),
                            ),
                          ],
                          onChanged: (value) =>
                              setState(() => _statusFilter = value),
                        ),
                      ),
                      SizedBox(
                        width: 160,
                        child: OutlinedButton.icon(
                          onPressed: _load,
                          icon: const Icon(Icons.tune_rounded),
                          label: const Text('Apply'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ];

          if (_books.isEmpty) {
            content.add(
              const EmptyState(
                title: 'No workout books created',
                message:
                    'Create a platform workout book to publish catalog plans for members.',
                icon: Icons.menu_book_rounded,
              ),
            );
          } else if (wide) {
            content.add(
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: _books.length,
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  crossAxisSpacing: 16,
                  mainAxisSpacing: 16,
                  mainAxisExtent: 286,
                ),
                itemBuilder: (context, index) => _WorkoutBookCard(
                  book: _books[index],
                  onPreview: () => _showDetail(_books[index]),
                  onEdit: () => _openForm(prefill: _books[index]),
                  onDelete: () => _delete(_books[index]),
                ),
              ),
            );
          } else {
            content.addAll(
              _books
                  .map(
                    (book) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _WorkoutBookCard(
                        book: book,
                        onPreview: () => _showDetail(book),
                        onEdit: () => _openForm(prefill: book),
                        onDelete: () => _delete(book),
                      ),
                    ),
                  )
                  .toList(),
            );
          }

          final body = ListView(
            padding: EdgeInsets.all(widget.embedded ? 0 : 20),
            children: content,
          );

          if (widget.embedded) {
            return body;
          }

          return Align(
            alignment: Alignment.topCenter,
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 1320),
              child: body,
            ),
          );
        },
      ),
    );
  }
}

class _WorkoutBookCard extends StatelessWidget {
  const _WorkoutBookCard({
    required this.book,
    required this.onPreview,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> book;
  final VoidCallback onPreview;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final plans =
        (book['plans_count'] as num?)?.toInt() ??
        (book['plans'] as List<dynamic>? ?? const []).length;

    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      book['name']?.toString() ?? 'Workout book',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 6),
                    Text(
                      book['description']?.toString() ?? 'No description set.',
                      style: Theme.of(context).textTheme.bodyMedium,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              _WorkoutBookBadge(label: book['status']?.toString() ?? 'active'),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _WorkoutBookBadge(label: '$plans plans'),
              if (book['difficulty'] != null)
                _WorkoutBookBadge(label: '${book['difficulty']}'),
              if (book['days_per_week'] != null)
                _WorkoutBookBadge(label: '${book['days_per_week']} days/week'),
              if (book['estimated_session_minutes'] != null)
                _WorkoutBookBadge(
                  label: '${book['estimated_session_minutes']} min',
                ),
              if (book['is_featured'] == true)
                const _WorkoutBookBadge(label: 'featured'),
            ],
          ),
          const Spacer(),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              SizedBox(
                width: 148,
                child: OutlinedButton.icon(
                  onPressed: onPreview,
                  icon: const Icon(Icons.visibility_outlined),
                  label: const Text('Preview'),
                ),
              ),
              SizedBox(
                width: 132,
                child: OutlinedButton.icon(
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_rounded),
                  label: const Text('Edit'),
                ),
              ),
              SizedBox(
                width: 132,
                child: OutlinedButton.icon(
                  onPressed: onDelete,
                  icon: const Icon(Icons.delete_outline_rounded),
                  label: const Text('Delete'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 156,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceStrong.withValues(alpha: 0.74),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.primaryBright),
          const SizedBox(height: 12),
          Text(value, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _WorkoutBookDetailDialog extends StatelessWidget {
  const _WorkoutBookDetailDialog({required this.detail});

  final Map<String, dynamic> detail;

  @override
  Widget build(BuildContext context) {
    final plans = (detail['plans'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();

    return FitModalSurface(
      title: detail['name']?.toString() ?? 'Workout book',
      subtitle: detail['description']?.toString() ?? 'No description set.',
      icon: Icons.menu_book_rounded,
      isDialog: true,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _WorkoutBookBadge(label: '${detail['plans_count'] ?? 0} plans'),
              if (detail['difficulty'] != null)
                _WorkoutBookBadge(label: '${detail['difficulty']}'),
              if (detail['days_per_week'] != null)
                _WorkoutBookBadge(
                  label: '${detail['days_per_week']} days/week',
                ),
              if (detail['total_exercises'] != null)
                _WorkoutBookBadge(
                  label: '${detail['total_exercises']} exercise slots',
                ),
            ],
          ),
          const SizedBox(height: 16),
          Flexible(
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: plans
                    .map(
                      (plan) => Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(18),
                          color: AppColors.surfaceSoft,
                          border: Border.all(color: AppColors.strokeStrong),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              plan['name']?.toString() ?? 'Plan',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 6),
                            Text(
                              plan['goal']?.toString() ??
                                  'Structured training plan',
                            ),
                            const SizedBox(height: 10),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                if (plan['total_workout_days'] != null)
                                  _WorkoutBookBadge(
                                    label: '${plan['total_workout_days']} days',
                                  ),
                                if (plan['total_exercises'] != null)
                                  _WorkoutBookBadge(
                                    label:
                                        '${plan['total_exercises']} exercises',
                                  ),
                                if (plan['estimated_session_minutes'] != null)
                                  _WorkoutBookBadge(
                                    label:
                                        '${plan['estimated_session_minutes']} min',
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    )
                    .toList(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class WorkoutBookEditorDialog extends StatefulWidget {
  const WorkoutBookEditorDialog({
    super.key,
    required this.repository,
    this.prefill,
  });

  final AdminRepository repository;
  final Map<String, dynamic>? prefill;

  @override
  State<WorkoutBookEditorDialog> createState() =>
      _WorkoutBookEditorDialogState();
}

class _WorkoutBookEditorDialogState extends State<WorkoutBookEditorDialog> {
  late final TextEditingController _nameController;
  late final TextEditingController _audienceController;
  late final TextEditingController _goalController;
  late final TextEditingController _programTypeController;
  late final TextEditingController _equipmentController;
  late final TextEditingController _daysController;
  late final TextEditingController _durationController;
  late final TextEditingController _minutesController;
  late final TextEditingController _descriptionController;
  late final TextEditingController _coachNotesController;
  late final TextEditingController _plansJsonController;
  late String _difficulty;
  late String _status;
  bool _featured = false;
  bool _saving = false;

  static const String _samplePlanJson = '''
[
  {
    "name": "Starter Full Body",
    "goal": "General strength and movement quality",
    "difficulty": "beginner",
    "program_type": "full_body",
    "equipment_profile": "mixed_gym",
    "duration_weeks": 4,
    "estimated_session_minutes": 45,
    "weekly_schedule": ["monday", "wednesday", "friday"],
    "notes": "Keep 1-2 reps in reserve on every set.",
    "status": "active",
    "days": [
      {
        "day_number": 1,
        "label": "Day 1",
        "focus": "Squat and push",
        "notes": "",
        "exercises": [
          {"exercise_id": 1, "sort_order": 1, "sets": 3, "reps": "8-10", "target_weight": null, "rest_seconds": 90, "notes": ""}
        ]
      }
    ]
  }
]
''';

  @override
  void initState() {
    super.initState();
    final prefill = widget.prefill ?? const <String, dynamic>{};
    _nameController = TextEditingController(
      text: prefill['name']?.toString() ?? '',
    );
    _audienceController = TextEditingController(
      text: prefill['audience']?.toString() ?? '',
    );
    _goalController = TextEditingController(
      text: prefill['goal']?.toString() ?? '',
    );
    _programTypeController = TextEditingController(
      text: prefill['program_type']?.toString() ?? '',
    );
    _equipmentController = TextEditingController(
      text: prefill['equipment_profile']?.toString() ?? '',
    );
    _daysController = TextEditingController(
      text: '${prefill['days_per_week'] ?? 3}',
    );
    _durationController = TextEditingController(
      text: '${prefill['duration_weeks'] ?? 4}',
    );
    _minutesController = TextEditingController(
      text: '${prefill['estimated_session_minutes'] ?? 45}',
    );
    _descriptionController = TextEditingController(
      text: prefill['description']?.toString() ?? '',
    );
    _coachNotesController = TextEditingController(
      text: prefill['coach_notes']?.toString() ?? '',
    );
    _difficulty = prefill['difficulty']?.toString() ?? 'beginner';
    _status = prefill['status']?.toString() ?? 'active';
    _featured = prefill['is_featured'] == true;
    final plans = (prefill['plans'] as List<dynamic>? ?? const []);
    _plansJsonController = TextEditingController(
      text: const JsonEncoder.withIndent('  ').convert(
        plans
            .map(
              (item) =>
                  _normalizePlanJson(Map<String, dynamic>.from(item as Map)),
            )
            .toList(),
      ),
    );
    if (plans.isEmpty) {
      _plansJsonController.text = _samplePlanJson;
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _audienceController.dispose();
    _goalController.dispose();
    _programTypeController.dispose();
    _equipmentController.dispose();
    _daysController.dispose();
    _durationController.dispose();
    _minutesController.dispose();
    _descriptionController.dispose();
    _coachNotesController.dispose();
    _plansJsonController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Workout book name is required.')),
      );
      return;
    }

    List<dynamic> plans;
    try {
      plans = jsonDecode(_plansJsonController.text.trim()) as List<dynamic>;
    } catch (_) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Plans JSON is not valid.')));
      return;
    }

    final payload = <String, dynamic>{
      'name': name,
      'audience': _audienceController.text.trim(),
      'goal': _goalController.text.trim(),
      'difficulty': _difficulty,
      'program_type': _programTypeController.text.trim(),
      'equipment_profile': _equipmentController.text.trim(),
      'days_per_week': int.tryParse(_daysController.text.trim()) ?? 3,
      'duration_weeks': int.tryParse(_durationController.text.trim()) ?? 4,
      'estimated_session_minutes':
          int.tryParse(_minutesController.text.trim()) ?? 45,
      'description': _descriptionController.text.trim(),
      'coach_notes': _coachNotesController.text.trim(),
      'is_featured': _featured,
      'status': _status,
      'plans': plans,
    };

    setState(() => _saving = true);
    try {
      final id = (widget.prefill?['id'] as num?)?.toInt();
      if (id == null) {
        await widget.repository.createPlatformWorkoutBook(payload);
      } else {
        await widget.repository.updatePlatformWorkoutBook(id, payload);
      }
      if (!mounted) {
        return;
      }
      Navigator.of(context).pop(true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
      setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FitModalSurface(
      title: 'Workout book setup',
      subtitle:
          'Manage platform catalog metadata and nested plan payloads in one place.',
      icon: Icons.menu_book_rounded,
      isDialog: true,
      showClose: false,
      child: SingleChildScrollView(
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
                        widget.prefill == null
                            ? 'Create workout book'
                            : 'Edit workout book',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Manage platform catalog metadata and the nested plan payload that powers member adoption.',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: _saving
                      ? null
                      : () => Navigator.of(context).pop(false),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
                color: AppColors.surfaceStrong.withValues(alpha: 0.68),
                border: Border.all(color: AppColors.strokeStrong),
              ),
              child: Text(
                'Tip: use the sample JSON and replace exercise ids with ids from your exercise catalog.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
            const SizedBox(height: 16),
            LayoutBuilder(
              builder: (context, constraints) {
                final wide = constraints.maxWidth >= 860;
                return Column(
                  children: [
                    if (wide)
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: _EditorColumn(
                              children: [
                                TextField(
                                  controller: _nameController,
                                  decoration: const InputDecoration(
                                    labelText: 'Name',
                                  ),
                                ),
                                TextField(
                                  controller: _audienceController,
                                  decoration: const InputDecoration(
                                    labelText: 'Audience',
                                  ),
                                ),
                                TextField(
                                  controller: _goalController,
                                  decoration: const InputDecoration(
                                    labelText: 'Goal',
                                  ),
                                ),
                                Row(
                                  children: [
                                    Expanded(
                                      child: DropdownButtonFormField<String>(
                                        initialValue: _difficulty,
                                        decoration: const InputDecoration(
                                          labelText: 'Difficulty',
                                        ),
                                        items: const [
                                          DropdownMenuItem(
                                            value: 'beginner',
                                            child: Text('Beginner'),
                                          ),
                                          DropdownMenuItem(
                                            value: 'intermediate',
                                            child: Text('Intermediate'),
                                          ),
                                          DropdownMenuItem(
                                            value: 'advanced',
                                            child: Text('Advanced'),
                                          ),
                                        ],
                                        onChanged: (value) => setState(
                                          () =>
                                              _difficulty = value ?? 'beginner',
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: DropdownButtonFormField<String>(
                                        initialValue: _status,
                                        decoration: const InputDecoration(
                                          labelText: 'Status',
                                        ),
                                        items: const [
                                          DropdownMenuItem(
                                            value: 'active',
                                            child: Text('Active'),
                                          ),
                                          DropdownMenuItem(
                                            value: 'inactive',
                                            child: Text('Inactive'),
                                          ),
                                        ],
                                        onChanged: (value) => setState(
                                          () => _status = value ?? 'active',
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                Row(
                                  children: [
                                    Expanded(
                                      child: TextField(
                                        controller: _programTypeController,
                                        decoration: const InputDecoration(
                                          labelText: 'Program type',
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: TextField(
                                        controller: _equipmentController,
                                        decoration: const InputDecoration(
                                          labelText: 'Equipment profile',
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                Row(
                                  children: [
                                    Expanded(
                                      child: TextField(
                                        controller: _daysController,
                                        keyboardType: TextInputType.number,
                                        decoration: const InputDecoration(
                                          labelText: 'Days/week',
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: TextField(
                                        controller: _durationController,
                                        keyboardType: TextInputType.number,
                                        decoration: const InputDecoration(
                                          labelText: 'Duration weeks',
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: TextField(
                                        controller: _minutesController,
                                        keyboardType: TextInputType.number,
                                        decoration: const InputDecoration(
                                          labelText: 'Minutes/session',
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                SwitchListTile.adaptive(
                                  value: _featured,
                                  onChanged: (value) =>
                                      setState(() => _featured = value),
                                  title: const Text('Featured in catalog'),
                                  contentPadding: EdgeInsets.zero,
                                ),
                                TextField(
                                  controller: _descriptionController,
                                  maxLines: 3,
                                  decoration: const InputDecoration(
                                    labelText: 'Description',
                                  ),
                                ),
                                TextField(
                                  controller: _coachNotesController,
                                  maxLines: 3,
                                  decoration: const InputDecoration(
                                    labelText: 'Coach notes',
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: _EditorColumn(
                              children: [
                                TextField(
                                  controller: _plansJsonController,
                                  maxLines: 24,
                                  decoration: const InputDecoration(
                                    labelText: 'Plans JSON',
                                    alignLabelWithHint: true,
                                  ),
                                ),
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: OutlinedButton.icon(
                                    onPressed: () => setState(
                                      () => _plansJsonController.text =
                                          _samplePlanJson,
                                    ),
                                    icon: const Icon(
                                      Icons.auto_fix_high_rounded,
                                    ),
                                    label: const Text('Load sample JSON'),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      )
                    else
                      _EditorColumn(
                        children: [
                          TextField(
                            controller: _nameController,
                            decoration: const InputDecoration(
                              labelText: 'Name',
                            ),
                          ),
                          TextField(
                            controller: _audienceController,
                            decoration: const InputDecoration(
                              labelText: 'Audience',
                            ),
                          ),
                          TextField(
                            controller: _goalController,
                            decoration: const InputDecoration(
                              labelText: 'Goal',
                            ),
                          ),
                          Row(
                            children: [
                              Expanded(
                                child: DropdownButtonFormField<String>(
                                  initialValue: _difficulty,
                                  decoration: const InputDecoration(
                                    labelText: 'Difficulty',
                                  ),
                                  items: const [
                                    DropdownMenuItem(
                                      value: 'beginner',
                                      child: Text('Beginner'),
                                    ),
                                    DropdownMenuItem(
                                      value: 'intermediate',
                                      child: Text('Intermediate'),
                                    ),
                                    DropdownMenuItem(
                                      value: 'advanced',
                                      child: Text('Advanced'),
                                    ),
                                  ],
                                  onChanged: (value) => setState(
                                    () => _difficulty = value ?? 'beginner',
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: DropdownButtonFormField<String>(
                                  initialValue: _status,
                                  decoration: const InputDecoration(
                                    labelText: 'Status',
                                  ),
                                  items: const [
                                    DropdownMenuItem(
                                      value: 'active',
                                      child: Text('Active'),
                                    ),
                                    DropdownMenuItem(
                                      value: 'inactive',
                                      child: Text('Inactive'),
                                    ),
                                  ],
                                  onChanged: (value) => setState(
                                    () => _status = value ?? 'active',
                                  ),
                                ),
                              ),
                            ],
                          ),
                          TextField(
                            controller: _programTypeController,
                            decoration: const InputDecoration(
                              labelText: 'Program type',
                            ),
                          ),
                          TextField(
                            controller: _equipmentController,
                            decoration: const InputDecoration(
                              labelText: 'Equipment profile',
                            ),
                          ),
                          Row(
                            children: [
                              Expanded(
                                child: TextField(
                                  controller: _daysController,
                                  keyboardType: TextInputType.number,
                                  decoration: const InputDecoration(
                                    labelText: 'Days/week',
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: TextField(
                                  controller: _durationController,
                                  keyboardType: TextInputType.number,
                                  decoration: const InputDecoration(
                                    labelText: 'Duration weeks',
                                  ),
                                ),
                              ),
                            ],
                          ),
                          TextField(
                            controller: _minutesController,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              labelText: 'Minutes/session',
                            ),
                          ),
                          SwitchListTile.adaptive(
                            value: _featured,
                            onChanged: (value) =>
                                setState(() => _featured = value),
                            title: const Text('Featured in catalog'),
                            contentPadding: EdgeInsets.zero,
                          ),
                          TextField(
                            controller: _descriptionController,
                            maxLines: 3,
                            decoration: const InputDecoration(
                              labelText: 'Description',
                            ),
                          ),
                          TextField(
                            controller: _coachNotesController,
                            maxLines: 3,
                            decoration: const InputDecoration(
                              labelText: 'Coach notes',
                            ),
                          ),
                          TextField(
                            controller: _plansJsonController,
                            maxLines: 18,
                            decoration: const InputDecoration(
                              labelText: 'Plans JSON',
                              alignLabelWithHint: true,
                            ),
                          ),
                          Align(
                            alignment: Alignment.centerLeft,
                            child: OutlinedButton.icon(
                              onPressed: () => setState(
                                () =>
                                    _plansJsonController.text = _samplePlanJson,
                              ),
                              icon: const Icon(Icons.auto_fix_high_rounded),
                              label: const Text('Load sample JSON'),
                            ),
                          ),
                        ],
                      ),
                  ],
                );
              },
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _saving
                        ? null
                        : () => Navigator.of(context).pop(false),
                    child: const Text('Cancel'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: GradientButton(
                    label: _saving ? 'Saving...' : 'Save workout book',
                    icon: Icons.save_rounded,
                    loading: _saving,
                    expanded: true,
                    onPressed: _saving ? null : _save,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _EditorColumn extends StatelessWidget {
  const _EditorColumn({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children:
          children
              .expand((child) => [child, const SizedBox(height: 12)])
              .toList()
            ..removeLast(),
    );
  }
}

class _WorkoutBookBadge extends StatelessWidget {
  const _WorkoutBookBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final color = _badgeColorForLabel(label);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.28)),
      ),
      child: Text(
        label.replaceAll('_', ' '),
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: color,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

Color _badgeColorForLabel(String label) {
  final normalized = label.trim().toLowerCase().replaceAll('_', ' ');

  if ({'active', 'verified', 'public'}.contains(normalized)) {
    return normalized == 'verified' || normalized == 'public'
        ? AppColors.primaryBright
        : AppColors.success;
  }

  if ({'inactive', 'error'}.contains(normalized)) {
    return AppColors.error;
  }

  if ({'attention'}.contains(normalized)) {
    return AppColors.warning;
  }

  if (normalized == 'featured') {
    return const Color(0xFFA78BFA);
  }

  return AppColors.primary;
}

Map<String, dynamic> _normalizePlanJson(Map<String, dynamic> plan) {
  return {
    'name': plan['name'],
    'goal': plan['goal'],
    'difficulty': plan['difficulty'],
    'program_type': plan['program_type'],
    'equipment_profile': plan['equipment_profile'],
    'duration_weeks': plan['duration_weeks'],
    'estimated_session_minutes': plan['estimated_session_minutes'],
    'weekly_schedule': plan['weekly_schedule'] ?? const [],
    'notes': plan['notes'],
    'status': plan['status'] ?? 'active',
    'days': (plan['days'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .map(
          (day) => {
            'day_number': day['day_number'],
            'label': day['label'],
            'focus': day['focus'],
            'notes': day['notes'],
            'exercises': (day['exercises'] as List<dynamic>? ?? const [])
                .map((exercise) => Map<String, dynamic>.from(exercise as Map))
                .map(
                  (exercise) => {
                    'exercise_id': exercise['exercise_id'],
                    'sort_order': exercise['sort_order'],
                    'sets': exercise['sets'],
                    'reps': exercise['reps'],
                    'target_weight': exercise['target_weight'],
                    'rest_seconds': exercise['rest_seconds'],
                    'notes': exercise['notes'],
                  },
                )
                .toList(),
          },
        )
        .toList(),
  };
}
