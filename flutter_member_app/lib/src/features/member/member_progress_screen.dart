import 'dart:convert';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:gym_flutter_core/metric_trend_chart.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:share_plus/share_plus.dart' hide XFile;

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import '../../core/pagination.dart';
import 'member_repository.dart';

// Keep the complete progress-photo implementation available, but hide its UI.
const bool _showProgressPhotos = false;

// Keep backup/export and history-import support available for future migration
// or support workflows, but do not expose these technical tools to members.
const bool _showWorkoutDataTools = false;

class MemberProgressScreen extends StatefulWidget {
  const MemberProgressScreen({
    super.key,
    required this.repository,
    required this.initialSummary,
    required this.onRefreshParent,
    this.memberName = 'Athlete',
  });

  final MemberRepository repository;
  final Map<String, dynamic> initialSummary;
  final Future<void> Function() onRefreshParent;
  final String memberName;

  @override
  State<MemberProgressScreen> createState() => _MemberProgressScreenState();
}

class _MemberProgressScreenState extends State<MemberProgressScreen>
    with SingleTickerProviderStateMixin {
  bool _loading = true;
  bool _savingWeight = false;
  bool _savingMeasurement = false;
  bool _savingPhoto = false;
  bool _loadingMoreWeight = false;
  bool _loadingMoreMeasurements = false;
  bool _loadingMorePhotos = false;
  String? _error;
  String? _lastSuccessMessage;
  Map<String, dynamic> _summary = const {};
  Map<String, dynamic> _workoutAnalytics = const {};
  Map<String, dynamic> _workoutPreferences = const {};
  List<Map<String, dynamic>> _stepSummary = const [];
  List<Map<String, dynamic>> _weightLogs = const [];
  List<Map<String, dynamic>> _bodyMeasurements = const [];
  List<Map<String, dynamic>> _photos = const [];
  ApiPagination _weightPage = const ApiPagination.singlePage();
  ApiPagination _measurementPage = const ApiPagination.singlePage();
  ApiPagination _photoPage = const ApiPagination.singlePage();

  final _weightController = TextEditingController();
  final _weightNotesController = TextEditingController();
  final _chestController = TextEditingController();
  final _waistController = TextEditingController();
  final _hipsController = TextEditingController();
  final _armController = TextEditingController();
  final _thighController = TextEditingController();
  final _calfController = TextEditingController();
  final _bodyFatController = TextEditingController();
  final _measurementNotesController = TextEditingController();
  final _photoNotesController = TextEditingController();
  final ImagePicker _imagePicker = ImagePicker();
  late final TabController _tabController;
  String _photoType = 'front';
  XFile? _selectedPhoto;
  Uint8List? _selectedPhotoBytes;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(
      length: _showProgressPhotos ? 6 : 5,
      vsync: this,
    );
    _summary = widget.initialSummary;
    _load();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _weightController.dispose();
    _weightNotesController.dispose();
    _chestController.dispose();
    _waistController.dispose();
    _hipsController.dispose();
    _armController.dispose();
    _thighController.dispose();
    _calfController.dispose();
    _bodyFatController.dispose();
    _measurementNotesController.dispose();
    _photoNotesController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final results = await Future.wait<Map<String, dynamic>>([
        widget.repository.fetchProgressSummary(),
        widget.repository.fetchWeightLogs(),
        widget.repository.fetchBodyMeasurements(),
        widget.repository.fetchPhotos(),
        widget.repository.fetchStepSummary(range: '7d'),
      ]);

      _summary = Map<String, dynamic>.from(
        results[0]['data'] as Map? ?? const {},
      );
      _weightLogs = apiPageItems(results[1]);
      _bodyMeasurements = apiPageItems(results[2]);
      _photos = apiPageItems(results[3]);
      _weightPage = ApiPagination.fromResponse(results[1]);
      _measurementPage = ApiPagination.fromResponse(results[2]);
      _photoPage = ApiPagination.fromResponse(results[3]);
      _stepSummary = (results[4]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      try {
        final workoutResults = await Future.wait([
          widget.repository.fetchWorkoutAnalytics(),
          widget.repository.fetchWorkoutPreferences(),
        ]);
        _workoutAnalytics = Map<String, dynamic>.from(
          workoutResults[0]['data'] as Map? ?? const {},
        );
        _workoutPreferences = Map<String, dynamic>.from(
          workoutResults[1]['data'] as Map? ?? const {},
        );
      } catch (_) {
        // Preserve existing progress during a rolling backend/app deployment.
      }
    } catch (exception) {
      _error =
          'We could not load your progress right now. Check your connection and try again.';
      debugPrint('[member-progress] initial load failed: $exception');
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _loadMoreWeight() async {
    if (_loadingMoreWeight || !_weightPage.hasMore) return;
    setState(() => _loadingMoreWeight = true);
    try {
      final response = await widget.repository.fetchWeightLogs(
        page: _weightPage.nextPage,
      );
      _weightLogs = mergeApiPageItems(_weightLogs, apiPageItems(response));
      _weightPage = ApiPagination.fromResponse(response);
    } catch (exception) {
      _showLoadMoreError('weight history', exception);
    } finally {
      if (mounted) setState(() => _loadingMoreWeight = false);
    }
  }

  Future<void> _loadMoreMeasurements() async {
    if (_loadingMoreMeasurements || !_measurementPage.hasMore) return;
    setState(() => _loadingMoreMeasurements = true);
    try {
      final response = await widget.repository.fetchBodyMeasurements(
        page: _measurementPage.nextPage,
      );
      _bodyMeasurements = mergeApiPageItems(
        _bodyMeasurements,
        apiPageItems(response),
      );
      _measurementPage = ApiPagination.fromResponse(response);
    } catch (exception) {
      _showLoadMoreError('measurement history', exception);
    } finally {
      if (mounted) setState(() => _loadingMoreMeasurements = false);
    }
  }

  Future<void> _loadMorePhotos() async {
    if (_loadingMorePhotos || !_photoPage.hasMore) return;
    setState(() => _loadingMorePhotos = true);
    try {
      final response = await widget.repository.fetchPhotos(
        page: _photoPage.nextPage,
      );
      _photos = mergeApiPageItems(_photos, apiPageItems(response));
      _photoPage = ApiPagination.fromResponse(response);
    } catch (exception) {
      _showLoadMoreError('photo history', exception);
    } finally {
      if (mounted) setState(() => _loadingMorePhotos = false);
    }
  }

  void _showLoadMoreError(String historyName, Object exception) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Could not load older $historyName. Please try again.'),
      ),
    );
    debugPrint('[member-progress] load more failed: $exception');
  }

  void _showActionError(String action, Object exception) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Could not $action. Please try again.')),
    );
    debugPrint('[member-progress] $action failed: $exception');
  }

  Future<void> _afterSave(String message) async {
    await _load();
    await widget.onRefreshParent();
    if (!mounted) {
      return;
    }
    setState(() => _lastSuccessMessage = message);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _editWorkoutPreferences() async {
    final target = TextEditingController(
      text: _workoutPreferences['target_weight_kg']?.toString() ?? '',
    );
    final minutes = TextEditingController(
      text: _workoutPreferences['reminder_minutes_before']?.toString() ?? '60',
    );
    final workoutTimeValue =
        _workoutPreferences['default_workout_time']?.toString() ?? '18:00';
    final workoutTime = TextEditingController(
      text: workoutTimeValue.length >= 5
          ? workoutTimeValue.substring(0, 5)
          : '18:00',
    );
    final quietStartValue =
        _workoutPreferences['quiet_hours_start']?.toString() ?? '';
    final quietEndValue =
        _workoutPreferences['quiet_hours_end']?.toString() ?? '';
    final quietStart = TextEditingController(
      text: quietStartValue.length >= 5 ? quietStartValue.substring(0, 5) : '',
    );
    final quietEnd = TextEditingController(
      text: quietEndValue.length >= 5 ? quietEndValue.substring(0, 5) : '',
    );
    var showGoal = _workoutPreferences['show_weight_goal'] != false;
    var reminderEnabled =
        _workoutPreferences['scheduled_workout_reminder_enabled'] == true;
    var missedFollowUp =
        _workoutPreferences['missed_workout_follow_up_enabled'] == true;
    var streakEncouragement =
        _workoutPreferences['streak_encouragement_enabled'] == true;
    final save = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Workout progress settings'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: target,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Target weight (kg)',
                    helperText: 'Leave blank to clear the goal.',
                  ),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Show goal on chart'),
                  value: showGoal,
                  onChanged: (value) => setDialogState(() => showGoal = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Scheduled workout reminders'),
                  value: reminderEnabled,
                  onChanged: (value) =>
                      setDialogState(() => reminderEnabled = value),
                ),
                if (reminderEnabled)
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: workoutTime,
                          decoration: const InputDecoration(
                            labelText: 'Workout time HH:mm',
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextField(
                          controller: minutes,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(
                            labelText: 'Minutes before',
                          ),
                        ),
                      ),
                    ],
                  ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Missed-workout follow-up'),
                  value: missedFollowUp,
                  onChanged: (value) =>
                      setDialogState(() => missedFollowUp = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Consistency encouragement'),
                  value: streakEncouragement,
                  onChanged: (value) =>
                      setDialogState(() => streakEncouragement = value),
                ),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: quietStart,
                        decoration: const InputDecoration(
                          labelText: 'Quiet from HH:mm',
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextField(
                        controller: quietEnd,
                        decoration: const InputDecoration(
                          labelText: 'Quiet until HH:mm',
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );
    if (save != true || !mounted) return;
    await widget.repository.updateWorkoutPreferences({
      'target_weight_kg': target.text.trim().isEmpty
          ? null
          : double.tryParse(target.text.trim()),
      'show_weight_goal': showGoal,
      'scheduled_workout_reminder_enabled': reminderEnabled,
      'reminder_minutes_before': int.tryParse(minutes.text.trim()) ?? 60,
      'default_workout_time': workoutTime.text.trim(),
      'missed_workout_follow_up_enabled': missedFollowUp,
      'streak_encouragement_enabled': streakEncouragement,
      'quiet_hours_start': quietStart.text.trim().isEmpty
          ? null
          : quietStart.text.trim(),
      'quiet_hours_end': quietEnd.text.trim().isEmpty
          ? null
          : quietEnd.text.trim(),
      'timezone': 'Asia/Kolkata',
    });
    target.dispose();
    minutes.dispose();
    workoutTime.dispose();
    quietStart.dispose();
    quietEnd.dispose();
    await _afterSave('Workout progress settings updated.');
  }

  Future<void> _overrideWorkout(Map<String, dynamic> item) async {
    final original = DateTime.tryParse(item['original_date']?.toString() ?? '');
    if (original == null) return;
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.event_repeat_rounded),
              title: const Text('Move to another date'),
              onTap: () => Navigator.pop(context, 'reschedule'),
            ),
            ListTile(
              leading: const Icon(Icons.hotel_rounded),
              title: const Text('Make this a rest day'),
              onTap: () => Navigator.pop(context, 'rest'),
            ),
          ],
        ),
      ),
    );
    if (choice == null || !mounted) return;
    DateTime? replacement;
    if (choice == 'reschedule') {
      replacement = await showDatePicker(
        context: context,
        initialDate: original.add(const Duration(days: 1)),
        firstDate: original.subtract(const Duration(days: 30)),
        lastDate: original.add(const Duration(days: 90)),
      );
      if (replacement == null) return;
    }
    await widget.repository.saveWorkoutScheduleOverride({
      'workout_plan_id': item['workout_plan_id'],
      'workout_plan_day_id': item['workout_plan_day_id'],
      'original_date': DateFormat('yyyy-MM-dd').format(original),
      'replacement_date': replacement == null
          ? null
          : DateFormat('yyyy-MM-dd').format(replacement),
      'override_type': choice,
    });
    await _afterSave(
      choice == 'rest' ? 'Rest day saved.' : 'Workout rescheduled.',
    );
  }

  Future<void> _exportWorkoutData() async {
    try {
      final export = await widget.repository.exportWorkoutData();
      final contents = const JsonEncoder.withIndent('  ').convert(export);
      if (!mounted) return;
      final box = context.findRenderObject() as RenderBox?;
      await SharePlus.instance.share(
        ShareParams(
          subject: 'My Atlas workout backup',
          text: 'Workout data backup exported from Gym Atlas.',
          files: [
            XFile.fromData(
              Uint8List.fromList(utf8.encode(contents)),
              mimeType: 'application/json',
              name: 'atlas-workout-backup.json',
            ),
          ],
          fileNameOverrides: const ['atlas-workout-backup.json'],
          sharePositionOrigin: box == null
              ? null
              : box.localToGlobal(Offset.zero) & box.size,
        ),
      );
    } catch (exception) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _importWorkoutHistory() async {
    try {
      final picked = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['csv'],
        withData: true,
      );
      final file = picked == null || picked.files.isEmpty
          ? null
          : picked.files.single;
      final bytes = file?.bytes;
      if (bytes == null) return;
      final csv = utf8.decode(bytes, allowMalformed: true);
      final preview = await widget.repository.previewWorkoutHistoryImport({
        'source_format': 'generic_csv',
        'source_filename': file?.name,
        'timezone':
            _workoutPreferences['timezone']?.toString() ?? 'Asia/Kolkata',
        'csv_text': csv,
      });
      final batch = Map<String, dynamic>.from(
        preview['data'] as Map? ?? const {},
      );
      final summary = Map<String, dynamic>.from(
        batch['summary'] as Map? ?? const {},
      );
      if (!mounted) return;
      final confirm = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Review workout history'),
          content: Text(
            '${summary['matched'] ?? 0} workouts can be imported. '
            '${summary['unmatched'] ?? 0} exercises need review and '
            '${summary['invalid'] ?? 0} rows will be skipped.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Not now'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Import workouts'),
            ),
          ],
        ),
      );
      if (confirm != true) return;
      await widget.repository.confirmWorkoutHistoryImport(
        (batch['id'] as num).toInt(),
      );
      await _afterSave('Workout history imported.');
    } catch (exception) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _showPhotoSourceSheet() async {
    if (_savingPhoto) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              0,
              AppSpacing.lg,
              AppSpacing.lg,
            ),
            child: PremiumCard(
              padding: const EdgeInsets.all(18),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Choose progress photo',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Pick a clean photo from your gallery or capture a new one.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 18),
                  _PhotoSourceAction(
                    icon: Icons.photo_library_outlined,
                    title: 'Gallery',
                    subtitle: 'Choose from saved photos',
                    onTap: () {
                      Navigator.of(context).pop();
                      _pickPhoto(ImageSource.gallery);
                    },
                  ),
                  const SizedBox(height: 10),
                  _PhotoSourceAction(
                    icon: Icons.photo_camera_outlined,
                    title: 'Camera',
                    subtitle: 'Take a new progress photo',
                    onTap: () {
                      Navigator.of(context).pop();
                      _pickPhoto(ImageSource.camera);
                    },
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Future<void> _pickPhoto(ImageSource source) async {
    try {
      final file = await _imagePicker.pickImage(
        source: source,
        imageQuality: 88,
        maxWidth: 1800,
      );

      if (file == null) {
        return;
      }

      final bytes = await file.readAsBytes();
      if (!mounted) {
        return;
      }

      setState(() {
        _selectedPhoto = file;
        _selectedPhotoBytes = bytes;
      });
    } catch (exception) {
      _showActionError('open that photo', exception);
    }
  }

  void _clearSelectedPhoto() {
    setState(() {
      _selectedPhoto = null;
      _selectedPhotoBytes = null;
    });
  }

  Future<void> _saveWeight() async {
    final weight = double.tryParse(_weightController.text.trim());
    if (weight == null || weight <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter a weight greater than zero.')),
      );
      return;
    }

    setState(() => _savingWeight = true);
    try {
      await widget.repository.addWeightLog({
        'log_date': DateTime.now().toIso8601String().split('T').first,
        'weight_kg': weight,
        'notes': _nullable(_weightNotesController.text),
      });
      _weightController.clear();
      _weightNotesController.clear();
      await _afterSave('Weight log saved.');
    } catch (exception) {
      _showActionError('save your weight', exception);
    } finally {
      if (mounted) {
        setState(() => _savingWeight = false);
      }
    }
  }

  Future<void> _saveMeasurement() async {
    final measurementInputs = <String, TextEditingController>{
      'chest': _chestController,
      'waist': _waistController,
      'hips': _hipsController,
      'arm': _armController,
      'thigh': _thighController,
      'calf': _calfController,
      'body fat': _bodyFatController,
    };
    for (final entry in measurementInputs.entries) {
      final text = entry.value.text.trim();
      if (text.isEmpty) continue;
      final value = double.tryParse(text);
      if (value == null || value <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Enter a valid ${entry.key} value above zero.'),
          ),
        );
        return;
      }
      if (entry.key == 'body fat' && value > 100) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Body fat percentage cannot be above 100%.'),
          ),
        );
        return;
      }
    }
    final payload = {
      'measured_on': DateTime.now().toIso8601String().split('T').first,
      'chest_cm': _nullableDouble(_chestController.text),
      'waist_cm': _nullableDouble(_waistController.text),
      'hips_cm': _nullableDouble(_hipsController.text),
      'arm_cm': _nullableDouble(_armController.text),
      'thigh_cm': _nullableDouble(_thighController.text),
      'calf_cm': _nullableDouble(_calfController.text),
      'body_fat_percentage': _nullableDouble(_bodyFatController.text),
      'notes': _nullable(_measurementNotesController.text),
    };

    final hasAnyMeasurement = payload.entries.any((entry) {
      if (entry.key == 'measured_on' || entry.key == 'notes') {
        return false;
      }
      return entry.value != null;
    });

    if (!hasAnyMeasurement) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Add at least one measurement value.')),
      );
      return;
    }

    setState(() => _savingMeasurement = true);
    try {
      await widget.repository.addBodyMeasurement(payload);
      _chestController.clear();
      _waistController.clear();
      _hipsController.clear();
      _armController.clear();
      _thighController.clear();
      _calfController.clear();
      _bodyFatController.clear();
      _measurementNotesController.clear();
      await _afterSave('Body measurements saved.');
    } catch (exception) {
      _showActionError('save your measurements', exception);
    } finally {
      if (mounted) {
        setState(() => _savingMeasurement = false);
      }
    }
  }

  Future<void> _savePhoto() async {
    final photoBytes = _selectedPhotoBytes;
    final photoFile = _selectedPhoto;
    if (photoBytes == null || photoFile == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Choose a progress photo first.')),
      );
      return;
    }

    setState(() => _savingPhoto = true);
    try {
      await widget.repository.uploadProgressPhoto(
        bytes: photoBytes,
        filename: photoFile.name.isEmpty
            ? 'progress-photo.jpg'
            : photoFile.name,
        photoType: _photoType,
        capturedOn: DateTime.now().toIso8601String().split('T').first,
        notes: _nullable(_photoNotesController.text),
      );
      _clearSelectedPhoto();
      _photoNotesController.clear();
      await _afterSave('Progress photo uploaded.');
    } catch (exception) {
      _showActionError('upload your progress photo', exception);
    } finally {
      if (mounted) {
        setState(() => _savingPhoto = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final latestWeight = Map<String, dynamic>.from(
      _summary['latest_weight_log'] as Map? ?? const {},
    );
    final latestMeasurement = Map<String, dynamic>.from(
      _summary['latest_body_measurement'] as Map? ?? const {},
    );
    final recentPhotos =
        (_summary['recent_progress_photos'] as List<dynamic>? ?? const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
    return AppGradientScaffold(
      title: 'Body progress',
      subtitle: 'Understand your trends and add your next check-in',
      body: _loading
          ? const _ProgressSkeleton()
          : _error != null
          ? ErrorStateView(message: _error!, onRetry: _load)
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg,
                    AppSpacing.md,
                    AppSpacing.lg,
                    0,
                  ),
                  child: _BodyProgressHeader(
                    onOpenSettings: _editWorkoutPreferences,
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg,
                    AppSpacing.md,
                    AppSpacing.lg,
                    0,
                  ),
                  child: _StrengthTrackingHeader(
                    weightCount: _weightLogs.length,
                    measurementCount: _bodyMeasurements.length,
                    photoCount: _photos.length,
                    stepCount: _stepSummary.length,
                    latestWeight: latestWeight,
                    successMessage: _lastSuccessMessage,
                    tabController: _tabController,
                  ),
                ),
                Expanded(
                  child: TabBarView(
                    controller: _tabController,
                    children: [
                      _ProgressOverviewTab(
                        latestMeasurement: latestMeasurement,
                        recentPhotos: recentPhotos,
                        weightLogs: _weightLogs,
                        bodyMeasurements: _bodyMeasurements,
                        photos: _photos,
                        stepSummary: _stepSummary,
                      ),
                      _WorkoutAnalyticsTab(
                        analytics: _workoutAnalytics,
                        onOverrideWorkout: _overrideWorkout,
                        onExportData: _exportWorkoutData,
                        onImportHistory: _importWorkoutHistory,
                      ),
                      _StepHistoryTab(stepSummary: _stepSummary),
                      _WeightLogsTab(
                        weightLogs: _weightLogs,
                        weightController: _weightController,
                        notesController: _weightNotesController,
                        goalValue:
                            _workoutPreferences['show_weight_goal'] == false
                            ? null
                            : double.tryParse(
                                _workoutPreferences['target_weight_kg']
                                        ?.toString() ??
                                    '',
                              ),
                        saving: _savingWeight,
                        onSave: _saveWeight,
                        hasMore: _weightPage.hasMore,
                        loadingMore: _loadingMoreWeight,
                        onLoadMore: _loadMoreWeight,
                      ),
                      _BodyMeasurementsTab(
                        measurements: _bodyMeasurements,
                        chestController: _chestController,
                        waistController: _waistController,
                        hipsController: _hipsController,
                        armController: _armController,
                        thighController: _thighController,
                        calfController: _calfController,
                        bodyFatController: _bodyFatController,
                        notesController: _measurementNotesController,
                        saving: _savingMeasurement,
                        onSave: _saveMeasurement,
                        hasMore: _measurementPage.hasMore,
                        loadingMore: _loadingMoreMeasurements,
                        onLoadMore: _loadMoreMeasurements,
                      ),
                      if (_showProgressPhotos)
                        _ProgressPhotosTab(
                          photos: _photos,
                          recentPhotos: recentPhotos,
                          selectedPhoto: _selectedPhoto,
                          selectedPhotoBytes: _selectedPhotoBytes,
                          notesController: _photoNotesController,
                          selectedType: _photoType,
                          onTypeChanged: (value) =>
                              setState(() => _photoType = value),
                          onChoosePhotoPressed: _showPhotoSourceSheet,
                          onClearPhotoPressed: _selectedPhotoBytes == null
                              ? null
                              : _clearSelectedPhoto,
                          saving: _savingPhoto,
                          onSave: _savePhoto,
                          hasMore: _photoPage.hasMore,
                          loadingMore: _loadingMorePhotos,
                          onLoadMore: _loadMorePhotos,
                        ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }
}

class _BodyProgressHeader extends StatelessWidget {
  const _BodyProgressHeader({required this.onOpenSettings});

  final VoidCallback onOpenSettings;

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
                'Body progress',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.7,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'See what is changing, then add your next check-in.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        Tooltip(
          message: 'Progress settings',
          child: Semantics(
            button: true,
            label: 'Open progress settings',
            child: MemberHeaderActionButton(
              icon: Icons.tune_rounded,
              onTap: onOpenSettings,
            ),
          ),
        ),
      ],
    );
  }
}

class _StrengthTrackingHeader extends StatelessWidget {
  const _StrengthTrackingHeader({
    required this.weightCount,
    required this.measurementCount,
    required this.photoCount,
    required this.stepCount,
    required this.latestWeight,
    required this.successMessage,
    required this.tabController,
  });

  final int weightCount;
  final int measurementCount;
  final int photoCount;
  final int stepCount;
  final Map<String, dynamic> latestWeight;
  final String? successMessage;
  final TabController tabController;

  @override
  Widget build(BuildContext context) {
    final weight = _asDouble(latestWeight['weight_kg']);
    final weightLabel = weight > 0
        ? '${weight.toStringAsFixed(1)} kg'
        : 'No log';
    final progress =
        weightCount == 0 && measurementCount == 0 && photoCount == 0
        ? 0.18
        : ((weightCount + measurementCount + photoCount) /
                  (weightCount + measurementCount + photoCount + stepCount + 2))
              .clamp(0.28, 0.88)
              .toDouble();
    const showBodyMetricsHeroCard = bool.fromEnvironment(
      'SHOW_BODY_METRICS_HERO_CARD',
    );

    return Column(
      children: [
        if (showBodyMetricsHeroCard) ...[
          ClipRRect(
            borderRadius: BorderRadius.circular(30),
            child: Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    Colors.white.withValues(alpha: 0.98),
                    const Color(0xFFF6FBFF),
                    const Color(0xFFF8FAFC),
                  ],
                ),
                border: Border.all(
                  color: AppColors.stroke.withValues(alpha: 0.8),
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.shadow.withValues(alpha: 0.06),
                    blurRadius: 20,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: Stack(
                children: [
                  Positioned(
                    top: -34,
                    right: -28,
                    child: Container(
                      width: 146,
                      height: 146,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: AppColors.primary.withValues(alpha: 0.10),
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (successMessage != null) ...[
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 10,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.surfaceSoft,
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(color: AppColors.stroke),
                            ),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.check_circle_rounded,
                                  color: AppColors.primaryBright,
                                  size: 18,
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    successMessage!,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: AppColors.textPrimary,
                                          fontWeight: FontWeight.w800,
                                        ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 14),
                        ],
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 12,
                                      vertical: 7,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.white.withValues(
                                        alpha: 0.68,
                                      ),
                                      borderRadius: BorderRadius.circular(999),
                                      border: Border.all(
                                        color: AppColors.stroke,
                                      ),
                                    ),
                                    child: Text(
                                      'BODY METRICS',
                                      style: Theme.of(context)
                                          .textTheme
                                          .labelSmall
                                          ?.copyWith(
                                            color: AppColors.primaryBright,
                                            fontWeight: FontWeight.w900,
                                            letterSpacing: 0.8,
                                          ),
                                    ),
                                  ),
                                  const SizedBox(height: 12),
                                  Text(
                                    'Track the numbers that move with you',
                                    style: Theme.of(context)
                                        .textTheme
                                        .headlineSmall
                                        ?.copyWith(
                                          color: AppColors.textPrimary,
                                          fontWeight: FontWeight.w900,
                                          letterSpacing: -0.9,
                                          height: 0.98,
                                        ),
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    _showProgressPhotos
                                        ? 'Weight, measurements, steps, and progress photos in one tracking flow.'
                                        : 'Weight, measurements, and steps in one clear tracking flow.',
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: AppColors.textSecondary,
                                          fontWeight: FontWeight.w600,
                                          height: 1.4,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 12),
                            _StrengthHeroRing(
                              progress: progress,
                              label: weightLabel == 'No log'
                                  ? '--'
                                  : weightLabel,
                            ),
                          ],
                        ),
                        const SizedBox(height: 18),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            _StrengthHeroChip(
                              icon: Icons.monitor_weight_rounded,
                              label: '$weightCount weight logs',
                            ),
                            _StrengthHeroChip(
                              icon: Icons.straighten_rounded,
                              label: '$measurementCount measurements',
                            ),
                            _StrengthHeroChip(
                              icon: Icons.directions_walk_rounded,
                              label: '$stepCount step days',
                            ),
                            if (_showProgressPhotos)
                              _StrengthHeroChip(
                                icon: Icons.photo_camera_back_rounded,
                                label: '$photoCount photos',
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
        ] else if (successMessage != null) ...[
          Container(
            margin: const EdgeInsets.only(bottom: AppSpacing.sm),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.check_circle_rounded,
                  color: AppColors.primaryBright,
                  size: 18,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    successMessage!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
        _StrengthTabSlider(controller: tabController),
      ],
    );
  }
}

class _StrengthHeroRing extends StatelessWidget {
  const _StrengthHeroRing({required this.progress, required this.label});

  final double progress;
  final String label;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 96,
      height: 96,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: 96,
            height: 96,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white.withValues(alpha: 0.78),
              border: Border.all(
                color: AppColors.stroke.withValues(alpha: 0.7),
              ),
            ),
          ),
          SizedBox(
            width: 72,
            height: 72,
            child: CircularProgressIndicator(
              value: 1,
              strokeWidth: 8,
              backgroundColor: AppColors.stroke.withValues(alpha: 0.8),
              valueColor: const AlwaysStoppedAnimation<Color>(AppColors.stroke),
            ),
          ),
          SizedBox(
            width: 72,
            height: 72,
            child: CircularProgressIndicator(
              value: progress,
              strokeWidth: 8,
              valueColor: const AlwaysStoppedAnimation<Color>(
                AppColors.primaryBright,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10),
            child: Text(
              label,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StrengthHeroChip extends StatelessWidget {
  const _StrengthHeroChip({required this.icon, required this.label});

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
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _StrengthTabSlider extends StatefulWidget {
  const _StrengthTabSlider({required this.controller});

  final TabController controller;

  @override
  State<_StrengthTabSlider> createState() => _StrengthTabSliderState();
}

class _StrengthTabSliderState extends State<_StrengthTabSlider> {
  static const _items = [
    (label: 'Summary', icon: Icons.dashboard_customize_rounded),
    (label: 'Training', icon: Icons.insights_rounded),
    (label: 'Steps', icon: Icons.directions_walk_rounded),
    (label: 'Weight', icon: Icons.monitor_weight_rounded),
    (label: 'Measurements', icon: Icons.straighten_rounded),
    if (_showProgressPhotos)
      (label: 'Photos', icon: Icons.photo_camera_back_rounded),
  ];
  final _tabKeys = List<GlobalKey>.generate(_items.length, (_) => GlobalKey());

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_handleTabChange);
  }

  @override
  void didUpdateWidget(covariant _StrengthTabSlider oldWidget) {
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
      WidgetsBinding.instance.addPostFrameCallback((_) {
        final selectedContext =
            _tabKeys[widget.controller.index].currentContext;
        if (selectedContext != null) {
          Scrollable.ensureVisible(
            selectedContext,
            alignment: 0.5,
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOutCubic,
          );
        }
      });
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
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (var index = 0; index < _items.length; index++)
              _StrengthTabPill(
                key: _tabKeys[index],
                label: _items[index].label,
                icon: _items[index].icon,
                active: widget.controller.index == index,
                onTap: () => widget.controller.animateTo(index),
              ),
          ],
        ),
      ),
    );
  }
}

