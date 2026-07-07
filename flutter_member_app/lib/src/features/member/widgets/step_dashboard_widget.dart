import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/common_widgets.dart';

class StepDashboardData {
  const StepDashboardData({
    required this.today,
    required this.goal,
    required this.progressPercent,
    required this.distanceKm,
    required this.calories,
    required this.streakDays,
    required this.lastSyncedAt,
  });

  final int today;
  final int goal;
  final int progressPercent;
  final double distanceKm;
  final int calories;
  final int streakDays;
  final String? lastSyncedAt;
}

class StepDashboardWidget extends StatelessWidget {
  const StepDashboardWidget({
    super.key,
    required this.steps,
    required this.permissionStatus,
    required this.loading,
    required this.onRefresh,
    required this.onRequestPermission,
    this.statusMessage,
  });

  final StepDashboardData? steps;
  final String permissionStatus;
  final bool loading;
  final VoidCallback onRefresh;
  final VoidCallback onRequestPermission;
  final String? statusMessage;

  static const _ink = AppColors.textPrimary;
  static const _mutedSoft = AppColors.textMuted;
  static const _primary = AppColors.primary;
  static const _primaryBright = AppColors.primaryBright;
  static const _success = AppColors.success;
  static const _surface = AppColors.surface;
  static const _surfaceSoft = AppColors.surfaceSoft;
  static const _stroke = AppColors.stroke;
  static const _strokeStrong = AppColors.strokeStrong;

