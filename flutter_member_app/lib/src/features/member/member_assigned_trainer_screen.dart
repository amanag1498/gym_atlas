import 'dart:async';

import 'package:flutter/material.dart';
import 'package:socket_io_client/socket_io_client.dart' as io;

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/loading_state.dart';
import 'member_repository.dart';

Map<String, dynamic> _trainerRecordMap(dynamic value) {
  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }

  return const <String, dynamic>{};
}

Map<String, dynamic> _normalizeMemberChatMessage(dynamic value) {
  final map = _trainerRecordMap(value);
  final clientId =
      map['client_message_id']?.toString() ??
      map['clientMessageId']?.toString();
  return <String, dynamic>{
    'id': map['id']?.toString() ?? clientId ?? UniqueKey().toString(),
    'room': map['room']?.toString(),
    'sender_id': _memberIntValue(map['sender_id'] ?? map['senderId']),
    'recipient_id': _memberIntValue(map['recipient_id'] ?? map['recipientId']),
    'body':
        map['body']?.toString() ??
        map['message']?.toString() ??
        map['content']?.toString() ??
        '',
    'client_message_id': clientId,
    'created_at':
        map['created_at']?.toString() ??
        map['createdAt']?.toString() ??
        DateTime.now().toIso8601String(),
    'read_at': map['read_at']?.toString() ?? map['readAt']?.toString(),
    'pending': map['pending'] == true,
    'failed': map['failed'] == true,
  };
}

int? _memberIntValue(dynamic value) {
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse(value?.toString() ?? '');
}

String _memberChatKey(Map<String, dynamic> message) {
  final clientId = message['client_message_id']?.toString();
  if (clientId != null && clientId.isNotEmpty) {
    return 'client:$clientId';
  }
  final id = message['id']?.toString();
  if (id != null && id.isNotEmpty) {
    return 'id:$id';
  }
  return '${message['sender_id']}:${message['recipient_id']}:${message['created_at']}:${message['body']}';
}

int _compareMemberChatMessages(Map<String, dynamic> a, Map<String, dynamic> b) {
  final aTime = DateTime.tryParse(a['created_at']?.toString() ?? '');
  final bTime = DateTime.tryParse(b['created_at']?.toString() ?? '');
  return (aTime ?? DateTime.fromMillisecondsSinceEpoch(0)).compareTo(
    bTime ?? DateTime.fromMillisecondsSinceEpoch(0),
  );
}

String _memberChatTime(dynamic value) {
  final parsed = DateTime.tryParse(value?.toString() ?? '');
  if (parsed == null) {
    return 'Just now';
  }
  final local = parsed.toLocal();
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}

String _memberChatDayLabel(dynamic value) {
  final parsed = DateTime.tryParse(value?.toString() ?? '');
  if (parsed == null) {
    return 'Today';
  }
  final local = parsed.toLocal();
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final messageDay = DateTime(local.year, local.month, local.day);
  final difference = today.difference(messageDay).inDays;
  if (difference == 0) {
    return 'Today';
  }
  if (difference == 1) {
    return 'Yesterday';
  }
  return '${local.day.toString().padLeft(2, '0')}/${local.month.toString().padLeft(2, '0')}/${local.year}';
}

const _fitPrimary1 = Color(0xFF92A3FD);
const _fitPrimary2 = Color(0xFF9DCEFF);
const _fitSecondary1 = Color(0xFFC58BF2);
const _fitSecondary2 = Color(0xFFEEA4CE);
const _fitBlack = Color(0xFF1D1617);
const _fitGray = Color(0xFF786F72);
const _fitLightGray = Color(0xFFF7F8F8);
const _fitPrimaryGradient = [_fitPrimary2, _fitPrimary1];
const _fitSecondaryGradient = [_fitSecondary2, _fitSecondary1];

class MemberAssignedTrainerScreen extends StatefulWidget {
  const MemberAssignedTrainerScreen({
    super.key,
    required this.repository,
    required this.socket,
    required this.chatEventVersion,
    required this.userState,
    required this.fallbackTrainerConnection,
    required this.onOpenAssignedWorkout,
  });

  final MemberRepository repository;
  final io.Socket? socket;
  final int chatEventVersion;
  final String userState;
  final Map<String, dynamic> fallbackTrainerConnection;
  final VoidCallback onOpenAssignedWorkout;

  @override
  State<MemberAssignedTrainerScreen> createState() =>
      _MemberAssignedTrainerScreenState();
}

