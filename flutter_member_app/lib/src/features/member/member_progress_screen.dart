import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/confirmation_dialog.dart';
import 'member_repository.dart';

class MemberProgressScreen extends StatefulWidget {
  const MemberProgressScreen({
    super.key,
    required this.repository,
    required this.initialSummary,
    required this.onRefreshParent,
  });

  final MemberRepository repository;
  final Map<String, dynamic> initialSummary;
  final Future<void> Function() onRefreshParent;

  @override
  State<MemberProgressScreen> createState() => _MemberProgressScreenState();
}

class _MemberProgressScreenState extends State<MemberProgressScreen>
    with SingleTickerProviderStateMixin {
  bool _loading = true;
  bool _savingWeight = false;
  bool _savingMeasurement = false;
  bool _savingPhoto = false;
  String? _error;
  String? _lastSuccessMessage;
  Map<String, dynamic> _summary = const {};
  Map<String, dynamic> _todaySteps = const {};
  List<Map<String, dynamic>> _stepSummary = const [];
  List<Map<String, dynamic>> _weightLogs = const [];
  List<Map<String, dynamic>> _bodyMeasurements = const [];
  List<Map<String, dynamic>> _photos = const [];

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
  final _photoUrlController = TextEditingController();
  final _photoNotesController = TextEditingController();
  late final TabController _tabController;
  String _photoType = 'front';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 5, vsync: this);
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
    _photoUrlController.dispose();
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
        widget.repository.fetchTodaySteps(),
        widget.repository.fetchStepSummary(range: '7d'),
      ]);

      _summary = Map<String, dynamic>.from(
        results[0]['data'] as Map? ?? const {},
      );
      _weightLogs = (results[1]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _bodyMeasurements = (results[2]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _photos = (results[3]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _todaySteps = Map<String, dynamic>.from(
        results[4]['data'] as Map? ?? const {},
      );
      _stepSummary = (results[5]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
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

  Future<void> _showUploadUnavailableDialog() async {
    await showDialog<bool>(
      context: context,
      builder: (context) => const ConfirmationDialog(
        title: 'Device upload is not available yet',
        message:
            'This member API currently accepts hosted photo URLs only. Local gallery upload and media permission prompts are intentionally disabled in this build so progress photo actions stay safe and never crash.',
        confirmLabel: 'Understood',
      ),
    );
  }

  Future<void> _saveWeight() async {
    final weight = double.tryParse(_weightController.text.trim());
    if (weight == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter a valid weight value.')),
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
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _savingWeight = false);
      }
    }
  }

  Future<void> _saveMeasurement() async {
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
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _savingMeasurement = false);
      }
    }
  }

  Future<void> _savePhoto() async {
    final photoUrl = _photoUrlController.text.trim();
    final uri = Uri.tryParse(photoUrl);
    if (photoUrl.isEmpty || uri == null || !uri.hasScheme) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter a valid hosted photo URL.')),
      );
      return;
    }

    setState(() => _savingPhoto = true);
    try {
      await widget.repository.addProgressPhoto({
        'photo_url': photoUrl,
        'photo_type': _photoType,
        'captured_on': DateTime.now().toIso8601String().split('T').first,
        'notes': _nullable(_photoNotesController.text),
      });
      _photoUrlController.clear();
      _photoNotesController.clear();
      await _afterSave('Progress photo saved.');
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
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
      title: 'Strength Tracking',
      subtitle: 'Weight, measurements, and progress photos',
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
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
                        latestWeight: latestWeight,
                        latestMeasurement: latestMeasurement,
                        recentPhotos: recentPhotos,
                        weightLogs: _weightLogs,
                        bodyMeasurements: _bodyMeasurements,
                        photos: _photos,
                        todaySteps: _todaySteps,
                        stepSummary: _stepSummary,
                      ),
                      _StepHistoryTab(
                        todaySteps: _todaySteps,
                        stepSummary: _stepSummary,
                      ),
                      _WeightLogsTab(
                        weightLogs: _weightLogs,
                        weightController: _weightController,
                        notesController: _weightNotesController,
                        saving: _savingWeight,
                        onSave: _saveWeight,
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
                      ),
                      _ProgressPhotosTab(
                        photos: _photos,
                        recentPhotos: recentPhotos,
                        photoUrlController: _photoUrlController,
                        notesController: _photoNotesController,
                        selectedType: _photoType,
                        onTypeChanged: (value) =>
                            setState(() => _photoType = value),
                        onDeviceUploadPressed: _showUploadUnavailableDialog,
                        saving: _savingPhoto,
                        onSave: _savePhoto,
                      ),
                    ],
                  ),
                ),
              ],
            ),
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

    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0.96, end: 1),
      duration: const Duration(milliseconds: 420),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) =>
          Transform.scale(scale: value, child: child),
      child: Container(
        padding: const EdgeInsets.all(22),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(32),
          gradient: const LinearGradient(
            colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD), Color(0xFFC58BF2)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: 0.24),
              blurRadius: 26,
              offset: const Offset(0, 16),
            ),
          ],
        ),
        child: Stack(
          children: [
            Positioned(
              right: -28,
              top: -36,
              child: _SoftOrb(
                size: 118,
                color: Colors.white.withValues(alpha: 0.22),
              ),
            ),
            Positioned(
              right: 36,
              bottom: 42,
              child: _SoftOrb(
                size: 44,
                color: Colors.white.withValues(alpha: 0.16),
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 260),
                  child: successMessage == null
                      ? const SizedBox.shrink()
                      : Padding(
                          key: ValueKey(successMessage),
                          padding: const EdgeInsets.only(bottom: 14),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 10,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.20),
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(
                                color: Colors.white.withValues(alpha: 0.28),
                              ),
                            ),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.check_circle_rounded,
                                  color: Colors.white,
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
                                          color: Colors.white,
                                          fontWeight: FontWeight.w800,
                                        ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                ),
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Strength Tracking',
                            style: Theme.of(context).textTheme.headlineSmall
                                ?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Body metrics, progress photos, and transformation timeline.',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.88),
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    _StrengthHeaderStat(value: weightLabel, label: 'latest'),
                  ],
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: _StrengthHeaderMiniStat(
                        value: '$weightCount',
                        label: 'Weight',
                        icon: Icons.monitor_weight_rounded,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _StrengthHeaderMiniStat(
                        value: '$measurementCount',
                        label: 'Measures',
                        icon: Icons.straighten_rounded,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _StrengthHeaderMiniStat(
                        value: '$stepCount',
                        label: 'Step days',
                        icon: Icons.directions_walk_rounded,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _StrengthHeaderMiniStat(
                        value: '$photoCount',
                        label: 'Photos',
                        icon: Icons.photo_camera_back_rounded,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                _StrengthTabSlider(controller: tabController),
              ],
            ),
          ],
        ),
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
    (label: 'Overview', icon: Icons.dashboard_customize_rounded),
    (label: 'Steps', icon: Icons.directions_walk_rounded),
    (label: 'Weight', icon: Icons.monitor_weight_rounded),
    (label: 'Measure', icon: Icons.straighten_rounded),
    (label: 'Photos', icon: Icons.photo_camera_back_rounded),
  ];

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
          final compact = constraints.maxWidth < 390;
          return Row(
            children: [
              for (var index = 0; index < _items.length; index++)
                Expanded(
                  child: _StrengthTabPill(
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

class _StrengthTabPill extends StatelessWidget {
  const _StrengthTabPill({
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
        duration: const Duration(milliseconds: 220),
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

class _ProgressOverviewTab extends StatelessWidget {
  const _ProgressOverviewTab({
    required this.latestWeight,
    required this.latestMeasurement,
    required this.recentPhotos,
    required this.weightLogs,
    required this.bodyMeasurements,
    required this.photos,
    required this.todaySteps,
    required this.stepSummary,
  });

  final Map<String, dynamic> latestWeight;
  final Map<String, dynamic> latestMeasurement;
  final List<Map<String, dynamic>> recentPhotos;
  final List<Map<String, dynamic>> weightLogs;
  final List<Map<String, dynamic>> bodyMeasurements;
  final List<Map<String, dynamic>> photos;
  final Map<String, dynamic> todaySteps;
  final List<Map<String, dynamic>> stepSummary;

  @override
  Widget build(BuildContext context) {
    if (weightLogs.isEmpty && bodyMeasurements.isEmpty && photos.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(AppSpacing.lg),
        child: _StrengthEmptyPanel(
          title: 'Start your strength profile',
          message:
              'Add weight, measurements, or progress photos to build a clear body timeline.',
          icon: Icons.insights_rounded,
        ),
      );
    }

    final latestWeightValue = _asDouble(latestWeight['weight_kg']);
    final previousWeightValue = weightLogs.length > 1
        ? _asDouble(weightLogs[1]['weight_kg'])
        : latestWeightValue;
    final delta = latestWeightValue - previousWeightValue;
    final trendText = weightLogs.length < 2
        ? 'Building baseline'
        : delta == 0
        ? 'Stable'
        : delta < 0
        ? '${delta.abs().toStringAsFixed(1)} kg down'
        : '${delta.toStringAsFixed(1)} kg up';

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        _StrengthMetricRail(
          items: [
            _StrengthMetricData(
              label: 'Latest weight',
              value: latestWeightValue > 0
                  ? '${latestWeightValue.toStringAsFixed(1)} kg'
                  : 'No log',
              icon: Icons.monitor_weight_rounded,
              color: const Color(0xFF92A3FD),
            ),
            _StrengthMetricData(
              label: 'Today steps',
              value: _formatCompactNumber(
                (todaySteps['steps'] as num?)?.toInt() ?? 0,
              ),
              icon: Icons.directions_walk_rounded,
              color: const Color(0xFF40D9B8),
            ),
            _StrengthMetricData(
              label: 'Trend',
              value: trendText,
              icon: Icons.show_chart_rounded,
              color: const Color(0xFF40D9B8),
            ),
            _StrengthMetricData(
              label: 'Measure logs',
              value: '${bodyMeasurements.length}',
              icon: Icons.straighten_rounded,
              color: const Color(0xFFC58BF2),
            ),
            _StrengthMetricData(
              label: 'Photos',
              value: '${photos.length}',
              icon: Icons.photo_camera_back_rounded,
              color: const Color(0xFFFFB86C),
            ),
          ],
        ),
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

class _StepHistoryTab extends StatelessWidget {
  const _StepHistoryTab({required this.todaySteps, required this.stepSummary});

  final Map<String, dynamic> todaySteps;
  final List<Map<String, dynamic>> stepSummary;

  @override
  Widget build(BuildContext context) {
    final today = (todaySteps['steps'] as num?)?.toInt() ?? 0;
    final goal = (todaySteps['goalSteps'] as num?)?.toInt() ?? 10000;
    final progress = goal <= 0
        ? 0
        : ((today / goal) * 100).round().clamp(0, 100);
    final weeklyTotal = stepSummary.fold<int>(
      0,
      (sum, item) => sum + ((item['steps'] as num?)?.toInt() ?? 0),
    );
    final activeDays = stepSummary
        .where((item) => ((item['steps'] as num?)?.toInt() ?? 0) > 0)
        .length;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        _StrengthMetricRail(
          items: [
            _StrengthMetricData(
              label: 'Today',
              value: _formatCompactNumber(today),
              icon: Icons.directions_walk_rounded,
              color: const Color(0xFF40D9B8),
            ),
            _StrengthMetricData(
              label: 'Goal progress',
              value: '$progress%',
              icon: Icons.track_changes_rounded,
              color: const Color(0xFF92A3FD),
            ),
            _StrengthMetricData(
              label: '7 day total',
              value: _formatCompactNumber(weeklyTotal),
              icon: Icons.calendar_month_rounded,
              color: const Color(0xFFC58BF2),
            ),
            _StrengthMetricData(
              label: 'Active days',
              value: '$activeDays/${stepSummary.length}',
              icon: Icons.local_fire_department_rounded,
              color: const Color(0xFFFFB86C),
            ),
          ],
        ),
        const SizedBox(height: 18),
        _StrengthInsightPanel(
          title: 'Server-backed step history',
          subtitle:
              'Synced steps are stored on your profile, so this view works across app refreshes.',
          icon: Icons.query_stats_rounded,
          child: stepSummary.isEmpty
              ? const _StrengthMiniEmpty(
                  icon: Icons.directions_walk_rounded,
                  text:
                      'Step history will appear after your first successful sync.',
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
                        badge: steps > 0 ? 'Synced' : 'Rest',
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
    required this.saving,
    required this.onSave,
  });

  final List<Map<String, dynamic>> weightLogs;
  final TextEditingController weightController;
  final TextEditingController notesController;
  final bool saving;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
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

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
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
                    label: 'Body fat %',
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
      ],
    );
  }
}

class _ProgressPhotosTab extends StatelessWidget {
  const _ProgressPhotosTab({
    required this.photos,
    required this.recentPhotos,
    required this.photoUrlController,
    required this.notesController,
    required this.selectedType,
    required this.onTypeChanged,
    required this.onDeviceUploadPressed,
    required this.saving,
    required this.onSave,
  });

  final List<Map<String, dynamic>> photos;
  final List<Map<String, dynamic>> recentPhotos;
  final TextEditingController photoUrlController;
  final TextEditingController notesController;
  final String selectedType;
  final ValueChanged<String> onTypeChanged;
  final VoidCallback onDeviceUploadPressed;
  final bool saving;
  final VoidCallback onSave;

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
              'Hosted URL only in this API build. Device upload is disabled safely.',
          icon: Icons.photo_camera_back_rounded,
          color: const Color(0xFFFFB86C),
          children: [
            OutlinedButton.icon(
              onPressed: onDeviceUploadPressed,
              icon: const Icon(Icons.perm_media_rounded),
              label: const Text('Upload from device'),
            ),
            const SizedBox(height: AppSpacing.sm),
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
              controller: photoUrlController,
              decoration: const InputDecoration(
                labelText: 'Hosted photo URL',
                prefixIcon: Icon(Icons.link_rounded),
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
              label: 'Save Progress Photo',
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
                'Add hosted photo links over time to build a transformation timeline.',
            icon: Icons.photo_library_outlined,
          )
        else
          ...photos.map(
            (photo) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _StrengthPhotoTimelineTile(photo: photo),
            ),
          ),
      ],
    );
  }
}

class _StrengthMetricRail extends StatelessWidget {
  const _StrengthMetricRail({required this.items});

  final List<_StrengthMetricData> items;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 118,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (context, index) =>
            _StrengthMetricTile(data: items[index]),
      ),
    );
  }
}

