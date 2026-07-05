import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/empty_state.dart';
import '../../../core/widgets/error_state.dart';
import '../../../core/widgets/premium_card.dart';
import 'member_repository.dart';

class MemberMembershipScreen extends StatefulWidget {
  const MemberMembershipScreen({
    super.key,
    required this.repository,
    required this.onDiscoverGyms,
    this.onShowQr,
    this.onOpenAttendance,
  });

  final MemberRepository repository;
  final VoidCallback onDiscoverGyms;
  final VoidCallback? onShowQr;
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
    final status = _titleCase(membership?['status']?.toString() ?? 'inactive');
    final paymentStatus = _titleCase(
      membership?['payment_status']?.toString() ?? 'pending',
    );

    return Scaffold(
      backgroundColor: _FitColor.white,
      appBar: _FitAppBar(
        title: 'Membership',
        onRefresh: _loading ? null : _load,
      ),
      body: _loading
          ? const _FitLoadingState()
          : _error != null
          ? _FitErrorState(message: _error!, onRetry: _load)
          : membership == null
          ? _FitEmptyState(
              icon: Icons.workspace_premium_outlined,
              title: 'No active membership yet',
              message: 'Join a gym to unlock QR check-in and member access.',
              buttonLabel: 'Discover Gyms',
              onPressed: _handleDiscoverGyms,
            )
          : RefreshIndicator(
              onRefresh: _load,
              color: _FitColor.primaryEnd,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                padding: const EdgeInsets.fromLTRB(25, 15, 25, 32),
                children: <Widget>[
                  _FitAnimatedSection(
                    child: _MembershipProfileHeader(
                      gymName: gymName,
                      branchName: branchName,
                      status: status,
                      onQr: widget.onShowQr,
                    ),
                  ),
                  const SizedBox(height: 15),
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
                          icon: Icons.qr_code_2_rounded,
                          title: 'QR Check-in Pass',
                          subtitle: branchName,
                          onPressed: widget.onShowQr,
                        ),
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
                            value: _formatCurrency(customFee['pt_custom_fee']),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
    );
  }
}

class MemberQrScreen extends StatefulWidget {
  const MemberQrScreen({
    super.key,
    required this.repository,
    required this.onDiscoverGyms,
  });

  final MemberRepository repository;
  final VoidCallback onDiscoverGyms;

  @override
  State<MemberQrScreen> createState() => _MemberQrScreenState();
}

