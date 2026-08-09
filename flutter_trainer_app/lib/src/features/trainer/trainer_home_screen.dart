import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:gym_flutter_core/workout_plan_summary_view.dart';
import 'package:provider/provider.dart';
import 'package:socket_io_client/socket_io_client.dart' as io;

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../core/config.dart';
import '../../core/pagination.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/confirmation_dialog.dart';
import '../../../core/widgets/premium_card.dart';
import '../auth/session_controller.dart';
import 'socket_service.dart';
import 'trainer_member_detail_screen.dart';
import 'trainer_onboarding_flow.dart';
import 'trainer_profile_screen.dart';
import 'trainer_coaching_mode.dart';
import 'trainer_repository.dart';
import 'trainer_diet_plan_screen.dart';
import 'trainer_settings_screen.dart';
import 'trainer_tasks_screen.dart';

class TrainerHomeScreen extends StatefulWidget {
  const TrainerHomeScreen({
    super.key,
    this.initialIndex = 0,
    this.initialChatMemberId,
    this.chatLaunchVersion = 0,
    this.storePreviewData,
  });

  final int initialIndex;
  final int? initialChatMemberId;
  final int chatLaunchVersion;
  final Map<String, dynamic>? storePreviewData;

  @override
  State<TrainerHomeScreen> createState() => _TrainerHomeScreenState();
}

class _TrainerHomeScreenState extends State<TrainerHomeScreen> {
  late TrainerRepository _repository;
  final TrainerSocketService _socketService = TrainerSocketService();
  io.Socket? _socket;
  late int _index;
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
  List<Map<String, dynamic>> _independentInvitations = const [];
  ApiPagination _gymMemberPage = const ApiPagination.singlePage();
  ApiPagination _independentMemberPage = const ApiPagination.singlePage();
  ApiPagination _notificationPage = const ApiPagination.singlePage();
  ApiPagination _trialPage = const ApiPagination.singlePage();
  ApiPagination _planPage = const ApiPagination.singlePage();
  ApiPagination _templatePage = const ApiPagination.singlePage();
  ApiPagination _exercisePage = const ApiPagination.singlePage();
  bool _loadingMoreMembers = false;
  bool _loadingMoreNotifications = false;
  bool _loadingMoreWorkoutData = false;
  String? _chatError;
  String? _workoutFocusAssignmentKey;
  int _handledChatLaunchVersion = -1;

