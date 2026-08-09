import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:gym_flutter_core/diet_plan_summary_view.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import '../../core/pagination.dart';
import 'trainer_repository.dart';

class TrainerMemberDetailScreen extends StatefulWidget {
  const TrainerMemberDetailScreen({
    super.key,
    required this.assignment,
    required this.repository,
    required this.onAssignWorkout,
    required this.onAssignDiet,
    required this.onMessage,
    required this.onAddCoachingNote,
  });

  final Map<String, dynamic> assignment;
  final TrainerRepository repository;
  final VoidCallback onAssignWorkout;
  final Future<void> Function() onAssignDiet;
  final VoidCallback onMessage;
  final VoidCallback onAddCoachingNote;

  @override
  State<TrainerMemberDetailScreen> createState() =>
      _TrainerMemberDetailScreenState();
}

class _TrainerMemberDetailScreenState extends State<TrainerMemberDetailScreen> {
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  Map<String, dynamic> _detail = const {};
  Map<String, dynamic> _progress = const {};
  List<Map<String, dynamic>> _attendance = const [];
  List<Map<String, dynamic>> _plans = const [];
  List<Map<String, dynamic>> _dietPlans = const [];
  List<Map<String, dynamic>> _notes = const [];
  List<Map<String, dynamic>> _workoutHistory = const [];
  List<Map<String, dynamic>> _personalRecords = const [];
  ApiPagination _attendancePage = const ApiPagination.singlePage();
  ApiPagination _planPage = const ApiPagination.singlePage();
  ApiPagination _dietPage = const ApiPagination.singlePage();
  ApiPagination _notePage = const ApiPagination.singlePage();
  ApiPagination _logbookPage = const ApiPagination.singlePage();
  ApiPagination _recordPage = const ApiPagination.singlePage();
  ApiPagination _progressWeightPage = const ApiPagination.singlePage();
  ApiPagination _progressMeasurementPage = const ApiPagination.singlePage();
  ApiPagination _progressPhotoPage = const ApiPagination.singlePage();
  ApiPagination _progressRecordPage = const ApiPagination.singlePage();