class _StrengthTabPill extends StatelessWidget {
  const _StrengthTabPill({
    super.key,
    required this.label,
    required this.icon,
    required this.active,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: active,
      label: '$label tab',
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          constraints: const BoxConstraints(minHeight: 48),
          margin: const EdgeInsets.symmetric(horizontal: 2),
          padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            color: active ? AppColors.primary : Colors.transparent,
            border: active
                ? Border.all(color: AppColors.primaryBright, width: 1.5)
                : null,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 18,
                color: active ? Colors.white : AppColors.textSecondary,
              ),
              const SizedBox(width: 6),
              Text(
                label,
                maxLines: 1,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: active ? Colors.white : AppColors.textSecondary,
                  fontWeight: active ? FontWeight.w900 : FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProgressOverviewTab extends StatelessWidget {
  const _ProgressOverviewTab({
    required this.latestMeasurement,
    required this.recentPhotos,
    required this.weightLogs,
    required this.bodyMeasurements,
    required this.photos,
    required this.stepSummary,
  });

  final Map<String, dynamic> latestMeasurement;
  final List<Map<String, dynamic>> recentPhotos;
  final List<Map<String, dynamic>> weightLogs;
  final List<Map<String, dynamic>> bodyMeasurements;
  final List<Map<String, dynamic>> photos;
  final List<Map<String, dynamic>> stepSummary;

  @override
  Widget build(BuildContext context) {
    if (weightLogs.isEmpty &&
        bodyMeasurements.isEmpty &&
        (!_showProgressPhotos || photos.isEmpty) &&
        stepSummary.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(AppSpacing.lg),
        child: _StrengthEmptyPanel(
          title: 'Start your strength profile',
          message: _showProgressPhotos
              ? 'Add weight, measurements, or progress photos to build a clear body timeline.'
              : 'Add weight or measurements to build a clear body timeline.',
          icon: Icons.insights_rounded,
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        MetricTrendChart(
          title: 'Weight trend',
          subtitle: 'Your logged weight across the visible timeline',
          points: _metricPoints(weightLogs, 'log_date', 'weight_kg'),
          accentColor: const Color(0xFF92A3FD),
          unit: ' kg',
        ),
        if (_showProgressPhotos) ...[
          const SizedBox(height: 18),
          _StrengthInsightPanel(
            title: 'Transformation timeline',
            subtitle: recentPhotos.length >= 2
                ? 'Compare your earliest and latest visual checkpoints.'
                : 'Add two progress photos to unlock a before and after view.',
            icon: Icons.compare_rounded,
            child: recentPhotos.length >= 2
                ? Row(
                    children: [
                      Expanded(
                        child: _PhotoFrame(
                          label: 'Before',
                          photo: recentPhotos.last,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: _PhotoFrame(
                          label: 'Latest',
                          photo: recentPhotos.first,
                        ),
                      ),
                    ],
                  )
                : const _StrengthMiniEmpty(
                    icon: Icons.add_a_photo_rounded,
                    text: 'Your visual compare view will appear here.',
                  ),
          ),
        ],
        const SizedBox(height: 18),
        _StrengthSectionTitle(
          title: 'Latest body snapshot',
          action: latestMeasurement['measured_on'] == null
              ? 'No data'
              : _formatDate(latestMeasurement['measured_on']),
        ),
        const SizedBox(height: 10),
        if (latestMeasurement.isEmpty)
          const _StrengthMiniEmpty(
            icon: Icons.straighten_rounded,
            text: 'Measurements make strength progress easier to understand.',
          )
        else
          _MeasurementChipWrap(measurement: latestMeasurement),
        const SizedBox(height: 18),
        _StrengthSectionTitle(title: 'Recent check-ins', action: 'Latest'),
        const SizedBox(height: 10),
        ...weightLogs
            .take(3)
            .map(
              (log) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _StrengthTimelineRow(
                  title: '${_asDouble(log['weight_kg']).toStringAsFixed(1)} kg',
                  subtitle: _formatDate(log['log_date']),
                  badge: 'Weight',
                  icon: Icons.monitor_weight_rounded,
                  color: const Color(0xFF92A3FD),
                ),
              ),
            ),
      ],
    );
  }
}

class _WorkoutAnalyticsTab extends StatelessWidget {
  const _WorkoutAnalyticsTab({
    required this.analytics,
    required this.onOverrideWorkout,
    required this.onExportData,
    required this.onImportHistory,
  });

  final Map<String, dynamic> analytics;
  final Future<void> Function(Map<String, dynamic>) onOverrideWorkout;
  final VoidCallback onExportData;
  final VoidCallback onImportHistory;

  @override
  Widget build(BuildContext context) {
    final weight = Map<String, dynamic>.from(
      analytics['weight'] as Map? ?? const {},
    );
    final adherence = Map<String, dynamic>.from(
      analytics['adherence'] as Map? ?? const {},
    );
    final effort = Map<String, dynamic>.from(
      analytics['effort'] as Map? ?? const {},
    );
    final coverage = Map<String, dynamic>.from(
      analytics['muscle_coverage'] as Map? ?? const {},
    );
    final calendar = (analytics['calendar'] as List? ?? const [])
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();
    final heatmap = (analytics['heatmap'] as List? ?? const [])
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();
    final muscles = (coverage['items'] as List? ?? const [])
        .whereType<Map>()
        .take(10);
    final e1rm = (analytics['estimated_one_rep_max'] as List? ?? const [])
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();
    final weightPoints = (weight['points'] as List? ?? const [])
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();
    final upcoming = calendar
        .where((row) => ['planned', 'rescheduled'].contains(row['status']))
        .take(8)
        .toList();
    final goal = double.tryParse(weight['target_weight_kg']?.toString() ?? '');

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        _StrengthSectionTitle(
          title: 'Training analytics',
          action: '${adherence['percentage'] ?? '--'}% adherence',
        ),
        if (_showWorkoutDataTools) ...[
          const SizedBox(height: 10),
          _StrengthInsightPanel(
            title: 'Backup and import',
            subtitle: 'For moving your own workout history.',
            icon: Icons.folder_copy_outlined,
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: onExportData,
                    icon: const Icon(Icons.download_rounded),
                    label: const Text('Export backup'),
                  ),
                ),
                const SizedBox(width: AppSpacing.xs),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: onImportHistory,
                    icon: const Icon(Icons.upload_file_rounded),
                    label: const Text('Choose CSV'),
                  ),
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 14),
        MetricTrendChart(
          title: 'Weight toward your goal',
          subtitle: weight['direction'] == 'toward_goal'
              ? 'Your recent change moved toward the target.'
              : 'Trend is shown without health judgments.',
          points: _metricPoints(weightPoints, 'date', 'weight_kg'),
          accentColor: const Color(0xFF92A3FD),
          unit: ' kg',
          goalValue: goal,
        ),
        const SizedBox(height: 14),
        _StrengthInsightPanel(
          title: 'Activity and adherence',
          subtitle:
              '${adherence['completed_count'] ?? 0} completed • ${adherence['missed_count'] ?? 0} missed • rest days excluded',
          icon: Icons.calendar_view_month_rounded,
          child: heatmap.isEmpty
              ? const _StrengthMiniEmpty(
                  icon: Icons.calendar_today_rounded,
                  text: 'Scheduled activity will appear here.',
                )
              : Wrap(
                  spacing: 5,
                  runSpacing: 5,
                  children: heatmap.take(42).map((day) {
                    final intensity = (day['intensity'] as num?)?.toInt() ?? 0;
                    return Tooltip(
                      message:
                          '${day['date']} • ${day['status']} • ${day['training_minutes']} min',
                      child: Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          color: intensity == 0
                              ? AppColors.surfaceSoft
                              : AppColors.primary.withValues(
                                  alpha: 0.25 + intensity * 0.16,
                                ),
                          borderRadius: BorderRadius.circular(7),
                          border: Border.all(color: AppColors.stroke),
                        ),
                      ),
                    );
                  }).toList(),
                ),
        ),
        const SizedBox(height: 14),
        _StrengthInsightPanel(
          title: 'Muscle coverage',
          subtitle:
              'Primary sets count 1.0 and secondary muscles count ${coverage['secondary_weight'] ?? 0.5}.',
          icon: Icons.accessibility_new_rounded,
          child: muscles.isEmpty
              ? const _StrengthMiniEmpty(
                  icon: Icons.fitness_center_rounded,
                  text: 'Complete workouts to build muscle coverage.',
                )
              : Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: muscles
                      .map(
                        (row) => Chip(
                          label: Text(
                            '${row['muscle']} · ${row['weighted_sets']}',
                          ),
                        ),
                      )
                      .toList(),
                ),
        ),
        const SizedBox(height: 14),
        _StrengthInsightPanel(
          title: 'Effort data',
          subtitle:
              '${effort['rated_set_count'] ?? 0} of ${effort['eligible_set_count'] ?? 0} completed sets rated (${effort['rated_coverage_percentage'] ?? 0}%).',
          icon: Icons.speed_rounded,
          child: Text(
            effort['average_rpe_equivalent'] == null
                ? 'Add optional RIR or RPE ratings during workouts.'
                : 'Average comparable effort: ${effort['average_rpe_equivalent']} RPE-equivalent. Unrated sets are excluded.',
          ),
        ),
        const SizedBox(height: 14),
        MetricTrendChart(
          title: 'Estimated 1RM trend',
          subtitle: 'Epley v1; eligible weighted sets up to 12 reps only.',
          points: _metricPoints(e1rm, 'date', 'value_kg'),
          accentColor: const Color(0xFFC58BF2),
          unit: ' kg',
        ),
        const SizedBox(height: 18),
        _StrengthSectionTitle(
          title: 'Upcoming workouts',
          action: '${upcoming.length} shown',
        ),
        const SizedBox(height: 10),
        if (upcoming.isEmpty)
          const _StrengthMiniEmpty(
            icon: Icons.event_available_rounded,
            text: 'No upcoming recurring workouts in this range.',
          )
        else
          ...upcoming.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _StrengthTimelineRow(
                title:
                    item['day_label']?.toString() ??
                    item['plan_name']?.toString() ??
                    'Workout',
                subtitle: '${_formatDate(item['date'])} • ${item['plan_name']}',
                badge: item['is_rescheduled'] == true ? 'Moved' : 'Planned',
                icon: Icons.event_repeat_rounded,
                color: AppColors.primary,
                onTap: () => onOverrideWorkout(item),
              ),
            ),
          ),
      ],
    );
  }
}