  List<Map<String, dynamic>> get _coachingActionMembers => _members
      .where(
        (assignment) =>
            assignment['relationship_type'] != 'independent' ||
            assignment['access_active'] != false,
      )
      .toList();

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
    final session = context.read<TrainerSessionController>();
    _repository = TrainerRepository(session.client);
    final previewData = widget.storePreviewData;
    if (previewData == null) {
      scheduleMicrotask(_bootstrap);
    } else {
      _applyStorePreviewData(previewData);
    }
  }

  void _applyStorePreviewData(Map<String, dynamic> previewData) {
    _contextData = _map(previewData['context']);
    _tasks = _map(previewData['tasks']);
    _members = _mapList(previewData['members']);
    _todayClients = _mapList(previewData['today_clients']);
    _followUps = _mapList(previewData['follow_ups']);
    _templates = _mapList(previewData['templates']);
    _plans = _mapList(previewData['plans']);
    _notifications = _mapList(previewData['notifications']);
    _trialRequests = _mapList(previewData['trial_requests']);
    _exercises = _mapList(previewData['exercises']);
    _chatConversations = _mapList(previewData['chat_conversations']);
    _independentInvitations = _mapList(previewData['independent_invitations']);
    _loading = false;
  }

  @override
  void didUpdateWidget(covariant TrainerHomeScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.chatLaunchVersion != widget.chatLaunchVersion) {
      _scheduleInitialChat();
    }
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
    _scheduleInitialChat();
  }

  void _scheduleInitialChat() {
    final memberId = widget.initialChatMemberId;
    if (memberId == null ||
        widget.chatLaunchVersion == _handledChatLaunchVersion ||
        _loading ||
        !mounted) {
      return;
    }

    _handledChatLaunchVersion = widget.chatLaunchVersion;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        unawaited(_openTrainerChatThread(memberId));
      }
    });
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
      final contextResponse = await _repository.fetchContext();
      final contextData = _map(contextResponse['data']);
      final trainerProfile = _map(contextData['trainer_profile']);
      final hasTrainerProfile = trainerProfile.isNotEmpty;
      final isIndependent = trainerIsIndependent(trainerProfile);
      final isVerified =
          (trainerProfile['verification_status']?.toString() ?? '')
              .toLowerCase() ==
          'verified';

      if (!hasTrainerProfile) {
        final notificationsResponse = await _repository.fetchNotifications();
        if (!mounted) {
          return;
        }
        _contextData = _normalizeTrainerContext(contextData);
        _notifications = _mapList(notificationsResponse['data']);
        _notificationPage = ApiPagination.fromResponse(notificationsResponse);
        _members = const [];
        _todayClients = const [];
        _followUps = const [];
        _templates = const [];
        _plans = const [];
        _trialRequests = const [];
        _exercises = const [];
        _chatConversations = const [];
        _independentInvitations = const [];
        _tasks = const {};
        _chatError = null;
        setState(() => _loading = false);
        return;
      }

      Future<Map<String, dynamic>> coachingRequest(
        Future<Map<String, dynamic>> Function() request,
        String label,
      ) async {
        try {
          return await request();
        } catch (exception) {
          if (!isIndependent) rethrow;
          debugPrint('[trainer-home][warn] $label: $exception');
          return const <String, dynamic>{'data': <dynamic>[]};
        }
      }

      final results = await Future.wait([
        coachingRequest(_repository.fetchAssignedMembers, 'gym members'),
        coachingRequest(_repository.fetchTodayClients, 'today clients'),
        coachingRequest(_repository.fetchWorkoutTemplates, 'workout templates'),
        coachingRequest(_repository.fetchWorkoutPlans, 'workout plans'),
        coachingRequest(_repository.fetchNotifications, 'notifications'),
        coachingRequest(_repository.fetchExercises, 'exercises'),
        coachingRequest(_repository.fetchTrialRequests, 'trial requests'),
      ]);
      List<Map<String, dynamic>> independentMembers = const [];
      Map<String, dynamic>? independentMembersResponse;
      List<Map<String, dynamic>> independentInvitations = const [];
      Map<String, dynamic> independentContext = const {};
      try {
        final response = await _repository.fetchIndependentContext();
        independentContext = _map(response['data']);
      } catch (exception) {
        debugPrint('[trainer-home][warn] independent context: $exception');
      }
      if (isVerified) {
        try {
          final response = await _repository.fetchIndependentMembers();
          independentMembersResponse = response;
          independentMembers = _recordsFromResponse(
            response,
          ).map(_normalizeIndependentAssignment).toList();
        } catch (exception) {
          debugPrint('[trainer-home][warn] independent members: $exception');
        }
        try {
          final response = await _repository
              .fetchIndependentMemberInvitations();
          independentInvitations = _recordsFromResponse(response);
        } catch (exception) {
          debugPrint('[trainer-home][warn] independent invites: $exception');
        }
      } else {
        independentMembers = _mapList(independentContext['relationships'])
            .where(
              (relationship) =>
                  relationship['status']?.toString().toLowerCase() == 'active',
            )
            .map(_normalizeIndependentAssignment)
            .toList();
        independentInvitations = _mapList(independentContext['invitations']);
      }
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
        if (!isIndependent &&
            !(message.contains('404') ||
                message.contains('not found') ||
                message.contains('endpoint'))) {
          rethrow;
        }
        followUps = const [];
      }
      try {
        chatConversations = await _fetchAllChatConversations();
        _chatError = null;
      } catch (exception) {
        chatConversations = const [];
        _chatError = exception.toString();
      }
      if (!mounted) {
        return;
      }
      _contextData = _normalizeTrainerContext(contextData);
      _members = [..._mapList(results[0]['data']), ...independentMembers];
      _gymMemberPage = ApiPagination.fromResponse(results[0]);
      _independentMemberPage = independentMembersResponse == null
          ? const ApiPagination.singlePage()
          : ApiPagination.fromResponse(independentMembersResponse);
      _todayClients = _mapList(results[1]['data']);
      _templates = _mapList(results[2]['data']);
      _templatePage = ApiPagination.fromResponse(results[2]);
      _plans = _mapList(results[3]['data']);
      _planPage = ApiPagination.fromResponse(results[3]);
      _notifications = _mapList(results[4]['data']);
      _notificationPage = ApiPagination.fromResponse(results[4]);
      _exercises = _mapList(results[5]['data']);
      _exercisePage = ApiPagination.fromResponse(results[5]);
      _trialRequests = _mapList(results[6]['data']);
      _trialPage = ApiPagination.fromResponse(results[6]);
      _tasks = tasks;
      _followUps = followUps;
      _chatConversations = chatConversations;
      _independentInvitations = independentInvitations;
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _loadMoreMembers() async {
    if (_loadingMoreMembers ||
        (!_gymMemberPage.hasMore && !_independentMemberPage.hasMore)) {
      return;
    }
    setState(() => _loadingMoreMembers = true);
    try {
      var gymMembers = _members
          .where((item) => item['relationship_type'] != 'independent')
          .toList();
      var independentMembers = _members
          .where((item) => item['relationship_type'] == 'independent')
          .toList();
      if (_gymMemberPage.hasMore) {
        final response = await _repository.fetchAssignedMembers(
          page: _gymMemberPage.nextPage,
        );
        gymMembers = mergeApiPageItems(gymMembers, apiPageItems(response));
        _gymMemberPage = ApiPagination.fromResponse(response);
      }
      if (_independentMemberPage.hasMore) {
        final response = await _repository.fetchIndependentMembers(
          page: _independentMemberPage.nextPage,
        );
        independentMembers = mergeApiPageItems(
          independentMembers,
          apiPageItems(response).map(_normalizeIndependentAssignment),
        );
        _independentMemberPage = ApiPagination.fromResponse(response);
      }
      _members = [...gymMembers, ...independentMembers];
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => _loadingMoreMembers = false);
    }
  }

  Future<void> _loadMoreNotifications() async {
    if (_loadingMoreNotifications ||
        (!_notificationPage.hasMore && !_trialPage.hasMore)) {
      return;
    }
    setState(() => _loadingMoreNotifications = true);
    try {
      if (_notificationPage.hasMore) {
        final response = await _repository.fetchNotifications(
          page: _notificationPage.nextPage,
        );
        _notifications = mergeApiPageItems(
          _notifications,
          apiPageItems(response),
        );
        _notificationPage = ApiPagination.fromResponse(response);
      }
      if (_trialPage.hasMore) {
        final response = await _repository.fetchTrialRequests(
          page: _trialPage.nextPage,
        );
        _trialRequests = mergeApiPageItems(
          _trialRequests,
          apiPageItems(response),
        );
        _trialPage = ApiPagination.fromResponse(response);
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => _loadingMoreNotifications = false);
    }
  }

  Future<void> _loadMoreWorkoutData() async {
    if (_loadingMoreWorkoutData ||
        (!_templatePage.hasMore &&
            !_planPage.hasMore &&
            !_exercisePage.hasMore)) {
      return;
    }
    setState(() => _loadingMoreWorkoutData = true);
    try {
      if (_templatePage.hasMore) {
        final response = await _repository.fetchWorkoutTemplates(
          page: _templatePage.nextPage,
        );
        _templates = mergeApiPageItems(_templates, apiPageItems(response));
        _templatePage = ApiPagination.fromResponse(response);
      }
      if (_planPage.hasMore) {
        final response = await _repository.fetchWorkoutPlans(
          page: _planPage.nextPage,
        );
        _plans = mergeApiPageItems(_plans, apiPageItems(response));
        _planPage = ApiPagination.fromResponse(response);
      }
      if (_exercisePage.hasMore) {
        final response = await _repository.fetchExercises(
          page: _exercisePage.nextPage,
        );
        _exercises = mergeApiPageItems(_exercises, apiPageItems(response));
        _exercisePage = ApiPagination.fromResponse(response);
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => _loadingMoreWorkoutData = false);
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
    final hasTrainerProfile = _map(_contextData['trainer_profile']).isNotEmpty;
    final trainerProfile = _map(_contextData['trainer_profile']);
    final isIndependentTrainer = trainerIsIndependent(trainerProfile);
    final verificationStatus =
        trainerProfile['verification_status']?.toString().toLowerCase() ??
        'pending';
    final verificationDisplayStatus =
        verificationStatus == 'pending' &&
            trainerProfile['verification_submitted'] != true
        ? 'not_submitted'
        : verificationStatus;
    final verificationReason = trainerProfile['verification_rejection_reason']
        ?.toString()
        .trim();
    final canInvitePersonalMember = verificationStatus == 'verified';
    final pendingInvitationCount = _independentInvitations
        .where(
          (item) =>
              (item['status']?.toString() ?? '').toLowerCase() == 'pending',
        )
        .length;
    final memberAction = trainerMemberAction(
      isIndependent: isIndependentTrainer,
      verificationStatus: verificationStatus,
      pendingInvitationCount: pendingInvitationCount,
    );

    final pages = <Widget>[
      _DashboardPage(
        contextData: _contextData,
        tasks: _tasks,
        todayClients: _todayClients,
        followUps: _followUps,
        members: _members,
        plans: _plans,
        notifications: _notifications,
        trialRequests: _trialRequests,
        chatConversations: _chatConversations,
        onRefresh: _load,
        onEditProfile: _openProfileEditSheet,
        onOpenMembers: () => setState(() => _index = 1),
        onOpenWorkouts: () => setState(() => _index = 2),
        onOpenDiet: _openDietBuilder,
        onOpenChat: () => setState(() => _index = 3),
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
        plans: _plans,
        onRefresh: _load,
        hasMore: _gymMemberPage.hasMore || _independentMemberPage.hasMore,
        loadingMore: _loadingMoreMembers,
        onLoadMore: _loadMoreMembers,
        onOpenMember: _openMemberDetailSheet,
        onQuickNote: _openQuickNoteSheet,
        onQuickAssign: _openQuickAssignSheet,
        onManageWorkouts: _openWorkoutManagerForMember,
        onSendMessage: _openChatWithMember,
        onAddFollowUp: _openQuickNoteSheet,
        onAddMember: () => _openMemberInvitationSheet(
          independent: isIndependentTrainer,
          allowed: !isIndependentTrainer || canInvitePersonalMember,
        ),
        isIndependentTrainer: isIndependentTrainer,
        verificationStatus: verificationDisplayStatus,
        verificationReason: verificationReason,
        pendingInvitationCount: pendingInvitationCount,
        onManageInvitations: switch (memberAction) {
          TrainerMemberAction.verification => _openProfileEditSheet,
          TrainerMemberAction.independentInvitation =>
            () => _openMemberInvitationSheet(independent: true, allowed: true),
          TrainerMemberAction.manageIndependentInvitations =>
            _openIndependentInvitationsSheet,
          TrainerMemberAction.gymInvitation => () => _openMemberInvitationSheet(
            independent: false,
            allowed: true,
          ),
        },
      ),
      _WorkoutPage(
        contextData: _contextData,
        members: _coachingActionMembers,
        templates: _templates,
        plans: _plans,
        exercises: _exercises,
        repository: _repository,
        initialAssignmentKey: _workoutFocusAssignmentKey,
        onRefresh: _load,
        hasMore:
            _templatePage.hasMore || _planPage.hasMore || _exercisePage.hasMore,
        loadingMore: _loadingMoreWorkoutData,
        onLoadMore: _loadMoreWorkoutData,
      ),
      _ChatPage(
        members: _coachingActionMembers,
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
        onRefresh: _load,
        hasMore: _notificationPage.hasMore || _trialPage.hasMore,
        loadingMore: _loadingMoreNotifications,
        onLoadMore: _loadMoreNotifications,
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
        onRespondGymInvitation: (id, decision) async {
          await _repository.respondToGymInvitation(id, decision);
          await _load();
        },
      ),
    ];

    return AppGradientScaffold(
      title: _pageTitle(_index, user.name),
      actions: [
        IconButton(
          tooltip: 'Diet plans',
          onPressed: _openDietBuilder,
          icon: const Icon(Icons.restaurant_menu_rounded),
        ),
      ],
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
            : !hasTrainerProfile
            ? KeyedSubtree(
                key: const ValueKey('trainer-gym-invitation'),
                child: _NotificationPage(
                  notifications: _notifications,
                  trialRequests: const [],
                  members: const [],
                  onRefresh: _load,
                  hasMore: _notificationPage.hasMore || _trialPage.hasMore,
                  loadingMore: _loadingMoreNotifications,
                  onLoadMore: _loadMoreNotifications,
                  onMarkRead: (notificationId) async {
                    await _repository.markNotificationRead(notificationId);
                    await _load();
                  },
                  onMarkAllRead: () async {
                    await _repository.markAllNotificationsRead();
                    await _load();
                  },
                  onUpdateTrial: (_, __) async {},
                  onCreateAnnouncement: (_) async {},
                  onRespondGymInvitation: (id, decision) async {
                    await _repository.respondToGymInvitation(id, decision);
                    await _load();
                  },
                ),
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
      bottomNavigationBar: onboardingCompleted && hasTrainerProfile
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
        return 'Workout Planner';
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
    for (final assignment in _coachingActionMembers) {
      if ((assignment['member_id'] as num?)?.toInt() == memberId) {
        selectedAssignment = assignment;
        break;
      }
    }

    if (selectedAssignment.isEmpty) {
      if (mounted) {
        setState(() => _index = 3);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'This coaching relationship is no longer active. Start or accept an active coaching relationship before opening chat.',
            ),
          ),
        );
      }
      return;
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
      } else {
        unawaited(_refreshChatConversations());
      }
      _chatConversations = conversations;
    });
  }

  Future<void> _refreshChatConversations() async {
    try {
      final conversations = await _fetchAllChatConversations();
      if (!mounted) {
        return;
      }
      setState(() {
        _chatConversations = conversations;
        _chatError = null;
      });
    } catch (exception) {
      if (mounted) {
        setState(() => _chatError = exception.toString());
      }
    }
  }

  Future<List<Map<String, dynamic>>> _fetchAllChatConversations() async {
    var response = await _repository.fetchChatConversations();
    var conversations = apiPageItems(response);
    var pagination = ApiPagination.fromResponse(response);

    while (pagination.hasMore) {
      response = await _repository.fetchChatConversations(
        page: pagination.nextPage,
      );
      conversations = mergeApiPageItems(conversations, apiPageItems(response));
      pagination = ApiPagination.fromResponse(response);
    }

    return conversations;
  }

  Future<void> _openMemberDetailSheet(Map<String, dynamic> assignment) async {
    final memberId = (assignment['member_id'] as num?)?.toInt();
    if (memberId == null) {
      return;
    }
    if (assignment['relationship_type'] == 'independent') {
      await _openIndependentMemberSheet(assignment);
      return;
    }

    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => TrainerMemberDetailScreen(
          assignment: assignment,
          repository: _repository,
          onAssignWorkout: () => _openQuickAssignSheet(assignment),
          onAssignDiet: () => _openDietBuilder(preselectedMemberId: memberId),
          onMessage: () => _openChatWithMember(assignment),
          onAddCoachingNote: () => _openQuickNoteSheet(assignment),
        ),
      ),
    );
  }

  Future<void> _openIndependentMemberSheet(
    Map<String, dynamic> assignment,
  ) async {
    final relationshipId = _intValue(
      assignment['relationship_id'] ?? assignment['id'],
    );
    if (relationshipId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('This coaching connection is invalid.')),
      );
      return;
    }
    final member = _map(assignment['member']);
    final name = member['name']?.toString() ?? 'Member';
    final permissions = (assignment['sharing_permissions'] as List? ?? const [])
        .map((value) => value.toString())
        .toSet();
    final accessActive = assignment['access_active'] != false;

    Future<({List<Map<String, dynamic>> items, int total})> loadPreview(
      Future<Map<String, dynamic>> Function(int page) request,
    ) async {
      try {
        final response = await request(1);
        final records = apiPageItems(response);
        return (
          items: records,
          total: ApiPagination.fromResponse(response).total,
        );
      } catch (exception) {
        debugPrint('[independent-member][warn] $exception');
        return (items: const <Map<String, dynamic>>[], total: 0);
      }
    }

    final results = await Future.wait([
      if (accessActive && permissions.contains('workouts'))
        loadPreview(
          (page) => _repository.fetchIndependentMemberWorkoutPlans(
            relationshipId,
            page: page,
          ),
        )
      else
        Future.value((items: const <Map<String, dynamic>>[], total: 0)),
      if (accessActive && permissions.contains('workouts'))
        loadPreview(
          (page) => _repository.fetchIndependentMemberWorkoutLogbook(
            relationshipId,
            page: page,
          ),
        )
      else
        Future.value((items: const <Map<String, dynamic>>[], total: 0)),
      if (accessActive && permissions.contains('profile'))
        loadPreview(
          (page) => _repository.fetchIndependentMemberNotes(
            relationshipId,
            page: page,
          ),
        )
      else
        Future.value((items: const <Map<String, dynamic>>[], total: 0)),
    ]);
    if (!mounted) return;
    final workoutPlans = results[0].items;
    final workoutPlanTotal = results[0].total;
    final workoutSessionTotal = results[1].total;
    final notes = results[2].items;
    final noteTotal = results[2].total;
    var progressTotal = 0;
    if (accessActive && permissions.contains('progress')) {
      try {
        final response = await _repository.fetchIndependentMemberProgress(
          relationshipId,
        );
        final meta = _map(response['meta']);
        progressTotal =
            const [
              'weight_logs_pagination',
              'body_measurements_pagination',
              'progress_photos_pagination',
              'personal_records_pagination',
            ].fold<int>(
              0,
              (total, key) =>
                  total + (_intValue(_map(meta[key])['total']) ?? 0),
            );
      } catch (exception) {
        debugPrint('[independent-member][warn] progress: $exception');
      }
    }
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      backgroundColor: AppColors.surfaceOverlay,
      builder: (sheetContext) => SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
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
                    name,
                    style: Theme.of(sheetContext).textTheme.headlineSmall,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              accessActive
                  ? 'Independent coaching relationship. Gym subscriptions, attendance, billing, and gym trainer assignment are not shared here.'
                  : 'Independent coaching access is paused because trainer eligibility or verification changed. Coaching data and chat are locked; the relationship can still be ended below.',
              style: Theme.of(sheetContext).textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
                height: 1.45,
              ),
            ),
            const SizedBox(height: 18),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _MemberMetaChip(
                  label: '$workoutPlanTotal workout plans',
                  icon: Icons.fitness_center_rounded,
                ),
                _MemberMetaChip(
                  label: '$workoutSessionTotal logged sessions',
                  icon: Icons.history_rounded,
                ),
                _MemberMetaChip(
                  label: '$noteTotal private notes',
                  icon: Icons.note_alt_outlined,
                ),
                if (accessActive && permissions.contains('progress'))
                  _MemberMetaChip(
                    label: '$progressTotal progress records',
                    icon: Icons.insights_rounded,
                  ),
              ],
            ),
            if (workoutPlans.isNotEmpty || notes.isNotEmpty) ...[
              const SizedBox(height: 18),
              if (workoutPlans.isNotEmpty) ...[
                Text(
                  'Independent workout plans',
                  style: Theme.of(
                    sheetContext,
                  ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 6),
                ...workoutPlans
                    .take(3)
                    .map(
                      (plan) => ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.fitness_center_rounded),
                        title: Text(plan['name']?.toString() ?? 'Workout plan'),
                        subtitle: Text(
                          _titleCase(plan['status']?.toString() ?? 'active'),
                        ),
                      ),
                    ),
              ],
              if (notes.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  'Recent private notes',
                  style: Theme.of(
                    sheetContext,
                  ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 6),
                ...notes
                    .take(3)
                    .map(
                      (note) => ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.lock_outline_rounded),
                        title: Text(
                          note['note']?.toString() ?? 'Private coaching note',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
              ],
            ],
            const SizedBox(height: 18),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                FilledButton.icon(
                  onPressed: accessActive && permissions.contains('chat')
                      ? () {
                          Navigator.of(sheetContext).pop();
                          _openChatWithMember(assignment);
                        }
                      : null,
                  icon: const Icon(Icons.chat_bubble_outline_rounded),
                  label: const Text('Message'),
                ),
                OutlinedButton.icon(
                  onPressed: accessActive && permissions.contains('workouts')
                      ? () {
                          Navigator.of(sheetContext).pop();
                          _openQuickAssignSheet(assignment);
                        }
                      : null,
                  icon: const Icon(Icons.fitness_center_rounded),
                  label: const Text('Assign workout'),
                ),
                OutlinedButton.icon(
                  onPressed: accessActive && permissions.contains('diets')
                      ? () {
                          Navigator.of(sheetContext).pop();
                          _openDietBuilder(
                            preselectedMemberId: _intValue(
                              assignment['member_id'],
                            ),
                            preselectedRelationshipId: relationshipId,
                          );
                        }
                      : null,
                  icon: const Icon(Icons.restaurant_menu_rounded),
                  label: const Text('Diet plan'),
                ),
                OutlinedButton.icon(
                  onPressed: accessActive && permissions.contains('profile')
                      ? () {
                          Navigator.of(sheetContext).pop();
                          _openQuickNoteSheet(assignment);
                        }
                      : null,
                  icon: const Icon(Icons.note_add_outlined),
                  label: const Text('Private note'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: TextButton.icon(
                onPressed: () async {
                  final rootMessenger = ScaffoldMessenger.of(context);
                  final confirmed =
                      await showDialog<bool>(
                        context: sheetContext,
                        builder: (dialogContext) => AlertDialog(
                          title: const Text('End independent coaching?'),
                          content: Text(
                            'This immediately removes independent plan, progress, and chat access for $name. Their gym membership and gym trainer remain unchanged.',
                          ),
                          actions: [
                            TextButton(
                              onPressed: () =>
                                  Navigator.of(dialogContext).pop(false),
                              child: const Text('Keep coaching'),
                            ),
                            FilledButton(
                              onPressed: () =>
                                  Navigator.of(dialogContext).pop(true),
                              child: const Text('End coaching'),
                            ),
                          ],
                        ),
                      ) ??
                      false;
                  if (!confirmed) return;
                  try {
                    await _repository.revokeIndependentMemberRelationship(
                      relationshipId,
                    );
                    if (!mounted || !sheetContext.mounted) return;
                    Navigator.of(sheetContext).pop();
                    rootMessenger.showSnackBar(
                      SnackBar(
                        content: Text(
                          'Independent coaching with $name has ended.',
                        ),
                      ),
                    );
                    await _load();
                  } catch (exception) {
                    if (sheetContext.mounted) {
                      ScaffoldMessenger.of(sheetContext).showSnackBar(
                        SnackBar(content: Text(exception.toString())),
                      );
                    }
                  }
                },
                icon: const Icon(
                  Icons.link_off_rounded,
                  color: AppColors.error,
                ),
                label: const Text('End independent coaching'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openDietBuilder({
    int? preselectedMemberId,
    int? preselectedRelationshipId,
  }) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => TrainerDietPlanScreen(
          repository: _repository,
          members: _coachingActionMembers,
          preselectedMemberId: preselectedMemberId,
          preselectedRelationshipId: preselectedRelationshipId,
        ),
      ),
    );
    if (mounted) {
      await _load();
    }
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
    final followUpController = TextEditingController();
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
                'Coaching note',
                style: Theme.of(modalContext).textTheme.headlineSmall,
              ),
              const SizedBox(height: 4),
              Text(
                'Keep a private note and optionally schedule a follow-up.',
                style: Theme.of(
                  modalContext,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: noteController,
                minLines: 3,
                maxLines: 5,
                decoration: const InputDecoration(
                  labelText: 'Private coaching note',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: followUpController,
                decoration: const InputDecoration(
                  labelText: 'Follow-up date (optional)',
                  hintText: 'YYYY-MM-DD',
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
                            final followUpDate = followUpController.text.trim();
                            final relationshipId = _intValue(
                              assignment['relationship_id'],
                            );
                            final payload = <String, dynamic>{
                              'note': noteController.text.trim(),
                              'visibility': 'private_to_trainer',
                              if (followUpDate.isNotEmpty)
                                'follow_up_date': followUpDate,
                            };
                            if (assignment['relationship_type'] ==
                                    'independent' &&
                                relationshipId != null) {
                              await _repository.createIndependentMemberNote(
                                relationshipId,
                                payload,
                              );
                            } else {
                              await _repository.createNote(memberId, payload);
                            }
                            if (!mounted) {
                              return;
                            }
                            navigator.pop();
                            await _showSuccessCelebration(
                              'Coaching note saved',
                              icon: Icons.check_circle_rounded,
                            );
                            rootMessenger.showSnackBar(
                              const SnackBar(
                                content: Text('Coaching note saved.'),
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
                  child: Text(submitting ? 'Saving...' : 'Save coaching note'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openMemberInvitationSheet({
    required bool independent,
    required bool allowed,
  }) async {
    if (!allowed) {
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          icon: const Icon(Icons.verified_user_outlined),
          title: const Text('Verification required'),
          content: const Text(
            'Trainers can invite personal coaching members after Atlas verifies their account. Gym assignments and gym-assigned members are not affected.',
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Understood'),
            ),
          ],
        ),
      );
      return;
    }
    final nameController = TextEditingController();
    final emailController = TextEditingController();
    final phoneController = TextEditingController();
    final goalController = TextEditingController();
    bool submitting = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surfaceOverlay,
      builder: (modalContext) => StatefulBuilder(
        builder: (modalContext, setModalState) => Padding(
          padding: EdgeInsets.fromLTRB(
            24,
            24,
            24,
            MediaQuery.of(modalContext).viewInsets.bottom + 24,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  independent ? 'Invite a personal member' : 'Invite a member',
                  style: Theme.of(modalContext).textTheme.headlineSmall,
                ),
                const SizedBox(height: 6),
                Text(
                  independent
                      ? 'This creates a separate coaching invitation. It never replaces the member’s gym trainer.'
                      : 'Existing app users approve in the app. Everyone else receives an email approval link.',
                  style: Theme.of(modalContext).textTheme.bodySmall,
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: nameController,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'Full name'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: emailController,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Email address'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: phoneController,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Phone (optional)',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: goalController,
                  decoration: const InputDecoration(
                    labelText: 'Fitness goal (optional)',
                  ),
                ),
                const SizedBox(height: 18),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: submitting
                        ? null
                        : () async {
                            if (nameController.text.trim().isEmpty ||
                                emailController.text.trim().isEmpty) {
                              return;
                            }
                            final navigator = Navigator.of(modalContext);
                            final rootMessenger = ScaffoldMessenger.of(context);
                            setModalState(() => submitting = true);
                            try {
                              final payload = <String, dynamic>{
                                'name': nameController.text.trim(),
                                'email': emailController.text.trim(),
                                if (phoneController.text.trim().isNotEmpty)
                                  'phone': phoneController.text.trim(),
                                if (goalController.text.trim().isNotEmpty)
                                  'fitness_goal': goalController.text.trim(),
                              };
                              final response = independent
                                  ? await _repository.inviteIndependentMember(
                                      payload,
                                    )
                                  : await _repository.inviteMember(payload);
                              if (!modalContext.mounted) {
                                return;
                              }
                              navigator.pop();
                              final channel = _map(
                                response['data'],
                              )['approval_channel'];
                              rootMessenger.showSnackBar(
                                SnackBar(
                                  content: Text(
                                    channel == 'app'
                                        ? 'Invitation sent for in-app approval.'
                                        : 'Enrollment approval email sent.',
                                  ),
                                ),
                              );
                            } catch (error) {
                              if (modalContext.mounted) {
                                ScaffoldMessenger.of(modalContext).showSnackBar(
                                  SnackBar(content: Text(error.toString())),
                                );
                              }
                            } finally {
                              if (modalContext.mounted) {
                                setModalState(() => submitting = false);
                              }
                            }
                          },
                    child: Text(submitting ? 'Sending...' : 'Send invitation'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    nameController.dispose();
    emailController.dispose();
    phoneController.dispose();
    goalController.dispose();
  }

  Future<void> _openIndependentInvitationsSheet() async {
    await showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      backgroundColor: AppColors.surfaceOverlay,
      builder: (sheetContext) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.68,
        minChildSize: 0.4,
        maxChildSize: 0.92,
        builder: (context, controller) => ListView(
          controller: controller,
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'Independent invitations',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 6),
            Text(
              'Track consent requests or resend an invitation. Resending safely supersedes the previous pending link.',
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 16),
            if (_independentInvitations.isEmpty)
              const EmptyStateView(
                title: 'No invitations yet',
                message: 'Invite a member to begin independent coaching.',
                icon: Icons.mark_email_unread_outlined,
              )
            else
              ..._independentInvitations.map((invitation) {
                final status = invitation['status']?.toString() ?? 'pending';
                final canResend = status != 'accepted';
                final invitationId = _intValue(invitation['id']);
                return Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: PremiumCard(
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      children: [
                        const Icon(Icons.mail_outline_rounded),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                invitation['invited_name']?.toString() ??
                                    'Invited member',
                                style: Theme.of(context).textTheme.titleSmall
                                    ?.copyWith(fontWeight: FontWeight.w900),
                              ),
                              Text(
                                invitation['invited_email']?.toString() ?? '',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              Text(
                                '${_titleCase(status)} · ${_titleCase(invitation['approval_channel']?.toString() ?? 'app')} approval',
                                style: Theme.of(context).textTheme.labelSmall
                                    ?.copyWith(color: AppColors.textSecondary),
                              ),
                            ],
                          ),
                        ),
                        if (canResend)
                          Wrap(
                            spacing: 4,
                            children: [
                              if (status == 'pending' && invitationId != null)
                                TextButton(
                                  onPressed: () async {
                                    final rootMessenger = ScaffoldMessenger.of(
                                      this.context,
                                    );
                                    final confirmed =
                                        await showDialog<bool>(
                                          context: sheetContext,
                                          builder: (dialogContext) => AlertDialog(
                                            title: const Text(
                                              'Withdraw invitation?',
                                            ),
                                            content: const Text(
                                              'The member will no longer be able to accept this coaching invitation. You can send a fresh invitation later.',
                                            ),
                                            actions: [
                                              TextButton(
                                                onPressed: () => Navigator.of(
                                                  dialogContext,
                                                ).pop(false),
                                                child: const Text('Keep'),
                                              ),
                                              FilledButton(
                                                onPressed: () => Navigator.of(
                                                  dialogContext,
                                                ).pop(true),
                                                child: const Text('Withdraw'),
                                              ),
                                            ],
                                          ),
                                        ) ??
                                        false;
                                    if (!confirmed) return;
                                    try {
                                      await _repository
                                          .cancelIndependentMemberInvitation(
                                            invitationId,
                                          );
                                      if (!mounted || !sheetContext.mounted) {
                                        return;
                                      }
                                      Navigator.of(sheetContext).pop();
                                      rootMessenger.showSnackBar(
                                        const SnackBar(
                                          content: Text(
                                            'Invitation withdrawn.',
                                          ),
                                        ),
                                      );
                                      await _load();
                                    } catch (exception) {
                                      if (sheetContext.mounted) {
                                        ScaffoldMessenger.of(
                                          sheetContext,
                                        ).showSnackBar(
                                          SnackBar(
                                            content: Text(exception.toString()),
                                          ),
                                        );
                                      }
                                    }
                                  },
                                  child: const Text('Withdraw'),
                                ),
                              TextButton(
                                onPressed: () async {
                                  final rootMessenger = ScaffoldMessenger.of(
                                    this.context,
                                  );
                                  try {
                                    await _repository.inviteIndependentMember({
                                      'name':
                                          invitation['invited_name']
                                              ?.toString() ??
                                          'Member',
                                      'email': invitation['invited_email']
                                          ?.toString(),
                                      if ((invitation['message']?.toString() ??
                                              '')
                                          .isNotEmpty)
                                        'message': invitation['message']
                                            .toString(),
                                      if (invitation['sharing_permissions']
                                          is List)
                                        'sharing_permissions':
                                            invitation['sharing_permissions'],
                                    });
                                    if (!mounted || !sheetContext.mounted) {
                                      return;
                                    }
                                    Navigator.of(sheetContext).pop();
                                    rootMessenger.showSnackBar(
                                      const SnackBar(
                                        content: Text('Invitation resent.'),
                                      ),
                                    );
                                    await _load();
                                  } catch (exception) {
                                    if (sheetContext.mounted) {
                                      ScaffoldMessenger.of(
                                        sheetContext,
                                      ).showSnackBar(
                                        SnackBar(
                                          content: Text(exception.toString()),
                                        ),
                                      );
                                    }
                                  }
                                },
                                child: const Text('Resend'),
                              ),
                            ],
                          ),
                      ],
                    ),
                  ),
                );
              }),
          ],
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
                .where((plan) => _planMatchesAssignment(plan, assignment))
                .toList();

            Future<void> assignTemplate() async {
              final templateId = selectedTemplateId;
              final gymId = (assignment['gym_id'] as num?)?.toInt();
              final branchId = (assignment['branch_id'] as num?)?.toInt();
              final relationshipId = _intValue(assignment['relationship_id']);
              if (templateId == null ||
                  (relationshipId == null &&
                      (gymId == null || branchId == null))) {
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
                  if (gymId != null) 'gym_id': gymId,
                  if (branchId != null) 'branch_id': branchId,
                  if (relationshipId != null)
                    'independent_trainer_member_relationship_id':
                        relationshipId,
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
      _workoutFocusAssignmentKey = _assignmentKey(assignment);
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
                      color: AppColors.shadow.withValues(alpha: 0.10),
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
              child: Icon(
                icon,
                color: active ? AppColors.primary : AppColors.textMuted,
                size: 25,
              ),
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
                color: AppColors.primaryBright,
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
                color: AppColors.primary.withValues(
                  alpha: active ? 0.34 : 0.24,
                ),
                blurRadius: active ? 24 : 16,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [AppColors.primaryBright, AppColors.primary],
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
    required this.trialRequests,
    required this.chatConversations,
    required this.onRefresh,
    required this.onEditProfile,
    required this.onOpenMembers,
    required this.onOpenWorkouts,
    required this.onOpenDiet,
    required this.onOpenChat,
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
  final List<Map<String, dynamic>> trialRequests;
  final List<Map<String, dynamic>> chatConversations;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onEditProfile;
  final VoidCallback onOpenMembers;
  final VoidCallback onOpenWorkouts;
  final VoidCallback onOpenDiet;
  final VoidCallback onOpenChat;
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
            title: 'Diet Builder',
            subtitle: 'Build nutrition plans or assign a diet template.',
            icon: Icons.restaurant_menu_rounded,
            onTap: onOpenDiet,
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
                            onPressed: onOpenDiet,
                            icon: const Icon(Icons.restaurant_menu_rounded),
                            label: const Text('Diet Builder'),
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
      trialRequests: trialRequests,
      chatConversations: chatConversations,
      idleDashboard: idleDashboard,
      onRefresh: onRefresh,
      onEditProfile: onEditProfile,
      onOpenMembers: onOpenMembers,
      onOpenWorkouts: onOpenWorkouts,
      onOpenDiet: onOpenDiet,
      onOpenChat: onOpenChat,
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
    required this.trialRequests,
    required this.chatConversations,
    required this.idleDashboard,
    required this.onRefresh,
    required this.onEditProfile,
    required this.onOpenMembers,
    required this.onOpenWorkouts,
    required this.onOpenDiet,
    required this.onOpenChat,
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
  final List<Map<String, dynamic>> trialRequests;
  final List<Map<String, dynamic>> chatConversations;
  final bool idleDashboard;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onEditProfile;
  final VoidCallback onOpenMembers;
  final VoidCallback onOpenWorkouts;
  final VoidCallback onOpenDiet;
  final VoidCallback onOpenChat;
  final VoidCallback onOpenNotifications;
  final VoidCallback onOpenSettings;
  final VoidCallback onOpenTasks;
  final VoidCallback onAddNote;
  final String Function(String value) titleCase;

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    final hasWideSplit = size.width >= 680;
    final completion =
        (trainerProfile['profile_completion_percentage'] as num?)?.toDouble() ??
        0;
    final specialization = trainerProfile['primary_specialization']
        ?.toString()
        .trim();
    final gymName = assignedGym['name']?.toString().trim().isNotEmpty == true
        ? assignedGym['name']!.toString().trim()
        : 'Trainer workspace';
    final branchName =
        assignedBranch['name']?.toString().trim().isNotEmpty == true
        ? assignedBranch['name']!.toString().trim()
        : 'Branch pending';
    final unreadChatsCount = chatConversations.fold<int>(0, (total, item) {
      return total +
          ((_map(item)['trainer_unread_count'] as num?)?.toInt() ?? 0);
    });
    final trialLeadCount = trialRequests.length;
    final trialPreview = trialRequests.take(3).toList();
    final snapshotMetrics = <_DashboardStatData>[
      _DashboardStatData(
        label: 'Members',
        value: '$assignedMembersCount',
        helper: 'Assigned roster',
        icon: Icons.groups_rounded,
        color: AppColors.primary,
      ),
      _DashboardStatData(
        label: 'Today',
        value: '$todaysClientsCount',
        helper: 'Coaching queue',
        icon: Icons.today_rounded,
        color: AppColors.primaryBright,
      ),
      _DashboardStatData(
        label: 'Follow-ups',
        value: '$pendingFollowUpsCount',
        helper: 'Pending tasks',
        icon: Icons.assignment_late_outlined,
        color: AppColors.accentPurple,
      ),
      _DashboardStatData(
        label: 'Plans',
        value: '$workoutPlansAssignedCount',
        helper: 'Assigned workouts',
        icon: Icons.fitness_center_rounded,
        color: AppColors.primaryBright,
      ),
    ];
    final focusBanner = pendingFollowUpsCount > 0
        ? _TrainerFocusBannerData(
            eyebrow: 'Coaching focus',
            title: '$pendingFollowUpsCount follow-ups need attention',
            description:
                'Open your task queue and complete the next member follow-up.',
            label: 'Open tasks',
            icon: Icons.assignment_late_outlined,
            onTap: onOpenTasks,
          )
        : unreadChatsCount > 0
        ? _TrainerFocusBannerData(
            eyebrow: 'Messages',
            title: '$unreadChatsCount unread member messages',
            description:
                'Continue your coaching conversations and clear the inbox.',
            label: 'Open chats',
            icon: Icons.chat_bubble_rounded,
            onTap: onOpenChat,
          )
        : trialLeadCount > 0
        ? _TrainerFocusBannerData(
            eyebrow: 'Trial leads',
            title: '$trialLeadCount trial requests are assigned',
            description:
                'Review the latest requests and prepare for upcoming trials.',
            label: 'View leads',
            icon: Icons.person_add_alt_1_rounded,
            onTap: onOpenNotifications,
          )
        : _TrainerFocusBannerData(
            eyebrow: 'Today focus',
            title: 'Your coaching workspace is ready',
            description:
                'Review assigned members and prepare their next workout plan.',
            label: 'View members',
            icon: Icons.groups_rounded,
            onTap: onOpenMembers,
          );

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: _PremiumDashboardBackground(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 104),
          children: [
            RevealOnBuild(
              child: _FitnessWelcomeBar(
                firstName: firstName,
                subtitle: 'Ready for today\'s coaching?',
                unreadMessages: unreadMessages,
                onOpenNotifications: onOpenNotifications,
                onOpenSettings: onOpenSettings,
              ),
            ),
            const SizedBox(height: 14),
            RevealOnBuild(
              delay: const Duration(milliseconds: 40),
              child: _FitnessHeroCard(
                gymName: gymName,
                branchName: branchName,
                specialization: specialization?.isNotEmpty == true
                    ? specialization!
                    : 'Complete trainer profile',
                completion: completion,
                onOpenMembers: onOpenMembers,
                onEditProfile: onEditProfile,
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 65),
              child: _DashboardSection(
                eyebrow: 'Snapshot',
                title: 'Today at a glance',
                child: _TrainerMetricGrid(metrics: snapshotMetrics),
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 85),
              child: _DashboardSection(
                eyebrow: 'Focus',
                title: 'What deserves attention now',
                child: _TrainerFocusBanner(data: focusBanner),
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 95),
              child: _DashboardSection(
                eyebrow: 'Plan tools',
                title: 'Build the next client plan',
                child: _TrainerPlanTools(
                  onOpenWorkouts: onOpenWorkouts,
                  onOpenDiet: onOpenDiet,
                ),
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 110),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  if (!hasWideSplit) {
                    return Column(
                      children: [
                        _DashboardSection(
                          eyebrow: 'Clients',
                          title: 'Today\'s coaching queue',
                          child: _TodayClientsPanel(
                            clients: todayClientPreview,
                            onOpenMembers: onOpenMembers,
                          ),
                        ),
                        const SizedBox(height: 16),
                        _DashboardSection(
                          eyebrow: 'Tasks',
                          title: 'Pending follow-ups',
                          child: _FollowUpPanel(
                            followUps: followUpPreview,
                            onOpenTasks: onOpenTasks,
                          ),
                        ),
                      ],
                    );
                  }

                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: _DashboardSection(
                          eyebrow: 'Clients',
                          title: 'Today\'s coaching queue',
                          child: _TodayClientsPanel(
                            clients: todayClientPreview,
                            onOpenMembers: onOpenMembers,
                          ),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: _DashboardSection(
                          eyebrow: 'Tasks',
                          title: 'Pending follow-ups',
                          child: _FollowUpPanel(
                            followUps: followUpPreview,
                            onOpenTasks: onOpenTasks,
                          ),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 130),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  if (!hasWideSplit) {
                    return Column(
                      children: [
                        _DashboardSection(
                          eyebrow: 'Insights',
                          title: 'Trial leads',
                          child: _TrialLeadPanel(
                            trialRequests: trialPreview,
                            onOpenNotifications: onOpenNotifications,
                          ),
                        ),
                        const SizedBox(height: 16),
                        _DashboardSection(
                          eyebrow: 'Progress',
                          title: 'Recent member progress',
                          child: _ProgressPanel(members: recentProgressMembers),
                        ),
                      ],
                    );
                  }

                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: _DashboardSection(
                          eyebrow: 'Insights',
                          title: 'Trial leads',
                          child: _TrialLeadPanel(
                            trialRequests: trialPreview,
                            onOpenNotifications: onOpenNotifications,
                          ),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: _DashboardSection(
                          eyebrow: 'Progress',
                          title: 'Recent member progress',
                          child: _ProgressPanel(members: recentProgressMembers),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: 16),
            RevealOnBuild(
              delay: const Duration(milliseconds: 145),
              child: _DashboardSection(
                eyebrow: 'Chats',
                title: 'Unread conversations',
                child: _ChatPanel(
                  chatConversations: chatConversations,
                  onOpenChat: onOpenChat,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TrainerPlanTools extends StatelessWidget {
  const _TrainerPlanTools({
    required this.onOpenWorkouts,
    required this.onOpenDiet,
  });

  final VoidCallback onOpenWorkouts;
  final VoidCallback onOpenDiet;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final stacked = constraints.maxWidth < 520;
        final workout = _TrainerPlanToolCard(
          title: 'Workout Builder',
          subtitle: 'Create sessions and assign a training plan.',
          icon: Icons.fitness_center_rounded,
          color: AppColors.primary,
          onTap: onOpenWorkouts,
        );
        final diet = _TrainerPlanToolCard(
          title: 'Diet Builder',
          subtitle: 'Create meal plans or start from a template.',
          icon: Icons.restaurant_menu_rounded,
          color: AppColors.accentPurple,
          onTap: onOpenDiet,
        );

        if (stacked) {
          return Column(children: [workout, const SizedBox(height: 12), diet]);
        }

        return Row(
          children: [
            Expanded(child: workout),
            const SizedBox(width: 12),
            Expanded(child: diet),
          ],
        );
      },
    );
  }
}

class _TrainerPlanToolCard extends StatelessWidget {
  const _TrainerPlanToolCard({
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

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: color.withValues(alpha: 0.16)),
          ),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(icon, color: color),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textMuted,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(Icons.arrow_forward_rounded, size: 20, color: color),
            ],
          ),
        ),
      ),
    );
  }
}

class _DashboardSection extends StatelessWidget {
  const _DashboardSection({
    required this.eyebrow,
    required this.title,
    required this.child,
  });

  final String eyebrow;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 2, bottom: 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                eyebrow.toUpperCase(),
                style: theme.textTheme.labelSmall?.copyWith(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 0.9,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ),
        child,
      ],
    );
  }
}