  bool get _hasMore =>
      _attendancePage.hasMore ||
      _planPage.hasMore ||
      _dietPage.hasMore ||
      _notePage.hasMore ||
      _logbookPage.hasMore ||
      _recordPage.hasMore ||
      _progressWeightPage.hasMore ||
      _progressMeasurementPage.hasMore ||
      _progressPhotoPage.hasMore ||
      _progressRecordPage.hasMore;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final memberId = (widget.assignment['member_id'] as num?)?.toInt();
    if (memberId == null) {
      setState(() {
        _loading = false;
        _error = 'Member id is missing for this assignment.';
      });
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final responses = await Future.wait([
        widget.repository.fetchMemberDetail(memberId),
        widget.repository.fetchMemberAttendance(memberId),
        widget.repository.fetchMemberProgress(memberId),
        widget.repository.fetchMemberPlans(memberId),
        widget.repository.fetchMemberNotes(memberId),
        widget.repository.fetchMemberWorkoutLogbook(memberId),
      ]);
      List<Map<String, dynamic>> dietPlans = const [];
      ApiPagination dietPage = const ApiPagination.singlePage();
      try {
        final dietResponse = await widget.repository.fetchDietPlans(
          memberId: memberId,
        );
        dietPlans = _mapList(dietResponse['data']);
        dietPage = ApiPagination.fromResponse(dietResponse);
      } catch (_) {
        // Diet permissions may lag during deployment. Member access and the
        // rest of the coaching profile must remain available independently.
      }

      final detail = _map(responses[0]['data']);
      final notes = _mapList(responses[4]['data']);
      final logbook = _map(responses[5]['data']);
      final logbookEnvelope = <String, dynamic>{
        'data': logbook['history'],
        'meta': logbook['meta'],
      };

      setState(() {
        _detail = detail;
        _progress = _map(responses[2]['data']);
        _progressWeightPage = _namedPagination(
          responses[2],
          'weight_logs_pagination',
        );
        _progressMeasurementPage = _namedPagination(
          responses[2],
          'body_measurements_pagination',
        );
        _progressPhotoPage = _namedPagination(
          responses[2],
          'progress_photos_pagination',
        );
        _progressRecordPage = _namedPagination(
          responses[2],
          'personal_records_pagination',
        );
        _attendance = _mapList(responses[1]['data']);
        _attendancePage = ApiPagination.fromResponse(responses[1]);
        _plans = _mapList(responses[3]['data']);
        _planPage = ApiPagination.fromResponse(responses[3]);
        _notes = notes;
        _notePage = ApiPagination.fromResponse(responses[4]);
        _workoutHistory = _mapList(logbook['history']);
        _logbookPage = ApiPagination.fromResponse(logbookEnvelope);
        _personalRecords = _mapList(logbook['personal_records']);
        _recordPage = ApiPagination.fromResponse({
          'meta': {
            'pagination': _map(
              _map(logbook['meta'])['personal_records_pagination'],
            ),
          },
        });
        _dietPlans = dietPlans;
        _dietPage = dietPage;
        _loading = false;
      });
    } catch (exception) {
      setState(() {
        _loading = false;
        _error = exception.toString();
      });
    }
  }

  Future<void> _loadMore() async {
    final memberId = (widget.assignment['member_id'] as num?)?.toInt();
    if (memberId == null || _loadingMore || !_hasMore) return;
    setState(() => _loadingMore = true);
    try {
      if (_attendancePage.hasMore) {
        final response = await widget.repository.fetchMemberAttendance(
          memberId,
          page: _attendancePage.nextPage,
        );
        _attendance = mergeApiPageItems(_attendance, apiPageItems(response));
        _attendancePage = ApiPagination.fromResponse(response);
      }
      if (_planPage.hasMore) {
        final response = await widget.repository.fetchMemberPlans(
          memberId,
          page: _planPage.nextPage,
        );
        _plans = mergeApiPageItems(_plans, apiPageItems(response));
        _planPage = ApiPagination.fromResponse(response);
      }
      if (_dietPage.hasMore) {
        final response = await widget.repository.fetchDietPlans(
          memberId: memberId,
          page: _dietPage.nextPage,
        );
        _dietPlans = mergeApiPageItems(_dietPlans, apiPageItems(response));
        _dietPage = ApiPagination.fromResponse(response);
      }
      if (_notePage.hasMore) {
        final response = await widget.repository.fetchMemberNotes(
          memberId,
          page: _notePage.nextPage,
        );
        _notes = mergeApiPageItems(_notes, apiPageItems(response));
        _notePage = ApiPagination.fromResponse(response);
      }
      if (_logbookPage.hasMore) {
        final response = await widget.repository.fetchMemberWorkoutLogbook(
          memberId,
          page: _logbookPage.nextPage,
        );
        final data = _map(response['data']);
        _workoutHistory = mergeApiPageItems(
          _workoutHistory,
          _mapList(data['history']),
        );
        _logbookPage = ApiPagination.fromResponse({
          'data': data['history'],
          'meta': data['meta'],
        });
      }
      if (_recordPage.hasMore) {
        final response = await widget.repository.fetchMemberWorkoutLogbook(
          memberId,
          recordsPage: _recordPage.nextPage,
        );
        final data = _map(response['data']);
        _personalRecords = mergeApiPageItems(
          _personalRecords,
          _mapList(data['personal_records']),
        );
        _recordPage = ApiPagination.fromResponse({
          'meta': {
            'pagination': _map(
              _map(data['meta'])['personal_records_pagination'],
            ),
          },
        });
      }
      if (_progressWeightPage.hasMore ||
          _progressMeasurementPage.hasMore ||
          _progressPhotoPage.hasMore ||
          _progressRecordPage.hasMore) {
        final response = await widget.repository.fetchMemberProgress(
          memberId,
          weightPage: _progressWeightPage.hasMore
              ? _progressWeightPage.nextPage
              : _progressWeightPage.currentPage,
          measurementPage: _progressMeasurementPage.hasMore
              ? _progressMeasurementPage.nextPage
              : _progressMeasurementPage.currentPage,
          photoPage: _progressPhotoPage.hasMore
              ? _progressPhotoPage.nextPage
              : _progressPhotoPage.currentPage,
          recordPage: _progressRecordPage.hasMore
              ? _progressRecordPage.nextPage
              : _progressRecordPage.currentPage,
        );
        final data = _map(response['data']);
        _progress = {
          ..._progress,
          ...data,
          'weight_logs': mergeApiPageItems(
            _mapList(_progress['weight_logs']),
            _mapList(data['weight_logs']),
          ),
          'body_measurements': mergeApiPageItems(
            _mapList(_progress['body_measurements']),
            _mapList(data['body_measurements']),
          ),
          'progress_photos': mergeApiPageItems(
            _mapList(_progress['progress_photos']),
            _mapList(data['progress_photos']),
          ),
          'personal_records': mergeApiPageItems(
            _mapList(_progress['personal_records']),
            _mapList(data['personal_records']),
          ),
        };
        _progressWeightPage = _namedPagination(
          response,
          'weight_logs_pagination',
        );
        _progressMeasurementPage = _namedPagination(
          response,
          'body_measurements_pagination',
        );
        _progressPhotoPage = _namedPagination(
          response,
          'progress_photos_pagination',
        );
        _progressRecordPage = _namedPagination(
          response,
          'personal_records_pagination',
        );
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _assignDiet() async {
    await widget.onAssignDiet();
    if (mounted) {
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final member = _map(_detail['member']);
    final memberProfile = _map(_detail['member_profile']);
    final membershipSummary = _map(_detail['membership_summary']);
    final attendanceSummary = _map(_detail['attendance_summary']);
    final progressSummary = _map(_detail['progress_summary']);
    final photos = _mapList(_progress['progress_photos']);
    final weightLogs = _mapList(_progress['weight_logs']);
    final bodyMeasurements = _mapList(_progress['body_measurements']);
    final displayName = member['name']?.toString() ?? 'Assigned member detail';
    final gym = _map(_detail['gym']);
    final branch = _map(_detail['branch']);
    final gymLabel = gym['name']?.toString().trim().isNotEmpty == true
        ? gym['name'].toString()
        : 'Gym ${_detail['gym_id'] ?? widget.assignment['gym_id'] ?? '--'}';
    final branchLabel = branch['name']?.toString().trim().isNotEmpty == true
        ? branch['name'].toString()
        : 'Branch ${_detail['branch_id'] ?? widget.assignment['branch_id'] ?? '--'}';

    return AppGradientScaffold(
      title: displayName,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 10, 18, 8),
            child: _MemberDetailPageHeader(
              name: displayName,
              subtitle: '$gymLabel · $branchLabel',
              onBack: () => Navigator.of(context).maybePop(),
            ),
          ),
          Expanded(
            child: _loading
                ? const LoadingStateView(label: 'Loading member detail...')
                : _error != null
                ? _buildError()
                : RefreshIndicator(
                    onRefresh: _load,
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(18, 8, 18, 32),
                      children: [
                        _FitMemberHero(
                          name: displayName,
                          email:
                              member['email']?.toString() ?? 'Assigned member',
                          avatarUrl: member['avatar']?.toString(),
                          goal:
                              progressSummary['fitness_goal']?.toString() ??
                              memberProfile['fitness_goal']?.toString() ??
                              'No fitness goal set',
                          membershipStatus: _titleCase(
                            membershipSummary['status']?.toString() ?? 'active',
                          ),
                          attendanceStatus: _attendanceLabel(attendanceSummary),
                          workoutStatus: _workoutCompletionLabel(_plans),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: _FitStatCell(
                                title: memberProfile['height_cm'] != null
                                    ? '${memberProfile['height_cm']}cm'
                                    : '--',
                                subtitle: 'Height',
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _FitStatCell(
                                title: progressSummary['weight_kg'] != null
                                    ? '${progressSummary['weight_kg']}kg'
                                    : (memberProfile['weight_kg'] != null
                                          ? '${memberProfile['weight_kg']}kg'
                                          : '--'),
                                subtitle: 'Weight',
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _FitStatCell(
                                title: '${_attendance.length}',
                                subtitle: 'Visits',
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        _FitActionPanel(
                          onAssignWorkout: widget.onAssignWorkout,
                          onAssignDiet: _assignDiet,
                          onMessage: widget.onMessage,
                          onAddCoachingNote: widget.onAddCoachingNote,
                        ),
                        const SizedBox(height: 18),
                        _FitSectionCard(
                          title: 'Overview',
                          icon: Icons.person_outline_rounded,
                          child: _OverviewTab(
                            memberProfile: memberProfile,
                            membershipSummary: membershipSummary,
                            attendanceSummary: attendanceSummary,
                            attendance: _attendance,
                            progressSummary: progressSummary,
                            planCount: _plans.length,
                            dietPlanCount: _dietPlans.length,
                          ),
                        ),
                        const SizedBox(height: 18),
                        _FitSectionCard(
                          title: 'Diet Plans',
                          icon: Icons.restaurant_menu_rounded,
                          child: _DietPlansTab(
                            plans: _dietPlans,
                            onAssign: _assignDiet,
                          ),
                        ),
                        const SizedBox(height: 18),
                        _FitSectionCard(
                          title: 'Progress',
                          icon: Icons.trending_up_rounded,
                          child: _ProgressTab(
                            progress: _progress,
                            photos: photos,
                            weightLogs: weightLogs,
                            bodyMeasurements: bodyMeasurements,
                          ),
                        ),
                        const SizedBox(height: 18),
                        _FitSectionCard(
                          title: 'Logbook',
                          icon: Icons.fitness_center_rounded,
                          child: _LogbookTab(
                            history: _workoutHistory,
                            personalRecords: _personalRecords,
                          ),
                        ),
                        const SizedBox(height: 18),
                        _FitSectionCard(
                          title: 'Notes',
                          icon: Icons.edit_note_rounded,
                          child: _NotesTab(notes: _notes),
                        ),
                        if (_hasMore) ...[
                          const SizedBox(height: 18),
                          Center(
                            child: OutlinedButton.icon(
                              onPressed: _loadingMore ? null : _loadMore,
                              icon: _loadingMore
                                  ? const SizedBox.square(
                                      dimension: 16,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(Icons.expand_more_rounded),
                              label: Text(
                                _loadingMore
                                    ? 'Loading...'
                                    : 'Load older coaching data',
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildError() {
    final denied =
        _error?.toLowerCase().contains('permission') == true ||
        _error?.contains('403') == true;

    if (denied) {
      return EmptyStateView(
        title: 'Access denied',
        message:
            'You can only view members currently assigned to you. Ask your gym admin if this assignment should exist.',
        icon: Icons.lock_outline_rounded,
        action: SizedBox(
          width: 220,
          child: GradientButton(
            label: 'Back',
            icon: Icons.arrow_back_rounded,
            expanded: true,
            onPressed: () => Navigator.of(context).pop(),
          ),
        ),
      );
    }

    return ErrorStateView(message: _error!, onRetry: _load);
  }
}

class _MemberDetailPageHeader extends StatelessWidget {
  const _MemberDetailPageHeader({
    required this.name,
    required this.subtitle,
    required this.onBack,
  });

  final String name;
  final String subtitle;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _MemberHeaderIconButton(
          icon: Icons.arrow_back_ios_new_rounded,
          onTap: onBack,
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.7,
                  height: 1.02,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _MemberHeaderIconButton extends StatelessWidget {
  const _MemberHeaderIconButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.stroke),
            boxShadow: [
              BoxShadow(
                color: AppColors.shadow.withValues(alpha: 0.05),
                blurRadius: 14,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Icon(icon, color: AppColors.textPrimary, size: 19),
        ),
      ),
    );
  }
}

class _FitMemberHero extends StatelessWidget {
  const _FitMemberHero({
    required this.name,
    required this.email,
    required this.avatarUrl,
    required this.goal,
    required this.membershipStatus,
    required this.attendanceStatus,
    required this.workoutStatus,
  });

  final String name;
  final String email;
  final String? avatarUrl;
  final String goal;
  final String membershipStatus;
  final String attendanceStatus;
  final String workoutStatus;

  @override
  Widget build(BuildContext context) {
    final avatar = avatarUrl?.trim() ?? '';
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.surface, AppColors.surfaceStrong],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: AppColors.stroke),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.06),
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
              Container(
                width: 64,
                height: 64,
                padding: const EdgeInsets.all(3),
                decoration: BoxDecoration(
                  color: AppColors.primaryBright.withValues(alpha: 0.10),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: AppColors.primaryBright.withValues(alpha: 0.18),
                  ),
                ),
                child: CircleAvatar(
                  backgroundColor: AppColors.surface,
                  backgroundImage: avatar.isNotEmpty
                      ? NetworkImage(avatar)
                      : null,
                  child: avatar.isEmpty
                      ? const Icon(
                          Icons.person_rounded,
                          color: AppColors.primaryBright,
                        )
                      : null,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'MEMBER COACHING',
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.8,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      email,
                      maxLines: 1,
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
          const SizedBox(height: 16),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.textSecondary.withValues(alpha: 0.04),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.flag_outlined,
                  color: AppColors.primary,
                  size: 18,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    goal,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w700,
                      height: 1.35,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _FitHeroChip(
                label: membershipStatus,
                icon: Icons.verified_outlined,
                emphasized: true,
              ),
              _FitHeroChip(
                label: attendanceStatus,
                icon: Icons.schedule_rounded,
              ),
              _FitHeroChip(
                label: workoutStatus,
                icon: Icons.fitness_center_rounded,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _FitHeroChip extends StatelessWidget {
  const _FitHeroChip({
    required this.label,
    required this.icon,
    this.emphasized = false,
  });

  final String label;
  final IconData icon;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 170),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: (emphasized ? AppColors.primaryBright : AppColors.textSecondary)
            .withValues(alpha: emphasized ? 0.10 : 0.06),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color:
              (emphasized ? AppColors.primaryBright : AppColors.textSecondary)
                  .withValues(alpha: 0.14),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            size: 15,
            color: emphasized
                ? AppColors.primaryBright
                : AppColors.textSecondary,
          ),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: emphasized
                    ? AppColors.textPrimary
                    : AppColors.textSecondary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FitStatCell extends StatelessWidget {
  const _FitStatCell({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        children: [
          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
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

class _FitActionPanel extends StatelessWidget {
  const _FitActionPanel({
    required this.onAssignWorkout,
    required this.onAssignDiet,
    required this.onMessage,
    required this.onAddCoachingNote,
  });

  final VoidCallback onAssignWorkout;
  final VoidCallback onAssignDiet;
  final VoidCallback onMessage;
  final VoidCallback onAddCoachingNote;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Coach actions',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _CoachActionButton(
                  icon: Icons.fitness_center_rounded,
                  label: 'Workout',
                  onTap: onAssignWorkout,
                  primary: true,
                  compact: true,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _CoachActionButton(
                  icon: Icons.restaurant_menu_rounded,
                  label: 'Diet',
                  onTap: onAssignDiet,
                  compact: true,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: _CoachActionButton(
                  icon: Icons.chat_bubble_outline_rounded,
                  label: 'Message',
                  onTap: onMessage,
                  compact: true,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _CoachActionButton(
                  icon: Icons.edit_calendar_outlined,
                  label: 'Note / follow-up',
                  onTap: onAddCoachingNote,
                  compact: true,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CoachActionButton extends StatelessWidget {
  const _CoachActionButton({
    required this.icon,
    required this.label,
    required this.onTap,
    this.primary = false,
    this.compact = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool primary;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: compact ? 52 : 56,
      child: Material(
        color: primary ? AppColors.primary : AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: primary ? null : Border.all(color: AppColors.stroke),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  icon,
                  size: 19,
                  color: primary ? Colors.white : AppColors.primary,
                ),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: primary ? Colors.white : AppColors.textPrimary,
                      fontWeight: FontWeight.w800,
                    ),
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

class _FitSectionCard extends StatelessWidget {
  const _FitSectionCard({
    required this.title,
    required this.icon,
    required this.child,
  });

  final String title;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: AppColors.stroke),
        ),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
          childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
          leading: Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: AppColors.primary, size: 19),
          ),
          title: Text(
            title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          children: [Align(alignment: Alignment.centerLeft, child: child)],
        ),
      ),
    );
  }
}

class _OverviewTab extends StatelessWidget {
  const _OverviewTab({
    required this.memberProfile,
    required this.membershipSummary,
    required this.attendanceSummary,
    required this.attendance,
    required this.progressSummary,
    required this.planCount,
    required this.dietPlanCount,
  });

  final Map<String, dynamic> memberProfile;
  final Map<String, dynamic> membershipSummary;
  final Map<String, dynamic> attendanceSummary;
  final List<Map<String, dynamic>> attendance;
  final Map<String, dynamic> progressSummary;
  final int planCount;
  final int dietPlanCount;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Member profile',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              _InfoRow(
                label: 'Fitness goal',
                value:
                    progressSummary['fitness_goal']?.toString() ??
                    memberProfile['fitness_goal']?.toString() ??
                    '--',
              ),
              _InfoRow(
                label: 'Height',
                value: memberProfile['height_cm'] != null
                    ? '${memberProfile['height_cm']} cm'
                    : '--',
              ),
              _InfoRow(
                label: 'Weight',
                value: progressSummary['weight_kg'] != null
                    ? '${progressSummary['weight_kg']} kg'
                    : (memberProfile['weight_kg'] != null
                          ? '${memberProfile['weight_kg']} kg'
                          : '--'),
              ),
              _InfoRow(
                label: 'Experience',
                value:
                    progressSummary['experience_level']?.toString() ??
                    memberProfile['experience_level']?.toString() ??
                    '--',
              ),
              _InfoRow(
                label: 'Injuries',
                value:
                    memberProfile['injury_notes']
                            ?.toString()
                            .trim()
                            .isNotEmpty ==
                        true
                    ? memberProfile['injury_notes'].toString()
                    : 'None noted',
              ),
              _InfoRow(
                label: 'Medical notes',
                value:
                    memberProfile['medical_notes']
                            ?.toString()
                            .trim()
                            .isNotEmpty ==
                        true
                    ? memberProfile['medical_notes'].toString()
                    : 'No medical notes recorded',
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Membership summary',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              _InfoRow(
                label: 'Status',
                value: _titleCase(
                  membershipSummary['status']?.toString() ?? '--',
                ),
              ),
              _InfoRow(
                label: 'Payment status',
                value: _titleCase(
                  membershipSummary['payment_status']?.toString() ?? '--',
                ),
              ),
              _InfoRow(
                label: 'Expiry date',
                value: _prettyDate(membershipSummary['expiry_date']),
              ),
              _InfoRow(label: 'Workout plans', value: '$planCount'),
              _InfoRow(label: 'Diet plans', value: '$dietPlanCount'),
            ],
          ),
        ),
        const SizedBox(height: 14),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Attendance summary',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              _InfoRow(
                label: 'Last check-in',
                value: _prettyDateTime(attendanceSummary['last_check_in_at']),
              ),
              _InfoRow(
                label: 'Recorded check-ins',
                value:
                    '${(attendanceSummary['attendance_count'] as num?)?.toInt() ?? attendance.length}',
              ),
              const SizedBox(height: 12),
              if (attendance.isEmpty)
                const EmptyStateView(
                  title: 'No attendance history',
                  message:
                      'Attendance will appear here after member check-ins.',
                  icon: Icons.event_busy_rounded,
                )
              else
                ...attendance
                    .take(5)
                    .map(
                      (log) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _TimelineTile(
                          title: _prettyDateTime(
                            log['checked_in_at'] ?? log['date'],
                          ),
                          subtitle:
                              '${_titleCase(log['check_in_method']?.toString() ?? 'manual')} at ${_map(log['branch'])['name']?.toString() ?? 'assigned branch'}',
                          icon: Icons.event_available_rounded,
                          accent: AppColors.info,
                        ),
                      ),
                    ),
            ],
          ),
        ),
      ],
    );
  }
}

class _DietPlansTab extends StatelessWidget {
  const _DietPlansTab({required this.plans, required this.onAssign});

  final List<Map<String, dynamic>> plans;
  final VoidCallback onAssign;

  @override
  Widget build(BuildContext context) {
    if (plans.isEmpty) {
      return EmptyStateView(
        title: 'No diet plan assigned',
        message:
            'Create a tailored meal plan or start from a nutrition template.',
        icon: Icons.restaurant_menu_rounded,
        action: SizedBox(
          width: 220,
          child: GradientButton(
            label: 'Assign Diet Plan',
            icon: Icons.add_rounded,
            expanded: true,
            onPressed: onAssign,
          ),
        ),
      );
    }

    return Column(
      children: [
        ...plans.map((plan) {
          final meals = _mapList(plan['meals']);
          final status = plan['status']?.toString() ?? 'active';
          final isActive = status.toLowerCase() == 'active';
          final macros = <String>[
            if (plan['protein_target_g'] != null)
              'P ${_numberLabel(plan['protein_target_g'])}g',
            if (plan['carbs_target_g'] != null)
              'C ${_numberLabel(plan['carbs_target_g'])}g',
            if (plan['fats_target_g'] != null)
              'F ${_numberLabel(plan['fats_target_g'])}g',
          ].join(' • ');

          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: PremiumCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: AppColors.accentPurple.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(
                          Icons.restaurant_rounded,
                          color: AppColors.accentPurple,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              plan['name']?.toString() ?? 'Diet plan',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              [
                                if (plan['daily_calorie_target'] != null)
                                  '${plan['daily_calorie_target']} kcal',
                                '${meals.length} meals',
                              ].join(' • '),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color:
                              (isActive
                                      ? AppColors.success
                                      : AppColors.textMuted)
                                  .withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          _titleCase(status),
                          style: Theme.of(context).textTheme.labelSmall
                              ?.copyWith(
                                color: isActive
                                    ? AppColors.success
                                    : AppColors.textMuted,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                      ),
                    ],
                  ),
                  if (macros.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(macros, style: Theme.of(context).textTheme.bodyMedium),
                  ],
                  const SizedBox(height: 8),
                  Text(
                    _dietSchedule(plan),
                    style: Theme.of(
                      context,
                    ).textTheme.bodySmall?.copyWith(color: AppColors.textMuted),
                  ),
                  if (meals.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    const Divider(height: 1),
                    const SizedBox(height: 10),
                    ...meals.take(4).map((meal) {
                      final items = _mapList(meal['items']);
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Row(
                          children: [
                            const Icon(
                              Icons.schedule_rounded,
                              size: 16,
                              color: AppColors.primary,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                meal['name']?.toString() ??
                                    _titleCase(
                                      meal['meal_type']?.toString() ?? 'meal',
                                    ),
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                            ),
                            Text(
                              '${items.length} items',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      );
                    }),
                    if (meals.length > 4)
                      Text(
                        '+ ${meals.length - 4} more meals',
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(
                              color: AppColors.primary,
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                  ],
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () =>
                          showDietPlanSummarySheet(context, plan: plan),
                      icon: const Icon(Icons.visibility_outlined),
                      label: const Text('See full plan'),
                    ),
                  ),
                ],
              ),
            ),
          );
        }),
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: onAssign,
            icon: const Icon(Icons.add_rounded),
            label: const Text('Create or Assign Another Plan'),
          ),
        ),
      ],
    );
  }
}

