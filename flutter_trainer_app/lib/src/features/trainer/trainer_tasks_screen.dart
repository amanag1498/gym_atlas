import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/confirmation_dialog.dart';
import '../../../core/widgets/premium_card.dart';
import 'trainer_repository.dart';

class TrainerTasksScreen extends StatefulWidget {
  const TrainerTasksScreen({
    super.key,
    required this.repository,
    required this.members,
    this.onChanged,
  });

  final TrainerRepository repository;
  final List<Map<String, dynamic>> members;
  final Future<void> Function()? onChanged;

  @override
  State<TrainerTasksScreen> createState() => _TrainerTasksScreenState();
}

class _TrainerTasksScreenState extends State<TrainerTasksScreen> {
  bool _loading = true;
  bool _saving = false;
  bool _completionUnavailable = false;
  bool _followUpEndpointUnavailable = false;
  String? _error;
  Map<String, dynamic> _taskSummary = const {};
  List<Map<String, dynamic>> _followUps = const [];

  int? _selectedMemberId;
  late final TextEditingController _noteController;
  late final TextEditingController _followUpDateController;

  @override
  void initState() {
    super.initState();
    _selectedMemberId = (widget.members.firstOrNull?['member_id'] as num?)
        ?.toInt();
    _noteController = TextEditingController();
    _followUpDateController = TextEditingController(
      text: DateFormat(
        'yyyy-MM-dd',
      ).format(DateTime.now().add(const Duration(days: 1))),
    );
    _load();
  }