class _PremiumDashboardBackground extends StatefulWidget {
  const _PremiumDashboardBackground({required this.child});

  final Widget child;

  @override
  State<_PremiumDashboardBackground> createState() =>
      _PremiumDashboardBackgroundState();
}

class _PremiumDashboardBackgroundState
    extends State<_PremiumDashboardBackground>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 14),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final phase = _controller.value * math.pi * 2;
        final topRightDx = math.sin(phase) * 10;
        final topRightDy = math.cos(phase) * 8;
        final leftDx = math.cos(phase * 0.8) * 8;
        final leftDy = math.sin(phase * 0.8) * 10;
        final bottomDx = math.sin(phase * 0.6) * 12;
        final bottomDy = math.cos(phase * 0.6) * 9;

        return Stack(
          children: [
            Container(
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    const Color(0xFFF8FAFC),
                    const Color(0xFFF1F5F9).withValues(alpha: 0.92),
                  ],
                ),
              ),
            ),
            Positioned(
              top: -96 + topRightDy,
              right: -82 + topRightDx,
              child: const _DashboardGlowOrb(
                size: 220,
                color: AppColors.primary,
                opacity: 0.08,
              ),
            ),
            Positioned(
              top: 280 + leftDy,
              left: -110 + leftDx,
              child: const _DashboardGlowOrb(
                size: 210,
                color: AppColors.primaryBright,
                opacity: 0.04,
              ),
            ),
            Positioned(
              bottom: 140 + bottomDy,
              right: -120 + bottomDx,
              child: const _DashboardGlowOrb(
                size: 230,
                color: AppColors.accentPurple,
                opacity: 0.035,
              ),
            ),
            widget.child,
          ],
        );
      },
    );
  }
}