class _ProgressTab extends StatelessWidget {
  const _ProgressTab({
    required this.progress,
    required this.photos,
    required this.weightLogs,
    required this.bodyMeasurements,
  });

  final Map<String, dynamic> progress;
  final List<Map<String, dynamic>> photos;
  final List<Map<String, dynamic>> weightLogs;
  final List<Map<String, dynamic>> bodyMeasurements;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Progress photos',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              if (photos.isEmpty)
                const EmptyStateView(
                  title: 'No progress photos yet',
                  message:
                      'Transformation photos will appear here once the member uploads them.',
                  icon: Icons.photo_library_outlined,
                )
              else
                SizedBox(
                  height: 154,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: photos.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 12),
                    itemBuilder: (_, index) {
                      final photo = photos[index];
                      return SizedBox(
                        width: 140,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            AppNetworkImage(
                              imageUrl: photo['photo_url']?.toString(),
                              height: 112,
                              width: 140,
                              borderRadius: 20,
                              fallbackIcon: Icons.photo_camera_back_outlined,
                            ),
                            const SizedBox(height: 8),
                            Text(_prettyDate(photo['captured_on'])),
                          ],
                        ),
                      );
                    },
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Weight logs',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              if (weightLogs.isEmpty)
                const EmptyStateView(
                  title: 'No weight logs',
                  message:
                      'Weight updates will appear here once the member tracks them.',
                  icon: Icons.monitor_weight_outlined,
                )
              else
                ...weightLogs
                    .take(6)
                    .map(
                      (log) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _TimelineTile(
                          title:
                              '${log['weight_kg'] ?? log['weight'] ?? '--'} kg',
                          subtitle: _prettyDate(log['log_date']),
                          icon: Icons.monitor_weight_outlined,
                          accent: AppColors.accentNeon,
                        ),
                      ),
                    ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Body measurements',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              if (bodyMeasurements.isEmpty)
                const EmptyStateView(
                  title: 'No measurements yet',
                  message:
                      'Body measurements will show here once the member logs them.',
                  icon: Icons.straighten_rounded,
                )
              else
                ...bodyMeasurements
                    .take(6)
                    .map(
                      (measurement) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _TimelineTile(
                          title: _measurementSummary(measurement),
                          subtitle: _prettyDate(
                            measurement['measured_on'] ?? measurement['date'],
                          ),
                          icon: Icons.straighten_rounded,
                          accent: AppColors.accentPurple,
                        ),
                      ),
                    ),
            ],
          ),
        ),
      ],
    );
  }
}

