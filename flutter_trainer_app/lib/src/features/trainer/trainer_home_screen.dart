import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:socket_io_client/socket_io_client.dart' as io;

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../core/config.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/confirmation_dialog.dart';
import '../../../core/widgets/premium_card.dart';
import '../auth/session_controller.dart';
import 'socket_service.dart';
import 'trainer_member_detail_screen.dart';
import 'trainer_onboarding_flow.dart';
import 'trainer_profile_screen.dart';
import 'trainer_repository.dart';
import 'trainer_settings_screen.dart';
import 'trainer_tasks_screen.dart';

class TrainerHomeScreen extends StatefulWidget {
  const TrainerHomeScreen({super.key});

  @override
  State<TrainerHomeScreen> createState() => _TrainerHomeScreenState();
}

class _TrainerHomeScreenState extends State<TrainerHomeScreen> {
  late TrainerRepository _repository;
  final TrainerSocketService _socketService = TrainerSocketService();
  io.Socket? _socket;
  int _index = 0;
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _contextData = const {};
  Map<String, dynamic> _tasks = const {};
  List<Map<String, dynamic>> _members = const [];
  List<Map<String, dynamic>> _todayClients = const [];
  List<Map<String, dynamic>> _followUps = const [];
  List<Map<String, dynamic>> _templates = const [];
  List<Map<String, dynamic>> _plans = const [];
  List<Map<String, dynamic>> _notifications = const [];
  List<Map<String, dynamic>> _trialRequests = const [];
  List<Map<String, dynamic>> _exercises = const [];
  List<Map<String, dynamic>> _chatConversations = const [];
  String? _chatError;
  int? _workoutFocusMemberId;

  @override
  void initState() {
    super.initState();
    final session = context.read<TrainerSessionController>();
    _repository = TrainerRepository(session.client);
    scheduleMicrotask(_bootstrap);
  }

  Future<void> _bootstrap() async {
    final session = context.read<TrainerSessionController>();
    await _load();
    if (!mounted) {
      return;
    }
    if (session.token != null) {
      _socket = _socketService.connect(session.token!);
      _socket?.on('chat:new_message', (data) {
        if (!mounted) {
          return;
        }
        final message = _normalizeChatMessage(_map(data)['message'] ?? data);
        if ((message['body']?.toString() ?? '').isEmpty) {
          return;
        }
        _upsertChatConversationFromMessage(message);
      });
      _socket?.on('notification:new', (data) {
        if (!mounted) {
          return;
        }
        setState(
          () => _notifications = [
            Map<String, dynamic>.from(data as Map? ?? const {}),
            ..._notifications,
          ],
        );
      });
    }
  }

  Future<void> _load() async {
    if (!mounted) {
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final results = await Future.wait([
        _repository.fetchContext(),
        _repository.fetchAssignedMembers(),
        _repository.fetchTodayClients(),
        _repository.fetchWorkoutTemplates(),
        _repository.fetchWorkoutPlans(),
        _repository.fetchNotifications(),
        _repository.fetchExercises(),
        _repository.fetchTrialRequests(),
      ]);
      Map<String, dynamic> tasks = const {};
      List<Map<String, dynamic>> followUps = const [];
      List<Map<String, dynamic>> chatConversations = const [];
      try {
        final response = await _repository.fetchTasks();
        tasks = _map(response['data']);
      } catch (_) {
        tasks = const {};
      }
      try {
        final response = await _repository.fetchPendingFollowUps();
        followUps = _mapList(response['data']);
      } catch (exception) {
        final message = exception.toString().toLowerCase();
        if (!(message.contains('404') ||
            message.contains('not found') ||
            message.contains('endpoint'))) {
          rethrow;
        }
        followUps = const [];
      }
      try {
        final response = await _repository.fetchChatConversations();
        chatConversations = _mapList(response['data']);
      } catch (_) {
        chatConversations = const [];
      }
      if (!mounted) {
        return;
      }
      _contextData = _normalizeTrainerContext(_map(results[0]['data']));
      _members = _mapList(results[1]['data']);
      _todayClients = _mapList(results[2]['data']);
      _templates = _mapList(results[3]['data']);
      _plans = _mapList(results[4]['data']);
      _notifications = _mapList(results[5]['data']);
      _exercises = _mapList(results[6]['data']);
      _trialRequests = _mapList(results[7]['data']);
      _tasks = tasks;
      _followUps = followUps;
      _chatConversations = chatConversations;
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _openProfileEditSheet() async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (context) => TrainerProfileScreen(repository: _repository),
      ),
    );