class _DashboardGlowOrb extends StatelessWidget {
  const _DashboardGlowOrb({
    required this.size,
    required this.color,
    required this.opacity,
  });

  final double size;
  final Color color;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [
              color.withValues(alpha: opacity),
              color.withValues(alpha: 0),
            ],
          ),
        ),
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
    this.helper = '',
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final String helper;
  final IconData icon;
  final Color color;
}

class _FitnessWelcomeBar extends StatelessWidget {
  const _FitnessWelcomeBar({
    required this.firstName,
    required this.subtitle,
    required this.unreadMessages,
    required this.onOpenNotifications,
    required this.onOpenSettings,
  });

  final String firstName;
  final String subtitle;
  final int unreadMessages;
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
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          _SquareIconButton(
            icon: Icons.notifications_none_rounded,
            count: unreadMessages,
            onTap: onOpenNotifications,
          ),
          const SizedBox(width: 10),
          _SquareIconButton(
            icon: Icons.settings_rounded,
            onTap: onOpenSettings,
          ),
        ],
      ),
    );
  }
}

class _SquareIconButton extends StatelessWidget {
  const _SquareIconButton({
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
                  color: AppColors.shadow.withValues(alpha: 0.10),
                  blurRadius: 14,
                  offset: const Offset(0, 8),
                ),
              ],
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: AppColors.stroke.withValues(alpha: 0.5),
              ),
            ),
            child: Icon(icon, color: AppColors.textPrimary, size: 21),
          ),
          if (count > 0)
            Positioned(
              top: -4,
              right: -4,
              child: Container(
                constraints: const BoxConstraints(minWidth: 19, minHeight: 19),
                padding: const EdgeInsets.symmetric(horizontal: 5),
                decoration: BoxDecoration(
                  color: AppColors.primary,
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

class _FitnessHeroCard extends StatelessWidget {
  const _FitnessHeroCard({
    required this.gymName,
    required this.branchName,
    required this.specialization,
    required this.completion,
    required this.onOpenMembers,
    required this.onEditProfile,
  });

  final String gymName;
  final String branchName;
  final String specialization;
  final double completion;
  final VoidCallback onOpenMembers;
  final Future<void> Function() onEditProfile;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final progress = (completion.clamp(0, 100) / 100).toDouble();
    final chips = <_TrainerHeroChipData>[
      _TrainerHeroChipData(icon: Icons.storefront_rounded, label: gymName),
      _TrainerHeroChipData(icon: Icons.location_on_outlined, label: branchName),
    ];
    return ClipRRect(
      borderRadius: BorderRadius.circular(34),
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
          border: Border.all(color: AppColors.stroke.withValues(alpha: 0.8)),
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
              top: -54,
              left: -28,
              child: Container(
                width: 134,
                height: 134,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.34),
                ),
              ),
            ),
            const Positioned(
              top: -34,
              right: -28,
              child: _DashboardGlowOrb(
                size: 154,
                color: AppColors.primary,
                opacity: 0.14,
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final compact = constraints.maxWidth < 360;
                  final ringSize = compact ? 86.0 : 104.0;
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 7,
                                  ),
                                  decoration: BoxDecoration(
                                    color: Colors.white.withValues(alpha: 0.68),
                                    borderRadius: BorderRadius.circular(999),
                                    border: Border.all(
                                      color: AppColors.stroke.withValues(
                                        alpha: 0.5,
                                      ),
                                    ),
                                  ),
                                  child: Text(
                                    specialization.toUpperCase(),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: theme.textTheme.labelSmall?.copyWith(
                                      color: AppColors.primary,
                                      fontWeight: FontWeight.w900,
                                      letterSpacing: 0.9,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  'Coaching overview',
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.displaySmall?.copyWith(
                                    color: AppColors.textPrimary,
                                    fontWeight: FontWeight.w900,
                                    height: 0.95,
                                    letterSpacing: -1.4,
                                    fontSize: compact ? 28 : null,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  'Members, plans and follow-ups in one place',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.labelSmall?.copyWith(
                                    color: AppColors.textSecondary,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 14),
                          _TrainerHeroProgressOrb(
                            progress: progress,
                            progressLabel: '${completion.round()}%',
                            size: ringSize,
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: chips
                            .map((chip) => _TrainerHeroChip(data: chip))
                            .toList(),
                      ),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          Expanded(
                            child: _ReferenceMiniButton(
                              title: 'Assigned Members',
                              onPressed: onOpenMembers,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _ReferenceMiniButton(
                              title: 'Edit Profile',
                              secondary: true,
                              onPressed: onEditProfile,
                            ),
                          ),
                        ],
                      ),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ReferenceMiniButton extends StatelessWidget {
  const _ReferenceMiniButton({
    required this.title,
    required this.onPressed,
    this.secondary = false,
  });

  final String title;
  final VoidCallback onPressed;
  final bool secondary;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        height: 48,
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          gradient: secondary
              ? null
              : const LinearGradient(
                  colors: [AppColors.primaryBright, AppColors.primary],
                ),
          color: secondary ? Colors.white.withValues(alpha: 0.70) : null,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: secondary
                ? AppColors.stroke.withValues(alpha: 0.72)
                : Colors.transparent,
          ),
        ),
        child: Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: secondary ? AppColors.textPrimary : Colors.white,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

class _TrainerHeroChipData {
  const _TrainerHeroChipData({required this.icon, required this.label});

  final IconData icon;
  final String label;
}

class _TrainerHeroChip extends StatelessWidget {
  const _TrainerHeroChip({required this.data});

  final _TrainerHeroChipData data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.4)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(data.icon, size: 14, color: AppColors.primary),
          const SizedBox(width: 6),
          Text(
            data.label,
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

class _TrainerHeroProgressOrb extends StatelessWidget {
  const _TrainerHeroProgressOrb({
    required this.progress,
    required this.progressLabel,
    required this.size,
  });

  final double progress;
  final String progressLabel;
  final double size;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: SizedBox(
        width: size,
        height: size,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Container(
              width: size,
              height: size,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.78),
                border: Border.all(
                  color: AppColors.stroke.withValues(alpha: 0.7),
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.10),
                    blurRadius: 16,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
            ),
            TweenAnimationBuilder<double>(
              tween: Tween<double>(begin: 0, end: progress),
              duration: const Duration(milliseconds: 850),
              curve: Curves.easeOutCubic,
              builder: (context, value, _) => SizedBox(
                width: size - 26,
                height: size - 26,
                child: CircularProgressIndicator(
                  value: value,
                  strokeWidth: size < 100 ? 8 : 10,
                  backgroundColor: AppColors.stroke.withValues(alpha: 0.8),
                  valueColor: const AlwaysStoppedAnimation<Color>(
                    AppColors.primary,
                  ),
                ),
              ),
            ),
            Text(
              progressLabel,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w900,
                fontSize: size < 100 ? 18 : null,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TrainerFocusBannerData {
  const _TrainerFocusBannerData({
    required this.eyebrow,
    required this.title,
    required this.description,
    required this.label,
    required this.icon,
    required this.onTap,
  });

  final String eyebrow;
  final String title;
  final String description;
  final String label;
  final IconData icon;
  final VoidCallback onTap;
}

class _TrainerMetricGrid extends StatelessWidget {
  const _TrainerMetricGrid({required this.metrics});

  final List<_DashboardStatData> metrics;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 620 ? 4 : 2;
        final spacing = constraints.maxWidth >= 620 ? 12.0 : 10.0;
        final tileWidth =
            (constraints.maxWidth - (spacing * (columns - 1))) / columns;

        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final entry in metrics.asMap().entries)
              SizedBox(
                width: tileWidth,
                child: RevealOnBuild(
                  delay: Duration(milliseconds: 30 * entry.key),
                  offset: const Offset(0, 0.06),
                  duration: const Duration(milliseconds: 420),
                  child: _TrainerMetricCard(data: entry.value),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _TrainerMetricCard extends StatelessWidget {
  const _TrainerMetricCard({required this.data});

  final _DashboardStatData data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.75)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.08),
            blurRadius: 12,
            offset: const Offset(0, 8),
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
              color: data.color.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: data.color.withValues(alpha: 0.14)),
            ),
            child: Icon(data.icon, color: data.color, size: 20),
          ),
          const SizedBox(height: 18),
          Text(
            data.value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            data.label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            data.helper,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _TrainerFocusBanner extends StatelessWidget {
  const _TrainerFocusBanner({required this.data});

  final _TrainerFocusBannerData data;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: data.onTap,
      borderRadius: BorderRadius.circular(28),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppColors.surface, AppColors.surfaceSoft],
          ),
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: AppColors.stroke.withValues(alpha: 0.72)),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.08),
              blurRadius: 16,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: AppColors.primaryBright.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: AppColors.primaryBright.withValues(alpha: 0.14),
                ),
              ),
              child: Icon(data.icon, color: AppColors.primaryBright, size: 24),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    data.eyebrow.toUpperCase(),
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: AppColors.primaryBright,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 0.7,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    data.title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    data.description,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w700,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  data.label,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: AppColors.primaryBright,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                const Icon(
                  Icons.arrow_forward_rounded,
                  color: AppColors.primaryBright,
                  size: 18,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionTitleRow extends StatelessWidget {
  const _SectionTitleRow({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

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
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                subtitle,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
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
                color: AppColors.primary,
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
                color: AppColors.primaryBright,
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
                color: AppColors.primary,
              );
            }).toList(),
    );
  }
}