  @override
  Widget build(BuildContext context) {
    final supported = permissionStatus != 'unavailable';
    final granted = permissionStatus == 'granted';
    final needsPermission =
        permissionStatus == 'denied' || permissionStatus == 'unknown';
    final stepData = steps ??
        const StepDashboardData(
          today: 0,
          goal: 10000,
          progressPercent: 0,
          distanceKm: 0,
          calories: 0,
          streakDays: 0,
          lastSyncedAt: null,
        );
    final progress = (stepData.progressPercent / 100).clamp(0.0, 1.0);

    return ClipRRect(
      borderRadius: BorderRadius.circular(36),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              _surface,
              _surfaceSoft,
            ],
          ),
          border: Border.all(color: _stroke.withValues(alpha: 0.88)),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.08),
              blurRadius: 24,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Stack(
          children: [
            Positioned(
              top: -72,
              right: -54,
              child: _StepGlowOrb(
                size: 188,
                color: _primaryBright,
                opacity: 0.10,
              ),
            ),
            Positioned(
              bottom: -84,
              left: -84,
              child: _StepGlowOrb(size: 220, color: _primary, opacity: 0.06),
            ),
            Positioned(
              top: 20,
              right: 20,
              child: Container(
                width: 74,
                height: 74,
                decoration: BoxDecoration(
                  border: Border.all(color: _stroke.withValues(alpha: 0.6)),
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      _surface.withValues(alpha: 0.78),
                      _primaryBright.withValues(alpha: 0.03),
                    ],
                  ),
                  shape: BoxShape.circle,
                ),
              ),
            ),
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: Container(
                height: 4,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      _primaryBright.withValues(alpha: 0.95),
                      _primary.withValues(alpha: 0.88),
                    ],
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
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
                              'Today\'s Steps',
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                color: _ink,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              granted
                                  ? 'Daily movement score'
                                  : supported
                                      ? 'Connect health access'
                                      : 'Health steps unavailable',
                              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                                color: _mutedSoft,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                      _StepRefreshButton(loading: loading, onRefresh: onRefresh),
                    ],
                  ),
                  const SizedBox(height: 20),
                  if (!supported)
                    _StepUnavailableState(onRefresh: onRefresh)
                  else if (needsPermission)
                    _StepPermissionState(
                      denied: permissionStatus == 'denied',
                      onRequestPermission: onRequestPermission,
                    )
                  else
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final compact = constraints.maxWidth < 350;
                        final ring = _StepProgressRing(
                          progress: progress,
                          percent: stepData.progressPercent,
                          size: compact ? 126 : 148,
                        );
                        final count = Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _formatCount(stepData.today),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.displayMedium?.copyWith(
                                color: _ink,
                                fontWeight: FontWeight.w900,
                                height: 0.92,
                                letterSpacing: -1.8,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'steps today',
                              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                color: _mutedSoft,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 10),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 8,
                              ),
                              decoration: BoxDecoration(
                                color: _primaryBright.withValues(alpha: 0.08),
                                borderRadius: BorderRadius.circular(999),
                                border: Border.all(
                                  color: _primaryBright.withValues(alpha: 0.14),
                                ),
                              ),
                              child: Text(
                                '${stepData.progressPercent}% of ${_formatCount(stepData.goal)}',
                                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                                  color: _primaryBright,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                          ],
                        );

                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            compact
                                ? Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      count,
                                      const SizedBox(height: 18),
                                      Center(child: ring),
                                    ],
                                  )
                                : Row(
                                    children: [
                                      Expanded(child: count),
                                      const SizedBox(width: 16),
                                      ring,
                                    ],
                                  ),
                            const SizedBox(height: 18),
                            Wrap(
                              spacing: 9,
                              runSpacing: 9,
                              children: [
                                _StepStatPill(
                                  icon: Icons.route_rounded,
                                  label: 'Distance',
                                  value: '${stepData.distanceKm.toStringAsFixed(1)} km',
                                  color: _primaryBright,
                                ),
                                _StepStatPill(
                                  icon: Icons.local_fire_department_rounded,
                                  label: 'Calories',
                                  value: '${stepData.calories}',
                                  color: _primary,
                                ),
                                _StepStatPill(
                                  icon: Icons.bolt_rounded,
                                  label: 'Streak',
                                  value: '${stepData.streakDays} d',
                                  color: _success,
                                ),
                              ],
                            ),
                            const SizedBox(height: 14),
                            _StepSyncFooter(
                              text: _lastSyncedLabel(
                                stepData.lastSyncedAt,
                                statusMessage,
                              ),
                            ),
                          ],
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

  String _formatCount(int value) {
    return value.toString().replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (match) => ',',
    );
  }

  String _lastSyncedLabel(String? raw, String? statusMessage) {
    if (statusMessage != null && statusMessage.trim().isNotEmpty) {
      return statusMessage;
    }

    if (raw == null || raw.isEmpty) {
      return 'Waiting for first device sync';
    }

    final parsed = DateTime.tryParse(raw)?.toLocal();
    if (parsed == null) {
      return 'Last synced just now';
    }

    final diff = DateTime.now().difference(parsed);
    if (diff.inMinutes < 1) {
      return 'Last synced just now';
    }
    if (diff.inHours < 1) {
      return 'Last synced ${diff.inMinutes} min ago';
    }
    if (diff.inDays < 1) {
      return 'Last synced ${diff.inHours} hr ago';
    }
    return 'Last synced ${diff.inDays} day${diff.inDays == 1 ? '' : 's'} ago';
  }
}

