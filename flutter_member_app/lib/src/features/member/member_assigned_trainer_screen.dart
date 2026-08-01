import 'dart:async';

import 'package:flutter/material.dart';
import 'package:socket_io_client/socket_io_client.dart' as io;

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/loading_state.dart';
import '../../../core/widgets/premium_card.dart';
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

class MemberAssignedTrainerScreen extends StatefulWidget {
  const MemberAssignedTrainerScreen({
    super.key,
    required this.repository,
    required this.socket,
    required this.chatEventVersion,
    required this.chatLaunchVersion,
    required this.selectedGymId,
    required this.selectedGymName,
    required this.userState,
    required this.currentUserName,
    required this.fallbackTrainerConnection,
    required this.onOpenAssignedWorkout,
    this.onCoachingChanged,
    this.chatTargetTrainerId,
  });

  final MemberRepository repository;
  final io.Socket? socket;
  final int chatEventVersion;
  final int chatLaunchVersion;
  final int? chatTargetTrainerId;
  final int? selectedGymId;
  final String selectedGymName;
  final String userState;
  final String currentUserName;
  final Map<String, dynamic> fallbackTrainerConnection;
  final VoidCallback onOpenAssignedWorkout;
  final Future<void> Function()? onCoachingChanged;

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
  List<Map<String, dynamic>> _independentTrainers = const [];
  List<Map<String, dynamic>> _pendingInvitations = const [];
  final List<Map<String, dynamic>> _messages = <Map<String, dynamic>>[];
  dynamic _chatMessageHandler;
  int _unreadCount = 0;
  bool _chatThreadOpen = false;
  int _openedChatLaunchVersion = 0;
  bool _openingNotificationChat = false;

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
    if (oldWidget.chatLaunchVersion != widget.chatLaunchVersion) {
      _openNotificationChatIfReady();
    }
    if (oldWidget.userState != widget.userState ||
        oldWidget.selectedGymId != widget.selectedGymId) {
      if (!_hasActiveGymMembership) {
        _trainerResponse = const {};
      }
      _messages.clear();
      _unreadCount = 0;
      unawaited(_load());
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
        final isNewMessage = _upsertMessage(message);
        if (isNewMessage &&
            senderId == trainerId &&
            !_chatThreadOpen &&
            mounted) {
          setState(() => _unreadCount++);
        }
      }
    };
    widget.socket?.on('chat:new_message', _chatMessageHandler);
  }

  int? get _assignedTrainerId {
    if (!_hasActiveGymMembership) return null;
    final assignedTrainer = Map<String, dynamic>.from(
      _effectiveTrainerConnection['assigned_trainer'] as Map? ?? const {},
    );
    return _memberIntValue(
      assignedTrainer['id'] ??
          assignedTrainer['user_id'] ??
          assignedTrainer['trainer_user_id'],
    );
  }

  Map<String, dynamic> get _effectiveTrainerConnection =>
      _trainerResponse.isNotEmpty
      ? _trainerResponse
      : widget.fallbackTrainerConnection;

  bool get _hasActiveGymMembership =>
      widget.userState == 'gym_member' ||
      widget.userState == 'gym_member_with_trainer';

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      if (_hasActiveGymMembership) {
        try {
          final response = await widget.repository.fetchMemberTrainer();
          _trainerResponse = Map<String, dynamic>.from(
            response['data'] as Map? ?? const {},
          );
        } catch (exception) {
          debugPrint('[member-chat][warn] gym trainer: $exception');
          _trainerResponse = const {};
        }
      } else {
        _trainerResponse = const {};
      }
      await _loadIndependentCoaching();
      final trainerId = _assignedTrainerId;
      if (trainerId != null) {
        await _loadChat(trainerId);
      }
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
      _openNotificationChatIfReady();
    }
  }

  Future<void> _loadIndependentCoaching() async {
    try {
      final response = await widget.repository.fetchIndependentTrainers();
      _independentTrainers = _memberRecordsFromResponse(
        response,
        keys: const ['relationships', 'trainers'],
      );
    } catch (exception) {
      debugPrint('[member-chat][warn] independent trainers: $exception');
      _independentTrainers = const [];
    }
    try {
      final response = await widget.repository
          .fetchIndependentTrainerInvitations(status: 'pending');
      _pendingInvitations =
          _memberRecordsFromResponse(
            response,
            keys: const ['invitations'],
          ).where((item) {
            final status = item['status']?.toString().toLowerCase();
            final pending =
                status == null || status.isEmpty || status == 'pending';
            return pending && item['actionable'] != false;
          }).toList();
    } catch (exception) {
      debugPrint('[member-chat][warn] independent invitations: $exception');
      _pendingInvitations = const [];
    }
  }

  Future<void> _respondIndependentInvitation(
    int invitationId,
    bool accept,
  ) async {
    try {
      if (accept) {
        await widget.repository.acceptIndependentTrainerInvitation(
          invitationId,
        );
      } else {
        await widget.repository.rejectIndependentTrainerInvitation(
          invitationId,
        );
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            accept
                ? 'Independent trainer connected. Your gym trainer is unchanged.'
                : 'Trainer invitation declined.',
          ),
        ),
      );
      await _load();
      await widget.onCoachingChanged?.call();
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    }
  }

  Future<void> _endIndependentCoaching(
    Map<String, dynamic> relationship,
  ) async {
    final relationshipId = _memberIntValue(
      relationship['relationship_id'] ?? relationship['id'],
    );
    final trainer = _trainerFromRelationship(relationship);
    final trainerName = trainer['name']?.toString() ?? 'this trainer';
    if (relationshipId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('This coaching connection is invalid.')),
      );
      return;
    }
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: const Text('End independent coaching?'),
            content: Text(
              'You will lose independent plan, progress, and chat access with $trainerName. Your gym, subscription, and gym trainer will not change.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: const Text('Keep coaching'),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                child: const Text('End coaching'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;
    setState(() => _loading = true);
    try {
      await widget.repository.revokeIndependentTrainerRelationship(
        relationshipId,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Independent coaching with $trainerName ended.'),
        ),
      );
      await _load();
      await widget.onCoachingChanged?.call();
    } catch (exception) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    }
  }

  Future<void> _removeGymTrainerAssignment() async {
    if (!_hasActiveGymMembership || _assignedTrainerId == null) return;
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: const Text('Remove gym trainer?'),
            content: Text(
              'This removes only the trainer assigned through ${widget.selectedGymName}. Your membership, other gym trainers, and every independent trainer connection stay unchanged.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: const Text('Keep trainer'),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                child: const Text('Remove trainer'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;
    setState(() => _loading = true);
    try {
      await widget.repository.removeGymTrainerAssignment();
      _trainerResponse = const {'enabled': false, 'assigned_trainer': null};
      _messages.clear();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${widget.selectedGymName} trainer removed. Your other gym and independent trainer relationships are unchanged.',
          ),
        ),
      );
      await _load();
      await widget.onCoachingChanged?.call();
    } catch (exception) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    }
  }

  void _openNotificationChatIfReady() {
    if (!mounted ||
        _loading ||
        _openingNotificationChat ||
        widget.chatLaunchVersion <= _openedChatLaunchVersion) {
      return;
    }

    final assignedTrainer = Map<String, dynamic>.from(
      _effectiveTrainerConnection['assigned_trainer'] as Map? ?? const {},
    );
    final targetTrainerId = widget.chatTargetTrainerId;
    Map<String, dynamic> target = assignedTrainer;
    if (targetTrainerId != null && _assignedTrainerId != targetTrainerId) {
      target = _independentTrainers.firstWhere((relationship) {
        final trainer = _trainerFromRelationship(relationship);
        return _memberIntValue(trainer['id'] ?? trainer['user_id']) ==
            targetTrainerId;
      }, orElse: () => const <String, dynamic>{});
    }
    if (target.isEmpty) {
      return;
    }

    _openedChatLaunchVersion = widget.chatLaunchVersion;
    _openingNotificationChat = true;
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) {
        return;
      }
      await _openTrainerChatThread(target);
      _openingNotificationChat = false;
    });
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
      final responses = await Future.wait([
        widget.repository.fetchChatMessages(trainerId),
        widget.repository.fetchChatConversations(),
      ]);
      final response = responses[0];
      final conversations = (responses[1]['data'] as List<dynamic>? ?? const [])
          .map(_trainerRecordMap)
          .toList();
      final conversation = conversations
          .cast<Map<String, dynamic>?>()
          .firstWhere(
            (item) =>
                _memberIntValue(item?['trainer_id']) == trainerId ||
                _memberIntValue(_trainerRecordMap(item?['peer'])['id']) ==
                    trainerId,
            orElse: () => null,
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
          _unreadCount = _chatThreadOpen
              ? 0
              : (_memberIntValue(conversation?['unread_count']) ?? 0);
        });
      }
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

  bool _upsertMessage(Map<String, dynamic> message) {
    if (!mounted) {
      return false;
    }
    final normalized = _normalizeMemberChatMessage(message);
    final key = _memberChatKey(normalized);
    final clientId = normalized['client_message_id']?.toString();
    final alreadyPresent = _messages.any((item) {
      final sameKey = _memberChatKey(item) == key;
      final sameClient =
          clientId != null &&
          clientId.isNotEmpty &&
          item['client_message_id']?.toString() == clientId;
      return sameKey || sameClient;
    });
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
    return !alreadyPresent;
  }

  Future<void> _openTrainerChatThread(Map<String, dynamic> trainer) async {
    final trainerRecord = _trainerFromRelationship(trainer);
    final trainerId = _memberIntValue(
      trainerRecord['id'] ??
          trainerRecord['user_id'] ??
          trainerRecord['trainer_user_id'],
    );
    if (trainerId == null) {
      return;
    }

    setState(() {
      _chatThreadOpen = true;
      _unreadCount = 0;
    });

    try {
      await Navigator.of(context).push<void>(
        MaterialPageRoute(
          builder: (_) => _MemberTrainerChatThreadScreen(
            repository: widget.repository,
            socket: widget.socket,
            trainerId: trainerId,
            trainer: trainerRecord,
          ),
        ),
      );
    } finally {
      try {
        await widget.repository.markChatRead(trainerId);
      } catch (_) {
        // Keep navigation responsive; opening the thread again retries the read.
      }

      if (mounted) {
        setState(() => _chatThreadOpen = false);
        await _load();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final assignedTrainer = Map<String, dynamic>.from(
      _effectiveTrainerConnection['assigned_trainer'] as Map? ?? const {},
    );
    final hasTrainer = _assignedTrainerId != null;
    final trainerId = _assignedTrainerId;
    final trainerName = assignedTrainer['name']?.toString() ?? 'Your trainer';
    final trainerAvatarUrl =
        assignedTrainer['profile_photo_url']?.toString() ??
        assignedTrainer['avatar']?.toString() ??
        assignedTrainer['photo']?.toString();
    final lastMessage = _messages.isEmpty ? null : _messages.last;
    final preview = lastMessage == null
        ? 'Tap to open private thread'
        : lastMessage['body']?.toString() ?? 'Message';
    return AppGradientScaffold(
      title: 'Chats',
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
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Messages',
                              style: Theme.of(context).textTheme.headlineSmall
                                  ?.copyWith(
                                    color: AppColors.textPrimary,
                                    fontWeight: FontWeight.w900,
                                  ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              'Private coaching conversations',
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: AppColors.textSecondary,
                                    fontWeight: FontWeight.w600,
                                  ),
                            ),
                          ],
                        ),
                      ),
                      _MemberChatSquareButton(
                        icon: _loading
                            ? Icons.sync_rounded
                            : Icons.refresh_rounded,
                        onTap: _loading ? null : _load,
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if (_chatLoading)
                    const Padding(
                      padding: EdgeInsets.only(bottom: 12),
                      child: LinearProgressIndicator(
                        minHeight: 3,
                        backgroundColor: AppColors.surfaceSoft,
                        color: AppColors.primaryBright,
                      ),
                    ),
                  if (_pendingInvitations.isNotEmpty) ...[
                    Text(
                      'Coaching invitations',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 10),
                    ..._pendingInvitations.map((invitation) {
                      final trainer = _trainerFromRelationship(invitation);
                      final invitationId = _memberIntValue(invitation['id']);
                      final expired = _independentInvitationExpired(invitation);
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _IndependentInvitationCard(
                          trainer: trainer,
                          expired: expired,
                          onAccept: invitationId == null || expired
                              ? null
                              : () => _respondIndependentInvitation(
                                  invitationId,
                                  true,
                                ),
                          onReject: invitationId == null || expired
                              ? null
                              : () => _respondIndependentInvitation(
                                  invitationId,
                                  false,
                                ),
                        ),
                      );
                    }),
                    const SizedBox(height: 8),
                  ],
                  if (hasTrainer) ...[
                    Text(
                      'Gym trainer · ${widget.selectedGymName}',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 10),
                    RevealOnBuild(
                      child: _MemberConversationCard(
                        trainerName: trainerName,
                        trainerAvatarUrl: trainerAvatarUrl,
                        preview: preview,
                        time: lastMessage == null
                            ? 'New'
                            : _memberChatTime(lastMessage['created_at']),
                        enabled: trainerId != null,
                        unreadCount: _unreadCount,
                        loading: _chatLoading,
                        onMore: trainerId == null
                            ? null
                            : _removeGymTrainerAssignment,
                        onTap: trainerId == null
                            ? null
                            : () => _openTrainerChatThread(assignedTrainer),
                      ),
                    ),
                    if (_chatError != null) ...[
                      const SizedBox(height: 10),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.error.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(
                            color: AppColors.error.withValues(alpha: 0.14),
                          ),
                        ),
                        child: Text(
                          _chatError!,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: AppColors.error),
                        ),
                      ),
                    ],
                  ],
                  if (_independentTrainers.isNotEmpty) ...[
                    const SizedBox(height: 18),
                    Text(
                      'Independent trainers',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Separate from your gym membership and gym-assigned trainer.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 10),
                    ..._independentTrainers.map((relationship) {
                      final trainer = _trainerFromRelationship(relationship);
                      final trainerId = _memberIntValue(
                        trainer['id'] ?? trainer['user_id'],
                      );
                      final accessActive =
                          relationship['access_active'] != false;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _MemberConversationCard(
                          trainerName:
                              trainer['name']?.toString() ?? 'Verified trainer',
                          trainerAvatarUrl:
                              trainer['profile_photo_url']?.toString() ??
                              trainer['avatar']?.toString(),
                          preview: accessActive
                              ? 'Independent coaching · tap to message'
                              : 'Coaching access paused · manage connection',
                          time: accessActive ? 'Verified' : 'Paused',
                          enabled: trainerId != null && accessActive,
                          onMore: () => _endIndependentCoaching(relationship),
                          onTap: trainerId == null || !accessActive
                              ? null
                              : () => _openTrainerChatThread(relationship),
                        ),
                      );
                    }),
                  ],
                  if (!hasTrainer && _independentTrainers.isEmpty) ...[
                    RevealOnBuild(
                      child: _MemberChatNoTrainerCard(onRefresh: _load),
                    ),
                  ],
                ],
              ),
            ),
    );
  }
}

