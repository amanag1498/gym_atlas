import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/loading_state.dart';
import 'member_repository.dart';

class MemberWorkoutBookScreen extends StatefulWidget {
  const MemberWorkoutBookScreen({
    super.key,
    required this.repository,
    required this.onStartPlan,
  });

  final MemberRepository repository;
  final ValueChanged<int?> onStartPlan;

  @override
  State<MemberWorkoutBookScreen> createState() =>
      _MemberWorkoutBookScreenState();
}

class _MemberWorkoutBookScreenState extends State<MemberWorkoutBookScreen>
    with SingleTickerProviderStateMixin {
  static const List<String> _bodyPartOrder = <String>[
    'chest',
    'back',
    'shoulders',
    'arms',
    'core',
    'glutes',
    'quads',
    'hamstrings',
    'calves',
    'full_body',
    'conditioning',
    'mobility',
    'other',
  ];

  static const Map<String, String> _repRangeOptions = <String, String>{
    '5-6': '5-6',
    '6-8': '6-8',
    '8-10': '8-10',
    '8-12': '8-12',
    '10-12': '10-12',
    '12-15': '12-15',
    '15-20': '15-20',
    'custom': 'Custom',
  };

  late final TabController _tabController;
  final TextEditingController _catalogSearchController =
      TextEditingController();
  bool _loading = true;
  bool _saving = false;
  String? _error;
  List<Map<String, dynamic>> _books = const [];
  List<Map<String, dynamic>> _recommendedBooks = const [];
  List<Map<String, dynamic>> _plans = const [];
  List<Map<String, dynamic>> _exercises = const [];
  String? _catalogDifficulty;
  String? _catalogProgramType;
  bool _featuredOnly = false;
  int? _editingPlanId;

  final _nameController = TextEditingController();
  final _goalController = TextEditingController();
  final _durationController = TextEditingController(text: '4');
  final _minutesController = TextEditingController(text: '45');
  String _difficulty = 'beginner';
  final List<_PlanDayDraft> _dayDrafts = <_PlanDayDraft>[_PlanDayDraft()];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _catalogSearchController.dispose();
    _nameController.dispose();
    _goalController.dispose();
    _durationController.dispose();
    _minutesController.dispose();
    for (final day in _dayDrafts) {
      day.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final catalogQuery = <String, dynamic>{
        if (_catalogSearchController.text.trim().isNotEmpty)
          'search': _catalogSearchController.text.trim(),
        if (_catalogDifficulty != null) 'difficulty': _catalogDifficulty,
        if (_catalogProgramType != null) 'program_type': _catalogProgramType,
        if (_featuredOnly) 'featured': true,
      };

      final responses = await Future.wait([
        widget.repository.fetchWorkoutBooks(queryParameters: catalogQuery),
        widget.repository.fetchRecommendedWorkoutBooks(),
        widget.repository.fetchWorkoutPlans(),
        widget.repository.fetchWorkoutExercises(),
      ]);

      _books = (responses[0]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _recommendedBooks = (responses[1]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _plans = (responses[2]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _exercises = (responses[3]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      for (final day in _dayDrafts) {
        for (final exercise in day.exercises) {
          exercise.bodyPart ??= _exerciseGroups.isEmpty
              ? null
              : _exerciseGroups.keys.first;
          exercise.exerciseId ??=
              (_exercisesForBodyPart(exercise.bodyPart).isEmpty
              ? null
              : (_exercisesForBodyPart(exercise.bodyPart).first['id'] as num?)
                    ?.toInt());
          exercise.repPreset = _repPresetFor(exercise.repsController.text);
        }
      }
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Map<String, List<Map<String, dynamic>>> get _exerciseGroups {
    final grouped = <String, List<Map<String, dynamic>>>{};
    for (final exercise in _exercises) {
      final bodyPart = _bodyPartKeyForExercise(exercise);
      grouped
          .putIfAbsent(bodyPart, () => <Map<String, dynamic>>[])
          .add(exercise);
    }

    final ordered = <String, List<Map<String, dynamic>>>{};
    for (final key in _bodyPartOrder) {
      final items = grouped[key];
      if (items != null && items.isNotEmpty) {
        ordered[key] = items;
      }
    }
    return ordered;
  }

  String _bodyPartKeyForExercise(Map<String, dynamic> exercise) {
    final explicit = exercise['body_part']?.toString().trim();
    if (explicit != null && explicit.isNotEmpty) {
      return explicit;
    }

    final muscleGroup = (exercise['muscle_group']?.toString() ?? '')
        .toLowerCase()
        .replaceAll('-', ' ')
        .replaceAll('_', ' ');

    if (muscleGroup.contains('chest')) {
      return 'chest';
    }
    if (muscleGroup.contains('back') ||
        muscleGroup.contains('lat') ||
        muscleGroup.contains('trap')) {
      return 'back';
    }
    if (muscleGroup.contains('shoulder') || muscleGroup.contains('delt')) {
      return 'shoulders';
    }
    if (muscleGroup.contains('bicep') ||
        muscleGroup.contains('tricep') ||
        muscleGroup.contains('arm') ||
        muscleGroup.contains('forearm')) {
      return 'arms';
    }
    if (muscleGroup.contains('core') ||
        muscleGroup.contains('ab') ||
        muscleGroup.contains('oblique')) {
      return 'core';
    }
    if (muscleGroup.contains('glute')) {
      return 'glutes';
    }
    if (muscleGroup.contains('quad') || muscleGroup.contains('leg')) {
      return 'quads';
    }
    if (muscleGroup.contains('hamstring')) {
      return 'hamstrings';
    }
    if (muscleGroup.contains('calf')) {
      return 'calves';
    }
    if (muscleGroup.contains('conditioning') ||
        muscleGroup.contains('cardio')) {
      return 'conditioning';
    }
    if (muscleGroup.contains('mobility') || muscleGroup.contains('recovery')) {
      return 'mobility';
    }
    if (muscleGroup.contains('full body')) {
      return 'full_body';
    }
    return 'other';
  }

  String _bodyPartLabel(String bodyPart) {
    if (bodyPart == 'full_body') {
      return 'Full Body';
    }
    return bodyPart
        .split('_')
        .where((part) => part.isNotEmpty)
        .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
        .join(' ');
  }

  List<Map<String, dynamic>> _exercisesForBodyPart(String? bodyPart) {
    final key =
        bodyPart ??
        (_exerciseGroups.isEmpty ? null : _exerciseGroups.keys.first);
    if (key == null) {
      return const <Map<String, dynamic>>[];
    }
    return _exerciseGroups[key] ?? const <Map<String, dynamic>>[];
  }

  Map<String, dynamic>? _exerciseById(int? id) {
    if (id == null) {
      return null;
    }
    for (final exercise in _exercises) {
      if ((exercise['id'] as num?)?.toInt() == id) {
        return exercise;
      }
    }
    return null;
  }

  String _repPresetFor(String value) {
    final trimmed = value.trim();
    return _repRangeOptions.containsKey(trimmed) ? trimmed : 'custom';
  }

  Widget _buildExerciseBookOverview(BuildContext context) {
    if (_exerciseGroups.isEmpty) {
      return const SizedBox.shrink();
    }

    return _WorkoutBuilderPanel(
      gradient: const [Color(0xFFEAF6FF), Color(0xFFFFFFFF)],
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Exercise book',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Build each workout day from body-part buckets first, then pick the exact movement and set the working range.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: _exerciseGroups.entries.map((entry) {
              return Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.76),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _bodyPartLabel(entry.key),
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${entry.value.length} exercises',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  _PlanExerciseDraft _buildExerciseDraft() {
    final draft = _PlanExerciseDraft();
    if (_exerciseGroups.isNotEmpty) {
      draft.bodyPart = _exerciseGroups.keys.first;
      final options = _exercisesForBodyPart(draft.bodyPart);
      final firstExercise = options.isEmpty ? null : options.first;
      draft.exerciseId = (firstExercise?['id'] as num?)?.toInt();
    }
    return draft;
  }

  Widget _buildExerciseMetaPanel(
    BuildContext context,
    Map<String, dynamic>? exerciseMeta,
  ) {
    if (exerciseMeta == null) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFF7F8F8),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Text(
          'Select an exercise to see its training focus and equipment profile.',
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            color: AppColors.textSecondary,
            fontWeight: FontWeight.w600,
          ),
        ),
      );
    }

    final badges = <String>[
      if ((exerciseMeta['body_part_label']?.toString() ?? '').isNotEmpty)
        exerciseMeta['body_part_label'].toString(),
      if ((exerciseMeta['muscle_group']?.toString() ?? '').isNotEmpty)
        exerciseMeta['muscle_group'].toString(),
      if ((exerciseMeta['equipment']?.toString() ?? '').isNotEmpty)
        exerciseMeta['equipment'].toString(),
      if ((exerciseMeta['difficulty']?.toString() ?? '').isNotEmpty)
        exerciseMeta['difficulty'].toString(),
    ];

    final secondary =
        (exerciseMeta['secondary_muscles'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .where((item) => item.isNotEmpty)
            .toList();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.82),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: badges
                .map(
                  (label) =>
                      StatusBadge(label: label, color: const Color(0xFF60A5FA)),
                )
                .toList(),
          ),
          if (secondary.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              'Secondary muscles: ${secondary.join(', ')}',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          if ((exerciseMeta['instructions']?.toString() ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              exerciseMeta['instructions'].toString(),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                height: 1.35,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _adoptPlan(Map<String, dynamic> template) async {
    final templateId = (template['id'] as num?)?.toInt();
    if (templateId == null) {
      return;
    }

    setState(() => _saving = true);
    try {
      await widget.repository.adoptWorkoutBookPlan(templateId, const {});
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Workout plan added to your library.')),
      );
      await _load();
      _tabController.animateTo(0);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_friendlyError(exception))));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _duplicatePlan(Map<String, dynamic> plan) async {
    final id = (plan['id'] as num?)?.toInt();
    if (id == null) {
      return;
    }

    setState(() => _saving = true);
    try {
      await widget.repository.duplicateWorkoutPlan(id);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Workout plan duplicated into your library.'),
        ),
      );
      await _load();
      _tabController.animateTo(0);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_friendlyError(exception))));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _deletePlan(Map<String, dynamic> plan) async {
    final id = (plan['id'] as num?)?.toInt();
    if (id == null) {
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete workout plan?'),
        content: Text(
          'Remove ${plan['name']?.toString() ?? 'this plan'} from your library?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    setState(() => _saving = true);
    try {
      await widget.repository.deleteWorkoutPlan(id);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Workout plan deleted.')));
      await _load();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_friendlyError(exception))));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  void _beginEditPlan(Map<String, dynamic> plan) {
    _editingPlanId = (plan['id'] as num?)?.toInt();
    _nameController.text = plan['name']?.toString() ?? '';
    _goalController.text = plan['goal']?.toString() ?? '';
    _durationController.text = '${plan['duration_weeks'] ?? 4}';
    _minutesController.text = '${plan['estimated_session_minutes'] ?? 45}';
    _difficulty = plan['difficulty']?.toString() ?? 'beginner';

    for (final day in _dayDrafts) {
      day.dispose();
    }
    _dayDrafts.clear();

    final days = (plan['days'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    for (final day in days) {
      final draft = _PlanDayDraft();
      draft.weekday = (day['day_number'] as num?)?.toInt();
      draft.labelController.text = day['label']?.toString() ?? '';
      draft.focusController.text = day['focus']?.toString() ?? '';
      draft.notesController.text = day['notes']?.toString() ?? '';
      draft.exercises.clear();
      final exercises = (day['exercises'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      for (final exercise in exercises) {
        final exerciseDraft = _PlanExerciseDraft();
        exerciseDraft.exerciseId = (exercise['exercise_id'] as num?)?.toInt();
        final exerciseMeta = Map<String, dynamic>.from(
          exercise['exercise'] as Map? ?? const {},
        );
        exerciseDraft.bodyPart = exerciseMeta.isEmpty
            ? null
            : _bodyPartKeyForExercise(exerciseMeta);
        exerciseDraft.setsController.text = '${exercise['sets'] ?? 3}';
        exerciseDraft.repsController.text =
            exercise['reps']?.toString() ?? '10';
        exerciseDraft.repPreset = _repPresetFor(
          exerciseDraft.repsController.text,
        );
        exerciseDraft.restController.text = '${exercise['rest_seconds'] ?? 60}';
        exerciseDraft.notesController.text =
            exercise['notes']?.toString() ?? '';
        draft.exercises.add(exerciseDraft);
      }
      if (draft.exercises.isEmpty) {
        draft.exercises.add(_buildExerciseDraft());
      }
      _dayDrafts.add(draft);
    }

    if (_dayDrafts.isEmpty) {
      _dayDrafts.add(_PlanDayDraft());
    }

    setState(() {});
    _tabController.animateTo(2);
  }

  Future<void> _savePlan() async {
    if (_nameController.text.trim().isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Add a plan name first.')));
      return;
    }
    if (_dayDrafts.any((day) => day.exercises.isEmpty)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Each workout day needs at least one exercise.'),
        ),
      );
      return;
    }
    if (_dayDrafts.any(
      (day) => day.exercises.any((exercise) => exercise.exerciseId == null),
    )) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Select an exercise for every row before saving.'),
        ),
      );
      return;
    }

    final payload = <String, dynamic>{
      'name': _nameController.text.trim(),
      'goal': _goalController.text.trim().isEmpty
          ? null
          : _goalController.text.trim(),
      'difficulty': _difficulty,
      'duration_weeks': int.tryParse(_durationController.text.trim()) ?? 4,
      'estimated_session_minutes':
          int.tryParse(_minutesController.text.trim()) ?? 45,
      'status': 'active',
      'weekly_schedule': _dayDrafts
          .where((day) => day.weekday != null)
          .map((day) => _weekdayLabel(day.weekday!))
          .toList(),
      'days': _dayDrafts.asMap().entries.map((entry) {
        final day = entry.value;
        return {
          'day_number': day.weekday ?? (entry.key + 1),
          'label': day.labelController.text.trim().isEmpty
              ? 'Day ${entry.key + 1}'
              : day.labelController.text.trim(),
          'focus': day.focusController.text.trim(),
          'notes': day.notesController.text.trim(),
          'exercises': day.exercises.asMap().entries.map((exerciseEntry) {
            final item = exerciseEntry.value;
            return {
              'exercise_id': item.exerciseId,
              'sort_order': exerciseEntry.key + 1,
              'sets': item.sets,
              'reps': item.reps,
              'rest_seconds': item.restSeconds,
              'notes': item.notes,
            };
          }).toList(),
        };
      }).toList(),
    };

    setState(() => _saving = true);
    try {
      if (_editingPlanId == null) {
        await widget.repository.createWorkoutPlan(payload);
      } else {
        await widget.repository.updateWorkoutPlan(_editingPlanId!, payload);
      }

      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _editingPlanId == null
                ? 'Custom workout plan created.'
                : 'Workout plan updated.',
          ),
        ),
      );
      _resetCreator();
      await _load();
      _tabController.animateTo(0);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_friendlyError(exception))));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  void _resetCreator() {
    _editingPlanId = null;
    _nameController.clear();
    _goalController.clear();
    _durationController.text = '4';
    _minutesController.text = '45';
    _difficulty = 'beginner';
    for (final day in _dayDrafts) {
      day.dispose();
    }
    _dayDrafts
      ..clear()
      ..add(_PlanDayDraft());
    setState(() {});
  }

  Future<void> _showPlanPreview({
    required String title,
    required Map<String, dynamic> plan,
    required VoidCallback primaryAction,
    required String primaryLabel,
  }) async {
    var planDetail = Map<String, dynamic>.from(plan);
    final planId = (planDetail['id'] as num?)?.toInt();
    if (planId != null) {
      try {
        final response = await widget.repository.fetchWorkoutPlan(planId);
        final data = Map<String, dynamic>.from(
          response['data'] as Map? ?? const {},
        );
        if (data.isNotEmpty) {
          planDetail = data;
        }
      } catch (_) {
        // Keep the existing list payload if detail refresh is unavailable.
      }
    }

    final days = (planDetail['days'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();

    if (!mounted) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 8),
                  Text(
                    planDetail['goal']?.toString() ??
                        'Structured training plan',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      if (planDetail['difficulty'] != null)
                        StatusBadge(
                          label: '${planDetail['difficulty']}',
                          color: const Color(0xFF34D399),
                        ),
                      if (planDetail['estimated_session_minutes'] != null)
                        StatusBadge(
                          label:
                              '${planDetail['estimated_session_minutes']} min',
                          color: const Color(0xFFF59E0B),
                        ),
                      if (planDetail['total_exercises'] != null)
                        StatusBadge(
                          label: '${planDetail['total_exercises']} exercises',
                          color: const Color(0xFF60A5FA),
                        ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  ...days.map((day) {
                    final exercises =
                        (day['exercises'] as List<dynamic>? ?? const [])
                            .map(
                              (item) => Map<String, dynamic>.from(item as Map),
                            )
                            .toList();
                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(18),
                        color: Colors.white.withValues(alpha: 0.03),
                        border: Border.all(
                          color: AppColors.stroke.withValues(alpha: 0.5),
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            day['label']?.toString() ??
                                'Day ${day['day_number']}',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          if ((day['focus']?.toString() ?? '').isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(day['focus'].toString()),
                          ],
                          const SizedBox(height: 12),
                          ...exercises.map((exercise) {
                            final exerciseMap = Map<String, dynamic>.from(
                              exercise['exercise'] as Map? ?? const {},
                            );
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    width: 28,
                                    height: 28,
                                    alignment: Alignment.center,
                                    decoration: BoxDecoration(
                                      color: AppColors.primary.withValues(
                                        alpha: 0.12,
                                      ),
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: Text(
                                      '${exercise['sort_order'] ?? 1}',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.labelMedium,
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          exerciseMap['name']?.toString() ??
                                              'Exercise',
                                          style: Theme.of(
                                            context,
                                          ).textTheme.titleSmall,
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          '${exercise['sets'] ?? '--'} sets • ${exercise['reps'] ?? '--'} reps • ${exercise['rest_seconds'] ?? 60}s rest',
                                          style: Theme.of(
                                            context,
                                          ).textTheme.bodySmall,
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            );
                          }),
                        ],
                      ),
                    );
                  }),
                  const SizedBox(height: 12),
                  GradientButton(
                    label: primaryLabel,
                    icon: Icons.play_arrow_rounded,
                    expanded: true,
                    onPressed: () {
                      Navigator.of(context).pop();
                      primaryAction();
                    },
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final customPlans = _plans
        .where((plan) => plan['plan_origin'] == 'member_custom')
        .length;
    final adoptedPlans = _plans
        .where((plan) => plan['plan_origin'] == 'catalog_adopted')
        .length;
    final assignedPlans = _plans
        .where((plan) => plan['plan_origin'] == 'trainer_assigned')
        .length;

    return AppGradientScaffold(
      title: 'Workout Book',
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
      body: _loading && _books.isEmpty && _plans.isEmpty
          ? const LoadingState(label: 'Loading workout book...')
          : _error != null && _books.isEmpty && _plans.isEmpty
          ? ErrorStateView(message: _error!, onRetry: _load)
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
                  child: _WorkoutBookFitLifeHeader(
                    planCount: _plans.length,
                    customPlans: customPlans,
                    catalogCount: _books.length,
                    assignedPlans: assignedPlans,
                    adoptedPlans: adoptedPlans,
                    recommendedCount: _recommendedBooks.length,
                    tabController: _tabController,
                  ),
                ),
                Expanded(
                  child: TabBarView(
                    controller: _tabController,
                    children: [
                      _buildLibraryTab(context),
                      _buildCatalogTab(context),
                      _buildBuilderTab(context),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildLibraryTab(BuildContext context) {
    if (_plans.isEmpty) {
      return Center(
        child: _WorkoutBookEmptyStatePanel(
          title: 'No plans in your library',
          message: 'Choose a catalog plan or build your own split.',
          icon: Icons.menu_book_rounded,
          actionLabel: 'Explore catalog',
          onAction: () => _tabController.animateTo(1),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(20),
      itemCount: _plans.length + 1,
      itemBuilder: (context, index) {
        if (index == 0) {
          return _WorkoutBookSectionIntro(
            title: 'Your training shelf',
            subtitle:
                'Start a plan, preview the structure, or tune custom splits.',
            icon: Icons.bookmark_added_rounded,
            gradient: const [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
          );
        }
        final plan = _plans[index - 1];
        final origin = plan['plan_origin']?.toString() ?? 'trainer_assigned';
        final editable = plan['is_member_editable'] == true;
        final focusAreas = (plan['focus_areas'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .toList();

        return _WorkoutBookPlanCard(
          plan: plan,
          originLabel: _originLabel(origin),
          originColor: _originColor(origin),
          editable: editable,
          focusAreas: focusAreas,
          saving: _saving,
          onStart: () => widget.onStartPlan((plan['id'] as num?)?.toInt()),
          onPreview: () => _showPlanPreview(
            title: plan['name']?.toString() ?? 'Workout plan',
            plan: plan,
            primaryAction: () =>
                widget.onStartPlan((plan['id'] as num?)?.toInt()),
            primaryLabel: 'Start with this plan',
          ),
          onDuplicate: () => _duplicatePlan(plan),
          onEdit: () => _beginEditPlan(plan),
          onDelete: () => _deletePlan(plan),
        );
      },
      separatorBuilder: (_, _) => const SizedBox(height: 12),
    );
  }

  Widget _buildCatalogTab(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        _WorkoutBookFilterPanel(
          searchController: _catalogSearchController,
          difficulty: _catalogDifficulty,
          programType: _catalogProgramType,
          featuredOnly: _featuredOnly,
          onSearch: _load,
          onDifficultyChanged: (value) =>
              setState(() => _catalogDifficulty = value),
          onProgramTypeChanged: (value) =>
              setState(() => _catalogProgramType = value),
          onFeaturedChanged: (value) => setState(() => _featuredOnly = value),
          onApply: _load,
        ),
        if (_recommendedBooks.isNotEmpty) ...[
          const SizedBox(height: 16),
          const _WorkoutBookSectionIntro(
            title: 'Recommended for you',
            subtitle: 'Goal-matched plans that are quick to add.',
            icon: Icons.stars_rounded,
            gradient: [Color(0xFFEEA4CE), Color(0xFFC58BF2)],
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 198,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _recommendedBooks.length,
              separatorBuilder: (_, _) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final book = _recommendedBooks[index];
                final firstPlan =
                    (book['plans'] as List<dynamic>? ?? const []).isEmpty
                    ? const <String, dynamic>{}
                    : Map<String, dynamic>.from(
                        (book['plans'] as List).first as Map,
                      );
                return SizedBox(
                  width: 280,
                  child: _WorkoutBookRecommendationCard(
                    book: book,
                    enabled: firstPlan.isNotEmpty,
                    onPreview: firstPlan.isEmpty
                        ? null
                        : () => _showPlanPreview(
                            title:
                                firstPlan['name']?.toString() ??
                                'Recommended plan',
                            plan: firstPlan,
                            primaryAction: () => _adoptPlan(firstPlan),
                            primaryLabel: 'Add to library',
                          ),
                  ),
                );
              },
            ),
          ),
        ],
        const SizedBox(height: 16),
        if (_books.isEmpty)
          const _WorkoutBookEmptyStatePanel(
            title: 'Catalog not available',
            message: 'No platform workout books are published yet.',
            icon: Icons.auto_stories_rounded,
          )
        else ...[
          const _WorkoutBookSectionIntro(
            title: 'Program catalog',
            subtitle: 'Browse structured books and preview weekly plans.',
            icon: Icons.auto_stories_rounded,
            gradient: [Color(0xFFFFC6A5), Color(0xFFFF8D77)],
          ),
          const SizedBox(height: 12),
          ..._books.map((book) {
            final plans = (book['plans'] as List<dynamic>? ?? const [])
                .map((item) => Map<String, dynamic>.from(item as Map))
                .toList();
            final focusAreas =
                (book['focus_areas'] as List<dynamic>? ?? const [])
                    .map((item) => item.toString())
                    .toList();

            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _WorkoutBookCatalogCard(
                book: book,
                plans: plans,
                focusAreas: focusAreas,
                onPreview: (plan) => _showPlanPreview(
                  title: plan['name']?.toString() ?? 'Workout plan',
                  plan: plan,
                  primaryAction: () => _adoptPlan(plan),
                  primaryLabel: 'Add to library',
                ),
              ),
            );
          }),
        ],
      ],
    );
  }

  Widget _buildBuilderTab(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        _WorkoutBuilderPanel(
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
                          _editingPlanId == null
                              ? 'Build your own plan'
                              : 'Edit your custom plan',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          _editingPlanId == null
                              ? 'Create a personal workout split with your preferred days, exercises, set targets, and rest times.'
                              : 'Refine the plan structure, reorder sessions, and tune volume without leaving the workout book.',
                          style: Theme.of(context).textTheme.bodyMedium
                              ?.copyWith(
                                color: AppColors.textSecondary,
                                fontWeight: FontWeight.w600,
                              ),
                        ),
                      ],
                    ),
                  ),
                  if (_editingPlanId != null)
                    OutlinedButton.icon(
                      onPressed: _resetCreator,
                      icon: const Icon(Icons.close_rounded),
                      label: const Text('Cancel edit'),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _nameController,
                decoration: const InputDecoration(labelText: 'Plan name'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _goalController,
                decoration: const InputDecoration(labelText: 'Primary goal'),
              ),
              const SizedBox(height: 12),
              LayoutBuilder(
                builder: (context, constraints) {
                  final compact = constraints.maxWidth < 680;
                  final fields = [
                    DropdownButtonFormField<String>(
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
                      onChanged: (value) =>
                          setState(() => _difficulty = value ?? 'beginner'),
                    ),
                    TextField(
                      controller: _durationController,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Duration (weeks)',
                      ),
                    ),
                    TextField(
                      controller: _minutesController,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Minutes/session',
                      ),
                    ),
                  ];
                  if (compact) {
                    return Column(
                      children: [
                        fields[0],
                        const SizedBox(height: 12),
                        fields[1],
                        const SizedBox(height: 12),
                        fields[2],
                      ],
                    );
                  }
                  return Row(
                    children: [
                      Expanded(child: fields[0]),
                      const SizedBox(width: 12),
                      Expanded(child: fields[1]),
                      const SizedBox(width: 12),
                      Expanded(child: fields[2]),
                    ],
                  );
                },
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _buildExerciseBookOverview(context),
        const SizedBox(height: 12),
        ..._dayDrafts.asMap().entries.map((entry) {
          final index = entry.key;
          final day = entry.value;
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _WorkoutBuilderPanel(
              gradient: const [Color(0xFFFFFFFF), Color(0xFFF7F8FF)],
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Workout day ${index + 1}',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                      ),
                      if (_dayDrafts.length > 1)
                        IconButton(
                          onPressed: () {
                            setState(() {
                              _dayDrafts.removeAt(index).dispose();
                            });
                          },
                          icon: const Icon(Icons.delete_outline_rounded),
                        ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final compact = constraints.maxWidth < 520;
                      if (compact) {
                        return Column(
                          children: [
                            DropdownButtonFormField<int>(
                              initialValue: day.weekday,
                              decoration: const InputDecoration(
                                labelText: 'Day of week',
                              ),
                              items: List.generate(
                                7,
                                (offset) => DropdownMenuItem(
                                  value: offset + 1,
                                  child: Text(_weekdayLabel(offset + 1)),
                                ),
                              ),
                              onChanged: (value) => day.weekday = value,
                            ),
                            const SizedBox(height: 12),
                            TextField(
                              controller: day.labelController,
                              decoration: const InputDecoration(
                                labelText: 'Label',
                              ),
                            ),
                          ],
                        );
                      }
                      return Row(
                        children: [
                          Expanded(
                            child: DropdownButtonFormField<int>(
                              initialValue: day.weekday,
                              decoration: const InputDecoration(
                                labelText: 'Day of week',
                              ),
                              items: List.generate(
                                7,
                                (offset) => DropdownMenuItem(
                                  value: offset + 1,
                                  child: Text(_weekdayLabel(offset + 1)),
                                ),
                              ),
                              onChanged: (value) => day.weekday = value,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextField(
                              controller: day.labelController,
                              decoration: const InputDecoration(
                                labelText: 'Label',
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: day.focusController,
                    decoration: const InputDecoration(labelText: 'Focus'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: day.notesController,
                    decoration: const InputDecoration(labelText: 'Coach note'),
                  ),
                  const SizedBox(height: 16),
                  ...day.exercises.asMap().entries.map((exerciseEntry) {
                    final exerciseIndex = exerciseEntry.key;
                    final exerciseDraft = exerciseEntry.value;
                    final bodyPart =
                        exerciseDraft.bodyPart ??
                        (_exerciseGroups.isEmpty
                            ? null
                            : _exerciseGroups.keys.first);
                    final exerciseOptions = _exercisesForBodyPart(bodyPart);
                    if (exerciseDraft.exerciseId != null &&
                        exerciseOptions.every(
                          (exercise) =>
                              (exercise['id'] as num?)?.toInt() !=
                              exerciseDraft.exerciseId,
                        )) {
                      final selectedExercise = _exerciseById(
                        exerciseDraft.exerciseId,
                      );
                      if (selectedExercise != null) {
                        exerciseDraft.bodyPart = _bodyPartKeyForExercise(
                          selectedExercise,
                        );
                      }
                    }
                    final currentBodyPart =
                        exerciseDraft.bodyPart ??
                        bodyPart ??
                        (_exerciseGroups.isEmpty
                            ? null
                            : _exerciseGroups.keys.first);
                    final currentExerciseOptions = _exercisesForBodyPart(
                      currentBodyPart,
                    );
                    final selectedExerciseMeta = _exerciseById(
                      exerciseDraft.exerciseId,
                    );

                    return Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFFFFF7FB), Color(0xFFF6F8FF)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Exercise ${exerciseIndex + 1}',
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                          const SizedBox(height: 10),
                          LayoutBuilder(
                            builder: (context, constraints) {
                              final compact = constraints.maxWidth < 720;
                              final bodyPartField =
                                  DropdownButtonFormField<String>(
                                    initialValue: currentBodyPart,
                                    decoration: const InputDecoration(
                                      labelText: 'Body part',
                                    ),
                                    items: _exerciseGroups.keys
                                        .map(
                                          (group) => DropdownMenuItem<String>(
                                            value: group,
                                            child: Text(_bodyPartLabel(group)),
                                          ),
                                        )
                                        .toList(),
                                    onChanged: (value) {
                                      setState(() {
                                        exerciseDraft.bodyPart = value;
                                        final nextOptions =
                                            _exercisesForBodyPart(value);
                                        exerciseDraft.exerciseId =
                                            nextOptions.isEmpty
                                            ? null
                                            : (nextOptions.first['id'] as num?)
                                                  ?.toInt();
                                      });
                                    },
                                  );
                              final exerciseField =
                                  DropdownButtonFormField<int>(
                                    initialValue: exerciseDraft.exerciseId,
                                    decoration: const InputDecoration(
                                      labelText: 'Exercise',
                                    ),
                                    items: currentExerciseOptions
                                        .map(
                                          (exercise) => DropdownMenuItem<int>(
                                            value: (exercise['id'] as num?)
                                                ?.toInt(),
                                            child: Text(
                                              exercise['name']?.toString() ??
                                                  'Exercise',
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                        )
                                        .toList(),
                                    onChanged: (value) {
                                      final selectedExercise = _exerciseById(
                                        value,
                                      );
                                      setState(() {
                                        exerciseDraft.exerciseId = value;
                                        if (selectedExercise != null) {
                                          exerciseDraft.bodyPart =
                                              _bodyPartKeyForExercise(
                                                selectedExercise,
                                              );
                                        }
                                      });
                                    },
                                  );
                              if (compact) {
                                return Column(
                                  children: [
                                    bodyPartField,
                                    const SizedBox(height: 12),
                                    exerciseField,
                                  ],
                                );
                              }
                              return Row(
                                children: [
                                  Expanded(child: bodyPartField),
                                  const SizedBox(width: 12),
                                  Expanded(flex: 2, child: exerciseField),
                                ],
                              );
                            },
                          ),
                          const SizedBox(height: 12),
                          _buildExerciseMetaPanel(
                            context,
                            selectedExerciseMeta,
                          ),
                          const SizedBox(height: 12),
                          LayoutBuilder(
                            builder: (context, constraints) {
                              final compact = constraints.maxWidth < 900;
                              final setsField = TextField(
                                controller: exerciseDraft.setsController,
                                keyboardType: TextInputType.number,
                                decoration: const InputDecoration(
                                  labelText: 'Working sets',
                                ),
                              );
                              final rangeField =
                                  DropdownButtonFormField<String>(
                                    initialValue: exerciseDraft.repPreset,
                                    decoration: const InputDecoration(
                                      labelText: 'Rep range',
                                    ),
                                    items: _repRangeOptions.entries
                                        .map(
                                          (entry) => DropdownMenuItem<String>(
                                            value: entry.key,
                                            child: Text(entry.value),
                                          ),
                                        )
                                        .toList(),
                                    onChanged: (value) {
                                      setState(() {
                                        exerciseDraft.repPreset =
                                            value ?? 'custom';
                                        if (exerciseDraft.repPreset !=
                                            'custom') {
                                          exerciseDraft.repsController.text =
                                              exerciseDraft.repPreset;
                                        }
                                      });
                                    },
                                  );
                              final repsField = TextField(
                                controller: exerciseDraft.repsController,
                                decoration: InputDecoration(
                                  labelText: exerciseDraft.repPreset == 'custom'
                                      ? 'Custom reps'
                                      : 'Rep target',
                                ),
                                enabled: exerciseDraft.repPreset == 'custom',
                                onChanged: (value) {
                                  final preset = _repPresetFor(value);
                                  if (preset == 'custom' ||
                                      value != exerciseDraft.repPreset) {
                                    setState(
                                      () => exerciseDraft.repPreset = preset,
                                    );
                                  }
                                },
                              );
                              final restField = TextField(
                                controller: exerciseDraft.restController,
                                keyboardType: TextInputType.number,
                                decoration: const InputDecoration(
                                  labelText: 'Rest seconds',
                                ),
                              );
                              if (compact) {
                                return Column(
                                  children: [
                                    setsField,
                                    const SizedBox(height: 12),
                                    rangeField,
                                    const SizedBox(height: 12),
                                    repsField,
                                    const SizedBox(height: 12),
                                    restField,
                                  ],
                                );
                              }
                              return Row(
                                children: [
                                  Expanded(child: setsField),
                                  const SizedBox(width: 12),
                                  Expanded(child: rangeField),
                                  const SizedBox(width: 12),
                                  Expanded(child: repsField),
                                  const SizedBox(width: 12),
                                  Expanded(child: restField),
                                ],
                              );
                            },
                          ),
                          const SizedBox(height: 12),
                          TextField(
                            controller: exerciseDraft.notesController,
                            decoration: const InputDecoration(
                              labelText: 'Exercise note',
                            ),
                          ),
                          const SizedBox(height: 8),
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton.icon(
                              onPressed: () {
                                setState(() {
                                  day.exercises
                                      .removeAt(exerciseIndex)
                                      .dispose();
                                  if (day.exercises.isEmpty) {
                                    day.exercises.add(_buildExerciseDraft());
                                  }
                                });
                              },
                              icon: const Icon(
                                Icons.remove_circle_outline_rounded,
                              ),
                              label: const Text('Remove exercise'),
                            ),
                          ),
                        ],
                      ),
                    );
                  }),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: () => setState(
                        () => day.exercises.add(_buildExerciseDraft()),
                      ),
                      icon: const Icon(Icons.add_circle_outline_rounded),
                      label: const Text('Add exercise'),
                    ),
                  ),
                ],
              ),
            ),
          );
        }),
        _WorkoutBuilderAddButton(
          label: 'Add workout day',
          icon: Icons.calendar_month_rounded,
          onTap: () => setState(() => _dayDrafts.add(_PlanDayDraft())),
        ),
        const SizedBox(height: 12),
        GradientButton(
          label: _saving
              ? (_editingPlanId == null ? 'Saving plan...' : 'Updating plan...')
              : (_editingPlanId == null
                    ? 'Save custom workout plan'
                    : 'Update workout plan'),
          icon: Icons.save_rounded,
          expanded: true,
          loading: _saving,
          onPressed: _saving ? null : _savePlan,
        ),
      ],
    );
  }
}