class _TrialLeadPanel extends StatelessWidget {
  const _TrialLeadPanel({
    required this.trialRequests,
    required this.onOpenNotifications,
  });

  final List<Map<String, dynamic>> trialRequests;
  final VoidCallback onOpenNotifications;

  @override
  Widget build(BuildContext context) {
    return _FitnessPanel(
      title: 'Trial leads',
      subtitle: 'Assigned trial follow-ups',
      children: trialRequests.isEmpty
          ? const [
              _PanelEmpty(
                icon: Icons.person_add_alt_1_rounded,
                title: 'No active trial leads',
                subtitle: 'New trial requests will appear here.',
              ),
            ]
          : trialRequests.map((item) {
              final member = _map(item['member']);
              final preferredDate = prettyDate(item['preferred_date']);
              final preferredTime = item['preferred_time']?.toString().trim();
              final subtitle = [
                if (preferredDate.isNotEmpty && preferredDate != '--')
                  preferredDate,
                if (preferredTime != null && preferredTime.isNotEmpty)
                  preferredTime,
              ].join(' • ');
              return _WorkoutStyleRow(
                title:
                    member['name']?.toString() ??
                    item['name']?.toString() ??
                    'Trial lead',
                subtitle: subtitle.isEmpty
                    ? 'Assigned trial request'
                    : subtitle,
                meta: item['status']?.toString() ?? 'pending',
                icon: Icons.person_add_alt_1_rounded,
                color: AppColors.accentPurple,
                onTap: onOpenNotifications,
              );
            }).toList(),
    );
  }
}

class _ChatPanel extends StatelessWidget {
  const _ChatPanel({required this.chatConversations, required this.onOpenChat});

  final List<Map<String, dynamic>> chatConversations;
  final VoidCallback onOpenChat;