List<Map<String, dynamic>> _memberRecordsFromResponse(
  Map<String, dynamic> response, {
  required List<String> keys,
}) {
  final data = response['data'];
  if (data is List) {
    return data.whereType<Map>().map(_trainerRecordMap).toList();
  }
  final envelope = _trainerRecordMap(data);
  for (final key in <String>['data', ...keys]) {
    final records = envelope[key];
    if (records is List) {
      return records.whereType<Map>().map(_trainerRecordMap).toList();
    }
  }
  return const [];
}

Map<String, dynamic> _trainerFromRelationship(Map<String, dynamic> record) {
  final trainer = _trainerRecordMap(record['trainer']);
  if (trainer.isNotEmpty) return trainer;
  final user = _trainerRecordMap(record['trainer_user'] ?? record['user']);
  if (user.isNotEmpty) return user;
  return record;
}

bool _independentInvitationExpired(Map<String, dynamic> invitation) {
  final expiresAt = DateTime.tryParse(
    invitation['expires_at']?.toString() ?? '',
  );
  return expiresAt != null &&
      expiresAt.toUtc().isBefore(DateTime.now().toUtc());
}

class _IndependentInvitationCard extends StatelessWidget {
  const _IndependentInvitationCard({
    required this.trainer,
    required this.onAccept,
    required this.onReject,
    required this.expired,
  });