class _WorkoutBookSectionIntro extends StatelessWidget {
  const _WorkoutBookSectionIntro({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.gradient,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final List<Color> gradient;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          colors: [
            gradient.first.withValues(alpha: 0.18),
            gradient.last.withValues(alpha: 0.08),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(colors: gradient),
            ),
            child: Icon(icon, color: Colors.white),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _WorkoutBookEmptyStatePanel extends StatelessWidget {
  const _WorkoutBookEmptyStatePanel({
    required this.title,
    required this.message,
    required this.icon,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String message;
  final IconData icon;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(30),
          gradient: const LinearGradient(
            colors: [Color(0xFFFFF7FB), Color(0xFFF5F8FF)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: 0.12),
              blurRadius: 22,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
                ),
              ),
              child: Icon(icon, color: Colors.white, size: 34),
            ),
            const SizedBox(height: 16),
            Text(
              title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 18),
              GradientButton(
                label: actionLabel!,
                icon: Icons.arrow_forward_rounded,
                onPressed: onAction,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _WorkoutBuilderPanel extends StatelessWidget {
  const _WorkoutBuilderPanel({
    required this.child,
    this.gradient = const [Color(0xFFFFFFFF), Color(0xFFFFF7FB)],
  });

  final Widget child;
  final List<Color> gradient;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: LinearGradient(
          colors: gradient,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.10),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: child,
    );
  }
}

class _WorkoutBuilderAddButton extends StatelessWidget {
  const _WorkoutBuilderAddButton({
    required this.label,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 15),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: AppColors.primary.withValues(alpha: 0.14)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: AppColors.primaryBright),
            const SizedBox(width: 8),
            Text(
              label,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: AppColors.primaryBright,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WorkoutBookMetric extends StatelessWidget {
  const _WorkoutBookMetric({
    required this.label,
    required this.value,
    required this.caption,
  });

  final String label;
  final String value;
  final String caption;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        color: Colors.white.withValues(alpha: 0.04),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.45)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.labelLarge),
          const SizedBox(height: 6),
          Text(value, style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 2),
          Text(caption, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _FocusChip extends StatelessWidget {
  const _FocusChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: AppColors.primary.withValues(alpha: 0.12),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: AppColors.primaryBright,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _WorkoutBookPlanCard extends StatelessWidget {
  const _WorkoutBookPlanCard({
    required this.plan,
    required this.originLabel,
    required this.originColor,
    required this.editable,
    required this.focusAreas,
    required this.saving,
    required this.onStart,
    required this.onPreview,
    required this.onDuplicate,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> plan;
  final String originLabel;
  final Color originColor;
  final bool editable;
  final List<String> focusAreas;
  final bool saving;
  final VoidCallback onStart;
  final VoidCallback onPreview;
  final VoidCallback onDuplicate;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final days =
        plan['total_workout_days'] ??
        (plan['days'] is List ? (plan['days'] as List).length : 0);
    final exercises = plan['total_exercises'] ?? 0;
    final minutes = plan['estimated_session_minutes'] ?? 45;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: const LinearGradient(
          colors: [Color(0xFFFFFFFF), Color(0xFFF6F8FF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: originColor.withValues(alpha: 0.16),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    colors: [originColor.withValues(alpha: 0.78), originColor],
                  ),
                ),
                child: const Icon(
                  Icons.fitness_center_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            plan['name']?.toString() ?? 'Workout plan',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                        ),
                        StatusBadge(label: originLabel, color: originColor),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      plan['goal']?.toString() ?? 'Goal not set yet',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _WorkoutBookMiniStat(value: '$days', label: 'days'),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _WorkoutBookMiniStat(
                  value: '$exercises',
                  label: 'moves',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _WorkoutBookMiniStat(value: '$minutes', label: 'min'),
              ),
            ],
          ),
          if (focusAreas.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: focusAreas
                  .take(4)
                  .map((focus) => _FocusChip(label: focus))
                  .toList(),
            ),
          ],
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: GradientButton(
                  label: 'Start',
                  icon: Icons.play_arrow_rounded,
                  onPressed: onStart,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onPreview,
                  icon: const Icon(Icons.visibility_outlined),
                  label: const Text('Preview'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _WorkoutBookActionChip(
                label: 'Duplicate',
                icon: Icons.copy_rounded,
                onTap: saving ? null : onDuplicate,
              ),
              if (editable)
                _WorkoutBookActionChip(
                  label: 'Edit',
                  icon: Icons.edit_rounded,
                  onTap: onEdit,
                ),
              if (editable)
                _WorkoutBookActionChip(
                  label: 'Delete',
                  icon: Icons.delete_outline_rounded,
                  onTap: saving ? null : onDelete,
                  danger: true,
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _WorkoutBookMiniStat extends StatelessWidget {
  const _WorkoutBookMiniStat({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F8F8),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _WorkoutBookActionChip extends StatelessWidget {
  const _WorkoutBookActionChip({
    required this.label,
    required this.icon,
    required this.onTap,
    this.danger = false,
  });

  final String label;
  final IconData icon;
  final VoidCallback? onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? AppColors.error : AppColors.primaryBright;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Opacity(
        opacity: onTap == null ? 0.48 : 1,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: color),
              const SizedBox(width: 6),
              Text(
                label,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: color,
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

class _WorkoutBookFilterPanel extends StatelessWidget {
  const _WorkoutBookFilterPanel({
    required this.searchController,
    required this.difficulty,
    required this.programType,
    required this.featuredOnly,
    required this.onSearch,
    required this.onDifficultyChanged,
    required this.onProgramTypeChanged,
    required this.onFeaturedChanged,
    required this.onApply,
  });

  final TextEditingController searchController;
  final String? difficulty;
  final String? programType;
  final bool featuredOnly;
  final VoidCallback onSearch;
  final ValueChanged<String?> onDifficultyChanged;
  final ValueChanged<String?> onProgramTypeChanged;
  final ValueChanged<bool> onFeaturedChanged;
  final VoidCallback onApply;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(26),
        gradient: const LinearGradient(
          colors: [Color(0xFFEAF6FF), Color(0xFFFFF4FB)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Find your next program',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Search by goal, style, or difficulty.',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: searchController,
            decoration: InputDecoration(
              labelText: 'Search workout books',
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: IconButton(
                onPressed: onSearch,
                icon: const Icon(Icons.arrow_forward_rounded),
              ),
            ),
            onSubmitted: (_) => onSearch(),
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxWidth < 520;
              final fields = [
                DropdownButtonFormField<String?>(
                  initialValue: difficulty,
                  decoration: const InputDecoration(labelText: 'Difficulty'),
                  items: const [
                    DropdownMenuItem<String?>(value: null, child: Text('All')),
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
                  onChanged: onDifficultyChanged,
                ),
                DropdownButtonFormField<String?>(
                  initialValue: programType,
                  decoration: const InputDecoration(labelText: 'Program type'),
                  items: const [
                    DropdownMenuItem<String?>(value: null, child: Text('All')),
                    DropdownMenuItem(
                      value: 'full_body',
                      child: Text('Full Body'),
                    ),
                    DropdownMenuItem(
                      value: 'upper_lower',
                      child: Text('Upper/Lower'),
                    ),
                    DropdownMenuItem(
                      value: 'push_pull_legs',
                      child: Text('Push Pull Legs'),
                    ),
                    DropdownMenuItem(
                      value: 'conditioning_circuit',
                      child: Text('Conditioning'),
                    ),
                    DropdownMenuItem(
                      value: 'home_training',
                      child: Text('Home Training'),
                    ),
                  ],
                  onChanged: onProgramTypeChanged,
                ),
              ];
              if (compact) {
                return Column(
                  children: [fields[0], const SizedBox(height: 10), fields[1]],
                );
              }
              return Row(
                children: [
                  Expanded(child: fields[0]),
                  const SizedBox(width: 12),
                  Expanded(child: fields[1]),
                ],
              );
            },
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: SwitchListTile.adaptive(
                  value: featuredOnly,
                  onChanged: onFeaturedChanged,
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Featured only'),
                ),
              ),
              GradientButton(
                label: 'Apply',
                icon: Icons.tune_rounded,
                onPressed: onApply,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _WorkoutBookRecommendationCard extends StatelessWidget {
  const _WorkoutBookRecommendationCard({
    required this.book,
    required this.enabled,
    required this.onPreview,
  });

  final Map<String, dynamic> book;
  final bool enabled;
  final VoidCallback? onPreview;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: const LinearGradient(
          colors: [Color(0xFFEEA4CE), Color(0xFFC58BF2)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFC58BF2).withValues(alpha: 0.24),
            blurRadius: 20,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const StatusBadge(label: 'Recommended', color: Colors.white),
          const Spacer(),
          Text(
            book['name']?.toString() ?? 'Workout book',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            book['goal']?.toString() ?? 'Goal aligned training',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: Colors.white.withValues(alpha: 0.84),
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          _WorkoutBookGradientGhostButton(
            label: 'Preview',
            icon: Icons.visibility_outlined,
            enabled: enabled,
            onTap: onPreview,
          ),
        ],
      ),
    );
  }
}

class _WorkoutBookCatalogCard extends StatelessWidget {
  const _WorkoutBookCatalogCard({
    required this.book,
    required this.plans,
    required this.focusAreas,
    required this.onPreview,
  });

  final Map<String, dynamic> book;
  final List<Map<String, dynamic>> plans;
  final List<String> focusAreas;
  final ValueChanged<Map<String, dynamic>> onPreview;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  book['name']?.toString() ?? 'Workout book',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              if (book['is_featured'] == true)
                const StatusBadge(label: 'Featured', color: Color(0xFFFF8D77)),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            book['description']?.toString() ?? 'No description available.',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (book['difficulty'] != null)
                StatusBadge(
                  label: '${book['difficulty']}',
                  color: const Color(0xFF34D399),
                ),
              if (book['days_per_week'] != null)
                StatusBadge(
                  label: '${book['days_per_week']} days/week',
                  color: const Color(0xFFA78BFA),
                ),
              if (book['total_exercises'] != null)
                StatusBadge(
                  label: '${book['total_exercises']} exercises',
                  color: const Color(0xFFF59E0B),
                ),
            ],
          ),
          if (focusAreas.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: focusAreas
                  .take(5)
                  .map((focus) => _FocusChip(label: focus))
                  .toList(),
            ),
          ],
          const SizedBox(height: 16),
          ...plans.map(
            (plan) => Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFFF7F8F8), Color(0xFFFFFFFF)],
                ),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: LinearGradient(
                        colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
                      ),
                    ),
                    child: const Icon(
                      Icons.play_arrow_rounded,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          plan['name']?.toString() ?? 'Plan',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          '${plan['total_workout_days'] ?? '--'} days • ${plan['estimated_session_minutes'] ?? '--'} min',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: AppColors.textSecondary),
                        ),
                      ],
                    ),
                  ),
                  TextButton(
                    onPressed: () => onPreview(plan),
                    child: const Text('Preview'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WorkoutBookGradientGhostButton extends StatelessWidget {
  const _WorkoutBookGradientGhostButton({
    required this.label,
    required this.icon,
    required this.enabled,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool enabled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(999),
      child: Opacity(
        opacity: enabled ? 1 : 0.5,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 11),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(999),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 18, color: AppColors.primaryBright),
              const SizedBox(width: 8),
              Text(
                label,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: AppColors.primaryBright,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PlanDayDraft {
  final TextEditingController labelController = TextEditingController();
  final TextEditingController focusController = TextEditingController();
  final TextEditingController notesController = TextEditingController();
  int? weekday;
  final List<_PlanExerciseDraft> exercises = <_PlanExerciseDraft>[
    _PlanExerciseDraft(),
  ];

  void dispose() {
    labelController.dispose();
    focusController.dispose();
    notesController.dispose();
    for (final exercise in exercises) {
      exercise.dispose();
    }
  }
}

class _PlanExerciseDraft {
  int? exerciseId;
  String? bodyPart;
  String repPreset = '8-12';
  final TextEditingController setsController = TextEditingController(text: '3');
  final TextEditingController repsController = TextEditingController(
    text: '8-12',
  );
  final TextEditingController restController = TextEditingController(
    text: '60',
  );
  final TextEditingController notesController = TextEditingController();

  int get sets => int.tryParse(setsController.text.trim()) ?? 3;
  String get reps =>
      repsController.text.trim().isEmpty ? '10' : repsController.text.trim();
  int get restSeconds => int.tryParse(restController.text.trim()) ?? 60;
  String? get notes =>
      notesController.text.trim().isEmpty ? null : notesController.text.trim();

  void dispose() {
    setsController.dispose();
    repsController.dispose();
    restController.dispose();
    notesController.dispose();
  }
}

class _WorkoutBookFitLifeHeader extends StatelessWidget {
  const _WorkoutBookFitLifeHeader({
    required this.planCount,
    required this.customPlans,
    required this.catalogCount,
    required this.assignedPlans,
    required this.adoptedPlans,
    required this.recommendedCount,
    required this.tabController,
  });

  final int planCount;
  final int customPlans;
  final int catalogCount;
  final int assignedPlans;
  final int adoptedPlans;
  final int recommendedCount;
  final TabController tabController;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        gradient: const LinearGradient(
          colors: [Color(0xFFFFF7FB), Color(0xFFF5F8FF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.14),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
                  ),
                ),
                child: const Icon(
                  Icons.library_books_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Workout Book',
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            color: AppColors.textPrimary,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    Text(
                      'Plan, clone, and build your training split.',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: _WorkoutBookMetric(
                  label: 'Library',
                  value: '$planCount',
                  caption: 'saved plans',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _WorkoutBookMetric(
                  label: 'Custom',
                  value: '$customPlans',
                  caption: 'editable',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _WorkoutBookMetric(
                  label: 'Catalog',
                  value: '$catalogCount',
                  caption: 'books live',
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              if (assignedPlans > 0)
                StatusBadge(
                  label: '$assignedPlans coach-assigned',
                  color: const Color(0xFFA78BFA),
                ),
              if (adoptedPlans > 0)
                StatusBadge(
                  label: '$adoptedPlans catalog adopted',
                  color: const Color(0xFF60A5FA),
                ),
              if (recommendedCount > 0)
                StatusBadge(
                  label: '$recommendedCount recommended',
                  color: const Color(0xFF34D399),
                ),
            ],
          ),
          const SizedBox(height: 16),
          _WorkoutBookTabSlider(controller: tabController),
        ],
      ),
    );
  }
}

class _WorkoutBookTabSlider extends StatefulWidget {
  const _WorkoutBookTabSlider({required this.controller});

  final TabController controller;

  @override
  State<_WorkoutBookTabSlider> createState() => _WorkoutBookTabSliderState();
}

class _WorkoutBookTabSliderState extends State<_WorkoutBookTabSlider> {
  static const _items = [
    (label: 'My Plans', icon: Icons.bookmark_added_rounded),
    (label: 'Catalog', icon: Icons.auto_stories_rounded),
    (label: 'Builder', icon: Icons.add_task_rounded),
  ];

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_handleTabChange);
  }

  @override
  void didUpdateWidget(covariant _WorkoutBookTabSlider oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_handleTabChange);
      widget.controller.addListener(_handleTabChange);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_handleTabChange);
    super.dispose();
  }

  void _handleTabChange() {
    if (mounted) {
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.82),
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.10),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 355;
          return Row(
            children: [
              for (var index = 0; index < _items.length; index++)
                Expanded(
                  child: _WorkoutBookTabPill(
                    label: _items[index].label,
                    icon: _items[index].icon,
                    active: widget.controller.index == index,
                    compact: compact,
                    onTap: () => widget.controller.animateTo(index),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _WorkoutBookTabPill extends StatelessWidget {
  const _WorkoutBookTabPill({
    required this.label,
    required this.icon,
    required this.active,
    required this.compact,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool active;
  final bool compact;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 240),
        curve: Curves.easeOutCubic,
        margin: const EdgeInsets.symmetric(horizontal: 2),
        padding: EdgeInsets.symmetric(
          horizontal: compact ? 8 : 12,
          vertical: 11,
        ),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(999),
          gradient: active
              ? const LinearGradient(
                  colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
              : null,
          color: active ? null : Colors.transparent,
          boxShadow: active
              ? [
                  BoxShadow(
                    color: const Color(0xFF92A3FD).withValues(alpha: 0.24),
                    blurRadius: 14,
                    offset: const Offset(0, 8),
                  ),
                ]
              : null,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: compact ? 16 : 18,
              color: active ? Colors.white : AppColors.textSecondary,
            ),
            if (!compact || active) ...[
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: active ? Colors.white : AppColors.textSecondary,
                    fontWeight: active ? FontWeight.w900 : FontWeight.w700,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

String _originLabel(String origin) {
  switch (origin) {
    case 'catalog_adopted':
      return 'Catalog';
    case 'member_custom':
      return 'Custom';
    default:
      return 'Assigned';
  }
}

Color _originColor(String origin) {
  switch (origin) {
    case 'catalog_adopted':
      return const Color(0xFF60A5FA);
    case 'member_custom':
      return const Color(0xFF34D399);
    default:
      return const Color(0xFFA78BFA);
  }
}

String _friendlyError(Object exception) {
  final message = exception.toString();
  if (message.contains('422')) {
    return 'Please review the workout plan details and try again.';
  }
  return message;
}

String _weekdayLabel(int value) {
  const labels = <int, String>{
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
    7: 'Sunday',
  };

  return labels[value] ?? 'Day $value';
}