    if (mounted) {
      await _load();
    }
  }

  Future<void> _openSettingsScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => TrainerSettingsScreen(repository: _repository),
      ),
    );
    if (mounted) {
      await _load();
    }
  }

  @override
  void dispose() {
    _socketService.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<TrainerSessionController>();
    final user = session.user;
    if (user == null) {
      return const SizedBox.shrink();
    }
    final contextUser = Map<String, dynamic>.from(
      _contextData['user'] as Map? ?? const {},
    );
    final onboardingCompleted =
        (contextUser['trainer_onboarding_completed'] as bool?) ?? false;

    final pages = <Widget>[
      _DashboardPage(
        contextData: _contextData,
        tasks: _tasks,
        todayClients: _todayClients,
        followUps: _followUps,
        members: _members,
        plans: _plans,
        notifications: _notifications,
        onRefresh: _load,
        onEditProfile: _openProfileEditSheet,
        onOpenMembers: () => setState(() => _index = 1),
        onOpenWorkouts: () => setState(() => _index = 2),
        onOpenNotifications: () => setState(() => _index = 4),
        onOpenSettings: _openSettingsScreen,
        onOpenTasks: _openTasksScreen,
        onAddNote: () {
          if (_members.isEmpty) {
            _openTasksScreen();
            return;
          }
          _openQuickNoteSheet(_members.first);
        },
      ),
      _MemberPage(
        members: _members,
        templates: _templates,
        plans: _plans,
        onRefresh: _load,
        onOpenMember: _openMemberDetailSheet,
        onQuickNote: _openQuickNoteSheet,
        onQuickAssign: _openQuickAssignSheet,
        onManageWorkouts: _openWorkoutManagerForMember,
        onSendMessage: _openChatWithMember,
        onAddFollowUp: _openQuickNoteSheet,
      ),
      _WorkoutPage(
        contextData: _contextData,
        members: _members,
        templates: _templates,
        plans: _plans,
        exercises: _exercises,
        repository: _repository,
        initialMemberId: _workoutFocusMemberId,
        onRefresh: _load,
      ),
      _ChatPage(
        members: _members,
        conversations: _chatConversations,
        error: _chatError,
        loading: _loading,
        onSelectMember: (value) {
          if (value != null) {
            unawaited(_openTrainerChatThread(value));
          }
        },
        onRefresh: _load,
      ),
      _NotificationPage(
        notifications: _notifications,
        trialRequests: _trialRequests,
        members: _members,
        onMarkRead: (notificationId) async {
          await _repository.markNotificationRead(notificationId);
          await _load();
        },
        onMarkAllRead: () async {
          await _repository.markAllNotificationsRead();
          await _load();
        },
        onUpdateTrial: (trialRequestId, status) async {
          await _repository.updateTrialRequest(trialRequestId, {
            'status': status,
          });
          await _load();
        },
        onCreateAnnouncement: (payload) async {
          await _repository.createAnnouncement(payload);
          await _load();
        },
      ),
    ];

    return AppGradientScaffold(
      title: _pageTitle(_index, user.name),
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 240),
        transitionBuilder: (child, animation) {
          final curved = CurvedAnimation(
            parent: animation,
            curve: Curves.easeOutCubic,
            reverseCurve: Curves.easeInCubic,
          );

          return FadeTransition(
            opacity: curved,
            child: SlideTransition(
              position: Tween<Offset>(
                begin: const Offset(0.03, 0.015),
                end: Offset.zero,
              ).animate(curved),
              child: child,
            ),
          );
        },
        child: _loading
            ? const _TrainerHomeSkeleton(key: ValueKey('trainer-loading'))
            : _error != null
            ? ErrorStateView(
                key: const ValueKey('trainer-error'),
                message: _error!,
                onRetry: _load,
              )
            : !onboardingCompleted
            ? KeyedSubtree(
                key: const ValueKey('trainer-onboarding'),
                child: TrainerOnboardingFlow(
                  repository: _repository,
                  contextData: _contextData,
                  onFinished: () async {
                    await _load();
                    if (mounted) {
                      setState(() => _index = 0);
                    }
                  },
                ),
              )
            : KeyedSubtree(
                key: ValueKey('trainer-page-$_index'),
                child: pages[_index],
              ),
      ),
      bottomNavigationBar: onboardingCompleted
          ? _TrainerBottomNav(
              currentIndex: _index,
              onSelect: (value) => setState(() => _index = value),
            )
          : null,
    );
  }

  String _pageTitle(int index, String userName) {
    switch (index) {
      case 0:
        return 'Trainer Dashboard';
      case 1:
        return 'Assigned Members';
      case 2:
        return 'Workout Builder';
      case 3:
        return 'Trainer Chat';
      case 4:
        return 'Notifications';
      default:
        return 'Trainer $userName';
    }
  }

  int? get _currentTrainerUserId {
    final id = _map(_contextData['user'])['id'];
    if (id is num) {
      return id.toInt();
    }
    return int.tryParse(id?.toString() ?? '');
  }

  Future<void> _openTrainerChatThread(int memberId) async {
    Map<String, dynamic> selectedAssignment = const {};
    for (final assignment in _members) {
      if ((assignment['member_id'] as num?)?.toInt() == memberId) {
        selectedAssignment = assignment;
        break;
      }
    }

    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => _TrainerChatThreadScreen(
          repository: _repository,
          socket: _socket,
          currentUserId: _currentTrainerUserId,
          memberId: memberId,
          member: _map(selectedAssignment['member']),
        ),
      ),
    );

    if (mounted) {
      await _load();
    }
  }

  Map<String, dynamic> _normalizeChatMessage(dynamic value) {
    final map = _map(value);
    final clientId =
        map['client_message_id']?.toString() ??
        map['clientMessageId']?.toString();
    return <String, dynamic>{
      'id': map['id']?.toString() ?? clientId ?? UniqueKey().toString(),
      'room': map['room']?.toString(),
      'sender_id': _intValue(map['sender_id'] ?? map['senderId']),
      'recipient_id': _intValue(map['recipient_id'] ?? map['recipientId']),
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

  int? _chatPeerId(Map<String, dynamic> message) {
    final senderId = _intValue(message['sender_id']);
    final recipientId = _intValue(message['recipient_id']);
    final currentUserId = _currentTrainerUserId;
    if (senderId == currentUserId) {
      return recipientId;
    }
    return senderId;
  }

  void _upsertChatConversationFromMessage(Map<String, dynamic> message) {
    final peerId = _chatPeerId(message);
    if (peerId == null || !mounted) {
      return;
    }

    setState(() {
      final conversations = _chatConversations
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
      final index = conversations.indexWhere((item) {
        final peer = _map(item['peer']);
        final memberId = _intValue(item['member_id']);
        return _intValue(peer['id']) == peerId || memberId == peerId;
      });

      if (index >= 0) {
        final current = conversations.removeAt(index);
        final normalized = _normalizeChatMessage(message);
        final currentLast = _normalizeChatMessage(current['last_message']);
        final sameLastMessage =
            _chatMessageKey(currentLast) == _chatMessageKey(normalized);
        final isIncoming =
            _intValue(normalized['recipient_id']) == _currentTrainerUserId;
        conversations.insert(0, {
          ...current,
          'last_message': normalized,
          'unread_count': isIncoming && !sameLastMessage
              ? (_intValue(current['unread_count']) ?? 0) + 1
              : (_intValue(current['unread_count']) ?? 0),
          'updated_at': normalized['created_at'],
        });
      }
      _chatConversations = conversations;
    });
  }

  Future<void> _openMemberDetailSheet(Map<String, dynamic> assignment) async {
    final memberId = (assignment['member_id'] as num?)?.toInt();
    if (memberId == null) {
      return;
    }

    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => TrainerMemberDetailScreen(
          assignment: assignment,
          repository: _repository,
          onAssignWorkout: () => _openQuickAssignSheet(assignment),
          onAddNote: () => _openQuickNoteSheet(assignment),
          onFollowUp: () => _openQuickNoteSheet(assignment),
        ),
      ),
    );
  }

  Future<void> _openTasksScreen() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => TrainerTasksScreen(
          repository: _repository,
          members: _members,
          onChanged: _load,
        ),
      ),
    );
  }

  Future<void> _openQuickNoteSheet(Map<String, dynamic> assignment) async {
    final memberId = (assignment['member_id'] as num?)?.toInt();
    if (memberId == null) {
      return;
    }

    final noteController = TextEditingController();
    final followUpController = TextEditingController(
      text: DateTime.now()
          .add(const Duration(days: 1))
          .toIso8601String()
          .split('T')
          .first,
    );
    bool submitting = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surfaceOverlay,
      builder: (modalContext) => StatefulBuilder(
        builder: (modalContext, setModalState) => Padding(
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(modalContext).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Quick note',
                style: Theme.of(modalContext).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: noteController,
                minLines: 3,
                maxLines: 5,
                decoration: const InputDecoration(
                  labelText: 'Note or follow-up',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: followUpController,
                decoration: const InputDecoration(
                  labelText: 'Follow-up date (YYYY-MM-DD)',
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: submitting
                      ? null
                      : () async {
                          if (noteController.text.trim().isEmpty) {
                            return;
                          }
                          final navigator = Navigator.of(modalContext);
                          final rootMessenger = ScaffoldMessenger.of(context);
                          final modalMessenger = ScaffoldMessenger.of(
                            modalContext,
                          );
                          setModalState(() => submitting = true);
                          try {
                            await _repository.createNote(memberId, {
                              'note': noteController.text.trim(),
                              'visibility': 'private_to_trainer',
                              'follow_up_date': followUpController.text.trim(),
                            });
                            if (!mounted) {
                              return;
                            }
                            navigator.pop();
                            await _showSuccessCelebration(
                              'Quick note saved',
                              icon: Icons.check_circle_rounded,
                            );
                            rootMessenger.showSnackBar(
                              const SnackBar(
                                content: Text('Quick note saved.'),
                              ),
                            );
                            await _load();
                          } catch (exception) {
                            if (modalContext.mounted) {
                              modalMessenger.showSnackBar(
                                SnackBar(content: Text(exception.toString())),
                              );
                            }
                          } finally {
                            if (modalContext.mounted) {
                              setModalState(() => submitting = false);
                            }
                          }
                        },
                  child: Text(submitting ? 'Saving...' : 'Save note'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openQuickAssignSheet(Map<String, dynamic> assignment) async {
    final memberId = (assignment['member_id'] as num?)?.toInt();
    if (memberId == null) {
      return;
    }

    final member = _map(assignment['member']);
    final memberName = member['name']?.toString() ?? 'Member';
    int? selectedTemplateId = (_templates.firstOrNull?['id'] as num?)?.toInt();
    final startDateController = TextEditingController(
      text: DateTime.now().toIso8601String().split('T').first,
    );
    final notesController = TextEditingController();
    bool submitting = false;

    try {
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: Colors.transparent,
        builder: (modalContext) => StatefulBuilder(
          builder: (modalContext, setModalState) {
            final selectedTemplate = _templates.firstWhere(
              (template) =>
                  (template['id'] as num?)?.toInt() == selectedTemplateId,
              orElse: () => const <String, dynamic>{},
            );
            final existingPlans = _plans
                .where(
                  (plan) => (plan['member_id'] as num?)?.toInt() == memberId,
                )
                .toList();

            Future<void> assignTemplate() async {
              final templateId = selectedTemplateId;
              final gymId = (assignment['gym_id'] as num?)?.toInt();
              final branchId = (assignment['branch_id'] as num?)?.toInt();
              if (templateId == null || gymId == null || branchId == null) {
                ScaffoldMessenger.of(modalContext).showSnackBar(
                  const SnackBar(
                    content: Text('Select a library workout first.'),
                  ),
                );
                return;
              }

              final navigator = Navigator.of(modalContext);
              final rootMessenger = ScaffoldMessenger.of(context);
              final modalMessenger = ScaffoldMessenger.of(modalContext);
              final confirmed =
                  await showDialog<bool>(
                    context: modalContext,
                    builder: (_) => ConfirmationDialog(
                      title: 'Assign workout?',
                      message:
                          'Assign ${selectedTemplate['name']?.toString() ?? 'this workout'} only to $memberName?',
                      confirmLabel: 'Assign',
                    ),
                  ) ??
                  false;
              if (!confirmed) {
                return;
              }

              setModalState(() => submitting = true);
              try {
                await _repository.assignWorkoutTemplate(templateId, {
                  'gym_id': gymId,
                  'branch_id': branchId,
                  'member_ids': <int>[memberId],
                  'notes': notesController.text.trim().isEmpty
                      ? null
                      : notesController.text.trim(),
                  'starts_on': startDateController.text.trim(),
                });
                if (!mounted) {
                  return;
                }
                navigator.pop();
                await _showSuccessCelebration(
                  'Workout assigned',
                  icon: Icons.fitness_center_rounded,
                );
                rootMessenger.showSnackBar(
                  SnackBar(content: Text('Workout assigned to $memberName.')),
                );
                await _load();
              } catch (exception) {
                if (modalContext.mounted) {
                  modalMessenger.showSnackBar(
                    SnackBar(content: Text(exception.toString())),
                  );
                }
              } finally {
                if (modalContext.mounted) {
                  setModalState(() => submitting = false);
                }
              }
            }

            return Padding(
              padding: EdgeInsets.only(
                left: 14,
                right: 14,
                top: 14,
                bottom: MediaQuery.of(modalContext).viewInsets.bottom + 14,
              ),
              child: Material(
                color: Colors.transparent,
                child: Container(
                  constraints: BoxConstraints(
                    maxHeight: MediaQuery.sizeOf(modalContext).height * 0.9,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(30),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.16),
                        blurRadius: 34,
                        offset: const Offset(0, 18),
                      ),
                    ],
                  ),
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 52,
                              height: 52,
                              decoration: BoxDecoration(
                                gradient: _TrainerWorkoutColor.primaryGradient,
                                borderRadius: BorderRadius.circular(19),
                              ),
                              child: const Icon(
                                Icons.assignment_turned_in_rounded,
                                color: Colors.white,
                              ),
                            ),
                            const SizedBox(width: 13),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Assign workout to $memberName',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: _TrainerWorkoutColor.black,
                                      fontSize: 18,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  const Text(
                                    'Pick one library workout. No multi-select, no cloning old member plans.',
                                    style: TextStyle(
                                      color: _TrainerWorkoutColor.gray,
                                      fontSize: 11,
                                      height: 1.35,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            IconButton(
                              onPressed: submitting
                                  ? null
                                  : () => Navigator.of(modalContext).pop(),
                              icon: const Icon(Icons.close_rounded),
                            ),
                          ],
                        ),
                        const SizedBox(height: 18),
                        _MemberWorkoutSnapshot(plans: existingPlans),
                        const SizedBox(height: 16),
                        if (_templates.isEmpty)
                          EmptyStateView(
                            title: 'No library workouts yet',
                            message:
                                'Create a reusable workout in Workout Builder first. Then come back and assign it to $memberName.',
                            icon: Icons.library_add_outlined,
                            action: GradientButton(
                              label: 'Open builder',
                              icon: Icons.construction_rounded,
                              onPressed: () {
                                Navigator.of(modalContext).pop();
                                setState(() => _index = 2);
                              },
                            ),
                          )
                        else ...[
                          DropdownButtonFormField<int>(
                            initialValue: selectedTemplateId,
                            isExpanded: true,
                            items: _templates
                                .map(
                                  (template) => DropdownMenuItem<int>(
                                    value: (template['id'] as num?)?.toInt(),
                                    child: Text(
                                      template['name']?.toString() ??
                                          'Library workout',
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                )
                                .toList(),
                            onChanged: (value) =>
                                setModalState(() => selectedTemplateId = value),
                            decoration: _workoutInputDecoration(
                              'Library workout',
                              icon: Icons.library_books_rounded,
                            ),
                          ),
                          const SizedBox(height: 12),
                          if (selectedTemplate.isNotEmpty)
                            _TrainerWorkoutTile(
                              title:
                                  selectedTemplate['name']?.toString() ??
                                  'Library workout',
                              subtitle:
                                  '${selectedTemplate['goal']?.toString() ?? 'Reusable workout'} • ${_mapList(selectedTemplate['days']).length} day(s)',
                              badge:
                                  selectedTemplate['is_public_catalog'] == true
                                  ? 'Global'
                                  : selectedTemplate['difficulty']?.toString(),
                              icon: Icons.bolt_rounded,
                            ),
                          const SizedBox(height: 12),
                          TextField(
                            controller: startDateController,
                            decoration: _workoutInputDecoration(
                              'Start date',
                              icon: Icons.event_rounded,
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextField(
                            controller: notesController,
                            minLines: 2,
                            maxLines: 3,
                            decoration: _workoutInputDecoration(
                              'Trainer note for this assignment',
                              icon: Icons.notes_rounded,
                            ),
                          ),
                          const SizedBox(height: 18),
                          GradientButton(
                            label: submitting
                                ? 'Assigning workout...'
                                : 'Assign to $memberName',
                            icon: Icons.check_circle_rounded,
                            expanded: true,
                            onPressed: submitting ? null : assignTemplate,
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      );
    } finally {
      startDateController.dispose();
      notesController.dispose();
    }
  }

  Future<void> _openWorkoutManagerForMember(Map<String, dynamic> assignment) {
    setState(() {
      _workoutFocusMemberId = (assignment['member_id'] as num?)?.toInt();
      _index = 2;
    });
    return Future<void>.value();
  }

  Future<void> _openChatWithMember(Map<String, dynamic> assignment) async {
    final memberId = (assignment['member_id'] as num?)?.toInt();
    if (!mounted || memberId == null) {
      return;
    }
    await _openTrainerChatThread(memberId);
  }

  Future<void> _showSuccessCelebration(String title, {required IconData icon}) {
    Future<void>.delayed(const Duration(milliseconds: 650), () {
      if (mounted && Navigator.of(context, rootNavigator: true).canPop()) {
        Navigator.of(context, rootNavigator: true).pop();
      }
    });

    return showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: title,
      pageBuilder: (context, _, __) => const SizedBox.shrink(),
      transitionDuration: const Duration(milliseconds: 240),
      transitionBuilder: (context, animation, _, __) {
        return FadeTransition(
          opacity: animation,
          child: ScaleTransition(
            scale: CurvedAnimation(
              parent: animation,
              curve: Curves.easeOutBack,
            ),
            child: Center(
              child: GlassCard(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    PulseGlow(
                      child: Icon(
                        icon,
                        size: 54,
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      title,
                      style: Theme.of(context).textTheme.headlineSmall,
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _TrainerBottomNav extends StatelessWidget {
  const _TrainerBottomNav({required this.currentIndex, required this.onSelect});

  final int currentIndex;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      minimum: const EdgeInsets.only(bottom: 0),
      child: SizedBox(
        height: 92,
        child: Stack(
          alignment: Alignment.topCenter,
          children: [
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Container(
                height: 62,
                decoration: BoxDecoration(
                  color: Colors.white,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.10),
                      blurRadius: 14,
                      offset: const Offset(0, -3),
                    ),
                  ],
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _TrainerBottomNavItem(
                      label: 'Home',
                      icon: Icons.home_rounded,
                      active: currentIndex == 0,
                      onTap: () => onSelect(0),
                    ),
                    _TrainerBottomNavItem(
                      label: 'Clients',
                      icon: Icons.groups_rounded,
                      active: currentIndex == 1,
                      onTap: () => onSelect(1),
                    ),
                    const SizedBox(width: 58),
                    _TrainerBottomNavItem(
                      label: 'Chat',
                      icon: Icons.chat_bubble_rounded,
                      active: currentIndex == 3,
                      onTap: () => onSelect(3),
                    ),
                    _TrainerBottomNavItem(
                      label: 'Alerts',
                      icon: Icons.notifications_rounded,
                      active: currentIndex == 4,
                      onTap: () => onSelect(4),
                    ),
                  ],
                ),
              ),
            ),
            _TrainerCenterAction(
              active: currentIndex == 2,
              onTap: () => onSelect(2),
            ),
          ],
        ),
      ),
    );
  }
}

class _TrainerBottomNavItem extends StatelessWidget {
  const _TrainerBottomNavItem({
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
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(22),
      child: SizedBox(
        width: 58,
        height: 56,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedScale(
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOutCubic,
              scale: active ? 1.08 : 1,
              child: active
                  ? ShaderMask(
                      shaderCallback: (bounds) => const LinearGradient(
                        colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
                      ).createShader(bounds),
                      child: Icon(icon, color: Colors.white, size: 25),
                    )
                  : Icon(icon, color: const Color(0xFFB6ADB1), size: 25),
            ),
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOutCubic,
              height: active ? 8 : 12,
            ),
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOutCubic,
              width: active ? 5 : 0,
              height: active ? 5 : 0,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFFEEA4CE), Color(0xFFC58BF2)],
                ),
                borderRadius: BorderRadius.circular(3),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TrainerCenterAction extends StatelessWidget {
  const _TrainerCenterAction({required this.active, required this.onTap});

  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 72,
      height: 72,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(36),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: const Color(
                  0xFF92A3FD,
                ).withValues(alpha: active ? 0.34 : 0.24),
                blurRadius: active ? 24 : 16,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: Colors.black12,
                  blurRadius: 2,
                  offset: Offset(0, 1),
                ),
              ],
            ),
            child: Stack(
              alignment: Alignment.center,
              children: [
                Icon(
                  Icons.fitness_center_rounded,
                  color: Colors.white.withValues(alpha: 0.20),
                  size: 44,
                ),
                const Icon(
                  Icons.add_task_rounded,
                  color: Colors.white,
                  size: 30,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TrainerGreetingHeader extends StatelessWidget {
  const _TrainerGreetingHeader({
    required this.firstName,
    required this.subtitle,
    required this.unreadNotifications,
    required this.onOpenNotifications,
    required this.onOpenSettings,
  });

  final String firstName;
  final String subtitle;
  final int unreadNotifications;
  final VoidCallback onOpenNotifications;
  final VoidCallback onOpenSettings;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Hi, $firstName',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    color: const Color(0xFF18202A),
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
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: const Color(0xFF758092),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          _HeaderAction(
            icon: Icons.notifications_none_rounded,
            count: unreadNotifications,
            onTap: onOpenNotifications,
          ),
          const SizedBox(width: 10),
          _HeaderAction(icon: Icons.settings_rounded, onTap: onOpenSettings),
        ],
      ),
    );
  }
}

class _HeaderAction extends StatelessWidget {
  const _HeaderAction({
    required this.icon,
    required this.onTap,
    this.count = 0,
  });

  final IconData icon;
  final VoidCallback onTap;
  final int count;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.92),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF5F6F88).withValues(alpha: 0.11),
                  blurRadius: 18,
                  offset: const Offset(0, 10),
                ),
              ],
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: Colors.white),
            ),
            child: Icon(icon, color: const Color(0xFF18202A), size: 21),
          ),
          if (count > 0)
            Positioned(
              top: -4,
              right: -4,
              child: Container(
                constraints: const BoxConstraints(minWidth: 19, minHeight: 19),
                padding: const EdgeInsets.symmetric(horizontal: 5),
                decoration: BoxDecoration(
                  color: const Color(0xFFFF8D5C),
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: Colors.white, width: 2),
                ),
                alignment: Alignment.center,
                child: Text(
                  count > 9 ? '9+' : '$count',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    height: 1,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _TrainerHomeSkeleton extends StatelessWidget {
  const _TrainerHomeSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: const [
          SkeletonProfileHeader(),
          SizedBox(height: AppSpacing.lg),
          SkeletonDashboardGrid(),
          SizedBox(height: AppSpacing.lg),
          SkeletonListCard(lines: 3),
          SizedBox(height: AppSpacing.md),
          SkeletonListCard(lines: 3),
          SizedBox(height: AppSpacing.lg),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.lg),
          SkeletonNotificationsList(items: 4),
        ],
      ),
    );
  }
}

class _DashboardPage extends StatelessWidget {
  const _DashboardPage({
    required this.contextData,
    required this.tasks,
    required this.todayClients,
    required this.followUps,
    required this.members,
    required this.plans,
    required this.notifications,
    required this.onRefresh,
    required this.onEditProfile,
    required this.onOpenMembers,
    required this.onOpenWorkouts,
    required this.onOpenNotifications,
    required this.onOpenSettings,
    required this.onOpenTasks,
    required this.onAddNote,
  });

  final Map<String, dynamic> contextData;
  final Map<String, dynamic> tasks;
  final List<Map<String, dynamic>> todayClients;
  final List<Map<String, dynamic>> followUps;
  final List<Map<String, dynamic>> members;
  final List<Map<String, dynamic>> plans;
  final List<Map<String, dynamic>> notifications;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onEditProfile;
  final VoidCallback onOpenMembers;
  final VoidCallback onOpenWorkouts;
  final VoidCallback onOpenNotifications;
  final VoidCallback onOpenSettings;
  final VoidCallback onOpenTasks;
  final VoidCallback onAddNote;

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    final isWide = size.width >= 1100;
    final isMedium = size.width >= 760;
    final metricColumns = isWide ? 4 : (isMedium ? 3 : 2);
    final unreadMessages = notifications
        .where((item) => item['read_at'] == null)
        .length;
    final progressPhotoUploads = notifications
        .where((item) => item['type'] == 'progress_photo_uploaded')
        .length;
    final missedWorkoutAlerts = notifications
        .where(
          (item) =>
              item['type'] == 'missed_workout_alert' ||
              item['type'] == 'attendance_inactivity',
        )
        .length;
    final recentProgressMembers = members
        .where(
          (item) =>
              _map(item['progress_summary'])['latest_note'] != null ||
              _map(item['progress_summary'])['weight_kg'] != null,
        )
        .take(4)
        .toList();
    final normalizedContext = _normalizeTrainerContext(contextData);
    final trainerUser = _map(normalizedContext['user']);
    final trainerProfile = _map(normalizedContext['trainer_profile']);
    final assignedGym = _map(contextData['assigned_gym']);
    final assignedBranch = _map(trainerProfile['assigned_branch']);
    final availability = _list(trainerProfile['availability_slots']);
    final certifications = _list(trainerProfile['certifications']);
    final languages = _list(trainerProfile['languages']);
    final trainerName =
        trainerUser['name']?.toString() ??
        trainerProfile['name']?.toString() ??
        'Coach';
    final firstName = trainerName.trim().split(RegExp(r'\s+')).first;
    final assignedMembersCount =
        (trainerProfile['client_count'] as num?)?.toInt() ?? members.length;
    final todaysClientsCount =
        (tasks['todays_clients_count'] as num?)?.toInt() ?? todayClients.length;
    final pendingFollowUpsCount =
        (tasks['pending_follow_ups_count'] as num?)?.toInt() ??
        followUps.length;
    final workoutPlansAssignedCount = plans.length;
    final missedWorkoutsCount =
        (tasks['missed_workout_alerts_count'] as num?)?.toInt() ??
        missedWorkoutAlerts;
    final progressUpdatesCount =
        (tasks['client_progress_updates_count'] as num?)?.toInt() ??
        (progressPhotoUploads + recentProgressMembers.length);
    final idleDashboard =
        assignedMembersCount == 0 &&
        todaysClientsCount == 0 &&
        pendingFollowUpsCount == 0 &&
        workoutPlansAssignedCount == 0 &&
        missedWorkoutsCount == 0 &&
        progressUpdatesCount == 0 &&
        unreadMessages == 0;
    final todayClientPreview = todayClients.take(3).toList();
    final followUpPreview = followUps.take(3).toList();
    final quickActionItems =
        <({String title, String subtitle, IconData icon, VoidCallback onTap})>[
          (
            title: 'Assigned Members',
            subtitle: 'Review your current coaching roster.',
            icon: Icons.groups_rounded,
            onTap: onOpenMembers,
          ),
          (
            title: 'Create Workout Plan',
            subtitle: 'Open the workout builder and assign a plan.',
            icon: Icons.fitness_center_rounded,
            onTap: onOpenWorkouts,
          ),
          (
            title: 'Add Note',
            subtitle: 'Jump into members and add a follow-up note.',
            icon: Icons.edit_note_rounded,
            onTap: onAddNote,
          ),
          (
            title: 'Tasks',
            subtitle: 'Open the pending task and member queue.',
            icon: Icons.task_alt_rounded,
            onTap: onOpenTasks,
          ),
          (
            title: 'Notifications',
            subtitle: 'Check reminders, alerts, and updates.',
            icon: Icons.notifications_active_rounded,
            onTap: onOpenNotifications,
          ),
        ];
    final topMetrics =
        <({String label, String value, IconData icon, Color color})>[
          (
            label: 'Assigned members',
            value: '$assignedMembersCount',
            icon: Icons.groups_rounded,
            color: const Color(0xFF22D3EE),
          ),
          (
            label: 'Today’s clients',
            value: '$todaysClientsCount',
            icon: Icons.today_rounded,
            color: const Color(0xFF34D399),
          ),
          (
            label: 'Pending follow-ups',
            value: '$pendingFollowUpsCount',
            icon: Icons.assignment_late_outlined,
            color: const Color(0xFFF59E0B),
          ),
          (
            label: 'Workout plans assigned',
            value: '$workoutPlansAssignedCount',
            icon: Icons.fitness_center_rounded,
            color: const Color(0xFFA78BFA),
          ),
          (
            label: 'Missed workouts',
            value: '$missedWorkoutsCount',
            icon: Icons.warning_amber_rounded,
            color: const Color(0xFFFB7185),
          ),
          (
            label: 'Progress updates',
            value: '$progressUpdatesCount',
            icon: Icons.insights_rounded,
            color: const Color(0xFF60A5FA),
          ),
          (
            label: 'Unread messages',
            value: '$unreadMessages',
            icon: Icons.mark_chat_unread_rounded,
            color: const Color(0xFF38BDF8),
          ),
        ];
    Widget buildSectionHeader(
      String title,
      String subtitle, {
      VoidCallback? onPressed,
      String actionLabel = 'View all',
    }) {
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 4),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
          if (onPressed != null)
            TextButton(onPressed: onPressed, child: Text(actionLabel)),
        ],
      );
    }

    if (DateTime.now().millisecondsSinceEpoch < 0) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          children: [
            _TrainerGreetingHeader(
              firstName: firstName.isEmpty ? 'Coach' : firstName,
              subtitle: assignedGym['name']?.toString() ?? 'Trainer workspace',
              unreadNotifications: unreadMessages,
              onOpenNotifications: onOpenNotifications,
              onOpenSettings: onOpenSettings,
            ),
            const SizedBox(height: 18),
            RevealOnBuild(
              child: GlassCard(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: <Color>[
                    Theme.of(
                      context,
                    ).colorScheme.secondary.withValues(alpha: 0.18),
                    Theme.of(
                      context,
                    ).colorScheme.primary.withValues(alpha: 0.12),
                    AppColors.surface,
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        const StatusBadge(
                          label: 'Trainer HQ',
                          color: Color(0xFF22D3EE),
                          icon: Icons.auto_awesome_rounded,
                        ),
                        StatusBadge(
                          label:
                              assignedGym['name']?.toString() ?? 'Gym pending',
                          color: const Color(0xFF34D399),
                          icon: Icons.apartment_rounded,
                        ),
                        StatusBadge(
                          label:
                              assignedBranch['name']?.toString() ??
                              'Branch pending',
                          color: const Color(0xFFA78BFA),
                          icon: Icons.location_on_outlined,
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Coach smarter. Keep every client moving.',
                      style: Theme.of(context).textTheme.headlineMedium,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Track assigned members, today’s coaching queue, follow-ups, missed workouts, progress signals, and unread updates from one premium trainer dashboard.',
                    ),
                    const SizedBox(height: 18),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        SizedBox(
                          width: isMedium ? 220 : double.infinity,
                          child: GradientButton(
                            label: 'Assigned Members',
                            icon: Icons.groups_rounded,
                            expanded: true,
                            onPressed: onOpenMembers,
                          ),
                        ),
                        SizedBox(
                          width: isMedium ? 220 : double.infinity,
                          child: OutlinedButton.icon(
                            onPressed: onOpenWorkouts,
                            icon: const Icon(Icons.fitness_center_rounded),
                            label: const Text('Create Workout Plan'),
                          ),
                        ),
                        SizedBox(
                          width: isMedium ? 200 : double.infinity,
                          child: OutlinedButton.icon(
                            onPressed: onRefresh,
                            icon: const Icon(Icons.refresh_rounded),
                            label: const Text('Refresh'),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    GridView.count(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      crossAxisCount: metricColumns,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                      childAspectRatio: isWide
                          ? 1.28
                          : (isMedium ? 1.18 : 1.02),
                      children: topMetrics
                          .map(
                            (item) => MetricTile(
                              label: item.label,
                              value: item.value,
                              icon: item.icon,
                              color: item.color,
                            ),
                          )
                          .toList(),
                    ),
                    const SizedBox(height: 14),
                    StatusBadge(
                      label: '$unreadMessages unread notifications/messages',
                      color: const Color(0xFFA78BFA),
                      icon: Icons.mark_chat_unread_rounded,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 18),
            if (idleDashboard)
              RevealOnBuild(
                delay: const Duration(milliseconds: 40),
                child: PremiumCard(
                  child: EmptyStateView(
                    title: 'Your dashboard is ready',
                    message:
                        'Assigned members, plans, progress updates, and reminders will appear here as your gym starts routing clients to you.',
                    icon: Icons.space_dashboard_rounded,
                    action: SizedBox(
                      width: 220,
                      child: GradientButton(
                        label: 'Refresh dashboard',
                        icon: Icons.refresh_rounded,
                        expanded: true,
                        onPressed: onRefresh,
                      ),
                    ),
                  ),
                ),
              ),
            if (idleDashboard) const SizedBox(height: 18),
            RevealOnBuild(
              delay: const Duration(milliseconds: 60),
              child: PremiumCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    buildSectionHeader(
                      'Quick actions',
                      'Jump into the core trainer workflows without leaving the dashboard.',
                    ),
                    const SizedBox(height: 12),
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: quickActionItems.length,
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: isWide ? 3 : 2,
                        crossAxisSpacing: 12,
                        mainAxisSpacing: 12,
                        childAspectRatio: isWide ? 1.52 : 1.42,
                      ),
                      itemBuilder: (context, index) {
                        final item = quickActionItems[index];
                        return RevealOnBuild(
                          delay: Duration(milliseconds: 45 * index),
                          child: TaskCard(
                            title: item.title,
                            description: item.subtitle,
                            icon: item.icon,
                            actionLabel: 'Open',
                            onActionPressed: item.onTap,
                            onTap: item.onTap,
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 18),
            RevealOnBuild(
              delay: const Duration(milliseconds: 100),
              child: GlassCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (isMedium)
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CircleAvatar(
                            radius: 32,
                            backgroundImage:
                                (trainerProfile['profile_photo_url']
                                        ?.toString()
                                        .isNotEmpty ==
                                    true)
                                ? NetworkImage(
                                    trainerProfile['profile_photo_url']
                                        .toString(),
                                  )
                                : null,
                            child:
                                trainerProfile['profile_photo_url']
                                        ?.toString()
                                        .isNotEmpty ==
                                    true
                                ? null
                                : const Icon(Icons.fitness_center_rounded),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                buildSectionHeader(
                                  'Trainer profile',
                                  trainerProfile['primary_specialization']
                                          ?.toString() ??
                                      'Complete your specialization and availability details.',
                                ),
                                const SizedBox(height: 12),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: [
                                    StatusBadge(
                                      label:
                                          '${trainerProfile['profile_completion_percentage'] ?? 0}% complete',
                                      color: const Color(0xFF22D3EE),
                                    ),
                                    StatusBadge(
                                      label:
                                          '$assignedMembersCount active members',
                                      color: const Color(0xFF34D399),
                                    ),
                                    StatusBadge(
                                      label:
                                          '${workoutPlansAssignedCount + pendingFollowUpsCount} coaching actions',
                                      color: const Color(0xFFA78BFA),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 12),
                          FilledButton.icon(
                            onPressed: onEditProfile,
                            icon: const Icon(Icons.edit_rounded),
                            label: const Text('Edit profile'),
                          ),
                        ],
                      )
                    else
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              CircleAvatar(
                                radius: 28,
                                backgroundImage:
                                    (trainerProfile['profile_photo_url']
                                            ?.toString()
                                            .isNotEmpty ==
                                        true)
                                    ? NetworkImage(
                                        trainerProfile['profile_photo_url']
                                            .toString(),
                                      )
                                    : null,
                                child:
                                    trainerProfile['profile_photo_url']
                                            ?.toString()
                                            .isNotEmpty ==
                                        true
                                    ? null
                                    : const Icon(Icons.fitness_center_rounded),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: buildSectionHeader(
                                  'Trainer profile',
                                  trainerProfile['primary_specialization']
                                          ?.toString() ??
                                      'Complete your specialization and availability details.',
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              StatusBadge(
                                label:
                                    '${trainerProfile['profile_completion_percentage'] ?? 0}% complete',
                                color: const Color(0xFF22D3EE),
                              ),
                              StatusBadge(
                                label: '$assignedMembersCount active members',
                                color: const Color(0xFF34D399),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            child: FilledButton.icon(
                              onPressed: onEditProfile,
                              icon: const Icon(Icons.edit_rounded),
                              label: const Text('Edit profile'),
                            ),
                          ),
                        ],
                      ),
                    const SizedBox(height: 16),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        SizedBox(
                          width: isWide
                              ? (size.width - 104) / 3
                              : (isMedium
                                    ? (size.width - 74) / 2
                                    : double.infinity),
                          child: _MiniMetric(
                            label: 'Assigned gym',
                            value:
                                assignedGym['name']?.toString() ??
                                'Gym pending',
                            icon: Icons.apartment_rounded,
                          ),
                        ),
                        SizedBox(
                          width: isWide
                              ? (size.width - 104) / 3
                              : (isMedium
                                    ? (size.width - 74) / 2
                                    : double.infinity),
                          child: _MiniMetric(
                            label: 'Assigned branch',
                            value:
                                assignedBranch['name']?.toString() ??
                                'Branch pending',
                            icon: Icons.location_on_outlined,
                          ),
                        ),
                        SizedBox(
                          width: isWide
                              ? (size.width - 104) / 3
                              : (isMedium
                                    ? (size.width - 74) / 2
                                    : double.infinity),
                          child: _MiniMetric(
                            label: 'Experience',
                            value:
                                trainerProfile['experience_label']
                                    ?.toString() ??
                                '${trainerProfile['experience_years'] ?? 0} yrs',
                            icon: Icons.workspace_premium_rounded,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        ..._list(trainerProfile['specializations'])
                            .take(3)
                            .map(
                              (item) => StatusBadge(
                                label: item,
                                color: AppColors.textSecondary,
                              ),
                            ),
                        if (availability.isNotEmpty)
                          StatusBadge(
                            label: availability.join(' • '),
                            color: const Color(0xFFF59E0B),
                          ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    if (isMedium)
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: _ProfileListCard(
                              title: 'Certifications',
                              items: certifications,
                              emptyText:
                                  'Add certifications to strengthen trust.',
                              icon: Icons.verified_rounded,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _ProfileListCard(
                              title: 'Languages',
                              items: languages,
                              emptyText:
                                  'Languages will appear here once added.',
                              icon: Icons.translate_rounded,
                            ),
                          ),
                        ],
                      )
                    else
                      Column(
                        children: [
                          _ProfileListCard(
                            title: 'Certifications',
                            items: certifications,
                            emptyText:
                                'Add certifications to strengthen trust.',
                            icon: Icons.verified_rounded,
                          ),
                          const SizedBox(height: 12),
                          _ProfileListCard(
                            title: 'Languages',
                            items: languages,
                            emptyText: 'Languages will appear here once added.',
                            icon: Icons.translate_rounded,
                          ),
                        ],
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 18),
            RevealOnBuild(
              delay: const Duration(milliseconds: 140),
              child: isMedium
                  ? Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: PremiumCard(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                buildSectionHeader(
                                  'Today’s clients',
                                  'Your live coaching queue for today.',
                                  onPressed: onOpenMembers,
                                ),
                                const SizedBox(height: 12),
                                if (todayClientPreview.isEmpty)
                                  const EmptyStateView(
                                    title: 'No clients scheduled today',
                                    message:
                                        'Your client queue is clear for today.',
                                    icon: Icons.group_off_rounded,
                                  )
                                else
                                  ...todayClientPreview.asMap().entries.map((
                                    entry,
                                  ) {
                                    final memberItem = entry.value;
                                    final member = _map(memberItem['member']);
                                    final progressSummary = _map(
                                      memberItem['progress_summary'],
                                    );
                                    final membershipSummary = _map(
                                      memberItem['membership_summary'],
                                    );
                                    return Padding(
                                      padding: EdgeInsets.only(
                                        bottom:
                                            entry.key ==
                                                todayClientPreview.length - 1
                                            ? 0
                                            : 12,
                                      ),
                                      child: ClientCard(
                                        name:
                                            member['name']?.toString() ??
                                            'Member',
                                        goal: progressSummary['fitness_goal']
                                            ?.toString(),
                                        branch: _map(
                                          memberItem['branch'],
                                        )['name']?.toString(),
                                        status:
                                            membershipSummary['status']
                                                ?.toString() ??
                                            'active',
                                        subtitle:
                                            'Assigned member ready for coaching attention.',
                                        onTap: onOpenMembers,
                                      ),
                                    );
                                  }),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: PremiumCard(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                buildSectionHeader(
                                  'Pending follow-ups',
                                  'Notes and outreach tasks that still need action.',
                                  onPressed: onOpenTasks,
                                  actionLabel: 'Open queue',
                                ),
                                const SizedBox(height: 12),
                                if (followUpPreview.isEmpty)
                                  const EmptyStateView(
                                    title: 'No pending follow-ups',
                                    message:
                                        'Notes and scheduled follow-ups are clear right now.',
                                    icon: Icons.task_alt_rounded,
                                  )
                                else
                                  ...followUpPreview.asMap().entries.map((
                                    entry,
                                  ) {
                                    final followUp = entry.value;
                                    return Padding(
                                      padding: EdgeInsets.only(
                                        bottom:
                                            entry.key ==
                                                followUpPreview.length - 1
                                            ? 0
                                            : 12,
                                      ),
                                      child: TaskCard(
                                        title:
                                            _map(
                                              followUp['member'],
                                            )['name']?.toString() ??
                                            'Follow-up',
                                        description:
                                            'Follow up ${prettyDate(followUp['follow_up_date'])}',
                                        status: 'pending',
                                        dueLabel: prettyDate(
                                          followUp['follow_up_date'],
                                        ),
                                        icon: Icons.event_note_outlined,
                                        onTap: onOpenTasks,
                                        actionLabel: 'Review',
                                        onActionPressed: onOpenTasks,
                                      ),
                                    );
                                  }),
                              ],
                            ),
                          ),
                        ),
                      ],
                    )
                  : Column(
                      children: [
                        PremiumCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              buildSectionHeader(
                                'Today’s clients',
                                'Your live coaching queue for today.',
                                onPressed: onOpenMembers,
                              ),
                              const SizedBox(height: 12),
                              if (todayClientPreview.isEmpty)
                                const EmptyStateView(
                                  title: 'No clients scheduled today',
                                  message:
                                      'Your client queue is clear for today.',
                                  icon: Icons.group_off_rounded,
                                )
                              else
                                ...todayClientPreview.asMap().entries.map((
                                  entry,
                                ) {
                                  final memberItem = entry.value;
                                  final member = _map(memberItem['member']);
                                  final progressSummary = _map(
                                    memberItem['progress_summary'],
                                  );
                                  final membershipSummary = _map(
                                    memberItem['membership_summary'],
                                  );
                                  return Padding(
                                    padding: EdgeInsets.only(
                                      bottom:
                                          entry.key ==
                                              todayClientPreview.length - 1
                                          ? 0
                                          : 12,
                                    ),
                                    child: ClientCard(
                                      name:
                                          member['name']?.toString() ??
                                          'Member',
                                      goal: progressSummary['fitness_goal']
                                          ?.toString(),
                                      branch: _map(
                                        memberItem['branch'],
                                      )['name']?.toString(),
                                      status:
                                          membershipSummary['status']
                                              ?.toString() ??
                                          'active',
                                      subtitle:
                                          'Assigned member ready for coaching attention.',
                                      onTap: onOpenMembers,
                                    ),
                                  );
                                }),
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                        PremiumCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              buildSectionHeader(
                                'Pending follow-ups',
                                'Notes and outreach tasks that still need action.',
                                onPressed: onOpenTasks,
                                actionLabel: 'Open queue',
                              ),
                              const SizedBox(height: 12),
                              if (followUpPreview.isEmpty)
                                const EmptyStateView(
                                  title: 'No pending follow-ups',
                                  message:
                                      'Notes and scheduled follow-ups are clear right now.',
                                  icon: Icons.task_alt_rounded,
                                )
                              else
                                ...followUpPreview.asMap().entries.map((entry) {
                                  final followUp = entry.value;
                                  return Padding(
                                    padding: EdgeInsets.only(
                                      bottom:
                                          entry.key ==
                                              followUpPreview.length - 1
                                          ? 0
                                          : 12,
                                    ),
                                    child: TaskCard(
                                      title:
                                          _map(
                                            followUp['member'],
                                          )['name']?.toString() ??
                                          'Follow-up',
                                      description:
                                          'Follow up ${prettyDate(followUp['follow_up_date'])}',
                                      status: 'pending',
                                      dueLabel: prettyDate(
                                        followUp['follow_up_date'],
                                      ),
                                      icon: Icons.event_note_outlined,
                                      onTap: onOpenTasks,
                                      actionLabel: 'Review',
                                      onActionPressed: onOpenTasks,
                                    ),
                                  );
                                }),
                            ],
                          ),
                        ),
                      ],
                    ),
            ),
            const SizedBox(height: 12),
            RevealOnBuild(
              delay: const Duration(milliseconds: 180),
              child: isMedium
                  ? Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: PremiumCard(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                buildSectionHeader(
                                  'Progress updates',
                                  'Recent coaching momentum and member feedback signals.',
                                ),
                                const SizedBox(height: 12),
                                if (recentProgressMembers.isEmpty)
                                  const EmptyStateView(
                                    title: 'No progress updates',
                                    message:
                                        'Progress notes and updates will surface here.',
                                    icon: Icons.trending_up_rounded,
                                  )
                                else
                                  ...recentProgressMembers.map(
                                    (memberItem) => _SimpleTaskTile(
                                      title:
                                          _map(
                                            memberItem['member'],
                                          )['name']?.toString() ??
                                          'Member',
                                      subtitle:
                                          _map(
                                            memberItem['progress_summary'],
                                          )['latest_note']?.toString() ??
                                          'Weight ${_map(memberItem['progress_summary'])['weight_kg'] ?? '--'} kg',
                                      icon: Icons.insights_outlined,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: PremiumCard(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                buildSectionHeader(
                                  'Unread notifications / messages',
                                  'Alerts, reminders, and unread member communication that still need attention.',
                                  onPressed: onOpenNotifications,
                                ),
                                const SizedBox(height: 12),
                                if (unreadMessages == 0)
                                  const EmptyStateView(
                                    title: 'Inbox is clear',
                                    message:
                                        'New trainer notifications and member messages will show here.',
                                    icon: Icons.mark_email_read_rounded,
                                  )
                                else
                                  ...notifications
                                      .where((item) => item['read_at'] == null)
                                      .take(4)
                                      .map(
                                        (item) => _SimpleTaskTile(
                                          title: _titleCase(
                                            item['type']?.toString() ??
                                                'message',
                                          ),
                                          subtitle:
                                              item['message']?.toString() ??
                                              item['body']?.toString() ??
                                              'Unread notification',
                                          icon: Icons.mark_chat_unread_rounded,
                                        ),
                                      ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    )
                  : Column(
                      children: [
                        PremiumCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              buildSectionHeader(
                                'Progress updates',
                                'Recent coaching momentum and member feedback signals.',
                              ),
                              const SizedBox(height: 12),
                              if (recentProgressMembers.isEmpty)
                                const EmptyStateView(
                                  title: 'No progress updates',
                                  message:
                                      'Progress notes and updates will surface here.',
                                  icon: Icons.trending_up_rounded,
                                )
                              else
                                ...recentProgressMembers.map(
                                  (memberItem) => _SimpleTaskTile(
                                    title:
                                        _map(
                                          memberItem['member'],
                                        )['name']?.toString() ??
                                        'Member',
                                    subtitle:
                                        _map(
                                          memberItem['progress_summary'],
                                        )['latest_note']?.toString() ??
                                        'Weight ${_map(memberItem['progress_summary'])['weight_kg'] ?? '--'} kg',
                                    icon: Icons.insights_outlined,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                        PremiumCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              buildSectionHeader(
                                'Unread notifications / messages',
                                'Alerts, reminders, and unread member communication that still need attention.',
                                onPressed: onOpenNotifications,
                              ),
                              const SizedBox(height: 12),
                              if (unreadMessages == 0)
                                const EmptyStateView(
                                  title: 'Inbox is clear',
                                  message:
                                      'New trainer notifications and member messages will show here.',
                                  icon: Icons.mark_email_read_rounded,
                                )
                              else
                                ...notifications
                                    .where((item) => item['read_at'] == null)
                                    .take(4)
                                    .map(
                                      (item) => _SimpleTaskTile(
                                        title: _titleCase(
                                          item['type']?.toString() ?? 'message',
                                        ),
                                        subtitle:
                                            item['message']?.toString() ??
                                            item['body']?.toString() ??
                                            'Unread notification',
                                        icon: Icons.mark_chat_unread_rounded,
                                      ),
                                    ),
                            ],
                          ),
                        ),
                      ],
                    ),
            ),
          ],
        ),
      );
    }

    return _TrainerFitnessDashboard(
      firstName: firstName.isEmpty ? 'Coach' : firstName,
      trainerProfile: trainerProfile,
      trainerUser: trainerUser,
      assignedGym: assignedGym,
      assignedBranch: assignedBranch,
      assignedMembersCount: assignedMembersCount,
      todaysClientsCount: todaysClientsCount,
      pendingFollowUpsCount: pendingFollowUpsCount,
      workoutPlansAssignedCount: workoutPlansAssignedCount,
      missedWorkoutsCount: missedWorkoutsCount,
      progressUpdatesCount: progressUpdatesCount,
      unreadMessages: unreadMessages,
      todayClientPreview: todayClientPreview,
      followUpPreview: followUpPreview,
      recentProgressMembers: recentProgressMembers,
      notifications: notifications,
      idleDashboard: idleDashboard,
      onRefresh: onRefresh,
      onEditProfile: onEditProfile,
      onOpenMembers: onOpenMembers,
      onOpenWorkouts: onOpenWorkouts,
      onOpenNotifications: onOpenNotifications,
      onOpenSettings: onOpenSettings,
      onOpenTasks: onOpenTasks,
      onAddNote: onAddNote,
      titleCase: _titleCase,
    );
  }

  String _titleCase(String value) {
    if (value.isEmpty) {
      return 'Notification';
    }
    return value
        .split('_')
        .map(
          (part) => part.isEmpty
              ? part
              : '${part[0].toUpperCase()}${part.substring(1)}',
        )
        .join(' ');
  }
}