  final Map<String, dynamic> trainer;
  final VoidCallback? onAccept;
  final VoidCallback? onReject;
  final bool expired;

  @override
  Widget build(BuildContext context) {
    final name = trainer['name']?.toString() ?? 'Verified trainer';
    return PremiumCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.verified_user_outlined,
                color: AppColors.primaryBright,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  '$name invited you',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            expired
                ? 'This invitation has expired. Ask the trainer to send a fresh consent request.'
                : 'Accepting creates independent coaching access. It does not change your gym, subscription, or gym trainer.',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              if (expired)
                const Chip(label: Text('Expired'))
              else ...[
                TextButton(onPressed: onReject, child: const Text('Decline')),
                const SizedBox(width: 8),
                FilledButton(onPressed: onAccept, child: const Text('Accept')),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _MemberChatSquareButton extends StatelessWidget {
  const _MemberChatSquareButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(15),
        onTap: onTap,
        child: Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: AppColors.stroke),
          ),
          child: Icon(icon, color: AppColors.primaryBright, size: 21),
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
    return PremiumCard(
      padding: const EdgeInsets.all(22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: AppColors.stroke),
            ),
            child: const Icon(
              Icons.person_search_rounded,
              color: AppColors.primaryBright,
            ),
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
            'A gym can assign its trainer, or a verified independent trainer can invite you. Both relationships stay separate.',
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
                color: AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.stroke),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(
                    Icons.refresh_rounded,
                    color: AppColors.primaryBright,
                    size: 18,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Check connections',
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: AppColors.textPrimary,
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
    this.onMore,
  });

  final String trainerName;
  final String preview;
  final String time;
  final bool enabled;
  final VoidCallback? onTap;
  final String? trainerAvatarUrl;
  final int unreadCount;
  final bool loading;
  final VoidCallback? onMore;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(10),
      onTap: onTap,
      child: Row(
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              Container(
                padding: const EdgeInsets.all(3),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.stroke),
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
                    color: enabled ? AppColors.success : AppColors.textMuted,
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
                          ? AppColors.primaryBright
                          : AppColors.textMuted,
                      size: 15,
                    ),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        loading ? 'Syncing latest messages...' : preview,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
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
          if (onMore != null)
            IconButton(
              tooltip: 'Manage coaching connection',
              onPressed: onMore,
              icon: const Icon(Icons.more_vert_rounded),
            ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                time,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: unreadCount > 0
                      ? AppColors.primaryBright
                      : AppColors.textMuted,
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
                      ? AppColors.primaryBright
                      : AppColors.surfaceSoft,
                  shape: unreadCount > 0 ? BoxShape.circle : BoxShape.rectangle,
                  borderRadius: unreadCount > 0
                      ? null
                      : BorderRadius.circular(999),
                  border: unreadCount > 0
                      ? null
                      : Border.all(color: AppColors.stroke),
                ),
                child: unreadCount > 0
                    ? Text(
                        '$unreadCount',
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                        ),
                      )
                    : Icon(
                        enabled
                            ? Icons.chevron_right_rounded
                            : Icons.lock_outline_rounded,
                        size: 18,
                        color: enabled
                            ? AppColors.primaryBright
                            : AppColors.textMuted,
                      ),
              ),
            ],
          ),
        ],
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
    extends State<_MemberTrainerChatThreadScreen>
    with WidgetsBindingObserver {
  final TextEditingController _controller = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final List<Map<String, dynamic>> _messages = <Map<String, dynamic>>[];
  bool _loading = true;
  bool _loadingOlder = false;
  bool _hasOlderMessages = false;
  bool _sending = false;
  bool _termsAccepted = false;
  bool _blockedByMe = false;
  bool _blockedMe = false;
  bool _safetyBusy = false;
  String? _error;
  int? _nextBeforeId;
  dynamic _chatMessageHandler;
  dynamic _chatReadHandler;
  dynamic _chatConnectHandler;
  bool _appIsResumed = true;

  @override
  void initState() {
    super.initState();
    final lifecycleState = WidgetsBinding.instance.lifecycleState;
    _appIsResumed =
        lifecycleState == null || lifecycleState == AppLifecycleState.resumed;
    _chatMessageHandler = _handleSocketMessage;
    _chatReadHandler = _handleSocketRead;
    _chatConnectHandler = (_) => _setChatFocus(_appIsResumed);
    WidgetsBinding.instance.addObserver(this);
    widget.socket?.on('chat:new_message', _chatMessageHandler);
    widget.socket?.on('chat:read_receipt', _chatReadHandler);
    widget.socket?.on('connect', _chatConnectHandler);
    _setChatFocus(true);
    _load();
  }

  @override
  void dispose() {
    _setChatFocus(false);
    WidgetsBinding.instance.removeObserver(this);
    if (_chatMessageHandler != null) {
      widget.socket?.off('chat:new_message', _chatMessageHandler);
    }
    if (_chatReadHandler != null) {
      widget.socket?.off('chat:read_receipt', _chatReadHandler);
    }
    if (_chatConnectHandler != null) {
      widget.socket?.off('connect', _chatConnectHandler);
    }
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _appIsResumed = state == AppLifecycleState.resumed;
    _setChatFocus(_appIsResumed);
  }

  @override
  void didChangeMetrics() {
    _scrollToLatest();
  }

  void _scrollToLatest() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_scrollController.hasClients) {
        return;
      }
      _scrollController.jumpTo(_scrollController.position.maxScrollExtent);
    });
  }

  void _setChatFocus(bool active) {
    if (widget.socket?.connected != true) {
      return;
    }
    widget.socket!.emit('chat:focus', {
      'recipientId': widget.trainerId,
      'active': active,
    });
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
      if (senderId == widget.trainerId) {
        widget.repository.markChatRead(widget.trainerId);
      }
    }
  }

  void _handleSocketRead(dynamic data) {
    if (!mounted) {
      return;
    }

    final receipt = _trainerRecordMap(data);
    if (_memberIntValue(receipt['userId'] ?? receipt['user_id']) !=
        widget.trainerId) {
      return;
    }

    final messageIds = (receipt['messageIds'] ?? receipt['message_ids']);
    if (messageIds is! List) {
      return;
    }

    final ids = messageIds.map((id) => id.toString()).toSet();
    final readAt =
        receipt['readAt']?.toString() ??
        receipt['read_at']?.toString() ??
        DateTime.now().toIso8601String();
    setState(() {
      for (var index = 0; index < _messages.length; index++) {
        if (ids.contains(_messages[index]['id']?.toString())) {
          _messages[index] = {..._messages[index], 'read_at': readAt};
        }
      }
    });
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
      final safetyResponse = await widget.repository.fetchChatSafety(
        widget.trainerId,
      );
      final safety = _trainerRecordMap(safetyResponse['data']);
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
          _termsAccepted = safety['terms_accepted'] == true;
          _blockedByMe = safety['blocked_by_me'] == true;
          _blockedMe = safety['blocked_me'] == true;
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
        _scrollToLatest();
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
    if (body.isEmpty ||
        _sending ||
        !_termsAccepted ||
        _blockedByMe ||
        _blockedMe) {
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

  Future<void> _acceptTerms() async {
    if (_safetyBusy) return;
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Chat terms'),
        content: const Text(
          'Use chat only for respectful fitness coaching. Harassment, threats, '
          'sexual or exploitative content, spam, impersonation, unlawful '
          'content, and privacy violations are prohibited. Reports may be '
          'reviewed and accounts may be restricted.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('I agree'),
          ),
        ],
      ),
    );
    if (accepted != true || !mounted) return;
    setState(() => _safetyBusy = true);
    try {
      await widget.repository.acceptChatTerms();
      if (mounted) setState(() => _termsAccepted = true);
    } catch (exception) {
      if (mounted) setState(() => _error = exception.toString());
    } finally {
      if (mounted) setState(() => _safetyBusy = false);
    }
  }

  Future<void> _reportConversation() async {
    final reason = await showDialog<String>(
      context: context,
      builder: (dialogContext) => SimpleDialog(
        title: const Text('Report this conversation'),
        children: [
          SimpleDialogOption(
            onPressed: () => Navigator.pop(dialogContext, 'harassment'),
            child: const Text('Harassment or bullying'),
          ),
          SimpleDialogOption(
            onPressed: () =>
                Navigator.pop(dialogContext, 'inappropriate_content'),
            child: const Text('Inappropriate content'),
          ),
          SimpleDialogOption(
            onPressed: () => Navigator.pop(dialogContext, 'spam'),
            child: const Text('Spam'),
          ),
          SimpleDialogOption(
            onPressed: () => Navigator.pop(dialogContext, 'safety_concern'),
            child: const Text('Safety concern'),
          ),
          SimpleDialogOption(
            onPressed: () => Navigator.pop(dialogContext, 'other'),
            child: const Text('Other'),
          ),
        ],
      ),
    );
    if (reason == null || !mounted) return;
    setState(() => _safetyBusy = true);
    try {
      await widget.repository.reportChatUser(widget.trainerId, reason: reason);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Report submitted for review.')),
        );
      }
    } catch (exception) {
      if (mounted) setState(() => _error = exception.toString());
    } finally {
      if (mounted) setState(() => _safetyBusy = false);
    }
  }

  Future<void> _toggleBlock() async {
    if (_safetyBusy) return;
    setState(() => _safetyBusy = true);
    try {
      if (_blockedByMe) {
        await widget.repository.unblockChatUser(widget.trainerId);
      } else {
        await widget.repository.blockChatUser(widget.trainerId);
      }
      if (mounted) setState(() => _blockedByMe = !_blockedByMe);
    } catch (exception) {
      if (mounted) setState(() => _error = exception.toString());
    } finally {
      if (mounted) setState(() => _safetyBusy = false);
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
    _scrollToLatest();
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
      backgroundColor: AppColors.background,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _MemberChatThreadHeader(
              trainerName: trainerName,
              trainerAvatarUrl: avatarUrl,
              loading: _loading,
              busy: _safetyBusy,
              blockedByMe: _blockedByMe,
              onRefresh: _load,
              onReport: _reportConversation,
              onToggleBlock: _toggleBlock,
            ),
            _MemberChatSafetyBar(
              termsAccepted: _termsAccepted,
              blockedByMe: _blockedByMe,
              blockedMe: _blockedMe,
              busy: _safetyBusy,
              onAcceptTerms: _acceptTerms,
              onToggleBlock: _toggleBlock,
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
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
                      controller: _scrollController,
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
            if (_termsAccepted && !_blockedByMe && !_blockedMe)
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

class _MemberChatSafetyBar extends StatelessWidget {
  const _MemberChatSafetyBar({
    required this.termsAccepted,
    required this.blockedByMe,
    required this.blockedMe,
    required this.busy,
    required this.onAcceptTerms,
    required this.onToggleBlock,
  });

  final bool termsAccepted;
  final bool blockedByMe;
  final bool blockedMe;
  final bool busy;
  final VoidCallback onAcceptTerms;
  final VoidCallback onToggleBlock;

  @override
  Widget build(BuildContext context) {
    final blocked = blockedByMe || blockedMe;
    if (termsAccepted && !blocked) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.stroke),
        ),
        child: Row(
          children: [
            Icon(
              blocked ? Icons.block_rounded : Icons.lock_outline_rounded,
              size: 18,
              color: blocked ? AppColors.error : AppColors.primaryBright,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                !termsAccepted
                    ? 'Accept respectful-use terms to start messaging.'
                    : blockedByMe
                    ? 'You blocked this conversation.'
                    : 'Messaging is unavailable for this conversation.',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            if (!termsAccepted)
              TextButton(
                onPressed: busy ? null : onAcceptTerms,
                child: const Text('Accept'),
              )
            else if (blockedByMe)
              TextButton(
                onPressed: busy ? null : onToggleBlock,
                child: const Text('Unblock'),
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
    required this.busy,
    required this.blockedByMe,
    required this.onRefresh,
    required this.onReport,
    required this.onToggleBlock,
    this.trainerAvatarUrl,
  });

  final String trainerName;
  final String? trainerAvatarUrl;
  final bool loading;
  final bool busy;
  final bool blockedByMe;
  final VoidCallback onRefresh;
  final VoidCallback onReport;
  final VoidCallback onToggleBlock;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      padding: const EdgeInsets.fromLTRB(6, 10, 8, 10),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.stroke),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.of(context).pop(),
            icon: const Icon(
              Icons.arrow_back_rounded,
              color: AppColors.textPrimary,
            ),
          ),
          CircleAvatar(
            radius: 24,
            backgroundColor: AppColors.surfaceSoft,
            backgroundImage:
                trainerAvatarUrl != null && trainerAvatarUrl!.trim().isNotEmpty
                ? NetworkImage(trainerAvatarUrl!)
                : null,
            child: trainerAvatarUrl == null || trainerAvatarUrl!.trim().isEmpty
                ? Text(
                    trainerName.trim().isEmpty ? 'T' : trainerName.trim()[0],
                    style: const TextStyle(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  )
                : null,
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
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  blockedByMe
                      ? 'Conversation blocked'
                      : loading
                      ? 'Syncing chat...'
                      : 'Trainer conversation',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: blockedByMe
                        ? AppColors.error
                        : AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: loading ? null : onRefresh,
            icon: Icon(
              Icons.refresh_rounded,
              color: loading
                  ? AppColors.textMuted.withValues(alpha: 0.4)
                  : AppColors.primaryBright,
            ),
          ),
          PopupMenuButton<String>(
            enabled: !busy,
            tooltip: 'Conversation options',
            icon: busy
                ? const SizedBox.square(
                    dimension: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(
                    Icons.more_horiz_rounded,
                    color: AppColors.textSecondary,
                  ),
            onSelected: (value) {
              if (value == 'report') {
                onReport();
              } else if (value == 'block') {
                onToggleBlock();
              }
            },
            itemBuilder: (context) => [
              const PopupMenuItem(
                value: 'report',
                child: Row(
                  children: [
                    Icon(Icons.flag_outlined, size: 19),
                    SizedBox(width: 10),
                    Text('Report conversation'),
                  ],
                ),
              ),
              PopupMenuItem(
                value: 'block',
                child: Row(
                  children: [
                    Icon(
                      blockedByMe
                          ? Icons.lock_open_rounded
                          : Icons.block_rounded,
                      size: 19,
                    ),
                    const SizedBox(width: 10),
                    Text(blockedByMe ? 'Unblock trainer' : 'Block trainer'),
                  ],
                ),
              ),
            ],
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
      decoration: const BoxDecoration(color: AppColors.background),
      child: Stack(
        children: [
          Positioned(
            right: -42,
            top: 46,
            child: _MemberChatSoftOrb(
              color: AppColors.primary.withValues(alpha: 0.08),
              size: 150,
            ),
          ),
          Positioned(
            left: -55,
            bottom: 90,
            child: _MemberChatSoftOrb(
              color: AppColors.primaryBright.withValues(alpha: 0.06),
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
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke),
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
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(999),
          onTap: loading ? null : onPressed,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  loading ? Icons.sync_rounded : Icons.history_rounded,
                  size: 16,
                  color: AppColors.primaryBright,
                ),
                const SizedBox(width: 8),
                Text(
                  loading ? 'Loading older messages' : 'Load older messages',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w700,
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

class _MemberChatEmptyState extends StatelessWidget {
  const _MemberChatEmptyState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: PremiumCard(
          padding: const EdgeInsets.all(22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 68,
                height: 68,
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: const Icon(
                  Icons.chat_bubble_outline_rounded,
                  color: AppColors.primaryBright,
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
        decoration: const BoxDecoration(color: AppColors.background),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: TextField(
                  controller: controller,
                  minLines: 1,
                  maxLines: 5,
                  enabled: !sending,
                  textCapitalization: TextCapitalization.sentences,
                  textInputAction: TextInputAction.send,
                  decoration: InputDecoration(
                    hintText: 'Message your trainer',
                    prefixIcon: const Icon(
                      Icons.lock_outline_rounded,
                      color: AppColors.textMuted,
                    ),
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
                color: AppColors.primaryBright,
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primaryBright.withValues(alpha: 0.22),
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
          maxWidth: MediaQuery.sizeOf(context).width * 0.76,
        ),
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 10, 12, 8),
          decoration: BoxDecoration(
            color: isIncoming ? AppColors.surface : AppColors.primaryBright,
            borderRadius: BorderRadius.only(
              topLeft: Radius.circular(isIncoming ? 8 : 18),
              topRight: Radius.circular(isIncoming ? 18 : 8),
              bottomLeft: const Radius.circular(18),
              bottomRight: const Radius.circular(18),
            ),
            border: isIncoming ? Border.all(color: AppColors.stroke) : null,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.04),
                blurRadius: 8,
                offset: const Offset(0, 4),
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
                  color: isIncoming ? AppColors.textPrimary : Colors.white,
                  fontWeight: FontWeight.w600,
                  height: 1.34,
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
                          ? AppColors.textMuted
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
                          ? AppColors.primaryBright
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
