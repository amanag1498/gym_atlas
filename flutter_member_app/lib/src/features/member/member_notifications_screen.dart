import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/loading_state.dart';
import 'member_repository.dart';

class MemberNotificationsScreen extends StatefulWidget {
  const MemberNotificationsScreen({
    super.key,
    required this.repository,
    required this.initialNotifications,
    required this.onChanged,
  });

  final MemberRepository repository;
  final List<Map<String, dynamic>> initialNotifications;
  final Future<void> Function() onChanged;

  @override
  State<MemberNotificationsScreen> createState() =>
      _MemberNotificationsScreenState();
}

class _MemberNotificationsScreenState extends State<MemberNotificationsScreen> {
  bool _loading = true;
  bool _markingAllRead = false;
  final Set<int> _respondingInvitationIds = <int>{};
  String? _error;
  List<Map<String, dynamic>> _notifications = const [];
  List<Map<String, dynamic>> _gymInvitations = const [];

  @override
  void initState() {
    super.initState();
    _notifications = widget.initialNotifications
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final results = await Future.wait<Map<String, dynamic>>([
        widget.repository.fetchNotifications(),
        widget.repository.fetchGymInvitations(status: 'pending'),
      ]);
      _notifications = (results[0]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _gymInvitations = (results[1]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      await widget.onChanged();
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _markRead(Map<String, dynamic> notification) async {
    final id = (notification['id'] as num?)?.toInt();
    if (id == null || notification['read_at'] != null) {
      return;
    }

    try {
      await widget.repository.markNotificationRead(id);
      setState(() {
        notification['read_at'] = DateTime.now().toIso8601String();
      });
      await widget.onChanged();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _markUnread(Map<String, dynamic> notification) async {
    final id = (notification['id'] as num?)?.toInt();
    if (id == null || notification['read_at'] == null) {
      return;
    }

    try {
      await widget.repository.markNotificationUnread(id);
      setState(() => notification['read_at'] = null);
      await widget.onChanged();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
  }

  Future<void> _markAllRead() async {
    if (_markingAllRead ||
        _notifications.every((item) => item['read_at'] != null)) {
      return;
    }

    setState(() => _markingAllRead = true);
    try {
      await widget.repository.markAllNotificationsRead();
      setState(() {
        final now = DateTime.now().toIso8601String();
        for (final item in _notifications) {
          item['read_at'] ??= now;
        }
      });
      await widget.onChanged();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _markingAllRead = false);
      }
    }
  }

  Future<void> _respondToGymInvitation(
    int invitationId, {
    Map<String, dynamic>? notification,
    Map<String, dynamic>? invitation,
    required bool accept,
  }) async {
    if (_respondingInvitationIds.contains(invitationId)) {
      return;
    }

    setState(() => _respondingInvitationIds.add(invitationId));
    try {
      if (accept) {
        await widget.repository.acceptGymInvitation(invitationId);
      } else {
        await widget.repository.rejectGymInvitation(invitationId);
      }

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            accept ? 'Gym invitation accepted.' : 'Gym invitation declined.',
          ),
        ),
      );
      final data = notification?['data'];
      if (data is Map) {
        data['status'] = accept ? 'accepted' : 'rejected';
      }
      if (invitation != null) {
        invitation['status'] = accept ? 'accepted' : 'rejected';
      }
      if (notification != null) {
        await _markRead(notification);
      }
      await widget.onChanged();
      if (accept && mounted) {
        Navigator.of(context).pop();
        return;
      }
      await _load();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _respondingInvitationIds.remove(invitationId));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final unreadCount = _notifications
        .where((item) => item['read_at'] == null)
        .length;
    final pendingInvitations = _gymInvitations
        .where((item) => (item['status']?.toString() ?? '') == 'pending')
        .toList();

    return Scaffold(
      backgroundColor: const Color(0xFFFFFFFF),
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        leadingWidth: 58,
        leading: Padding(
          padding: const EdgeInsets.only(left: 16, top: 8, bottom: 8),
          child: _TopSquareButton(
            icon: Icons.arrow_back_ios_new_rounded,
            onTap: () => Navigator.of(context).pop(),
          ),
        ),
        title: const Text(
          'Notification',
          style: TextStyle(
            color: Color(0xFF1D1617),
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 16, top: 8, bottom: 8),
            child: _TopSquareButton(
              icon: Icons.refresh_rounded,
              onTap: _loading ? null : _load,
            ),
          ),
        ],
      ),
      body: _loading && _notifications.isEmpty
          ? const LoadingState(label: 'Loading your notifications...')
          : _error != null && _notifications.isEmpty
          ? ErrorStateView(message: _error!, onRetry: _load)
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(25, 15, 25, 28),
                children: [
                  _NotificationSummaryBand(
                    unreadCount: unreadCount,
                    totalCount: _notifications.length,
                    invitationCount: pendingInvitations.length,
                    markingAllRead: _markingAllRead,
                    onMarkAllRead: unreadCount == 0 ? null : _markAllRead,
                  ),
                  if (pendingInvitations.isNotEmpty) ...[
                    const SizedBox(height: 18),
                    _NotificationSectionTitle(
                      title: 'Gym invitations',
                      action: '${pendingInvitations.length} pending',
                    ),
                    const SizedBox(height: 10),
                    ...pendingInvitations.asMap().entries.map(
                      (entry) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: RevealOnBuild(
                          delay: Duration(milliseconds: 45 * entry.key),
                          child: _GymInvitationInboxCard(
                            invitation: entry.value,
                            loading: _respondingInvitationIds.contains(
                              (entry.value['id'] as num?)?.toInt(),
                            ),
                            onAccept: () {
                              final id = (entry.value['id'] as num?)?.toInt();
                              if (id != null) {
                                _respondToGymInvitation(
                                  id,
                                  invitation: entry.value,
                                  accept: true,
                                );
                              }
                            },
                            onReject: () {
                              final id = (entry.value['id'] as num?)?.toInt();
                              if (id != null) {
                                _respondToGymInvitation(
                                  id,
                                  invitation: entry.value,
                                  accept: false,
                                );
                              }
                            },
                          ),
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  const _NotificationSectionTitle(
                    title: 'Updates',
                    action: 'Latest',
                  ),
                  const SizedBox(height: 2),
                  if (_notifications.isEmpty)
                    const EmptyStateView(
                      title: 'No notifications yet',
                      message:
                          'Membership expiry, dues, trainer updates, and workout reminders will appear here.',
                      icon: Icons.notifications_none_rounded,
                    )
                  else
                    ..._notifications.asMap().entries.expand(
                      (entry) => [
                        RevealOnBuild(
                          delay: Duration(milliseconds: 35 * entry.key),
                          child: _NotificationCard(
                            notification: entry.value,
                            respondingInvitationIds: _respondingInvitationIds,
                            onMarkRead: () => _markRead(entry.value),
                            onMarkUnread: () => _markUnread(entry.value),
                            onAcceptInvitation: () => _respondToGymInvitation(
                              _gymInvitationId(entry.value)!,
                              notification: entry.value,
                              accept: true,
                            ),
                            onRejectInvitation: () => _respondToGymInvitation(
                              _gymInvitationId(entry.value)!,
                              notification: entry.value,
                              accept: false,
                            ),
                          ),
                        ),
                        if (entry.key < _notifications.length - 1)
                          Divider(
                            color: const Color(
                              0xFF786F72,
                            ).withValues(alpha: 0.26),
                            height: 1,
                          ),
                      ],
                    ),
                ],
              ),
            ),
    );
  }