class _StrengthMetricTile extends StatelessWidget {
  const _StrengthMetricTile({required this.data});

  final _StrengthMetricData data;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 320 + data.label.length * 8),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) => Opacity(
        opacity: value,
        child: Transform.translate(
          offset: Offset(18 * (1 - value), 0),
          child: child,
        ),
      ),
      child: Container(
        width: 168,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(26),
          boxShadow: [
            BoxShadow(
              color: data.color.withValues(alpha: 0.14),
              blurRadius: 20,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: data.color.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(15),
              ),
              child: Icon(data.icon, color: data.color, size: 20),
            ),
            const Spacer(),
            Text(
              data.value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              data.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StrengthMetricData {
  const _StrengthMetricData({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
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
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: color.withValues(alpha: 0.14),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [color.withValues(alpha: 0.82), color],
                  ),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Icon(icon, color: Colors.white),
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
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFFFFFF), Color(0xFFF7F9FF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.10),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
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
                  color: const Color(0xFFC58BF2).withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(icon, color: const Color(0xFFC58BF2), size: 20),
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
  });

  final String title;
  final String subtitle;
  final String badge;
  final IconData icon;
  final Color color;
  final String? detail;

  @override
  Widget build(BuildContext context) {
    final cleanDetail = detail?.trim() ?? '';
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: color.withValues(alpha: 0.10),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.13),
              borderRadius: BorderRadius.circular(18),
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
  }
}