class _StepHistoryTab extends StatelessWidget {
  const _StepHistoryTab({required this.stepSummary});

  final List<Map<String, dynamic>> stepSummary;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        MetricTrendChart(
          title: 'Daily step trend',
          subtitle: 'See how your daily movement changes through the week',
          points: _metricPoints(stepSummary, 'date', 'steps'),
          accentColor: const Color(0xFF40D9B8),
          unit: ' steps',
          emptyMessage:
              'Your trend will appear after steps are recorded on two days.',
        ),
        const SizedBox(height: 18),
        _StrengthInsightPanel(
          title: 'Your recent steps',
          subtitle: 'Follow your daily movement and progress toward your goal.',
          icon: Icons.query_stats_rounded,
          child: stepSummary.isEmpty
              ? const _StrengthMiniEmpty(
                  icon: Icons.directions_walk_rounded,
                  text:
                      'Your step history will appear after your first active day.',
                )
              : Column(
                  children: stepSummary.reversed.map((day) {
                    final steps = (day['steps'] as num?)?.toInt() ?? 0;
                    final dayGoal =
                        (day['goalSteps'] as num?)?.toInt() ?? 10000;
                    final dayProgress = dayGoal <= 0
                        ? 0
                        : ((steps / dayGoal) * 100).round().clamp(0, 100);
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _StrengthTimelineRow(
                        title: _formatCompactNumber(steps),
                        subtitle:
                            '${_formatDate(day['date'])} • $dayProgress% of goal',
                        badge: steps > 0 ? 'Active' : 'Rest day',
                        icon: Icons.directions_walk_rounded,
                        color: steps > 0
                            ? const Color(0xFF40D9B8)
                            : AppColors.textSecondary,
                      ),
                    );
                  }).toList(),
                ),
        ),
      ],
    );
  }
}

