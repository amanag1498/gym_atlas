import 'package:flutter/material.dart';

import '../../core/models.dart';
import '../../core/secure_storage_service.dart';
import '../../core/pagination.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import 'member_repository.dart';

class MemberTrialRequestsScreen extends StatefulWidget {
  const MemberTrialRequestsScreen({
    super.key,
    required this.repository,
    required this.currentUser,
    this.initialGym,
    this.initialStatusTab = false,
  });

  final MemberRepository repository;
  final MemberUser currentUser;
  final Map<String, dynamic>? initialGym;
  final bool initialStatusTab;

  @override
  State<MemberTrialRequestsScreen> createState() =>
      _MemberTrialRequestsScreenState();
}

class _MemberTrialRequestsScreenState extends State<MemberTrialRequestsScreen>
    with SingleTickerProviderStateMixin {
  final SecureStorageService _storage = const SecureStorageService();
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _preferredDateController =
      TextEditingController();
  final TextEditingController _preferredTimeController =
      TextEditingController();
  final TextEditingController _notesController = TextEditingController();

  late final TabController _tabController;
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  String? _successMessage;
  List<Map<String, dynamic>> _publicGyms = const [];
  List<Map<String, dynamic>> _trialRequests = const [];
  List<Map<String, dynamic>> _availableBranches = const [];
  int? _selectedGymId;
  int? _selectedBranchId;

  @override
  void initState() {
    super.initState();
    _nameController.text = widget.currentUser.name;
    _emailController.text = widget.currentUser.email;
    _preferredDateController.text = DateTime.now()
        .add(const Duration(days: 1))
        .toIso8601String()
        .split('T')
        .first;
    _preferredTimeController.text = '18:00';
    _tabController = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _preferredDateController.dispose();
    _preferredTimeController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final storedTrials = await _storage.readTrialRequests(
        widget.currentUser.id,
      );
      final results = await Future.wait<Map<String, dynamic>>([
        _fetchAllPublicGyms(),
        widget.repository.fetchContext(),
        _fetchAllTrialRequests(),
      ]);

      final gyms = (results[0]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      final contextData = Map<String, dynamic>.from(
        results[1]['data'] as Map? ?? const {},
      );
      final userState =
          contextData['user_state']?.toString() ?? 'independent_user';
      final serverTrials = apiPageItems(results[2]);
      final normalizedTrials = _reconcileTrialRequests(
        mergeApiPageItems(storedTrials, serverTrials),
        userState,
        serverTrialIds: serverTrials
            .map((item) => (item['id'] as num?)?.toInt())
            .whereType<int>()
            .toSet(),
      );

      _publicGyms = gyms;
      _trialRequests = normalizedTrials;

      if (widget.initialGym != null) {
        final initialGym = Map<String, dynamic>.from(widget.initialGym!);
        _selectedGymId = (initialGym['id'] as num?)?.toInt();
        await _hydrateGymBranches(initialGym);
      } else if (_selectedGymId != null) {
        final selected = _publicGyms.firstWhere(
          (gym) => (gym['id'] as num?)?.toInt() == _selectedGymId,
          orElse: () => const <String, dynamic>{},
        );
        if (selected.isNotEmpty) {
          await _hydrateGymBranches(selected);
        }
      }

      await _storage.saveTrialRequests(widget.currentUser.id, normalizedTrials);

      if (widget.initialStatusTab && _trialRequests.isNotEmpty) {
        _tabController.index = 1;
      }
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<Map<String, dynamic>> _fetchAllPublicGyms() async {
    var response = await widget.repository.fetchPublicGyms(
      filters: const {'page': 1, 'per_page': 50},
    );
    var gyms = apiPageItems(response);
    var pagination = ApiPagination.fromResponse(response);

    while (pagination.hasMore) {
      response = await widget.repository.fetchPublicGyms(
        filters: {'page': pagination.nextPage, 'per_page': 50},
      );
      gyms = mergeApiPageItems(gyms, apiPageItems(response));
      pagination = ApiPagination.fromResponse(response);
    }

    return {'data': gyms};
  }

  Future<Map<String, dynamic>> _fetchAllTrialRequests() async {
    var response = await widget.repository.fetchTrialRequests();
    var trials = apiPageItems(response);
    var pagination = ApiPagination.fromResponse(response);

    while (pagination.hasMore) {
      response = await widget.repository.fetchTrialRequests(
        page: pagination.nextPage,
      );
      trials = mergeApiPageItems(trials, apiPageItems(response));
      pagination = ApiPagination.fromResponse(response);
    }

    return {'data': trials};
  }

  Future<void> _hydrateGymBranches(Map<String, dynamic> gym) async {
    final detail = await _fetchGymDetail(gym);
    final branches = (detail['branches'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();

    _availableBranches = branches;
    if (branches.length == 1) {
      _selectedBranchId = (branches.first['id'] as num?)?.toInt();
    } else if (_selectedBranchId != null &&
        !branches.any(
          (item) => (item['id'] as num?)?.toInt() == _selectedBranchId,
        )) {
      _selectedBranchId = null;
    }
  }

  Future<Map<String, dynamic>> _fetchGymDetail(Map<String, dynamic> gym) async {
    final branches = gym['branches'];
    if (branches is List && gym.containsKey('timings')) {
      return Map<String, dynamic>.from(gym);
    }

    final slug = gym['slug']?.toString();
    if (slug == null || slug.isEmpty) {
      throw Exception('Gym profile is unavailable.');
    }

    final response = await widget.repository.fetchPublicGymDetail(slug);
    return Map<String, dynamic>.from(response['data'] as Map? ?? const {});
  }

  List<Map<String, dynamic>> _reconcileTrialRequests(
    List<Map<String, dynamic>> trialRequests,
    String userState, {
    Set<int> serverTrialIds = const <int>{},
  }) {
    final normalized =
        trialRequests.map((item) => Map<String, dynamic>.from(item)).toList()
          ..sort((left, right) {
            final rightDate =
                DateTime.tryParse(right['created_at']?.toString() ?? '') ??
                DateTime.fromMillisecondsSinceEpoch(0);
            final leftDate =
                DateTime.tryParse(left['created_at']?.toString() ?? '') ??
                DateTime.fromMillisecondsSinceEpoch(0);
            return rightDate.compareTo(leftDate);
          });

    if ((userState == 'gym_member' || userState == 'gym_member_with_trainer') &&
        normalized.isNotEmpty) {
      final mutable = normalized.firstWhere(
        (item) =>
            const [
              'pending',
              'accepted',
              'completed',
            ].contains(item['status']?.toString()) &&
            !serverTrialIds.contains((item['id'] as num?)?.toInt()),
        orElse: () => const <String, dynamic>{},
      );

      if (mutable.isNotEmpty) {
        mutable['status'] = 'converted';
      }
    }

    return normalized;
  }

  Future<void> _submitTrial() async {
    final selectedGymId = _selectedGymId;
    if (selectedGymId == null) {
      _showMessage('Select a gym first.');
      return;
    }
    if (_nameController.text.trim().isEmpty) {
      _showMessage('Name is required.');
      return;
    }
    if (_phoneController.text.trim().isEmpty) {
      _showMessage('Phone is required.');
      return;
    }

    setState(() => _submitting = true);
    try {
      final response = await widget.repository.submitTrialRequest({
        'gym_id': selectedGymId,
        if (_selectedBranchId != null) 'branch_id': _selectedBranchId,
        'name': _nameController.text.trim(),
        'phone': _phoneController.text.trim(),
        'email': _nullable(_emailController.text),
        'preferred_date': _preferredDateController.text.trim(),
        'preferred_time': _preferredTimeController.text.trim(),
        'notes': _nullable(_notesController.text),
      });

      final trial = Map<String, dynamic>.from(
        response['data'] as Map? ?? const {},
      );
      final updatedRequests = [trial, ..._trialRequests];
      await _storage.saveTrialRequests(widget.currentUser.id, updatedRequests);

      if (!mounted) {
        return;
      }

      setState(() {
        _trialRequests = updatedRequests;
        _successMessage = 'Trial request submitted successfully.';
        _notesController.clear();
      });
      _tabController.index = 1;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Trial request submitted successfully.')),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      _showMessage(exception.toString());
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message.replaceFirst('Exception: ', ''))),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Trial Requests',
      body: SafeArea(
        bottom: false,
        child: _loading
            ? const _TrialSkeleton()
            : _error != null
            ? ErrorStateView(message: _error!, onRetry: _load)
            : Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.lg,
                      AppSpacing.sm,
                      AppSpacing.lg,
                      0,
                    ),
                    child: _TrialTopBar(
                      title: 'Trial Requests',
                      subtitle: 'Book a trial and track the outcome.',
                      onRefresh: _load,
                    ),
                  ),
                  if (_successMessage != null)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(
                        AppSpacing.lg,
                        AppSpacing.md,
                        AppSpacing.lg,
                        0,
                      ),
                      child: _TrialSuccessBanner(message: _successMessage!),
                    ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.lg,
                      AppSpacing.md,
                      AppSpacing.lg,
                      0,
                    ),
                    child: _TrialTabSlider(controller: _tabController),
                  ),
                  Expanded(
                    child: TabBarView(
                      controller: _tabController,
                      children: [
                        _TrialRequestFormTab(
                          publicGyms: _publicGyms,
                          branches: _availableBranches,
                          selectedGymId: _selectedGymId,
                          selectedBranchId: _selectedBranchId,
                          nameController: _nameController,
                          phoneController: _phoneController,
                          emailController: _emailController,
                          preferredDateController: _preferredDateController,
                          preferredTimeController: _preferredTimeController,
                          notesController: _notesController,
                          submitting: _submitting,
                          onGymChanged: (gymId) async {
                            if (gymId == null) {
                              return;
                            }
                            setState(() {
                              _selectedGymId = gymId;
                              _selectedBranchId = null;
                              _availableBranches = const [];
                            });
                            final gym = _publicGyms.firstWhere(
                              (item) => (item['id'] as num?)?.toInt() == gymId,
                              orElse: () => const <String, dynamic>{},
                            );
                            if (gym.isNotEmpty) {
                              await _hydrateGymBranches(gym);
                              if (mounted) {
                                setState(() {});
                              }
                            }
                          },
                          onBranchChanged: (branchId) =>
                              setState(() => _selectedBranchId = branchId),
                          onSubmit: _submitTrial,
                        ),
                        _TrialRequestsListTab(
                          trialRequests: _trialRequests,
                          onOpenDetail: (trial) {
                            Navigator.of(context).push<void>(
                              MaterialPageRoute<void>(
                                builder: (_) =>
                                    TrialStatusDetailScreen(trial: trial),
                              ),
                            );
                          },
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

class _TrialTopBar extends StatelessWidget {
  const _TrialTopBar({
    required this.title,
    required this.subtitle,
    required this.onRefresh,
  });

  final String title;
  final String subtitle;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        InkWell(
          onTap: () => Navigator.of(context).maybePop(),
          borderRadius: BorderRadius.circular(16),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.stroke),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 10,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: const Icon(
              Icons.arrow_back_rounded,
              color: AppColors.textPrimary,
              size: 20,
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        MemberHeaderActionButton(icon: Icons.refresh_rounded, onTap: onRefresh),
      ],
    );
  }
}

class _TrialSuccessBanner extends StatelessWidget {
  const _TrialSuccessBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.16)),
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.check_rounded,
              size: 18,
              color: AppColors.primaryBright,
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TrialTabSlider extends StatefulWidget {
  const _TrialTabSlider({required this.controller});

  final TabController controller;

  @override
  State<_TrialTabSlider> createState() => _TrialTabSliderState();
}

class _TrialTabSliderState extends State<_TrialTabSlider> {
  static const _tabs = [
    (label: 'Book Trial', icon: Icons.edit_calendar_rounded),
    (label: 'Status', icon: Icons.history_rounded),
  ];

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_handleTick);
  }

  @override
  void didUpdateWidget(covariant _TrialTabSlider oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller == widget.controller) {
      return;
    }
    oldWidget.controller.removeListener(_handleTick);
    widget.controller.addListener(_handleTick);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_handleTick);
    super.dispose();
  }

  void _handleTick() {
    if (mounted) {
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(6),
      child: Row(
        children: List.generate(_tabs.length, (index) {
          final tab = _tabs[index];
          final selected = widget.controller.index == index;

          return Expanded(
            child: GestureDetector(
              onTap: () => widget.controller.animateTo(index),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                curve: Curves.easeOutCubic,
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.sm,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: selected
                      ? AppColors.primary.withValues(alpha: 0.10)
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color: selected
                        ? AppColors.primary.withValues(alpha: 0.18)
                        : Colors.transparent,
                  ),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      tab.icon,
                      size: 18,
                      color: selected
                          ? AppColors.primaryBright
                          : AppColors.textMuted,
                    ),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        tab.label,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: selected
                              ? AppColors.textPrimary
                              : AppColors.textSecondary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}

class _TrialRequestFormTab extends StatelessWidget {
  const _TrialRequestFormTab({
    required this.publicGyms,
    required this.branches,
    required this.selectedGymId,
    required this.selectedBranchId,
    required this.nameController,
    required this.phoneController,
    required this.emailController,
    required this.preferredDateController,
    required this.preferredTimeController,
    required this.notesController,
    required this.submitting,
    required this.onGymChanged,
    required this.onBranchChanged,
    required this.onSubmit,
  });

  final List<Map<String, dynamic>> publicGyms;
  final List<Map<String, dynamic>> branches;
  final int? selectedGymId;
  final int? selectedBranchId;
  final TextEditingController nameController;
  final TextEditingController phoneController;
  final TextEditingController emailController;
  final TextEditingController preferredDateController;
  final TextEditingController preferredTimeController;
  final TextEditingController notesController;
  final bool submitting;
  final ValueChanged<int?> onGymChanged;
  final ValueChanged<int?> onBranchChanged;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.md,
        AppSpacing.lg,
        AppSpacing.xl,
      ),
      children: [
        PremiumCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(AppSpacing.md),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Book a trial',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Choose the gym, pick your slot, and keep the request easy to follow.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              DropdownButtonFormField<int>(
                initialValue: selectedGymId,
                decoration: const InputDecoration(
                  labelText: 'Gym',
                  prefixIcon: Icon(Icons.storefront_rounded),
                ),
                items: publicGyms
                    .map(
                      (gym) => DropdownMenuItem<int>(
                        value: (gym['id'] as num?)?.toInt(),
                        child: Text(gym['name']?.toString() ?? 'Gym'),
                      ),
                    )
                    .toList(),
                onChanged: onGymChanged,
              ),
              const SizedBox(height: AppSpacing.md),
              DropdownButtonFormField<int>(
                initialValue: selectedBranchId,
                decoration: const InputDecoration(
                  labelText: 'Branch (optional)',
                  prefixIcon: Icon(Icons.account_tree_rounded),
                ),
                items: [
                  const DropdownMenuItem<int>(
                    value: null,
                    child: Text('Auto / no preference'),
                  ),
                  ...branches.map(
                    (branch) => DropdownMenuItem<int>(
                      value: (branch['id'] as num?)?.toInt(),
                      child: Text(branch['name']?.toString() ?? 'Branch'),
                    ),
                  ),
                ],
                onChanged: onBranchChanged,
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: nameController,
                decoration: const InputDecoration(
                  labelText: 'Name',
                  prefixIcon: Icon(Icons.person_outline_rounded),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: phoneController,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Phone',
                  prefixIcon: Icon(Icons.phone_outlined),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: emailController,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(
                  labelText: 'Email (optional)',
                  prefixIcon: Icon(Icons.alternate_email_rounded),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: preferredDateController,
                      decoration: const InputDecoration(
                        labelText: 'Preferred date',
                        prefixIcon: Icon(Icons.calendar_month_rounded),
                      ),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: TextField(
                      controller: preferredTimeController,
                      decoration: const InputDecoration(
                        labelText: 'Preferred time',
                        prefixIcon: Icon(Icons.schedule_rounded),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: notesController,
                minLines: 2,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'Notes',
                  prefixIcon: Icon(Icons.notes_rounded),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              GradientButton(
                label: 'Submit Trial',
                icon: Icons.flash_on_rounded,
                loading: submitting,
                expanded: true,
                onPressed: submitting ? null : onSubmit,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _TrialRequestsListTab extends StatelessWidget {
  const _TrialRequestsListTab({
    required this.trialRequests,
    required this.onOpenDetail,
  });

  final List<Map<String, dynamic>> trialRequests;
  final ValueChanged<Map<String, dynamic>> onOpenDetail;

  @override
  Widget build(BuildContext context) {
    if (trialRequests.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(AppSpacing.lg),
        child: EmptyStateView(
          title: 'No trial requests yet',
          message:
              'Once you submit a trial request, its status timeline will show up here.',
          icon: Icons.flag_outlined,
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.md,
        AppSpacing.lg,
        AppSpacing.xl,
      ),
      children: trialRequests
          .map(
            (trial) => Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.md),
              child: PremiumCard(
                padding: const EdgeInsets.all(AppSpacing.md),
                onTap: () => onOpenDetail(trial),
                child: Row(
                  children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(18),
                        color: AppColors.primary.withValues(alpha: 0.12),
                      ),
                      child: const Icon(
                        Icons.flag_rounded,
                        color: AppColors.primaryBright,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            trial['gym'] is Map
                                ? (trial['gym']['name']?.toString() ?? 'Gym')
                                : 'Gym',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            [
                                  if (trial['branch'] is Map)
                                    trial['branch']['name']?.toString() ?? '',
                                  _formatTrialDate(trial['preferred_date']),
                                ]
                                .where((item) => item.trim().isNotEmpty)
                                .join(' • '),
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: AppColors.textSecondary,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        StatusBadge(
                          label: _titleCase(
                            trial['status']?.toString() ?? 'pending',
                          ),
                          color: AppColors.statusColor(
                            trial['status']?.toString() ?? 'pending',
                          ),
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        const Icon(
                          Icons.chevron_right_rounded,
                          color: AppColors.textMuted,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          )
          .toList(),
    );
  }
}

class TrialStatusDetailScreen extends StatelessWidget {
  const TrialStatusDetailScreen({super.key, required this.trial});

  final Map<String, dynamic> trial;

  @override
  Widget build(BuildContext context) {
    final status = trial['status']?.toString() ?? 'pending';
    final branch = trial['branch'] is Map
        ? Map<String, dynamic>.from(trial['branch'] as Map)
        : const <String, dynamic>{};
    final trainer = trial['assigned_trainer'] is Map
        ? Map<String, dynamic>.from(trial['assigned_trainer'] as Map)
        : const <String, dynamic>{};

    return AppGradientScaffold(
      title: 'Trial Status Detail',
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(
                AppSpacing.lg,
                AppSpacing.sm,
                AppSpacing.lg,
                0,
              ),
              child: _TrialDetailTopBar(
                title: 'Trial Status',
                subtitle: 'Overview, timeline, and next update.',
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.lg,
                  AppSpacing.md,
                  AppSpacing.lg,
                  AppSpacing.xl,
                ),
                children: [
                  PremiumCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          trial['gym'] is Map
                              ? (trial['gym']['name']?.toString() ?? 'Gym')
                              : 'Gym',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Wrap(
                          spacing: AppSpacing.sm,
                          runSpacing: AppSpacing.sm,
                          children: [
                            StatusBadge(
                              label: _titleCase(status),
                              color: AppColors.statusColor(status),
                              icon: Icons.flag_rounded,
                            ),
                            if (branch.isNotEmpty)
                              StatusBadge(
                                label: branch['name']?.toString() ?? 'Branch',
                                color: AppColors.primaryBright,
                                icon: Icons.account_tree_rounded,
                              ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Row(
                          children: [
                            Expanded(
                              child: _TrialDetailStatTile(
                                label: 'Preferred date',
                                value: _formatTrialDate(
                                  trial['preferred_date'],
                                ),
                                icon: Icons.calendar_month_rounded,
                              ),
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Expanded(
                              child: _TrialDetailStatTile(
                                label: 'Preferred time',
                                value:
                                    _nullableText(
                                      trial['preferred_time'],
                                    ).isEmpty
                                    ? 'Pending'
                                    : _nullableText(trial['preferred_time']),
                                icon: Icons.schedule_rounded,
                              ),
                            ),
                          ],
                        ),
                        if ((trial['notes']?.toString() ?? '')
                            .trim()
                            .isNotEmpty) ...[
                          const SizedBox(height: AppSpacing.md),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(AppSpacing.md),
                            decoration: BoxDecoration(
                              color: AppColors.surfaceSoft,
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(color: AppColors.stroke),
                            ),
                            child: Text(
                              trial['notes'].toString(),
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: AppColors.textSecondary,
                                    fontWeight: FontWeight.w600,
                                    height: 1.45,
                                  ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  PremiumCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Status timeline',
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        _TimelineStep(
                          title: 'Trial submitted',
                          subtitle:
                              'Your request was recorded and shared with the gym team.',
                          active: true,
                        ),
                        _TimelineStep(
                          title: 'Gym review',
                          subtitle:
                              'The gym reviews your preferred slot and branch request.',
                          active: const [
                            'accepted',
                            'completed',
                            'converted',
                          ].contains(status),
                        ),
                        _TimelineStep(
                          title: 'Trial completed',
                          subtitle:
                              'Your trial is marked completed once the session is done.',
                          active: const [
                            'completed',
                            'converted',
                          ].contains(status),
                        ),
                        _TimelineStep(
                          title: 'Converted to member',
                          subtitle:
                              'Membership unlocks automatically after conversion.',
                          active: status == 'converted',
                          isLast: true,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  PremiumCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Assignment',
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        if (trainer.isNotEmpty)
                          _TrialAssignmentRow(
                            icon: Icons.person_pin_circle_rounded,
                            label: 'Assigned trainer',
                            value:
                                trainer['name']?.toString() ??
                                'Trainer assigned',
                          )
                        else
                          const _TrialAssignmentRow(
                            icon: Icons.hourglass_bottom_rounded,
                            label: 'Trainer',
                            value: 'Assignment pending',
                          ),
                        const SizedBox(height: AppSpacing.sm),
                        const _TrialAssignmentRow(
                          icon: Icons.notifications_active_rounded,
                          label: 'Reminder',
                          value:
                              'Check back before your preferred date for updates.',
                        ),
                      ],
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

class _TrialDetailTopBar extends StatelessWidget {
  const _TrialDetailTopBar({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        InkWell(
          onTap: () => Navigator.of(context).maybePop(),
          borderRadius: BorderRadius.circular(16),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.stroke),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 10,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: const Icon(
              Icons.arrow_back_rounded,
              color: AppColors.textPrimary,
              size: 20,
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _TrialDetailStatTile extends StatelessWidget {
  const _TrialDetailStatTile({
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
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.primaryBright),
          const SizedBox(height: AppSpacing.sm),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: AppColors.textMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _TrialAssignmentRow extends StatelessWidget {
  const _TrialAssignmentRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: AppColors.primaryBright, size: 18),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: AppColors.textMuted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textPrimary,
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

class _TimelineStep extends StatelessWidget {
  const _TimelineStep({
    required this.title,
    required this.subtitle,
    required this.active,
    this.isLast = false,
  });

  final String title;
  final String subtitle;
  final bool active;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final color = active ? AppColors.success : AppColors.textMuted;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            Container(
              width: 16,
              height: 16,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: color.withValues(alpha: active ? 1 : 0.28),
                border: Border.all(color: color.withValues(alpha: 0.4)),
              ),
            ),
            if (!isLast)
              Container(
                width: 2,
                height: 48,
                color: color.withValues(alpha: 0.22),
              ),
          ],
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: AppSpacing.xs),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _TrialSkeleton extends StatelessWidget {
  const _TrialSkeleton();

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: const [
          SkeletonLoader(lines: 4),
          SizedBox(height: AppSpacing.lg),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonHistoryList(items: 3),
        ],
      ),
    );
  }
}

String? _nullable(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

String _titleCase(String value) {
  if (value.isEmpty) {
    return 'Unknown';
  }
  return value
      .split('_')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

String _formatTrialDate(Object? value) {
  final text = value?.toString() ?? '';
  final date = DateTime.tryParse(text);
  if (date == null) {
    return text.isEmpty ? 'Date pending' : text;
  }
  return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _nullableText(Object? value) {
  final text = value?.toString() ?? '';
  return text.trim().isEmpty ? '' : text;
}