class _StrengthMeasurementSnapshot extends StatelessWidget {
  const _StrengthMeasurementSnapshot({required this.measurement});

  final Map<String, dynamic> measurement;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFC58BF2).withValues(alpha: 0.12),
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
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: const Color(0xFFC58BF2).withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(
                  Icons.straighten_rounded,
                  color: Color(0xFFC58BF2),
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
              const StatusBadge(label: 'Snapshot', color: Color(0xFFC58BF2)),
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
        color: const Color(0xFFF7F8F8),
        borderRadius: BorderRadius.circular(18),
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
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFFFB86C).withValues(alpha: 0.12),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
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
                      color: const Color(0xFFF7F8F8),
                      child: const Icon(Icons.photo_library_outlined),
                    )
                  : Image.network(
                      url,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(
                        color: const Color(0xFFF7F8F8),
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
                  color: const Color(0xFFFFB86C),
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
        StatusBadge(label: label, color: const Color(0xFFC58BF2)),
        const SizedBox(height: AppSpacing.sm),
        ClipRRect(
          borderRadius: BorderRadius.circular(22),
          child: AspectRatio(
            aspectRatio: 0.78,
            child: url.isEmpty
                ? Container(
                    color: const Color(0xFFF7F8F8),
                    child: const Icon(Icons.photo_library_outlined),
                  )
                : Image.network(
                    url,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(
                      color: const Color(0xFFF7F8F8),
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
            color: AppColors.primary,
            fontWeight: FontWeight.w900,
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
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.10),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
              ),
              borderRadius: BorderRadius.circular(22),
            ),
            child: Icon(icon, color: Colors.white),
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
        color: const Color(0xFFF7F8F8),
        borderRadius: BorderRadius.circular(22),
      ),
      child: Row(
        children: [
          Icon(icon, color: AppColors.primary),
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

class _StrengthHeaderStat extends StatelessWidget {
  const _StrengthHeaderStat({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 104),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.20),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: Colors.white.withValues(alpha: 0.20)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Colors.white.withValues(alpha: 0.78),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _StrengthHeaderMiniStat extends StatelessWidget {
  const _StrengthHeaderMiniStat({
    required this.value,
    required this.label,
    required this.icon,
  });

  final String value;
  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Icon(icon, color: Colors.white, size: 17),
          const SizedBox(width: 7),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.78),
                    fontWeight: FontWeight.w700,
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

class _SoftOrb extends StatelessWidget {
  const _SoftOrb({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}

class _NumberField extends StatelessWidget {
  const _NumberField({required this.controller, required this.label});

  final TextEditingController controller;
  final String label;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      decoration: InputDecoration(labelText: label),
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