class _WeightLogsTab extends StatelessWidget {
  const _WeightLogsTab({
    required this.weightLogs,
    required this.weightController,
    required this.notesController,
    required this.goalValue,
    required this.saving,
    required this.onSave,
    required this.hasMore,
    required this.loadingMore,
    required this.onLoadMore,
  });

  final List<Map<String, dynamic>> weightLogs;
  final TextEditingController weightController;
  final TextEditingController notesController;
  final double? goalValue;
  final bool saving;
  final VoidCallback onSave;
  final bool hasMore;
  final bool loadingMore;
  final VoidCallback onLoadMore;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        MetricTrendChart(
          title: 'Weight trend',
          subtitle: 'Dates are spaced by the time between check-ins',
          points: _metricPoints(weightLogs, 'log_date', 'weight_kg'),
          accentColor: const Color(0xFF92A3FD),
          unit: ' kg',
          goalValue: goalValue,
        ),
        const SizedBox(height: 18),
        _StrengthFormPanel(
          title: 'Quick weight check-in',
          subtitle: 'Log one clean number. Notes are optional.',
          icon: Icons.monitor_weight_rounded,
          color: const Color(0xFF92A3FD),
          children: [
            TextField(
              controller: weightController,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(
                labelText: 'Weight (kg)',
                prefixIcon: Icon(Icons.monitor_weight_rounded),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            TextField(
              controller: notesController,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'Notes',
                prefixIcon: Icon(Icons.notes_rounded),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            GradientButton(
              label: 'Save Weight Log',
              icon: Icons.check_rounded,
              loading: saving,
              expanded: true,
              onPressed: saving ? null : onSave,
            ),
          ],
        ),
        const SizedBox(height: 18),
        _StrengthSectionTitle(
          title: 'Weight timeline',
          action: '${weightLogs.length} logs',
        ),
        const SizedBox(height: 10),
        if (weightLogs.isEmpty)
          const _StrengthEmptyPanel(
            title: 'No weight logs yet',
            message:
                'Your timeline will appear here after the first progress check-in.',
            icon: Icons.timeline_rounded,
          )
        else
          ...weightLogs.asMap().entries.map((entry) {
            final log = entry.value;
            final current = _asDouble(log['weight_kg']);
            final previous = entry.key + 1 < weightLogs.length
                ? _asDouble(weightLogs[entry.key + 1]['weight_kg'])
                : current;
            final delta = current - previous;
            final subtitle = delta == 0
                ? _formatDate(log['log_date'])
                : '${delta > 0 ? '+' : ''}${delta.toStringAsFixed(1)} kg from last • ${_formatDate(log['log_date'])}';
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _StrengthTimelineRow(
                title: '${current.toStringAsFixed(1)} kg',
                subtitle: subtitle,
                detail: log['notes']?.toString(),
                badge: entry.key == 0 ? 'Latest' : 'Log',
                icon: Icons.monitor_weight_rounded,
                color: const Color(0xFF92A3FD),
              ),
            );
          }),
        if (hasMore) ...[
          const SizedBox(height: 4),
          _LoadOlderButton(
            label: 'Load older weight logs',
            loading: loadingMore,
            onPressed: onLoadMore,
          ),
        ],
      ],
    );
  }
}