class _StepGlowOrb extends StatelessWidget {
  const _StepGlowOrb({
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

class _StepProgressRing extends StatelessWidget {
  const _StepProgressRing({
    required this.progress,
    required this.percent,
    required this.size,
  });

  final double progress;
  final int percent;
  final double size;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0, end: progress),
      duration: const Duration(milliseconds: 520),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) {
        return SizedBox(
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
                  color: Colors.white.withValues(alpha: 0.86),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.shadow.withValues(alpha: 0.06),
                      blurRadius: 18,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
              ),
              SizedBox(
                width: size - 18,
                height: size - 18,
                child: CircularProgressIndicator(
                  value: value,
                  strokeWidth: 12,
                  strokeCap: StrokeCap.round,
                  backgroundColor: StepDashboardWidget._stroke,
                  valueColor: const AlwaysStoppedAnimation<Color>(StepDashboardWidget._primaryBright),
                ),
              ),
              SizedBox(
                width: size - 50,
                height: size - 50,
                child: CircularProgressIndicator(
                  value: (value * 0.82).clamp(0.0, 1.0),
                  strokeWidth: 7,
                  strokeCap: StrokeCap.round,
                  backgroundColor: Colors.transparent,
                  valueColor: const AlwaysStoppedAnimation<Color>(StepDashboardWidget._primary),
                ),
              ),
              child!,
            ],
          ),
        );
      },
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '$percent%',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              color: StepDashboardWidget._ink,
              fontWeight: FontWeight.w900,
              height: 1,
            ),
          ),
          Text(
            'goal',
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: StepDashboardWidget._mutedSoft,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _StepRefreshButton extends StatelessWidget {
  const _StepRefreshButton({
    required this.loading,
    required this.onRefresh,
  });

  final bool loading;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: loading ? null : onRefresh,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        width: 46,
        height: 46,
        decoration: BoxDecoration(
          color: StepDashboardWidget._surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: StepDashboardWidget._strokeStrong),
          boxShadow: [
            BoxShadow(
              color: AppColors.shadow.withValues(alpha: 0.05),
              blurRadius: 10,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: loading
            ? const Center(
                child: SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(
                      StepDashboardWidget._primaryBright,
                    ),
                  ),
                ),
              )
            : const Icon(
                Icons.refresh_rounded,
                color: StepDashboardWidget._primaryBright,
                size: 21,
              ),
      ),
    );
  }
}

class _StepSyncFooter extends StatelessWidget {
  const _StepSyncFooter({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
      decoration: BoxDecoration(
        color: StepDashboardWidget._surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: StepDashboardWidget._stroke),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.sync_rounded,
            color: StepDashboardWidget._primaryBright,
            size: 17,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: StepDashboardWidget._mutedSoft,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StepStatPill extends StatelessWidget {
  const _StepStatPill({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minWidth: 96),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: StepDashboardWidget._surface.withValues(alpha: 0.9),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: color.withValues(alpha: 0.16)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                value,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: StepDashboardWidget._ink,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                label,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: StepDashboardWidget._mutedSoft,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StepPermissionState extends StatelessWidget {
  const _StepPermissionState({
    required this.denied,
    required this.onRequestPermission,
  });

  final bool denied;
  final VoidCallback onRequestPermission;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _StepStatePanel(
          icon: Icons.directions_walk_rounded,
          title: denied ? 'Step access is denied' : 'Connect step tracking',
          message: denied
              ? 'Allow health permissions from device settings to sync movement.'
              : 'Grant health access to show today\'s steps, distance, and streak.',
        ),
        const SizedBox(height: 14),
        GradientButton(
          label: denied ? 'Allow Steps Access' : 'Connect Step Tracking',
          icon: Icons.lock_open_rounded,
          expanded: true,
          onPressed: onRequestPermission,
        ),
      ],
    );
  }
}

class _StepUnavailableState extends StatelessWidget {
  const _StepUnavailableState({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const _StepStatePanel(
          icon: Icons.mobile_off_rounded,
          title: 'Health steps unavailable',
          message: 'Use Health Connect on Android or HealthKit on iPhone to sync movement.',
        ),
        const SizedBox(height: 14),
        GradientButton(
          label: 'Try Again',
          icon: Icons.refresh_rounded,
          variant: GradientButtonVariant.secondary,
          expanded: true,
          onPressed: onRefresh,
        ),
      ],
    );
  }
}

class _StepStatePanel extends StatelessWidget {
  const _StepStatePanel({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: StepDashboardWidget._surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: StepDashboardWidget._stroke),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: StepDashboardWidget._primaryBright.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icon, color: StepDashboardWidget._primaryBright),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: StepDashboardWidget._ink,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  message,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: StepDashboardWidget._mutedSoft,
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