class _LogbookTab extends StatelessWidget {
  const _LogbookTab({required this.history, required this.personalRecords});

  final List<Map<String, dynamic>> history;
  final List<Map<String, dynamic>> personalRecords;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Workout history',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              if (history.isEmpty)
                const EmptyStateView(
                  title: 'No workout logs yet',
                  message:
                      'Completed workout sessions will appear here with exercises and volume.',
                  icon: Icons.history_rounded,
                )
              else
                ...history.take(8).map((session) {
                  final exercises = _mapList(session['exercises']);
                  final dayLabel = _workoutSessionDayLabel(session);
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _TimelineTile(
                      title:
                          session['name']?.toString().trim().isNotEmpty == true
                          ? session['name'].toString()
                          : 'Workout on ${_prettyDate(session['session_date'])}',
                      subtitle: [
                        if (dayLabel != null) dayLabel,
                        _titleCase(
                          session['status']?.toString() ?? 'completed',
                        ),
                        '${exercises.length} exercises',
                        'volume ${session['total_volume'] ?? 0}',
                      ].join(' • '),
                      icon: Icons.fitness_center_rounded,
                      accent: AppColors.primary,
                    ),
                  );
                }),
            ],
          ),
        ),
        const SizedBox(height: 14),
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Personal records',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              if (personalRecords.isEmpty)
                const EmptyStateView(
                  title: 'No PRs yet',
                  message:
                      'Member best weights, reps, and volume records unlock after workouts.',
                  icon: Icons.emoji_events_outlined,
                )
              else
                ...personalRecords.take(8).map((record) {
                  final exercise = _map(record['exercise']);
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _TimelineTile(
                      title: exercise['name']?.toString() ?? 'Exercise PR',
                      subtitle:
                          'Weight ${record['best_weight'] ?? 0} • reps ${record['best_reps'] ?? 0} • volume ${record['best_volume'] ?? 0}',
                      icon: Icons.emoji_events_rounded,
                      accent: AppColors.accentPurple,
                    ),
                  );
                }),
            ],
          ),
        ),
      ],
    );
  }
}