  int? _gymInvitationId(Map<String, dynamic> notification) {
    final data = notification['data'];
    if (data is Map) {
      final id = data['invitation_id'];
      if (id is num) {
        return id.toInt();
      }
      return int.tryParse(id?.toString() ?? '');
    }

    return null;
  }
}

class _TopSquareButton extends StatelessWidget {
  const _TopSquareButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        height: 40,
        width: 40,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: const Color(0xFFF7F8F8),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(icon, color: const Color(0xFF1D1617), size: 18),
      ),
    );
  }
}

class _NotificationSummaryBand extends StatelessWidget {
  const _NotificationSummaryBand({
    required this.unreadCount,
    required this.totalCount,
    required this.invitationCount,
    required this.markingAllRead,
    required this.onMarkAllRead,
  });

  final int unreadCount;
  final int totalCount;
  final int invitationCount;
  final bool markingAllRead;
  final VoidCallback? onMarkAllRead;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 16, 14, 16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF92A3FD).withValues(alpha: 0.25),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.24),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.notifications_active_rounded,
              color: Colors.white,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$unreadCount unread updates',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  invitationCount == 0
                      ? '$totalCount total notifications'
                      : '$totalCount updates • $invitationCount invitations',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.82),
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: markingAllRead ? null : onMarkAllRead,
            style: TextButton.styleFrom(
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            ),
            child: Text(
              markingAllRead ? '...' : 'Read all',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );
  }
}

class _NotificationSectionTitle extends StatelessWidget {
  const _NotificationSectionTitle({required this.title, required this.action});