class _TrainerFitnessDashboard extends StatelessWidget {
  const _TrainerFitnessDashboard({
    required this.firstName,
    required this.trainerProfile,
    required this.trainerUser,
    required this.assignedGym,
    required this.assignedBranch,
    required this.assignedMembersCount,
    required this.todaysClientsCount,
    required this.pendingFollowUpsCount,
    required this.workoutPlansAssignedCount,
    required this.missedWorkoutsCount,
    required this.progressUpdatesCount,
    required this.unreadMessages,
    required this.todayClientPreview,
    required this.followUpPreview,
    required this.recentProgressMembers,
    required this.notifications,
    required this.idleDashboard,
    required this.onRefresh,
    required this.onEditProfile,
    required this.onOpenMembers,
    required this.onOpenWorkouts,
    required this.onOpenNotifications,
    required this.onOpenSettings,
    required this.onOpenTasks,
    required this.onAddNote,
    required this.titleCase,
  });

  final String firstName;
  final Map<String, dynamic> trainerProfile;
  final Map<String, dynamic> trainerUser;
  final Map<String, dynamic> assignedGym;
  final Map<String, dynamic> assignedBranch;
  final int assignedMembersCount;
  final int todaysClientsCount;
  final int pendingFollowUpsCount;
  final int workoutPlansAssignedCount;
  final int missedWorkoutsCount;
  final int progressUpdatesCount;
  final int unreadMessages;
  final List<Map<String, dynamic>> todayClientPreview;
  final List<Map<String, dynamic>> followUpPreview;
  final List<Map<String, dynamic>> recentProgressMembers;
  final List<Map<String, dynamic>> notifications;
  final bool idleDashboard;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onEditProfile;
  final VoidCallback onOpenMembers;
  final VoidCallback onOpenWorkouts;
  final VoidCallback onOpenNotifications;
  final VoidCallback onOpenSettings;
  final VoidCallback onOpenTasks;
  final VoidCallback onAddNote;
  final String Function(String value) titleCase;

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    final isWide = size.width >= 760;
    final profilePhoto = _resolveTrainerPhotoUrl(
      trainerProfile: trainerProfile,
      trainerUser: trainerUser,
    );
    final completion =
        (trainerProfile['profile_completion_percentage'] as num?)?.toDouble() ??
        0;
    final specialization = trainerProfile['primary_specialization']
        ?.toString()
        .trim();
    final heroStats = [
      _DashboardStatData(
        label: 'Clients',
        value: '$assignedMembersCount',
        icon: Icons.groups_rounded,
        color: const Color(0xFF92A3FD),
      ),
      _DashboardStatData(
        label: 'Today',
        value: '$todaysClientsCount',
        icon: Icons.calendar_today_rounded,
        color: const Color(0xFF9DCEFF),
      ),
      _DashboardStatData(
        label: 'Plans',
        value: '$workoutPlansAssignedCount',
        icon: Icons.fitness_center_rounded,
        color: const Color(0xFFC58BF2),
      ),
    ];
    final targetItems = [
      _DashboardStatData(
        label: 'Follow ups',
        value: '$pendingFollowUpsCount',
        icon: Icons.task_alt_rounded,
        color: const Color(0xFFFFB86B),
      ),
      _DashboardStatData(
        label: 'Progress',
        value: '$progressUpdatesCount',
        icon: Icons.trending_up_rounded,
        color: const Color(0xFF5AD7A8),
      ),
    ];
    final alertItems = [
      _DashboardStatData(
        label: 'Missed',
        value: '$missedWorkoutsCount',
        icon: Icons.warning_amber_rounded,
        color: const Color(0xFFFF7F9A),
      ),
      _DashboardStatData(
        label: 'Unread',
        value: '$unreadMessages',
        icon: Icons.notifications_active_rounded,
        color: const Color(0xFF7DD3FC),
      ),
    ];

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 10, 18, 28),
        children: [
          _FitnessWelcomeBar(
            firstName: firstName,
            unreadMessages: unreadMessages,
            onOpenNotifications: onOpenNotifications,
            onOpenSettings: onOpenSettings,
          ),
          const SizedBox(height: 18),
          _FitnessHeroCard(
            firstName: firstName,
            profilePhoto: profilePhoto,
            gymName: assignedGym['name']?.toString() ?? 'Trainer workspace',
            branchName: assignedBranch['name']?.toString() ?? 'Branch pending',
            specialization: specialization?.isNotEmpty == true
                ? specialization!
                : 'Complete trainer profile',
            completion: completion,
            stats: heroStats,
            onOpenMembers: onOpenMembers,
            onEditProfile: onEditProfile,
          ),
          const SizedBox(height: 18),
          _TodayTargetStrip(
            primaryItems: targetItems,
            alertItems: alertItems,
            onOpenTasks: onOpenTasks,
          ),
          const SizedBox(height: 18),
          _SectionTitleRow(
            title: 'Coach shortcuts',
            subtitle: 'Fast actions for your daily trainer flow',
            actionLabel: 'Refresh',
            onAction: onRefresh,
          ),
          const SizedBox(height: 12),
          _QuickActionRail(
            actions: [
              _QuickActionData(
                title: 'Members',
                subtitle: 'Roster',
                icon: Icons.groups_rounded,
                color: const Color(0xFF92A3FD),
                onTap: onOpenMembers,
              ),
              _QuickActionData(
                title: 'Workout',
                subtitle: 'Create plan',
                icon: Icons.fitness_center_rounded,
                color: const Color(0xFFC58BF2),
                onTap: onOpenWorkouts,
              ),
              _QuickActionData(
                title: 'Note',
                subtitle: 'Follow-up',
                icon: Icons.edit_note_rounded,
                color: const Color(0xFF5AD7A8),
                onTap: onAddNote,
              ),
              _QuickActionData(
                title: 'Tasks',
                subtitle: 'Queue',
                icon: Icons.fact_check_rounded,
                color: const Color(0xFFFFB86B),
                onTap: onOpenTasks,
              ),
            ],
          ),
          const SizedBox(height: 20),
          if (idleDashboard) ...[
            _SoftInfoCard(
              icon: Icons.space_dashboard_rounded,
              title: 'Dashboard is ready',
              subtitle:
                  'Members, plans, progress updates, and alerts will appear here as your gym routes clients to you.',
              actionLabel: 'Refresh dashboard',
              onAction: onRefresh,
            ),
            const SizedBox(height: 20),
          ],
          if (isWide)
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _TodayClientsPanel(
                    clients: todayClientPreview,
                    onOpenMembers: onOpenMembers,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: _FollowUpPanel(
                    followUps: followUpPreview,
                    onOpenTasks: onOpenTasks,
                  ),
                ),
              ],
            )
          else ...[
            _TodayClientsPanel(
              clients: todayClientPreview,
              onOpenMembers: onOpenMembers,
            ),
            const SizedBox(height: 14),
            _FollowUpPanel(
              followUps: followUpPreview,
              onOpenTasks: onOpenTasks,
            ),
          ],
          const SizedBox(height: 18),
          if (isWide)
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: _ProgressPanel(members: recentProgressMembers)),
                const SizedBox(width: 14),
                Expanded(
                  child: _NotificationPanel(
                    notifications: notifications,
                    unreadMessages: unreadMessages,
                    onOpenNotifications: onOpenNotifications,
                    titleCase: titleCase,
                  ),
                ),
              ],
            )
          else ...[
            _ProgressPanel(members: recentProgressMembers),
            const SizedBox(height: 14),
            _NotificationPanel(
              notifications: notifications,
              unreadMessages: unreadMessages,
              onOpenNotifications: onOpenNotifications,
              titleCase: titleCase,
            ),
          ],
        ],
      ),
    );
  }
}

String _resolveTrainerMediaUrl(String? rawUrl) {
  final value = rawUrl?.trim() ?? '';
  if (value.isEmpty) {
    return '';
  }
  if (value.startsWith('http://') || value.startsWith('https://')) {
    return value;
  }

  final apiUri = Uri.tryParse(TrainerConfig.apiBaseUrl);
  if (apiUri == null) {
    return value;
  }

  final baseUri = Uri(
    scheme: apiUri.scheme,
    host: apiUri.host,
    port: apiUri.hasPort ? apiUri.port : null,
  );
  final path = value.startsWith('/') ? value : '/$value';

  return baseUri.resolve(path).toString();
}

String _resolveTrainerPhotoUrl({
  required Map<String, dynamic> trainerProfile,
  required Map<String, dynamic> trainerUser,
}) {
  final nestedTrainerProfile = _map(trainerUser['trainer_profile']);
  final candidates = <String?>[
    trainerProfile['profile_photo_url']?.toString(),
    trainerProfile['photo_url']?.toString(),
    trainerProfile['photo']?.toString(),
    trainerProfile['avatar']?.toString(),
    nestedTrainerProfile['profile_photo_url']?.toString(),
    nestedTrainerProfile['photo_url']?.toString(),
    trainerUser['profile_photo_url']?.toString(),
    trainerUser['avatar']?.toString(),
  ];

  for (final candidate in candidates) {
    final resolved = _resolveTrainerMediaUrl(candidate);
    if (resolved.isNotEmpty) {
      return resolved;
    }
  }

  return '';
}

Map<String, dynamic> _normalizeTrainerContext(
  Map<String, dynamic> contextData,
) {
  final normalized = Map<String, dynamic>.from(contextData);
  final user = _map(normalized['user']);
  final trainerProfile = {
    ..._map(normalized['trainer_profile']),
    if (normalized['trainer_photo_url'] != null)
      'profile_photo_url': normalized['trainer_photo_url'],
  };
  final trainerPhoto = _resolveTrainerPhotoUrl(
    trainerProfile: trainerProfile,
    trainerUser: user,
  );

  if (trainerPhoto.isNotEmpty) {
    normalized['trainer_profile'] = {
      ...trainerProfile,
      'profile_photo_url': trainerPhoto,
    };
    normalized['user'] = {...user, 'avatar': trainerPhoto};
  }

  return normalized;
}

class _DashboardStatData {
  const _DashboardStatData({
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

class _QuickActionData {
  const _QuickActionData({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;
}

class _FitnessWelcomeBar extends StatelessWidget {
  const _FitnessWelcomeBar({
    required this.firstName,
    required this.unreadMessages,
    required this.onOpenNotifications,
    required this.onOpenSettings,
  });

  final String firstName;
  final int unreadMessages;
  final VoidCallback onOpenNotifications;
  final VoidCallback onOpenSettings;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Welcome Back,',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                firstName,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
        ),
        _SquareIconButton(icon: Icons.settings_rounded, onTap: onOpenSettings),
        const SizedBox(width: 10),
        Stack(
          clipBehavior: Clip.none,
          children: [
            _SquareIconButton(
              icon: Icons.notifications_rounded,
              onTap: onOpenNotifications,
            ),
            if (unreadMessages > 0)
              Positioned(
                right: -3,
                top: -3,
                child: Container(
                  width: 18,
                  height: 18,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    color: Color(0xFFFF7F9A),
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    unreadMessages > 9 ? '9+' : '$unreadMessages',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 9,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _SquareIconButton extends StatelessWidget {
  const _SquareIconButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.86),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.stroke),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.45),
              blurRadius: 18,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Icon(icon, color: AppColors.textPrimary, size: 20),
      ),
    );
  }
}

class _FitnessHeroCard extends StatelessWidget {
  const _FitnessHeroCard({
    required this.firstName,
    required this.profilePhoto,
    required this.gymName,
    required this.branchName,
    required this.specialization,
    required this.completion,
    required this.stats,
    required this.onOpenMembers,
    required this.onEditProfile,
  });

  final String firstName;
  final String profilePhoto;
  final String gymName;
  final String branchName;
  final String specialization;
  final double completion;
  final List<_DashboardStatData> stats;
  final VoidCallback onOpenMembers;
  final Future<void> Function() onEditProfile;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF92A3FD), Color(0xFF9DCEFF)],
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF92A3FD).withValues(alpha: 0.35),
            blurRadius: 30,
            offset: const Offset(0, 18),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -36,
            top: -32,
            child: _HeroBubble(size: 122, alpha: 0.18),
          ),
          Positioned(
            right: 18,
            bottom: 18,
            child: _HeroBubble(size: 62, alpha: 0.13),
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
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 7,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.20),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            specialization,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: 12,
                            ),
                          ),
                        ),
                        const SizedBox(height: 14),
                        Text(
                          'Coach smarter,\n$firstName',
                          style: Theme.of(context).textTheme.headlineMedium
                              ?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                height: 1.03,
                              ),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          '$gymName • $branchName',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.78),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 14),
                  Container(
                    width: 86,
                    height: 104,
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.30),
                      borderRadius: BorderRadius.circular(28),
                    ),
                    child: AppNetworkImage(
                      imageUrl: profilePhoto,
                      height: 96,
                      width: 78,
                      borderRadius: 24,
                      fallbackIcon: Icons.person_4_rounded,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(child: _CompletionPill(completion: completion)),
                  const SizedBox(width: 10),
                  SizedBox(
                    height: 42,
                    child: ElevatedButton(
                      onPressed: onEditProfile,
                      style: ElevatedButton.styleFrom(
                        elevation: 0,
                        backgroundColor: Colors.white,
                        foregroundColor: const Color(0xFF92A3FD),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                      child: const Text('Edit'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Row(
                children: stats
                    .map(
                      (item) => Expanded(
                        child: Padding(
                          padding: EdgeInsets.only(
                            right: item == stats.last ? 0 : 8,
                          ),
                          child: _HeroStatCell(data: item),
                        ),
                      ),
                    )
                    .toList(),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: onOpenMembers,
                  icon: const Icon(Icons.groups_rounded),
                  label: const Text('View assigned members'),
                  style: ElevatedButton.styleFrom(
                    elevation: 0,
                    backgroundColor: const Color(0xFFC58BF2),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(18),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeroBubble extends StatelessWidget {
  const _HeroBubble({required this.size, required this.alpha});

  final double size;
  final double alpha;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: alpha),
      ),
    );
  }
}

class _CompletionPill extends StatelessWidget {
  const _CompletionPill({required this.completion});

  final double completion;

  @override
  Widget build(BuildContext context) {
    final value = (completion.clamp(0, 100) / 100).toDouble();
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Profile completion ${completion.round()}%',
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: value,
              minHeight: 8,
              color: Colors.white,
              backgroundColor: Colors.white.withValues(alpha: 0.24),
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroStatCell extends StatelessWidget {
  const _HeroStatCell({required this.data});

  final _DashboardStatData data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.20),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          Icon(data.icon, color: Colors.white, size: 19),
          const SizedBox(height: 8),
          Text(
            data.value,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w900,
              fontSize: 20,
            ),
          ),
          Text(
            data.label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.78),
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _TodayTargetStrip extends StatelessWidget {
  const _TodayTargetStrip({
    required this.primaryItems,
    required this.alertItems,
    required this.onOpenTasks,
  });

  final List<_DashboardStatData> primaryItems;
  final List<_DashboardStatData> alertItems;
  final VoidCallback onOpenTasks;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF9DCEFF).withValues(alpha: 0.22),
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Today Target',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              SizedBox(
                height: 32,
                child: ElevatedButton(
                  onPressed: onOpenTasks,
                  style: ElevatedButton.styleFrom(
                    elevation: 0,
                    backgroundColor: const Color(0xFF92A3FD),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                  child: const Text('Check'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [...primaryItems, ...alertItems]
                .map(
                  (item) => Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(
                        right: item == alertItems.last ? 0 : 8,
                      ),
                      child: _TargetMiniCell(data: item),
                    ),
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }
}

class _TargetMiniCell extends StatelessWidget {
  const _TargetMiniCell({required this.data});

  final _DashboardStatData data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.88),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          Icon(data.icon, color: data.color, size: 19),
          const SizedBox(height: 6),
          Text(
            data.value,
            style: TextStyle(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
              fontSize: 16,
            ),
          ),
          Text(
            data.label,
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

class _SectionTitleRow extends StatelessWidget {
  const _SectionTitleRow({
    required this.title,
    required this.subtitle,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

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
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 3),
              Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
        if (onAction != null && actionLabel != null)
          TextButton(onPressed: onAction, child: Text(actionLabel!)),
      ],
    );
  }
}

class _QuickActionRail extends StatelessWidget {
  const _QuickActionRail({required this.actions});

  final List<_QuickActionData> actions;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 116,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: actions.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (context, index) => _QuickActionTile(data: actions[index]),
      ),
    );
  }
}

class _QuickActionTile extends StatelessWidget {
  const _QuickActionTile({required this.data});

  final _QuickActionData data;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: data.onTap,
      borderRadius: BorderRadius.circular(22),
      child: Container(
        width: 138,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.90),
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: AppColors.stroke),
          boxShadow: [
            BoxShadow(
              color: data.color.withValues(alpha: 0.16),
              blurRadius: 24,
              offset: const Offset(0, 14),
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
                gradient: LinearGradient(
                  colors: [data.color, data.color.withValues(alpha: 0.58)],
                ),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(data.icon, color: Colors.white, size: 20),
            ),
            const Spacer(),
            Text(
              data.title,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w900,
                color: AppColors.textPrimary,
              ),
            ),
            Text(
              data.subtitle,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(
                context,
              ).textTheme.labelSmall?.copyWith(color: AppColors.textSecondary),
            ),
          ],
        ),
      ),
    );
  }
}