class _MemberAssignedTrainerScreenState
    extends State<MemberAssignedTrainerScreen> {
  bool _loading = true;
  bool _chatLoading = false;
  String? _error;
  String? _chatError;
  Map<String, dynamic> _trainerResponse = const {};
  final List<Map<String, dynamic>> _messages = <Map<String, dynamic>>[];
  dynamic _chatMessageHandler;

  @override
  void initState() {
    super.initState();
    _trainerResponse = Map<String, dynamic>.from(
      widget.fallbackTrainerConnection,
    );
    _bindSocket();
    _load();
  }

  @override
  void didUpdateWidget(covariant MemberAssignedTrainerScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.socket != widget.socket) {
      if (_chatMessageHandler != null) {
        oldWidget.socket?.off('chat:new_message', _chatMessageHandler);
      }
      _bindSocket();
    }
    if (oldWidget.chatEventVersion != widget.chatEventVersion) {
      final trainerId = _assignedTrainerId;
      if (trainerId != null) {
        _loadChat(trainerId);
      }
    }
  }

  @override
  void dispose() {
    if (_chatMessageHandler != null) {
      widget.socket?.off('chat:new_message', _chatMessageHandler);
    }
    super.dispose();
  }

  void _bindSocket() {
    if (_chatMessageHandler != null) {
      widget.socket?.off('chat:new_message', _chatMessageHandler);
    }
    _chatMessageHandler = (data) {
      final message = _normalizeMemberChatMessage(
        _trainerRecordMap(data)['message'] ?? data,
      );
      final trainerId = _assignedTrainerId;
      if (trainerId == null || (message['body']?.toString() ?? '').isEmpty) {
        return;
      }
      final senderId = _memberIntValue(message['sender_id']);
      final recipientId = _memberIntValue(message['recipient_id']);
      if (senderId == trainerId || recipientId == trainerId) {
        _upsertMessage(message);
        widget.repository.markChatRead(trainerId);
      }
    };
    widget.socket?.on('chat:new_message', _chatMessageHandler);
  }

  int? get _assignedTrainerId {
    final assignedTrainer = Map<String, dynamic>.from(
      _trainerResponse['assigned_trainer'] as Map? ??
          widget.fallbackTrainerConnection['assigned_trainer'] as Map? ??
          const {},
    );
    return _memberIntValue(
      assignedTrainer['id'] ??
          assignedTrainer['user_id'] ??
          assignedTrainer['trainer_user_id'],
    );
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await widget.repository.fetchMemberTrainer();
      _trainerResponse = Map<String, dynamic>.from(
        response['data'] as Map? ?? const {},
      );
      final trainerId = _assignedTrainerId;
      if (trainerId != null) {
        await _loadChat(trainerId);
      }
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _loadChat(int trainerId) async {
    if (!mounted) {
      return;
    }
    setState(() {
      _chatLoading = true;
      _chatError = null;
    });

    try {
      final response = await widget.repository.fetchChatMessages(trainerId);
      final messages =
          (response['data'] as List<dynamic>? ?? const [])
              .map(_normalizeMemberChatMessage)
              .where(
                (message) => (message['body']?.toString() ?? '').isNotEmpty,
              )
              .toList()
            ..sort(_compareMemberChatMessages);
      if (mounted) {
        setState(() {
          _messages
            ..clear()
            ..addAll(messages);
        });
      }
      widget.repository.markChatRead(trainerId);
    } catch (exception) {
      if (mounted) {
        setState(() => _chatError = exception.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _chatLoading = false);
      }
    }
  }

  void _upsertMessage(Map<String, dynamic> message) {
    if (!mounted) {
      return;
    }
    final normalized = _normalizeMemberChatMessage(message);
    final key = _memberChatKey(normalized);
    final clientId = normalized['client_message_id']?.toString();
    setState(() {
      _messages.removeWhere((item) {
        final sameKey = _memberChatKey(item) == key;
        final sameClient =
            clientId != null &&
            clientId.isNotEmpty &&
            item['client_message_id']?.toString() == clientId;
        return sameKey || sameClient;
      });
      _messages.add(normalized);
      _messages.sort(_compareMemberChatMessages);
    });
  }

  Future<void> _openTrainerChatThread(Map<String, dynamic> trainer) async {
    final trainerId = _assignedTrainerId;
    if (trainerId == null) {
      return;
    }

    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => _MemberTrainerChatThreadScreen(
          repository: widget.repository,
          socket: widget.socket,
          trainerId: trainerId,
          trainer: trainer,
        ),
      ),
    );

    if (mounted) {
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final assignedTrainer = Map<String, dynamic>.from(
      _trainerResponse['assigned_trainer'] as Map? ??
          widget.fallbackTrainerConnection['assigned_trainer'] as Map? ??
          const {},
    );
    final hasTrainer = (assignedTrainer['id'] as num?)?.toInt() != null;
    final trainerId = _assignedTrainerId;
    final trainerName = assignedTrainer['name']?.toString() ?? 'Your trainer';
    final trainerAvatarUrl =
        assignedTrainer['profile_photo_url']?.toString() ??
        assignedTrainer['avatar']?.toString() ??
        assignedTrainer['photo']?.toString();

    return AppGradientScaffold(
      title: 'Chats',
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
      body: _loading && !hasTrainer
          ? const LoadingState(label: 'Loading your trainer chat...')
          : _error != null && !hasTrainer
          ? ErrorStateView(message: _error!, onRetry: _load)
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.lg,
                  AppSpacing.md,
                  AppSpacing.lg,
                  120,
                ),
                children: [
                  RevealOnBuild(
                    child: _MemberChatInboxHero(
                      hasTrainer: hasTrainer,
                      trainerName: trainerName,
                      trainerAvatarUrl: trainerAvatarUrl,
                      loading: _loading || _chatLoading,
                      onRefresh: _loading ? null : _load,
                    ),
                  ),
                  if (hasTrainer) ...[
                    const SizedBox(height: AppSpacing.lg),
                    RevealOnBuild(
                      delay: const Duration(milliseconds: 80),
                      child: _MemberTrainerChatCard(
                        trainerId: trainerId,
                        trainerName: trainerName,
                        trainerAvatarUrl: trainerAvatarUrl,
                        messages: _messages,
                        loading: _chatLoading,
                        error: _chatError,
                        onOpenChat: () =>
                            _openTrainerChatThread(assignedTrainer),
                        onRefresh: trainerId == null
                            ? null
                            : () => _loadChat(trainerId),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    RevealOnBuild(
                      delay: const Duration(milliseconds: 140),
                      child: _MemberChatQuickActions(
                        onOpenWorkout: widget.onOpenAssignedWorkout,
                      ),
                    ),
                  ] else ...[
                    const SizedBox(height: AppSpacing.lg),
                    RevealOnBuild(
                      delay: const Duration(milliseconds: 80),
                      child: _MemberChatNoTrainerCard(onRefresh: _load),
                    ),
                  ],
                ],
              ),
            ),
    );
  }
}