  @override
  Widget build(BuildContext context) {
    final unread = chatConversations
        .where(
          (item) =>
              ((_map(item)['trainer_unread_count'] as num?)?.toInt() ?? 0) > 0,
        )
        .take(4)
        .toList();

    return _FitnessPanel(
      title: 'Chats',
      subtitle: 'Unread member conversations',
      children: unread.isEmpty
          ? const [
              _PanelEmpty(
                icon: Icons.chat_bubble_outline_rounded,
                title: 'No unread chats',
                subtitle: 'New member messages will appear here.',
              ),
            ]
          : unread.map((item) {
              final peer = _map(item['peer']);
              final lastMessage = _map(item['last_message']);
              final unreadCount =
                  ((_map(item)['trainer_unread_count'] as num?)?.toInt() ?? 0);
              return _WorkoutStyleRow(
                title: peer['name']?.toString() ?? 'Member chat',
                subtitle:
                    lastMessage['body']?.toString().trim().isNotEmpty == true
                    ? lastMessage['body']!.toString()
                    : 'Unread member message',
                meta: unreadCount == 1 ? '1 unread' : '$unreadCount unread',
                icon: Icons.chat_bubble_rounded,
                color: AppColors.primaryBright,
                onTap: onOpenChat,
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
  });

  final String title;
  final String subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.88),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.75)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionTitleRow(title: title, subtitle: subtitle),
          const SizedBox(height: 4),
          ...children.expand((child) sync* {
            yield child;
            if (child != children.last) {
              yield Divider(height: 1, color: AppColors.stroke);
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
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    color.withValues(alpha: 0.10),
                    AppColors.primaryBright.withValues(alpha: 0.06),
                  ],
                ),
                borderRadius: BorderRadius.circular(17),
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
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(
              meta,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: AppColors.primary,
                fontWeight: FontWeight.w900,
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
    return Padding(
      padding: const EdgeInsets.only(top: 10, bottom: 4),
      child: Row(
        children: [
          Icon(icon, color: AppColors.primary, size: 24),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
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

class _MemberPage extends StatefulWidget {
  const _MemberPage({
    required this.members,
    required this.plans,
    required this.onRefresh,
    required this.hasMore,
    required this.loadingMore,
    required this.onLoadMore,
    required this.onOpenMember,
    required this.onQuickNote,
    required this.onQuickAssign,
    required this.onManageWorkouts,
    required this.onSendMessage,
    required this.onAddFollowUp,
    required this.onAddMember,
    required this.isIndependentTrainer,
    required this.verificationStatus,
    required this.verificationReason,
    required this.pendingInvitationCount,
    required this.onManageInvitations,
  });

  final List<Map<String, dynamic>> members;
  final List<Map<String, dynamic>> plans;
  final Future<void> Function() onRefresh;
  final bool hasMore;
  final bool loadingMore;
  final Future<void> Function() onLoadMore;
  final Future<void> Function(Map<String, dynamic>) onOpenMember;
  final Future<void> Function(Map<String, dynamic>) onQuickNote;
  final Future<void> Function(Map<String, dynamic>) onQuickAssign;
  final Future<void> Function(Map<String, dynamic>) onManageWorkouts;
  final Future<void> Function(Map<String, dynamic>) onSendMessage;
  final Future<void> Function(Map<String, dynamic>) onAddFollowUp;
  final Future<void> Function() onAddMember;
  final bool isIndependentTrainer;
  final String verificationStatus;
  final String? verificationReason;
  final int pendingInvitationCount;
  final Future<void> Function() onManageInvitations;

  @override
  State<_MemberPage> createState() => _MemberPageState();
}

class _MemberPageState extends State<_MemberPage> {
  final TextEditingController _searchController = TextEditingController();
  bool _dueOnly = false;
  bool _needsPlanOnly = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = _searchController.text.trim().toLowerCase();
    final filteredMembers = widget.members.where((assignment) {
      final member = _map(assignment['member']);
      final name = member['name']?.toString().toLowerCase() ?? '';
      final goal =
          _map(assignment['progress_summary'])['fitness_goal']?.toString() ??
          '';
      final goalLower = goal.toLowerCase();
      final membershipSummary = _map(assignment['membership_summary']);
      final memberPlans = widget.plans.where(
        (plan) => _planMatchesAssignment(plan, assignment),
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

      return true;
    }).toList();

    if (widget.members.isEmpty) {
      return RefreshIndicator(
        onRefresh: widget.onRefresh,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 104),
          children: [
            _MembersPageHeader(totalCount: 0, onInvite: widget.onAddMember),
            if (widget.isIndependentTrainer) ...[
              const SizedBox(height: 12),
              _IndependentTrainerStatusCard(
                verificationStatus: widget.verificationStatus,
                verificationReason: widget.verificationReason,
                pendingInvitationCount: widget.pendingInvitationCount,
                onTap: widget.onManageInvitations,
              ),
            ],
            const SizedBox(height: 16),
            _MembersSearchCard(
              controller: _searchController,
              query: query,
              dueOnly: _dueOnly,
              needsPlanOnly: _needsPlanOnly,
              onQueryChanged: (_) => setState(() {}),
              onClear: () {
                _searchController.clear();
                setState(() {});
              },
              onDueOnlyChanged: (value) => setState(() => _dueOnly = value),
              onNeedsPlanChanged: (value) =>
                  setState(() => _needsPlanOnly = value),
            ),
            const SizedBox(height: 16),
            PremiumCard(
              padding: const EdgeInsets.all(22),
              child: Column(
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.10),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: const Icon(
                      Icons.groups_outlined,
                      color: AppColors.primary,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    'No members yet',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    widget.isIndependentTrainer
                        ? widget.verificationStatus == 'verified'
                              ? 'Invite a member to start a coaching relationship. Their gym subscription stays separate.'
                              : 'Complete Atlas verification before inviting independent members.'
                        : 'Members assigned by your gym will appear here.',
                    textAlign: TextAlign.center,
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

    return RefreshIndicator(
      onRefresh: widget.onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 10, 18, 104),
        children: [
          _MembersPageHeader(
            totalCount: widget.members.length,
            onInvite: widget.onAddMember,
          ),
          if (widget.isIndependentTrainer) ...[
            const SizedBox(height: 12),
            _IndependentTrainerStatusCard(
              verificationStatus: widget.verificationStatus,
              verificationReason: widget.verificationReason,
              pendingInvitationCount: widget.pendingInvitationCount,
              onTap: widget.onManageInvitations,
            ),
          ],
          const SizedBox(height: 16),
          _MembersSearchCard(
            controller: _searchController,
            query: query,
            dueOnly: _dueOnly,
            needsPlanOnly: _needsPlanOnly,
            onQueryChanged: (_) => setState(() {}),
            onClear: () {
              _searchController.clear();
              setState(() {});
            },
            onDueOnlyChanged: (value) => setState(() => _dueOnly = value),
            onNeedsPlanChanged: (value) =>
                setState(() => _needsPlanOnly = value),
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
          if (widget.hasMore) ...[
            const SizedBox(height: 4),
            Center(
              child: OutlinedButton.icon(
                onPressed: widget.loadingMore ? null : widget.onLoadMore,
                icon: widget.loadingMore
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more_rounded),
                label: Text(
                  widget.loadingMore ? 'Loading...' : 'Load more members',
                ),
              ),
            ),
          ],
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
    required this.initialAssignmentKey,
    required this.onRefresh,
    required this.hasMore,
    required this.loadingMore,
    required this.onLoadMore,
  });

  final Map<String, dynamic> contextData;
  final List<Map<String, dynamic>> members;
  final List<Map<String, dynamic>> templates;
  final List<Map<String, dynamic>> plans;
  final List<Map<String, dynamic>> exercises;
  final TrainerRepository repository;
  final String? initialAssignmentKey;
  final Future<void> Function() onRefresh;
  final bool hasMore;
  final bool loadingMore;
  final Future<void> Function() onLoadMore;

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
  String? _selectedAssignmentKey;
  int? _selectedTemplateId;
  int? _selectedExerciseId;
  bool _savingPlan = false;
  bool _savingExercise = false;
  int _workoutTabIndex = 1;
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
    _selectedAssignmentKey =
        _validAssignmentKey(widget.initialAssignmentKey) ??
        (widget.members.firstOrNull == null
            ? null
            : _assignmentKey(widget.members.first));
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
    if (widget.initialAssignmentKey != oldWidget.initialAssignmentKey) {
      final focusedAssignmentKey = _validAssignmentKey(
        widget.initialAssignmentKey,
      );
      if (focusedAssignmentKey != null) {
        setState(() => _selectedAssignmentKey = focusedAssignmentKey);
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

  String? _validAssignmentKey(String? assignmentKey) {
    if (assignmentKey == null) {
      return null;
    }
    final exists = widget.members.any(
      (member) => _assignmentKey(member) == assignmentKey,
    );
    return exists ? assignmentKey : null;
  }

  @override
  Widget build(BuildContext context) {
    if (_workoutTabIndex == 2) {
      return TrainerDietPlanScreen(
        repository: widget.repository,
        members: widget.members,
        embedded: true,
        plannerNavigation: _TrainerWorkoutTabs(
          selectedIndex: _workoutTabIndex,
          onChanged: (index) => setState(() => _workoutTabIndex = index),
        ),
      );
    }
    if (_workoutTabIndex == 0) {
      return _buildWorkoutLibrary(context);
    }

    final selectedMember = widget.members.firstWhere(
      (item) => _assignmentKey(item) == _selectedAssignmentKey,
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
    final selectedMemberPlans = widget.plans
        .where((plan) => _planMatchesAssignment(plan, selectedMember))
        .toList();

    return ListView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(18, 12, 18, 104),
      children: [
        _TrainerWorkoutHero(
          templateCount: widget.templates.length,
          exerciseCount: widget.exercises.length,
        ),
        const SizedBox(height: 22),
        if (widget.members.isEmpty && widget.contextData.isEmpty)
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
                        DropdownButtonFormField<String>(
                          key: ValueKey('member-$_selectedAssignmentKey'),
                          initialValue: _selectedAssignmentKey,
                          isExpanded: true,
                          items: widget.members
                              .map(
                                (member) => DropdownMenuItem<String>(
                                  value: _assignmentKey(member),
                                  child: Text(
                                    '${_map(member['member'])['name']?.toString() ?? 'Member'} · '
                                    '${_assignmentScopeLabel(member)}',
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: (value) =>
                              setState(() => _selectedAssignmentKey = value),
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
                    title: 'Workout details',
                    subtitle:
                        'Create a reusable program with a clear goal and weekly structure.',
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
        if (widget.hasMore) ...[
          const SizedBox(height: 16),
          _workoutLoadMoreButton(),
        ],
      ],
    );
  }

  Widget _buildWorkoutLibrary(BuildContext context) {
    return ListView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(18, 12, 18, 104),
      children: [
        _TrainerWorkoutHero(
          templateCount: widget.templates.length,
          exerciseCount: widget.exercises.length,
        ),
        const SizedBox(height: 16),
        _TrainerWorkoutTabs(
          selectedIndex: _workoutTabIndex,
          onChanged: (index) => setState(() => _workoutTabIndex = index),
        ),
        const SizedBox(height: 16),
        _TrainerWorkoutSection(
          title: 'Workout library',
          subtitle:
              'Reusable programs created by you and programs available from Atlas.',
          icon: Icons.library_books_rounded,
          child: widget.templates.isEmpty
              ? const EmptyStateView(
                  title: 'Your library is empty',
                  message:
                      'Open Workouts and create your first reusable program.',
                  icon: Icons.fitness_center_outlined,
                )
              : Column(
                  children: widget.templates.map((template) {
                    final days = _mapList(template['days']);
                    final exerciseCount = days.fold<int>(
                      0,
                      (total, day) => total + _mapList(day['exercises']).length,
                    );
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _TrainerWorkoutTile(
                        title:
                            template['name']?.toString() ?? 'Workout program',
                        subtitle:
                            '${template['goal']?.toString() ?? 'Flexible goal'} • ${days.length} days • $exerciseCount exercises',
                        badge: template['is_public_catalog'] == true
                            ? 'Atlas'
                            : template['difficulty']?.toString(),
                        icon: template['is_public_catalog'] == true
                            ? Icons.public_rounded
                            : Icons.fitness_center_rounded,
                        actionLabel: 'Preview',
                        onAction: () => _openWorkoutTemplatePreview(template),
                      ),
                    );
                  }).toList(),
                ),
        ),
        const SizedBox(height: 16),
        _TrainerWorkoutSection(
          title: 'Exercise library',
          subtitle: 'Exercises available while building your workout programs.',
          icon: Icons.sports_gymnastics_rounded,
          child: widget.exercises.isEmpty
              ? const EmptyStateView(
                  title: 'No exercises yet',
                  message:
                      'Create an exercise from the Workouts builder to add it here.',
                  icon: Icons.sports_gymnastics_outlined,
                )
              : Column(
                  children: widget.exercises.map((exercise) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _TrainerWorkoutTile(
                        title: exercise['name']?.toString() ?? 'Exercise',
                        subtitle:
                            [
                                  _exerciseBodyPartLabel(exercise),
                                  exercise['equipment']?.toString(),
                                ]
                                .whereType<String>()
                                .where((item) => item.isNotEmpty)
                                .join(' • '),
                        badge: exercise['is_global'] == true ? 'Atlas' : null,
                        icon: Icons.sports_gymnastics_rounded,
                      ),
                    );
                  }).toList(),
                ),
        ),
        if (widget.hasMore) ...[
          const SizedBox(height: 16),
          _workoutLoadMoreButton(),
        ],
      ],
    );
  }

  Widget _workoutLoadMoreButton() => Center(
    child: OutlinedButton.icon(
      onPressed: widget.loadingMore ? null : widget.onLoadMore,
      icon: widget.loadingMore
          ? const SizedBox.square(
              dimension: 16,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : const Icon(Icons.expand_more_rounded),
      label: Text(widget.loadingMore ? 'Loading...' : 'Load more workout data'),
    ),
  );

  Future<void> _openWorkoutTemplatePreview(
    Map<String, dynamic> template,
  ) async {
    var detail = Map<String, dynamic>.from(template);
    final templateId = (template['id'] as num?)?.toInt();
    if (templateId != null) {
      try {
        final response = await widget.repository.fetchWorkoutTemplate(
          templateId,
        );
        final fetched = _map(response['data']);
        if (fetched.isNotEmpty) {
          detail = fetched;
        }
      } catch (_) {
        // The list payload is still useful if the detail refresh is unavailable.
      }
    }
    if (!mounted) {
      return;
    }
    await showWorkoutPlanSummarySheet(context, plan: detail);
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
      (item) => _assignmentKey(item) == _selectedAssignmentKey,
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
      (item) => _assignmentKey(item) == _selectedAssignmentKey,
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
    int? gymId,
    int? branchId,
    required int durationWeeks,
    required List<String> days,
    required List<Map<String, dynamic>> payloadDays,
  }) {
    return <String, dynamic>{
      if (gymId != null) 'gym_id': gymId,
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
    final relationshipId = _intValue(selectedMember['relationship_id']);
    final templateId = (selectedTemplate['id'] as num?)?.toInt();

    if (memberId == null ||
        (relationshipId == null && (gymId == null || branchId == null))) {
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
        if (gymId != null) 'gym_id': gymId,
        if (branchId != null) 'branch_id': branchId,
        if (relationshipId != null)
          'independent_trainer_member_relationship_id': relationshipId,
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
    final relationshipId = _intValue(selectedMember['relationship_id']);
    final memberId = _intValue(selectedMember['member_id']);
    final days = _selectedWeekDays.toList()
      ..sort((a, b) => _dayNumbers[a]!.compareTo(_dayNumbers[b]!));
    if (days.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select at least one training day.')),
      );
      return;
    }
    if (gymId == null && relationshipId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Select a valid coaching member before saving.'),
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
      final payload = _templatePayload(
        gymId: gymId,
        branchId: branchId,
        durationWeeks: durationWeeks,
        days: days,
        payloadDays: payloadDays,
      );
      final response = relationshipId != null && memberId != null
          ? await widget.repository.createWorkoutPlan({
              ...payload,
              'independent_trainer_member_relationship_id': relationshipId,
              'member_ids': <int>[memberId],
              'starts_on': DateTime.now().toIso8601String().split('T').first,
            })
          : await widget.repository.createWorkoutTemplate(payload);
      final createdTemplateId = (_map(response['data'])['id'] as num?)?.toInt();
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            relationshipId != null
                ? 'Independent workout assigned to the member.'
                : 'Workout saved to your library.',
          ),
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
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
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
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        children: [
          Expanded(
            child: _TrainerWorkoutTabButton(
              label: 'Workouts',
              icon: Icons.fitness_center_rounded,
              selected: selectedIndex == 1,
              onTap: () => onChanged(1),
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: _TrainerWorkoutTabButton(
              label: 'Diet',
              icon: Icons.restaurant_menu_rounded,
              selected: selectedIndex == 2,
              onTap: () => onChanged(2),
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: _TrainerWorkoutTabButton(
              label: 'Library',
              icon: Icons.library_books_rounded,
              selected: selectedIndex == 0,
              onTap: () => onChanged(0),
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
          color: selected ? AppColors.surface : Colors.transparent,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 17,
              color: selected ? AppColors.primary : AppColors.textMuted,
            ),
            const SizedBox(width: 7),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: selected
                      ? AppColors.textPrimary
                      : AppColors.textSecondary,
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
    required this.templateCount,
    required this.exerciseCount,
  });

  final int templateCount;
  final int exerciseCount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 2),
      decoration: BoxDecoration(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Workout studio',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '$templateCount programs • $exerciseCount exercises',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: AppColors.stroke),
            ),
            child: const Icon(
              Icons.fitness_center_rounded,
              color: AppColors.primary,
              size: 21,
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
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.stroke),
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
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(15),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: Icon(icon, color: AppColors.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: const TextStyle(
                        color: AppColors.textPrimary,
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        color: AppColors.textSecondary,
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
  static const Color black = AppColors.textPrimary;
  static const Color gray = AppColors.textSecondary;
  static const Color field = AppColors.surfaceSoft;
  static const Color border = AppColors.stroke;
  static const Color primaryStart = AppColors.primaryBright;
  static const Color primaryEnd = AppColors.primary;

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

class _ChatPage extends StatefulWidget {
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
  State<_ChatPage> createState() => _ChatPageState();
}

class _ChatPageState extends State<_ChatPage> {
  final TextEditingController _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final assignmentsByMember = <int, Map<String, dynamic>>{};
    for (final assignment in widget.members) {
      final memberId = _intValue(assignment['member_id']);
      if (memberId == null) continue;
      final current = assignmentsByMember.putIfAbsent(
        memberId,
        () => {...assignment, 'chat_scope_labels': <String>[]},
      );
      final labels = (current['chat_scope_labels'] as List<String>);
      final label = _assignmentScopeLabel(assignment);
      if (!labels.contains(label)) labels.add(label);
    }
    final filteredMembers = assignmentsByMember.values.where((assignment) {
      if (_query.isEmpty) {
        return true;
      }
      final member = _map(assignment['member']);
      final searchable = '${member['name'] ?? ''} ${member['email'] ?? ''}'
          .toLowerCase();
      return searchable.contains(_query);
    }).toList();
    final inbox = _TrainerChatInboxList(
      members: filteredMembers,
      selectedMemberId: null,
      conversationForMember: _conversationForMember,
      onSelectMember: widget.onSelectMember,
      emptyTitle: _query.isEmpty ? 'No members assigned' : 'No member found',
      emptyMessage: _query.isEmpty
          ? 'Assigned members will appear here when they are ready to chat.'
          : 'Try another name or email address.',
    );

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
          child: Column(
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
                  if (widget.onRefresh != null)
                    _SquareIconButton(
                      icon: widget.loading
                          ? Icons.sync_rounded
                          : Icons.refresh_rounded,
                      onTap: widget.loading ? () {} : () => widget.onRefresh!(),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              Container(
                height: 50,
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(17),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: TextField(
                  controller: _searchController,
                  onChanged: (value) =>
                      setState(() => _query = value.trim().toLowerCase()),
                  textInputAction: TextInputAction.search,
                  decoration: InputDecoration(
                    hintText: 'Search members',
                    prefixIcon: const Icon(
                      Icons.search_rounded,
                      color: AppColors.textMuted,
                    ),
                    suffixIcon: _query.isEmpty
                        ? null
                        : IconButton(
                            tooltip: 'Clear search',
                            onPressed: () {
                              _searchController.clear();
                              setState(() => _query = '');
                            },
                            icon: const Icon(Icons.close_rounded, size: 19),
                          ),
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    filled: false,
                    contentPadding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                ),
              ),
              if (widget.error != null) ...[
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
                    widget.error!,
                    style: Theme.of(
                      context,
                    ).textTheme.bodySmall?.copyWith(color: AppColors.error),
                  ),
                ),
              ],
            ],
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

    for (final conversation in widget.conversations) {
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

class _ChatInboxEmptyState extends StatelessWidget {
  const _ChatInboxEmptyState({required this.title, required this.message});

  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(28, 10, 28, 100),
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
                  Icons.forum_outlined,
                  color: AppColors.primaryBright,
                  size: 31,
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Text(
                title,
                textAlign: TextAlign.center,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 6),
              Text(
                message,
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

class _TrainerChatInboxList extends StatelessWidget {
  const _TrainerChatInboxList({
    required this.members,
    required this.selectedMemberId,
    required this.conversationForMember,
    required this.onSelectMember,
    this.emptyTitle = 'No members assigned',
    this.emptyMessage = 'Assigned members will appear here as chat boxes.',
  });

  final List<Map<String, dynamic>> members;
  final int? selectedMemberId;
  final Map<String, dynamic> Function(int? memberId) conversationForMember;
  final ValueChanged<int?> onSelectMember;
  final String emptyTitle;
  final String emptyMessage;

  @override
  Widget build(BuildContext context) {
    if (members.isEmpty) {
      return _ChatInboxEmptyState(title: emptyTitle, message: emptyMessage);
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(18, 0, 18, 104),
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
          scope: (assignment['chat_scope_labels'] as List? ?? const [])
              .map((value) => value.toString())
              .join(' + '),
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
    required this.scope,
    required this.time,
    required this.unreadCount,
    required this.isSelected,
    required this.onTap,
    this.avatarUrl,
  });

  final String name;
  final String preview;
  final String scope;
  final String time;
  final int unreadCount;
  final bool isSelected;
  final VoidCallback? onTap;
  final String? avatarUrl;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            color: isSelected ? AppColors.surfaceSoft : AppColors.surface,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(
              color: isSelected
                  ? AppColors.primaryBright.withValues(alpha: 0.24)
                  : AppColors.stroke,
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.035),
                blurRadius: 10,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: AppColors.stroke),
                  image: avatarUrl != null && avatarUrl!.trim().isNotEmpty
                      ? DecorationImage(
                          image: NetworkImage(avatarUrl!),
                          fit: BoxFit.cover,
                        )
                      : null,
                ),
                alignment: Alignment.center,
                child: avatarUrl == null || avatarUrl!.trim().isEmpty
                    ? Text(
                        name.trim().isEmpty ? 'M' : name.trim()[0],
                        style: const TextStyle(
                          color: AppColors.primaryBright,
                          fontSize: 18,
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
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleSmall
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          time,
                          style: Theme.of(context).textTheme.labelSmall
                              ?.copyWith(
                                color: unreadCount > 0
                                    ? AppColors.primaryBright
                                    : AppColors.textMuted,
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Text(
                      scope,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            preview,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: unreadCount > 0
                                      ? AppColors.textSecondary
                                      : AppColors.textMuted,
                                  fontWeight: unreadCount > 0
                                      ? FontWeight.w700
                                      : FontWeight.w500,
                                ),
                          ),
                        ),
                        if (unreadCount > 0) ...[
                          const SizedBox(width: 8),
                          _ChatUnreadBadge(unreadCount: unreadCount),
                        ] else ...[
                          const SizedBox(width: 8),
                          const Icon(
                            Icons.chevron_right_rounded,
                            size: 20,
                            color: AppColors.textMuted,
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ChatUnreadBadge extends StatelessWidget {
  const _ChatUnreadBadge({required this.unreadCount});

  final int unreadCount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: AppColors.primary,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        unreadCount > 99 ? '99+' : '$unreadCount',
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: Colors.white,
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
    required this.busy,
    required this.blockedByMe,
    required this.onBack,
    required this.onRefresh,
    required this.onReport,
    required this.onToggleBlock,
  });

  final Map<String, dynamic> member;
  final bool loading;
  final bool busy;
  final bool blockedByMe;
  final VoidCallback onBack;
  final Future<void> Function()? onRefresh;
  final VoidCallback onReport;
  final VoidCallback onToggleBlock;

  @override
  Widget build(BuildContext context) {
    final name = member['name']?.toString() ?? 'Member';
    final avatarUrl =
        member['avatar']?.toString() ?? member['profile_photo_url']?.toString();

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
            onPressed: onBack,
            icon: const Icon(
              Icons.arrow_back_rounded,
              color: AppColors.textPrimary,
            ),
          ),
          CircleAvatar(
            radius: 24,
            backgroundColor: AppColors.surfaceSoft,
            backgroundImage: avatarUrl != null && avatarUrl.trim().isNotEmpty
                ? NetworkImage(avatarUrl)
                : null,
            child: avatarUrl == null || avatarUrl.trim().isEmpty
                ? Text(
                    name.trim().isEmpty ? 'M' : name.trim()[0],
                    style: const TextStyle(
                      color: AppColors.primaryBright,
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
                  name,
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
                      : 'Member conversation',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
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
          if (onRefresh != null)
            IconButton(
              onPressed: loading ? null : () => onRefresh!(),
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
                    Text(blockedByMe ? 'Unblock member' : 'Block member'),
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

class _TrainerChatThreadScreenState extends State<_TrainerChatThreadScreen>
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
      'recipientId': widget.memberId,
      'active': active,
    });
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
      if (senderId == widget.memberId) {
        unawaited(widget.repository.markChatRead(widget.memberId));
      }
    }
  }

  void _handleSocketRead(dynamic data) {
    if (!mounted) {
      return;
    }

    final receipt = _map(data);
    if (_intValue(receipt['userId'] ?? receipt['user_id']) != widget.memberId) {
      return;
    }

    final messageIds = receipt['messageIds'] ?? receipt['message_ids'];
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
        widget.memberId,
      );
      final safetyResponse = await widget.repository.fetchChatSafety(
        widget.memberId,
      );
      final safety = _map(safetyResponse['data']);
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
          _termsAccepted = safety['terms_accepted'] == true;
          _blockedByMe = safety['blocked_by_me'] == true;
          _blockedMe = safety['blocked_me'] == true;
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
    if (body.isEmpty ||
        _sending ||
        !_termsAccepted ||
        _blockedByMe ||
        _blockedMe) {
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
      await widget.repository.reportChatUser(widget.memberId, reason: reason);
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
        await widget.repository.unblockChatUser(widget.memberId);
      } else {
        await widget.repository.blockChatUser(widget.memberId);
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
    _scrollToLatest();
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
    final memberName = widget.member['name']?.toString() ?? 'Member';

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _ChatThreadHeader(
              member: widget.member,
              loading: _loading,
              busy: _safetyBusy,
              blockedByMe: _blockedByMe,
              onBack: () => Navigator.of(context).maybePop(),
              onRefresh: _load,
              onReport: _reportConversation,
              onToggleBlock: _toggleBlock,
            ),
            _TrainerChatSafetyBar(
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
                  width: double.infinity,
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
                  const _TrainerChatPatternBackground(),
                  if (_loading)
                    const Center(child: CircularProgressIndicator())
                  else if (_messages.isEmpty)
                    _TrainerChatEmptyState(memberName: memberName)
                  else
                    ListView.builder(
                      controller: _scrollController,
                      padding: const EdgeInsets.fromLTRB(16, 18, 16, 18),
                      itemCount: _messages.length + (_hasOlderMessages ? 1 : 0),
                      itemBuilder: (context, index) {
                        if (_hasOlderMessages) {
                          if (index == 0) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: _LoadOlderChatMessagesButton(
                                loading: _loadingOlder,
                                onPressed: _loadOlder,
                              ),
                            );
                          }
                          index -= 1;
                        }

                        final message = _messages[index];
                        final dayLabel = _chatDayLabel(message['created_at']);
                        final previousDay = index == 0
                            ? null
                            : _chatDayLabel(_messages[index - 1]['created_at']);
                        final isOutgoing =
                            _intValue(message['sender_id']) ==
                            widget.currentUserId;
                        final failed = message['failed'] == true;
                        final pending = message['pending'] == true;
                        return Column(
                          children: [
                            if (dayLabel != previousDay)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 12),
                                child: _TrainerChatDatePill(label: dayLabel),
                              ),
                            _ChatBubble(
                              body: message['body']?.toString() ?? '',
                              time: failed
                                  ? 'Failed'
                                  : pending
                                  ? 'Sending'
                                  : _chatTime(message['created_at']),
                              isOutgoing: isOutgoing,
                              pending: pending,
                              failed: failed,
                            ),
                            const SizedBox(height: 8),
                          ],
                        );
                      },
                    ),
                ],
              ),
            ),
            if (_termsAccepted && !_blockedByMe && !_blockedMe)
              _TrainerChatComposer(
                controller: _controller,
                sending: _sending,
                onSend: _send,
                memberName: memberName,
              ),
          ],
        ),
      ),
    );
  }
}

class _TrainerChatSafetyBar extends StatelessWidget {
  const _TrainerChatSafetyBar({
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

class _TrainerChatPatternBackground extends StatelessWidget {
  const _TrainerChatPatternBackground();

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(color: AppColors.background),
      child: Stack(
        children: [
          Positioned(
            right: -42,
            top: 46,
            child: _TrainerChatSoftOrb(
              color: AppColors.primary.withValues(alpha: 0.08),
              size: 150,
            ),
          ),
          Positioned(
            left: -55,
            bottom: 90,
            child: _TrainerChatSoftOrb(
              color: AppColors.primaryBright.withValues(alpha: 0.06),
              size: 170,
            ),
          ),
        ],
      ),
    );
  }
}

class _TrainerChatSoftOrb extends StatelessWidget {
  const _TrainerChatSoftOrb({required this.color, required this.size});

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

class _TrainerChatDatePill extends StatelessWidget {
  const _TrainerChatDatePill({required this.label});

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

class _TrainerChatEmptyState extends StatelessWidget {
  const _TrainerChatEmptyState({required this.memberName});

  final String memberName;

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
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 6),
              Text(
                'Send $memberName a private message about their workout, recovery, nutrition, or progress.',
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

class _TrainerChatComposer extends StatelessWidget {
  const _TrainerChatComposer({
    required this.controller,
    required this.sending,
    required this.onSend,
    required this.memberName,
  });

  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;
  final String memberName;

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
                    hintText: 'Message $memberName',
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

class _ChatBubble extends StatelessWidget {
  const _ChatBubble({
    required this.body,
    required this.time,
    required this.isOutgoing,
    required this.pending,
    required this.failed,
  });

  final String body;
  final String time;
  final bool isOutgoing;
  final bool pending;
  final bool failed;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: isOutgoing ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.76,
        ),
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 10, 12, 8),
          decoration: BoxDecoration(
            color: isOutgoing ? AppColors.primaryBright : AppColors.surface,
            border: isOutgoing ? null : Border.all(color: AppColors.stroke),
            borderRadius: BorderRadius.only(
              topLeft: Radius.circular(isOutgoing ? 18 : 8),
              topRight: Radius.circular(isOutgoing ? 8 : 18),
              bottomLeft: const Radius.circular(18),
              bottomRight: const Radius.circular(18),
            ),
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
                  color: isOutgoing ? Colors.white : AppColors.textPrimary,
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
                          : isOutgoing
                          ? Colors.white.withValues(alpha: 0.82)
                          : AppColors.textMuted,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  if (isOutgoing) ...[
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
                          ? Colors.white.withValues(alpha: 0.72)
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

class _NotificationPage extends StatelessWidget {
  const _NotificationPage({
    required this.notifications,
    required this.trialRequests,
    required this.members,
    required this.onRefresh,
    required this.hasMore,
    required this.loadingMore,
    required this.onLoadMore,
    required this.onMarkRead,
    required this.onMarkAllRead,
    required this.onUpdateTrial,
    required this.onCreateAnnouncement,
    required this.onRespondGymInvitation,
  });

  final List<Map<String, dynamic>> notifications;
  final List<Map<String, dynamic>> trialRequests;
  final List<Map<String, dynamic>> members;
  final Future<void> Function() onRefresh;
  final bool hasMore;
  final bool loadingMore;
  final Future<void> Function() onLoadMore;
  final Future<void> Function(int notificationId) onMarkRead;
  final Future<void> Function() onMarkAllRead;
  final Future<void> Function(int trialRequestId, String status) onUpdateTrial;
  final Future<void> Function(Map<String, dynamic> payload)
  onCreateAnnouncement;
  final Future<void> Function(int invitationId, String decision)
  onRespondGymInvitation;

  @override
  Widget build(BuildContext context) {
    final unreadCount = notifications
        .where((item) => item['read_at'] == null)
        .length;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(25, 15, 25, 104),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Notification',
                  style: const TextStyle(
                    color: Color(0xFF1D1617),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (members.isNotEmpty) ...[
                _SquareIconButton(
                  icon: Icons.campaign_outlined,
                  onTap: () => _openAnnouncementSheet(context),
                ),
                const SizedBox(width: 8),
              ],
              if (unreadCount > 0) ...[
                _SquareIconButton(
                  icon: Icons.done_all_rounded,
                  onTap: () async {
                    try {
                      await onMarkAllRead();
                    } catch (exception) {
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(exception.toString())),
                        );
                      }
                    }
                  },
                ),
                const SizedBox(width: 8),
              ],
              _SquareIconButton(
                icon: Icons.refresh_rounded,
                onTap: () => onRefresh(),
              ),
            ],
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
              message: 'Member updates and coaching alerts will appear here.',
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
                  onRespondGymInvitation: onRespondGymInvitation,
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
                  Divider(color: AppColors.stroke, height: 1),
              ];
            }),
          if (hasMore) ...[
            const SizedBox(height: 20),
            Center(
              child: OutlinedButton.icon(
                onPressed: loadingMore ? null : onLoadMore,
                icon: loadingMore
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more_rounded),
                label: Text(
                  loadingMore ? 'Loading...' : 'Load older notifications',
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _openAnnouncementSheet(BuildContext context) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 12,
          bottom: MediaQuery.viewInsetsOf(sheetContext).bottom + 16,
        ),
        child: _TrainerSendUpdateSheetHost(
          members: members,
          onCreateAnnouncement: onCreateAnnouncement,
        ),
      ),
    );
  }
}

class _TrainerSendUpdateSheetHost extends StatefulWidget {
  const _TrainerSendUpdateSheetHost({
    required this.members,
    required this.onCreateAnnouncement,
  });

  final List<Map<String, dynamic>> members;
  final Future<void> Function(Map<String, dynamic>) onCreateAnnouncement;

  @override
  State<_TrainerSendUpdateSheetHost> createState() =>
      _TrainerSendUpdateSheetHostState();
}

class _TrainerSendUpdateSheetHostState
    extends State<_TrainerSendUpdateSheetHost> {
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _messageController = TextEditingController();
  int? _selectedMemberId;
  bool _saving = false;

  List<Map<String, dynamic>> get _gymMembers => widget.members
      .where(
        (assignment) =>
            assignment['relationship_type'] != 'independent' &&
            assignment['gym_id'] != null,
      )
      .toList();

  @override
  void initState() {
    super.initState();
    _selectedMemberId = (_gymMembers.firstOrNull?['member_id'] as num?)
        ?.toInt();
  }

  @override
  void dispose() {
    _titleController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final selectedAssignment = _gymMembers.firstWhere(
      (item) => (item['member_id'] as num?)?.toInt() == _selectedMemberId,
      orElse: () => const <String, dynamic>{},
    );
    final memberId = (selectedAssignment['member_id'] as num?)?.toInt();
    final title = _titleController.text.trim();
    final message = _messageController.text.trim();
    if (memberId == null || title.isEmpty || message.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Choose a member and enter a title/message.'),
        ),
      );
      return;
    }

    setState(() => _saving = true);
    try {
      await widget.onCreateAnnouncement({
        'gym_id': (selectedAssignment['gym_id'] as num?)?.toInt(),
        'branch_id': (selectedAssignment['branch_id'] as num?)?.toInt(),
        'audience_type': 'selected_members',
        'member_ids': [memberId],
        'title': title,
        'message': message,
      });
      if (!mounted) {
        return;
      }
      Navigator.of(context).pop();
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final selectedAssignment = _gymMembers.firstWhere(
      (item) => (item['member_id'] as num?)?.toInt() == _selectedMemberId,
      orElse: () => const <String, dynamic>{},
    );
    final selectedMember = _map(selectedAssignment['member']);
    final selectedGoal =
        _map(
          selectedAssignment['progress_summary'],
        )['fitness_goal']?.toString() ??
        'Assigned coaching member';

    return _TrainerSendUpdateSheet(
      selectedMemberName:
          selectedMember['name']?.toString() ?? 'Assigned member',
      selectedMemberSubtitle: selectedGoal,
      titleController: _titleController,
      messageController: _messageController,
      selectedMemberId: _selectedMemberId,
      members: _gymMembers,
      saving: _saving,
      onMemberChanged: (value) => setState(() => _selectedMemberId = value),
      onSend: _send,
    );
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
                    color: AppColors.strokeStrong,
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
                      color: AppColors.surfaceSoft,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: AppColors.stroke),
                    ),
                    child: const Icon(
                      Icons.campaign_rounded,
                      color: AppColors.primaryBright,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Send member update',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                        SizedBox(height: 4),
                        Text(
                          'Share a focused coaching reminder.',
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
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.10),
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        selectedMemberName.trim().isEmpty
                            ? 'M'
                            : selectedMemberName.trim()[0].toUpperCase(),
                        style: const TextStyle(
                          color: AppColors.primary,
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
                            style: Theme.of(context).textTheme.titleSmall
                                ?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            selectedMemberSubtitle,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: AppColors.textSecondary,
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
    prefixIcon: Icon(icon, color: AppColors.primary),
    filled: true,
    fillColor: AppColors.surfaceStrong,
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: const BorderSide(color: AppColors.stroke),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: const BorderSide(color: AppColors.stroke),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: const BorderSide(color: AppColors.primary),
    ),
  );
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
                    colors: [Color(0xFF9DCEFF), Color(0xFF92A3FD)],
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
                child: FilledButton.icon(
                  onPressed: onCompleted,
                  icon: const Icon(Icons.check_rounded, size: 17),
                  label: const Text('Done'),
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
    required this.onRespondGymInvitation,
  });

  final Map<String, dynamic> notification;
  final bool isUnread;
  final VoidCallback onMarkRead;
  final Future<void> Function(int invitationId, String decision)
  onRespondGymInvitation;

  @override
  Widget build(BuildContext context) {
    final type = notification['type']?.toString();
    final title = notification['title']?.toString() ?? 'Notification';
    final body = notification['body']?.toString().trim().isNotEmpty == true
        ? notification['body'].toString()
        : _notificationFallbackBody(type);
    final color = _notificationColor(context, type);
    final invitationId = (_map(notification['data'])['invitation_id'] as num?)
        ?.toInt();
    final canRespond =
        type == 'trainer_gym_invitation' &&
        invitationId != null &&
        _map(notification['data'])['status'] == 'pending';

    return InkWell(
      onTap: isUnread ? onMarkRead : null,
      borderRadius: BorderRadius.circular(18),
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
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
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
                    style: TextStyle(
                      color: const Color(0xFF786F72).withValues(alpha: 0.92),
                      fontSize: 11,
                      height: 1.35,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 6),
                  if (canRespond)
                    Row(
                      children: [
                        OutlinedButton(
                          onPressed: () =>
                              onRespondGymInvitation(invitationId, 'reject'),
                          child: const Text('Decline'),
                        ),
                        const SizedBox(width: 8),
                        FilledButton(
                          onPressed: () =>
                              onRespondGymInvitation(invitationId, 'accept'),
                          child: const Text('Accept'),
                        ),
                      ],
                    ),
                  if (canRespond) const SizedBox(height: 8),
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
                icon: const Icon(
                  Icons.done_rounded,
                  color: Color(0xFF786F72),
                  size: 18,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _MembersPageHeader extends StatelessWidget {
  const _MembersPageHeader({required this.totalCount, required this.onInvite});

  final int totalCount;
  final Future<void> Function() onInvite;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Members',
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
                  '$totalCount ${totalCount == 1 ? 'member' : 'members'} in your roster',
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
          const SizedBox(width: 12),
          _SquareIconButton(
            icon: Icons.person_add_alt_1_rounded,
            onTap: onInvite,
          ),
        ],
      ),
    );
  }
}

class _IndependentTrainerStatusCard extends StatelessWidget {
  const _IndependentTrainerStatusCard({
    required this.verificationStatus,
    required this.verificationReason,
    required this.pendingInvitationCount,
    this.onTap,
  });

  final String verificationStatus;
  final String? verificationReason;
  final int pendingInvitationCount;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final verified = verificationStatus == 'verified';
    return PremiumCard(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Icon(
            verified ? Icons.verified_rounded : Icons.hourglass_top_rounded,
            color: verified ? AppColors.success : AppColors.warning,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  verified
                      ? 'Verified for personal coaching'
                      : verificationStatus == 'not_submitted'
                      ? 'Verification not submitted'
                      : 'Verification ${_titleCase(verificationStatus)}',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  verified
                      ? '$pendingInvitationCount pending invitation${pendingInvitationCount == 1 ? '' : 's'} · gym assignments remain separate'
                      : verificationReason?.isNotEmpty == true
                      ? verificationReason!
                      : 'Member invitations unlock only after Atlas approval.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          if (onTap != null)
            const Icon(
              Icons.chevron_right_rounded,
              color: AppColors.textSecondary,
            ),
        ],
      ),
    );
  }
}

class _MemberMetaChip extends StatelessWidget {
  const _MemberMetaChip({
    required this.label,
    required this.icon,
    this.emphasized = false,
  });

  final String label;
  final IconData icon;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    final color = emphasized
        ? AppColors.primaryBright
        : AppColors.textSecondary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: color.withValues(alpha: emphasized ? 0.09 : 0.05),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: color, size: 14),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: emphasized ? AppColors.textPrimary : color,
              fontWeight: FontWeight.w800,
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
    required this.onQueryChanged,
    required this.onDueOnlyChanged,
    required this.onNeedsPlanChanged,
    required this.onClear,
  });

  final TextEditingController controller;
  final String query;
  final bool dueOnly;
  final bool needsPlanOnly;
  final ValueChanged<String> onQueryChanged;
  final ValueChanged<bool> onDueOnlyChanged;
  final ValueChanged<bool> onNeedsPlanChanged;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.stroke.withValues(alpha: 0.9)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 8),
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
              fillColor: AppColors.surfaceStrong,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
                borderSide: const BorderSide(color: AppColors.stroke),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
                borderSide: const BorderSide(color: AppColors.stroke),
              ),
            ),
          ),
          const SizedBox(height: 10),
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
    final membershipSummary = _map(assignment['membership_summary']);
    final memberPlans = plans
        .where((plan) => _planMatchesAssignment(plan, assignment))
        .toList();
    final avatar = member['avatar']?.toString() ?? '';
    final name = member['name']?.toString() ?? 'Member';
    final goal = progressSummary['fitness_goal']?.toString() ?? 'No goal set';
    final completionLabel = memberPlans.isEmpty
        ? 'Workout needed'
        : '${memberPlans.length} workout${memberPlans.length == 1 ? '' : 's'}';
    final independent = assignment['relationship_type'] == 'independent';
    final accessActive = !independent || assignment['access_active'] != false;

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(22),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: AppColors.stroke),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                width: 50,
                height: 50,
                padding: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.primary.withValues(alpha: 0.08),
                  border: Border.all(
                    color: AppColors.primary.withValues(alpha: 0.14),
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
                          color: AppColors.primary,
                        )
                      : null,
                ),
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
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      goal,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        _MemberMetaChip(
                          label: independent
                              ? accessActive
                                    ? 'Independent coaching'
                                    : 'Independent access paused'
                              : 'Gym assigned',
                          icon: independent
                              ? accessActive
                                    ? Icons.verified_user_outlined
                                    : Icons.lock_outline_rounded
                              : Icons.apartment_rounded,
                        ),
                        if (!independent)
                          _MemberMetaChip(
                            label: _assignmentScopeLabel(assignment),
                            icon: Icons.location_on_outlined,
                          ),
                        _MemberMetaChip(
                          label: completionLabel,
                          icon: Icons.fitness_center_rounded,
                          emphasized: memberPlans.isEmpty,
                        ),
                        _MemberMetaChip(
                          label: _titleCase(
                            membershipSummary['status']?.toString() ?? 'active',
                          ),
                          icon: Icons.verified_outlined,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              if (accessActive)
                PopupMenuButton<String>(
                  tooltip: 'Member actions',
                  icon: const Icon(
                    Icons.more_vert_rounded,
                    color: AppColors.textSecondary,
                  ),
                  onSelected: (value) {
                    switch (value) {
                      case 'message':
                        onSendMessage();
                      case 'workout':
                        memberPlans.isEmpty
                            ? onQuickAssign()
                            : onManageWorkouts();
                      case 'note':
                        onQuickNote();
                      case 'follow_up':
                        onAddFollowUp();
                    }
                  },
                  itemBuilder: (context) => [
                    const PopupMenuItem(
                      value: 'message',
                      child: ListTile(
                        dense: true,
                        leading: Icon(Icons.chat_bubble_outline_rounded),
                        title: Text('Message'),
                      ),
                    ),
                    PopupMenuItem(
                      value: 'workout',
                      child: ListTile(
                        dense: true,
                        leading: const Icon(Icons.fitness_center_rounded),
                        title: Text(
                          memberPlans.isEmpty
                              ? 'Assign workout'
                              : 'Manage workouts',
                        ),
                      ),
                    ),
                    if (!independent)
                      const PopupMenuItem(
                        value: 'note',
                        child: ListTile(
                          dense: true,
                          leading: Icon(Icons.edit_note_rounded),
                          title: Text('Add note'),
                        ),
                      ),
                    if (!independent)
                      const PopupMenuItem(
                        value: 'follow_up',
                        child: ListTile(
                          dense: true,
                          leading: Icon(Icons.event_available_rounded),
                          title: Text('Add follow-up'),
                        ),
                      ),
                  ],
                )
              else
                const Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.textSecondary,
                ),
            ],
          ),
        ),
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

