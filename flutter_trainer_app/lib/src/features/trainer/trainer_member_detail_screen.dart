import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import 'trainer_repository.dart';

class TrainerMemberDetailScreen extends StatefulWidget {
  const TrainerMemberDetailScreen({
    super.key,
    required this.assignment,
    required this.repository,
    required this.onAssignWorkout,
    required this.onAddNote,
    required this.onFollowUp,
  });

  final Map<String, dynamic> assignment;
  final TrainerRepository repository;
  final VoidCallback onAssignWorkout;
  final VoidCallback onAddNote;
  final VoidCallback onFollowUp;

  @override
  State<TrainerMemberDetailScreen> createState() =>
      _TrainerMemberDetailScreenState();
}

class _TrainerMemberDetailScreenState extends State<TrainerMemberDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _detail = const {};
  Map<String, dynamic> _progress = const {};
  List<Map<String, dynamic>> _attendance = const [];
  List<Map<String, dynamic>> _plans = const [];
  List<Map<String, dynamic>> _notes = const [];
  List<Map<String, dynamic>> _workoutHistory = const [];
  List<Map<String, dynamic>> _personalRecords = const [];

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

      final detail = _map(responses[0]['data']);
      final notes = _mapList(responses[4]['data']);
      final logbook = _map(responses[5]['data']);

      setState(() {
        _detail = detail;
        _progress = _map(responses[2]['data']);
        _attendance = _mapList(responses[1]['data']);
        _plans = _mapList(responses[3]['data']);
        _notes = notes;
        _workoutHistory = _mapList(logbook['history']);
        _personalRecords = _mapList(logbook['personal_records']);
        _loading = false;
      });
    } catch (exception) {
      setState(() {
        _loading = false;
        _error = exception.toString();
      });
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

    return Scaffold(
      backgroundColor: const Color(0xFFFFFFFF),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        leading: Padding(
          padding: const EdgeInsets.all(8),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: const Color(0xFFF7F8F8),
              borderRadius: BorderRadius.circular(12),
            ),
            child: IconButton(
              icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
              color: const Color(0xFF1D1617),
              onPressed: () => Navigator.of(context).maybePop(),
            ),
          ),
        ),
        title: const Text(
          'Member Detail',
          style: TextStyle(
            color: Color(0xFF1D1617),
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      body: _loading
          ? const LoadingStateView(label: 'Loading member detail...')
          : _error != null
          ? _buildError()
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(25, 15, 25, 30),
                children: [
                  _FitMemberHero(
                    name: displayName,
                    email: member['email']?.toString() ?? 'Assigned member',
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
                  const SizedBox(height: 15),
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
                      const SizedBox(width: 15),
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
                      const SizedBox(width: 15),
                      Expanded(
                        child: _FitStatCell(
                          title: '${_attendance.length}',
                          subtitle: 'Visits',
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 25),
                  _FitActionPanel(
                    onAssignWorkout: widget.onAssignWorkout,
                    onAddNote: widget.onAddNote,
                    onFollowUp: widget.onFollowUp,
                  ),
                  const SizedBox(height: 25),
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
                    ),
                  ),
                  const SizedBox(height: 25),
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
                  const SizedBox(height: 25),
                  _FitSectionCard(
                    title: 'Logbook',
                    icon: Icons.fitness_center_rounded,
                    child: _LogbookTab(
                      history: _workoutHistory,
                      personalRecords: _personalRecords,
                    ),
                  ),
                  const SizedBox(height: 25),
                  _FitSectionCard(
                    title: 'Notes',
                    icon: Icons.edit_note_rounded,
                    child: _NotesTab(notes: _notes),
                  ),
                ],
              ),
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
          colors: [Color(0xFF92A3FD), Color(0xFF9DCEFF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF92A3FD).withValues(alpha: 0.22),
            blurRadius: 22,
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
                width: 64,
                height: 64,
                padding: const EdgeInsets.all(3),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.22),
                  shape: BoxShape.circle,
                ),
                child: CircleAvatar(
                  backgroundColor: Colors.white,
                  backgroundImage: avatar.isNotEmpty
                      ? NetworkImage(avatar)
                      : null,
                  child: avatar.isEmpty
                      ? const Icon(
                          Icons.person_rounded,
                          color: Color(0xFF92A3FD),
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
                      name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.78),
                        fontSize: 12,
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
              color: Colors.white.withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withValues(alpha: 0.20)),
            ),
            child: Text(
              goal,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 13,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _FitHeroChip(label: membershipStatus),
              _FitHeroChip(label: attendanceStatus),
              _FitHeroChip(label: workoutStatus),
            ],
          ),
        ],
      ),
    );
  }
}

class _FitHeroChip extends StatelessWidget {
  const _FitHeroChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 170),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.22),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
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
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 2)],
      ),
      child: Column(
        children: [
          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Color(0xFF92A3FD),
              fontSize: 14,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Color(0xFF786F72), fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _FitActionPanel extends StatelessWidget {
  const _FitActionPanel({
    required this.onAssignWorkout,
    required this.onAddNote,
    required this.onFollowUp,
  });

  final VoidCallback onAssignWorkout;
  final VoidCallback onAddNote;
  final VoidCallback onFollowUp;

  @override
  Widget build(BuildContext context) {
    return _FitSectionShell(
      title: 'Coach Actions',
      child: Column(
        children: [
          _FitActionRow(
            icon: Icons.playlist_add_check_circle_outlined,
            title: 'Assign workout',
            subtitle: 'Create or assign the next training block.',
            onTap: onAssignWorkout,
          ),
          _FitActionRow(
            icon: Icons.edit_note_rounded,
            title: 'Add trainer note',
            subtitle: 'Record context, blockers, or form notes.',
            onTap: onAddNote,
          ),
          _FitActionRow(
            icon: Icons.alarm_add_rounded,
            title: 'Schedule follow-up',
            subtitle: 'Create a private reminder for this member.',
            onTap: onFollowUp,
          ),
        ],
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
    return _FitSectionShell(title: title, icon: icon, child: child);
  }
}

class _FitSectionShell extends StatelessWidget {
  const _FitSectionShell({required this.title, required this.child, this.icon});

  final String title;
  final Widget child;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 2)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (icon != null) ...[
                Icon(icon, color: const Color(0xFF92A3FD), size: 20),
                const SizedBox(width: 10),
              ],
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: Color(0xFF1D1617),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
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

class _FitActionRow extends StatelessWidget {
  const _FitActionRow({
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
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: const Color(0xFFF7F8F8),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: const Color(0xFF92A3FD), size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: Color(0xFF1D1617),
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF786F72),
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.arrow_forward_ios_rounded,
              color: Color(0xFFADA4A5),
              size: 14,
            ),
          ],
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
  });

  final Map<String, dynamic> memberProfile;
  final Map<String, dynamic> membershipSummary;
  final Map<String, dynamic> attendanceSummary;
  final List<Map<String, dynamic>> attendance;
  final Map<String, dynamic> progressSummary;
  final int planCount;

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
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _TimelineTile(
                      title:
                          session['name']?.toString().trim().isNotEmpty == true
                          ? session['name'].toString()
                          : 'Workout on ${_prettyDate(session['session_date'])}',
                      subtitle:
                          '${_titleCase(session['status']?.toString() ?? 'completed')} • ${exercises.length} exercises • volume ${session['total_volume'] ?? 0}',
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