  final String title;
  final String action;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
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
        Text(
          action,
          style: const TextStyle(
            color: Color(0xFF786F72),
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _GymInvitationInboxCard extends StatelessWidget {
  const _GymInvitationInboxCard({
    required this.invitation,
    required this.loading,
    required this.onAccept,
    required this.onReject,
  });

  final Map<String, dynamic> invitation;
  final bool loading;
  final VoidCallback onAccept;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context) {
    final gym = invitation['gym'] is Map
        ? Map<String, dynamic>.from(invitation['gym'] as Map)
        : const <String, dynamic>{};
    final branch = invitation['branch'] is Map
        ? Map<String, dynamic>.from(invitation['branch'] as Map)
        : const <String, dynamic>{};
    final trainer = invitation['assigned_trainer'] is Map
        ? Map<String, dynamic>.from(invitation['assigned_trainer'] as Map)
        : const <String, dynamic>{};
    final gymName = gym['name']?.toString() ?? 'Gym invitation';
    final meta = [
      branch['name']?.toString(),
      trainer['name'] == null ? null : 'Coach ${trainer['name']}',
    ].whereType<String>().where((item) => item.trim().isNotEmpty).join(' • ');

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFFFFFF), Color(0xFFF7F9FF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF92A3FD).withValues(alpha: 0.14),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
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
                child: const Icon(Icons.handshake_rounded, color: Colors.white),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      gymName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Color(0xFF1D1617),
                        fontWeight: FontWeight.w900,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      meta.isEmpty
                          ? 'Confirm before this gym can activate access.'
                          : meta,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Color(0xFF786F72),
                        fontWeight: FontWeight.w600,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _GymInvitationActions(
            loading: loading,
            onAccept: onAccept,
            onReject: onReject,
          ),
        ],
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({
    required this.notification,
    required this.respondingInvitationIds,
    required this.onMarkRead,
    required this.onMarkUnread,
    required this.onAcceptInvitation,
    required this.onRejectInvitation,
  });

  final Map<String, dynamic> notification;
  final Set<int> respondingInvitationIds;
  final VoidCallback onMarkRead;
  final VoidCallback onMarkUnread;
  final VoidCallback onAcceptInvitation;
  final VoidCallback onRejectInvitation;

  @override
  Widget build(BuildContext context) {
    final read = notification['read_at'] != null;
    final type = _notificationType(notification);
    final title =
        notification['title']?.toString() ??
        notification['message']?.toString() ??
        'Notification';
    final body =
        notification['body']?.toString() ??
        notification['content']?.toString() ??
        notification['message']?.toString() ??
        'No details available.';
    final invitationId = _gymInvitationId(notification);
    final isGymInvitation =
        type == 'gym_member_invitation' &&
        invitationId != null &&
        _gymInvitationStatus(notification) == 'pending' &&
        !read;
    final isResponding =
        invitationId != null && respondingInvitationIds.contains(invitationId);

    return InkWell(
      onTap: onMarkRead,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Column(
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        _typeColor(type).withValues(alpha: 0.88),
                        _typeColor(type).withValues(alpha: 0.58),
                      ],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(_typeIcon(type), color: Colors.white, size: 20),
                ),
                const SizedBox(width: 15),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 1),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                title,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: const Color(0xFF1D1617),
                                  fontWeight: read
                                      ? FontWeight.w600
                                      : FontWeight.w800,
                                  fontSize: 13,
                                  height: 1.25,
                                ),
                              ),
                            ),
                            if (!read)
                              Container(
                                width: 8,
                                height: 8,
                                margin: const EdgeInsets.only(left: 8),
                                decoration: const BoxDecoration(
                                  color: Color(0xFF92A3FD),
                                  shape: BoxShape.circle,
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 5),
                        Text(
                          body,
                          maxLines: isGymInvitation ? 3 : 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: const Color(
                              0xFF786F72,
                            ).withValues(alpha: 0.92),
                            fontSize: 11,
                            height: 1.35,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                _formatNotificationTime(notification),
                                style: const TextStyle(
                                  color: Color(0xFF786F72),
                                  fontSize: 10,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF7F8F8),
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Text(
                                _typeLabel(type),
                                style: TextStyle(
                                  color: _typeColor(type),
                                  fontSize: 9,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(
                  onPressed: read ? onMarkUnread : onMarkRead,
                  icon: Icon(
                    read ? Icons.mark_email_unread_rounded : Icons.done_rounded,
                    color: const Color(0xFF786F72),
                    size: 18,
                  ),
                ),
              ],
            ),
            if (isGymInvitation) ...[
              const SizedBox(height: 12),
              Padding(
                padding: const EdgeInsets.only(left: 57),
                child: _GymInvitationActions(
                  loading: isResponding,
                  onAccept: onAcceptInvitation,
                  onReject: onRejectInvitation,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _notificationType(Map<String, dynamic> notification) {
    return notification['type']?.toString() ??
        notification['notification_type']?.toString() ??
        'general';
  }

  String _typeLabel(String type) {
    switch (type) {
      case 'membership_expiry':
        return 'Membership Expiry';
      case 'payment_due':
        return 'Payment Due';
      case 'custom_due':
        return 'Custom Due';
      case 'gym_announcement':
        return 'Gym Announcement';
      case 'trainer_assignment':
        return 'Trainer Assignment';
      case 'gym_member_invitation':
        return 'Gym Invitation';
      case 'attendance_inactivity':
        return 'Attendance Inactivity';
      case 'trial_request_update':
        return 'Trial Update';
      case 'workout_reminder':
        return 'Workout Reminder';
      case 'pr_achievement':
      case 'PR achievement':
        return 'PR Achievement';
      default:
        return type
            .split('_')
            .map(
              (part) => part.isEmpty
                  ? part
                  : '${part[0].toUpperCase()}${part.substring(1)}',
            )
            .join(' ');
    }
  }

  Color _typeColor(String type) {
    switch (type) {
      case 'membership_expiry':
        return AppColors.warning;
      case 'payment_due':
      case 'custom_due':
        return const Color(0xFFF59E0B);
      case 'gym_announcement':
        return const Color(0xFF60A5FA);
      case 'trainer_assignment':
        return const Color(0xFF34D399);
      case 'gym_member_invitation':
        return const Color(0xFF38BDF8);
      case 'attendance_inactivity':
        return AppColors.statusPending;
      case 'trial_request_update':
        return const Color(0xFFA78BFA);
      case 'workout_reminder':
        return const Color(0xFF22D3EE);
      case 'pr_achievement':
      case 'PR achievement':
        return const Color(0xFFEAB308);
      default:
        return AppColors.primaryBright;
    }
  }

  IconData _typeIcon(String type) {
    switch (type) {
      case 'membership_expiry':
        return Icons.event_busy_rounded;
      case 'payment_due':
      case 'custom_due':
        return Icons.account_balance_wallet_rounded;
      case 'gym_announcement':
        return Icons.campaign_rounded;
      case 'trainer_assignment':
        return Icons.support_agent_rounded;
      case 'gym_member_invitation':
        return Icons.handshake_rounded;
      case 'attendance_inactivity':
        return Icons.qr_code_2_rounded;
      case 'trial_request_update':
        return Icons.flag_rounded;
      case 'workout_reminder':
        return Icons.fitness_center_rounded;
      case 'pr_achievement':
      case 'PR achievement':
        return Icons.emoji_events_rounded;
      default:
        return Icons.notifications_rounded;
    }
  }

  String _formatNotificationTime(Map<String, dynamic> notification) {
    final value =
        notification['created_at']?.toString() ??
        notification['sent_at']?.toString() ??
        notification['updated_at']?.toString();
    final date = DateTime.tryParse(value ?? '');
    if (date == null) {
      return 'Just now';
    }

    return DateFormat('dd MMM • hh:mm a').format(date.toLocal());
  }

  int? _gymInvitationId(Map<String, dynamic> notification) {
    final data = notification['data'];
    if (data is Map) {
      final id = data['invitation_id'];
      if (id is num) {
        return id.toInt();
      }
      return int.tryParse(id?.toString() ?? '');
    }

    return null;
  }

  String _gymInvitationStatus(Map<String, dynamic> notification) {
    final data = notification['data'];
    if (data is Map) {
      return data['status']?.toString() ?? 'pending';
    }

    return 'pending';
  }
}

class _GymInvitationActions extends StatelessWidget {
  const _GymInvitationActions({
    required this.loading,
    required this.onAccept,
    required this.onReject,
  });

  final bool loading;
  final VoidCallback onAccept;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final narrow = constraints.maxWidth < 340;
        final acceptButton = FilledButton.icon(
          onPressed: loading ? null : onAccept,
          icon: loading
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.check_rounded),
          label: Text(loading ? 'Updating...' : 'Accept'),
        );
        final rejectButton = OutlinedButton.icon(
          onPressed: loading ? null : onReject,
          icon: const Icon(Icons.close_rounded),
          label: const Text('Reject'),
        );

        if (narrow) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [acceptButton, const SizedBox(height: 10), rejectButton],
          );
        }

        return Row(
          children: [
            Expanded(child: acceptButton),
            const SizedBox(width: 10),
            Expanded(child: rejectButton),
          ],
        );
      },
    );
  }
}