class _BodyMeasurementsTab extends StatelessWidget {
  const _BodyMeasurementsTab({
    required this.measurements,
    required this.chestController,
    required this.waistController,
    required this.hipsController,
    required this.armController,
    required this.thighController,
    required this.calfController,
    required this.bodyFatController,
    required this.notesController,
    required this.saving,
    required this.onSave,
    required this.hasMore,
    required this.loadingMore,
    required this.onLoadMore,
  });

  final List<Map<String, dynamic>> measurements;
  final TextEditingController chestController;
  final TextEditingController waistController;
  final TextEditingController hipsController;
  final TextEditingController armController;
  final TextEditingController thighController;
  final TextEditingController calfController;
  final TextEditingController bodyFatController;
  final TextEditingController notesController;
  final bool saving;
  final VoidCallback onSave;
  final bool hasMore;
  final bool loadingMore;
  final VoidCallback onLoadMore;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        _BodyMeasurementTrends(measurements: measurements),
        const SizedBox(height: 18),
        _StrengthFormPanel(
          title: 'Body snapshot',
          subtitle: 'Add only the numbers you measured today.',
          icon: Icons.straighten_rounded,
          color: const Color(0xFFC58BF2),
          children: [
            LayoutBuilder(
              builder: (context, constraints) {
                final twoColumns = constraints.maxWidth >= 340;
                final fields = [
                  _NumberField(controller: chestController, label: 'Chest'),
                  _NumberField(controller: waistController, label: 'Waist'),
                  _NumberField(controller: hipsController, label: 'Hips'),
                  _NumberField(controller: armController, label: 'Arm'),
                  _NumberField(controller: thighController, label: 'Thigh'),
                  _NumberField(controller: calfController, label: 'Calf'),
                  _NumberField(
                    controller: bodyFatController,
                    label: 'Body fat',
                    unit: '%',
                  ),
                ];

                if (!twoColumns) {
                  return Column(
                    children: [
                      for (final field in fields) ...[
                        field,
                        const SizedBox(height: AppSpacing.sm),
                      ],
                    ],
                  );
                }

                return Wrap(
                  spacing: AppSpacing.sm,
                  runSpacing: AppSpacing.sm,
                  children: fields
                      .map(
                        (field) => SizedBox(
                          width: (constraints.maxWidth - AppSpacing.sm) / 2,
                          child: field,
                        ),
                      )
                      .toList(),
                );
              },
            ),
            const SizedBox(height: AppSpacing.md),
            TextField(
              controller: notesController,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'Notes',
                prefixIcon: Icon(Icons.notes_rounded),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            GradientButton(
              label: 'Save Measurements',
              icon: Icons.check_rounded,
              loading: saving,
              expanded: true,
              onPressed: saving ? null : onSave,
            ),
          ],
        ),
        const SizedBox(height: 18),
        _StrengthSectionTitle(
          title: 'Measurement history',
          action: '${measurements.length} snapshots',
        ),
        const SizedBox(height: 10),
        if (measurements.isEmpty)
          const _StrengthEmptyPanel(
            title: 'No body measurements yet',
            message:
                'Snapshots help connect training effort with visible body changes.',
            icon: Icons.straighten_rounded,
          )
        else
          ...measurements.map(
            (measurement) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _StrengthMeasurementSnapshot(measurement: measurement),
            ),
          ),
        if (hasMore) ...[
          const SizedBox(height: 4),
          _LoadOlderButton(
            label: 'Load older measurements',
            loading: loadingMore,
            onPressed: onLoadMore,
          ),
        ],
      ],
    );
  }
}