class _SoftInfoCard extends StatelessWidget {
  const _SoftInfoCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.actionLabel,
    required this.onAction,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String actionLabel;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFFC58BF2).withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFFC58BF2), size: 34),
          const SizedBox(width: 14),
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
          TextButton(onPressed: onAction, child: Text(actionLabel)),
        ],
      ),
    );
  }
}

class _TodayClientsPanel extends StatelessWidget {
  const _TodayClientsPanel({
    required this.clients,
    required this.onOpenMembers,
  });

  final List<Map<String, dynamic>> clients;
  final VoidCallback onOpenMembers;

  @override
  Widget build(BuildContext context) {
    return _FitnessPanel(
      title: 'Today clients',
      subtitle: 'Live coaching queue',
      actionLabel: 'View all',
      onAction: onOpenMembers,
      children: clients.isEmpty
          ? const [
              _PanelEmpty(
                icon: Icons.group_off_rounded,
                title: 'No clients scheduled today',
                subtitle: 'Your client queue is clear.',
              ),
            ]
          : clients.map((item) {
              final member = _map(item['member']);
              final progress = _map(item['progress_summary']);
              final status =
                  _map(item['membership_summary'])['status']?.toString() ??
                  'active';
              return _WorkoutStyleRow(
                title: member['name']?.toString() ?? 'Member',
                subtitle:
                    progress['fitness_goal']?.toString() ??
                    'Ready for coaching attention',
                meta: status,
                icon: Icons.person_rounded,
                color: const Color(0xFF92A3FD),
                onTap: onOpenMembers,
              );
            }).toList(),
    );
  }
}

class _FollowUpPanel extends StatelessWidget {
  const _FollowUpPanel({required this.followUps, required this.onOpenTasks});

  final List<Map<String, dynamic>> followUps;
  final VoidCallback onOpenTasks;

  @override
  Widget build(BuildContext context) {
    return _FitnessPanel(
      title: 'Pending follow-ups',
      subtitle: 'Notes and outreach tasks',
      actionLabel: 'Open queue',
      onAction: onOpenTasks,
      children: followUps.isEmpty
          ? const [
              _PanelEmpty(
                icon: Icons.task_alt_rounded,
                title: 'No pending follow-ups',
                subtitle: 'Scheduled follow-ups are clear right now.',
              ),
            ]
          : followUps.map((item) {
              final member = _map(item['member']);
              final due = prettyDate(item['follow_up_date']);
              return _WorkoutStyleRow(
                title: member['name']?.toString() ?? 'Follow-up',
                subtitle: 'Follow up $due',
                meta: 'Pending',
                icon: Icons.event_note_rounded,
                color: const Color(0xFFFFB86B),
                onTap: onOpenTasks,
              );
            }).toList(),
    );
  }
}

class _ProgressPanel extends StatelessWidget {
  const _ProgressPanel({required this.members});

  final List<Map<String, dynamic>> members;

  @override
  Widget build(BuildContext context) {
    return _FitnessPanel(
      title: 'Progress updates',
      subtitle: 'Recent client momentum',
      children: members.isEmpty
          ? const [
              _PanelEmpty(
                icon: Icons.trending_up_rounded,
                title: 'No progress updates',
                subtitle: 'Progress notes will surface here.',
              ),
            ]
          : members.map((item) {
              final member = _map(item['member']);
              final progress = _map(item['progress_summary']);
              return _WorkoutStyleRow(
                title: member['name']?.toString() ?? 'Member',
                subtitle:
                    progress['latest_note']?.toString() ??
                    'Weight ${progress['weight_kg'] ?? '--'} kg',
                meta: 'Update',
                icon: Icons.insights_rounded,
                color: const Color(0xFF5AD7A8),
              );
            }).toList(),
    );
  }
}

class _NotificationPanel extends StatelessWidget {
  const _NotificationPanel({
    required this.notifications,
    required this.unreadMessages,
    required this.onOpenNotifications,
    required this.titleCase,
  });

  final List<Map<String, dynamic>> notifications;
  final int unreadMessages;
  final VoidCallback onOpenNotifications;
  final String Function(String value) titleCase;

  @override
  Widget build(BuildContext context) {
    final unread = notifications
        .where((item) => item['read_at'] == null)
        .take(4)
        .toList();
    return _FitnessPanel(
      title: 'Notifications',
      subtitle: '$unreadMessages unread updates',
      actionLabel: 'Open',
      onAction: onOpenNotifications,
      children: unread.isEmpty
          ? const [
              _PanelEmpty(
                icon: Icons.mark_email_read_rounded,
                title: 'Inbox is clear',
                subtitle: 'New trainer updates will appear here.',
              ),
            ]
          : unread.map((item) {
              return _WorkoutStyleRow(
                title: titleCase(item['type']?.toString() ?? 'notification'),
                subtitle:
                    item['message']?.toString() ??
                    item['body']?.toString() ??
                    'Unread notification',
                meta: 'Unread',
                icon: Icons.notifications_active_rounded,
                color: const Color(0xFF7DD3FC),
                onTap: onOpenNotifications,
              );
            }).toList(),
    );
  }
}

class _FitnessPanel extends StatelessWidget {
  const _FitnessPanel({
    required this.title,
    required this.subtitle,
    required this.children,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String subtitle;
  final List<Widget> children;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.stroke),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.35),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionTitleRow(
            title: title,
            subtitle: subtitle,
            actionLabel: actionLabel,
            onAction: onAction,
          ),
          const SizedBox(height: 12),
          ...children.expand((child) sync* {
            yield child;
            if (child != children.last) {
              yield const SizedBox(height: 10);
            }
          }),
        ],
      ),
    );
  }
}

class _WorkoutStyleRow extends StatelessWidget {
  const _WorkoutStyleRow({
    required this.title,
    required this.subtitle,
    required this.meta,
    required this.icon,
    required this.color,
    this.onTap,
  });