  @override
  void dispose() {
    _noteController.dispose();
    _followUpDateController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      Map<String, dynamic> taskSummary = const {};
      List<Map<String, dynamic>> followUps = const [];
      bool followUpEndpointUnavailable = false;

      try {
        final summaryResponse = await widget.repository.fetchTasks();
        taskSummary = _map(summaryResponse['data']);
      } catch (exception) {
        taskSummary = const {};
        if (_looksLikePermissionError(exception)) {
          rethrow;
        }
      }

      try {
        final followUpResponse = await widget.repository
            .fetchPendingFollowUps();
        followUps = _mapList(followUpResponse['data']);
      } catch (exception) {
        if (_looksLikeMissingEndpoint(exception)) {
          followUpEndpointUnavailable = true;
          followUps = const [];
        } else {
          rethrow;
        }
      }

      if (!mounted) {
        return;
      }
      setState(() {
        _taskSummary = taskSummary;
        _followUps = followUps;
        _followUpEndpointUnavailable = followUpEndpointUnavailable;
        _loading = false;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = exception.toString();
        _loading = false;
      });
    }
  }

  Future<void> _submitNote() async {
    final memberId = _selectedMemberId;
    final note = _noteController.text.trim();
    final followUpDate = _followUpDateController.text.trim();
    if (memberId == null || note.isEmpty) {
      return;
    }

    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.repository.createNote(memberId, {
        'note': note,
        'visibility': 'private_to_trainer',
        if (followUpDate.isNotEmpty) 'follow_up_date': followUpDate,
      });
      if (!mounted) {
        return;
      }
      _noteController.clear();
      _followUpDateController.text = DateFormat(
        'yyyy-MM-dd',
      ).format(DateTime.now().add(const Duration(days: 1)));
      await _showSuccessOverlay(
        title: 'Follow-up saved',
        message: 'The trainer note is now in your coaching timeline.',
        icon: Icons.check_circle_rounded,
      );
      messenger.showSnackBar(
        const SnackBar(content: Text('Trainer note added successfully.')),
      );
      await _load();
      await widget.onChanged?.call();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _completeTask(Map<String, dynamic> note) async {
    final noteId = (note['id'] as num?)?.toInt();
    if (noteId == null) {
      return;
    }

    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (_) => const ConfirmationDialog(
            title: 'Mark follow-up complete',
            message:
                'This will remove the item from your pending follow-ups list.',
            confirmLabel: 'Complete',
          ),
        ) ??
        false;
    if (!confirmed) {
      return;
    }

    if (!mounted) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.repository.completeNote(noteId);
      if (!mounted) {
        return;
      }
      await _showSuccessOverlay(
        title: 'Task completed',
        message: 'Your follow-up queue has been updated.',
        icon: Icons.task_alt_rounded,
      );
      messenger.showSnackBar(
        const SnackBar(content: Text('Follow-up marked complete.')),
      );
      await _load();
      await widget.onChanged?.call();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      if (_looksLikeMissingCompletion(exception)) {
        setState(() => _completionUnavailable = true);
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _showSuccessOverlay({
    required String title,
    required String message,
    required IconData icon,
  }) async {
    await showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'success',
      barrierColor: AppColors.textPrimary.withValues(alpha: 0.34),
      transitionDuration: const Duration(milliseconds: 220),
      pageBuilder: (_, __, ___) {
        return SafeArea(
          child: Center(
            child: Material(
              color: Colors.transparent,
              child: PremiumCard(
                padding: const EdgeInsets.all(28),
                glowColor: AppColors.success,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TweenAnimationBuilder<double>(
                      tween: Tween<double>(begin: 0.8, end: 1),
                      duration: const Duration(milliseconds: 260),
                      curve: Curves.easeOutBack,
                      builder: (context, value, child) =>
                          Transform.scale(scale: value, child: child),
                      child: Container(
                        width: 74,
                        height: 74,
                        decoration: BoxDecoration(
                          color: AppColors.success.withValues(alpha: 0.14),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(icon, color: AppColors.success, size: 36),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Text(
                      title,
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: 8),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 280),
                      child: Text(
                        message,
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
      transitionBuilder: (_, animation, __, child) => FadeTransition(
        opacity: CurvedAnimation(parent: animation, curve: Curves.easeOut),
        child: child,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final todayCount = _todayFollowUps(_followUps).length;
    final overdueCount = _overdueFollowUps(_followUps).length;
    final pendingCount = _followUps.length;
    final summaryPendingCount =
        (_taskSummary['pending_follow_ups_count'] as num?)?.toInt() ??
        pendingCount;

    return Scaffold(
      backgroundColor: _FollowFitColor.white,
      appBar: AppBar(
        backgroundColor: _FollowFitColor.white,
        elevation: 0,
        centerTitle: true,
        leadingWidth: 72,
        leading: Padding(
          padding: const EdgeInsets.only(left: 25),
          child: _FollowIconButton(
            icon: Icons.arrow_back_ios_new_rounded,
            onTap: () => Navigator.of(context).pop(),
          ),
        ),
        title: Text(
          'Follow Ups',
          style: TextStyle(
            color: _FollowFitColor.black,
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
        actions: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: 25),
            child: _FollowIconButton(
              icon: Icons.refresh_rounded,
              onTap: _loading ? null : _load,
            ),
          ),
        ],
      ),
      body: _loading
          ? const LoadingStateView(label: 'Loading your coaching tasks...')
          : _error != null
          ? _buildError()
          : RefreshIndicator(
              color: _FollowFitColor.primaryEnd,
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                padding: const EdgeInsets.fromLTRB(25, 15, 25, 32),
                children: <Widget>[
                  _FollowHeroCard(
                    todayCount: todayCount,
                    overdueCount: overdueCount,
                    pendingCount: summaryPendingCount,
                  ),
                  const SizedBox(height: 22),
                  _FollowComposerCard(
                    members: widget.members,
                    selectedMemberId: _selectedMemberId,
                    noteController: _noteController,
                    followUpDateController: _followUpDateController,
                    saving: _saving,
                    onMemberChanged: (value) =>
                        setState(() => _selectedMemberId = value),
                    onSave: _saving ? null : _submitNote,
                  ),
                  const SizedBox(height: 22),
                  _FollowTimelineCard(
                    followUps: _followUps,
                    endpointUnavailable: _followUpEndpointUnavailable,
                    completionUnavailable: _completionUnavailable,
                    onComplete: _completeTask,
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildError() {
    final denied = _looksLikePermissionError(_error);
    if (denied) {
      return EmptyStateView(
        title: 'Tasks unavailable',
        message:
            'You do not have permission to view trainer tasks for this account.',
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

    if (_looksLikeMissingEndpoint(_error)) {
      return EmptyStateView(
        title: 'Task endpoints not ready',
        message:
            'This server does not expose the trainer task endpoints yet. You can still add notes from member actions once supported.',
        icon: Icons.construction_rounded,
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

List<Map<String, dynamic>> _todayFollowUps(
  List<Map<String, dynamic>> followUps,
) {
  final today = DateUtils.dateOnly(DateTime.now());
  return followUps.where((note) {
    final date = _parseDate(note['follow_up_date']);
    return date != null && DateUtils.isSameDay(date, today);
  }).toList();
}

List<Map<String, dynamic>> _overdueFollowUps(
  List<Map<String, dynamic>> followUps,
) {
  final today = DateUtils.dateOnly(DateTime.now());
  return followUps.where((note) {
    final date = _parseDate(note['follow_up_date']);
    return date != null && date.isBefore(today);
  }).toList();
}

String _followUpStatus(Map<String, dynamic> note) {
  final date = _parseDate(note['follow_up_date']);
  if (date == null) {
    return 'pending';
  }
  final today = DateUtils.dateOnly(DateTime.now());
  if (date.isBefore(today)) {
    return 'overdue';
  }
  if (DateUtils.isSameDay(date, today)) {
    return 'today';
  }
  return 'upcoming';
}

DateTime? _parseDate(dynamic value) {
  final raw = value?.toString();
  if (raw == null || raw.trim().isEmpty) {
    return null;
  }
  return DateTime.tryParse(raw.trim());
}

String _prettyDate(dynamic value) {
  final date = _parseDate(value);
  if (date == null) {
    return '--';
  }
  return DateFormat('dd MMM yyyy').format(date);
}

String _titleCase(String value) {
  if (value.trim().isEmpty) {
    return '--';
  }
  return value
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map(
        (part) => '${part[0].toUpperCase()}${part.substring(1).toLowerCase()}',
      )
      .join(' ');
}

bool _looksLikePermissionError(Object? error) {
  final message = error?.toString().toLowerCase() ?? '';
  return message.contains('permission') || message.contains('403');
}

bool _looksLikeMissingEndpoint(Object? error) {
  final message = error?.toString().toLowerCase() ?? '';
  return message.contains('404') ||
      message.contains('not found') ||
      message.contains('endpoint');
}

bool _looksLikeMissingCompletion(Object? error) {
  final message = error?.toString().toLowerCase() ?? '';
  return message.contains('not available') || message.contains('404');
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
    return value
        .whereType<Map>()
        .map((item) => item.map((key, item) => MapEntry(key.toString(), item)))
        .toList();
  }
  return const <Map<String, dynamic>>[];
}

class _FollowHeroCard extends StatelessWidget {
  const _FollowHeroCard({
    required this.todayCount,
    required this.overdueCount,
    required this.pendingCount,
  });

  final int todayCount;
  final int overdueCount;
  final int pendingCount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: _FollowFitColor.primaryGradient,
        borderRadius: BorderRadius.circular(24),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: _FollowFitColor.primaryEnd.withValues(alpha: 0.28),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: _FollowFitColor.white.withValues(alpha: 0.22),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(Icons.task_alt_rounded, color: Colors.white),
              ),
              const SizedBox(width: 14),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Follow-up Queue',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Track clients that need attention today.',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            children: <Widget>[
              Expanded(
                child: _FollowHeroMetric(
                  value: '$todayCount',
                  label: 'Today',
                  icon: Icons.today_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _FollowHeroMetric(
                  value: '$overdueCount',
                  label: 'Overdue',
                  icon: Icons.warning_amber_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _FollowHeroMetric(
                  value: '$pendingCount',
                  label: 'Pending',
                  icon: Icons.calendar_month_rounded,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _FollowHeroMetric extends StatelessWidget {
  const _FollowHeroMetric({
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
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
      decoration: BoxDecoration(
        color: _FollowFitColor.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _FollowFitColor.white.withValues(alpha: 0.2)),
      ),
      child: Column(
        children: <Widget>[
          Icon(icon, color: Colors.white, size: 18),
          const SizedBox(height: 7),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _FollowComposerCard extends StatelessWidget {
  const _FollowComposerCard({
    required this.members,
    required this.selectedMemberId,
    required this.noteController,
    required this.followUpDateController,
    required this.saving,
    required this.onMemberChanged,
    required this.onSave,
  });

  final List<Map<String, dynamic>> members;
  final int? selectedMemberId;
  final TextEditingController noteController;
  final TextEditingController followUpDateController;
  final bool saving;
  final ValueChanged<int?> onMemberChanged;
  final VoidCallback? onSave;

  @override
  Widget build(BuildContext context) {
    return _FollowSectionCard(
      title: 'Add Trainer Note',
      subtitle:
          'Write the coaching context and pin the next check-in date while it is fresh.',
      icon: Icons.edit_note_rounded,
      child: members.isEmpty
          ? const EmptyStateView(
              title: 'No assigned members',
              message:
                  'Once your gym assigns clients to you, you can create coaching notes and schedule follow-ups here.',
              icon: Icons.groups_outlined,
            )
          : Column(
              children: <Widget>[
                DropdownButtonFormField<int>(
                  initialValue: selectedMemberId,
                  isExpanded: true,
                  items: members
                      .map((assignment) {
                        final memberId = (assignment['member_id'] as num?)
                            ?.toInt();
                        if (memberId == null) {
                          return null;
                        }
                        final member = _map(assignment['member']);
                        return DropdownMenuItem<int>(
                          value: memberId,
                          child: Text(
                            member['name']?.toString() ?? 'Member',
                            overflow: TextOverflow.ellipsis,
                          ),
                        );
                      })
                      .whereType<DropdownMenuItem<int>>()
                      .toList(),
                  onChanged: onMemberChanged,
                  decoration: _followInputDecoration(
                    'Assigned member',
                    icon: Icons.person_search_rounded,
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: noteController,
                  minLines: 4,
                  maxLines: 6,
                  decoration: _followInputDecoration(
                    'Coaching note',
                    hint: 'What should you remember before the next check-in?',
                    icon: Icons.notes_rounded,
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: followUpDateController,
                  decoration: _followInputDecoration(
                    'Follow-up date',
                    hint: 'YYYY-MM-DD',
                    icon: Icons.event_rounded,
                  ),
                ),
                const SizedBox(height: 18),
                GradientButton(
                  label: saving ? 'Saving...' : 'Save note',
                  icon: Icons.check_circle_rounded,
                  loading: saving,
                  expanded: true,
                  onPressed: onSave,
                ),
              ],
            ),
    );
  }
}

class _FollowTimelineCard extends StatelessWidget {
  const _FollowTimelineCard({
    required this.followUps,
    required this.endpointUnavailable,
    required this.completionUnavailable,
    required this.onComplete,
  });

  final List<Map<String, dynamic>> followUps;
  final bool endpointUnavailable;
  final bool completionUnavailable;
  final ValueChanged<Map<String, dynamic>> onComplete;

  @override
  Widget build(BuildContext context) {
    Widget child;
    if (endpointUnavailable) {
      child = const EmptyStateView(
        title: 'Follow-up timeline unavailable',
        message:
            'The follow-up queue could not be loaded. You can still create trainer notes above.',
        icon: Icons.timeline_rounded,
      );
    } else if (followUps.isEmpty) {
      child = const EmptyStateView(
        title: 'No pending follow-ups',
        message:
            'Your coaching queue is clear. New notes with follow-up dates will appear here automatically.',
        icon: Icons.task_alt_rounded,
      );
    } else {
      child = Column(
        children: followUps.asMap().entries.map((entry) {
          return Padding(
            padding: EdgeInsets.only(
              bottom: entry.key == followUps.length - 1 ? 0 : 14,
            ),
            child: _FollowTimelineRow(
              note: entry.value,
              completionUnavailable: completionUnavailable,
              onComplete: () => onComplete(entry.value),
            ),
          );
        }).toList(),
      );
    }

    return _FollowSectionCard(
      title: 'Timeline',
      subtitle:
          'Overdue and due-today items stay visible until you close them.',
      icon: Icons.timeline_rounded,
      child: child,
    );
  }
}

class _FollowTimelineRow extends StatelessWidget {
  const _FollowTimelineRow({
    required this.note,
    required this.completionUnavailable,
    required this.onComplete,
  });

  final Map<String, dynamic> note;
  final bool completionUnavailable;
  final VoidCallback onComplete;

  @override
  Widget build(BuildContext context) {
    final member = _map(note['member']);
    final status = _followUpStatus(note);
    final dueText = _prettyDate(note['follow_up_date']);
    final noteText = note['note']?.toString() ?? 'Trainer follow-up';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _FollowFitColor.field,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              gradient: _FollowFitColor.softGradient,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(
              status == 'overdue'
                  ? Icons.priority_high_rounded
                  : Icons.event_repeat_rounded,
              color: _FollowFitColor.primaryEnd,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        member['name']?.toString() ?? 'Assigned member',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: _FollowFitColor.black,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    _FollowStatusPill(label: _titleCase(status)),
                  ],
                ),
                const SizedBox(height: 7),
                Text(
                  noteText,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: _FollowFitColor.gray,
                    fontSize: 12,
                    height: 1.35,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 11),
                Row(
                  children: <Widget>[
                    Icon(
                      Icons.calendar_today_rounded,
                      color: _FollowFitColor.gray,
                      size: 14,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        dueText == '--' ? 'No date set' : 'Follow-up $dueText',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: _FollowFitColor.gray,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    if (!completionUnavailable)
                      TextButton(
                        onPressed: onComplete,
                        style: TextButton.styleFrom(
                          foregroundColor: _FollowFitColor.primaryEnd,
                          padding: const EdgeInsets.symmetric(horizontal: 8),
                          minimumSize: const Size(0, 32),
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        child: const Text(
                          'Complete',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FollowSectionCard extends StatelessWidget {
  const _FollowSectionCard({
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
        color: _FollowFitColor.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _FollowFitColor.border),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: _FollowFitColor.black.withValues(alpha: 0.05),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  gradient: _FollowFitColor.softGradient,
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(icon, color: _FollowFitColor.primaryEnd),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: TextStyle(
                        color: _FollowFitColor.black,
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: TextStyle(
                        color: _FollowFitColor.gray,
                        fontSize: 11,
                        height: 1.35,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          child,
        ],
      ),
    );
  }
}

class _FollowStatusPill extends StatelessWidget {
  const _FollowStatusPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: _FollowFitColor.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: _FollowFitColor.border),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: _FollowFitColor.primaryEnd,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _FollowIconButton extends StatelessWidget {
  const _FollowIconButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: _FollowFitColor.field,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, size: 18, color: _FollowFitColor.black),
        ),
      ),
    );
  }
}

InputDecoration _followInputDecoration(
  String label, {
  String? hint,
  IconData? icon,
}) {
  return InputDecoration(
    labelText: label,
    hintText: hint,
    prefixIcon: icon == null ? null : Icon(icon, size: 20),
    filled: true,
    fillColor: _FollowFitColor.field,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(color: _FollowFitColor.border),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(color: _FollowFitColor.border),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(color: _FollowFitColor.primaryEnd, width: 1.5),
    ),
  );
}

class _FollowFitColor {
  static const Color white = Colors.white;
  static const Color black = Color(0xFF1D1617);
  static const Color gray = Color(0xFF7B6F72);
  static const Color field = Color(0xFFF7F8F8);
  static const Color border = Color(0xFFEDEDED);
  static const Color primaryStart = Color(0xFF9DCEFF);
  static const Color primaryEnd = Color(0xFF92A3FD);

  static const LinearGradient primaryGradient = LinearGradient(
    colors: <Color>[primaryStart, primaryEnd],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient softGradient = LinearGradient(
    colors: <Color>[Color(0x1A9DCEFF), Color(0x1A92A3FD)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
}