class _BodyMeasurementTrends extends StatefulWidget {
  const _BodyMeasurementTrends({required this.measurements});

  final List<Map<String, dynamic>> measurements;

  @override
  State<_BodyMeasurementTrends> createState() => _BodyMeasurementTrendsState();
}

class _BodyMeasurementTrendsState extends State<_BodyMeasurementTrends> {
  static const _metrics = [
    (label: 'Waist', key: 'waist_cm', unit: ' cm', color: Color(0xFFC58BF2)),
    (
      label: 'Body fat',
      key: 'body_fat_percentage',
      unit: '%',
      color: Color(0xFFFFB86C),
    ),
    (label: 'Chest', key: 'chest_cm', unit: ' cm', color: Color(0xFF92A3FD)),
    (label: 'Hips', key: 'hips_cm', unit: ' cm', color: Color(0xFF40D9B8)),
    (label: 'Arm', key: 'arm_cm', unit: ' cm', color: Color(0xFF6E7BF2)),
    (label: 'Thigh', key: 'thigh_cm', unit: ' cm', color: Color(0xFFFF8A9B)),
    (label: 'Calf', key: 'calf_cm', unit: ' cm', color: Color(0xFF45B7D1)),
  ];

  String _selectedKey = 'waist_cm';

  @override
  Widget build(BuildContext context) {
    final available = _metrics
        .where(
          (metric) => widget.measurements.any(
            (measurement) => measurement[metric.key] != null,
          ),
        )
        .toList();
    final choices = available.isEmpty ? _metrics : available;
    final selected = choices.any((metric) => metric.key == _selectedKey)
        ? choices.firstWhere((metric) => metric.key == _selectedKey)
        : choices.first;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _StrengthSectionTitle(
          title: 'Measurement trends',
          action: '${available.length} tracked',
        ),
        const SizedBox(height: 10),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: choices.map((metric) {
              final active = metric.key == selected.key;
              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(metric.label),
                  selected: active,
                  showCheckmark: true,
                  onSelected: (_) => setState(() => _selectedKey = metric.key),
                ),
              );
            }).toList(),
          ),
        ),
        const SizedBox(height: 12),
        MetricTrendChart(
          title: '${selected.label} trend',
          subtitle: 'Dates are spaced by the time between snapshots',
          points: _metricPoints(
            widget.measurements,
            'measured_on',
            selected.key,
          ),
          accentColor: selected.color,
          unit: selected.unit,
          emptyMessage:
              'Add ${selected.label.toLowerCase()} values to compare change over time.',
        ),
      ],
    );
  }
}