class _NotesTab extends StatelessWidget {
  const _NotesTab({required this.notes});

  final List<Map<String, dynamic>> notes;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Notes timeline',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              if (notes.isEmpty)
                const EmptyStateView(
                  title: 'No notes yet',
                  message:
                      'Coaching notes and follow-ups will appear here once added.',
                  icon: Icons.edit_note_rounded,
                )
              else
                ...notes.map(
                  (note) => Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: _TimelineTile(
                      title: note['note']?.toString() ?? 'Trainer note',
                      subtitle:
                          'Follow-up ${_prettyDate(note['follow_up_date'])}',
                      icon: Icons.edit_note_rounded,
                      accent: AppColors.warning,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _TimelineTile extends StatelessWidget {
  const _TimelineTile({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.accent,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: accent.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Icon(icon, color: accent),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 4),
              Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 128,
            child: Text(
              label,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.textMuted),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(value, style: Theme.of(context).textTheme.bodyMedium),
          ),
        ],
      ),
    );
  }
}

Map<String, dynamic> _map(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, item) => MapEntry(key.toString(), item));
  }
  return <String, dynamic>{};
}

List<Map<String, dynamic>> _mapList(dynamic value) {
  if (value is List) {
    return value.map((item) => _map(item)).toList();
  }
  return const <Map<String, dynamic>>[];
}