class _MemberQrScreenState extends State<MemberQrScreen>
    with SingleTickerProviderStateMixin {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _membership;
  Map<String, dynamic> _qr = const <String, dynamic>{};
  late final AnimationController _pulseController;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    )..repeat(reverse: true);
    _load();
  }

  @override
  void dispose() {
    _pulseController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final results = await Future.wait(<Future<Map<String, dynamic>>>[
        widget.repository.fetchMembership(),
        widget.repository.fetchQrCode(),
      ]);

      final membershipData = results[0]['data'];
      _membership = membershipData is Map
          ? Map<String, dynamic>.from(membershipData)
          : null;
      _qr = Map<String, dynamic>.from(results[1]['data'] as Map? ?? const {});
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
    final enabled = _qr['enabled'] == true;
    final qrPayload = _qr['qr_payload']?.toString() ?? '';
    final status = _qr['check_in_status'] is Map
        ? Map<String, dynamic>.from(_qr['check_in_status'] as Map)
        : const <String, dynamic>{};

    return AppGradientScaffold(
      title: 'Member QR',
      actions: <Widget>[
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
      body: _loading
          ? const _MemberStatusSkeleton()
          : _error != null
          ? ErrorState(message: _error!, onRetry: _load)
          : membership == null || !enabled || qrPayload.isEmpty
          ? EmptyState(
              title: 'QR check-in is locked',
              message:
                  _qr['message']?.toString() ??
                  'Attendance QR becomes available once you have an active gym membership and branch assignment.',
              icon: Icons.qr_code_2_rounded,
              action: GradientButton(
                label: 'Discover Gyms',
                icon: Icons.travel_explore_rounded,
                onPressed: _handleDiscoverGyms,
              ),
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: <Widget>[
                  PremiumCard(
                    glowColor: AppColors.primaryBright,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Scan at front desk',
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          'Present this active member check-in token when you arrive. It is tied to your current gym and branch.',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        AnimatedBuilder(
                          animation: _pulseController,
                          builder: (context, child) {
                            final pulse = 1 + (_pulseController.value * 0.03);
                            return Transform.scale(scale: pulse, child: child);
                          },
                          child: Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(AppSpacing.lg),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(
                                AppSpacing.radiusLg,
                              ),
                              gradient: const LinearGradient(
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                                colors: <Color>[
                                  Color(0xFFEDF7FF),
                                  Color(0xFFCFEFFF),
                                  Color(0xFFB9F4FF),
                                ],
                              ),
                              boxShadow: <BoxShadow>[
                                BoxShadow(
                                  color: AppColors.primary.withValues(
                                    alpha: 0.22,
                                  ),
                                  blurRadius: 26,
                                  offset: const Offset(0, 18),
                                ),
                              ],
                            ),
                            child: Column(
                              children: <Widget>[
                                const Icon(
                                  Icons.qr_code_2_rounded,
                                  size: 124,
                                  color: Color(0xFF08111C),
                                ),
                                const SizedBox(height: AppSpacing.md),
                                Text(
                                  _chunkToken(qrPayload),
                                  textAlign: TextAlign.center,
                                  style: Theme.of(context).textTheme.titleSmall
                                      ?.copyWith(
                                        color: const Color(0xFF08111C),
                                        fontFamily: 'monospace',
                                        height: 1.5,
                                      ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Wrap(
                          spacing: AppSpacing.sm,
                          runSpacing: AppSpacing.sm,
                          children: <Widget>[
                            StatusBadge(
                              label: status['checked_in_today'] == true
                                  ? 'Checked in today'
                                  : 'Ready to scan',
                              color: status['checked_in_today'] == true
                                  ? AppColors.statusCompleted
                                  : AppColors.primaryBright,
                              icon: Icons.flash_on_rounded,
                            ),
                            if (_membership?['branch'] is Map)
                              StatusBadge(
                                label: _stringValue(
                                  (_membership!['branch'] as Map)['name'],
                                  fallback: 'Assigned branch',
                                ),
                                color: AppColors.accentNeon,
                              ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          'Last check-in: ${_formatDateTime(status['last_check_in_at'])}',
                          style: Theme.of(context).textTheme.bodyMedium,
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
    final qrCount = _attendance
        .where((entry) => entry['check_in_method']?.toString() == 'qr')
        .length;
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

    return Scaffold(
      backgroundColor: _FitColor.white,
      appBar: _FitAppBar(
        title: 'Activity History',
        onRefresh: _loading ? null : _load,
      ),
      body: _loading
          ? const _FitLoadingState()
          : _error != null
          ? _FitErrorState(message: _error!, onRetry: _load)
          : RefreshIndicator(
              onRefresh: _load,
              color: _FitColor.primaryEnd,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                padding: const EdgeInsets.fromLTRB(25, 15, 25, 32),
                children: <Widget>[
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
                            title: '$qrCount',
                            subtitle: 'QR',
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
    );
  }
}

class _FitAppBar extends StatelessWidget implements PreferredSizeWidget {
  const _FitAppBar({required this.title, required this.onRefresh});

  final String title;
  final VoidCallback? onRefresh;

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      backgroundColor: _FitColor.white,
      centerTitle: true,
      elevation: 0,
      title: Text(
        title,
        style: TextStyle(
          color: _FitColor.black,
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
      ),
      actions: <Widget>[
        Padding(
          padding: const EdgeInsets.only(right: 16),
          child: _FitIconButton(icon: Icons.refresh_rounded, onTap: onRefresh),
        ),
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
    required this.onQr,
  });

  final String gymName;
  final String branchName;
  final String status;
  final VoidCallback? onQr;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Container(
          width: 54,
          height: 54,
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: _FitColor.primaryGradient),
            borderRadius: BorderRadius.circular(18),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: _FitColor.primaryEnd.withValues(alpha: 0.22),
                blurRadius: 18,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: const Icon(
            Icons.fitness_center_rounded,
            color: Colors.white,
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
                style: TextStyle(
                  color: _FitColor.black,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                '$branchName • $status',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: _FitColor.gray,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
        _FitRoundButton(title: 'QR', width: 76, height: 34, onPressed: onQr),
      ],
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
    return Row(
      children: <Widget>[
        Container(
          width: 54,
          height: 54,
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: _FitColor.secondaryGradient),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Icon(
            checkedInToday
                ? Icons.verified_rounded
                : enabled
                ? Icons.directions_walk_rounded
                : Icons.lock_outline_rounded,
            color: Colors.white,
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
                style: TextStyle(
                  color: _FitColor.black,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
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
                style: TextStyle(
                  color: _FitColor.gray,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _FitInfoCell extends StatelessWidget {
  const _FitInfoCell({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 72,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: _FitColor.white,
        borderRadius: BorderRadius.circular(15),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 2,
            offset: const Offset(0, 1),
          ),
        ],
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          ShaderMask(
            blendMode: BlendMode.srcIn,
            shaderCallback: (bounds) => LinearGradient(
              colors: _FitColor.primaryGradient,
            ).createShader(bounds),
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(height: 5),
          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: _FitColor.gray,
              fontSize: 11,
              fontWeight: FontWeight.w500,
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
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 15),
      decoration: BoxDecoration(
        color: _FitColor.white,
        borderRadius: BorderRadius.circular(15),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 2,
            offset: const Offset(0, 1),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: TextStyle(
              color: _FitColor.black,
              fontSize: 16,
              fontWeight: FontWeight.w700,
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
                    style: TextStyle(
                      color: _FitColor.black,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (subtitle != null) ...<Widget>[
                    const SizedBox(height: 3),
                    Text(
                      subtitle!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: _FitColor.gray,
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (showChevron)
              Icon(
                Icons.chevron_right_rounded,
                color: _FitColor.gray,
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
              style: TextStyle(
                color: _FitColor.black,
                fontSize: 12,
                fontWeight: FontWeight.w600,
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
              style: TextStyle(
                color: _FitColor.gray,
                fontSize: 12,
                fontWeight: FontWeight.w600,
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
    final method = entry['check_in_method']?.toString() ?? 'unknown';
    final isManual = method == 'manual';
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
          _FitRowIcon(
            icon: isManual
                ? Icons.edit_calendar_rounded
                : Icons.qr_code_scanner_rounded,
            secondary: isManual,
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
                  style: TextStyle(
                    color: _FitColor.black,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  _formatDateTime(entry['checked_in_at']),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: _FitColor.gray,
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          _FitMethodPill(label: method.toUpperCase(), secondary: isManual),
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
                  style: TextStyle(
                    color: _FitColor.black,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  message,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: _FitColor.gray,
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
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

class _FitMethodPill extends StatelessWidget {
  const _FitMethodPill({required this.label, required this.secondary});

  final String label;
  final bool secondary;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: secondary
              ? _FitColor.secondaryGradient
              : _FitColor.primaryGradient,
        ),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 9,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.4,
        ),
      ),
    );
  }
}

class _FitRowIcon extends StatelessWidget {
  const _FitRowIcon({required this.icon, this.secondary = false});

  final IconData icon;
  final bool secondary;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 30,
      height: 30,
      decoration: BoxDecoration(
        color: secondary
            ? _FitColor.secondaryStart.withValues(alpha: 0.16)
            : _FitColor.lightGray,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Icon(
        icon,
        color: secondary ? _FitColor.secondaryEnd : _FitColor.primaryEnd,
        size: 16,
      ),
    );
  }
}

class _FitIconButton extends StatelessWidget {
  const _FitIconButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        width: 32,
        height: 32,
        decoration: BoxDecoration(
          color: _FitColor.lightGray,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(icon, color: _FitColor.black, size: 18),
      ),
    );
  }
}

class _FitRoundButton extends StatelessWidget {
  const _FitRoundButton({
    required this.title,
    required this.onPressed,
    this.width = 120,
    this.height = 40,
  });

  final String title;
  final VoidCallback? onPressed;
  final double width;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onPressed == null ? 0.55 : 1,
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(99),
        child: Container(
          width: width,
          height: height,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: _FitColor.primaryGradient),
            borderRadius: BorderRadius.circular(99),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: _FitColor.primaryEnd.withValues(alpha: 0.22),
                blurRadius: 12,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Text(
            title,
            style: const TextStyle(
              color: Colors.white,
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
          color: _FitColor.lightGray,
          borderRadius: BorderRadius.circular(15),
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
        Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: _FitColor.white,
            borderRadius: BorderRadius.circular(18),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.06),
                blurRadius: 2,
                offset: const Offset(0, 1),
              ),
            ],
          ),
          child: Column(
            children: <Widget>[
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  gradient: LinearGradient(colors: _FitColor.primaryGradient),
                  borderRadius: BorderRadius.circular(22),
                ),
                child: Icon(icon, color: Colors.white, size: 32),
              ),
              const SizedBox(height: 18),
              Text(
                title,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: _FitColor.black,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: _FitColor.gray,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
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

class _FitColor {
  static const Color black = Color(0xFF1D1617);
  static const Color gray = Color(0xFF786F72);
  static const Color white = Colors.white;
  static const Color lightGray = Color(0xFFF7F8F8);
  static const Color primaryStart = Color(0xFF9DCEFF);
  static const Color primaryEnd = Color(0xFF92A3FD);
  static const Color secondaryStart = Color(0xFFEEA4CE);
  static const Color secondaryEnd = Color(0xFFC58BF2);

  static const List<Color> primaryGradient = <Color>[primaryStart, primaryEnd];
  static const List<Color> secondaryGradient = <Color>[
    secondaryStart,
    secondaryEnd,
  ];
}

class _MemberStatusSkeleton extends StatelessWidget {
  const _MemberStatusSkeleton();

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: const <Widget>[
          SkeletonProfileHeader(),
          SizedBox(height: AppSpacing.lg),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonHistoryList(items: 3),
        ],
      ),
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

String _chunkToken(String token) {
  if (token.isEmpty) {
    return 'QR payload unavailable.';
  }

  final buffer = StringBuffer();
  for (var i = 0; i < token.length; i += 18) {
    final end = (i + 18 < token.length) ? i + 18 : token.length;
    buffer.writeln(token.substring(i, end));
  }

  return buffer.toString().trim();
}