class _ProgressPhotosTab extends StatelessWidget {
  const _ProgressPhotosTab({
    required this.photos,
    required this.recentPhotos,
    required this.selectedPhoto,
    required this.selectedPhotoBytes,
    required this.notesController,
    required this.selectedType,
    required this.onTypeChanged,
    required this.onChoosePhotoPressed,
    required this.onClearPhotoPressed,
    required this.saving,
    required this.onSave,
    required this.hasMore,
    required this.loadingMore,
    required this.onLoadMore,
  });

  final List<Map<String, dynamic>> photos;
  final List<Map<String, dynamic>> recentPhotos;
  final XFile? selectedPhoto;
  final Uint8List? selectedPhotoBytes;
  final TextEditingController notesController;
  final String selectedType;
  final ValueChanged<String> onTypeChanged;
  final VoidCallback onChoosePhotoPressed;
  final VoidCallback? onClearPhotoPressed;
  final bool saving;
  final VoidCallback onSave;
  final bool hasMore;
  final bool loadingMore;
  final VoidCallback onLoadMore;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        if (recentPhotos.length >= 2) ...[
          _StrengthInsightPanel(
            title: 'Before / latest',
            subtitle: 'A quick visual comparison from your timeline.',
            icon: Icons.compare_rounded,
            child: Row(
              children: [
                Expanded(
                  child: _PhotoFrame(label: 'Before', photo: recentPhotos.last),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: _PhotoFrame(
                    label: 'Latest',
                    photo: recentPhotos.first,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
        ],
        _StrengthFormPanel(
          title: 'Add progress photo',
          subtitle:
              'Keep your visual timeline current with a simple device upload.',
          icon: Icons.photo_camera_back_rounded,
          color: const Color(0xFFFFB86C),
          children: [
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: saving ? null : onChoosePhotoPressed,
                    icon: const Icon(Icons.perm_media_rounded),
                    label: Text(
                      selectedPhotoBytes == null
                          ? 'Choose photo'
                          : 'Change photo',
                    ),
                  ),
                ),
                if (onClearPhotoPressed != null) ...[
                  const SizedBox(width: AppSpacing.sm),
                  OutlinedButton(
                    onPressed: saving ? null : onClearPhotoPressed,
                    child: const Text('Clear'),
                  ),
                ],
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            if (selectedPhotoBytes != null) ...[
              _SelectedPhotoPreview(
                bytes: selectedPhotoBytes!,
                label: _titleCase(selectedType),
                filename: selectedPhoto?.name ?? 'Selected photo',
              ),
              const SizedBox(height: AppSpacing.md),
            ],
            DropdownButtonFormField<String>(
              initialValue: selectedType,
              decoration: const InputDecoration(
                labelText: 'Photo type',
                prefixIcon: Icon(Icons.photo_camera_back_rounded),
              ),
              items: const [
                DropdownMenuItem(value: 'front', child: Text('Front')),
                DropdownMenuItem(value: 'side', child: Text('Side')),
                DropdownMenuItem(value: 'back', child: Text('Back')),
                DropdownMenuItem(value: 'other', child: Text('Other')),
              ],
              onChanged: (value) {
                if (value != null) {
                  onTypeChanged(value);
                }
              },
            ),
            const SizedBox(height: AppSpacing.md),
            TextField(
              controller: notesController,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'Notes',
                prefixIcon: Icon(Icons.notes_rounded),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            GradientButton(
              label: 'Upload Progress Photo',
              icon: Icons.add_photo_alternate_rounded,
              loading: saving,
              expanded: true,
              onPressed: saving ? null : onSave,
            ),
          ],
        ),
        const SizedBox(height: 18),
        _StrengthSectionTitle(
          title: 'Photo timeline',
          action: '${photos.length} photos',
        ),
        const SizedBox(height: 10),
        if (photos.isEmpty)
          const _StrengthEmptyPanel(
            title: 'No progress photos yet',
            message:
                'Add progress photos over time to build a clean transformation timeline.',
            icon: Icons.photo_library_outlined,
          )
        else
          ...photos.map(
            (photo) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _StrengthPhotoTimelineTile(photo: photo),
            ),
          ),
        if (hasMore) ...[
          const SizedBox(height: 4),
          _LoadOlderButton(
            label: 'Load older photos',
            loading: loadingMore,
            onPressed: onLoadMore,
          ),
        ],
      ],
    );
  }
}

class _PhotoSourceAction extends StatelessWidget {
  const _PhotoSourceAction({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.stroke),
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: AppColors.stroke),
              ),
              child: Icon(icon, color: AppColors.primaryBright, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
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
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(
              Icons.chevron_right_rounded,
              color: AppColors.textMuted,
              size: 20,
            ),
          ],
        ),
      ),
    );
  }
}

class _SelectedPhotoPreview extends StatelessWidget {
  const _SelectedPhotoPreview({
    required this.bytes,
    required this.label,
    required this.filename,
  });

  final Uint8List bytes;
  final String label;
  final String filename;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
            child: AspectRatio(
              aspectRatio: 0.82,
              child: Image.memory(
                bytes,
                fit: BoxFit.cover,
                semanticLabel: '$label progress photo preview',
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                StatusBadge(label: label, color: AppColors.primaryBright),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    filename,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w700,
                    ),
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

class _StrengthFormPanel extends StatelessWidget {
  const _StrengthFormPanel({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
    required this.children,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      glowColor: color,
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: Icon(icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
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
          const SizedBox(height: 18),
          ...children,
        ],
      ),
    );
  }
}

class _StrengthInsightPanel extends StatelessWidget {
  const _StrengthInsightPanel({
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
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: Icon(icon, color: AppColors.primaryBright, size: 20),
              ),
              const SizedBox(width: 12),
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
                        fontWeight: FontWeight.w600,
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

class _StrengthTimelineRow extends StatelessWidget {
  const _StrengthTimelineRow({
    required this.title,
    required this.subtitle,
    required this.badge,
    required this.icon,
    required this.color,
    this.detail,
    this.onTap,
  });

  final String title;
  final String subtitle;
  final String badge;
  final IconData icon;
  final Color color;
  final String? detail;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final cleanDetail = detail?.trim() ?? '';
    final card = PremiumCard(
      glowColor: color,
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Icon(icon, color: color),
          ),
          const SizedBox(width: 12),
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
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (cleanDetail.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    cleanDetail,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 10),
          StatusBadge(label: badge, color: color),
        ],
      ),
    );
    return onTap == null
        ? card
        : InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(24),
            child: card,
          );
  }
}