String _chatDayLabel(dynamic value) {
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

List<Map<String, dynamic>> _recordsFromResponse(Map<String, dynamic> response) {
  final data = response['data'];
  if (data is List) {
    return data.whereType<Map>().map((item) => _map(item)).toList();
  }
  final envelope = _map(data);
  for (final key in const ['data', 'members', 'relationships', 'invitations']) {
    final records = envelope[key];
    if (records is List) {
      return records.whereType<Map>().map((item) => _map(item)).toList();
    }
  }
  return const [];
}

Map<String, dynamic> _normalizeIndependentAssignment(
  Map<String, dynamic> relationship,
) {
  final member = _map(relationship['member']);
  final memberId = _intValue(
    relationship['member_id'] ?? member['id'] ?? member['user_id'],
  );
  return <String, dynamic>{
    ...relationship,
    'member_id': memberId,
    'member': member,
    'gym_id': null,
    'branch_id': null,
    'relationship_id': _intValue(relationship['id']),
    'relationship_type': 'independent',
    'membership_summary': const <String, dynamic>{'status': 'independent'},
  };
}

String _assignmentKey(Map<String, dynamic> assignment) {
  final relationshipId = _intValue(
    assignment['relationship_id'] ??
        assignment['independent_trainer_member_relationship_id'],
  );
  if (relationshipId != null) {
    return 'independent:$relationshipId';
  }
  return 'gym:${_intValue(assignment['gym_id']) ?? 0}:'
      '${_intValue(assignment['branch_id']) ?? 0}:'
      '${_intValue(assignment['member_id']) ?? 0}';
}

bool _planMatchesAssignment(
  Map<String, dynamic> plan,
  Map<String, dynamic> assignment,
) {
  if (_intValue(plan['member_id']) != _intValue(assignment['member_id'])) {
    return false;
  }
  final relationshipId = _intValue(
    assignment['relationship_id'] ??
        assignment['independent_trainer_member_relationship_id'],
  );
  if (relationshipId != null) {
    return _intValue(plan['independent_trainer_member_relationship_id']) ==
        relationshipId;
  }
  if (plan['independent_trainer_member_relationship_id'] != null) {
    return false;
  }
  return _intValue(plan['gym_id']) == _intValue(assignment['gym_id']) &&
      _intValue(plan['branch_id']) == _intValue(assignment['branch_id']);
}

String _assignmentScopeLabel(Map<String, dynamic> assignment) {
  if (assignment['relationship_type'] == 'independent') {
    return 'Personal coaching';
  }
  final gym = _map(assignment['gym']);
  final branch = _map(assignment['branch']);
  final gymLabel = gym['name']?.toString().trim().isNotEmpty == true
      ? gym['name'].toString()
      : 'Gym ${_intValue(assignment['gym_id']) ?? '--'}';
  final branchLabel = branch['name']?.toString().trim().isNotEmpty == true
      ? branch['name'].toString()
      : 'Branch ${_intValue(assignment['branch_id']) ?? '--'}';
  return '$gymLabel · $branchLabel';
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
    case 'independent_trainer_verification':
      return const Color(0xFF8B5CF6);
    case 'independent_coaching_response':
      return const Color(0xFF34D399);
    case 'independent_coaching_revoked':
      return const Color(0xFFFB7185);
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
    case 'independent_trainer_verification':
      return Icons.verified_user_rounded;
    case 'independent_coaching_response':
      return Icons.handshake_rounded;
    case 'independent_coaching_revoked':
      return Icons.link_off_rounded;
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
    case 'independent_trainer_verification':
      return 'Verification';
    case 'independent_coaching_response':
      return 'Coaching response';
    case 'independent_coaching_revoked':
      return 'Coaching ended';
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
    case 'independent_trainer_verification':
      return 'Your personal coaching verification status has changed.';
    case 'independent_coaching_response':
      return 'A member responded to your independent coaching invitation.';
    case 'independent_coaching_revoked':
      return 'An independent coaching connection is no longer active.';
    default:
      return 'A new trainer update is available.';
  }
}

extension<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
