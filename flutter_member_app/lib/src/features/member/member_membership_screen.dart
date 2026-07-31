import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import 'member_repository.dart';

class MemberMembershipScreen extends StatefulWidget {
  const MemberMembershipScreen({
    super.key,
    required this.repository,
    required this.onDiscoverGyms,
    this.onOpenAttendance,
  });

  final MemberRepository repository;
  final VoidCallback onDiscoverGyms;
  final VoidCallback? onOpenAttendance;

  @override
  State<MemberMembershipScreen> createState() => _MemberMembershipScreenState();
}

class _MemberMembershipScreenState extends State<MemberMembershipScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _membership;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await widget.repository.fetchMembership();
      final data = response['data'];
      _membership = data is Map ? Map<String, dynamic>.from(data) : null;
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  void _handleDiscoverGyms() {
    widget.onDiscoverGyms();
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final membership = _membership;
    final currentGym = membership?['current_gym'] is Map
        ? Map<String, dynamic>.from(membership!['current_gym'] as Map)
        : const <String, dynamic>{};
    final branch = membership?['branch'] is Map
        ? Map<String, dynamic>.from(membership!['branch'] as Map)
        : const <String, dynamic>{};
    final plan = membership?['membership_plan'] is Map
        ? Map<String, dynamic>.from(membership!['membership_plan'] as Map)
        : const <String, dynamic>{};
    final trainer = membership?['assigned_trainer'] is Map
        ? Map<String, dynamic>.from(membership!['assigned_trainer'] as Map)
        : const <String, dynamic>{};
    final customFee = membership?['custom_fee_display'] is Map
        ? Map<String, dynamic>.from(membership!['custom_fee_display'] as Map)
        : const <String, dynamic>{};
    final hasCustomFee = customFee['custom_fee_enabled'] == true;

    final gymName = _stringValue(currentGym['name'], fallback: 'Current gym');
    final branchName = _stringValue(branch['name'], fallback: 'Branch pending');
    final isPaused = membership?['status']?.toString() == 'frozen';
    final status = isPaused
        ? 'Paused'
        : _titleCase(membership?['status']?.toString() ?? 'inactive');
    final paymentStatus = _titleCase(
      membership?['payment_status']?.toString() ?? 'pending',
    );

    return AppGradientScaffold(
      title: 'Membership',
      body: SafeArea(
        bottom: false,
        child: _loading
            ? const _FitLoadingState()
            : _error != null
            ? _FitErrorState(message: _error!, onRetry: _load)
            : membership == null
            ? _FitEmptyState(
                icon: Icons.workspace_premium_outlined,
                title: 'No active membership yet',
                message: 'Join a gym to unlock member access and gym benefits.',
                buttonLabel: 'Discover Gyms',
                onPressed: _handleDiscoverGyms,
              )
            : RefreshIndicator(
                onRefresh: _load,
                color: AppColors.primaryBright,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg,
                    AppSpacing.sm,
                    AppSpacing.lg,
                    AppSpacing.xl,
                  ),
                  children: <Widget>[
                    _MembershipTopBar(
                      title: 'Membership',
                      subtitle: 'Current access, payments, and plan details.',
                      onRefresh: _loading ? null : _load,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _FitAnimatedSection(
                      child: _MembershipProfileHeader(
                        gymName: gymName,
                        branchName: branchName,
                        status: status,
                      ),
                    ),
                    const SizedBox(height: 15),
                    if (isPaused) ...<Widget>[
                      _FitAnimatedSection(
                        delay: const Duration(milliseconds: 45),
                        child: _FitGroup(
                          title: 'Membership paused',
                          children: <Widget>[
                            _FitValueRow(
                              icon: Icons.pause_circle_outline_rounded,
                              title: 'Paused since',
                              value: _formatDate(membership['paused_at']),
                            ),
                            _FitValueRow(
                              icon: Icons.calendar_month_rounded,
                              title: 'Extension on resume',
                              value:
                                  '${membership['current_paused_days'] ?? 0} day(s) will be added to your expiry',
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 15),
                    ],
                    _FitAnimatedSection(
                      delay: const Duration(milliseconds: 70),
                      child: Row(
                        children: <Widget>[
                          Expanded(
                            child: _FitInfoCell(
                              title: status,
                              subtitle: 'Status',
                            ),
                          ),
                          const SizedBox(width: 15),
                          Expanded(
                            child: _FitInfoCell(
                              title: _formatCurrency(membership['amount_paid']),
                              subtitle: 'Paid',
                            ),
                          ),
                          const SizedBox(width: 15),
                          Expanded(
                            child: _FitInfoCell(
                              title: _formatDate(membership['expiry_date']),
                              subtitle: 'Expiry',
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 25),
                    _FitAnimatedSection(
                      delay: const Duration(milliseconds: 120),
                      child: _FitGroup(
                        title: 'Access',
                        children: <Widget>[
                          _FitRow(
                            icon: Icons.fact_check_outlined,
                            title: 'Attendance History',
                            subtitle: 'Recent check-ins',
                            onPressed: widget.onOpenAttendance,
                          ),
                          _FitRow(
                            icon: Icons.person_pin_circle_outlined,
                            title: 'Assigned Trainer',
                            subtitle: _stringValue(trainer['name']),
                            showChevron: false,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 25),
                    _FitAnimatedSection(
                      delay: const Duration(milliseconds: 170),
                      child: _FitGroup(
                        title: 'Payment',
                        children: <Widget>[
                          _FitValueRow(
                            icon: Icons.payments_rounded,
                            title: 'Final Payable',
                            value: _formatCurrency(
                              membership['final_payable_amount'],
                            ),
                          ),
                          _FitValueRow(
                            icon: Icons.account_balance_wallet_rounded,
                            title: 'Due Amount',
                            value: _formatCurrency(membership['due_amount']),
                          ),
                          _FitValueRow(
                            icon: Icons.event_available_rounded,
                            title: 'Due Date',
                            value: _formatDate(membership['due_date']),
                          ),
                          _FitValueRow(
                            icon: Icons.verified_rounded,
                            title: 'Payment Status',
                            value: paymentStatus,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 25),
                    _FitAnimatedSection(
                      delay: const Duration(milliseconds: 220),
                      child: _FitGroup(
                        title: 'Plan',
                        children: <Widget>[
                          _FitValueRow(
                            icon: Icons.card_membership_rounded,
                            title: 'Plan Name',
                            value: _stringValue(plan['name']),
                          ),
                          _FitValueRow(
                            icon: Icons.calendar_today_rounded,
                            title: 'Start Date',
                            value: _formatDate(membership['start_date']),
                          ),
                          _FitValueRow(
                            icon: Icons.event_busy_rounded,
                            title: 'Expiry Date',
                            value: _formatDate(membership['expiry_date']),
                          ),
                          _FitValueRow(
                            icon: Icons.location_city_rounded,
                            title: 'Branch City',
                            value: _stringValue(branch['city']),
                          ),
                        ],
                      ),
                    ),
                    if (hasCustomFee) ...<Widget>[
                      const SizedBox(height: 25),
                      _FitAnimatedSection(
                        delay: const Duration(milliseconds: 270),
                        child: _FitGroup(
                          title: 'Custom Fee',
                          children: <Widget>[
                            _FitValueRow(
                              icon: Icons.local_offer_rounded,
                              title: 'Custom Fee',
                              value: _formatCurrency(
                                customFee['custom_fee_amount'],
                              ),
                            ),
                            _FitValueRow(
                              icon: Icons.add_card_rounded,
                              title: 'Joining Fee',
                              value: _formatCurrency(
                                customFee['custom_joining_fee'],
                              ),
                            ),
                            _FitValueRow(
                              icon: Icons.sports_gymnastics_rounded,
                              title: 'PT Fee',
                              value: _formatCurrency(
                                customFee['pt_custom_fee'],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
      ),
    );
  }
}

class MemberAttendanceScreen extends StatefulWidget {
  const MemberAttendanceScreen({super.key, required this.repository});

  final MemberRepository repository;

  @override
  State<MemberAttendanceScreen> createState() => _MemberAttendanceScreenState();
}

class _MemberAttendanceScreenState extends State<MemberAttendanceScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _attendance = const <Map<String, dynamic>>[];
  Map<String, dynamic> _attendanceStatus = const <String, dynamic>{};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final results = await Future.wait(<Future<Map<String, dynamic>>>[
        widget.repository.fetchAttendanceHistory(),
        widget.repository.fetchAttendanceStatus(),
      ]);
      _attendance = (results[0]['data'] as List<dynamic>? ?? const <dynamic>[])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _attendanceStatus = Map<String, dynamic>.from(
        results[1]['data'] as Map? ?? const <String, dynamic>{},
      );
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final latestGym = _attendance.isEmpty
        ? 'No gym yet'
        : _attendance.first['gym'] is Map
        ? _stringValue(
            (_attendance.first['gym'] as Map)['name'],
            fallback: 'Gym unavailable',
          )
        : 'Gym unavailable';
    final checkedInToday = _attendanceStatus['checked_in_today'] == true;
    final attendanceEnabled = _attendanceStatus['enabled'] == true;

    return AppGradientScaffold(
      title: 'Activity History',
      body: SafeArea(
        bottom: false,
        child: _loading
            ? const _FitLoadingState()
            : _error != null
            ? _FitErrorState(message: _error!, onRetry: _load)
            : RefreshIndicator(
                onRefresh: _load,
                color: AppColors.primaryBright,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg,
                    AppSpacing.sm,
                    AppSpacing.lg,
                    AppSpacing.xl,
                  ),
                  children: <Widget>[
                    _MembershipTopBar(
                      title: 'Activity History',
                      subtitle: 'Attendance history and latest check-ins.',
                      onRefresh: _loading ? null : _load,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _FitAnimatedSection(
                      child: _AttendanceHeader(
                        latestGym: latestGym,
                        totalVisits: _attendance.length,
                        checkedInToday: checkedInToday,
                        enabled: attendanceEnabled,
                      ),
                    ),
                    const SizedBox(height: 15),
                    _FitAnimatedSection(
                      delay: const Duration(milliseconds: 70),
                      child: Row(
                        children: <Widget>[
                          Expanded(
                            child: _FitInfoCell(
                              title: '${_attendance.length}',
                              subtitle: 'Visits',
                            ),
                          ),
                          const SizedBox(width: 15),
                          Expanded(
                            child: _FitInfoCell(
                              title: checkedInToday ? 'Yes' : 'No',
                              subtitle: 'Today',
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 25),
                    _FitAnimatedSection(
                      delay: const Duration(milliseconds: 120),
                      child: _FitGroup(
                        title: 'Recent Check-ins',
                        children: _attendance.isEmpty
                            ? <Widget>[
                                const _FitInlineEmpty(
                                  icon: Icons.fact_check_outlined,
                                  title: 'No attendance history yet',
                                  message:
                                      'Your gym check-ins will appear here after your first visit.',
                                ),
                              ]
                            : _attendance
                                  .map(
                                    (entry) =>
                                        _AttendanceHistoryRow(entry: entry),
                                  )
                                  .toList(),
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}

class _MembershipTopBar extends StatelessWidget {
  const _MembershipTopBar({
    required this.title,
    required this.subtitle,
    required this.onRefresh,
  });

  final String title;
  final String subtitle;
  final VoidCallback? onRefresh;

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
        if (onRefresh != null) ...[
          const SizedBox(width: AppSpacing.md),
          MemberHeaderActionButton(
            icon: Icons.refresh_rounded,
            onTap: onRefresh!,
          ),
        ],
      ],
    );
  }
}

class _FitAnimatedSection extends StatelessWidget {
  const _FitAnimatedSection({required this.child, this.delay = Duration.zero});

  final Widget child;
  final Duration delay;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0, end: 1),
      duration: Duration(milliseconds: 420 + delay.inMilliseconds),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) {
        final delayed = delay == Duration.zero
            ? value
            : ((value * (420 + delay.inMilliseconds) - delay.inMilliseconds) /
                      420)
                  .clamp(0.0, 1.0);
        return Opacity(
          opacity: delayed,
          child: Transform.translate(
            offset: Offset(0, 18 * (1 - delayed)),
            child: child,
          ),
        );
      },
      child: child,
    );
  }
}

class _MembershipProfileHeader extends StatelessWidget {
  const _MembershipProfileHeader({
    required this.gymName,
    required this.branchName,
    required this.status,
  });

  final String gymName;
  final String branchName;
  final String status;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Row(
        children: <Widget>[
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.stroke),
            ),
            child: const Icon(
              Icons.fitness_center_rounded,
              color: AppColors.primaryBright,
              size: 26,
            ),
          ),
          const SizedBox(width: 15),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  gymName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '$branchName • $status',
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
    );
  }
}

class _AttendanceHeader extends StatelessWidget {
  const _AttendanceHeader({
    required this.latestGym,
    required this.totalVisits,
    required this.checkedInToday,
    required this.enabled,
  });

  final String latestGym;
  final int totalVisits;
  final bool checkedInToday;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Row(
        children: <Widget>[
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Icon(
              checkedInToday
                  ? Icons.verified_rounded
                  : enabled
                  ? Icons.directions_walk_rounded
                  : Icons.lock_outline_rounded,
              color: AppColors.primaryBright,
              size: 28,
            ),
          ),
          const SizedBox(width: 15),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Attendance',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  enabled
                      ? checkedInToday
                            ? 'Checked in today at $latestGym'
                            : totalVisits == 0
                            ? 'Ready for your first check-in'
                            : 'Latest visit at $latestGym'
                      : 'Attendance unlocks with active gym access',
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
    );
  }
}

class _FitInfoCell extends StatelessWidget {
  const _FitInfoCell({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _FitGroup extends StatelessWidget {
  const _FitGroup({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          ...children,
        ],
      ),
    );
  }
}

class _FitRow extends StatelessWidget {
  const _FitRow({
    required this.icon,
    required this.title,
    this.subtitle,
    this.onPressed,
    this.showChevron = true,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback? onPressed;
  final bool showChevron;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          children: <Widget>[
            _FitRowIcon(icon: icon),
            const SizedBox(width: 15),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (subtitle != null) ...<Widget>[
                    const SizedBox(height: 3),
                    Text(
                      subtitle!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (showChevron)
              Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textMuted,
                size: 20,
              ),
          ],
        ),
      ),
    );
  }
}

class _FitValueRow extends StatelessWidget {
  const _FitValueRow({
    required this.icon,
    required this.title,
    required this.value,
  });

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: <Widget>[
          _FitRowIcon(icon: icon),
          const SizedBox(width: 15),
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.right,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AttendanceHistoryRow extends StatelessWidget {
  const _AttendanceHistoryRow({required this.entry});

  final Map<String, dynamic> entry;

  @override
  Widget build(BuildContext context) {
    final gym = entry['gym'] is Map
        ? Map<String, dynamic>.from(entry['gym'] as Map)
        : const <String, dynamic>{};
    final gymName = _stringValue(
      gym['name'],
      fallback: entry['gym_id'] != null
          ? 'Gym #${entry['gym_id']}'
          : 'Gym unavailable',
    );

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: <Widget>[
          _FitRowIcon(icon: Icons.event_available_rounded),
          const SizedBox(width: 15),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  gymName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  _formatDateTime(entry['checked_in_at']),
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
    );
  }
}

class _FitInlineEmpty extends StatelessWidget {
  const _FitInlineEmpty({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: <Widget>[
          _FitRowIcon(icon: icon),
          const SizedBox(width: 15),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  message,
                  maxLines: 2,
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
    );
  }
}

class _FitRowIcon extends StatelessWidget {
  const _FitRowIcon({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 30,
      height: 30,
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Icon(icon, color: AppColors.primaryBright, size: 16),
    );
  }
}

class _FitRoundButton extends StatelessWidget {
  const _FitRoundButton({required this.title, required this.onPressed});

  final String title;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onPressed == null ? 0.55 : 1,
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          width: 120,
          height: 40,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.surfaceSoft,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.stroke),
          ),
          child: Text(
            title,
            style: const TextStyle(
              color: AppColors.textPrimary,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}

class _FitLoadingState extends StatelessWidget {
  const _FitLoadingState();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(25, 15, 25, 32),
      children: <Widget>[
        _FitSkeletonBlock(height: 54),
        const SizedBox(height: 15),
        Row(
          children: const <Widget>[
            Expanded(child: _FitSkeletonBlock(height: 72)),
            SizedBox(width: 15),
            Expanded(child: _FitSkeletonBlock(height: 72)),
            SizedBox(width: 15),
            Expanded(child: _FitSkeletonBlock(height: 72)),
          ],
        ),
        const SizedBox(height: 25),
        const _FitSkeletonBlock(height: 180),
        const SizedBox(height: 25),
        const _FitSkeletonBlock(height: 150),
      ],
    );
  }
}

class _FitSkeletonBlock extends StatelessWidget {
  const _FitSkeletonBlock({required this.height});

  final double height;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0.45, end: 1),
      duration: const Duration(milliseconds: 900),
      curve: Curves.easeInOut,
      builder: (context, value, child) {
        return Opacity(opacity: value, child: child);
      },
      child: Container(
        height: height,
        decoration: BoxDecoration(
          color: AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.stroke),
        ),
      ),
    );
  }
}

class _FitErrorState extends StatelessWidget {
  const _FitErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return _FitEmptyState(
      icon: Icons.error_outline_rounded,
      title: 'Could not load data',
      message: message,
      buttonLabel: 'Retry',
      onPressed: onRetry,
    );
  }
}

class _FitEmptyState extends StatelessWidget {
  const _FitEmptyState({
    required this.icon,
    required this.title,
    required this.message,
    this.buttonLabel,
    this.onPressed,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? buttonLabel;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(25, 80, 25, 32),
      children: <Widget>[
        PremiumCard(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: <Widget>[
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: Icon(icon, color: AppColors.primaryBright, size: 32),
              ),
              const SizedBox(height: 18),
              Text(
                title,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                  height: 1.4,
                ),
              ),
              if (buttonLabel != null && onPressed != null) ...<Widget>[
                const SizedBox(height: 20),
                _FitRoundButton(title: buttonLabel!, onPressed: onPressed),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

String _stringValue(Object? value, {String fallback = 'Not available'}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

String _formatDate(Object? value) {
  final text = value?.toString().trim() ?? '';
  if (text.isEmpty) {
    return 'Not available';
  }

  final date = DateTime.tryParse(text);
  if (date == null) {
    return text;
  }

  return DateFormat('dd MMM yyyy').format(date.toLocal());
}

String _formatDateTime(Object? value) {
  final text = value?.toString().trim() ?? '';
  if (text.isEmpty) {
    return 'Not recorded yet';
  }

  final date = DateTime.tryParse(text);
  if (date == null) {
    return text;
  }

  return DateFormat('dd MMM yyyy • hh:mm a').format(date.toLocal());
}

String _formatCurrency(Object? value) {
  final number = value is num
      ? value.toDouble()
      : double.tryParse('$value') ?? 0;
  return '₹${number.toStringAsFixed(number.truncateToDouble() == number ? 0 : 2)}';
}

String _titleCase(String value) {
  return value
      .split('_')
      .where((part) => part.trim().isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}
