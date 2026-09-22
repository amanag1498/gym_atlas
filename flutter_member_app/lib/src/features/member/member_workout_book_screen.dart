import 'dart:async';

import 'package:flutter/material.dart';
import 'package:gym_flutter_core/workout_builder_validation.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/user_facing_error.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import '../../core/pagination.dart';
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
  final TextEditingController _equipmentProfileNameController =
      TextEditingController();
  final TextEditingController _equipmentProfileEquipmentController =
      TextEditingController();
  bool _loading = true;
  bool _saving = false;
  bool _loadingMoreBooks = false;
  bool _loadingMorePlans = false;
  bool _loadingExercises = false;
  int _exerciseSearchGeneration = 0;
  int _activeTabIndex = 0;
  int? _sharingPlanId;
  String? _error;
  List<Map<String, dynamic>> _books = const [];
  List<Map<String, dynamic>> _recommendedBooks = const [];
  List<Map<String, dynamic>> _plans = const [];
  List<Map<String, dynamic>> _exercises = const [];
  ApiPagination _bookPage = const ApiPagination.singlePage();
  ApiPagination _planPage = const ApiPagination.singlePage();
  ApiPagination _exercisePage = const ApiPagination.singlePage();
  List<Map<String, dynamic>> _equipmentProfiles = const [];
  String _exerciseCatalogView = 'all';
  int? _selectedEquipmentProfileId;

  String? _catalogDifficulty;
  String? _catalogProgramType;
  bool _featuredOnly = false;
  int? _editingPlanId;

  final _nameController = TextEditingController();
  final _goalController = TextEditingController();
  final _durationController = TextEditingController(text: '4');
  final _minutesController = TextEditingController(text: '45');
  final _planNotesController = TextEditingController();
  String _planProgressionPolicy = 'off';
  final _planProgressionMinRepsController = TextEditingController(text: '8');
  final _planProgressionMaxRepsController = TextEditingController(text: '12');
  final _planProgressionIncrementController = TextEditingController(
    text: '2.5',
  );
  final _planDeloadAfterController = TextEditingController(text: '3');
  final _planDeloadPercentController = TextEditingController(text: '10');
  final _exerciseSearchController = TextEditingController();
  final _exercisePickerTextController = TextEditingController();
  final Map<int, Map<String, dynamic>> _savedExerciseMetadata = {};
  Timer? _exerciseSearchDebounce;
  final _setsController = TextEditingController(text: '4');
  final _repsController = TextEditingController(text: '10');
  final _targetWeightController = TextEditingController();
  final _restController = TextEditingController(text: '60');
  final _durationSecondsController = TextEditingController();
  final _distanceMetersController = TextEditingController();
  final _speedKphController = TextEditingController();
  final _paceSecondsController = TextEditingController();
  final _exerciseNotesController = TextEditingController();
  final _groupKeyController = TextEditingController();
  final _groupRoundsController = TextEditingController(text: '3');
  final _transitionSecondsController = TextEditingController(text: '15');
  String _groupType = 'superset';
  String _exerciseProgressionPolicy = 'off';
  final _exerciseProgressionMinRepsController = TextEditingController(
    text: '8',
  );
  final _exerciseProgressionMaxRepsController = TextEditingController(
    text: '12',
  );
  final _exerciseProgressionIncrementController = TextEditingController(
    text: '2.5',
  );
  final _exerciseProgressionDeloadAfterController = TextEditingController(
    text: '3',
  );
  final _exerciseProgressionDeloadPercentController = TextEditingController(
    text: '10',
  );
  String _trackingMode = 'reps';
  String _difficulty = 'intermediate';
  final List<_PlanDayDraft> _dayDrafts = <_PlanDayDraft>[];
  int _selectedBuilderDayIndex = 0;
  int? _selectedBuilderExerciseId;
  static const int _loadMoreExercisePickerValue = -1;

  static const Map<int, String> _builderWeekdays = <int, String>{
    1: 'Mon',
    2: 'Tue',
    3: 'Wed',
    4: 'Thu',
    5: 'Fri',
    6: 'Sat',
    7: 'Sun',
  };

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _tabController.addListener(_handleTabChange);
    _initializeDefaultBuilderDays();
    _exerciseSearchController.addListener(_scheduleExerciseSearch);
    _load();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _catalogSearchController.dispose();
    _equipmentProfileNameController.dispose();
    _equipmentProfileEquipmentController.dispose();
    _nameController.dispose();
    _goalController.dispose();
    _durationController.dispose();
    _minutesController.dispose();
    _planNotesController.dispose();
    _planProgressionMinRepsController.dispose();
    _planProgressionMaxRepsController.dispose();
    _planProgressionIncrementController.dispose();
    _planDeloadAfterController.dispose();
    _planDeloadPercentController.dispose();
    _exerciseSearchController.dispose();
    _exercisePickerTextController.dispose();
    _exerciseSearchDebounce?.cancel();
    _setsController.dispose();
    _repsController.dispose();
    _targetWeightController.dispose();
    _restController.dispose();
    _durationSecondsController.dispose();
    _distanceMetersController.dispose();
    _speedKphController.dispose();
    _paceSecondsController.dispose();
    _exerciseNotesController.dispose();
    _groupKeyController.dispose();
    _groupRoundsController.dispose();
    _transitionSecondsController.dispose();
    _exerciseProgressionMinRepsController.dispose();
    _exerciseProgressionMaxRepsController.dispose();
    _exerciseProgressionIncrementController.dispose();
    _exerciseProgressionDeloadAfterController.dispose();
    _exerciseProgressionDeloadPercentController.dispose();
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
        'page': 1,
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
        widget.repository.fetchWorkoutExercises(
          queryParameters: _exerciseQuery(),
        ),
        widget.repository.fetchEquipmentProfiles(),
      ]);

      _books = apiPageItems(responses[0]);
      _recommendedBooks = apiPageItems(responses[1]);
      _plans = apiPageItems(responses[2]);
      _exercises = apiPageItems(responses[3]);
      _bookPage = ApiPagination.fromResponse(responses[0]);
      _planPage = ApiPagination.fromResponse(responses[2]);
      _exercisePage = ApiPagination.fromResponse(responses[3]);
      final equipmentData = responses[4]['data'];
      if (equipmentData is Map) {
        _equipmentProfiles = (equipmentData['profiles'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
        final defaultProfile = _equipmentProfiles
            .where((profile) => profile['is_default'] == true)
            .firstOrNull;
        _selectedEquipmentProfileId ??= (defaultProfile?['id'] as num?)
            ?.toInt();
        if (_selectedEquipmentProfileId != null) {
          final filteredExerciseResponse = await widget.repository
              .fetchWorkoutExercises(queryParameters: _exerciseQuery());
          _exercises = apiPageItems(filteredExerciseResponse);
          _exercisePage = ApiPagination.fromResponse(filteredExerciseResponse);
        }
      }
      for (final day in _dayDrafts) {
        for (final exercise in day.exercises) {
          exercise.bodyPart ??= _exerciseGroups.isEmpty
              ? null
              : _exerciseGroups.keys.first;
          exercise.repPreset = _repPresetFor(exercise.repsController.text);
        }
      }
    } catch (exception) {
      _error = _friendlyError(exception);
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _loadMoreBooks() async {
    if (_loadingMoreBooks || !_bookPage.hasMore) return;
    setState(() => _loadingMoreBooks = true);
    try {
      final catalogQuery = <String, dynamic>{
        if (_catalogSearchController.text.trim().isNotEmpty)
          'search': _catalogSearchController.text.trim(),
        if (_catalogDifficulty != null) 'difficulty': _catalogDifficulty,
        if (_catalogProgramType != null) 'program_type': _catalogProgramType,
        if (_featuredOnly) 'featured': true,
      };
      final response = await widget.repository.fetchWorkoutBooks(
        queryParameters: {...catalogQuery, 'page': _bookPage.nextPage},
      );
      _books = mergeApiPageItems(_books, apiPageItems(response));
      _bookPage = ApiPagination.fromResponse(response);
    } catch (exception) {
      _showLoadMoreError(exception);
    } finally {
      if (mounted) setState(() => _loadingMoreBooks = false);
    }
  }

  Future<void> _loadMorePlans() async {
    if (_loadingMorePlans || !_planPage.hasMore) return;
    setState(() => _loadingMorePlans = true);
    try {
      final response = await widget.repository.fetchWorkoutPlans(
        page: _planPage.nextPage,
      );
      _plans = mergeApiPageItems(_plans, apiPageItems(response));
      _planPage = ApiPagination.fromResponse(response);
    } catch (exception) {
      _showLoadMoreError(exception);
    } finally {
      if (mounted) setState(() => _loadingMorePlans = false);
    }
  }

  Future<void> _loadMoreExercises() async {
    if (_loadingExercises || !_exercisePage.hasMore) return;
    final generation = _exerciseSearchGeneration;
    final page = _exercisePage.nextPage;
    final query = _exerciseQuery();
    setState(() => _loadingExercises = true);
    try {
      final response = await widget.repository.fetchWorkoutExercises(
        queryParameters: {...query, 'page': page},
      );
      if (!mounted || generation != _exerciseSearchGeneration) return;
      setState(() {
        _exercises = mergeApiPageItems(_exercises, apiPageItems(response));
        _exercisePage = ApiPagination.fromResponse(response);
      });
    } catch (exception) {
      if (generation == _exerciseSearchGeneration) {
        _showLoadMoreError(exception);
      }
    } finally {
      if (mounted && generation == _exerciseSearchGeneration) {
        setState(() => _loadingExercises = false);
      }
    }
  }

  void _showLoadMoreError(Object exception) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(_friendlyError(exception))));
  }

  void _resetCatalogFilters() {
    setState(() {
      _catalogSearchController.clear();
      _catalogDifficulty = null;
      _catalogProgramType = null;
      _featuredOnly = false;
    });
    _load();
  }

  bool get _catalogHasFilters =>
      _catalogSearchController.text.trim().isNotEmpty ||
      _catalogDifficulty != null ||
      _catalogProgramType != null ||
      _featuredOnly;

  Widget _loadMoreButton({
    required String label,
    required bool loading,
    required VoidCallback onPressed,
  }) => Center(
    child: OutlinedButton.icon(
      onPressed: loading ? null : onPressed,
      icon: loading
          ? const SizedBox.square(
              dimension: 16,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : const Icon(Icons.expand_more_rounded),
      label: Text(loading ? 'Loading...' : label),
    ),
  );

  void _scheduleExerciseSearch() {
    _exerciseSearchDebounce?.cancel();
    _exerciseSearchDebounce = Timer(
      const Duration(milliseconds: 350),
      _searchExercises,
    );
  }

  Future<void> _searchExercises() async {
    final requestGeneration = ++_exerciseSearchGeneration;
    setState(() => _loadingExercises = true);
    try {
      final response = await widget.repository.fetchWorkoutExercises(
        queryParameters: _exerciseQuery(),
      );
      if (!mounted || requestGeneration != _exerciseSearchGeneration) return;

      setState(() {
        _exercises = apiPageItems(response);
        _exercisePage = ApiPagination.fromResponse(response);
        if (_exerciseById(_selectedBuilderExerciseId) == null) {
          _selectedBuilderExerciseId = null;
          _exercisePickerTextController.clear();
        }
      });
    } catch (exception) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_friendlyError(exception))));
    } finally {
      if (mounted && requestGeneration == _exerciseSearchGeneration) {
        setState(() => _loadingExercises = false);
      }
    }
  }

  void _handleTabChange() {
    if (!mounted || _activeTabIndex == _tabController.index) return;
    setState(() => _activeTabIndex = _tabController.index);
  }

  Map<String, dynamic> _exerciseQuery() => <String, dynamic>{
    'page': 1,
    'per_page': 100,
    'locale': WidgetsBinding.instance.platformDispatcher.locale.languageCode,
    if (_exerciseSearchController.text.trim().isNotEmpty)
      'search': _exerciseSearchController.text.trim(),
    if (_exerciseCatalogView == 'favourites') 'favourites': true,
    if (_exerciseCatalogView == 'recent') 'recent': true,
    if (_selectedEquipmentProfileId != null)
      'equipment_profile_id': _selectedEquipmentProfileId,
  };

  Future<void> _toggleExerciseFavourite(Map<String, dynamic> exercise) async {
    final id = (exercise['id'] as num?)?.toInt();
    if (id == null) return;
    final wasFavourite = exercise['is_favourite'] == true;
    try {
      if (wasFavourite) {
        await widget.repository.unfavouriteWorkoutExercise(id);
      } else {
        await widget.repository.favouriteWorkoutExercise(id);
      }
      if (!mounted) return;
      setState(() => exercise['is_favourite'] = !wasFavourite);
      if (wasFavourite && _exerciseCatalogView == 'favourites') {
        await _searchExercises();
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userFacingError(exception))));
      }
    }
  }

  Future<void> _showExerciseDetails(Map<String, dynamic> exercise) async {
    final id = (exercise['id'] as num?)?.toInt();
    if (id == null) return;
    try {
      final response = await widget.repository.fetchWorkoutExercise(
        id,
        equipmentProfileId: _selectedEquipmentProfileId,
      );
      if (!mounted) return;
      final data = response['data'];
      if (data is! Map) return;
      final detail = data['exercise'] is Map
          ? Map<String, dynamic>.from(data['exercise'] as Map)
          : exercise;
      final history = data['recent_history'] as List? ?? const [];
      final substitutions = data['substitutions'] as List? ?? const [];
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (context) => SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _exerciseDisplayName(detail),
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                _buildExerciseMetaPanel(context, detail, showActions: false),
                const SizedBox(height: 16),
                Text('Recent workouts: ${history.length}'),
                if (data['personal_record'] != null)
                  const Padding(
                    padding: EdgeInsets.only(top: 6),
                    child: Text('Personal record available'),
                  ),
                if (substitutions.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text(
                    'Curated substitutions',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  ...substitutions.whereType<Map>().map((item) {
                    final substitute = item['exercise'];
                    final name = substitute is Map
                        ? _exerciseDisplayName(
                            Map<String, dynamic>.from(substitute),
                          )
                        : 'Exercise';
                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(name),
                      subtitle: Text(item['reason']?.toString() ?? 'Curated'),
                      trailing: item['requires_trainer_approval'] == true
                          ? const Icon(Icons.verified_user_outlined)
                          : null,
                    );
                  }),
                  Text(
                    data['substitution_notice']?.toString() ?? '',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ],
            ),
          ),
        ),
      );
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userFacingError(exception))));
      }
    }
  }

  Future<void> _showEquipmentProfileSheet() async {
    _equipmentProfileNameController.clear();
    _equipmentProfileEquipmentController.clear();
    var presetKey = 'bodyweight_only';
    var isDefault = true;
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              20,
              20,
              20 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Equipment profile',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _equipmentProfileNameController,
                    decoration: _memberWorkoutInputDecoration(
                      'Profile name',
                      icon: Icons.badge_outlined,
                    ),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: presetKey,
                    decoration: _memberWorkoutInputDecoration(
                      'Setup',
                      icon: Icons.home_repair_service_outlined,
                    ),
                    items: const [
                      DropdownMenuItem(
                        value: 'commercial_gym',
                        child: Text('Commercial gym'),
                      ),
                      DropdownMenuItem(
                        value: 'bodyweight_only',
                        child: Text('Bodyweight only'),
                      ),
                      DropdownMenuItem(
                        value: 'dumbbells_and_bench',
                        child: Text('Dumbbells and bench'),
                      ),
                      DropdownMenuItem(
                        value: 'resistance_bands',
                        child: Text('Resistance bands'),
                      ),
                      DropdownMenuItem(
                        value: 'home_gym',
                        child: Text('Home gym'),
                      ),
                      DropdownMenuItem(value: 'custom', child: Text('Custom')),
                    ],
                    onChanged: (value) =>
                        setSheetState(() => presetKey = value ?? presetKey),
                  ),
                  if (presetKey == 'custom') ...[
                    const SizedBox(height: 12),
                    TextField(
                      controller: _equipmentProfileEquipmentController,
                      decoration: _memberWorkoutInputDecoration(
                        'Equipment, comma separated',
                        icon: Icons.edit_note_rounded,
                      ),
                    ),
                  ],
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Use by default'),
                    value: isDefault,
                    onChanged: (value) =>
                        setSheetState(() => isDefault = value),
                  ),
                  FilledButton.icon(
                    onPressed: () async {
                      final name = _equipmentProfileNameController.text.trim();
                      if (name.isEmpty) return;
                      try {
                        await widget.repository.saveEquipmentProfile({
                          'name': name,
                          'preset_key': presetKey,
                          'equipment': presetKey == 'custom'
                              ? _equipmentProfileEquipmentController.text
                                    .split(',')
                                    .map((item) => item.trim())
                                    .where((item) => item.isNotEmpty)
                                    .toList()
                              : <String>[],
                          'is_default': isDefault,
                        });
                        if (context.mounted) Navigator.pop(context, true);
                      } catch (exception) {
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text(userFacingError(exception))),
                          );
                        }
                      }
                    },
                    icon: const Icon(Icons.save_outlined),
                    label: const Text('Save profile'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    if (saved == true && mounted) {
      final response = await widget.repository.fetchEquipmentProfiles();
      final data = response['data'];
      final profiles = data is Map
          ? (data['profiles'] as List? ?? const [])
                .whereType<Map>()
                .map((item) => Map<String, dynamic>.from(item))
                .toList()
          : <Map<String, dynamic>>[];
      setState(() {
        _equipmentProfiles = profiles;
        _selectedEquipmentProfileId =
            (profiles
                        .where((profile) => profile['is_default'] == true)
                        .firstOrNull?['id']
                    as num?)
                ?.toInt();
      });
      await _searchExercises();
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

  String _exerciseDisplayName(Map<String, dynamic> exercise) {
    final localized = exercise['localized_name']?.toString().trim() ?? '';
    if (localized.isNotEmpty) return localized;

    final canonical = exercise['name']?.toString().trim() ?? '';
    return canonical.isNotEmpty ? canonical : 'Exercise';
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
    return _savedExerciseMetadata[id];
  }

  String _exercisePickerLabelForSelection() {
    final exercise = _exerciseById(_selectedBuilderExerciseId);
    if (exercise == null) return 'Choose an exercise';
    return '${_exerciseDisplayName(exercise)} • ${_bodyPartLabel(_bodyPartKeyForExercise(exercise))}';
  }

  String _repPresetFor(String value) {
    final trimmed = value.trim();
    return _repRangeOptions.containsKey(trimmed) ? trimmed : 'custom';
  }

  void _initializeDefaultBuilderDays() {
    _dayDrafts.addAll(<_PlanDayDraft>[
      _newBuilderDay(1),
      _newBuilderDay(3),
      _newBuilderDay(5),
    ]);
  }

  _PlanDayDraft _newBuilderDay(int weekday) {
    final draft = _PlanDayDraft(weekday: weekday);
    draft.labelController.text = _weekdayLabel(weekday);
    return draft;
  }

  _PlanDayDraft get _selectedBuilderDay {
    if (_dayDrafts.isEmpty) {
      _dayDrafts.add(_newBuilderDay(1));
    }
    _selectedBuilderDayIndex = _selectedBuilderDayIndex.clamp(
      0,
      _dayDrafts.length - 1,
    );
    return _dayDrafts[_selectedBuilderDayIndex];
  }

  void _toggleBuilderWeekday(int weekday) {
    final existingIndex = _dayDrafts.indexWhere(
      (day) => day.weekday == weekday,
    );
    if (existingIndex >= 0 && _dayDrafts.length == 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Keep at least one training day.')),
      );
      return;
    }
    setState(() {
      if (existingIndex >= 0) {
        _dayDrafts.removeAt(existingIndex).dispose();
      } else {
        _dayDrafts.add(_newBuilderDay(weekday));
        _dayDrafts.sort((a, b) => (a.weekday ?? 7).compareTo(b.weekday ?? 7));
      }
      _selectedBuilderDayIndex = _selectedBuilderDayIndex.clamp(
        0,
        _dayDrafts.length - 1,
      );
    });
  }

  void _addExerciseToBuilderDay() {
    final selectedExercise = _exerciseById(_selectedBuilderExerciseId);
    final sets = int.tryParse(_setsController.text.trim());
    if (selectedExercise == null || sets == null || sets < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select an exercise and valid sets.')),
      );
      return;
    }

    final groupKey = _groupKeyController.text.trim().isEmpty
        ? null
        : _groupKeyController.text.trim();
    final groupRounds = int.tryParse(_groupRoundsController.text.trim());
    final transitionSeconds = int.tryParse(
      _transitionSecondsController.text.trim(),
    );
    if (groupKey != null &&
        (groupRounds == null ||
            groupRounds < 1 ||
            groupRounds > 20 ||
            transitionSeconds == null ||
            transitionSeconds < 0 ||
            transitionSeconds > 3600)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Enter 1–20 rounds and 0–3600 transition seconds.'),
        ),
      );
      return;
    }
    final groupOrder = groupKey == null
        ? null
        : _selectedBuilderDay.exercises
                  .where((item) => item.groupKey == groupKey)
                  .length +
              1;

    final draft = _PlanExerciseDraft();
    draft.exerciseId = (selectedExercise['id'] as num?)?.toInt();
    draft.bodyPart = _bodyPartKeyForExercise(selectedExercise);
    draft.setsController.text = sets.toString();
    draft.repsController.text = _repsController.text.trim();
    draft.repPreset = _repPresetFor(draft.repsController.text);
    draft.targetWeightController.text = _targetWeightController.text.trim();
    draft.restController.text = _restController.text.trim();
    draft.trackingMode = _trackingMode;
    draft.durationSecondsController.text = _durationSecondsController.text
        .trim();
    draft.distanceMetersController.text = _distanceMetersController.text.trim();
    draft.speedKphController.text = _speedKphController.text.trim();
    draft.paceSecondsController.text = _paceSecondsController.text.trim();
    draft.isPerSide = selectedExercise['is_per_side'] == true;
    draft.isBodyweight = selectedExercise['is_bodyweight'] == true;
    draft.groupKey = groupKey;
    draft.groupType = groupKey == null ? null : _groupType;
    draft.groupOrder = groupOrder;
    draft.groupRounds = groupKey == null ? null : groupRounds;
    draft.transitionSeconds = groupKey == null ? null : transitionSeconds;
    draft.progressionPolicy = _trackingMode == 'reps'
        ? _exerciseProgressionPolicy
        : 'off';
    draft.progressionMinReps = int.tryParse(
      _exerciseProgressionMinRepsController.text.trim(),
    );
    draft.progressionMaxReps = int.tryParse(
      _exerciseProgressionMaxRepsController.text.trim(),
    );
    draft.progressionIncrementKg = double.tryParse(
      _exerciseProgressionIncrementController.text.trim(),
    );
    draft.progressionDeloadAfterMisses = int.tryParse(
      _exerciseProgressionDeloadAfterController.text.trim(),
    );
    draft.progressionDeloadPercent = double.tryParse(
      _exerciseProgressionDeloadPercentController.text.trim(),
    );
    draft.notesController.text = _exerciseNotesController.text.trim();

    setState(() {
      _selectedBuilderDay.exercises.add(draft);
      _targetWeightController.clear();
      _exerciseNotesController.clear();
    });
  }

  void _moveBuilderExercise(_PlanDayDraft day, int index, int delta) {
    final nextIndex = (index + delta).clamp(0, day.exercises.length - 1);
    if (nextIndex == index) return;
    setState(() {
      final exercise = day.exercises.removeAt(index);
      day.exercises.insert(nextIndex, exercise);
      _renumberBuilderGroups(day);
    });
  }

  void _removeBuilderExercise(_PlanDayDraft day, int index) {
    setState(() {
      day.exercises.removeAt(index).dispose();
      _renumberBuilderGroups(day);
    });
  }

  Future<void> _editOrRemoveBuilderExercise(
    _PlanDayDraft day,
    int index,
  ) async {
    final exercise = day.exercises[index];
    if (exercise.groupKey == null) {
      _removeBuilderExercise(day, index);
      return;
    }
    final action = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.link_off_rounded),
              title: Text('Ungroup ${exercise.groupKey}'),
              subtitle: const Text(
                'Keep the exercises and remove this superset or circuit.',
              ),
              onTap: () => Navigator.pop(context, 'ungroup'),
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline_rounded),
              title: const Text('Remove exercise'),
              onTap: () => Navigator.pop(context, 'remove'),
            ),
          ],
        ),
      ),
    );
    if (!mounted || action == null) return;
    if (action == 'remove') {
      _removeBuilderExercise(day, index);
      return;
    }
    final groupKey = exercise.groupKey;
    setState(() {
      for (final item in day.exercises.where(
        (item) => item.groupKey == groupKey,
      )) {
        item.groupKey = null;
        item.groupType = null;
        item.groupOrder = null;
        item.groupRounds = null;
        item.transitionSeconds = null;
      }
    });
  }

  void _renumberBuilderGroups(_PlanDayDraft day) {
    final nextOrders = <String, int>{};
    for (final exercise in day.exercises) {
      final key = exercise.groupKey;
      if (key == null) continue;
      final next = (nextOrders[key] ?? 0) + 1;
      nextOrders[key] = next;
      exercise.groupOrder = next;
    }
  }

  Widget _buildExerciseBookOverview(BuildContext context) {
    if (_exerciseGroups.isEmpty) {
      return const SizedBox.shrink();
    }

    return _WorkoutBuilderPanel(
      gradient: const [Color(0xFFF8FBFF), Color(0xFFFFFFFF)],
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _BuilderSectionHeading(
            title: 'Exercise library',
            subtitle:
                'Choose a body part first, then attach the exact movement to each exercise block.',
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _exerciseGroups.entries.map((entry) {
              return Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 9,
                ),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 28,
                      height: 28,
                      decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.10),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(
                        Icons.fitness_center_rounded,
                        size: 14,
                        color: AppColors.primary,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          _bodyPartLabel(entry.key),
                          style: Theme.of(context).textTheme.labelLarge
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        Text(
                          '${entry.value.length} exercises',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: AppColors.textSecondary),
                        ),
                      ],
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

  Widget _buildTrainerStyleBuilderTab(BuildContext context) {
    final query = _exerciseSearchController.text.trim().toLowerCase();
    final filteredExercises = _exercises.where((exercise) {
      if (query.isEmpty) {
        return true;
      }
      return <String>[
        exercise['localized_name']?.toString() ?? '',
        exercise['name']?.toString() ?? '',
        exercise['muscle_group']?.toString() ?? '',
        exercise['body_part_label']?.toString() ?? '',
      ].any((value) => value.toLowerCase().contains(query));
    }).toList();
    final selectedDay = _selectedBuilderDay;
    final selectedExercise = _exerciseById(_selectedBuilderExerciseId);

    return ListView(
      key: const ValueKey('workout-builder-scroll'),
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.md,
        AppSpacing.lg,
        AppSpacing.xl,
      ),
      children: [
        _WorkoutBuilderPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: _BuilderSectionHeading(
                      title: _editingPlanId == null
                          ? 'Workout details'
                          : 'Edit workout details',
                      subtitle:
                          'Create a reusable program with a clear goal and weekly structure.',
                    ),
                  ),
                  if (_editingPlanId != null)
                    TextButton.icon(
                      onPressed: _resetCreator,
                      icon: const Icon(Icons.close_rounded),
                      label: const Text('Cancel'),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _nameController,
                decoration: _memberWorkoutInputDecoration(
                  'Plan name',
                  icon: Icons.drive_file_rename_outline_rounded,
                ),
              ),
              const SizedBox(height: 12),
              LayoutBuilder(
                builder: (context, constraints) {
                  final compact = constraints.maxWidth < 680;
                  final fields = <Widget>[
                    TextField(
                      controller: _goalController,
                      decoration: _memberWorkoutInputDecoration(
                        'Goal',
                        icon: Icons.flag_rounded,
                      ),
                    ),
                    DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue: _difficulty,
                      decoration: _memberWorkoutInputDecoration(
                        'Difficulty',
                        icon: Icons.speed_rounded,
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
                  ];
                  if (compact) {
                    return Column(
                      children: [
                        fields[0],
                        const SizedBox(height: 12),
                        fields[1],
                      ],
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
              LayoutBuilder(
                builder: (context, constraints) {
                  final compact = constraints.maxWidth < 680;
                  final duration = TextField(
                    controller: _durationController,
                    keyboardType: TextInputType.number,
                    decoration: _memberWorkoutInputDecoration(
                      'Duration weeks',
                      icon: Icons.date_range_rounded,
                    ),
                  );
                  final minutes = TextField(
                    controller: _minutesController,
                    keyboardType: TextInputType.number,
                    decoration: _memberWorkoutInputDecoration(
                      'Minutes per session',
                      icon: Icons.timer_outlined,
                    ),
                  );
                  if (compact) {
                    return Column(
                      children: [duration, const SizedBox(height: 12), minutes],
                    );
                  }
                  return Row(
                    children: [
                      Expanded(child: duration),
                      const SizedBox(width: 12),
                      Expanded(child: minutes),
                    ],
                  );
                },
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _planNotesController,
                minLines: 2,
                maxLines: 4,
                decoration: _memberWorkoutInputDecoration(
                  'Plan notes',
                  icon: Icons.notes_rounded,
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                isExpanded: true,
                initialValue: _planProgressionPolicy,
                decoration: _memberWorkoutInputDecoration(
                  'Automatic progression',
                  icon: Icons.trending_up_rounded,
                ),
                items: const [
                  DropdownMenuItem(value: 'off', child: Text('Manual / off')),
                  DropdownMenuItem(
                    value: 'linear_load',
                    child: Text('Linear load'),
                  ),
                  DropdownMenuItem(
                    value: 'double_progression',
                    child: Text('Double progression'),
                  ),
                ],
                onChanged: (value) =>
                    setState(() => _planProgressionPolicy = value ?? 'off'),
              ),
              if (_planProgressionPolicy != 'off') ...[
                const SizedBox(height: 12),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    if (_planProgressionPolicy == 'double_progression') ...[
                      SizedBox(
                        width: 150,
                        child: TextField(
                          controller: _planProgressionMinRepsController,
                          keyboardType: TextInputType.number,
                          decoration: _memberWorkoutInputDecoration('Min reps'),
                        ),
                      ),
                      SizedBox(
                        width: 150,
                        child: TextField(
                          controller: _planProgressionMaxRepsController,
                          keyboardType: TextInputType.number,
                          decoration: _memberWorkoutInputDecoration('Max reps'),
                        ),
                      ),
                    ],
                    SizedBox(
                      width: 180,
                      child: TextField(
                        controller: _planProgressionIncrementController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: _memberWorkoutInputDecoration(
                          'Load increase kg',
                        ),
                      ),
                    ),
                    SizedBox(
                      width: 180,
                      child: TextField(
                        controller: _planDeloadAfterController,
                        keyboardType: TextInputType.number,
                        decoration: _memberWorkoutInputDecoration(
                          'Deload after misses',
                        ),
                      ),
                    ),
                    SizedBox(
                      width: 180,
                      child: TextField(
                        controller: _planDeloadPercentController,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: _memberWorkoutInputDecoration(
                          'Deload percent',
                        ),
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Weekly schedule',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  _BuilderInfoChip(
                    label:
                        '${_dayDrafts.length} day${_dayDrafts.length == 1 ? '' : 's'}',
                    icon: Icons.calendar_today_rounded,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _builderWeekdays.entries.map((entry) {
                  final selected = _dayDrafts.any(
                    (day) => day.weekday == entry.key,
                  );
                  return FilterChip(
                    selected: selected,
                    onSelected: (_) => _toggleBuilderWeekday(entry.key),
                    label: Text(entry.value),
                  );
                }).toList(),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _WorkoutBuilderPanel(
          gradient: const [Color(0xFFFFFFFF), Color(0xFFF7FAFF)],
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const _BuilderSectionHeading(
                title: 'Day builder',
                subtitle:
                    'Choose each day, add exercises, and set the work and recovery targets.',
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _dayDrafts.asMap().entries.map((entry) {
                  final weekday = entry.value.weekday ?? entry.key + 1;
                  final selected = _selectedBuilderDayIndex == entry.key;
                  return ChoiceChip(
                    label: Text(_weekdayLabel(weekday)),
                    selected: selected,
                    onSelected: (_) =>
                        setState(() => _selectedBuilderDayIndex = entry.key),
                  );
                }).toList(),
              ),
              const SizedBox(height: 14),
              LayoutBuilder(
                builder: (context, constraints) {
                  final compact = constraints.maxWidth < 680;
                  final label = TextField(
                    controller: selectedDay.labelController,
                    decoration: _memberWorkoutInputDecoration(
                      'Day label',
                      icon: Icons.label_outline_rounded,
                    ),
                  );
                  final focus = TextField(
                    controller: selectedDay.focusController,
                    decoration: _memberWorkoutInputDecoration(
                      'Focus',
                      icon: Icons.center_focus_strong_rounded,
                    ),
                  );
                  if (compact) {
                    return Column(
                      children: [label, const SizedBox(height: 12), focus],
                    );
                  }
                  return Row(
                    children: [
                      Expanded(child: label),
                      const SizedBox(width: 12),
                      Expanded(child: focus),
                    ],
                  );
                },
              ),
              const SizedBox(height: 12),
              TextField(
                controller: selectedDay.notesController,
                minLines: 2,
                maxLines: 3,
                decoration: _memberWorkoutInputDecoration(
                  'Day notes',
                  icon: Icons.sticky_note_2_outlined,
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'Exercise library',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _exerciseSearchController,
                onChanged: (_) => setState(() {}),
                decoration:
                    _memberWorkoutInputDecoration(
                      'Search exercises',
                      icon: Icons.search_rounded,
                    ).copyWith(
                      suffixIcon: _exerciseSearchController.text.trim().isEmpty
                          ? null
                          : IconButton(
                              tooltip: 'Clear exercise search',
                              onPressed: () {
                                _exerciseSearchController.clear();
                                _searchExercises();
                              },
                              icon: const Icon(Icons.close_rounded),
                            ),
                      helperText: 'Search by exercise, muscle, or body part.',
                    ),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children:
                    <(String, String, IconData)>[
                      ('all', 'All', Icons.grid_view_rounded),
                      ('favourites', 'Favourites', Icons.favorite_rounded),
                      ('recent', 'Recent', Icons.history_rounded),
                    ].map((option) {
                      return ChoiceChip(
                        avatar:
                            _loadingExercises &&
                                _exerciseCatalogView == option.$1
                            ? const SizedBox.square(
                                dimension: 14,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : Icon(option.$3, size: 16),
                        label: Text(option.$2),
                        selected: _exerciseCatalogView == option.$1,
                        onSelected: _loadingExercises
                            ? null
                            : (_) {
                                setState(
                                  () => _exerciseCatalogView = option.$1,
                                );
                                _searchExercises();
                              },
                      );
                    }).toList(),
              ),
              if (_equipmentProfiles.isNotEmpty) ...[
                const SizedBox(height: 12),
                DropdownButtonFormField<int?>(
                  isExpanded: true,
                  initialValue: _selectedEquipmentProfileId,
                  decoration: _memberWorkoutInputDecoration(
                    'Available equipment',
                    icon: Icons.home_repair_service_outlined,
                  ),
                  items: <DropdownMenuItem<int?>>[
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('Any equipment'),
                    ),
                    ..._equipmentProfiles.map(
                      (profile) => DropdownMenuItem<int?>(
                        value: (profile['id'] as num?)?.toInt(),
                        child: Text(profile['name']?.toString() ?? 'Profile'),
                      ),
                    ),
                  ],
                  onChanged: _loadingExercises
                      ? null
                      : (value) {
                          setState(() => _selectedEquipmentProfileId = value);
                          _searchExercises();
                        },
                ),
              ],
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: _loadingExercises
                      ? null
                      : _showEquipmentProfileSheet,
                  icon: const Icon(Icons.tune_rounded),
                  label: Text(
                    _equipmentProfiles.isEmpty
                        ? 'Set available equipment'
                        : 'Add equipment profile',
                  ),
                ),
              ),
              const SizedBox(height: 12),
              if (filteredExercises.isEmpty)
                EmptyStateView(
                  title: _loadingExercises
                      ? 'Finding exercises...'
                      : 'No exercises found',
                  message: _loadingExercises
                      ? 'Checking the exercise library for matching results.'
                      : 'Try another search, choose All, or change your equipment profile.',
                  icon: _loadingExercises
                      ? Icons.hourglass_top_rounded
                      : Icons.search_off_rounded,
                )
              else ...[
                DropdownMenu<int>(
                  controller: _exercisePickerTextController,
                  initialSelection: _selectedBuilderExerciseId,
                  expandedInsets: EdgeInsets.zero,
                  requestFocusOnTap: false,
                  enableFilter: false,
                  enableSearch: false,
                  closeBehavior: DropdownMenuCloseBehavior.none,
                  label: const Text('Exercise picker'),
                  leadingIcon: const Icon(Icons.fitness_center_rounded),
                  helperText: _exercisePage.hasMore
                      ? 'More results are available at the end of this list.'
                      : '${filteredExercises.length} exercises available',
                  dropdownMenuEntries: [
                    ...filteredExercises
                        .where((exercise) => exercise['id'] is num)
                        .map(
                          (exercise) => DropdownMenuEntry<int>(
                            value: (exercise['id'] as num).toInt(),
                            label:
                                '${_exerciseDisplayName(exercise)} • ${_bodyPartLabel(_bodyPartKeyForExercise(exercise))}',
                            labelWidget: Text(
                              '${_exerciseDisplayName(exercise)} • ${_bodyPartLabel(_bodyPartKeyForExercise(exercise))}',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ),
                    if (_exercisePage.hasMore)
                      DropdownMenuEntry<int>(
                        value: _loadMoreExercisePickerValue,
                        label: _loadingExercises
                            ? 'Loading exercises...'
                            : 'Load more exercise results',
                        enabled: !_loadingExercises,
                        leadingIcon: _loadingExercises
                            ? const SizedBox.square(
                                dimension: 16,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Icon(Icons.expand_more_rounded, size: 20),
                      ),
                  ],
                  onSelected: (value) {
                    if (value == _loadMoreExercisePickerValue) {
                      _exercisePickerTextController.text =
                          _exercisePickerLabelForSelection() ==
                              'Choose an exercise'
                          ? ''
                          : _exercisePickerLabelForSelection();
                      unawaited(_loadMoreExercises());
                      return;
                    }
                    setState(() {
                      _selectedBuilderExerciseId = value;
                      final selected = _exerciseById(value);
                      final suggested =
                          selected?['default_tracking_mode']?.toString() ??
                          'reps';
                      _trackingMode =
                          const {
                            'reps',
                            'timed',
                            'cardio',
                            'distance',
                          }.contains(suggested)
                          ? suggested
                          : 'reps';
                      if (_trackingMode != 'reps') {
                        _exerciseProgressionPolicy = 'off';
                      }
                    });
                  },
                ),
                const SizedBox(height: 12),
                _buildExerciseMetaPanel(context, selectedExercise),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  isExpanded: true,
                  key: ValueKey('member-tracking-mode-$_trackingMode'),
                  initialValue: _trackingMode,
                  decoration: _memberWorkoutInputDecoration(
                    'Tracking mode',
                    icon: Icons.track_changes_rounded,
                  ),
                  items: const [
                    DropdownMenuItem(value: 'reps', child: Text('Reps & load')),
                    DropdownMenuItem(value: 'timed', child: Text('Timed work')),
                    DropdownMenuItem(value: 'cardio', child: Text('Cardio')),
                    DropdownMenuItem(
                      value: 'distance',
                      child: Text('Distance'),
                    ),
                  ],
                  onChanged: (value) => setState(() {
                    _trackingMode = value ?? 'reps';
                    if (_trackingMode != 'reps') {
                      _exerciseProgressionPolicy = 'off';
                    }
                  }),
                ),
                const SizedBox(height: 12),
                LayoutBuilder(
                  builder: (context, constraints) {
                    final compact = constraints.maxWidth < 720;
                    final fields = <Widget>[
                      TextField(
                        controller: _setsController,
                        keyboardType: TextInputType.number,
                        decoration: _memberWorkoutInputDecoration('Sets'),
                      ),
                      TextField(
                        controller: _repsController,
                        decoration: _memberWorkoutInputDecoration('Reps'),
                      ),
                      TextField(
                        controller: _restController,
                        keyboardType: TextInputType.number,
                        decoration: _memberWorkoutInputDecoration('Rest sec'),
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
                const SizedBox(height: 12),
                _buildGroupingOptions(context),
                const SizedBox(height: 12),
                _buildAdvancedExerciseOptions(context),
                const SizedBox(height: 12),
                if (_trackingMode != 'reps') ...[
                  Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      SizedBox(
                        width: 180,
                        child: TextField(
                          controller: _durationSecondsController,
                          keyboardType: TextInputType.number,
                          decoration: _memberWorkoutInputDecoration(
                            'Target duration sec',
                          ),
                        ),
                      ),
                      if (_trackingMode == 'cardio' ||
                          _trackingMode == 'distance')
                        SizedBox(
                          width: 180,
                          child: TextField(
                            controller: _distanceMetersController,
                            keyboardType: const TextInputType.numberWithOptions(
                              decimal: true,
                            ),
                            decoration: _memberWorkoutInputDecoration(
                              'Target distance m',
                            ),
                          ),
                        ),
                      if (_trackingMode == 'cardio') ...[
                        SizedBox(
                          width: 180,
                          child: TextField(
                            controller: _speedKphController,
                            keyboardType: const TextInputType.numberWithOptions(
                              decimal: true,
                            ),
                            decoration: _memberWorkoutInputDecoration(
                              'Target speed km/h',
                            ),
                          ),
                        ),
                        SizedBox(
                          width: 180,
                          child: TextField(
                            controller: _paceSecondsController,
                            keyboardType: TextInputType.number,
                            decoration: _memberWorkoutInputDecoration(
                              'Target pace sec/km',
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 12),
                ],
                LayoutBuilder(
                  builder: (context, constraints) {
                    final compact = constraints.maxWidth < 680;
                    final weight = TextField(
                      controller: _targetWeightController,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: _memberWorkoutInputDecoration(
                        'Target weight',
                      ),
                    );
                    final notes = TextField(
                      controller: _exerciseNotesController,
                      decoration: _memberWorkoutInputDecoration(
                        'Exercise notes',
                      ),
                    );
                    if (compact) {
                      return Column(
                        children: [weight, const SizedBox(height: 12), notes],
                      );
                    }
                    return Row(
                      children: [
                        Expanded(child: weight),
                        const SizedBox(width: 12),
                        Expanded(child: notes),
                      ],
                    );
                  },
                ),
                const SizedBox(height: 14),
                GradientButton(
                  label:
                      'Add exercise to ${_weekdayLabel(selectedDay.weekday ?? 1).toUpperCase()}',
                  icon: Icons.add_circle_outline_rounded,
                  expanded: true,
                  onPressed: _addExerciseToBuilderDay,
                ),
              ],
              const SizedBox(height: 20),
              Text(
                'Exercises for ${_weekdayLabel(selectedDay.weekday ?? 1).toUpperCase()}',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              if (selectedDay.exercises.isEmpty)
                const EmptyStateView(
                  title: 'No exercises added yet',
                  message:
                      'Pick an exercise, set the prescription, and add it to this day.',
                  icon: Icons.playlist_add_check_circle_outlined,
                )
              else
                ...selectedDay.exercises.asMap().entries.map((entry) {
                  final exercise = entry.value;
                  final meta = _exerciseById(exercise.exerciseId);
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _MemberBuilderExerciseTile(
                      title: meta?['name']?.toString() ?? 'Exercise',
                      subtitle:
                          '${exercise.sets} sets • ${exercise.reps} • ${exercise.restSeconds} sec rest${exercise.groupKey == null ? '' : ' • ${exercise.groupKey}${exercise.groupOrder} ${exercise.groupType} • ${exercise.groupRounds} rounds • ${exercise.transitionSeconds}s transition'}${exercise.progressionPolicy == 'off' ? '' : ' • progression'}',
                      badge: meta == null
                          ? null
                          : _bodyPartLabel(_bodyPartKeyForExercise(meta)),
                      moveLabel: entry.key == 0 ? 'Move down' : 'Move up',
                      onMove: selectedDay.exercises.length < 2
                          ? null
                          : () => _moveBuilderExercise(
                              selectedDay,
                              entry.key,
                              entry.key == 0 ? 1 : -1,
                            ),
                      removeLabel: exercise.groupKey == null
                          ? 'Remove'
                          : 'Group / Remove',
                      onRemove: () =>
                          _editOrRemoveBuilderExercise(selectedDay, entry.key),
                    ),
                  );
                }),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
      ],
    );
  }

  _PlanExerciseDraft _buildExerciseDraft() {
    final draft = _PlanExerciseDraft();
    if (_exerciseGroups.isNotEmpty) {
      draft.bodyPart = _exerciseGroups.keys.first;
    }
    return draft;
  }

  Widget _buildGroupingOptions(BuildContext context) {
    return Material(
      color: AppColors.surfaceSoft,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppColors.stroke),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Exercise group (optional)',
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _groupKeyController,
              textCapitalization: TextCapitalization.characters,
              decoration:
                  _memberWorkoutInputDecoration(
                    'Group label (optional)',
                    icon: Icons.link_rounded,
                  ).copyWith(
                    hintText: 'A, B, or Circuit 1',
                    helperText: 'Use the same label on two or more exercises.',
                  ),
            ),
            const SizedBox(height: 12),
            LayoutBuilder(
              builder: (context, constraints) {
                final compact = constraints.maxWidth < 520;
                final type = DropdownButtonFormField<String>(
                  isExpanded: true,
                  initialValue: _groupType,
                  decoration: _memberWorkoutInputDecoration('Group type'),
                  items: const [
                    DropdownMenuItem(
                      value: 'superset',
                      child: Text('Superset'),
                    ),
                    DropdownMenuItem(value: 'circuit', child: Text('Circuit')),
                  ],
                  onChanged: (value) =>
                      setState(() => _groupType = value ?? 'superset'),
                );
                final rounds = TextField(
                  controller: _groupRoundsController,
                  keyboardType: TextInputType.number,
                  decoration: _memberWorkoutInputDecoration('Rounds'),
                );
                final transition = TextField(
                  controller: _transitionSecondsController,
                  keyboardType: TextInputType.number,
                  decoration: _memberWorkoutInputDecoration('Transition sec'),
                );
                if (compact) {
                  return Column(
                    children: [
                      type,
                      const SizedBox(height: 10),
                      rounds,
                      const SizedBox(height: 10),
                      transition,
                    ],
                  );
                }
                return Row(
                  children: [
                    Expanded(child: type),
                    const SizedBox(width: 10),
                    Expanded(child: rounds),
                    const SizedBox(width: 10),
                    Expanded(child: transition),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAdvancedExerciseOptions(BuildContext context) {
    return Material(
      color: AppColors.surfaceSoft,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppColors.stroke),
      ),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
        childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
        leading: const Icon(Icons.tune_rounded, color: AppColors.primary),
        title: const Text('Advanced exercise options'),
        subtitle: const Text('Exercise progression settings'),
        children: [
          DropdownButtonFormField<String>(
            isExpanded: true,
            initialValue: _exerciseProgressionPolicy,
            decoration: _memberWorkoutInputDecoration(
              'Progression policy',
              icon: Icons.trending_up_rounded,
            ),
            items: const [
              DropdownMenuItem(value: 'off', child: Text('Manual / off')),
              DropdownMenuItem(
                value: 'linear_load',
                child: Text('Linear load'),
              ),
              DropdownMenuItem(
                value: 'double_progression',
                child: Text('Double progression'),
              ),
            ],
            onChanged: _trackingMode == 'reps'
                ? (value) => setState(
                    () => _exerciseProgressionPolicy = value ?? 'off',
                  )
                : null,
          ),
          if (_exerciseProgressionPolicy != 'off') ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (_exerciseProgressionPolicy == 'double_progression') ...[
                  SizedBox(
                    width: 138,
                    child: TextField(
                      controller: _exerciseProgressionMinRepsController,
                      keyboardType: TextInputType.number,
                      decoration: _memberWorkoutInputDecoration('Min reps'),
                    ),
                  ),
                  SizedBox(
                    width: 138,
                    child: TextField(
                      controller: _exerciseProgressionMaxRepsController,
                      keyboardType: TextInputType.number,
                      decoration: _memberWorkoutInputDecoration('Max reps'),
                    ),
                  ),
                ],
                SizedBox(
                  width: 168,
                  child: TextField(
                    controller: _exerciseProgressionIncrementController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: _memberWorkoutInputDecoration(
                      'Load increase kg',
                    ),
                  ),
                ),
                SizedBox(
                  width: 168,
                  child: TextField(
                    controller: _exerciseProgressionDeloadAfterController,
                    keyboardType: TextInputType.number,
                    decoration: _memberWorkoutInputDecoration(
                      'Deload after misses',
                    ),
                  ),
                ),
                SizedBox(
                  width: 168,
                  child: TextField(
                    controller: _exerciseProgressionDeloadPercentController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: _memberWorkoutInputDecoration('Deload percent'),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildExerciseMetaPanel(
    BuildContext context,
    Map<String, dynamic>? exerciseMeta, {
    bool showActions = true,
  }) {
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
      if ((exerciseMeta['target_muscle']?.toString() ?? '').isNotEmpty)
        'Target: ${exerciseMeta['target_muscle']}',
      if ((exerciseMeta['muscle_group']?.toString() ?? '').isNotEmpty)
        exerciseMeta['muscle_group'].toString(),
      if ((exerciseMeta['equipment']?.toString() ?? '').isNotEmpty)
        exerciseMeta['equipment'].toString(),
      if ((exerciseMeta['difficulty']?.toString() ?? '').isNotEmpty)
        exerciseMeta['difficulty'].toString(),
      if ((exerciseMeta['default_tracking_mode']?.toString() ?? '').isNotEmpty)
        exerciseMeta['default_tracking_mode'].toString(),
      if (exerciseMeta['is_bodyweight'] == true) 'Bodyweight',
      if (exerciseMeta['is_per_side'] == true) 'Per side',
    ];

    final secondary =
        (exerciseMeta['secondary_muscles'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .where((item) => item.isNotEmpty)
            .toList();
    final instructionSteps =
        (exerciseMeta['instruction_steps'] as List<dynamic>? ?? const [])
            .map((item) => item.toString().trim())
            .where((item) => item.isNotEmpty)
            .toList();
    final preview = exerciseMeta['preview_media'];
    final previewUrl = preview is Map
        ? preview['url']?.toString().trim() ?? ''
        : '';

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
          if (showActions)
            Row(
              children: [
                Expanded(
                  child: Text(
                    _exerciseDisplayName(exerciseMeta),
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: exerciseMeta['is_favourite'] == true
                      ? 'Remove favourite'
                      : 'Add favourite',
                  onPressed: () => _toggleExerciseFavourite(exerciseMeta),
                  icon: Icon(
                    exerciseMeta['is_favourite'] == true
                        ? Icons.favorite_rounded
                        : Icons.favorite_border_rounded,
                    color: exerciseMeta['is_favourite'] == true
                        ? Colors.redAccent
                        : AppColors.textSecondary,
                  ),
                ),
                IconButton(
                  tooltip: 'Exercise details',
                  onPressed: () => _showExerciseDetails(exerciseMeta),
                  icon: const Icon(Icons.info_outline_rounded),
                ),
              ],
            ),
          if (previewUrl.isNotEmpty) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.network(
                previewUrl,
                height: 180,
                width: double.infinity,
                fit: BoxFit.contain,
                semanticLabel: '${_exerciseDisplayName(exerciseMeta)} preview',
                errorBuilder: (context, error, stackTrace) => const SizedBox(
                  height: 72,
                  child: Center(child: Text('Preview unavailable')),
                ),
              ),
            ),
            const SizedBox(height: 12),
          ],
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
          if (instructionSteps.isNotEmpty) ...[
            const SizedBox(height: 8),
            ...instructionSteps.indexed.map(
              (entry) => Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(
                  '${entry.$1 + 1}. ${entry.$2}',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    height: 1.35,
                  ),
                ),
              ),
            ),
          ] else if ((exerciseMeta['instructions']?.toString() ?? '')
              .isNotEmpty) ...[
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

  Future<void> _sharePlan(Map<String, dynamic> plan) async {
    final id = (plan['id'] as num?)?.toInt();
    if (id == null || _sharingPlanId != null) {
      return;
    }

    setState(() => _sharingPlanId = id);
    try {
      final response = await widget.repository.createWorkoutPlanShare(id, {
        'expires_in_days': 14,
      });
      final data = Map<String, dynamic>.from(
        response['data'] as Map? ?? const {},
      );
      final shareUrl = data['share_url']?.toString() ?? '';
      if (shareUrl.isEmpty) {
        throw StateError('The workout link could not be created.');
      }
      if (!mounted) return;
      final planName = plan['name']?.toString() ?? 'Workout plan';
      final box = context.findRenderObject() as RenderBox?;
      await SharePlus.instance.share(
        ShareParams(
          subject: '$planName on Atlas',
          text:
              'I shared "$planName" with you. Open it in Gym Atlas to review and save your own copy:\n$shareUrl',
          sharePositionOrigin: box == null
              ? null
              : box.localToGlobal(Offset.zero) & box.size,
        ),
      );
    } catch (exception) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_friendlyError(exception))));
    } finally {
      if (mounted) {
        setState(() => _sharingPlanId = null);
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
            style: FilledButton.styleFrom(backgroundColor: AppColors.error),
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
    _planNotesController.text = plan['notes']?.toString() ?? '';
    _planProgressionPolicy =
        const {
          'off',
          'linear_load',
          'double_progression',
        }.contains(plan['progression_policy'])
        ? plan['progression_policy'].toString()
        : 'off';
    final progressionConfig = Map<String, dynamic>.from(
      plan['progression_config'] as Map? ?? const {},
    );
    _planProgressionMinRepsController.text =
        progressionConfig['min_reps']?.toString() ?? '8';
    _planProgressionMaxRepsController.text =
        progressionConfig['max_reps']?.toString() ?? '12';
    _planProgressionIncrementController.text =
        progressionConfig['load_increment_kg']?.toString() ?? '2.5';
    _planDeloadAfterController.text =
        progressionConfig['deload_after_misses']?.toString() ?? '3';
    _planDeloadPercentController.text =
        progressionConfig['deload_percent']?.toString() ?? '10';
    final difficulty = plan['difficulty']?.toString() ?? 'intermediate';
    _difficulty =
        const <String>{
          'beginner',
          'intermediate',
          'advanced',
        }.contains(difficulty)
        ? difficulty
        : 'intermediate';

    for (final day in _dayDrafts) {
      day.dispose();
    }
    _dayDrafts.clear();

    final days = (plan['days'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    for (final day in days) {
      final draft = _PlanDayDraft(
        weekday: (day['day_number'] as num?)?.toInt(),
      );
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
        if (exerciseDraft.exerciseId != null && exerciseMeta.isNotEmpty) {
          _savedExerciseMetadata[exerciseDraft.exerciseId!] = {
            ...exerciseMeta,
            'id': exerciseDraft.exerciseId,
          };
        }
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
        exerciseDraft.trackingMode =
            exercise['tracking_mode']?.toString() ?? 'reps';
        exerciseDraft.durationSecondsController.text =
            exercise['planned_duration_seconds']?.toString() ?? '';
        exerciseDraft.distanceMetersController.text =
            exercise['planned_distance_meters']?.toString() ?? '';
        exerciseDraft.speedKphController.text =
            exercise['planned_speed_kph']?.toString() ?? '';
        exerciseDraft.paceSecondsController.text =
            exercise['planned_pace_seconds_per_km']?.toString() ?? '';
        exerciseDraft.isPerSide = exercise['is_per_side'] == true;
        exerciseDraft.isBodyweight = exercise['is_bodyweight'] == true;
        exerciseDraft.groupKey = exercise['group_key']?.toString();
        exerciseDraft.groupType = exercise['group_type']?.toString();
        exerciseDraft.groupOrder = (exercise['group_order'] as num?)?.toInt();
        exerciseDraft.groupRounds = (exercise['group_rounds'] as num?)?.toInt();
        exerciseDraft.transitionSeconds =
            (exercise['transition_seconds'] as num?)?.toInt();
        exerciseDraft.progressionPolicy =
            exercise['progression_policy']?.toString() ?? 'off';
        final exerciseProgression = Map<String, dynamic>.from(
          exercise['progression_config'] as Map? ?? const {},
        );
        exerciseDraft.progressionMinReps =
            (exerciseProgression['min_reps'] as num?)?.toInt();
        exerciseDraft.progressionMaxReps =
            (exerciseProgression['max_reps'] as num?)?.toInt();
        exerciseDraft.progressionIncrementKg =
            (exerciseProgression['load_increment_kg'] as num?)?.toDouble();
        exerciseDraft.progressionDeloadAfterMisses =
            (exerciseProgression['deload_after_misses'] as num?)?.toInt();
        exerciseDraft.progressionDeloadPercent =
            (exerciseProgression['deload_percent'] as num?)?.toDouble();
        exerciseDraft.targetWeightController.text =
            exercise['target_weight']?.toString() ?? '';
        exerciseDraft.notesController.text =
            exercise['notes']?.toString() ?? '';
        draft.exercises.add(exerciseDraft);
      }
      _dayDrafts.add(draft);
    }

    if (_dayDrafts.isEmpty) {
      _initializeDefaultBuilderDays();
    }

    _selectedBuilderDayIndex = 0;
    setState(() {});
    _tabController.animateTo(2);
  }

  Future<void> _savePlan() async {
    if (_nameController.text.trim().isEmpty) {
      _nameController.text = 'Custom workout';
    }
    final durationWeeks = int.tryParse(_durationController.text.trim());
    final sessionMinutes = int.tryParse(_minutesController.text.trim());
    if (durationWeeks == null || durationWeeks < 1 || durationWeeks > 52) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Duration must be from 1 to 52 weeks.')),
      );
      return;
    }
    if (sessionMinutes == null || sessionMinutes < 10 || sessionMinutes > 240) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Session duration must be from 10 to 240 minutes.'),
        ),
      );
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
    for (final day in _dayDrafts) {
      final groupedCounts = <String, int>{};
      for (final exercise in day.exercises) {
        final key = exercise.groupKey;
        if (key != null && key.isNotEmpty) {
          groupedCounts[key] = (groupedCounts[key] ?? 0) + 1;
        }
      }
      final invalidGroup = groupedCounts.entries
          .where((entry) => entry.value < 2)
          .firstOrNull;
      if (invalidGroup != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Group ${invalidGroup.key} on ${_weekdayLabel(day.weekday ?? 1)} needs at least two exercises.',
            ),
          ),
        );
        return;
      }
    }

    final payload = <String, dynamic>{
      'name': _nameController.text.trim(),
      'goal': _goalController.text.trim().isEmpty
          ? null
          : _goalController.text.trim(),
      'difficulty': _difficulty,
      'duration_weeks': durationWeeks,
      'estimated_session_minutes': sessionMinutes,
      'notes': _planNotesController.text.trim().isEmpty
          ? null
          : _planNotesController.text.trim(),
      'status': 'active',
      'progression_policy': _planProgressionPolicy,
      'progression_config': _planProgressionPolicy == 'off'
          ? null
          : {
              'load_increment_kg':
                  double.tryParse(
                    _planProgressionIncrementController.text.trim(),
                  ) ??
                  2.5,
              'deload_after_misses':
                  int.tryParse(_planDeloadAfterController.text.trim()) ?? 3,
              'deload_percent':
                  double.tryParse(_planDeloadPercentController.text.trim()) ??
                  10,
              if (_planProgressionPolicy == 'double_progression')
                'min_reps':
                    int.tryParse(
                      _planProgressionMinRepsController.text.trim(),
                    ) ??
                    8,
              if (_planProgressionPolicy == 'double_progression')
                'max_reps':
                    int.tryParse(
                      _planProgressionMaxRepsController.text.trim(),
                    ) ??
                    12,
            },
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
              'tracking_mode': item.trackingMode,
              'reps': item.reps,
              'planned_duration_seconds': item.durationSeconds,
              'planned_distance_meters': item.distanceMeters,
              'planned_speed_kph': item.speedKph,
              'planned_pace_seconds_per_km': item.paceSeconds,
              'target_weight': item.targetWeight,
              'is_per_side': item.isPerSide,
              'is_bodyweight': item.isBodyweight,
              'rest_seconds': item.restSeconds,
              'group_key': item.groupKey,
              'group_type': item.groupType,
              'group_order': item.groupOrder,
              'group_rounds': item.groupRounds,
              'transition_seconds': item.transitionSeconds,
              'rest_after': item.groupKey == null ? 'exercise' : 'group',
              'progression_policy': item.trackingMode == 'reps'
                  ? item.progressionPolicy
                  : 'off',
              'progression_config':
                  item.trackingMode != 'reps' || item.progressionPolicy == 'off'
                  ? null
                  : {
                      'load_increment_kg': item.progressionIncrementKg ?? 2.5,
                      'deload_after_misses':
                          item.progressionDeloadAfterMisses ?? 3,
                      'deload_percent': item.progressionDeloadPercent ?? 10,
                      if (item.progressionPolicy == 'double_progression')
                        'min_reps': item.progressionMinReps ?? 8,
                      if (item.progressionPolicy == 'double_progression')
                        'max_reps': item.progressionMaxReps ?? 12,
                    },
              'notes': item.notes,
            };
          }).toList(),
        };
      }).toList(),
    };

    final validationError = validateWorkoutBuilderPayload(payload);
    if (validationError != null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(validationError)));
      return;
    }

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
    _planNotesController.clear();
    _planProgressionPolicy = 'off';
    _planProgressionMinRepsController.text = '8';
    _planProgressionMaxRepsController.text = '12';
    _planProgressionIncrementController.text = '2.5';
    _planDeloadAfterController.text = '3';
    _planDeloadPercentController.text = '10';
    _exerciseSearchController.clear();
    _setsController.text = '4';
    _repsController.text = '10';
    _targetWeightController.clear();
    _restController.text = '60';
    _durationSecondsController.clear();
    _distanceMetersController.clear();
    _speedKphController.clear();
    _paceSecondsController.clear();
    _trackingMode = 'reps';
    _exerciseNotesController.clear();
    _groupKeyController.clear();
    _groupRoundsController.text = '3';
    _transitionSecondsController.text = '15';
    _groupType = 'superset';
    _exerciseProgressionPolicy = 'off';
    _exerciseProgressionMinRepsController.text = '8';
    _exerciseProgressionMaxRepsController.text = '12';
    _exerciseProgressionIncrementController.text = '2.5';
    _exerciseProgressionDeloadAfterController.text = '3';
    _exerciseProgressionDeloadPercentController.text = '10';
    _difficulty = 'intermediate';
    for (final day in _dayDrafts) {
      day.dispose();
    }
    _dayDrafts.clear();
    _initializeDefaultBuilderDays();
    _selectedBuilderDayIndex = 0;
    setState(() {});
  }

  Future<void> _showPlanPreview({
    required String title,
    required Map<String, dynamic> plan,
    required VoidCallback primaryAction,
    required String primaryLabel,
    bool refreshMemberPlan = true,
  }) async {
    var planDetail = Map<String, dynamic>.from(plan);
    final planId = (planDetail['id'] as num?)?.toInt();
    if (refreshMemberPlan && planId != null) {
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
      builder: (context) => WorkoutBookPreviewSheet(
        title: title,
        planDetail: planDetail,
        days: days,
        primaryAction: primaryAction,
        primaryLabel: primaryLabel,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Workout Book',
      bottomNavigationBar: _activeTabIndex == 2
          ? _WorkoutBuilderSaveBar(
              saving: _saving,
              editing: _editingPlanId != null,
              onSave: _saving ? null : _savePlan,
            )
          : null,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _WorkoutBookTopBar(
              title: 'Workout Book',
              subtitle: 'Choose a plan, discover one, or build your own.',
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                0,
                AppSpacing.lg,
                0,
              ),
              child: _WorkoutBookTabSlider(controller: _tabController),
            ),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 180),
              child: _loading && (_books.isNotEmpty || _plans.isNotEmpty)
                  ? const LinearProgressIndicator(
                      key: ValueKey('workout-book-refreshing'),
                      minHeight: 2,
                    )
                  : const SizedBox(
                      key: ValueKey('workout-book-not-refreshing'),
                      height: 2,
                    ),
            ),
            Expanded(
              child: _loading && _books.isEmpty && _plans.isEmpty
                  ? const _WorkoutBookLoadingState()
                  : _error != null && _books.isEmpty && _plans.isEmpty
                  ? ErrorStateView(message: _error!, onRetry: _load)
                  : TabBarView(
                      controller: _tabController,
                      children: [
                        _buildLibraryTab(context),
                        _buildCatalogTab(context),
                        _buildTrainerStyleBuilderTab(context),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLibraryTab(BuildContext context) {
    if (_plans.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          _WorkoutBookEmptyStatePanel(
            title: 'Your library is ready for its first plan',
            message:
                'Add a ready-made program from the catalog or create a plan around your own schedule.',
            icon: Icons.menu_book_rounded,
            actionLabel: 'Explore catalog',
            onAction: () => _tabController.animateTo(1),
            secondaryActionLabel: 'Build my own',
            onSecondaryAction: () => _tabController.animateTo(2),
          ),
        ],
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg,
          AppSpacing.md,
          AppSpacing.lg,
          AppSpacing.xl,
        ),
        itemCount: _plans.length + 1 + (_planPage.hasMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index == 0) {
            return _WorkoutBookSectionIntro(
              title: 'Your training plans',
              subtitle:
                  'Start, preview, share, or update the plans available to you.',
              icon: Icons.bookmark_added_rounded,
              gradient: const [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
            );
          }
          if (index > _plans.length) {
            return Padding(
              padding: const EdgeInsets.only(top: AppSpacing.xs),
              child: _loadMoreButton(
                label: 'Load more plans',
                loading: _loadingMorePlans,
                onPressed: _loadMorePlans,
              ),
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
            sharing: _sharingPlanId == (plan['id'] as num?)?.toInt(),
            onShare: () => _sharePlan(plan),
            onDuplicate: () => _duplicatePlan(plan),
            onEdit: () => _beginEditPlan(plan),
            onDelete: () => _deletePlan(plan),
          );
        },
        separatorBuilder: (_, _) => const SizedBox(height: 12),
      ),
    );
  }

  Widget _buildCatalogTab(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 720;
        final isMedium = constraints.maxWidth >= 560;
        final recommendedCardWidth = isWide
            ? (constraints.maxWidth - AppSpacing.md) / 2
            : (constraints.maxWidth < 380 ? constraints.maxWidth - 8 : 280.0);

        return RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.md,
              AppSpacing.lg,
              AppSpacing.xl,
            ),
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
                onFeaturedChanged: (value) =>
                    setState(() => _featuredOnly = value),
                onApply: _load,
                onReset: _resetCatalogFilters,
              ),
              if (_recommendedBooks.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.lg),
                _CatalogSectionHeader(
                  title: 'Recommended',
                  subtitle: 'Ready-made programs selected for common goals.',
                  actionLabel: '${_recommendedBooks.length} picks',
                ),
                const SizedBox(height: AppSpacing.sm),
                if (isWide)
                  Wrap(
                    spacing: AppSpacing.md,
                    runSpacing: AppSpacing.md,
                    children: _recommendedBooks.map((book) {
                      final firstPlan =
                          (book['plans'] as List<dynamic>? ?? const []).isEmpty
                          ? const <String, dynamic>{}
                          : Map<String, dynamic>.from(
                              (book['plans'] as List).first as Map,
                            );

                      return SizedBox(
                        width: recommendedCardWidth,
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
                                  refreshMemberPlan: false,
                                ),
                        ),
                      );
                    }).toList(),
                  )
                else
                  SizedBox(
                    height: 112,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: _recommendedBooks.length,
                      separatorBuilder: (_, _) =>
                          const SizedBox(width: AppSpacing.md),
                      itemBuilder: (context, index) {
                        final book = _recommendedBooks[index];
                        final firstPlan =
                            (book['plans'] as List<dynamic>? ?? const [])
                                .isEmpty
                            ? const <String, dynamic>{}
                            : Map<String, dynamic>.from(
                                (book['plans'] as List).first as Map,
                              );
                        return SizedBox(
                          width: recommendedCardWidth,
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
                                    refreshMemberPlan: false,
                                  ),
                          ),
                        );
                      },
                    ),
                  ),
              ],
              const SizedBox(height: AppSpacing.lg),
              if (_books.isEmpty)
                _WorkoutBookEmptyStatePanel(
                  title: _catalogHasFilters
                      ? 'No programs match these filters'
                      : 'No programs are available yet',
                  message: _catalogHasFilters
                      ? 'Clear a filter or try a broader search.'
                      : 'Published workout programs will appear here when they are ready.',
                  icon: Icons.auto_stories_rounded,
                  actionLabel: _catalogHasFilters ? 'Clear filters' : null,
                  onAction: _catalogHasFilters ? _resetCatalogFilters : null,
                )
              else ...[
                _CatalogSectionHeader(
                  title: 'Program Catalog',
                  subtitle:
                      'Structured books with ready-to-preview weekly plans.',
                  actionLabel: '${_books.length} books',
                ),
                const SizedBox(height: AppSpacing.sm),
                ..._books.map((book) {
                  final plans = (book['plans'] as List<dynamic>? ?? const [])
                      .map((item) => Map<String, dynamic>.from(item as Map))
                      .toList();
                  final focusAreas =
                      (book['focus_areas'] as List<dynamic>? ?? const [])
                          .map((item) => item.toString())
                          .toList();

                  return Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.md),
                    child: _WorkoutBookCatalogCard(
                      book: book,
                      plans: plans,
                      focusAreas: focusAreas,
                      compact: !isMedium,
                      onPreview: (plan) => _showPlanPreview(
                        title: plan['name']?.toString() ?? 'Workout plan',
                        plan: plan,
                        primaryAction: () => _adoptPlan(plan),
                        primaryLabel: 'Add to library',
                        refreshMemberPlan: false,
                      ),
                    ),
                  );
                }),
                if (_bookPage.hasMore) ...[
                  const SizedBox(height: AppSpacing.xs),
                  _loadMoreButton(
                    label: 'Load more workout books',
                    loading: _loadingMoreBooks,
                    onPressed: _loadMoreBooks,
                  ),
                ],
              ],
            ],
          ),
        );
      },
    );
  }

  // ignore: unused_element
  Widget _buildBuilderTabLegacy(BuildContext context) {
    final totalExercises = _dayDrafts.fold<int>(
      0,
      (sum, day) => sum + day.exercises.length,
    );

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.md,
        AppSpacing.lg,
        AppSpacing.xl,
      ),
      children: [
        _WorkoutBuilderPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: _BuilderSectionHeading(
                      title: _editingPlanId == null
                          ? 'Build your own plan'
                          : 'Edit custom plan',
                      subtitle: _editingPlanId == null
                          ? 'Set the basics, add workout days, then keep each day clean and focused.'
                          : 'Refine the structure, volume, and exercise choices without leaving the builder.',
                    ),
                  ),
                  if (_editingPlanId != null) ...[
                    const SizedBox(width: 12),
                    OutlinedButton.icon(
                      onPressed: _resetCreator,
                      icon: const Icon(Icons.close_rounded),
                      label: const Text('Cancel'),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _BuilderInfoChip(
                    label: '${_dayDrafts.length} workout days',
                    icon: Icons.calendar_today_rounded,
                  ),
                  _BuilderInfoChip(
                    label: '$totalExercises exercise blocks',
                    icon: Icons.playlist_add_check_circle_rounded,
                  ),
                  _BuilderInfoChip(
                    label:
                        '${_durationController.text.trim().isEmpty ? '4' : _durationController.text.trim()} weeks',
                    icon: Icons.timelapse_rounded,
                  ),
                ],
              ),
              const SizedBox(height: 16),
              _BuilderSubsection(
                title: 'Plan details',
                child: Column(
                  children: [
                    TextField(
                      controller: _nameController,
                      decoration: const InputDecoration(labelText: 'Plan name'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _goalController,
                      decoration: const InputDecoration(
                        labelText: 'Primary goal',
                      ),
                    ),
                    const SizedBox(height: 12),
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final compact = constraints.maxWidth < 680;
                        final fields = [
                          DropdownButtonFormField<String>(
                            isExpanded: true,
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
                              labelText: 'Minutes per session',
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
              gradient: const [Color(0xFFFFFFFF), Color(0xFFF9FBFF)],
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
                            _BuilderInfoChip(
                              label: 'Workout day ${index + 1}',
                              icon: Icons.event_note_rounded,
                            ),
                            const SizedBox(height: 10),
                            Text(
                              day.labelController.text.trim().isEmpty
                                  ? 'Day ${index + 1} setup'
                                  : day.labelController.text.trim(),
                              style: Theme.of(context).textTheme.titleLarge
                                  ?.copyWith(
                                    color: AppColors.textPrimary,
                                    fontWeight: FontWeight.w800,
                                  ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              day.focusController.text.trim().isEmpty
                                  ? 'Define the session focus and keep the exercises aligned to one goal.'
                                  : day.focusController.text.trim(),
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(color: AppColors.textSecondary),
                            ),
                          ],
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
                  const SizedBox(height: 14),
                  _BuilderSubsection(
                    title: 'Day details',
                    child: Column(
                      children: [
                        LayoutBuilder(
                          builder: (context, constraints) {
                            final compact = constraints.maxWidth < 520;
                            final weekdayField = DropdownButtonFormField<int>(
                              isExpanded: true,
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
                            );
                            final labelField = TextField(
                              controller: day.labelController,
                              decoration: const InputDecoration(
                                labelText: 'Label',
                              ),
                            );
                            if (compact) {
                              return Column(
                                children: [
                                  weekdayField,
                                  const SizedBox(height: 12),
                                  labelField,
                                ],
                              );
                            }
                            return Row(
                              children: [
                                Expanded(child: weekdayField),
                                const SizedBox(width: 12),
                                Expanded(child: labelField),
                              ],
                            );
                          },
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: day.focusController,
                          decoration: const InputDecoration(
                            labelText: 'Session focus',
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: day.notesController,
                          decoration: const InputDecoration(
                            labelText: 'Coach note',
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  _BuilderSubsection(
                    title: 'Exercises',
                    child: Column(
                      children: [
                        ...day.exercises.asMap().entries.map((exerciseEntry) {
                          final exerciseIndex = exerciseEntry.key;
                          final exerciseDraft = exerciseEntry.value;
                          final bodyPart =
                              exerciseDraft.bodyPart ??
                              (_exerciseGroups.isEmpty
                                  ? null
                                  : _exerciseGroups.keys.first);
                          final exerciseOptions = _exercisesForBodyPart(
                            bodyPart,
                          );
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
                          final bodyPartOptions = [
                            ..._exerciseGroups.keys,
                            if (currentBodyPart != null &&
                                !_exerciseGroups.containsKey(currentBodyPart))
                              currentBodyPart,
                          ];
                          final savedExercise = _exerciseById(
                            exerciseDraft.exerciseId,
                          );
                          final currentExerciseOptions = [
                            ..._exercisesForBodyPart(currentBodyPart),
                            if (exerciseDraft.exerciseId != null &&
                                !_exercisesForBodyPart(currentBodyPart).any(
                                  (item) =>
                                      (item['id'] as num?)?.toInt() ==
                                      exerciseDraft.exerciseId,
                                ))
                              savedExercise ??
                                  {
                                    'id': exerciseDraft.exerciseId,
                                    'name':
                                        'Saved exercise #${exerciseDraft.exerciseId}',
                                  },
                          ];
                          final selectedExerciseMeta = _exerciseById(
                            exerciseDraft.exerciseId,
                          );

                          return Padding(
                            padding: EdgeInsets.only(
                              bottom: exerciseIndex == day.exercises.length - 1
                                  ? 0
                                  : 12,
                            ),
                            child: _BuilderExerciseCard(
                              index: exerciseIndex,
                              onRemove: () {
                                setState(() {
                                  day.exercises
                                      .removeAt(exerciseIndex)
                                      .dispose();
                                  if (day.exercises.isEmpty) {
                                    day.exercises.add(_buildExerciseDraft());
                                  }
                                });
                              },
                              child: Column(
                                children: [
                                  LayoutBuilder(
                                    builder: (context, constraints) {
                                      final compact =
                                          constraints.maxWidth < 720;
                                      final bodyPartField =
                                          DropdownButtonFormField<String>(
                                            isExpanded: true,
                                            initialValue: currentBodyPart,
                                            decoration: const InputDecoration(
                                              labelText: 'Body part',
                                            ),
                                            items: bodyPartOptions
                                                .map(
                                                  (group) =>
                                                      DropdownMenuItem<String>(
                                                        value: group,
                                                        child: Text(
                                                          _bodyPartLabel(group),
                                                        ),
                                                      ),
                                                )
                                                .toList(),
                                            onChanged: (value) {
                                              setState(() {
                                                exerciseDraft.bodyPart = value;
                                                exerciseDraft.exerciseId = null;
                                              });
                                            },
                                          );
                                      final exerciseField =
                                          DropdownButtonFormField<int>(
                                            isExpanded: true,
                                            initialValue:
                                                exerciseDraft.exerciseId,
                                            decoration: const InputDecoration(
                                              labelText: 'Exercise',
                                            ),
                                            items: currentExerciseOptions
                                                .map(
                                                  (
                                                    exercise,
                                                  ) => DropdownMenuItem<int>(
                                                    value:
                                                        (exercise['id'] as num?)
                                                            ?.toInt(),
                                                    child: Text(
                                                      _exerciseDisplayName(
                                                        exercise,
                                                      ),
                                                      overflow:
                                                          TextOverflow.ellipsis,
                                                    ),
                                                  ),
                                                )
                                                .toList(),
                                            onChanged: (value) {
                                              final selectedExercise =
                                                  _exerciseById(value);
                                              setState(() {
                                                exerciseDraft.exerciseId =
                                                    value;
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
                                            const SizedBox(height: 10),
                                            exerciseField,
                                          ],
                                        );
                                      }
                                      return Row(
                                        children: [
                                          Expanded(child: bodyPartField),
                                          const SizedBox(width: 10),
                                          Expanded(
                                            flex: 2,
                                            child: exerciseField,
                                          ),
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
                                      final compact =
                                          constraints.maxWidth < 900;
                                      final setsField = TextField(
                                        controller:
                                            exerciseDraft.setsController,
                                        keyboardType: TextInputType.number,
                                        decoration: const InputDecoration(
                                          labelText: 'Working sets',
                                        ),
                                      );
                                      final rangeField =
                                          DropdownButtonFormField<String>(
                                            isExpanded: true,
                                            initialValue:
                                                exerciseDraft.repPreset,
                                            decoration: const InputDecoration(
                                              labelText: 'Rep range',
                                            ),
                                            items: _repRangeOptions.entries
                                                .map(
                                                  (entry) =>
                                                      DropdownMenuItem<String>(
                                                        value: entry.key,
                                                        child: Text(
                                                          entry.value,
                                                        ),
                                                      ),
                                                )
                                                .toList(),
                                            onChanged: (value) {
                                              setState(() {
                                                exerciseDraft.repPreset =
                                                    value ?? 'custom';
                                                if (exerciseDraft.repPreset !=
                                                    'custom') {
                                                  exerciseDraft
                                                          .repsController
                                                          .text =
                                                      exerciseDraft.repPreset;
                                                }
                                              });
                                            },
                                          );
                                      final repsField = TextField(
                                        controller:
                                            exerciseDraft.repsController,
                                        decoration: InputDecoration(
                                          labelText:
                                              exerciseDraft.repPreset ==
                                                  'custom'
                                              ? 'Custom reps'
                                              : 'Rep target',
                                        ),
                                        enabled:
                                            exerciseDraft.repPreset == 'custom',
                                        onChanged: (value) {
                                          final preset = _repPresetFor(value);
                                          if (preset == 'custom' ||
                                              value !=
                                                  exerciseDraft.repPreset) {
                                            setState(
                                              () => exerciseDraft.repPreset =
                                                  preset,
                                            );
                                          }
                                        },
                                      );
                                      final restField = TextField(
                                        controller:
                                            exerciseDraft.restController,
                                        keyboardType: TextInputType.number,
                                        decoration: const InputDecoration(
                                          labelText: 'Rest seconds',
                                        ),
                                      );
                                      if (compact) {
                                        return Column(
                                          children: [
                                            setsField,
                                            const SizedBox(height: 10),
                                            rangeField,
                                            const SizedBox(height: 10),
                                            repsField,
                                            const SizedBox(height: 10),
                                            restField,
                                          ],
                                        );
                                      }
                                      return Row(
                                        children: [
                                          Expanded(child: setsField),
                                          const SizedBox(width: 10),
                                          Expanded(child: rangeField),
                                          const SizedBox(width: 10),
                                          Expanded(child: repsField),
                                          const SizedBox(width: 10),
                                          Expanded(child: restField),
                                        ],
                                      );
                                    },
                                  ),
                                  const SizedBox(height: 10),
                                  TextField(
                                    controller: exerciseDraft.notesController,
                                    decoration: const InputDecoration(
                                      labelText: 'Exercise note',
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        }),
                        const SizedBox(height: 10),
                        Align(
                          alignment: Alignment.centerLeft,
                          child: OutlinedButton.icon(
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
                ],
              ),
            ),
          );
        }),
        _WorkoutBuilderPanel(
          gradient: const [Color(0xFFF8FBFF), Color(0xFFFFFFFF)],
          child: Column(
            children: [
              _WorkoutBuilderAddButton(
                label: 'Add workout day',
                icon: Icons.calendar_month_rounded,
                onTap: () => setState(() => _dayDrafts.add(_PlanDayDraft())),
              ),
              const SizedBox(height: 10),
              GradientButton(
                label: _saving
                    ? (_editingPlanId == null
                          ? 'Saving plan...'
                          : 'Updating plan...')
                    : (_editingPlanId == null
                          ? 'Save custom workout plan'
                          : 'Update workout plan'),
                icon: Icons.save_rounded,
                expanded: true,
                loading: _saving,
                onPressed: _saving ? null : _savePlan,
              ),
            ],
          ),
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
    return PremiumCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              color: gradient.first.withValues(alpha: 0.14),
            ),
            child: Icon(icon, color: gradient.last),
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

class _WorkoutBookLoadingState extends StatelessWidget {
  const _WorkoutBookLoadingState();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Padding(
        padding: EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: AppSpacing.md),
            Text('Loading your workout book...', textAlign: TextAlign.center),
          ],
        ),
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
    this.secondaryActionLabel,
    this.onSecondaryAction,
  });

  final String title;
  final String message;
  final IconData icon;
  final String? actionLabel;
  final VoidCallback? onAction;
  final String? secondaryActionLabel;
  final VoidCallback? onSecondaryAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: PremiumCard(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(22),
                color: AppColors.primary.withValues(alpha: 0.12),
              ),
              child: Icon(icon, color: AppColors.primary, size: 30),
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
            if (secondaryActionLabel != null && onSecondaryAction != null) ...[
              const SizedBox(height: 8),
              TextButton.icon(
                onPressed: onSecondaryAction,
                icon: const Icon(Icons.add_task_rounded),
                label: Text(secondaryActionLabel!),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _WorkoutBuilderSaveBar extends StatelessWidget {
  const _WorkoutBuilderSaveBar({
    required this.saving,
    required this.editing,
    required this.onSave,
  });

  final bool saving;
  final bool editing;
  final VoidCallback? onSave;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg,
          AppSpacing.sm,
          AppSpacing.lg,
          AppSpacing.sm,
        ),
        decoration: BoxDecoration(
          color: AppColors.surface.withValues(alpha: 0.97),
          border: const Border(top: BorderSide(color: AppColors.stroke)),
        ),
        child: GradientButton(
          label: saving
              ? (editing ? 'Updating workout...' : 'Saving workout...')
              : (editing ? 'Update workout plan' : 'Save to My Plans'),
          icon: editing
              ? Icons.save_as_outlined
              : Icons.library_add_check_rounded,
          expanded: true,
          loading: saving,
          onPressed: onSave,
        ),
      ),
    );
  }
}

InputDecoration _memberWorkoutInputDecoration(String label, {IconData? icon}) {
  return InputDecoration(
    labelText: label,
    prefixIcon: icon == null ? null : Icon(icon, size: 20),
    filled: true,
    fillColor: AppColors.surfaceSoft,
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(16),
      borderSide: const BorderSide(color: AppColors.stroke),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(16),
      borderSide: const BorderSide(color: AppColors.stroke),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(16),
      borderSide: const BorderSide(color: AppColors.primary, width: 1.4),
    ),
  );
}

class _MemberBuilderExerciseTile extends StatelessWidget {
  const _MemberBuilderExerciseTile({
    required this.title,
    required this.subtitle,
    required this.badge,
    required this.moveLabel,
    required this.onMove,
    required this.removeLabel,
    required this.onRemove,
  });

  final String title;
  final String subtitle;
  final String? badge;
  final String moveLabel;
  final VoidCallback? onMove;
  final String removeLabel;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.fitness_center_rounded,
                  color: AppColors.primary,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (badge != null && badge!.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: _FocusChip(label: badge!),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            alignment: WrapAlignment.end,
            spacing: 4,
            children: [
              TextButton.icon(
                onPressed: onMove,
                icon: Icon(
                  moveLabel == 'Move up'
                      ? Icons.arrow_upward_rounded
                      : Icons.arrow_downward_rounded,
                  size: 18,
                ),
                label: Text(moveLabel),
              ),
              TextButton.icon(
                onPressed: onRemove,
                icon: const Icon(Icons.remove_circle_outline_rounded, size: 18),
                label: Text(removeLabel),
              ),
            ],
          ),
        ],
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
    return PremiumCard(
      padding: const EdgeInsets.all(18),
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(22),
          gradient: LinearGradient(
            colors: [
              gradient.first.withValues(alpha: 0.45),
              gradient.last.withValues(alpha: 0.18),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: child,
        ),
      ),
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
    return OutlinedButton.icon(
      onPressed: onTap,
      icon: Icon(icon, color: AppColors.primary),
      label: Text(
        label,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
          color: AppColors.primary,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _BuilderSectionHeading extends StatelessWidget {
  const _BuilderSectionHeading({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          subtitle,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            color: AppColors.textSecondary,
            height: 1.35,
          ),
        ),
      ],
    );
  }
}

class _BuilderInfoChip extends StatelessWidget {
  const _BuilderInfoChip({required this.label, required this.icon});

  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: AppColors.primary),
          const SizedBox(width: 8),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _BuilderSubsection extends StatelessWidget {
  const _BuilderSubsection({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _BuilderExerciseCard extends StatelessWidget {
  const _BuilderExerciseCard({
    required this.index,
    required this.onRemove,
    required this.child,
  });

  final int index;
  final VoidCallback onRemove;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _BuilderInfoChip(
                label: 'Exercise ${index + 1}',
                icon: Icons.fitness_center_rounded,
              ),
              const Spacer(),
              TextButton.icon(
                onPressed: onRemove,
                icon: const Icon(Icons.remove_circle_outline_rounded),
                label: const Text('Remove'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          child,
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
        color: AppColors.surfaceSoft,
        border: Border.all(color: AppColors.stroke),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: AppColors.textSecondary,
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
    required this.sharing,
    required this.onStart,
    required this.onPreview,
    required this.onShare,
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
  final bool sharing;
  final VoidCallback onStart;
  final VoidCallback onPreview;
  final VoidCallback onShare;
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
    return PremiumCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      glowColor: originColor,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(20),
                  color: originColor.withValues(alpha: 0.12),
                ),
                child: Icon(Icons.fitness_center_rounded, color: originColor),
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
                label: sharing ? 'Sharing...' : 'Share',
                icon: Icons.ios_share_rounded,
                onTap: saving || sharing ? null : onShare,
              ),
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
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.stroke),
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
    return Opacity(
      opacity: onTap == null ? 0.48 : 1,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 44),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(999),
          child: Container(
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(999),
              border: Border.all(
                color: danger
                    ? AppColors.error.withValues(alpha: 0.22)
                    : AppColors.strokeStrong,
              ),
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
    required this.onReset,
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
  final VoidCallback onReset;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(16),
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
                      'Find a program',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Search by goal, style, or difficulty.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              TextButton(onPressed: onReset, child: const Text('Reset')),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: searchController,
            decoration: InputDecoration(
              labelText: 'Search books or goals',
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
              final compact = constraints.maxWidth < 560;
              final fields = <Widget>[
                DropdownButtonFormField<String?>(
                  isExpanded: true,
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
                  isExpanded: true,
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
          LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxWidth < 420;
              final featuredSwitch = SwitchListTile.adaptive(
                value: featuredOnly,
                onChanged: onFeaturedChanged,
                contentPadding: EdgeInsets.zero,
                dense: true,
                title: const Text('Featured only'),
              );
              final apply = GradientButton(
                label: 'Show programs',
                icon: Icons.tune_rounded,
                expanded: compact,
                onPressed: onApply,
              );
              if (compact) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [featuredSwitch, const SizedBox(height: 8), apply],
                );
              }
              return Row(
                children: [
                  Expanded(child: featuredSwitch),
                  const SizedBox(width: 12),
                  SizedBox(width: 220, child: apply),
                ],
              );
            },
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
    final label = book['name']?.toString() ?? 'Workout book';
    final goal = book['goal']?.toString() ?? 'Goal aligned training';
    final difficulty = book['difficulty']?.toString();
    final daysPerWeek = book['days_per_week']?.toString();
    final subtitleParts = <String>[
      goal,
      if (difficulty != null && difficulty.isNotEmpty) difficulty,
      if (daysPerWeek != null && daysPerWeek.isNotEmpty)
        '$daysPerWeek days/week',
    ];

    return Opacity(
      opacity: enabled ? 1 : 0.56,
      child: QuickActionCard(
        label: label,
        subtitle: subtitleParts.join(' • '),
        icon: Icons.auto_awesome_rounded,
        onTap: enabled ? onPreview : null,
      ),
    );
  }
}

class _WorkoutBookCatalogCard extends StatelessWidget {
  const _WorkoutBookCatalogCard({
    required this.book,
    required this.plans,
    required this.focusAreas,
    required this.compact,
    required this.onPreview,
  });

  final Map<String, dynamic> book;
  final List<Map<String, dynamic>> plans;
  final List<String> focusAreas;
  final bool compact;
  final ValueChanged<Map<String, dynamic>> onPreview;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(16),
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
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      book['description']?.toString() ??
                          'No description available.',
                      maxLines: compact ? 2 : 3,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
              if (book['is_featured'] == true) ...[
                const SizedBox(width: 8),
                const StatusBadge(label: 'Featured', color: Color(0xFFFF8D77)),
              ],
            ],
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
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: focusAreas
                  .take(5)
                  .map((focus) => _FocusChip(label: focus))
                  .toList(),
            ),
          ],
          const SizedBox(height: 14),
          ...plans.map(
            (plan) => Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: EdgeInsets.all(compact ? 12 : 14),
              decoration: BoxDecoration(
                color: AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.stroke),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: compact ? 40 : 44,
                    height: compact ? 40 : 44,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(14),
                      color: AppColors.primary.withValues(alpha: 0.10),
                    ),
                    child: const Icon(
                      Icons.play_arrow_rounded,
                      color: AppColors.primary,
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
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${plan['total_workout_days'] ?? '--'} days • ${plan['estimated_session_minutes'] ?? '--'} min/session',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: AppColors.textSecondary),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  TextButton(
                    onPressed: () => onPreview(plan),
                    style: TextButton.styleFrom(
                      minimumSize: const Size(0, 44),
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                    ),
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

class _PlanDayDraft {
  _PlanDayDraft({this.weekday});

  final TextEditingController labelController = TextEditingController();
  final TextEditingController focusController = TextEditingController();
  final TextEditingController notesController = TextEditingController();
  int? weekday;
  final List<_PlanExerciseDraft> exercises = <_PlanExerciseDraft>[];

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
  String trackingMode = 'reps';
  bool isPerSide = false;
  bool isBodyweight = false;
  String? groupKey;
  String? groupType;
  int? groupOrder;
  int? groupRounds;
  int? transitionSeconds;
  String progressionPolicy = 'off';
  int? progressionMinReps;
  int? progressionMaxReps;
  double? progressionIncrementKg;
  int? progressionDeloadAfterMisses;
  double? progressionDeloadPercent;
  final TextEditingController setsController = TextEditingController(text: '3');
  final TextEditingController repsController = TextEditingController(
    text: '8-12',
  );
  final TextEditingController targetWeightController = TextEditingController();
  final TextEditingController restController = TextEditingController(
    text: '60',
  );
  final TextEditingController durationSecondsController =
      TextEditingController();
  final TextEditingController distanceMetersController =
      TextEditingController();
  final TextEditingController speedKphController = TextEditingController();
  final TextEditingController paceSecondsController = TextEditingController();
  final TextEditingController notesController = TextEditingController();

  int get sets => int.tryParse(setsController.text.trim()) ?? 3;
  String get reps =>
      repsController.text.trim().isEmpty ? '10' : repsController.text.trim();
  int get restSeconds => int.tryParse(restController.text.trim()) ?? 60;
  int? get durationSeconds =>
      int.tryParse(durationSecondsController.text.trim());
  double? get distanceMeters =>
      double.tryParse(distanceMetersController.text.trim());
  double? get speedKph => double.tryParse(speedKphController.text.trim());
  int? get paceSeconds => int.tryParse(paceSecondsController.text.trim());
  double? get targetWeight => targetWeightController.text.trim().isEmpty
      ? null
      : double.tryParse(targetWeightController.text.trim());
  String? get notes =>
      notesController.text.trim().isEmpty ? null : notesController.text.trim();

  void dispose() {
    setsController.dispose();
    repsController.dispose();
    targetWeightController.dispose();
    restController.dispose();
    durationSecondsController.dispose();
    distanceMetersController.dispose();
    speedKphController.dispose();
    paceSecondsController.dispose();
    notesController.dispose();
  }
}

class _WorkoutBookTopBar extends StatelessWidget {
  const _WorkoutBookTopBar({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.md,
        AppSpacing.lg,
        AppSpacing.md,
      ),
      child: Row(
        children: [
          Semantics(
            button: true,
            label: 'Back to training',
            child: Tooltip(
              message: 'Back to training',
              child: InkWell(
                onTap: () => Navigator.of(context).maybePop(),
                borderRadius: BorderRadius.circular(16),
                child: Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppColors.stroke),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.04),
                        blurRadius: 10,
                        offset: const Offset(0, 6),
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.arrow_back_rounded,
                    color: AppColors.textPrimary,
                    size: 20,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
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

class WorkoutBookPreviewSheet extends StatelessWidget {
  const WorkoutBookPreviewSheet({
    super.key,
    required this.title,
    required this.planDetail,
    required this.days,
    required this.primaryAction,
    required this.primaryLabel,
  });

  final String title;
  final Map<String, dynamic> planDetail;
  final List<Map<String, dynamic>> days;
  final VoidCallback primaryAction;
  final String primaryLabel;

  @override
  Widget build(BuildContext context) {
    final totalExercises =
        (planDetail['total_exercises'] as num?)?.toInt() ??
        days.fold<int>(
          0,
          (sum, day) =>
              sum + (day['exercises'] as List<dynamic>? ?? const []).length,
        );
    final estimatedMinutes = (planDetail['estimated_session_minutes'] as num?)
        ?.toInt();
    final difficulty = planDetail['difficulty']?.toString();
    final goal =
        planDetail['goal']?.toString() ?? 'Structured training plan preview';

    return FractionallySizedBox(
      heightFactor: 0.92,
      child: Container(
        decoration: const BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
        child: SafeArea(
          top: false,
          child: Column(
            children: [
              Container(
                width: 52,
                height: 5,
                margin: const EdgeInsets.only(top: 10, bottom: 10),
                decoration: BoxDecoration(
                  color: AppColors.strokeStrong,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg,
                    AppSpacing.sm,
                    AppSpacing.lg,
                    AppSpacing.lg,
                  ),
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            style: Theme.of(context).textTheme.headlineSmall
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                        ),
                        MemberHeaderActionButton(
                          icon: Icons.close_rounded,
                          onTap: () => Navigator.of(context).pop(),
                          tooltip: 'Close preview',
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    PremiumCard(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 7,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.surfaceSoft,
                              borderRadius: BorderRadius.circular(999),
                              border: Border.all(color: AppColors.stroke),
                            ),
                            child: Text(
                              'PLAN PREVIEW',
                              style: Theme.of(context).textTheme.labelSmall
                                  ?.copyWith(
                                    color: AppColors.primaryBright,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: 0.8,
                                  ),
                            ),
                          ),
                          const SizedBox(height: 14),
                          Text(
                            goal,
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w900,
                                  height: 1.0,
                                ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Review the weekly schedule, exercise order, and session workload before you add or start this plan.',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: AppColors.textSecondary,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                          const SizedBox(height: 16),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              _WorkoutBookPreviewChip(
                                icon: Icons.calendar_view_week_rounded,
                                label: '${days.length} days',
                              ),
                              _WorkoutBookPreviewChip(
                                icon: Icons.fitness_center_rounded,
                                label: '$totalExercises exercises',
                              ),
                              if (estimatedMinutes != null)
                                _WorkoutBookPreviewChip(
                                  icon: Icons.timer_outlined,
                                  label: '$estimatedMinutes min',
                                ),
                              if (difficulty != null && difficulty.isNotEmpty)
                                _WorkoutBookPreviewChip(
                                  icon: Icons.local_fire_department_rounded,
                                  label: difficulty,
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    if (days.isEmpty)
                      PremiumCard(
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            const Icon(
                              Icons.calendar_view_week_outlined,
                              color: AppColors.primaryBright,
                            ),
                            const SizedBox(width: AppSpacing.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  Text(
                                    'Schedule details unavailable',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleSmall
                                        ?.copyWith(
                                          color: AppColors.textPrimary,
                                          fontWeight: FontWeight.w800,
                                        ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'You can still continue to the workout and review the available plan details there.',
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: AppColors.textSecondary,
                                          height: 1.4,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      )
                    else
                      ...days.asMap().entries.map((entry) {
                        return Padding(
                          padding: EdgeInsets.only(
                            bottom: entry.key == days.length - 1
                                ? 0
                                : AppSpacing.md,
                          ),
                          child: _WorkoutBookPreviewDayCard(day: entry.value),
                        );
                      }),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.lg,
                  AppSpacing.sm,
                  AppSpacing.lg,
                  AppSpacing.lg,
                ),
                decoration: BoxDecoration(
                  color: AppColors.surface.withValues(alpha: 0.97),
                  border: Border(top: BorderSide(color: AppColors.stroke)),
                ),
                child: GradientButton(
                  label: primaryLabel,
                  icon: primaryLabel.toLowerCase().contains('add')
                      ? Icons.library_add_rounded
                      : Icons.play_arrow_rounded,
                  expanded: true,
                  onPressed: () {
                    Navigator.of(context).pop();
                    primaryAction();
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _WorkoutBookPreviewChip extends StatelessWidget {
  const _WorkoutBookPreviewChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: AppColors.primaryBright),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WorkoutBookPreviewDayCard extends StatelessWidget {
  const _WorkoutBookPreviewDayCard({required this.day});

  final Map<String, dynamic> day;

  @override
  Widget build(BuildContext context) {
    final exercises = (day['exercises'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final focus = day['focus']?.toString() ?? '';

    return PremiumCard(
      padding: const EdgeInsets.all(18),
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
                alignment: Alignment.center,
                child: Text(
                  '${day['day_number'] ?? 1}',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.primaryBright,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      day['label']?.toString() ?? 'Workout day',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      focus.isEmpty
                          ? '${exercises.length} exercises planned'
                          : focus,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              StatusBadge(
                label: '${exercises.length} items',
                color: AppColors.primaryBright,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          ...exercises.asMap().entries.map((entry) {
            final exercise = entry.value;
            final exerciseMap = Map<String, dynamic>.from(
              exercise['exercise'] as Map? ?? const {},
            );
            final isLast = entry.key == exercises.length - 1;
            return Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : AppSpacing.sm),
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 30,
                      height: 30,
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppColors.stroke),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        '${exercise['sort_order'] ?? entry.key + 1}',
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(
                              color: AppColors.primaryBright,
                              fontWeight: FontWeight.w900,
                            ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            exerciseMap['name']?.toString() ?? 'Exercise',
                            style: Theme.of(context).textTheme.titleSmall
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${exercise['sets'] ?? '--'} sets • ${exercise['reps'] ?? '--'} reps • ${exercise['rest_seconds'] ?? 60}s rest',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: AppColors.textSecondary,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          }),
        ],
      ),
    );
  }
}

class _CatalogSectionHeader extends StatelessWidget {
  const _CatalogSectionHeader({
    required this.title,
    required this.subtitle,
    this.actionLabel,
  });

  final String title;
  final String subtitle;
  final String? actionLabel;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
        if ((actionLabel ?? '').trim().isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(left: 12),
            child: Text(
              actionLabel!,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: AppColors.textMuted,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
      ],
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
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: AppColors.surfaceOverlay,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke),
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
    return Semantics(
      button: true,
      selected: active,
      label: label,
      child: Tooltip(
        message: label,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(999),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOutCubic,
            margin: const EdgeInsets.symmetric(horizontal: 2),
            padding: EdgeInsets.symmetric(
              horizontal: compact ? 8 : 12,
              vertical: 11,
            ),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(999),
              color: active ? AppColors.primary : Colors.transparent,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  icon,
                  size: compact ? 18 : 18,
                  color: active ? Colors.white : AppColors.textSecondary,
                ),
                if (!compact) ...[
                  const SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: active ? Colors.white : AppColors.textSecondary,
                        fontWeight: active ? FontWeight.w800 : FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
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
  return userFacingError(exception);
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