String? _workoutSessionDayLabel(Map<String, dynamic> session) {
  final label = session['plan_day_label']?.toString().trim() ?? '';
  final number = (session['plan_day_number'] as num?)?.toInt();
  if (label.isNotEmpty && number != null) return 'Day $number — $label';
  if (label.isNotEmpty) return label;
  if (number != null) return 'Day $number';
  return null;
}

String _titleCase(String value) {
  if (value.trim().isEmpty) {
    return '--';
  }
  return value
      .replaceAll('_', ' ')
      .split(' ')
      .map(
        (part) => part.isEmpty
            ? part
            : '${part[0].toUpperCase()}${part.substring(1)}',
      )
      .join(' ');
}

String _prettyDate(dynamic value) {
  final raw = value?.toString();
  if (raw == null || raw.isEmpty) {
    return '--';
  }
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) {
    return raw;
  }
  return DateFormat('dd MMM yyyy').format(parsed.toLocal());
}

String _prettyDateTime(dynamic value) {
  final raw = value?.toString();
  if (raw == null || raw.isEmpty) {
    return '--';
  }
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) {
    return raw;
  }
  return DateFormat('dd MMM yyyy, hh:mm a').format(parsed.toLocal());
}

String _numberLabel(dynamic value) {
  final number = value is num ? value : num.tryParse(value?.toString() ?? '');
  if (number == null) {
    return '--';
  }
  return number == number.roundToDouble()
      ? number.toInt().toString()
      : number.toStringAsFixed(1);
}