class _MemberChatInboxHero extends StatelessWidget {
  const _MemberChatInboxHero({
    required this.hasTrainer,
    required this.trainerName,
    required this.loading,
    this.trainerAvatarUrl,
    this.onRefresh,
  });

  final bool hasTrainer;
  final String trainerName;
  final String? trainerAvatarUrl;
  final bool loading;
  final VoidCallback? onRefresh;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: _fitPrimaryGradient,
        ),
        boxShadow: [
          BoxShadow(
            color: _fitPrimary1.withValues(alpha: 0.30),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -24,
            top: -28,
            child: Container(
              width: 118,
              height: 118,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.10),
              ),
            ),
          ),
          Positioned(
            right: 36,
            bottom: -34,
            child: Container(
              width: 92,
              height: 92,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.08),
              ),
            ),
          ),
          Column(
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
                          'Chats',
                          style: Theme.of(context).textTheme.headlineSmall
                              ?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w700,
                                letterSpacing: -0.6,
                              ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          hasTrainer
                              ? 'Message $trainerName about workouts, progress and recovery.'
                              : 'Your trainer conversation will appear here once the gym assigns a coach.',
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodyMedium
                              ?.copyWith(
                                color: Colors.white.withValues(alpha: 0.84),
                                fontWeight: FontWeight.w600,
                                height: 1.35,
                              ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 14),
                  _MemberChatHeroAvatar(
                    trainerAvatarUrl: trainerAvatarUrl,
                    hasTrainer: hasTrainer,
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _MemberChatHeroPill(
                    icon: hasTrainer
                        ? Icons.lock_rounded
                        : Icons.hourglass_top_rounded,
                    label: hasTrainer ? 'Private 1:1 chat' : 'Pending trainer',
                  ),
                  const _MemberChatHeroPill(
                    icon: Icons.notifications_active_rounded,
                    label: 'Push alerts',
                  ),
                  if (onRefresh != null)
                    InkWell(
                      borderRadius: BorderRadius.circular(999),
                      onTap: loading ? null : onRefresh,
                      child: _MemberChatHeroPill(
                        icon: loading
                            ? Icons.sync_rounded
                            : Icons.refresh_rounded,
                        label: loading ? 'Syncing' : 'Refresh',
                      ),
                    ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MemberChatHeroAvatar extends StatelessWidget {
  const _MemberChatHeroAvatar({
    required this.trainerAvatarUrl,
    required this.hasTrainer,
  });

  final String? trainerAvatarUrl;
  final bool hasTrainer;

  @override
  Widget build(BuildContext context) {
    final imageUrl = trainerAvatarUrl?.trim();
    return Container(
      width: 58,
      height: 58,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.20),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white.withValues(alpha: 0.42)),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(15),
        child: imageUrl == null || imageUrl.isEmpty
            ? Container(
                color: Colors.white.withValues(alpha: 0.18),
                child: Icon(
                  hasTrainer
                      ? Icons.support_agent_rounded
                      : Icons.person_search_rounded,
                  color: Colors.white,
                ),
              )
            : Image.network(
                imageUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => Container(
                  color: Colors.white.withValues(alpha: 0.18),
                  child: const Icon(
                    Icons.support_agent_rounded,
                    color: Colors.white,
                  ),
                ),
              ),
      ),
    );
  }
}

class _MemberChatHeroPill extends StatelessWidget {
  const _MemberChatHeroPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.22),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.20)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: Colors.white),
          const SizedBox(width: 7),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MemberChatQuickActions extends StatelessWidget {
  const _MemberChatQuickActions({required this.onOpenWorkout});

  final VoidCallback onOpenWorkout;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _MemberChatActionTile(
            icon: Icons.fitness_center_rounded,
            title: 'Assigned workout',
            subtitle: 'Open current plan',
            color: _fitPrimary1,
            gradient: _fitPrimaryGradient,
            onTap: onOpenWorkout,
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        const Expanded(
          child: _MemberChatActionTile(
            icon: Icons.verified_user_rounded,
            title: 'Trainer chat',
            subtitle: 'Private to you',
            color: _fitSecondary1,
            gradient: _fitSecondaryGradient,
          ),
        ),
      ],
    );
  }
}