class _StrengthMeasurementSnapshot extends StatelessWidget {
  const _StrengthMeasurementSnapshot({required this.measurement});

  final Map<String, dynamic> measurement;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      glowColor: AppColors.primaryBright,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: const Icon(
                  Icons.straighten_rounded,
                  color: AppColors.primaryBright,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  _formatDate(measurement['measured_on']),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const StatusBadge(
                label: 'Snapshot',
                color: AppColors.primaryBright,
              ),
            ],
          ),
          const SizedBox(height: 14),
          _MeasurementChipWrap(measurement: measurement),
          if ((measurement['notes']?.toString() ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              measurement['notes'].toString(),
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
            ),
          ],
        ],
      ),
    );
  }
}

class _MeasurementChipWrap extends StatelessWidget {
  const _MeasurementChipWrap({required this.measurement});

  final Map<String, dynamic> measurement;

  @override
  Widget build(BuildContext context) {
    final chips = <Widget>[
      if (measurement['chest_cm'] != null)
        _MetricChip(label: 'Chest', value: '${measurement['chest_cm']} cm'),
      if (measurement['waist_cm'] != null)
        _MetricChip(label: 'Waist', value: '${measurement['waist_cm']} cm'),
      if (measurement['hips_cm'] != null)
        _MetricChip(label: 'Hips', value: '${measurement['hips_cm']} cm'),
      if (measurement['arm_cm'] != null)
        _MetricChip(label: 'Arm', value: '${measurement['arm_cm']} cm'),
      if (measurement['thigh_cm'] != null)
        _MetricChip(label: 'Thigh', value: '${measurement['thigh_cm']} cm'),
      if (measurement['calf_cm'] != null)
        _MetricChip(label: 'Calf', value: '${measurement['calf_cm']} cm'),
      if (measurement['body_fat_percentage'] != null)
        _MetricChip(
          label: 'Body fat',
          value: '${measurement['body_fat_percentage']}%',
        ),
    ];

    if (chips.isEmpty) {
      return const _StrengthMiniEmpty(
        icon: Icons.info_outline_rounded,
        text: 'No measurement values were saved for this snapshot.',
      );
    }

    return Wrap(spacing: 10, runSpacing: 10, children: chips);
  }
}

class _MetricChip extends StatelessWidget {
  const _MetricChip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            value,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
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

class _StrengthPhotoTimelineTile extends StatelessWidget {
  const _StrengthPhotoTimelineTile({required this.photo});

  final Map<String, dynamic> photo;

  @override
  Widget build(BuildContext context) {
    final url = photo['photo_url']?.toString() ?? '';
    return PremiumCard(
      glowColor: AppColors.primaryBright,
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(22),
            child: SizedBox(
              width: 88,
              height: 112,
              child: url.isEmpty
                  ? Container(
                      color: AppColors.surfaceSoft,
                      child: const Icon(Icons.photo_library_outlined),
                    )
                  : Image.network(
                      url,
                      fit: BoxFit.cover,
                      semanticLabel:
                          '${_titleCase(photo['photo_type']?.toString() ?? 'Progress')} photo from ${_formatDate(photo['captured_on'])}',
                      errorBuilder: (_, __, ___) => Container(
                        color: AppColors.surfaceSoft,
                        child: const Icon(Icons.broken_image_outlined),
                      ),
                    ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StatusBadge(
                  label: _titleCase(photo['photo_type']?.toString() ?? 'other'),
                  color: AppColors.primaryBright,
                ),
                const SizedBox(height: 10),
                Text(
                  _formatDate(photo['captured_on']),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  (photo['notes']?.toString() ?? '').trim().isEmpty
                      ? 'No notes added.'
                      : photo['notes'].toString(),
                  maxLines: 3,
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

class _PhotoFrame extends StatelessWidget {
  const _PhotoFrame({required this.label, required this.photo});

  final String label;
  final Map<String, dynamic> photo;

  @override
  Widget build(BuildContext context) {
    final url = photo['photo_url']?.toString() ?? '';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        StatusBadge(label: label, color: AppColors.primaryBright),
        const SizedBox(height: AppSpacing.sm),
        ClipRRect(
          borderRadius: BorderRadius.circular(22),
          child: AspectRatio(
            aspectRatio: 0.78,
            child: url.isEmpty
                ? Container(
                    color: AppColors.surfaceSoft,
                    child: const Icon(Icons.photo_library_outlined),
                  )
                : Image.network(
                    url,
                    fit: BoxFit.cover,
                    semanticLabel:
                        '$label progress photo from ${_formatDate(photo['captured_on'])}',
                    errorBuilder: (_, __, ___) => Container(
                      color: AppColors.surfaceSoft,
                      child: const Icon(Icons.broken_image_outlined),
                    ),
                  ),
          ),
        ),
      ],
    );
  }
}

class _StrengthSectionTitle extends StatelessWidget {
  const _StrengthSectionTitle({required this.title, required this.action});

  final String title;
  final String action;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        Text(
          action,
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
            color: AppColors.textSecondary,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _StrengthEmptyPanel extends StatelessWidget {
  const _StrengthEmptyPanel({
    required this.title,
    required this.message,
    required this.icon,
  });

  final String title;
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(22),
      child: Column(
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Icon(icon, color: AppColors.primaryBright),
          ),
          const SizedBox(height: 14),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _StrengthMiniEmpty extends StatelessWidget {
  const _StrengthMiniEmpty({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        children: [
          Icon(icon, color: AppColors.primaryBright),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _LoadOlderButton extends StatelessWidget {
  const _LoadOlderButton({
    required this.label,
    required this.loading,
    required this.onPressed,
  });

  final String label;
  final bool loading;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: loading ? null : onPressed,
        icon: loading
            ? const SizedBox.square(
                dimension: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.expand_more_rounded),
        label: Text(loading ? 'Loading…' : label),
      ),
    );
  }
}

class _NumberField extends StatelessWidget {
  const _NumberField({
    required this.controller,
    required this.label,
    this.unit = 'cm',
  });

  final TextEditingController controller;
  final String label;
  final String unit;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(labelText: label, suffixText: unit),
    );
  }
}

class _ProgressSkeleton extends StatelessWidget {
  const _ProgressSkeleton();

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: const [
          SkeletonProfileHeader(),
          SizedBox(height: AppSpacing.lg),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonHistoryList(items: 4),
        ],
      ),
    );
  }
}

double _asDouble(Object? value) {
  if (value is num) {
    return value.toDouble();
  }
  return double.tryParse('$value') ?? 0;
}

double? _nullableDouble(String value) {
  final trimmed = value.trim();
  if (trimmed.isEmpty) {
    return null;
  }
  return double.tryParse(trimmed);
}

String? _nullable(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

String _formatDate(Object? value) {
  final text = value?.toString() ?? '';
  final date = DateTime.tryParse(text);
  if (date == null) {
    return text.isEmpty ? 'Unknown date' : text;
  }
  return DateFormat('dd MMM yyyy').format(date.toLocal());
}

String _formatCompactNumber(num value) {
  if (value >= 1000000) {
    return '${(value / 1000000).toStringAsFixed(1)}M';
  }
  if (value >= 1000) {
    return '${(value / 1000).toStringAsFixed(1)}K';
  }
  return value.toStringAsFixed(0);
}

String _titleCase(String value) {
  if (value.isEmpty) {
    return value;
  }
  return value
      .split(RegExp(r'[_\s-]+'))
      .where((part) => part.isNotEmpty)
      .map((part) => part[0].toUpperCase() + part.substring(1).toLowerCase())
      .join(' ');
}

List<MetricChartPoint> _metricPoints(
  List<Map<String, dynamic>> rows,
  String dateKey,
  String valueKey,
) {
  final points =
      rows
          .where((row) => row[valueKey] != null)
          .map((row) {
            final date = DateTime.tryParse(row[dateKey]?.toString() ?? '');
            final value = double.tryParse(row[valueKey].toString());
            if (date == null || value == null) return null;
            return (date: date, value: value);
          })
          .whereType<({DateTime date, double value})>()
          .toList()
        ..sort((left, right) => left.date.compareTo(right.date));

  return points
      .map(
        (point) => MetricChartPoint(
          label: DateFormat('d MMM').format(point.date.toLocal()),
          value: point.value,
          timestamp: point.date,
        ),
      )
      .toList();
}