String _dietSchedule(Map<String, dynamic> plan) {
  final startsOn = _prettyDate(plan['starts_on']);
  final endsOn = _prettyDate(plan['ends_on']);
  if (startsOn == '--' && endsOn == '--') {
    return 'No fixed schedule';
  }
  if (startsOn == '--') {
    return 'Until $endsOn';
  }
  if (endsOn == '--') {
    return 'Starts $startsOn';
  }
  return '$startsOn – $endsOn';
}

String _attendanceLabel(Map<String, dynamic> attendanceSummary) {
  final lastCheckIn = attendanceSummary['last_check_in_at']?.toString();
  if (lastCheckIn == null || lastCheckIn.isEmpty) {
    return 'No recent check-in';
  }

  final parsed = DateTime.tryParse(lastCheckIn)?.toLocal();
  if (parsed == null) {
    return 'Attendance recorded';
  }

  final now = DateTime.now();
  final difference = now.difference(parsed).inDays;
  if (difference <= 0) {
    return 'Checked in today';
  }
  if (difference == 1) {
    return 'Checked in yesterday';
  }
  if (difference <= 6) {
    return 'Checked in ${difference}d ago';
  }
  return 'Inactive ${difference}d';
}

String _workoutCompletionLabel(List<Map<String, dynamic>> plans) {
  if (plans.isEmpty) {
    return 'Needs workout';
  }
  final activeCount = plans.where((plan) {
    final status = plan['status']?.toString().toLowerCase();
    return status == null || status == 'active' || status == 'assigned';
  }).length;
  return activeCount > 0 ? '$activeCount active' : '${plans.length} assigned';
}

String _measurementSummary(Map<String, dynamic> measurement) {
  final pairs = <String>[
    if (measurement['chest_cm'] != null) 'Chest ${measurement['chest_cm']}',
    if (measurement['waist_cm'] != null) 'Waist ${measurement['waist_cm']}',
    if (measurement['hips_cm'] != null) 'Hips ${measurement['hips_cm']}',
    if (measurement['arm_cm'] != null) 'Arm ${measurement['arm_cm']}',
    if (measurement['thigh_cm'] != null) 'Thigh ${measurement['thigh_cm']}',
  ];
  if (pairs.isEmpty) {
    return 'Body measurement updated';
  }
  return pairs.take(2).join(' • ');
}

ApiPagination _namedPagination(Map<String, dynamic> response, String key) {
  final meta = _map(response['meta']);
  return ApiPagination.fromResponse({
    'meta': {'pagination': _map(meta[key])},
  });
}