class _MemberChatActionTile extends StatelessWidget {
  const _MemberChatActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.gradient,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final List<Color> gradient;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          boxShadow: const [
            BoxShadow(
              color: Colors.black12,
              blurRadius: 2,
              offset: Offset(0, 1),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: gradient),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Icon(icon, color: Colors.white, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberChatNoTrainerCard extends StatelessWidget {
  const _MemberChatNoTrainerCard({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [
          BoxShadow(color: Colors.black12, blurRadius: 2, offset: Offset(0, 1)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: _fitSecondaryGradient),
              borderRadius: BorderRadius.circular(22),
            ),
            child: const Icon(Icons.person_search_rounded, color: Colors.white),
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            'No trainer chat yet',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            'Once your gym assigns a trainer, this page becomes your private WhatsApp-style conversation.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
              height: 1.45,
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          InkWell(
            borderRadius: BorderRadius.circular(18),
            onTap: onRefresh,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 14),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: _fitPrimaryGradient),
                borderRadius: BorderRadius.circular(25),
                boxShadow: const [
                  BoxShadow(
                    color: Colors.black26,
                    blurRadius: 0.5,
                    offset: Offset(0, 0.5),
                  ),
                ],
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(
                    Icons.refresh_rounded,
                    color: Colors.white,
                    size: 18,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Check assignment',
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
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

class _MemberTrainerChatCard extends StatelessWidget {
  const _MemberTrainerChatCard({
    required this.trainerId,
    required this.trainerName,
    required this.trainerAvatarUrl,
    required this.messages,
    required this.loading,
    required this.onOpenChat,
    this.error,
    this.onRefresh,
  });

  final int? trainerId;
  final String trainerName;
  final String? trainerAvatarUrl;
  final List<Map<String, dynamic>> messages;
  final bool loading;
  final VoidCallback onOpenChat;
  final String? error;
  final Future<void> Function()? onRefresh;

  @override
  Widget build(BuildContext context) {
    final lastMessage = messages.isEmpty ? null : messages.last;
    final preview = lastMessage == null
        ? 'Tap into your private trainer conversation'
        : lastMessage['body']?.toString() ?? 'Message';
    final hasUnread =
        trainerId != null &&
        lastMessage != null &&
        _memberIntValue(lastMessage['sender_id']) == trainerId;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: const [
          BoxShadow(color: Colors.black12, blurRadius: 2, offset: Offset(0, 1)),
        ],
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: _fitSecondaryGradient,
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: [
                    BoxShadow(
                      color: _fitSecondary1.withValues(alpha: 0.22),
                      blurRadius: 12,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.chat_bubble_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Messages',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    Text(
                      trainerId == null
                          ? 'Trainer assignment pending'
                          : 'Your trainer inbox',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 11,
                  vertical: 7,
                ),
                decoration: BoxDecoration(
                  color: trainerId == null
                      ? _fitLightGray
                      : _fitPrimary1.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(
                    color: trainerId == null
                        ? AppColors.primaryBright.withValues(alpha: 0.35)
                        : _fitPrimary1.withValues(alpha: 0.28),
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 7,
                      height: 7,
                      decoration: BoxDecoration(
                        color: trainerId == null
                            ? AppColors.primaryBright
                            : _fitPrimary1,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      trainerId == null ? 'Locked' : 'Live',
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: trainerId == null
                            ? AppColors.textSecondary
                            : _fitPrimary1,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              if (onRefresh != null)
                IconButton(
                  onPressed: loading ? null : onRefresh,
                  icon: const Icon(Icons.refresh_rounded),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          if (loading)
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: const LinearProgressIndicator(
                minHeight: 4,
                backgroundColor: _fitLightGray,
                color: _fitPrimary1,
              ),
            ),
          if (loading) const SizedBox(height: AppSpacing.md),
          _MemberConversationCard(
            trainerName: trainerName,
            trainerAvatarUrl: trainerAvatarUrl,
            preview: preview,
            time: lastMessage == null
                ? 'New'
                : _memberChatTime(lastMessage['created_at']),
            enabled: trainerId != null,
            unreadCount: hasUnread ? 1 : 0,
            loading: loading,
            onTap: trainerId == null ? null : onOpenChat,
          ),
          if (error != null) ...[
            const SizedBox(height: AppSpacing.sm),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.error.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.info_outline_rounded,
                    color: AppColors.error,
                    size: 18,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      error!,
                      style: Theme.of(
                        context,
                      ).textTheme.bodySmall?.copyWith(color: AppColors.error),
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: const [
              _MemberChatTopicChip(label: 'Workout'),
              _MemberChatTopicChip(label: 'Progress'),
              _MemberChatTopicChip(label: 'Recovery'),
            ],
          ),
        ],
      ),
    );
  }
}

class _MemberChatTopicChip extends StatelessWidget {
  const _MemberChatTopicChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0x1F0B7C66)),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: _fitPrimary1,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _MemberConversationCard extends StatelessWidget {
  const _MemberConversationCard({
    required this.trainerName,
    required this.preview,
    required this.time,
    required this.enabled,
    required this.onTap,
    this.trainerAvatarUrl,
    this.unreadCount = 0,
    this.loading = false,
  });

  final String trainerName;
  final String preview;
  final String time;
  final bool enabled;
  final VoidCallback? onTap;
  final String? trainerAvatarUrl;
  final int unreadCount;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(15),
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(15),
          boxShadow: const [
            BoxShadow(
              color: Colors.black12,
              blurRadius: 2,
              offset: Offset(0, 1),
            ),
          ],
        ),
        child: Row(
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  padding: const EdgeInsets.all(3),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(colors: _fitPrimaryGradient),
                    shape: BoxShape.circle,
                  ),
                  child: CircleAvatar(
                    radius: 29,
                    backgroundColor: AppColors.surfaceStrong,
                    backgroundImage:
                        trainerAvatarUrl != null &&
                            trainerAvatarUrl!.trim().isNotEmpty
                        ? NetworkImage(trainerAvatarUrl!)
                        : null,
                    child:
                        trainerAvatarUrl == null ||
                            trainerAvatarUrl!.trim().isEmpty
                        ? Text(
                            trainerName.trim().isEmpty
                                ? 'T'
                                : trainerName.trim()[0],
                            style: const TextStyle(
                              color: AppColors.textPrimary,
                              fontWeight: FontWeight.w900,
                            ),
                          )
                        : null,
                  ),
                ),
                Positioned(
                  right: 2,
                  bottom: 2,
                  child: Container(
                    width: 14,
                    height: 14,
                    decoration: BoxDecoration(
                      color: enabled ? _fitSecondary1 : AppColors.textMuted,
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    trainerName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Row(
                    children: [
                      Icon(
                        enabled
                            ? Icons.done_all_rounded
                            : Icons.lock_outline_rounded,
                        color: unreadCount > 0
                            ? _fitPrimary1
                            : AppColors.textMuted,
                        size: 15,
                      ),
                      const SizedBox(width: 5),
                      Expanded(
                        child: Text(
                          loading ? 'Syncing latest messages...' : preview,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: unreadCount > 0
                                    ? AppColors.textPrimary
                                    : AppColors.textMuted,
                                fontWeight: unreadCount > 0
                                    ? FontWeight.w800
                                    : FontWeight.w600,
                              ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  time,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: unreadCount > 0 ? _fitPrimary1 : AppColors.textMuted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  width: unreadCount > 0 ? 24 : 30,
                  height: 24,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: unreadCount > 0
                        ? _fitPrimary1
                        : const Color(0xFFF1F7F4),
                    shape: unreadCount > 0
                        ? BoxShape.circle
                        : BoxShape.rectangle,
                    borderRadius: unreadCount > 0
                        ? null
                        : BorderRadius.circular(999),
                  ),
                  child: unreadCount > 0
                      ? Text(
                          '$unreadCount',
                          style: Theme.of(context).textTheme.labelSmall
                              ?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w700,
                              ),
                        )
                      : Icon(
                          enabled
                              ? Icons.chevron_right_rounded
                              : Icons.lock_outline_rounded,
                          size: 18,
                          color: enabled ? _fitPrimary1 : AppColors.textMuted,
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

class _MemberTrainerChatThreadScreen extends StatefulWidget {
  const _MemberTrainerChatThreadScreen({
    required this.repository,
    required this.socket,
    required this.trainerId,
    required this.trainer,
  });

  final MemberRepository repository;
  final io.Socket? socket;
  final int trainerId;
  final Map<String, dynamic> trainer;

  @override
  State<_MemberTrainerChatThreadScreen> createState() =>
      _MemberTrainerChatThreadScreenState();
}

class _MemberTrainerChatThreadScreenState
    extends State<_MemberTrainerChatThreadScreen> {
  final TextEditingController _controller = TextEditingController();
  final List<Map<String, dynamic>> _messages = <Map<String, dynamic>>[];
  bool _loading = true;
  bool _loadingOlder = false;
  bool _hasOlderMessages = false;
  bool _sending = false;
  String? _error;
  int? _nextBeforeId;
  dynamic _chatMessageHandler;

  @override
  void initState() {
    super.initState();
    _chatMessageHandler = _handleSocketMessage;
    widget.socket?.on('chat:new_message', _chatMessageHandler);
    _load();
  }

  @override
  void dispose() {
    if (_chatMessageHandler != null) {
      widget.socket?.off('chat:new_message', _chatMessageHandler);
    }
    _controller.dispose();
    super.dispose();
  }

  void _handleSocketMessage(dynamic data) {
    if (!mounted) {
      return;
    }
    final message = _normalizeMemberChatMessage(
      _trainerRecordMap(data)['message'] ?? data,
    );
    final senderId = _memberIntValue(message['sender_id']);
    final recipientId = _memberIntValue(message['recipient_id']);
    if (senderId == widget.trainerId || recipientId == widget.trainerId) {
      _upsert(message);
      widget.repository.markChatRead(widget.trainerId);
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.repository.fetchChatMessages(
        widget.trainerId,
      );
      final messages =
          (response['data'] as List<dynamic>? ?? const [])
              .map(_normalizeMemberChatMessage)
              .where(
                (message) => (message['body']?.toString() ?? '').isNotEmpty,
              )
              .toList()
            ..sort(_compareMemberChatMessages);
      if (mounted) {
        setState(() {
          _messages
            ..clear()
            ..addAll(messages);
          _applyCursorMeta(response['meta']);
        });
      }
      widget.repository.markChatRead(widget.trainerId);
    } catch (exception) {
      if (mounted) {
        setState(() => _error = exception.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _loadOlder() async {
    final beforeId = _nextBeforeId;
    if (_loadingOlder || !_hasOlderMessages || beforeId == null) {
      return;
    }

    setState(() {
      _loadingOlder = true;
      _error = null;
    });

    try {
      final response = await widget.repository.fetchChatMessages(
        widget.trainerId,
        beforeId: beforeId,
      );
      final olderMessages = (response['data'] as List<dynamic>? ?? const [])
          .map(_normalizeMemberChatMessage)
          .where((message) => (message['body']?.toString() ?? '').isNotEmpty)
          .toList();

      if (mounted) {
        setState(() {
          for (final message in olderMessages) {
            _upsertSilently(message);
          }
          _messages.sort(_compareMemberChatMessages);
          _applyCursorMeta(response['meta']);
        });
      }
    } catch (exception) {
      if (mounted) {
        setState(() => _error = exception.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _loadingOlder = false);
      }
    }
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    if (body.isEmpty || _sending) {
      return;
    }

    final clientMessageId =
        'member-${DateTime.now().microsecondsSinceEpoch}-${widget.trainerId}';
    final optimistic = <String, dynamic>{
      'id': clientMessageId,
      'sender_id': null,
      'recipient_id': widget.trainerId,
      'body': body,
      'client_message_id': clientMessageId,
      'created_at': DateTime.now().toIso8601String(),
      'pending': true,
    };

    _controller.clear();
    _upsert(optimistic);
    setState(() => _sending = true);

    try {
      if (widget.socket?.connected == true) {
        try {
          final socketMessage = await _sendChatOverSocket(
            body: body,
            clientMessageId: clientMessageId,
          );
          _upsert(socketMessage);
        } catch (_) {
          final response = await widget.repository.sendChatMessage(
            widget.trainerId,
            body,
            clientMessageId: clientMessageId,
          );
          _upsert(_normalizeMemberChatMessage(response['data']));
        }
      } else {
        final response = await widget.repository.sendChatMessage(
          widget.trainerId,
          body,
          clientMessageId: clientMessageId,
        );
        _upsert(_normalizeMemberChatMessage(response['data']));
      }
    } catch (exception) {
      _upsert({...optimistic, 'pending': false, 'failed': true});
      if (mounted) {
        setState(() => _error = exception.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  Future<Map<String, dynamic>> _sendChatOverSocket({
    required String body,
    required String clientMessageId,
  }) {
    final socket = widget.socket;
    if (socket?.connected != true) {
      return Future<Map<String, dynamic>>.error(
        StateError('Chat socket is not connected.'),
      );
    }

    final completer = Completer<Map<String, dynamic>>();
    socket!.emitWithAck(
      'chat:send',
      {
        'recipientId': widget.trainerId,
        'message': body,
        'clientMessageId': clientMessageId,
        'metadata': {'source': 'member_app'},
      },
      ack: (dynamic response) {
        if (completer.isCompleted) {
          return;
        }

        final map = _trainerRecordMap(response);
        if (map['ok'] != true) {
          completer.completeError(
            Exception(
              map['error']?.toString() ?? 'Socket chat persistence failed.',
            ),
          );
          return;
        }

        final message = _normalizeMemberChatMessage(map['message']);
        if (map['message'] is Map &&
            Map<String, dynamic>.from(map['message'] as Map)['persisted'] ==
                false) {
          completer.completeError(
            Exception('Socket chat message was not persisted.'),
          );
          return;
        }

        completer.complete(message);
      },
    );

    return completer.future.timeout(const Duration(seconds: 8));
  }

  void _upsert(Map<String, dynamic> message) {
    final normalized = _normalizeMemberChatMessage(message);
    setState(() {
      _upsertSilently(normalized);
      _messages.sort(_compareMemberChatMessages);
    });
  }

  void _upsertSilently(Map<String, dynamic> message) {
    final normalized = _normalizeMemberChatMessage(message);
    final key = _memberChatKey(normalized);
    final clientId = normalized['client_message_id']?.toString();
    _messages.removeWhere((item) {
      return _memberChatKey(item) == key ||
          (clientId != null &&
              clientId.isNotEmpty &&
              item['client_message_id']?.toString() == clientId);
    });
    _messages.add(normalized);
  }

  void _applyCursorMeta(dynamic meta) {
    final cursor = _trainerRecordMap(_trainerRecordMap(meta)['cursor']);
    _hasOlderMessages = cursor['has_more'] == true;
    _nextBeforeId = _memberIntValue(cursor['next_before_id']);
  }

  @override
  Widget build(BuildContext context) {
    final trainerName = widget.trainer['name']?.toString() ?? 'Trainer';
    final avatarUrl =
        widget.trainer['profile_photo_url']?.toString() ??
        widget.trainer['avatar']?.toString() ??
        widget.trainer['photo']?.toString();

    return Scaffold(
      backgroundColor: _fitLightGray,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _MemberChatThreadHeader(
              trainerName: trainerName,
              trainerAvatarUrl: avatarUrl,
              loading: _loading,
              onRefresh: _load,
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: AppColors.error.withValues(alpha: 0.18),
                    ),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.info_outline_rounded,
                        color: AppColors.error,
                        size: 18,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _error!,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: AppColors.error),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            Expanded(
              child: Stack(
                children: [
                  const _MemberChatPatternBackground(),
                  if (_loading)
                    const Center(child: CircularProgressIndicator())
                  else if (_messages.isEmpty)
                    const _MemberChatEmptyState()
                  else
                    ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 18, 16, 18),
                      itemBuilder: (context, index) {
                        if (_hasOlderMessages) {
                          if (index == 0) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: _MemberLoadOlderMessagesButton(
                                loading: _loadingOlder,
                                onPressed: _loadOlder,
                              ),
                            );
                          }
                          index -= 1;
                        }

                        final message = _messages[index];
                        final dayLabel = _memberChatDayLabel(
                          message['created_at'],
                        );
                        final previousDay = index == 0
                            ? null
                            : _memberChatDayLabel(
                                _messages[index - 1]['created_at'],
                              );
                        final showDay = dayLabel != previousDay;
                        final isIncoming =
                            _memberIntValue(message['sender_id']) ==
                            widget.trainerId;
                        return Column(
                          children: [
                            if (showDay)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 12),
                                child: _MemberChatDatePill(label: dayLabel),
                              ),
                            _MemberChatBubble(
                              body: message['body']?.toString() ?? '',
                              time: message['failed'] == true
                                  ? 'Failed'
                                  : message['pending'] == true
                                  ? 'Sending'
                                  : _memberChatTime(message['created_at']),
                              isIncoming: isIncoming,
                              pending: message['pending'] == true,
                              failed: message['failed'] == true,
                            ),
                            const SizedBox(height: 8),
                          ],
                        );
                      },
                      itemCount: _messages.length + (_hasOlderMessages ? 1 : 0),
                    ),
                ],
              ),
            ),
            _MemberChatComposer(
              controller: _controller,
              sending: _sending,
              onSend: _send,
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberChatThreadHeader extends StatelessWidget {
  const _MemberChatThreadHeader({
    required this.trainerName,
    required this.loading,
    required this.onRefresh,
    this.trainerAvatarUrl,
  });

  final String trainerName;
  final String? trainerAvatarUrl;
  final bool loading;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      padding: const EdgeInsets.fromLTRB(6, 10, 10, 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(25),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: _fitPrimaryGradient,
        ),
        boxShadow: [
          BoxShadow(
            color: _fitPrimary1.withValues(alpha: 0.26),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.of(context).pop(),
            icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
          ),
          Stack(
            clipBehavior: Clip.none,
            children: [
              CircleAvatar(
                radius: 24,
                backgroundColor: Colors.white24,
                backgroundImage:
                    trainerAvatarUrl != null &&
                        trainerAvatarUrl!.trim().isNotEmpty
                    ? NetworkImage(trainerAvatarUrl!)
                    : null,
                child:
                    trainerAvatarUrl == null || trainerAvatarUrl!.trim().isEmpty
                    ? Text(
                        trainerName.trim().isEmpty
                            ? 'T'
                            : trainerName.trim()[0],
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                        ),
                      )
                    : null,
              ),
              Positioned(
                right: 0,
                bottom: 0,
                child: Container(
                  width: 13,
                  height: 13,
                  decoration: BoxDecoration(
                    color: _fitSecondary2,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  trainerName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Container(
                      width: 6,
                      height: 6,
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      loading ? 'Syncing chat...' : 'Trainer conversation',
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: Colors.white.withValues(alpha: 0.86),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: loading ? null : onRefresh,
            icon: Icon(
              Icons.refresh_rounded,
              color: loading ? Colors.white38 : Colors.white,
            ),
          ),
        ],
      ),
    );
  }
}

class _MemberChatPatternBackground extends StatelessWidget {
  const _MemberChatPatternBackground();

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [Colors.white, _fitLightGray],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            right: -42,
            top: 46,
            child: _MemberChatSoftOrb(
              color: _fitPrimary2.withValues(alpha: 0.18),
              size: 150,
            ),
          ),
          Positioned(
            left: -55,
            bottom: 90,
            child: _MemberChatSoftOrb(
              color: _fitSecondary2.withValues(alpha: 0.18),
              size: 170,
            ),
          ),
        ],
      ),
    );
  }
}

class _MemberChatSoftOrb extends StatelessWidget {
  const _MemberChatSoftOrb({required this.color, required this.size});

  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}

class _MemberChatDatePill extends StatelessWidget {
  const _MemberChatDatePill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.86),
        borderRadius: BorderRadius.circular(999),
        boxShadow: const [
          BoxShadow(color: Colors.black12, blurRadius: 2, offset: Offset(0, 1)),
        ],
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: AppColors.textSecondary,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _MemberLoadOlderMessagesButton extends StatelessWidget {
  const _MemberLoadOlderMessagesButton({
    required this.loading,
    required this.onPressed,
  });

  final bool loading;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: loading ? null : onPressed,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(999),
            boxShadow: const [
              BoxShadow(
                color: Colors.black12,
                blurRadius: 2,
                offset: Offset(0, 1),
              ),
            ],
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                loading ? Icons.sync_rounded : Icons.history_rounded,
                size: 16,
                color: _fitPrimary1,
              ),
              const SizedBox(width: 8),
              Text(
                loading ? 'Loading older messages' : 'Load older messages',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: _fitGray,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MemberChatEmptyState extends StatelessWidget {
  const _MemberChatEmptyState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.92),
            borderRadius: BorderRadius.circular(22),
            boxShadow: const [
              BoxShadow(
                color: Colors.black12,
                blurRadius: 2,
                offset: Offset(0, 1),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 68,
                height: 68,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(colors: _fitSecondaryGradient),
                  borderRadius: BorderRadius.circular(24),
                ),
                child: const Icon(
                  Icons.chat_bubble_outline_rounded,
                  color: Colors.white,
                  size: 32,
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Text(
                'Start the conversation',
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              Text(
                'Ask about workouts, recovery, soreness, or progress. Your trainer will see it in their inbox.',
                textAlign: TextAlign.center,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MemberChatComposer extends StatelessWidget {
  const _MemberChatComposer({
    required this.controller,
    required this.sending,
    required this.onSend,
  });

  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 14),
        decoration: const BoxDecoration(
          color: _fitLightGray,
          boxShadow: [
            BoxShadow(
              color: Color(0x101D1617),
              blurRadius: 20,
              offset: Offset(0, -8),
            ),
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(25),
                  boxShadow: const [
                    BoxShadow(
                      color: Colors.black12,
                      blurRadius: 2,
                      offset: Offset(0, 1),
                    ),
                  ],
                ),
                child: TextField(
                  controller: controller,
                  minLines: 1,
                  maxLines: 5,
                  enabled: !sending,
                  textInputAction: TextInputAction.send,
                  decoration: InputDecoration(
                    hintText: 'Message your trainer',
                    prefixIcon: const Icon(Icons.lock_outline_rounded),
                    suffixIcon: Icon(
                      Icons.sentiment_satisfied_alt_rounded,
                      color: AppColors.textMuted.withValues(alpha: 0.75),
                    ),
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 15,
                    ),
                  ),
                  onSubmitted: (_) => onSend(),
                ),
              ),
            ),
            const SizedBox(width: 10),
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: _fitPrimaryGradient,
                ),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: _fitPrimary1.withValues(alpha: 0.32),
                    blurRadius: 10,
                    offset: const Offset(0, 5),
                  ),
                ],
              ),
              child: IconButton(
                onPressed: sending ? null : onSend,
                icon: Icon(
                  sending ? Icons.hourglass_top_rounded : Icons.send_rounded,
                  color: Colors.white,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberChatBubble extends StatelessWidget {
  const _MemberChatBubble({
    required this.body,
    required this.time,
    required this.isIncoming,
    required this.pending,
    required this.failed,
  });

  final String body;
  final String time;
  final bool isIncoming;
  final bool pending;
  final bool failed;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: isIncoming ? Alignment.centerLeft : Alignment.centerRight,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.78,
        ),
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 10, 10, 8),
          decoration: BoxDecoration(
            gradient: isIncoming
                ? null
                : const LinearGradient(colors: _fitPrimaryGradient),
            color: isIncoming ? Colors.white : null,
            borderRadius: BorderRadius.only(
              topLeft: Radius.circular(isIncoming ? 6 : 20),
              topRight: Radius.circular(isIncoming ? 20 : 6),
              bottomLeft: const Radius.circular(20),
              bottomRight: const Radius.circular(20),
            ),
            boxShadow: const [
              BoxShadow(
                color: Colors.black12,
                blurRadius: 2,
                offset: Offset(0, 1),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                body,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: isIncoming ? _fitBlack : Colors.white,
                  fontWeight: FontWeight.w600,
                  height: 1.28,
                ),
              ),
              const SizedBox(height: 5),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    time,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: failed
                          ? AppColors.error
                          : isIncoming
                          ? _fitGray
                          : Colors.white.withValues(alpha: 0.82),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  if (!isIncoming) ...[
                    const SizedBox(width: 4),
                    Icon(
                      failed
                          ? Icons.error_outline_rounded
                          : pending
                          ? Icons.access_time_rounded
                          : Icons.done_all_rounded,
                      size: 15,
                      color: failed
                          ? AppColors.error
                          : pending
                          ? AppColors.textMuted
                          : isIncoming
                          ? _fitPrimary1
                          : Colors.white,
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class AssignedTrainerShowcaseCard extends StatelessWidget {
  const AssignedTrainerShowcaseCard({
    super.key,
    required this.trainer,
    required this.workoutLabel,
    required this.onOpenWorkout,
    this.compact = false,
    this.disabledMessage,
  });

  final Map<String, dynamic> trainer;
  final String workoutLabel;
  final VoidCallback onOpenWorkout;
  final bool compact;
  final String? disabledMessage;

  @override
  Widget build(BuildContext context) {
    final specializations =
        (trainer['specializations'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .where((item) => item.isNotEmpty)
            .take(compact ? 2 : 4)
            .toList();
    final languages = (trainer['languages'] as List<dynamic>? ?? const [])
        .map((item) => item.toString())
        .where((item) => item.isNotEmpty)
        .take(3)
        .toList();
    final availability =
        (trainer['availability_slots'] as List<dynamic>? ?? const [])
            .map((item) => item.toString())
            .where((item) => item.isNotEmpty)
            .take(compact ? 2 : 3)
            .toList();
    final branch = _trainerRecordMap(trainer['assigned_branch']);
    final gym = _trainerRecordMap(trainer['assigned_gym']);
    final imageUrl = (trainer['profile_photo_url']?.toString() ?? '').isNotEmpty
        ? trainer['profile_photo_url'].toString()
        : trainer['photo']?.toString();
    final hasTrainer = (trainer['id'] as num?)?.toInt() != null;
    final availabilityNotes = trainer['availability_notes']?.toString() ?? '';

    return GlassCard(
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [
          const Color(0x221ED8C0),
          Theme.of(context).colorScheme.primary.withValues(alpha: 0.16),
          const Color(0xFF111827),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: compact ? 30 : 38,
                backgroundColor: Colors.white.withValues(alpha: 0.08),
                backgroundImage: (imageUrl ?? '').trim().isNotEmpty
                    ? NetworkImage(imageUrl!)
                    : null,
                child: (imageUrl ?? '').trim().isNotEmpty
                    ? null
                    : const Icon(Icons.fitness_center_rounded),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      trainer['name']?.toString() ?? 'Trainer pending',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      hasTrainer
                          ? (trainer['primary_specialization']?.toString() ??
                                specializations.firstOrNull ??
                                'Guided training support')
                          : (disabledMessage ??
                                'Your trainer profile will appear here once assigned.'),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        StatusBadge(
                          label: hasTrainer
                              ? (trainer['experience_label']?.toString() ??
                                    '${trainer['experience_years'] ?? 0} yrs experience')
                              : 'Pending assignment',
                          color: const Color(0xFF22D3EE),
                        ),
                        if (branch.isNotEmpty)
                          StatusBadge(
                            label:
                                branch['name']?.toString() ?? 'Assigned branch',
                            color: const Color(0xFF34D399),
                          ),
                        if (gym.isNotEmpty)
                          StatusBadge(
                            label: gym['name']?.toString() ?? 'Assigned gym',
                            color: const Color(0xFFA78BFA),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          if ((trainer['bio']?.toString() ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              trainer['bio'].toString(),
              maxLines: compact ? 2 : 4,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          const SizedBox(height: AppSpacing.md),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (languages.isNotEmpty)
                _InlineInfoChip(
                  icon: Icons.translate_rounded,
                  label: languages.join(' • '),
                ),
              if (availability.isNotEmpty)
                _InlineInfoChip(
                  icon: Icons.schedule_rounded,
                  label: availability.join(' • '),
                ),
              if (availabilityNotes.trim().isNotEmpty)
                _InlineInfoChip(
                  icon: Icons.event_note_rounded,
                  label: availabilityNotes,
                ),
            ],
          ),
          if (specializations.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: specializations
                  .map(
                    (item) => StatusBadge(
                      label: item,
                      color: AppColors.textSecondary,
                    ),
                  )
                  .toList(),
            ),
          ],
          const SizedBox(height: AppSpacing.lg),
          GradientButton(
            label: workoutLabel,
            icon: Icons.play_circle_fill_rounded,
            expanded: true,
            onPressed: onOpenWorkout,
          ),
        ],
      ),
    );
  }
}

class _InlineInfoChip extends StatelessWidget {
  const _InlineInfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.sm,
        vertical: AppSpacing.xs,
      ),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: AppColors.primaryBright),
          const SizedBox(width: 6),
          Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
        ],
      ),
    );
  }
}
