import 'package:flutter/material.dart';

import '../../core/models.dart';
import '../../core/secure_storage_service.dart';
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
  final TextEditingController _preferredDateController = TextEditingController();
  final TextEditingController _preferredTimeController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();

  late final TabController _tabController;
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  String? _successMessage;
  String _userState = 'independent_user';
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
    _tabController = TabController(
      length: 2,
      vsync: this,
    );
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
      final storedTrials = await _storage.readTrialRequests(widget.currentUser.id);
      final results = await Future.wait<Map<String, dynamic>>([
        widget.repository.fetchPublicGyms(),
        widget.repository.fetchContext(),
      ]);

      final gyms = (results[0]['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      final contextData = Map<String, dynamic>.from(
        results[1]['data'] as Map? ?? const {},
      );
      final userState = contextData['user_state']?.toString() ?? 'independent_user';
      final normalizedTrials = _reconcileTrialRequests(
        storedTrials,
        userState,
      );

      _publicGyms = gyms;
      _userState = userState;
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

  Future<void> _hydrateGymBranches(Map<String, dynamic> gym) async {
    final detail = await _fetchGymDetail(gym);
    final branches = (detail['branches'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();

    _availableBranches = branches;
    if (branches.length == 1) {
      _selectedBranchId = (branches.first['id'] as num?)?.toInt();
    } else if (_selectedBranchId != null &&
        !branches.any((item) => (item['id'] as num?)?.toInt() == _selectedBranchId)) {
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
    String userState,
  ) {
    final normalized = trialRequests
        .map((item) => Map<String, dynamic>.from(item))
        .toList()
      ..sort((left, right) {
        final rightDate = DateTime.tryParse(
              right['created_at']?.toString() ?? '',
            ) ??
            DateTime.fromMillisecondsSinceEpoch(0);
        final leftDate = DateTime.tryParse(
              left['created_at']?.toString() ?? '',
            ) ??
            DateTime.fromMillisecondsSinceEpoch(0);
        return rightDate.compareTo(leftDate);
      });

    if ((userState == 'gym_member' || userState == 'gym_member_with_trainer') &&
        normalized.isNotEmpty) {
      final mutable = normalized.firstWhere(
        (item) => const ['pending', 'accepted', 'completed'].contains(
          item['status']?.toString(),
        ),
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
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message.replaceFirst('Exception: ', ''))));
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Trial Requests',
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
      body: _loading
          ? const _TrialSkeleton()
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
                  child: PremiumCard(
                    glowColor: AppColors.accentPurple,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        AnimatedSwitcher(
                          duration: const Duration(milliseconds: 260),
                          child: _successMessage == null
                              ? const SizedBox.shrink()
                              : Container(
                                  key: ValueKey<String>(_successMessage!),
                                  width: double.infinity,
                                  margin: const EdgeInsets.only(
                                    bottom: AppSpacing.md,
                                  ),
                                  padding: const EdgeInsets.all(AppSpacing.md),
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(
                                      AppSpacing.radiusMd,
                                    ),
                                    gradient: LinearGradient(
                                      colors: [
                                        AppColors.success.withValues(alpha: 0.18),
                                        AppColors.accentNeon.withValues(alpha: 0.08),
                                      ],
                                    ),
                                    border: Border.all(
                                      color: AppColors.success.withValues(alpha: 0.28),
                                    ),
                                  ),
                                  child: Row(
                                    children: [
                                      const Icon(
                                        Icons.check_circle_rounded,
                                        color: AppColors.success,
                                      ),
                                      const SizedBox(width: AppSpacing.sm),
                                      Expanded(
                                        child: Text(
                                          _successMessage!,
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodyMedium,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                        ),
                        Text(
                          'Book a trial and track the outcome',
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        Text(
                          'Send a trial request to a gym, then keep the status visible while you continue workouts and progress tracking.',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Wrap(
                          spacing: AppSpacing.sm,
                          runSpacing: AppSpacing.sm,
                          children: [
                            StatusBadge(
                              label: _titleCase(_userState),
                              color: AppColors.statusColor(_userState),
                            ),
                            if (_trialRequests.isNotEmpty)
                              StatusBadge(
                                label: '${_trialRequests.length} requests',
                                color: AppColors.primaryBright,
                                icon: Icons.history_rounded,
                              ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.md),
                        TabBar(
                          controller: _tabController,
                          isScrollable: true,
                          tabs: const [
                            Tab(text: 'Trial Form'),
                            Tab(text: 'My Trial Requests'),
                          ],
                        ),
                      ],
                    ),
                  ),
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
                              builder: (_) => TrialStatusDetailScreen(trial: trial),
                            ),
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ],
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
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        PremiumCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Trial Request Form',
                style: Theme.of(context).textTheme.titleLarge,
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
                label: 'Submit Trial Request',
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
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: trialRequests
          .map(
            (trial) => Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.md),
              child: PremiumCard(
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
                            ].where((item) => item.trim().isNotEmpty).join(' • '),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    StatusBadge(
                      label: _titleCase(trial['status']?.toString() ?? 'pending'),
                      color: AppColors.statusColor(
                        trial['status']?.toString() ?? 'pending',
                      ),
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
  const TrialStatusDetailScreen({
    super.key,
    required this.trial,
  });

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
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          PremiumCard(
            glowColor: AppColors.accentPurple,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  trial['gym'] is Map
                      ? (trial['gym']['name']?.toString() ?? 'Gym')
                      : 'Gym',
                  style: Theme.of(context).textTheme.headlineSmall,
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
                Text(
                  'Preferred visit: ${_formatTrialDate(trial['preferred_date'])} ${_nullableText(trial['preferred_time'])}',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                if ((trial['notes']?.toString() ?? '').trim().isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    trial['notes'].toString(),
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Status timeline',
                  style: Theme.of(context).textTheme.titleLarge,
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
                  active: const ['accepted', 'completed', 'converted'].contains(
                    status,
                  ),
                ),
                _TimelineStep(
                  title: 'Trial completed',
                  subtitle:
                      'Your trial is marked completed once you visit and finish the session.',
                  active: const ['completed', 'converted'].contains(status),
                ),
                _TimelineStep(
                  title: 'Converted to member',
                  subtitle:
                      'If the gym converts your request into membership, your member features unlock automatically.',
                  active: status == 'converted',
                  isLast: true,
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          PremiumCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Assignment & reminder',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: AppSpacing.md),
                if (trainer.isNotEmpty)
                  StatusBadge(
                    label: trainer['name']?.toString() ?? 'Trainer assigned',
                    color: AppColors.accentNeon,
                    icon: Icons.person_pin_circle_rounded,
                  )
                else
                  const StatusBadge(
                    label: 'Trainer assignment pending',
                    color: AppColors.warning,
                    icon: Icons.hourglass_bottom_rounded,
                  ),
                const SizedBox(height: AppSpacing.md),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(AppSpacing.md),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
                    color: Colors.white.withValues(alpha: 0.04),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(
                        Icons.notifications_active_rounded,
                        color: AppColors.accentAmber,
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          'Trial reminder placeholder: keep this request visible and check back before your preferred date for updates from the gym.',
                          style: Theme.of(context).textTheme.bodyMedium,
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