  final String title;
  final String subtitle;
  final String meta;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.backgroundAlt.withValues(alpha: 0.70),
          borderRadius: BorderRadius.circular(18),
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    color.withValues(alpha: 0.95),
                    color.withValues(alpha: 0.50),
                  ],
                ),
                borderRadius: BorderRadius.circular(16),
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
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(
              meta,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PanelEmpty extends StatelessWidget {
  const _PanelEmpty({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.backgroundAlt.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          Icon(icon, color: AppColors.textSecondary, size: 30),
          const SizedBox(height: 8),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _MemberPage extends StatefulWidget {
  const _MemberPage({
    required this.members,
    required this.templates,
    required this.plans,
    required this.onRefresh,
    required this.onOpenMember,
    required this.onQuickNote,
    required this.onQuickAssign,
    required this.onManageWorkouts,
    required this.onSendMessage,
    required this.onAddFollowUp,
  });

  final List<Map<String, dynamic>> members;
  final List<Map<String, dynamic>> templates;
  final List<Map<String, dynamic>> plans;
  final Future<void> Function() onRefresh;
  final Future<void> Function(Map<String, dynamic>) onOpenMember;
  final Future<void> Function(Map<String, dynamic>) onQuickNote;
  final Future<void> Function(Map<String, dynamic>) onQuickAssign;
  final Future<void> Function(Map<String, dynamic>) onManageWorkouts;
  final Future<void> Function(Map<String, dynamic>) onSendMessage;
  final Future<void> Function(Map<String, dynamic>) onAddFollowUp;

  @override
  State<_MemberPage> createState() => _MemberPageState();
}

class _MemberPageState extends State<_MemberPage> {
  final TextEditingController _searchController = TextEditingController();
  bool _dueOnly = false;
  bool _needsPlanOnly = false;
  String _goalFilter = 'all';
  String _statusFilter = 'all';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = _searchController.text.trim().toLowerCase();
    final goals =
        widget.members
            .map(
              (assignment) =>
                  _map(assignment['progress_summary'])['fitness_goal'],
            )
            .map((value) => value?.toString().trim() ?? '')
            .where((value) => value.isNotEmpty)
            .toSet()
            .toList()
          ..sort();
    final statuses =
        widget.members
            .map(
              (assignment) => _map(assignment['membership_summary'])['status'],
            )
            .map((value) => value?.toString().trim() ?? '')
            .where((value) => value.isNotEmpty)
            .toSet()
            .toList()
          ..sort();
    final filteredMembers = widget.members.where((assignment) {
      final member = _map(assignment['member']);
      final name = member['name']?.toString().toLowerCase() ?? '';
      final goal =
          _map(assignment['progress_summary'])['fitness_goal']?.toString() ??
          '';
      final goalLower = goal.toLowerCase();
      final membershipSummary = _map(assignment['membership_summary']);
      final status = membershipSummary['status']?.toString() ?? '';
      final memberPlans = widget.plans.where(
        (plan) =>
            (plan['member_id'] as num?)?.toInt() ==
            (assignment['member_id'] as num?)?.toInt(),
      );

      if (query.isNotEmpty &&
          !name.contains(query) &&
          !goalLower.contains(query)) {
        return false;
      }

      if (_dueOnly && _toDouble(membershipSummary['due_amount']) <= 0) {
        return false;
      }

      if (_needsPlanOnly && memberPlans.isNotEmpty) {
        return false;
      }

      if (_goalFilter != 'all' && goal != _goalFilter) {
        return false;
      }

      if (_statusFilter != 'all' && status != _statusFilter) {
        return false;
      }

      return true;
    }).toList();

    if (widget.members.isEmpty) {
      return EmptyStateView(
        title: 'No assigned members yet',
        message:
            'Once your gym assigns Members, they will appear here with notes and workout actions.',
        icon: Icons.groups_outlined,
        action: SizedBox(
          width: 220,
          child: GradientButton(
            label: 'Refresh',
            icon: Icons.refresh_rounded,
            expanded: true,
            onPressed: widget.onRefresh,
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: widget.onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 26),
        children: [
          _MembersFitnessHeader(
            totalCount: widget.members.length,
            visibleCount: filteredMembers.length,
            dueCount: widget.members
                .where(
                  (item) =>
                      _toDouble(
                        _map(item['membership_summary'])['due_amount'],
                      ) >
                      0,
                )
                .length,
            needsPlanCount: widget.members.where((assignment) {
              final memberId = (assignment['member_id'] as num?)?.toInt();
              return widget.plans.every(
                (plan) => (plan['member_id'] as num?)?.toInt() != memberId,
              );
            }).length,
            onRefresh: widget.onRefresh,
          ),
          const SizedBox(height: 16),
          _MembersFlowCard(
            libraryCount: widget.templates.length,
            assignedPlanCount: widget.plans.length,
            needsPlanCount: widget.members.where((assignment) {
              final memberId = (assignment['member_id'] as num?)?.toInt();
              return widget.plans.every(
                (plan) => (plan['member_id'] as num?)?.toInt() != memberId,
              );
            }).length,
          ),
          const SizedBox(height: 16),
          _MembersSearchCard(
            controller: _searchController,
            query: query,
            dueOnly: _dueOnly,
            needsPlanOnly: _needsPlanOnly,
            goalFilter: _goalFilter == 'all' ? null : _goalFilter,
            statusFilter: _statusFilter == 'all' ? null : _statusFilter,
            goals: goals,
            statuses: statuses,
            onQueryChanged: (_) => setState(() {}),
            onClear: () {
              _searchController.clear();
              setState(() {});
            },
            onDueOnlyChanged: (value) => setState(() => _dueOnly = value),
            onNeedsPlanChanged: (value) =>
                setState(() => _needsPlanOnly = value),
            onGoalChanged: (value) =>
                setState(() => _goalFilter = value ?? 'all'),
            onStatusChanged: (value) =>
                setState(() => _statusFilter = value ?? 'all'),
          ),
          const SizedBox(height: 14),
          if (filteredMembers.isEmpty)
            const EmptyStateView(
              title: 'No Members match these filters',
              message:
                  'Try clearing filters or refreshing the assigned Member list.',
              icon: Icons.filter_alt_off_rounded,
            )
          else
            ...filteredMembers.map(
              (assignment) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _FitnessMemberRow(
                  assignment: assignment,
                  plans: widget.plans,
                  onOpen: () => widget.onOpenMember(assignment),
                  onQuickNote: () => widget.onQuickNote(assignment),
                  onQuickAssign: () => widget.onQuickAssign(assignment),
                  onManageWorkouts: () => widget.onManageWorkouts(assignment),
                  onSendMessage: () => widget.onSendMessage(assignment),
                  onAddFollowUp: () => widget.onAddFollowUp(assignment),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _WorkoutPage extends StatefulWidget {
  const _WorkoutPage({
    required this.contextData,
    required this.members,
    required this.templates,
    required this.plans,
    required this.exercises,
    required this.repository,
    required this.initialMemberId,
    required this.onRefresh,
  });

  final Map<String, dynamic> contextData;
  final List<Map<String, dynamic>> members;
  final List<Map<String, dynamic>> templates;
  final List<Map<String, dynamic>> plans;
  final List<Map<String, dynamic>> exercises;
  final TrainerRepository repository;
  final int? initialMemberId;
  final Future<void> Function() onRefresh;

  @override
  State<_WorkoutPage> createState() => __WorkoutPageState();
}

class __WorkoutPageState extends State<_WorkoutPage> {
  final _planNameController = TextEditingController();
  final _goalController = TextEditingController();
  final _difficultyController = TextEditingController(text: 'intermediate');
  final _durationController = TextEditingController(text: '4');
  final _notesController = TextEditingController();
  final _exerciseSearchController = TextEditingController();
  final _dayLabelController = TextEditingController();
  final _focusController = TextEditingController();
  final _dayNotesController = TextEditingController();
  final _setsController = TextEditingController(text: '4');
  final _repsController = TextEditingController(text: '10');
  final _targetWeightController = TextEditingController();
  final _restController = TextEditingController(text: '60');
  final _exerciseNotesController = TextEditingController();
  final _newExerciseNameController = TextEditingController();
  final _newExerciseBodyPartController = TextEditingController(text: 'chest');
  final _newExerciseMuscleController = TextEditingController();
  final _newExerciseEquipmentController = TextEditingController();
  final _newExerciseDifficultyController = TextEditingController(
    text: 'beginner',
  );
  final _newExerciseInstructionsController = TextEditingController();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  int? _selectedMemberId;
  int? _selectedTemplateId;
  int? _selectedExerciseId;
  bool _savingPlan = false;
  bool _savingExercise = false;
  int _workoutTabIndex = 0;
  String _selectedDayKey = 'Mon';
  final Set<String> _selectedWeekDays = <String>{'Mon', 'Wed', 'Fri'};
  final Map<String, _WorkoutDayDraft> _dayDrafts = <String, _WorkoutDayDraft>{};
  static const Map<String, int> _dayNumbers = <String, int>{
    'Mon': 1,
    'Tue': 2,
    'Wed': 3,
    'Thu': 4,
    'Fri': 5,
    'Sat': 6,
    'Sun': 7,
  };
  static const List<String> _bodyParts = <String>[
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

  @override
  void initState() {
    super.initState();
    _selectedMemberId =
        _validMemberId(widget.initialMemberId) ??
        (widget.members.firstOrNull?['member_id'] as num?)?.toInt();
    _selectedTemplateId = (widget.templates.firstOrNull?['id'] as num?)
        ?.toInt();
    _selectedExerciseId = (widget.exercises.firstOrNull?['id'] as num?)
        ?.toInt();
    for (final day in _selectedWeekDays) {
      _dayDrafts[day] = _WorkoutDayDraft(
        label: day,
        focus: '',
        notes: '',
        exercises: <_WorkoutExerciseDraft>[],
      );
    }
    _loadDayIntoFields(_selectedDayKey);
  }

  @override
  void didUpdateWidget(covariant _WorkoutPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.initialMemberId != oldWidget.initialMemberId) {
      final focusedMemberId = _validMemberId(widget.initialMemberId);
      if (focusedMemberId != null) {
        setState(() => _selectedMemberId = focusedMemberId);
      }
    }
  }

  @override
  void dispose() {
    _planNameController.dispose();
    _goalController.dispose();
    _difficultyController.dispose();
    _durationController.dispose();
    _notesController.dispose();
    _exerciseSearchController.dispose();
    _dayLabelController.dispose();
    _focusController.dispose();
    _dayNotesController.dispose();
    _setsController.dispose();
    _repsController.dispose();
    _targetWeightController.dispose();
    _restController.dispose();
    _exerciseNotesController.dispose();
    _newExerciseNameController.dispose();
    _newExerciseBodyPartController.dispose();
    _newExerciseMuscleController.dispose();
    _newExerciseEquipmentController.dispose();
    _newExerciseDifficultyController.dispose();
    _newExerciseInstructionsController.dispose();
    super.dispose();
  }

  int? _validMemberId(int? memberId) {
    if (memberId == null) {
      return null;
    }
    final exists = widget.members.any(
      (member) => (member['member_id'] as num?)?.toInt() == memberId,
    );
    return exists ? memberId : null;
  }

  @override
  Widget build(BuildContext context) {
    final selectedMember = widget.members.firstWhere(
      (item) => (item['member_id'] as num?)?.toInt() == _selectedMemberId,
      orElse: () => widget.members.firstOrNull ?? const <String, dynamic>{},
    );
    final filteredExercises = widget.exercises.where((exercise) {
      final query = _exerciseSearchController.text.trim().toLowerCase();
      if (query.isEmpty) {
        return true;
      }
      return (exercise['name']?.toString().toLowerCase() ?? '').contains(
            query,
          ) ||
          (exercise['muscle_group']?.toString().toLowerCase() ?? '').contains(
            query,
          ) ||
          (exercise['body_part_label']?.toString().toLowerCase() ?? '')
              .contains(query);
    }).toList();
    final selectedDayDraft = _ensureDayDraft(_selectedDayKey);
    final canUseTemplate = widget.templates.isNotEmpty;
    final memberName =
        _map(selectedMember['member'])['name']?.toString() ?? 'Assigned member';
    final selectedMemberPlans = widget.plans.where((plan) {
      return (plan['member_id'] as num?)?.toInt() == _selectedMemberId;
    }).toList();

    return ListView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(25, 14, 25, 30),
      children: [
        _TrainerWorkoutHero(
          planCount: widget.plans.length,
          templateCount: widget.templates.length,
          exerciseCount: widget.exercises.length,
          memberCount: widget.members.length,
        ),
        const SizedBox(height: 22),
        if (widget.members.isEmpty)
          const EmptyStateView(
            title: 'No assigned members available',
            message:
                'Assign a member to begin building and saving workout plans.',
            icon: Icons.groups_outlined,
          )
        else
          Form(
            key: _formKey,
            child: Column(
              children: [
                _TrainerWorkoutTabs(
                  selectedIndex: _workoutTabIndex,
                  onChanged: (index) =>
                      setState(() => _workoutTabIndex = index),
                ),
                const SizedBox(height: 18),
                if (_workoutTabIndex == 0) ...[
                  _TrainerWorkoutSection(
                    title: 'Step 1: Select Member',
                    subtitle:
                        'Choose one client first. Everything below is scoped only to this member.',
                    icon: Icons.person_search_rounded,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        DropdownButtonFormField<int>(
                          key: ValueKey('member-$_selectedMemberId'),
                          initialValue: _selectedMemberId,
                          isExpanded: true,
                          items: widget.members
                              .map(
                                (member) => DropdownMenuItem<int>(
                                  value: (member['member_id'] as num?)?.toInt(),
                                  child: Text(
                                    _map(
                                          member['member'],
                                        )['name']?.toString() ??
                                        'Member',
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: (value) =>
                              setState(() => _selectedMemberId = value),
                          decoration: _workoutInputDecoration(
                            'Member',
                            icon: Icons.person_search_rounded,
                          ),
                        ),
                        const SizedBox(height: 14),
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: _TrainerWorkoutColor.field,
                            borderRadius: BorderRadius.circular(18),
                          ),
                          child: Row(
                            children: [
                              const Icon(
                                Icons.info_outline_rounded,
                                color: _TrainerWorkoutColor.primaryEnd,
                                size: 20,
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  selectedMemberPlans.isEmpty
                                      ? 'No workout assigned yet. Select a library workout below and tap Assign.'
                                      : '${selectedMemberPlans.length} workout plan(s) assigned to $memberName.',
                                  style: const TextStyle(
                                    color: _TrainerWorkoutColor.gray,
                                    fontSize: 12,
                                    height: 1.35,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  _TrainerWorkoutSection(
                    title: 'Step 2: Pick Library Workout',
                    subtitle:
                        'Select a saved or global workout. It will be assigned only to the member selected above.',
                    icon: Icons.library_books_rounded,
                    child: Column(
                      children: [
                        if (!canUseTemplate)
                          const EmptyStateView(
                            title: 'No library workouts yet',
                            message:
                                'Open Workout Builder to create a reusable workout, then assign it from here.',
                            icon: Icons.dashboard_customize_outlined,
                          )
                        else ...[
                          ...widget.templates.map((template) {
                            final templateId = (template['id'] as num?)
                                ?.toInt();
                            final isSelected =
                                templateId != null &&
                                templateId == _selectedTemplateId;
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: _TrainerWorkoutTile(
                                title:
                                    template['name']?.toString() ??
                                    'Library workout',
                                subtitle:
                                    '${template['is_public_catalog'] == true ? 'Global library' : 'Trainer library'} • ${template['goal']?.toString() ?? 'Reusable workout'} • ${_mapList(template['days']).length} day(s)',
                                badge:
                                    template['difficulty']?.toString() ??
                                    (template['is_public_catalog'] == true
                                        ? 'Global'
                                        : null),
                                icon: isSelected
                                    ? Icons.check_circle_rounded
                                    : Icons.bolt_rounded,
                                actionLabel: isSelected ? 'Selected' : 'Select',
                                onAction: () => setState(
                                  () => _selectedTemplateId = templateId,
                                ),
                              ),
                            );
                          }),
                          const SizedBox(height: 2),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: _TrainerWorkoutColor.primaryEnd.withValues(
                                alpha: 0.08,
                              ),
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(
                                color: _TrainerWorkoutColor.primaryEnd
                                    .withValues(alpha: 0.14),
                              ),
                            ),
                            child: const Text(
                              'Tap Select on one workout, then use the Assign button below.',
                              style: TextStyle(
                                color: _TrainerWorkoutColor.gray,
                                fontSize: 11,
                                height: 1.35,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                ] else ...[
                  _TrainerWorkoutSection(
                    title: 'Create Library Workout',
                    subtitle:
                        'Build a reusable workout once. After saving, assign it to any member from the Assign Workout tab.',
                    icon: Icons.tune_rounded,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        TextFormField(
                          controller: _planNameController,
                          decoration: _workoutInputDecoration(
                            'Plan name',
                            icon: Icons.drive_file_rename_outline_rounded,
                          ),
                        ),
                        const SizedBox(height: 14),
                        _WorkoutFieldGroup(
                          children: [
                            TextFormField(
                              controller: _goalController,
                              decoration: _workoutInputDecoration(
                                'Goal',
                                icon: Icons.flag_rounded,
                              ),
                            ),
                            TextFormField(
                              controller: _difficultyController,
                              decoration: _workoutInputDecoration(
                                'Difficulty',
                                icon: Icons.speed_rounded,
                              ),
                            ),
                            TextFormField(
                              controller: _durationController,
                              keyboardType: TextInputType.number,
                              decoration: _workoutInputDecoration(
                                'Duration weeks',
                                icon: Icons.date_range_rounded,
                              ),
                              validator: (value) {
                                final parsed = int.tryParse(
                                  value?.trim() ?? '',
                                );
                                if (parsed == null || parsed < 1) {
                                  return 'Min 1';
                                }
                                return null;
                              },
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          controller: _notesController,
                          minLines: 2,
                          maxLines: 4,
                          decoration: _workoutInputDecoration(
                            'Trainer notes',
                            icon: Icons.notes_rounded,
                          ),
                        ),
                        const SizedBox(height: 18),
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                'Weekly schedule',
                                style: TextStyle(
                                  color: _TrainerWorkoutColor.black,
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            _WorkoutTinyPill(
                              label:
                                  '${_selectedWeekDays.length} day${_selectedWeekDays.length == 1 ? '' : 's'}',
                              icon: Icons.calendar_today_rounded,
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        _WeekSchedulePicker(
                          selectedDays: _selectedWeekDays,
                          onToggle: _toggleWeekDay,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  _TrainerWorkoutSection(
                    title: 'Day Builder',
                    subtitle:
                        'Build one training day at a time with exercise prescriptions, rest, load targets, and notes.',
                    icon: Icons.view_day_rounded,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children:
                              (_selectedWeekDays.toList()..sort(
                                    (a, b) => _dayNumbers[a]!.compareTo(
                                      _dayNumbers[b]!,
                                    ),
                                  ))
                                  .map(
                                    (day) => ChoiceChip(
                                      label: Text(day),
                                      selected: _selectedDayKey == day,
                                      onSelected: (_) => _selectDay(day),
                                      selectedColor: _TrainerWorkoutColor
                                          .primaryEnd
                                          .withValues(alpha: 0.16),
                                      labelStyle: TextStyle(
                                        color: _selectedDayKey == day
                                            ? _TrainerWorkoutColor.primaryEnd
                                            : _TrainerWorkoutColor.black,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  )
                                  .toList(),
                        ),
                        const SizedBox(height: 14),
                        _WorkoutFieldGroup(
                          children: [
                            TextFormField(
                              controller: _dayLabelController,
                              decoration: _workoutInputDecoration(
                                'Day label',
                                icon: Icons.label_outline_rounded,
                              ),
                            ),
                            TextFormField(
                              controller: _focusController,
                              decoration: _workoutInputDecoration(
                                'Focus',
                                icon: Icons.center_focus_strong_rounded,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          controller: _dayNotesController,
                          minLines: 2,
                          maxLines: 3,
                          decoration: _workoutInputDecoration(
                            'Day notes',
                            icon: Icons.sticky_note_2_outlined,
                          ),
                        ),
                        const SizedBox(height: 18),
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                'Exercise library',
                                style: TextStyle(
                                  color: _TrainerWorkoutColor.black,
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            TextButton.icon(
                              onPressed: _savingExercise
                                  ? null
                                  : _openCreateExerciseSheet,
                              icon: const Icon(Icons.add_rounded),
                              label: const Text('Create'),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        if (widget.exercises.isEmpty)
                          EmptyStateView(
                            title: 'Exercise library is empty',
                            message:
                                'Create a gym exercise now, then use it in this workout day.',
                            icon: Icons.fitness_center_outlined,
                            action: FilledButton.icon(
                              onPressed: _savingExercise
                                  ? null
                                  : _openCreateExerciseSheet,
                              icon: const Icon(Icons.add_rounded),
                              label: const Text('Create exercise'),
                            ),
                          )
                        else ...[
                          TextField(
                            controller: _exerciseSearchController,
                            onChanged: (_) => setState(() {}),
                            decoration: _workoutInputDecoration(
                              'Search exercise',
                              icon: Icons.search_rounded,
                            ),
                          ),
                          const SizedBox(height: 14),
                          DropdownButtonFormField<int>(
                            key: ValueKey('exercise-$_selectedExerciseId'),
                            initialValue:
                                filteredExercises.any(
                                  (exercise) =>
                                      (exercise['id'] as num?)?.toInt() ==
                                      _selectedExerciseId,
                                )
                                ? _selectedExerciseId
                                : null,
                            isExpanded: true,
                            items: filteredExercises.take(40).map((exercise) {
                              final bodyPart =
                                  exercise['body_part_label']?.toString() ??
                                  _bodyPartLabel(
                                    exercise['body_part']?.toString() ?? '',
                                  );
                              return DropdownMenuItem<int>(
                                value: (exercise['id'] as num?)?.toInt(),
                                child: Text(
                                  '${exercise['name']?.toString() ?? 'Exercise'} • $bodyPart',
                                  overflow: TextOverflow.ellipsis,
                                ),
                              );
                            }).toList(),
                            onChanged: (value) =>
                                setState(() => _selectedExerciseId = value),
                            decoration: _workoutInputDecoration(
                              'Exercise picker',
                              icon: Icons.fitness_center_rounded,
                            ),
                          ),
                          const SizedBox(height: 14),
                          _SelectedExerciseBodyPart(
                            exercise: widget.exercises.firstWhere(
                              (exercise) =>
                                  (exercise['id'] as num?)?.toInt() ==
                                  _selectedExerciseId,
                              orElse: () => const <String, dynamic>{},
                            ),
                          ),
                          const SizedBox(height: 14),
                          _WorkoutFieldGroup(
                            children: [
                              TextFormField(
                                controller: _setsController,
                                keyboardType: TextInputType.number,
                                decoration: _workoutInputDecoration('Sets'),
                                validator: (value) {
                                  final sets = int.tryParse(
                                    value?.trim() ?? '',
                                  );
                                  if (sets == null || sets < 1) {
                                    return 'Required';
                                  }
                                  return null;
                                },
                              ),
                              TextFormField(
                                controller: _repsController,
                                decoration: _workoutInputDecoration('Reps'),
                              ),
                              TextFormField(
                                controller: _restController,
                                keyboardType: TextInputType.number,
                                decoration: _workoutInputDecoration('Rest sec'),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),
                          _WorkoutFieldGroup(
                            children: [
                              TextFormField(
                                controller: _targetWeightController,
                                keyboardType: TextInputType.number,
                                decoration: _workoutInputDecoration(
                                  'Target weight',
                                ),
                              ),
                              TextFormField(
                                controller: _exerciseNotesController,
                                decoration: _workoutInputDecoration(
                                  'Exercise notes',
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 16),
                          GradientButton(
                            label:
                                'Add exercise to ${_selectedDayKey.toUpperCase()}',
                            icon: Icons.add_circle_outline_rounded,
                            expanded: true,
                            onPressed: _addExerciseToCurrentDay,
                          ),
                        ],
                        const SizedBox(height: 20),
                        Text(
                          'Exercises for ${_selectedDayKey.toUpperCase()}',
                          style: TextStyle(
                            color: _TrainerWorkoutColor.black,
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 12),
                        if (selectedDayDraft.exercises.isEmpty)
                          const EmptyStateView(
                            title: 'No exercises added yet',
                            message:
                                'Pick an exercise, set the prescription, and add it to this day.',
                            icon: Icons.playlist_add_check_circle_outlined,
                          )
                        else
                          ...selectedDayDraft.exercises.asMap().entries.map(
                            (entry) => Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: _TrainerWorkoutTile(
                                title: entry.value.exerciseName,
                                subtitle:
                                    '${entry.value.sets} sets • ${entry.value.reps.isEmpty ? 'reps open' : entry.value.reps} • ${entry.value.restSeconds} sec rest',
                                badge: entry.value.bodyPartLabel,
                                icon: Icons.fitness_center_rounded,
                                actionLabel: 'Remove',
                                onAction: () => setState(() {
                                  selectedDayDraft.exercises.removeAt(
                                    entry.key,
                                  );
                                }),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                ],
                if (_workoutTabIndex == 0) ...[
                  _TrainerWorkoutSection(
                    title: 'Step 3: Current Member Workouts',
                    subtitle:
                        'View, edit, or delete only the workout plans previously assigned to $memberName.',
                    icon: Icons.people_alt_rounded,
                    child: Column(
                      children: [
                        if (selectedMemberPlans.isEmpty)
                          EmptyStateView(
                            title: 'No workout for $memberName',
                            message:
                                'Pick a library workout above and tap Assign. Custom workouts must be created in Workout Builder first.',
                            icon: Icons.view_week_outlined,
                          )
                        else
                          ...selectedMemberPlans.take(8).map((plan) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: _TrainerWorkoutTile(
                                title:
                                    plan['name']?.toString() ?? 'Workout plan',
                                subtitle:
                                    '${plan['goal']?.toString() ?? 'Goal not set'} • ${_mapList(plan['days']).length} day(s) • ${_map(plan['member'])['name']?.toString() ?? memberName}',
                                badge: plan['difficulty']?.toString(),
                                icon: Icons.edit_calendar_rounded,
                                actionLabel: 'View/Edit',
                                onAction: () => _openMemberPlanSheet(plan),
                                secondaryActionLabel: 'Delete',
                                onSecondaryAction: () =>
                                    _confirmDeleteMemberPlan(plan),
                              ),
                            );
                          }),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                ],
                GradientButton(
                  label: _savingPlan
                      ? (_workoutTabIndex == 0
                            ? 'Assigning workout...'
                            : 'Saving library workout...')
                      : (_workoutTabIndex == 0
                            ? 'Assign selected workout to $memberName'
                            : 'Save workout to library'),
                  icon: _workoutTabIndex == 0
                      ? Icons.assignment_turned_in_rounded
                      : Icons.library_add_check_rounded,
                  expanded: true,
                  onPressed: _savingPlan
                      ? null
                      : (_workoutTabIndex == 0
                            ? _assignSelectedTemplateToMember
                            : _saveLibraryWorkout),
                ),
              ],
            ),
          ),
      ],
    );
  }

  _WorkoutDayDraft _ensureDayDraft(String day) {
    return _dayDrafts.putIfAbsent(
      day,
      () => _WorkoutDayDraft(
        label: day,
        focus: '',
        notes: '',
        exercises: <_WorkoutExerciseDraft>[],
      ),
    );
  }

  void _persistCurrentDayFromFields() {
    final draft = _ensureDayDraft(_selectedDayKey);
    draft.label = _dayLabelController.text.trim().isEmpty
        ? _selectedDayKey
        : _dayLabelController.text.trim();
    draft.focus = _focusController.text.trim();
    draft.notes = _dayNotesController.text.trim();
  }

  void _loadDayIntoFields(String day) {
    final draft = _ensureDayDraft(day);
    _dayLabelController.text = draft.label;
    _focusController.text = draft.focus;
    _dayNotesController.text = draft.notes;
  }

  void _selectDay(String day) {
    setState(() {
      _persistCurrentDayFromFields();
      _selectedDayKey = day;
      _loadDayIntoFields(day);
    });
  }

  void _toggleWeekDay(String day) {
    setState(() {
      _persistCurrentDayFromFields();
      if (_selectedWeekDays.contains(day)) {
        if (_selectedWeekDays.length == 1) {
          return;
        }
        _selectedWeekDays.remove(day);
        _dayDrafts.remove(day);
        if (_selectedDayKey == day) {
          _selectedDayKey = _selectedWeekDays.first;
          _loadDayIntoFields(_selectedDayKey);
        }
      } else {
        _selectedWeekDays.add(day);
        _ensureDayDraft(day);
      }
    });
  }

  Map<String, dynamic> _selectedMember() {
    return widget.members.firstWhere(
      (item) => (item['member_id'] as num?)?.toInt() == _selectedMemberId,
      orElse: () => widget.members.firstOrNull ?? const <String, dynamic>{},
    );
  }

  Map<String, dynamic> _selectedTemplate() {
    return widget.templates.firstWhere(
      (item) => (item['id'] as num?)?.toInt() == _selectedTemplateId,
      orElse: () => const <String, dynamic>{},
    );
  }

  void _resetBuilder() {
    setState(() {
      _selectedTemplateId = (widget.templates.firstOrNull?['id'] as num?)
          ?.toInt();
      _planNameController.clear();
      _goalController.clear();
      _difficultyController.text = 'intermediate';
      _durationController.text = '4';
      _notesController.clear();
      _selectedWeekDays
        ..clear()
        ..addAll(<String>{'Mon', 'Wed', 'Fri'});
      _dayDrafts
        ..clear()
        ..addAll({
          'Mon': _WorkoutDayDraft(
            label: 'Mon',
            focus: '',
            notes: '',
            exercises: <_WorkoutExerciseDraft>[],
          ),
          'Wed': _WorkoutDayDraft(
            label: 'Wed',
            focus: '',
            notes: '',
            exercises: <_WorkoutExerciseDraft>[],
          ),
          'Fri': _WorkoutDayDraft(
            label: 'Fri',
            focus: '',
            notes: '',
            exercises: <_WorkoutExerciseDraft>[],
          ),
        });
      _selectedDayKey = 'Mon';
      _loadDayIntoFields(_selectedDayKey);
      _setsController.text = '4';
      _repsController.text = '10';
      _targetWeightController.clear();
      _restController.text = '60';
      _exerciseNotesController.clear();
    });
  }

  void _addExerciseToCurrentDay() {
    final selectedExercise = widget.exercises.firstWhere(
      (item) => (item['id'] as num?)?.toInt() == _selectedExerciseId,
      orElse: () => const <String, dynamic>{},
    );
    final exerciseId = (selectedExercise['id'] as num?)?.toInt();
    final sets = int.tryParse(_setsController.text.trim());
    final rest = int.tryParse(_restController.text.trim()) ?? 0;

    if (exerciseId == null || sets == null || sets < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select an exercise and valid sets.')),
      );
      return;
    }

    _persistCurrentDayFromFields();
    setState(() {
      _ensureDayDraft(_selectedDayKey).exercises.add(
        _WorkoutExerciseDraft(
          exerciseId: exerciseId,
          exerciseName: selectedExercise['name']?.toString() ?? 'Exercise',
          bodyPartLabel: _exerciseBodyPartLabel(selectedExercise),
          sets: sets,
          reps: _repsController.text.trim(),
          targetWeight: _targetWeightController.text.trim().isEmpty
              ? null
              : double.tryParse(_targetWeightController.text.trim()),
          restSeconds: rest,
          notes: _exerciseNotesController.text.trim(),
        ),
      );
      _targetWeightController.clear();
      _exerciseNotesController.clear();
    });
  }

  int? _activeGymId(Map<String, dynamic> selectedMember) {
    final memberGymId = (selectedMember['gym_id'] as num?)?.toInt();
    if (memberGymId != null) {
      return memberGymId;
    }
    final trainerProfile = _map(widget.contextData['trainer_profile']);
    return (_map(trainerProfile['assigned_gym'])['id'] as num?)?.toInt() ??
        (trainerProfile['gym_id'] as num?)?.toInt();
  }

  int? _activeBranchId(Map<String, dynamic> selectedMember) {
    final memberBranchId = (selectedMember['branch_id'] as num?)?.toInt();
    if (memberBranchId != null) {
      return memberBranchId;
    }
    final trainerProfile = _map(widget.contextData['trainer_profile']);
    return (_map(trainerProfile['assigned_branch'])['id'] as num?)?.toInt() ??
        (trainerProfile['branch_id'] as num?)?.toInt();
  }

  Future<void> _openCreateExerciseSheet() async {
    final selectedMember = widget.members.firstWhere(
      (item) => (item['member_id'] as num?)?.toInt() == _selectedMemberId,
      orElse: () => widget.members.firstOrNull ?? const <String, dynamic>{},
    );
    final gymId = _activeGymId(selectedMember);
    final branchId = _activeBranchId(selectedMember);

    if (gymId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'A trainer gym assignment is required to add exercises.',
          ),
        ),
      );
      return;
    }

    _newExerciseNameController.clear();
    _newExerciseBodyPartController.text = 'chest';
    _newExerciseMuscleController.clear();
    _newExerciseEquipmentController.clear();
    _newExerciseDifficultyController.text = 'beginner';
    _newExerciseInstructionsController.clear();

    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            Future<void> saveExercise() async {
              final name = _newExerciseNameController.text.trim();
              final bodyPart = _newExerciseBodyPartController.text.trim();
              final muscle = _newExerciseMuscleController.text.trim();
              if (name.isEmpty || bodyPart.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Exercise name and body part are required.'),
                  ),
                );
                return;
              }

              setSheetState(() => _savingExercise = true);
              try {
                final response = await widget.repository.createExercise({
                  'gym_id': gymId,
                  if (branchId != null) 'branch_id': branchId,
                  'name': name,
                  'muscle_group': muscle.isEmpty ? bodyPart : muscle,
                  'equipment':
                      _newExerciseEquipmentController.text.trim().isEmpty
                      ? null
                      : _newExerciseEquipmentController.text.trim(),
                  'difficulty':
                      _newExerciseDifficultyController.text.trim().isEmpty
                      ? null
                      : _newExerciseDifficultyController.text.trim(),
                  'instructions':
                      _newExerciseInstructionsController.text.trim().isEmpty
                      ? null
                      : _newExerciseInstructionsController.text.trim(),
                  'status': 'pending',
                });
                final createdExercise = _map(response['data']);
                final createdId = (createdExercise['id'] as num?)?.toInt();
                if (mounted && createdId != null) {
                  setState(() => _selectedExerciseId = createdId);
                }
                if (context.mounted) {
                  Navigator.of(context).pop(true);
                }
              } catch (exception) {
                if (context.mounted) {
                  ScaffoldMessenger.of(
                    context,
                  ).showSnackBar(SnackBar(content: Text(exception.toString())));
                }
              } finally {
                if (mounted) {
                  setState(() => _savingExercise = false);
                }
                if (context.mounted) {
                  setSheetState(() {});
                }
              }
            }

            return Padding(
              padding: EdgeInsets.only(
                left: 14,
                right: 14,
                top: 14,
                bottom: MediaQuery.of(context).viewInsets.bottom + 14,
              ),
              child: Material(
                color: Colors.transparent,
                child: Container(
                  constraints: BoxConstraints(
                    maxHeight: MediaQuery.sizeOf(context).height * 0.9,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(30),
                    boxShadow: <BoxShadow>[
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.16),
                        blurRadius: 34,
                        offset: const Offset(0, 18),
                      ),
                    ],
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.fromLTRB(20, 14, 20, 22),
                          decoration: const BoxDecoration(
                            gradient: _TrainerWorkoutColor.primaryGradient,
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Center(
                                child: Container(
                                  width: 42,
                                  height: 4,
                                  decoration: BoxDecoration(
                                    color: Colors.white.withValues(alpha: 0.55),
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 18),
                              Row(
                                children: [
                                  Container(
                                    width: 50,
                                    height: 50,
                                    decoration: BoxDecoration(
                                      color: Colors.white.withValues(
                                        alpha: 0.2,
                                      ),
                                      borderRadius: BorderRadius.circular(18),
                                    ),
                                    child: const Icon(
                                      Icons.sports_gymnastics_rounded,
                                      color: Colors.white,
                                    ),
                                  ),
                                  const SizedBox(width: 14),
                                  const Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          'Create Exercise',
                                          style: TextStyle(
                                            color: Colors.white,
                                            fontSize: 19,
                                            fontWeight: FontWeight.w800,
                                          ),
                                        ),
                                        SizedBox(height: 5),
                                        Text(
                                          'Add a trainer-created move to your gym exercise library.',
                                          style: TextStyle(
                                            color: Colors.white70,
                                            fontSize: 12,
                                            height: 1.35,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  IconButton(
                                    onPressed: _savingExercise
                                        ? null
                                        : () => Navigator.of(context).pop(),
                                    icon: const Icon(
                                      Icons.close_rounded,
                                      color: Colors.white,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 18),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: [
                                  _WorkoutTinyPill(
                                    label: 'Gym $gymId',
                                    icon: Icons.apartment_rounded,
                                    inverted: true,
                                  ),
                                  if (branchId != null)
                                    _WorkoutTinyPill(
                                      label: 'Branch $branchId',
                                      icon: Icons.location_on_outlined,
                                      inverted: true,
                                    ),
                                  const _WorkoutTinyPill(
                                    label: 'Pending approval',
                                    icon: Icons.hourglass_top_rounded,
                                    inverted: true,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(20, 20, 20, 22),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              TextField(
                                controller: _newExerciseNameController,
                                textInputAction: TextInputAction.next,
                                decoration: _workoutInputDecoration(
                                  'Exercise name',
                                  icon: Icons.fitness_center_rounded,
                                ),
                              ),
                              const SizedBox(height: 14),
                              DropdownButtonFormField<String>(
                                initialValue:
                                    _newExerciseBodyPartController.text,
                                isExpanded: true,
                                items: _bodyParts
                                    .map(
                                      (bodyPart) => DropdownMenuItem<String>(
                                        value: bodyPart,
                                        child: Text(
                                          _bodyPartLabel(bodyPart),
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                    )
                                    .toList(),
                                onChanged: (value) {
                                  if (value == null) {
                                    return;
                                  }
                                  _newExerciseBodyPartController.text = value;
                                },
                                decoration: _workoutInputDecoration(
                                  'Body part',
                                  icon: Icons.accessibility_new_rounded,
                                ),
                              ),
                              const SizedBox(height: 14),
                              TextField(
                                controller: _newExerciseMuscleController,
                                textInputAction: TextInputAction.next,
                                decoration: _workoutInputDecoration(
                                  'Muscle group override',
                                  icon: Icons.center_focus_strong_rounded,
                                ),
                              ),
                              const SizedBox(height: 14),
                              _WorkoutFieldGroup(
                                children: [
                                  TextField(
                                    controller: _newExerciseEquipmentController,
                                    decoration: _workoutInputDecoration(
                                      'Equipment',
                                      icon: Icons.inventory_2_outlined,
                                    ),
                                  ),
                                  TextField(
                                    controller:
                                        _newExerciseDifficultyController,
                                    decoration: _workoutInputDecoration(
                                      'Difficulty',
                                      icon: Icons.speed_rounded,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 14),
                              TextField(
                                controller: _newExerciseInstructionsController,
                                minLines: 4,
                                maxLines: 6,
                                decoration: _workoutInputDecoration(
                                  'Coaching instructions',
                                  icon: Icons.notes_rounded,
                                ),
                              ),
                              const SizedBox(height: 16),
                              Container(
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: _TrainerWorkoutColor.field,
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: const Row(
                                  children: [
                                    Icon(
                                      Icons.info_outline_rounded,
                                      color: _TrainerWorkoutColor.primaryEnd,
                                      size: 20,
                                    ),
                                    SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        'After saving, refresh loads this exercise into the picker so it can be added to workout days.',
                                        style: TextStyle(
                                          color: _TrainerWorkoutColor.gray,
                                          fontSize: 11,
                                          height: 1.35,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 20),
                              GradientButton(
                                label: _savingExercise
                                    ? 'Creating exercise...'
                                    : 'Create exercise',
                                icon: Icons.add_circle_outline_rounded,
                                expanded: true,
                                onPressed: _savingExercise
                                    ? null
                                    : saveExercise,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        );
      },
    );

    if (created == true && mounted) {
      await widget.onRefresh();
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Exercise added to gym library.')),
      );
    }
  }

  Map<String, dynamic> _templatePayload({
    required int gymId,
    int? branchId,
    required int durationWeeks,
    required List<String> days,
    required List<Map<String, dynamic>> payloadDays,
  }) {
    return <String, dynamic>{
      'gym_id': gymId,
      if (branchId != null) 'branch_id': branchId,
      'name': _planNameController.text.trim(),
      'goal': _goalController.text.trim().isEmpty
          ? null
          : _goalController.text.trim(),
      'difficulty': _difficultyController.text.trim().isEmpty
          ? null
          : _difficultyController.text.trim(),
      'duration_weeks': durationWeeks,
      'weekly_schedule': days,
      'notes': _notesController.text.trim().isEmpty
          ? null
          : _notesController.text.trim(),
      'status': 'active',
      'days': payloadDays,
    };
  }

  List<Map<String, dynamic>>? _draftPayloadDays(List<String> days) {
    final payloadDays = <Map<String, dynamic>>[];
    for (final day in days) {
      final draft = _ensureDayDraft(day);
      if (draft.exercises.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Add at least one exercise for $day.')),
        );
        return null;
      }
      payloadDays.add({
        'day_number': _dayNumbers[day],
        'label': draft.label,
        'focus': draft.focus.isEmpty ? null : draft.focus,
        'notes': draft.notes.isEmpty ? null : draft.notes,
        'exercises': draft.exercises.asMap().entries.map((entry) {
          final exercise = entry.value;
          return {
            'exercise_id': exercise.exerciseId,
            'sort_order': entry.key + 1,
            'sets': exercise.sets,
            'reps': exercise.reps.isEmpty ? null : exercise.reps,
            'target_weight': exercise.targetWeight,
            'rest_seconds': exercise.restSeconds,
            'notes': exercise.notes.isEmpty ? null : exercise.notes,
          };
        }).toList(),
      });
    }
    return payloadDays;
  }

  List<Map<String, dynamic>> _planPayloadDays(Map<String, dynamic> plan) {
    return _mapList(plan['days']).map((day) {
      return {
        'day_number': (day['day_number'] as num?)?.toInt() ?? 1,
        'label': day['label'],
        'focus': day['focus'],
        'notes': day['notes'],
        'exercises': _mapList(day['exercises']).asMap().entries.map((entry) {
          final exercise = entry.value;
          return {
            'exercise_id': (exercise['exercise_id'] as num?)?.toInt() ?? 0,
            'sort_order':
                (exercise['sort_order'] as num?)?.toInt() ?? entry.key + 1,
            'sets': (exercise['sets'] as num?)?.toInt() ?? 1,
            'reps': exercise['reps'],
            'target_weight': exercise['target_weight'],
            'rest_seconds': (exercise['rest_seconds'] as num?)?.toInt(),
            'notes': exercise['notes'],
          };
        }).toList(),
      };
    }).toList();
  }

  Future<void> _assignSelectedTemplateToMember() async {
    final selectedMember = _selectedMember();
    final selectedTemplate = _selectedTemplate();
    final memberId = (selectedMember['member_id'] as num?)?.toInt();
    final gymId = (selectedMember['gym_id'] as num?)?.toInt();
    final branchId = (selectedMember['branch_id'] as num?)?.toInt();
    final templateId = (selectedTemplate['id'] as num?)?.toInt();

    if (memberId == null || gymId == null || branchId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a valid assigned member.')),
      );
      return;
    }
    if (templateId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a library workout first.')),
      );
      return;
    }

    setState(() => _savingPlan = true);
    try {
      await widget.repository.assignWorkoutTemplate(templateId, {
        'gym_id': gymId,
        'branch_id': branchId,
        'member_ids': <int>[memberId],
        'starts_on': DateTime.now().toIso8601String().split('T').first,
      });
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${selectedTemplate['name']?.toString() ?? 'Workout'} assigned to ${_map(selectedMember['member'])['name']?.toString() ?? 'member'}.',
          ),
        ),
      );
      await widget.onRefresh();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _savingPlan = false);
      }
    }
  }

  Future<void> _openMemberPlanSheet(Map<String, dynamic> plan) async {
    final nameController = TextEditingController(
      text: plan['name']?.toString() ?? '',
    );
    final goalController = TextEditingController(
      text: plan['goal']?.toString() ?? '',
    );
    final difficultyController = TextEditingController(
      text: plan['difficulty']?.toString() ?? 'intermediate',
    );
    final durationController = TextEditingController(
      text: '${(plan['duration_weeks'] as num?)?.toInt() ?? 4}',
    );
    final notesController = TextEditingController(
      text: plan['notes']?.toString() ?? '',
    );
    var saving = false;

    try {
      final updated = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: Colors.transparent,
        builder: (sheetContext) {
          return StatefulBuilder(
            builder: (context, setSheetState) {
              Future<void> savePlan() async {
                final planId = (plan['id'] as num?)?.toInt();
                final duration = int.tryParse(durationController.text.trim());
                if (planId == null ||
                    nameController.text.trim().isEmpty ||
                    duration == null ||
                    duration < 1) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Name and valid duration are required.'),
                    ),
                  );
                  return;
                }

                setSheetState(() => saving = true);
                try {
                  await widget.repository.updateWorkoutPlan(planId, {
                    'name': nameController.text.trim(),
                    'goal': goalController.text.trim().isEmpty
                        ? null
                        : goalController.text.trim(),
                    'difficulty': difficultyController.text.trim().isEmpty
                        ? null
                        : difficultyController.text.trim(),
                    'duration_weeks': duration,
                    'weekly_schedule': _list(plan['weekly_schedule']),
                    'notes': notesController.text.trim().isEmpty
                        ? null
                        : notesController.text.trim(),
                    'status': plan['status']?.toString() ?? 'active',
                    'days': _planPayloadDays(plan),
                  });
                  if (context.mounted) {
                    Navigator.of(context).pop(true);
                  }
                } catch (exception) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(exception.toString())),
                    );
                  }
                } finally {
                  if (context.mounted) {
                    setSheetState(() => saving = false);
                  }
                }
              }

              return Padding(
                padding: EdgeInsets.only(
                  left: 14,
                  right: 14,
                  top: 14,
                  bottom: MediaQuery.of(context).viewInsets.bottom + 14,
                ),
                child: Material(
                  color: Colors.transparent,
                  child: Container(
                    constraints: BoxConstraints(
                      maxHeight: MediaQuery.sizeOf(context).height * 0.9,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(30),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.16),
                          blurRadius: 34,
                          offset: const Offset(0, 18),
                        ),
                      ],
                    ),
                    child: SingleChildScrollView(
                      physics: const BouncingScrollPhysics(),
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 48,
                                height: 48,
                                decoration: BoxDecoration(
                                  gradient: _TrainerWorkoutColor.softGradient,
                                  borderRadius: BorderRadius.circular(17),
                                ),
                                child: const Icon(
                                  Icons.edit_calendar_rounded,
                                  color: _TrainerWorkoutColor.primaryEnd,
                                ),
                              ),
                              const SizedBox(width: 12),
                              const Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Member Workout',
                                      style: TextStyle(
                                        color: _TrainerWorkoutColor.black,
                                        fontSize: 18,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    SizedBox(height: 4),
                                    Text(
                                      'Edit basic plan details. Exercise structure stays from the assigned library workout.',
                                      style: TextStyle(
                                        color: _TrainerWorkoutColor.gray,
                                        fontSize: 11,
                                        height: 1.35,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              IconButton(
                                onPressed: saving
                                    ? null
                                    : () => Navigator.of(context).pop(false),
                                icon: const Icon(Icons.close_rounded),
                              ),
                            ],
                          ),
                          const SizedBox(height: 20),
                          TextField(
                            controller: nameController,
                            decoration: _workoutInputDecoration(
                              'Workout name',
                              icon: Icons.drive_file_rename_outline_rounded,
                            ),
                          ),
                          const SizedBox(height: 14),
                          TextField(
                            controller: goalController,
                            decoration: _workoutInputDecoration(
                              'Goal',
                              icon: Icons.flag_rounded,
                            ),
                          ),
                          const SizedBox(height: 14),
                          _WorkoutFieldGroup(
                            children: [
                              TextField(
                                controller: difficultyController,
                                decoration: _workoutInputDecoration(
                                  'Difficulty',
                                  icon: Icons.speed_rounded,
                                ),
                              ),
                              TextField(
                                controller: durationController,
                                keyboardType: TextInputType.number,
                                decoration: _workoutInputDecoration(
                                  'Duration weeks',
                                  icon: Icons.date_range_rounded,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),
                          TextField(
                            controller: notesController,
                            minLines: 3,
                            maxLines: 5,
                            decoration: _workoutInputDecoration(
                              'Trainer notes',
                              icon: Icons.notes_rounded,
                            ),
                          ),
                          const SizedBox(height: 18),
                          ..._mapList(plan['days']).map((day) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: _TrainerWorkoutTile(
                                title:
                                    day['label']?.toString() ?? 'Workout day',
                                subtitle:
                                    '${day['focus']?.toString() ?? 'Training day'} • ${_mapList(day['exercises']).length} exercise(s)',
                                icon: Icons.view_day_rounded,
                              ),
                            );
                          }),
                          const SizedBox(height: 10),
                          GradientButton(
                            label: saving
                                ? 'Updating workout...'
                                : 'Update member workout',
                            icon: Icons.system_update_alt_rounded,
                            expanded: true,
                            onPressed: saving ? null : savePlan,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            },
          );
        },
      );
      if (updated == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Member workout updated.')),
        );
        await widget.onRefresh();
      }
    } finally {
      nameController.dispose();
      goalController.dispose();
      difficultyController.dispose();
      durationController.dispose();
      notesController.dispose();
    }
  }

  Future<void> _confirmDeleteMemberPlan(Map<String, dynamic> plan) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => ConfirmationDialog(
        title: 'Delete workout plan?',
        message:
            'This removes "${plan['name']?.toString() ?? 'this workout'}" from the selected member. The library workout remains saved.',
        confirmLabel: 'Delete',
      ),
    );
    if (confirmed != true) {
      return;
    }

    final planId = (plan['id'] as num?)?.toInt();
    if (planId == null) {
      return;
    }

    setState(() => _savingPlan = true);
    try {
      await widget.repository.deleteWorkoutPlan(planId);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Member workout deleted.')));
      await widget.onRefresh();
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _savingPlan = false);
      }
    }
  }

  Future<void> _saveLibraryWorkout() async {
    if (_planNameController.text.trim().isEmpty) {
      _planNameController.text = 'Custom workout';
    }

    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    _persistCurrentDayFromFields();
    final selectedMember = _selectedMember();
    final gymId = _activeGymId(selectedMember);
    final branchId = _activeBranchId(selectedMember);
    final days = _selectedWeekDays.toList()
      ..sort((a, b) => _dayNumbers[a]!.compareTo(_dayNumbers[b]!));
    if (days.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select at least one training day.')),
      );
      return;
    }
    if (gymId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('A trainer gym assignment is required to save.'),
        ),
      );
      return;
    }
    final payloadDays = _draftPayloadDays(days);
    if (payloadDays == null) {
      return;
    }
    final durationWeeks = int.tryParse(_durationController.text.trim()) ?? 4;

    setState(() => _savingPlan = true);
    try {
      final response = await widget.repository.createWorkoutTemplate(
        _templatePayload(
          gymId: gymId,
          branchId: branchId,
          durationWeeks: durationWeeks,
          days: days,
          payloadDays: payloadDays,
        ),
      );
      final createdTemplateId = (_map(response['data'])['id'] as num?)?.toInt();
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Workout saved to library. Select a member to assign.'),
        ),
      );
      _resetBuilder();
      await widget.onRefresh();
      if (mounted) {
        setState(() {
          _workoutTabIndex = 0;
          if (createdTemplateId != null) {
            _selectedTemplateId = createdTemplateId;
          }
        });
      }
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _savingPlan = false);
      }
    }
  }
}

class _WorkoutDayDraft {
  _WorkoutDayDraft({
    required this.label,
    required this.focus,
    required this.notes,
    required this.exercises,
  });

  String label;
  String focus;
  String notes;
  List<_WorkoutExerciseDraft> exercises;
}

class _WorkoutExerciseDraft {
  _WorkoutExerciseDraft({
    required this.exerciseId,
    required this.exerciseName,
    required this.bodyPartLabel,
    required this.sets,
    required this.reps,
    required this.targetWeight,
    required this.restSeconds,
    required this.notes,
  });

  final int exerciseId;
  final String exerciseName;
  final String bodyPartLabel;
  final int sets;
  final String reps;
  final double? targetWeight;
  final int restSeconds;
  final String notes;
}

String _exerciseBodyPartLabel(Map<String, dynamic> exercise) {
  final label = exercise['body_part_label']?.toString().trim();
  if (label != null && label.isNotEmpty) {
    return label;
  }
  return _bodyPartLabel(exercise['body_part']?.toString() ?? '');
}

String _bodyPartLabel(String value) {
  final normalized = value.trim().replaceAll('-', '_').toLowerCase();
  if (normalized.isEmpty) {
    return 'Other';
  }
  if (normalized == 'full_body') {
    return 'Full Body';
  }
  return normalized
      .split('_')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

class _SelectedExerciseBodyPart extends StatelessWidget {
  const _SelectedExerciseBodyPart({required this.exercise});

  final Map<String, dynamic> exercise;

  @override
  Widget build(BuildContext context) {
    if (exercise.isEmpty) {
      return const SizedBox.shrink();
    }
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _TrainerWorkoutColor.field,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              gradient: _TrainerWorkoutColor.softGradient,
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(
              Icons.accessibility_new_rounded,
              color: _TrainerWorkoutColor.primaryEnd,
              size: 19,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Body part',
                  style: TextStyle(
                    color: _TrainerWorkoutColor.gray,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  _exerciseBodyPartLabel(exercise),
                  style: const TextStyle(
                    color: _TrainerWorkoutColor.black,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          if ((exercise['is_global'] as bool?) == true)
            const _WorkoutTinyPill(label: 'Global', icon: Icons.public_rounded),
        ],
      ),
    );
  }
}

class _TrainerWorkoutTabs extends StatelessWidget {
  const _TrainerWorkoutTabs({
    required this.selectedIndex,
    required this.onChanged,
  });

  final int selectedIndex;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(
        color: _TrainerWorkoutColor.field,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Expanded(
            child: _TrainerWorkoutTabButton(
              label: 'Assign Workout',
              icon: Icons.assignment_turned_in_rounded,
              selected: selectedIndex == 0,
              onTap: () => onChanged(0),
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: _TrainerWorkoutTabButton(
              label: 'Workout Builder',
              icon: Icons.construction_rounded,
              selected: selectedIndex == 1,
              onTap: () => onChanged(1),
            ),
          ),
        ],
      ),
    );
  }
}

class _TrainerWorkoutTabButton extends StatelessWidget {
  const _TrainerWorkoutTabButton({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
        decoration: BoxDecoration(
          color: selected ? Colors.white : Colors.transparent,
          borderRadius: BorderRadius.circular(14),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
                    blurRadius: 16,
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
              size: 17,
              color: selected
                  ? _TrainerWorkoutColor.primaryEnd
                  : _TrainerWorkoutColor.gray,
            ),
            const SizedBox(width: 7),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: selected
                      ? _TrainerWorkoutColor.black
                      : _TrainerWorkoutColor.gray,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TrainerWorkoutHero extends StatelessWidget {
  const _TrainerWorkoutHero({
    required this.planCount,
    required this.templateCount,
    required this.exerciseCount,
    required this.memberCount,
  });

  final int planCount;
  final int templateCount;
  final int exerciseCount;
  final int memberCount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: _TrainerWorkoutColor.primaryGradient,
        borderRadius: BorderRadius.circular(26),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: _TrainerWorkoutColor.primaryEnd.withValues(alpha: 0.28),
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
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: const Icon(
                  Icons.fitness_center_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 14),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Workout Builder',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    SizedBox(height: 5),
                    Text(
                      'Build, reuse, assign, and edit member workouts.',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                        height: 1.35,
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
                child: _TrainerWorkoutHeroMetric(
                  value: '$planCount',
                  label: 'Plans',
                  icon: Icons.view_week_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _TrainerWorkoutHeroMetric(
                  value: '$templateCount',
                  label: 'Library',
                  icon: Icons.library_books_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _TrainerWorkoutHeroMetric(
                  value: '$exerciseCount',
                  label: 'Exercises',
                  icon: Icons.sports_gymnastics_rounded,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _WorkoutTinyPill(
            label: '$memberCount assigned members',
            icon: Icons.groups_rounded,
            inverted: true,
          ),
        ],
      ),
    );
  }
}

class _TrainerWorkoutHeroMetric extends StatelessWidget {
  const _TrainerWorkoutHeroMetric({
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
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
      ),
      child: Column(
        children: <Widget>[
          Icon(icon, color: Colors.white, size: 18),
          const SizedBox(height: 8),
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

class _TrainerWorkoutSection extends StatelessWidget {
  const _TrainerWorkoutSection({
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
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _TrainerWorkoutColor.border),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
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
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  gradient: _TrainerWorkoutColor.softGradient,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(icon, color: _TrainerWorkoutColor.primaryEnd),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: const TextStyle(
                        color: _TrainerWorkoutColor.black,
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        color: _TrainerWorkoutColor.gray,
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

class _TrainerWorkoutTile extends StatelessWidget {
  const _TrainerWorkoutTile({
    required this.title,
    required this.subtitle,
    required this.icon,
    this.badge,
    this.actionLabel,
    this.onAction,
    this.secondaryActionLabel,
    this.onSecondaryAction,
  });

  final String title;
  final String subtitle;
  final String? badge;
  final IconData icon;
  final String? actionLabel;
  final VoidCallback? onAction;
  final String? secondaryActionLabel;
  final VoidCallback? onSecondaryAction;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _TrainerWorkoutColor.field,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        children: <Widget>[
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              gradient: _TrainerWorkoutColor.softGradient,
              borderRadius: BorderRadius.circular(17),
            ),
            child: Icon(icon, color: _TrainerWorkoutColor.primaryEnd),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _TrainerWorkoutColor.black,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _TrainerWorkoutColor.gray,
                    fontSize: 11,
                    height: 1.35,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              if ((badge ?? '').trim().isNotEmpty)
                _WorkoutTinyPill(label: badge!, icon: Icons.bolt_rounded),
              if ((actionLabel ?? '').trim().isNotEmpty) ...[
                const SizedBox(height: 8),
                TextButton(
                  onPressed: onAction,
                  style: TextButton.styleFrom(
                    foregroundColor: _TrainerWorkoutColor.primaryEnd,
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    minimumSize: const Size(0, 30),
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: Text(
                    actionLabel!,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
              ],
              if ((secondaryActionLabel ?? '').trim().isNotEmpty) ...[
                const SizedBox(height: 2),
                TextButton(
                  onPressed: onSecondaryAction,
                  style: TextButton.styleFrom(
                    foregroundColor: Colors.redAccent,
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    minimumSize: const Size(0, 30),
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: Text(
                    secondaryActionLabel!,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _MemberWorkoutSnapshot extends StatelessWidget {
  const _MemberWorkoutSnapshot({required this.plans});

  final List<Map<String, dynamic>> plans;

  @override
  Widget build(BuildContext context) {
    final title = plans.isEmpty
        ? 'No current workout'
        : '${plans.length} current workout${plans.length == 1 ? '' : 's'}';
    final message = plans.isEmpty
        ? 'This member does not have a trainer-assigned workout yet.'
        : plans
              .take(2)
              .map((plan) => plan['name']?.toString() ?? 'Workout')
              .join(' • ');

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _TrainerWorkoutColor.field,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              gradient: _TrainerWorkoutColor.softGradient,
              borderRadius: BorderRadius.circular(15),
            ),
            child: Icon(
              plans.isEmpty
                  ? Icons.playlist_add_rounded
                  : Icons.assignment_turned_in_rounded,
              color: _TrainerWorkoutColor.primaryEnd,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: _TrainerWorkoutColor.black,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  message,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _TrainerWorkoutColor.gray,
                    fontSize: 11,
                    height: 1.35,
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

class _WorkoutFieldGroup extends StatelessWidget {
  const _WorkoutFieldGroup({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 620) {
          return Column(
            children: children
                .map(
                  (child) => Padding(
                    padding: EdgeInsets.only(
                      bottom: child == children.last ? 0 : 14,
                    ),
                    child: child,
                  ),
                )
                .toList(),
          );
        }

        return Row(
          children: children
              .map(
                (child) => Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(
                      right: child == children.last ? 0 : 12,
                    ),
                    child: child,
                  ),
                ),
              )
              .toList(),
        );
      },
    );
  }
}

class _WorkoutTinyPill extends StatelessWidget {
  const _WorkoutTinyPill({
    required this.label,
    required this.icon,
    this.inverted = false,
  });

  final String label;
  final IconData icon;
  final bool inverted;

  @override
  Widget build(BuildContext context) {
    final textColor = inverted ? Colors.white : _TrainerWorkoutColor.primaryEnd;
    final bgColor = inverted
        ? Colors.white.withValues(alpha: 0.16)
        : Colors.white;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: inverted
              ? Colors.white.withValues(alpha: 0.2)
              : _TrainerWorkoutColor.border,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, color: textColor, size: 13),
          const SizedBox(width: 5),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 130),
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: textColor,
                fontSize: 10,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

InputDecoration _workoutInputDecoration(String label, {IconData? icon}) {
  return InputDecoration(
    labelText: label,
    prefixIcon: icon == null ? null : Icon(icon, size: 20),
    filled: true,
    fillColor: _TrainerWorkoutColor.field,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(color: _TrainerWorkoutColor.border),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(color: _TrainerWorkoutColor.border),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(
        color: _TrainerWorkoutColor.primaryEnd,
        width: 1.5,
      ),
    ),
  );
}

class _TrainerWorkoutColor {
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

class _ChatPage extends StatelessWidget {
  const _ChatPage({
    required this.members,
    required this.conversations,
    required this.loading,
    required this.onSelectMember,
    this.error,
    this.onRefresh,
  });

  final List<Map<String, dynamic>> members;
  final List<Map<String, dynamic>> conversations;
  final bool loading;
  final String? error;
  final ValueChanged<int?> onSelectMember;
  final Future<void> Function()? onRefresh;

  @override
  Widget build(BuildContext context) {
    final inbox = _TrainerChatInboxList(
      members: members,
      selectedMemberId: null,
      conversationForMember: _conversationForMember,
      onSelectMember: onSelectMember,
    );

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
          child: GlassCard(
            gradient: LinearGradient(
              colors: [
                const Color(0xFF111827),
                Theme.of(context).colorScheme.primary.withValues(alpha: 0.20),
              ],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.10),
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: const Icon(Icons.forum_rounded),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Messages',
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                          Text(
                            '${members.length} private member chats',
                            style: Theme.of(context).textTheme.bodySmall,
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
                if (error != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    error!,
                    style: Theme.of(
                      context,
                    ).textTheme.bodySmall?.copyWith(color: AppColors.error),
                  ),
                ],
              ],
            ),
          ),
        ),
        Expanded(child: inbox),
      ],
    );
  }

  Map<String, dynamic> _conversationForMember(int? memberId) {
    if (memberId == null) {
      return const <String, dynamic>{};
    }

    for (final conversation in conversations) {
      if (_intValue(conversation['member_id']) == memberId) {
        return conversation;
      }
      if (_intValue(_map(conversation['peer'])['id']) == memberId) {
        return conversation;
      }
    }

    return const <String, dynamic>{};
  }
}

class _TrainerChatInboxList extends StatelessWidget {
  const _TrainerChatInboxList({
    required this.members,
    required this.selectedMemberId,
    required this.conversationForMember,
    required this.onSelectMember,
  });

  final List<Map<String, dynamic>> members;
  final int? selectedMemberId;
  final Map<String, dynamic> Function(int? memberId) conversationForMember;
  final ValueChanged<int?> onSelectMember;

  @override
  Widget build(BuildContext context) {
    if (members.isEmpty) {
      return const EmptyStateView(
        title: 'No members assigned',
        message: 'Assigned members will appear here as chat boxes.',
        icon: Icons.group_outlined,
      );
    }

    final compact = selectedMemberId != null;

    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      itemCount: members.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final assignment = members[index];
        final memberId = (assignment['member_id'] as num?)?.toInt();
        final member = _map(assignment['member']);
        final conversation = conversationForMember(memberId);
        final lastMessage = _map(conversation['last_message']);
        final isSelected = memberId == selectedMemberId;
        final preview = lastMessage.isNotEmpty
            ? lastMessage['body']?.toString() ?? 'Message'
            : 'Tap to open private thread';

        return _ChatInboxCard(
          name: member['name']?.toString() ?? 'Member',
          avatarUrl:
              member['avatar']?.toString() ??
              member['profile_photo_url']?.toString(),
          preview: preview,
          time: lastMessage.isEmpty
              ? 'New'
              : _chatTime(
                  lastMessage['created_at'] ?? conversation['updated_at'],
                ),
          unreadCount: isSelected
              ? 0
              : (_intValue(conversation['unread_count']) ?? 0),
          isSelected: isSelected,
          compact: compact,
          onTap: memberId == null ? null : () => onSelectMember(memberId),
        );
      },
    );
  }
}

class _ChatInboxCard extends StatelessWidget {
  const _ChatInboxCard({
    required this.name,
    required this.preview,
    required this.time,
    required this.unreadCount,
    required this.isSelected,
    required this.onTap,
    this.compact = false,
    this.avatarUrl,
  });

  final String name;
  final String preview;
  final String time;
  final int unreadCount;
  final bool isSelected;
  final VoidCallback? onTap;
  final bool compact;
  final String? avatarUrl;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: BoxConstraints(minHeight: compact ? 72 : 88),
      child: InkWell(
        borderRadius: BorderRadius.circular(26),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: EdgeInsets.symmetric(
            horizontal: compact ? 12 : 16,
            vertical: compact ? 8 : 14,
          ),
          decoration: BoxDecoration(
            gradient: isSelected
                ? LinearGradient(
                    colors: [
                      Theme.of(context).colorScheme.primary,
                      Theme.of(context).colorScheme.secondary,
                    ],
                  )
                : null,
            color: isSelected ? null : AppColors.surfaceOverlay,
            borderRadius: BorderRadius.circular(26),
            border: Border.all(
              color: isSelected ? Colors.white24 : AppColors.strokeStrong,
            ),
            boxShadow: const [
              BoxShadow(
                color: AppColors.shadow,
                blurRadius: 18,
                offset: Offset(0, 10),
              ),
            ],
          ),
          child: Row(
            children: [
              CircleAvatar(
                radius: compact ? 22 : 28,
                backgroundColor: isSelected
                    ? Colors.white.withValues(alpha: 0.20)
                    : AppColors.surfaceStrong,
                backgroundImage:
                    avatarUrl != null && avatarUrl!.trim().isNotEmpty
                    ? NetworkImage(avatarUrl!)
                    : null,
                child: avatarUrl == null || avatarUrl!.trim().isEmpty
                    ? Text(
                        name.trim().isEmpty ? 'M' : name.trim()[0],
                        style: TextStyle(
                          color: isSelected
                              ? Colors.white
                              : AppColors.textPrimary,
                          fontWeight: FontWeight.w800,
                        ),
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: isSelected
                            ? Colors.white
                            : AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    SizedBox(height: compact ? 2 : 6),
                    Text(
                      preview,
                      maxLines: compact ? 1 : 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: isSelected
                            ? Colors.white70
                            : AppColors.textMuted,
                      ),
                    ),
                    if (!compact) const SizedBox(height: 8),
                    if (!compact)
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              time,
                              style: Theme.of(context).textTheme.labelSmall
                                  ?.copyWith(
                                    color: isSelected
                                        ? Colors.white70
                                        : AppColors.textMuted,
                                  ),
                            ),
                          ),
                          if (unreadCount > 0)
                            _ChatUnreadBadge(
                              unreadCount: unreadCount,
                              isSelected: isSelected,
                            ),
                        ],
                      ),
                  ],
                ),
              ),
              if (compact) ...[
                const SizedBox(width: 8),
                Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      time,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: isSelected
                            ? Colors.white70
                            : AppColors.textMuted,
                      ),
                    ),
                    if (unreadCount > 0) ...[
                      const SizedBox(height: 4),
                      _ChatUnreadBadge(
                        unreadCount: unreadCount,
                        isSelected: isSelected,
                      ),
                    ],
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ChatUnreadBadge extends StatelessWidget {
  const _ChatUnreadBadge({required this.unreadCount, required this.isSelected});

  final int unreadCount;
  final bool isSelected;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: isSelected
            ? Colors.white
            : Theme.of(context).colorScheme.primary,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        unreadCount > 99 ? '99+' : '$unreadCount',
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: isSelected ? AppColors.textPrimary : Colors.white,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _ChatThreadHeader extends StatelessWidget {
  const _ChatThreadHeader({
    required this.member,
    required this.loading,
    required this.onRefresh,
  });

  final Map<String, dynamic> member;
  final bool loading;
  final Future<void> Function()? onRefresh;

  @override
  Widget build(BuildContext context) {
    final name = member['name']?.toString() ?? 'Member';
    final avatarUrl =
        member['avatar']?.toString() ?? member['profile_photo_url']?.toString();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.stroke)),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 24,
            backgroundColor: AppColors.surfaceStrong,
            backgroundImage: avatarUrl != null && avatarUrl.trim().isNotEmpty
                ? NetworkImage(avatarUrl)
                : null,
            child: avatarUrl == null || avatarUrl.trim().isEmpty
                ? Text(
                    name.trim().isEmpty ? 'M' : name.trim()[0],
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  )
                : null,
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
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  'Private member conversation',
                  style: Theme.of(context).textTheme.bodySmall,
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
    );
  }
}

class _TrainerChatThreadScreen extends StatefulWidget {
  const _TrainerChatThreadScreen({
    required this.repository,
    required this.socket,
    required this.currentUserId,
    required this.memberId,
    required this.member,
  });

  final TrainerRepository repository;
  final io.Socket? socket;
  final int? currentUserId;
  final int memberId;
  final Map<String, dynamic> member;

  @override
  State<_TrainerChatThreadScreen> createState() =>
      _TrainerChatThreadScreenState();
}

class _TrainerChatThreadScreenState extends State<_TrainerChatThreadScreen> {
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
    final message = _normalizeThreadMessage(_map(data)['message'] ?? data);
    final senderId = _intValue(message['sender_id']);
    final recipientId = _intValue(message['recipient_id']);
    if (senderId == widget.memberId || recipientId == widget.memberId) {
      _upsert(message);
      unawaited(widget.repository.markChatRead(widget.memberId));
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.repository.fetchChatMessages(
        widget.memberId,
      );
      final messages =
          _mapList(response['data'])
              .map(_normalizeThreadMessage)
              .where(
                (message) => (message['body']?.toString() ?? '').isNotEmpty,
              )
              .toList()
            ..sort(_compareChatMessages);
      if (mounted) {
        setState(() {
          _messages
            ..clear()
            ..addAll(messages);
          _applyCursorMeta(response['meta']);
        });
      }
      unawaited(widget.repository.markChatRead(widget.memberId));
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
        widget.memberId,
        beforeId: beforeId,
      );
      final olderMessages = _mapList(response['data'])
          .map(_normalizeThreadMessage)
          .where((message) => (message['body']?.toString() ?? '').isNotEmpty)
          .toList();

      if (mounted) {
        setState(() {
          for (final message in olderMessages) {
            _upsertSilently(message);
          }
          _messages.sort(_compareChatMessages);
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
        'trainer-${DateTime.now().microsecondsSinceEpoch}-${widget.memberId}';
    final optimistic = <String, dynamic>{
      'id': clientMessageId,
      'sender_id': widget.currentUserId,
      'recipient_id': widget.memberId,
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
            widget.memberId,
            body,
            clientMessageId: clientMessageId,
          );
          _upsert(_normalizeThreadMessage(response['data']));
        }
      } else {
        final response = await widget.repository.sendChatMessage(
          widget.memberId,
          body,
          clientMessageId: clientMessageId,
        );
        _upsert(_normalizeThreadMessage(response['data']));
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
        'recipientId': widget.memberId,
        'message': body,
        'clientMessageId': clientMessageId,
        'metadata': {'source': 'trainer_app'},
      },
      ack: (dynamic response) {
        if (completer.isCompleted) {
          return;
        }

        final map = _map(response);
        if (map['ok'] != true) {
          completer.completeError(
            Exception(
              map['error']?.toString() ?? 'Socket chat persistence failed.',
            ),
          );
          return;
        }

        final message = _normalizeThreadMessage(map['message']);
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
    final normalized = _normalizeThreadMessage(message);
    setState(() {
      _upsertSilently(normalized);
      _messages.sort(_compareChatMessages);
    });
  }

  void _upsertSilently(Map<String, dynamic> message) {
    final normalized = _normalizeThreadMessage(message);
    final key = _chatMessageKey(normalized);
    final clientId = normalized['client_message_id']?.toString();
    _messages.removeWhere((item) {
      return _chatMessageKey(item) == key ||
          (clientId != null &&
              clientId.isNotEmpty &&
              item['client_message_id']?.toString() == clientId);
    });
    _messages.add(normalized);
  }

  void _applyCursorMeta(dynamic meta) {
    final cursor = _map(_map(meta)['cursor']);
    _hasOlderMessages = cursor['has_more'] == true;
    _nextBeforeId = _intValue(cursor['next_before_id']);
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: widget.member['name']?.toString() ?? 'Member chat',
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
            child: GlassCard(
              padding: EdgeInsets.zero,
              child: _ChatThreadHeader(
                member: widget.member,
                loading: _loading,
                onRefresh: _load,
              ),
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 0),
              child: Text(
                _error!,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.error),
              ),
            ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _messages.isEmpty
                ? const EmptyStateView(
                    title: 'No messages yet',
                    message: 'Start a private member conversation.',
                    icon: Icons.chat_bubble_outline_rounded,
                  )
                : ListView.separated(
                    padding: const EdgeInsets.all(20),
                    itemCount: _messages.length + (_hasOlderMessages ? 1 : 0),
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      if (_hasOlderMessages) {
                        if (index == 0) {
                          return _LoadOlderChatMessagesButton(
                            loading: _loadingOlder,
                            onPressed: _loadOlder,
                          );
                        }
                        index -= 1;
                      }

                      final message = _messages[index];
                      final isOutgoing =
                          _intValue(message['sender_id']) ==
                          widget.currentUserId;
                      final failed = message['failed'] == true;
                      final pending = message['pending'] == true;
                      return _ChatBubble(
                        body: message['body']?.toString() ?? '',
                        time: failed
                            ? 'Failed to send'
                            : pending
                            ? 'Sending...'
                            : _chatTime(message['created_at']),
                        isOutgoing: isOutgoing,
                        failed: failed,
                      );
                    },
                  ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      minLines: 1,
                      maxLines: 4,
                      enabled: !_sending,
                      decoration: const InputDecoration(
                        labelText: 'Message this member',
                        prefixIcon: Icon(Icons.lock_outline_rounded),
                      ),
                      onSubmitted: (_) => _send(),
                    ),
                  ),
                  const SizedBox(width: 12),
                  FilledButton(
                    onPressed: _sending ? null : _send,
                    style: FilledButton.styleFrom(
                      shape: const CircleBorder(),
                      padding: const EdgeInsets.all(16),
                    ),
                    child: Icon(
                      _sending
                          ? Icons.hourglass_top_rounded
                          : Icons.send_rounded,
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

class _LoadOlderChatMessagesButton extends StatelessWidget {
  const _LoadOlderChatMessagesButton({
    required this.loading,
    required this.onPressed,
  });

  final bool loading;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: OutlinedButton.icon(
        onPressed: loading ? null : onPressed,
        icon: Icon(loading ? Icons.sync_rounded : Icons.history_rounded),
        label: Text(loading ? 'Loading older messages' : 'Load older messages'),
      ),
    );
  }
}

class _ChatBubble extends StatelessWidget {
  const _ChatBubble({
    required this.body,
    required this.time,
    required this.isOutgoing,
    required this.failed,
  });

  final String body;
  final String time;
  final bool isOutgoing;
  final bool failed;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: isOutgoing ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 310),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            gradient: isOutgoing
                ? LinearGradient(
                    colors: [
                      failed
                          ? AppColors.error
                          : Theme.of(context).colorScheme.primary,
                      Theme.of(context).colorScheme.secondary,
                    ],
                  )
                : null,
            color: isOutgoing ? null : AppColors.surfaceStrong,
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(22),
              topRight: const Radius.circular(22),
              bottomLeft: Radius.circular(isOutgoing ? 22 : 6),
              bottomRight: Radius.circular(isOutgoing ? 6 : 22),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                body,
                style: TextStyle(
                  color: isOutgoing ? Colors.white : AppColors.textPrimary,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                time,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: isOutgoing ? Colors.white70 : AppColors.textMuted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NotificationPage extends StatelessWidget {
  const _NotificationPage({
    required this.notifications,
    required this.trialRequests,
    required this.members,
    required this.onMarkRead,
    required this.onMarkAllRead,
    required this.onUpdateTrial,
    required this.onCreateAnnouncement,
  });

  final List<Map<String, dynamic>> notifications;
  final List<Map<String, dynamic>> trialRequests;
  final List<Map<String, dynamic>> members;
  final Future<void> Function(int notificationId) onMarkRead;
  final Future<void> Function() onMarkAllRead;
  final Future<void> Function(int trialRequestId, String status) onUpdateTrial;
  final Future<void> Function(Map<String, dynamic> payload)
  onCreateAnnouncement;

  @override
  Widget build(BuildContext context) {
    final unreadCount = notifications
        .where((item) => item['read_at'] == null)
        .length;

    if (notifications.isEmpty && trialRequests.isEmpty && members.isEmpty) {
      return const EmptyStateView(
        title: 'No trainer notifications yet',
        message:
            'Alerts, assignments, reminders, trial leads, and progress updates will show here.',
        icon: Icons.notifications_none_rounded,
      );
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(25, 15, 25, 28),
      children: [
        _TrainerNotificationSummaryBand(
          unreadCount: unreadCount,
          totalCount: notifications.length,
          trialCount: trialRequests.length,
          canMarkAllRead: unreadCount > 0,
          canSendUpdate: members.isNotEmpty,
          onMarkAllRead: () async {
            try {
              await onMarkAllRead();
            } catch (exception) {
              if (context.mounted) {
                ScaffoldMessenger.of(
                  context,
                ).showSnackBar(SnackBar(content: Text(exception.toString())));
              }
            }
          },
          onSendUpdate: () => _openAnnouncementSheet(context),
        ),
        if (trialRequests.isNotEmpty) ...[
          const SizedBox(height: 18),
          const _TrainerNotificationSectionTitle(
            title: 'Trial requests',
            action: 'Assigned',
          ),
          const SizedBox(height: 10),
          ...trialRequests.take(5).map((trial) {
            final id = (trial['id'] as num?)?.toInt();
            final member = _map(trial['member']);
            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _TrainerTrialLeadCard(
                title:
                    member['name']?.toString() ??
                    trial['name']?.toString() ??
                    'Trial lead',
                subtitle:
                    'Preferred ${prettyDate(trial['preferred_date'])} ${trial['preferred_time'] ?? ''}',
                status: trial['status']?.toString() ?? 'pending',
                onAccept: id == null
                    ? null
                    : () => onUpdateTrial(id, 'accepted'),
                onCompleted: id == null
                    ? null
                    : () => onUpdateTrial(id, 'completed'),
              ),
            );
          }),
        ],
        const SizedBox(height: 18),
        const _TrainerNotificationSectionTitle(
          title: 'Updates',
          action: 'Latest',
        ),
        const SizedBox(height: 2),
        if (notifications.isEmpty)
          const EmptyStateView(
            title: 'No notification feed items',
            message:
                'Member updates you send and backend alerts will appear in this feed when available.',
            icon: Icons.notifications_none_rounded,
          )
        else
          ...notifications.asMap().entries.expand((entry) {
            final item = entry.value;
            final isUnread = item['read_at'] == null;
            return [
              _TrainerNotificationRow(
                notification: item,
                isUnread: isUnread,
                onMarkRead: () async {
                  final id = (item['id'] as num?)?.toInt();
                  if (id == null) {
                    return;
                  }
                  try {
                    await onMarkRead(id);
                  } catch (exception) {
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text(exception.toString())),
                      );
                    }
                  }
                },
              ),
              if (entry.key < notifications.length - 1)
                Divider(
                  color: const Color(0xFF786F72).withValues(alpha: 0.20),
                  height: 1,
                ),
            ];
          }),
      ],
    );
  }

  Future<void> _openAnnouncementSheet(BuildContext context) async {
    final titleController = TextEditingController();
    final messageController = TextEditingController();
    var selectedMemberId = (members.firstOrNull?['member_id'] as num?)?.toInt();
    var saving = false;

    try {
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: Colors.transparent,
        builder: (sheetContext) {
          return StatefulBuilder(
            builder: (context, setSheetState) {
              Future<void> sendAnnouncement() async {
                final selectedAssignment = members.firstWhere(
                  (item) =>
                      (item['member_id'] as num?)?.toInt() == selectedMemberId,
                  orElse: () => const <String, dynamic>{},
                );
                final memberId = (selectedAssignment['member_id'] as num?)
                    ?.toInt();
                final title = titleController.text.trim();
                final message = messageController.text.trim();
                if (memberId == null || title.isEmpty || message.isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Choose a member and enter a title/message.',
                      ),
                    ),
                  );
                  return;
                }

                setSheetState(() => saving = true);
                try {
                  await onCreateAnnouncement({
                    'gym_id': (selectedAssignment['gym_id'] as num?)?.toInt(),
                    'branch_id': (selectedAssignment['branch_id'] as num?)
                        ?.toInt(),
                    'audience_type': 'selected_members',
                    'member_ids': [memberId],
                    'title': title,
                    'message': message,
                  });
                  if (context.mounted) {
                    Navigator.of(context).pop();
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Member update sent.')),
                    );
                  }
                } catch (exception) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(exception.toString())),
                    );
                  }
                } finally {
                  if (context.mounted) {
                    setSheetState(() => saving = false);
                  }
                }
              }

              final selectedAssignment = members.firstWhere(
                (item) =>
                    (item['member_id'] as num?)?.toInt() == selectedMemberId,
                orElse: () => const <String, dynamic>{},
              );
              final selectedMember = _map(selectedAssignment['member']);
              final selectedGoal =
                  _map(
                    selectedAssignment['progress_summary'],
                  )['fitness_goal']?.toString() ??
                  'Assigned coaching member';

              return Padding(
                padding: EdgeInsets.only(
                  left: 16,
                  right: 16,
                  top: 12,
                  bottom: MediaQuery.of(context).viewInsets.bottom + 16,
                ),
                child: _TrainerSendUpdateSheet(
                  selectedMemberName:
                      selectedMember['name']?.toString() ?? 'Assigned member',
                  selectedMemberSubtitle: selectedGoal,
                  titleController: titleController,
                  messageController: messageController,
                  selectedMemberId: selectedMemberId,
                  members: members,
                  saving: saving,
                  onMemberChanged: (value) =>
                      setSheetState(() => selectedMemberId = value),
                  onSend: sendAnnouncement,
                ),
              );
            },
          );
        },
      );
    } finally {
      titleController.dispose();
      messageController.dispose();
    }
  }
}

class _TrainerSendUpdateSheet extends StatelessWidget {
  const _TrainerSendUpdateSheet({
    required this.selectedMemberName,
    required this.selectedMemberSubtitle,
    required this.titleController,
    required this.messageController,
    required this.selectedMemberId,
    required this.members,
    required this.saving,
    required this.onMemberChanged,
    required this.onSend,
  });

  final String selectedMemberName;
  final String selectedMemberSubtitle;
  final TextEditingController titleController;
  final TextEditingController messageController;
  final int? selectedMemberId;
  final List<Map<String, dynamic>> members;
  final bool saving;
  final ValueChanged<int?> onMemberChanged;
  final Future<void> Function() onSend;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.88,
        ),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(34)),
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 52,
                  height: 5,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE5E7EB),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF92A3FD), Color(0xFFC58BF2)],
                      ),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: const Icon(
                      Icons.campaign_rounded,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Send member update',
                          style: TextStyle(
                            color: Color(0xFF1D1617),
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        SizedBox(height: 4),
                        Text(
                          'Share a focused coaching reminder.',
                          style: TextStyle(
                            color: Color(0xFF786F72),
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                  ),
                  borderRadius: BorderRadius.circular(24),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.22),
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        selectedMemberName.trim().isEmpty
                            ? 'M'
                            : selectedMemberName.trim()[0].toUpperCase(),
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 18,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            selectedMemberName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            selectedMemberSubtitle,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.82),
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<int>(
                initialValue: selectedMemberId,
                isExpanded: true,
                items: members
                    .map(
                      (assignment) => DropdownMenuItem<int>(
                        value: (assignment['member_id'] as num?)?.toInt(),
                        child: Text(
                          _map(assignment['member'])['name']?.toString() ??
                              'Assigned member',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    )
                    .toList(),
                onChanged: saving ? null : onMemberChanged,
                decoration: _fitInputDecoration('Member', Icons.person_rounded),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: titleController,
                textInputAction: TextInputAction.next,
                decoration: _fitInputDecoration(
                  'Update title',
                  Icons.title_rounded,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: messageController,
                minLines: 4,
                maxLines: 6,
                decoration: _fitInputDecoration(
                  'Message',
                  Icons.notes_rounded,
                ).copyWith(alignLabelWithHint: true),
              ),
              const SizedBox(height: 18),
              SizedBox(
                height: 54,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF92A3FD), Color(0xFFC58BF2)],
                    ),
                    borderRadius: BorderRadius.circular(99),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF92A3FD).withValues(alpha: 0.28),
                        blurRadius: 18,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: FilledButton.icon(
                    onPressed: saving ? null : onSend,
                    icon: saving
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.send_rounded),
                    label: Text(saving ? 'Sending update...' : 'Send update'),
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.transparent,
                      disabledBackgroundColor: Colors.transparent,
                      shadowColor: Colors.transparent,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

InputDecoration _fitInputDecoration(String label, IconData icon) {
  return InputDecoration(
    labelText: label,
    prefixIcon: Icon(icon, color: const Color(0xFF92A3FD)),
    filled: true,
    fillColor: const Color(0xFFF7F8F8),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide.none,
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide.none,
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: const BorderSide(color: Color(0xFF92A3FD)),
    ),
  );
}

class _TrainerNotificationSummaryBand extends StatelessWidget {
  const _TrainerNotificationSummaryBand({
    required this.unreadCount,
    required this.totalCount,
    required this.trialCount,
    required this.canMarkAllRead,
    required this.canSendUpdate,
    required this.onMarkAllRead,
    required this.onSendUpdate,
  });

  final int unreadCount;
  final int totalCount;
  final int trialCount;
  final bool canMarkAllRead;
  final bool canSendUpdate;
  final VoidCallback onMarkAllRead;
  final VoidCallback onSendUpdate;

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
      child: Column(
        children: [
          Row(
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
                      trialCount == 0
                          ? '$totalCount total notifications'
                          : '$totalCount updates • $trialCount trial leads',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.82),
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (canMarkAllRead || canSendUpdate) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                if (canMarkAllRead)
                  Expanded(
                    child: _WhitePillButton(
                      label: 'Read all',
                      icon: Icons.done_all_rounded,
                      onTap: onMarkAllRead,
                    ),
                  ),
                if (canMarkAllRead && canSendUpdate) const SizedBox(width: 10),
                if (canSendUpdate)
                  Expanded(
                    child: _WhitePillButton(
                      label: 'Send update',
                      icon: Icons.campaign_rounded,
                      onTap: onSendUpdate,
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _WhitePillButton extends StatelessWidget {
  const _WhitePillButton({
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
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.24),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: Colors.white.withValues(alpha: 0.28)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: Colors.white, size: 17),
            const SizedBox(width: 7),
            Flexible(
              child: Text(
                label,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 12,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TrainerNotificationSectionTitle extends StatelessWidget {
  const _TrainerNotificationSectionTitle({
    required this.title,
    required this.action,
  });

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

class _TrainerTrialLeadCard extends StatelessWidget {
  const _TrainerTrialLeadCard({
    required this.title,
    required this.subtitle,
    required this.status,
    required this.onAccept,
    required this.onCompleted,
  });

  final String title;
  final String subtitle;
  final String status;
  final VoidCallback? onAccept;
  final VoidCallback? onCompleted;

  @override
  Widget build(BuildContext context) {
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
                    colors: [Color(0xFFC58BF2), Color(0xFF92A3FD)],
                  ),
                ),
                child: const Icon(
                  Icons.person_add_alt_1_rounded,
                  color: Colors.white,
                ),
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
                      style: const TextStyle(
                        color: Color(0xFF1D1617),
                        fontWeight: FontWeight.w900,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
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
              StatusBadge(
                label: _titleCase(status),
                color: AppColors.statusColor(status),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onAccept,
                  icon: const Icon(Icons.call_rounded, size: 17),
                  label: const Text('Accept'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: onCompleted,
                  icon: const Icon(Icons.check_rounded, size: 17),
                  label: const Text('Done'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF92A3FD),
                    foregroundColor: Colors.white,
                    elevation: 0,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TrainerNotificationRow extends StatelessWidget {
  const _TrainerNotificationRow({
    required this.notification,
    required this.isUnread,
    required this.onMarkRead,
  });

  final Map<String, dynamic> notification;
  final bool isUnread;
  final VoidCallback onMarkRead;

  @override
  Widget build(BuildContext context) {
    final type = notification['type']?.toString();
    final title = notification['title']?.toString() ?? 'Notification';
    final body = notification['body']?.toString().trim().isNotEmpty == true
        ? notification['body'].toString()
        : _notificationFallbackBody(type);
    final color = _notificationColor(context, type);

    return InkWell(
      onTap: isUnread ? onMarkRead : null,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    color.withValues(alpha: 0.88),
                    color.withValues(alpha: 0.58),
                  ],
                ),
                shape: BoxShape.circle,
              ),
              child: Icon(
                _notificationIcon(type),
                color: Colors.white,
                size: 20,
              ),
            ),
            const SizedBox(width: 15),
            Expanded(
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
                            fontWeight: isUnread
                                ? FontWeight.w800
                                : FontWeight.w600,
                            fontSize: 13,
                            height: 1.25,
                          ),
                        ),
                      ),
                      if (isUnread)
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
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF786F72),
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
                          prettyDateTime(notification['created_at']),
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
                          _notificationLabel(type),
                          style: TextStyle(
                            color: color,
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
            if (isUnread) ...[
              const SizedBox(width: 8),
              IconButton(
                onPressed: onMarkRead,
                icon: const Icon(Icons.done_rounded),
                color: const Color(0xFF92A3FD),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _MembersFitnessHeader extends StatelessWidget {
  const _MembersFitnessHeader({
    required this.totalCount,
    required this.visibleCount,
    required this.dueCount,
    required this.needsPlanCount,
    required this.onRefresh,
  });

  final int totalCount;
  final int visibleCount;
  final int dueCount;
  final int needsPlanCount;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF92A3FD), Color(0xFF9DCEFF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF92A3FD).withValues(alpha: 0.28),
            blurRadius: 26,
            offset: const Offset(0, 16),
          ),
        ],
      ),
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
                      'Coach your squad',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.80),
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Members',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 26,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.5,
                      ),
                    ),
                  ],
                ),
              ),
              InkWell(
                onTap: () {
                  onRefresh();
                },
                borderRadius: BorderRadius.circular(18),
                child: Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.22),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.26),
                    ),
                  ),
                  child: const Icon(Icons.refresh_rounded, color: Colors.white),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: _MembersHeaderMetric(
                  label: 'Visible',
                  value: '$visibleCount',
                  icon: Icons.groups_2_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MembersHeaderMetric(
                  label: 'Need plan',
                  value: '$needsPlanCount',
                  icon: Icons.playlist_add_check_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MembersHeaderMetric(
                  label: 'Dues',
                  value: '$dueCount',
                  icon: Icons.payments_rounded,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            '$totalCount total assigned members',
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.78),
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MembersHeaderMetric extends StatelessWidget {
  const _MembersHeaderMetric({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.20),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withValues(alpha: 0.22)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: Colors.white, size: 18),
          const SizedBox(height: 10),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.76),
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MembersFlowCard extends StatelessWidget {
  const _MembersFlowCard({
    required this.libraryCount,
    required this.assignedPlanCount,
    required this.needsPlanCount,
  });

  final int libraryCount;
  final int assignedPlanCount;
  final int needsPlanCount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.96),
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: const Color(0xFFEDEDED)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF1D1617).withValues(alpha: 0.05),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.route_rounded, color: _TrainerWorkoutColor.primaryEnd),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Simple workout assignment flow',
                  style: TextStyle(
                    color: _TrainerWorkoutColor.black,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          const Text(
            'Create reusable workouts in Builder. On this page, choose a member and assign one saved library workout only to that member.',
            style: TextStyle(
              color: _TrainerWorkoutColor.gray,
              fontSize: 12,
              height: 1.35,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _MembersFlowMetric(
                  label: 'Library',
                  value: '$libraryCount',
                  icon: Icons.library_books_rounded,
                  color: const Color(0xFF92A3FD),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MembersFlowMetric(
                  label: 'Assigned',
                  value: '$assignedPlanCount',
                  icon: Icons.assignment_turned_in_rounded,
                  color: const Color(0xFF34D399),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MembersFlowMetric(
                  label: 'Need plan',
                  value: '$needsPlanCount',
                  icon: Icons.playlist_add_rounded,
                  color: const Color(0xFFFFB86B),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MembersFlowMetric extends StatelessWidget {
  const _MembersFlowMetric({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 17),
          const SizedBox(height: 8),
          Text(
            value,
            style: const TextStyle(
              color: _TrainerWorkoutColor.black,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _TrainerWorkoutColor.gray,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MembersSearchCard extends StatelessWidget {
  const _MembersSearchCard({
    required this.controller,
    required this.query,
    required this.dueOnly,
    required this.needsPlanOnly,
    required this.goalFilter,
    required this.statusFilter,
    required this.goals,
    required this.statuses,
    required this.onQueryChanged,
    required this.onDueOnlyChanged,
    required this.onNeedsPlanChanged,
    required this.onGoalChanged,
    required this.onStatusChanged,
    required this.onClear,
  });

  final TextEditingController controller;
  final String query;
  final bool dueOnly;
  final bool needsPlanOnly;
  final String? goalFilter;
  final String? statusFilter;
  final List<String> goals;
  final List<String> statuses;
  final ValueChanged<String> onQueryChanged;
  final ValueChanged<bool> onDueOnlyChanged;
  final ValueChanged<bool> onNeedsPlanChanged;
  final ValueChanged<String?> onGoalChanged;
  final ValueChanged<String?> onStatusChanged;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.96),
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF1D1617).withValues(alpha: 0.06),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        children: [
          TextField(
            controller: controller,
            onChanged: onQueryChanged,
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              hintText: 'Search member, goal, email...',
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: query.isEmpty
                  ? null
                  : IconButton(
                      onPressed: onClear,
                      icon: const Icon(Icons.close_rounded),
                    ),
              filled: true,
              fillColor: const Color(0xFFF7F8F8),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
                borderSide: BorderSide.none,
              ),
            ),
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                FilterChip(
                  selected: dueOnly,
                  label: const Text('Payment due'),
                  avatar: const Icon(Icons.payments_rounded, size: 16),
                  onSelected: onDueOnlyChanged,
                ),
                const SizedBox(width: 8),
                FilterChip(
                  selected: needsPlanOnly,
                  label: const Text('Needs workout'),
                  avatar: const Icon(Icons.fitness_center_rounded, size: 16),
                  onSelected: onNeedsPlanChanged,
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxWidth < 360;
              final filters = [
                DropdownButtonFormField<String?>(
                  initialValue: goalFilter,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Goal',
                    prefixIcon: Icon(Icons.flag_rounded),
                  ),
                  items: [
                    const DropdownMenuItem<String?>(
                      value: null,
                      child: Text('All goals'),
                    ),
                    ...goals.map(
                      (goal) => DropdownMenuItem<String?>(
                        value: goal,
                        child: Text(goal),
                      ),
                    ),
                  ],
                  onChanged: onGoalChanged,
                ),
                DropdownButtonFormField<String?>(
                  initialValue: statusFilter,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Status',
                    prefixIcon: Icon(Icons.verified_rounded),
                  ),
                  items: [
                    const DropdownMenuItem<String?>(
                      value: null,
                      child: Text('All statuses'),
                    ),
                    ...statuses.map(
                      (status) => DropdownMenuItem<String?>(
                        value: status,
                        child: Text(_titleCase(status)),
                      ),
                    ),
                  ],
                  onChanged: onStatusChanged,
                ),
              ];

              if (compact) {
                return Column(
                  children: [
                    filters.first,
                    const SizedBox(height: 10),
                    filters.last,
                  ],
                );
              }

              return Row(
                children: [
                  Expanded(child: filters.first),
                  const SizedBox(width: 10),
                  Expanded(child: filters.last),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _FitnessMemberRow extends StatelessWidget {
  const _FitnessMemberRow({
    required this.assignment,
    required this.plans,
    required this.onOpen,
    required this.onQuickNote,
    required this.onQuickAssign,
    required this.onManageWorkouts,
    required this.onSendMessage,
    required this.onAddFollowUp,
  });

  final Map<String, dynamic> assignment;
  final List<Map<String, dynamic>> plans;
  final VoidCallback onOpen;
  final VoidCallback onQuickNote;
  final VoidCallback onQuickAssign;
  final VoidCallback onManageWorkouts;
  final VoidCallback onSendMessage;
  final VoidCallback onAddFollowUp;

  @override
  Widget build(BuildContext context) {
    final member = _map(assignment['member']);
    final progressSummary = _map(assignment['progress_summary']);
    final attendanceSummary = _map(assignment['attendance_summary']);
    final membershipSummary = _map(assignment['membership_summary']);
    final engagement = _map(assignment['engagement_score']);
    final memberId = (assignment['member_id'] as num?)?.toInt();
    final memberPlans = plans
        .where((plan) => (plan['member_id'] as num?)?.toInt() == memberId)
        .toList();
    final latestProgressUpdate = progressSummary['latest_note']?.toString();
    final avatar = member['avatar']?.toString() ?? '';
    final name = member['name']?.toString() ?? 'Member';
    final email = member['email']?.toString() ?? 'Assigned client';
    final goal = progressSummary['fitness_goal']?.toString() ?? 'No goal set';
    final dueAmount = _toDouble(membershipSummary['due_amount']);
    final completionLabel = memberPlans.isEmpty
        ? 'Needs plan'
        : '${memberPlans.length} plan${memberPlans.length == 1 ? '' : 's'}';

    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(26),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.96),
          borderRadius: BorderRadius.circular(26),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF1D1617).withValues(alpha: 0.06),
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
                  width: 58,
                  height: 58,
                  padding: const EdgeInsets.all(3),
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: LinearGradient(
                      colors: [Color(0xFFC58BF2), Color(0xFF92A3FD)],
                    ),
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
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF1D1617),
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        email,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF786F72),
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 7,
                        runSpacing: 7,
                        children: [
                          StatusBadge(
                            label: goal,
                            color: const Color(0xFF92A3FD),
                            icon: Icons.flag_rounded,
                          ),
                          StatusBadge(
                            label: _attendanceLabel(attendanceSummary),
                            color: const Color(0xFF22D3EE),
                            icon: Icons.qr_code_scanner_rounded,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                if (dueAmount > 0)
                  StatusBadge(
                    label: 'Due ${_currency(dueAmount)}',
                    color: const Color(0xFFF97316),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: _MemberRowMetric(
                    label: 'Workout',
                    value: completionLabel,
                    icon: Icons.fitness_center_rounded,
                    color: const Color(0xFF92A3FD),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _MemberRowMetric(
                    label: 'Status',
                    value: _titleCase(
                      membershipSummary['status']?.toString() ?? 'active',
                    ),
                    icon: Icons.verified_rounded,
                    color: const Color(0xFF34D399),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _MemberRowMetric(
                    label: 'Score',
                    value: engagement['score']?.toString() ?? '--',
                    icon: Icons.insights_rounded,
                    color: const Color(0xFFC58BF2),
                  ),
                ),
              ],
            ),
            if (latestProgressUpdate != null ||
                engagement['summary']?.toString().isNotEmpty == true) ...[
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF7F8F8),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Text(
                  latestProgressUpdate ??
                      engagement['summary']?.toString() ??
                      'No progress note has been added yet.',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF786F72),
                    fontSize: 11,
                    height: 1.35,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
            const SizedBox(height: 14),
            Wrap(
              spacing: 9,
              runSpacing: 9,
              children: [
                QuickActionButton(
                  label: 'View',
                  icon: Icons.visibility_rounded,
                  onPressed: onOpen,
                ),
                QuickActionButton(
                  label: 'Note',
                  icon: Icons.edit_note_rounded,
                  onPressed: onQuickNote,
                ),
                QuickActionButton(
                  label: memberPlans.isEmpty ? 'Assign' : 'Manage',
                  icon: Icons.playlist_add_check_circle_outlined,
                  onPressed: memberPlans.isEmpty
                      ? onQuickAssign
                      : onManageWorkouts,
                ),
                QuickActionButton(
                  label: 'Follow-up',
                  icon: Icons.event_available_rounded,
                  onPressed: onAddFollowUp,
                ),
                QuickActionButton(
                  label: 'Message',
                  icon: Icons.chat_bubble_rounded,
                  onPressed: onSendMessage,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberRowMetric extends StatelessWidget {
  const _MemberRowMetric({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 17),
          const SizedBox(height: 8),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Color(0xFF1D1617),
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Color(0xFF786F72),
              fontSize: 9,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _WeekSchedulePicker extends StatelessWidget {
  const _WeekSchedulePicker({
    required this.selectedDays,
    required this.onToggle,
  });

  final Set<String> selectedDays;
  final ValueChanged<String> onToggle;

  @override
  Widget build(BuildContext context) {
    const days = <String>['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: days
          .map(
            (day) => FilterChip(
              selected: selectedDays.contains(day),
              onSelected: (_) => onToggle(day),
              label: Text(day),
            ),
          )
          .toList(),
    );
  }
}

class _SimpleTaskTile extends StatelessWidget {
  const _SimpleTaskTile({
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  final String title;
  final String subtitle;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: Theme.of(
                context,
              ).colorScheme.secondary.withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              icon,
              size: 18,
              color: Theme.of(context).colorScheme.secondary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 3),
                Text(subtitle, style: Theme.of(context).textTheme.bodyMedium),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MiniMetric extends StatelessWidget {
  const _MiniMetric({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        color: AppColors.surfaceStrong,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: Theme.of(context).colorScheme.secondary),
          const SizedBox(height: 10),
          Text(value, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _ProfileListCard extends StatelessWidget {
  const _ProfileListCard({
    required this.title,
    required this.items,
    required this.emptyText,
    required this.icon,
  });

  final String title;
  final List<String> items;
  final String emptyText;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        color: AppColors.surfaceStrong,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                icon,
                size: 18,
                color: Theme.of(context).colorScheme.secondary,
              ),
              const SizedBox(width: 8),
              Text(title, style: Theme.of(context).textTheme.titleMedium),
            ],
          ),
          const SizedBox(height: 10),
          if (items.isEmpty)
            Text(emptyText, style: Theme.of(context).textTheme.bodySmall)
          else
            ...items
                .take(3)
                .map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Text(
                      item,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ),
                ),
        ],
      ),
    );
  }
}

Map<String, dynamic> _map(dynamic value) {
  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }
  return const <String, dynamic>{};
}

List<Map<String, dynamic>> _mapList(dynamic value) {
  if (value is List) {
    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }
  return const <Map<String, dynamic>>[];
}

Map<String, dynamic> _normalizeThreadMessage(dynamic value) {
  final map = _map(value);
  final clientId =
      map['client_message_id']?.toString() ??
      map['clientMessageId']?.toString();
  return <String, dynamic>{
    'id': map['id']?.toString() ?? clientId ?? UniqueKey().toString(),
    'room': map['room']?.toString(),
    'sender_id': _intValue(map['sender_id'] ?? map['senderId']),
    'recipient_id': _intValue(map['recipient_id'] ?? map['recipientId']),
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

String _chatMessageKey(Map<String, dynamic> message) {
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

int _compareChatMessages(Map<String, dynamic> a, Map<String, dynamic> b) {
  final aTime = DateTime.tryParse(a['created_at']?.toString() ?? '');
  final bTime = DateTime.tryParse(b['created_at']?.toString() ?? '');
  return (aTime ?? DateTime.fromMillisecondsSinceEpoch(0)).compareTo(
    bTime ?? DateTime.fromMillisecondsSinceEpoch(0),
  );
}

int? _intValue(dynamic value) {
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse(value?.toString() ?? '');
}

String _chatTime(dynamic value) {
  final parsed = DateTime.tryParse(value?.toString() ?? '');
  if (parsed == null) {
    return 'Just now';
  }
  final local = parsed.toLocal();
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}

List<String> _list(dynamic value) {
  if (value is List) {
    return value
        .map((item) => item.toString().trim())
        .where((item) => item.isNotEmpty)
        .toList();
  }

  return const <String>[];
}

double _toDouble(dynamic value) {
  if (value is num) {
    return value.toDouble();
  }
  return double.tryParse(value?.toString() ?? '') ?? 0;
}

String _currency(dynamic value) {
  return 'Rs ${_toDouble(value).toStringAsFixed(0)}';
}

String _titleCase(String value) {
  if (value.trim().isEmpty) {
    return '--';
  }
  return value
      .split('_')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

String _attendanceLabel(Map<String, dynamic> attendanceSummary) {
  final lastCheckIn = attendanceSummary['last_check_in_at'];
  if (lastCheckIn == null) {
    return 'No check-in';
  }
  final date = DateTime.tryParse(lastCheckIn.toString());
  if (date == null) {
    return 'Tracked';
  }
  final sameDay = DateTime.now().difference(date).inDays == 0;
  return sameDay ? 'Today' : prettyDate(date.toIso8601String());
}

Color _notificationColor(BuildContext context, String? type) {
  switch (type) {
    case 'new_member_assigned':
    case 'trainer_assignment':
    case 'trial_assigned':
      return const Color(0xFF22D3EE);
    case 'attendance_inactivity':
    case 'missed_workout_alert':
    case 'missed_workout':
      return const Color(0xFFFB7185);
    case 'member_completed_workout':
    case 'client_progress_update':
    case 'progress_photo_uploaded':
    case 'pr_achievement':
      return const Color(0xFF34D399);
    case 'gym_announcement':
      return const Color(0xFFA78BFA);
    default:
      return Theme.of(context).colorScheme.secondary;
  }
}

IconData _notificationIcon(String? type) {
  switch (type) {
    case 'new_member_assigned':
    case 'trainer_assignment':
    case 'trial_assigned':
      return Icons.person_add_alt_1_rounded;
    case 'attendance_inactivity':
    case 'missed_workout_alert':
    case 'missed_workout':
      return Icons.warning_amber_rounded;
    case 'member_completed_workout':
    case 'client_progress_update':
    case 'progress_photo_uploaded':
    case 'pr_achievement':
      return Icons.insights_rounded;
    case 'gym_announcement':
      return Icons.campaign_rounded;
    default:
      return Icons.notifications_rounded;
  }
}

String _notificationLabel(String? type) {
  if (type == 'message_${'place'}holder') {
    return 'Message';
  }

  switch (type) {
    case 'new_member_assigned':
      return 'New member assigned';
    case 'member_completed_workout':
      return 'Workout completed';
    case 'missed_workout':
    case 'missed_workout_alert':
      return 'Missed workout';
    case 'progress_photo_uploaded':
      return 'Progress photo';
    case 'trial_assigned':
      return 'Trial assigned';
    case 'gym_announcement':
      return 'Gym announcement';
    default:
      return _titleCase(type ?? 'update');
  }
}

String _notificationFallbackBody(String? type) {
  if (type == 'message_${'place'}holder') {
    return 'Conversation alerts and member replies will appear here.';
  }

  switch (type) {
    case 'new_member_assigned':
      return 'A new member has been assigned to your coaching queue.';
    case 'member_completed_workout':
      return 'One of your assigned members completed a workout and may need review.';
    case 'missed_workout':
    case 'missed_workout_alert':
      return 'A member has missed a planned workout and may need a follow-up.';
    case 'progress_photo_uploaded':
      return 'A member uploaded fresh progress so you can review the update.';
    case 'trial_assigned':
      return 'A trial lead has been assigned to you for follow-up.';
    case 'gym_announcement':
      return 'Your gym sent an announcement that may affect today’s coaching work.';
    default:
      return 'A new trainer update is available.';
  }
}

extension<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
