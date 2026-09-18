import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import '../../core/config.dart';
import '../auth/session_controller.dart';
import 'member_repository.dart';

class MemberSettingsScreen extends StatelessWidget {
  const MemberSettingsScreen({
    super.key,
    required this.repository,
    required this.session,
    required this.onOpenProfile,
    required this.onEditProfile,
    required this.onOpenMembership,
    required this.onOpenAttendance,
    required this.onPreferencesChanged,
  });

  final MemberRepository repository;
  final MemberSessionController session;
  final Future<void> Function() onOpenProfile;
  final Future<void> Function() onEditProfile;
  final Future<void> Function() onOpenMembership;
  final Future<void> Function() onOpenAttendance;
  final Future<void> Function() onPreferencesChanged;

  @override
  Widget build(BuildContext context) {
    final baseUri = Uri.tryParse(MemberConfig.apiBaseUrl);
    final webBase = baseUri == null
        ? null
        : Uri(
            scheme: baseUri.scheme,
            host: baseUri.host,
            port: baseUri.hasPort ? baseUri.port : null,
          ).toString();
    final contactUrl = webBase == null ? '/contact' : '$webBase/contact';
    final privacyUrl = webBase == null
        ? '/privacy-policy'
        : '$webBase/privacy-policy';
    final termsUrl = webBase == null ? '/terms' : '$webBase/terms';
    final deletionUrl = webBase == null
        ? '/account-deletion?app=member'
        : '$webBase/account-deletion?app=member';
    final user = session.user;
    final name = user?.name.trim().isNotEmpty == true ? user!.name : 'Member';
    final email = user?.email.trim().isNotEmpty == true
        ? user!.email
        : 'member account';
    final role = user?.activeRole.trim().isNotEmpty == true
        ? user!.activeRole
        : 'member';

    return AppGradientScaffold(
      title: 'Settings',
      body: SafeArea(
        bottom: false,
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.sm,
            AppSpacing.lg,
            AppSpacing.xl,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const _SettingsTopBar(
                title: 'Settings',
                subtitle: 'Profile, membership, activity, and app preferences.',
              ),
              const SizedBox(height: AppSpacing.md),
              _AnimatedSection(
                child: _ProfileHeader(
                  name: name,
                  email: email,
                  role: role,
                  isActive: user?.isActive == true,
                  onEdit: onEditProfile,
                ),
              ),
              const SizedBox(height: 15),
              _AnimatedSection(
                delay: const Duration(milliseconds: 70),
                child: Row(
                  children: [
                    Expanded(
                      child: _TitleSubtitleCell(
                        title: role.toUpperCase(),
                        subtitle: 'Role',
                      ),
                    ),
                    const SizedBox(width: 15),
                    Expanded(
                      child: _TitleSubtitleCell(
                        title: user?.isActive == true ? 'Active' : 'Limited',
                        subtitle: 'Session',
                      ),
                    ),
                    const SizedBox(width: 15),
                    const Expanded(
                      child: _TitleSubtitleCell(
                        title: 'Synced',
                        subtitle: 'Cloud',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _AnimatedSection(
                delay: const Duration(milliseconds: 120),
                child: _SettingsGroup(
                  title: 'Account',
                  children: [
                    _SettingsRow(
                      icon: Icons.person_outline_rounded,
                      title: 'Profile Overview',
                      onPressed: onOpenProfile,
                    ),
                    _SettingsRow(
                      icon: Icons.workspace_premium_rounded,
                      title: 'Membership',
                      onPressed: onOpenMembership,
                    ),
                    _SettingsRow(
                      icon: Icons.fact_check_outlined,
                      title: 'Activity History',
                      onPressed: onOpenAttendance,
                    ),
                    _SettingsRow(
                      icon: Icons.insights_rounded,
                      title: 'Workout progress settings',
                      onPressed: () => _openWorkoutProgressSettings(context),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _AnimatedSection(
                delay: const Duration(milliseconds: 220),
                child: _SettingsGroup(
                  title: 'Other',
                  children: [
                    _SettingsRow(
                      icon: Icons.support_agent_rounded,
                      title: 'Contact Us',
                      onPressed: () =>
                          _openLink(context, contactUrl, 'Contact page'),
                    ),
                    _SettingsRow(
                      icon: Icons.privacy_tip_outlined,
                      title: 'Privacy Policy',
                      onPressed: () =>
                          _openLink(context, privacyUrl, 'Privacy policy'),
                    ),
                    _SettingsRow(
                      icon: Icons.gavel_rounded,
                      title: 'Terms',
                      onPressed: () => _openLink(context, termsUrl, 'Terms'),
                    ),
                    _SettingsRow(
                      icon: Icons.person_remove_outlined,
                      title: 'Delete Account',
                      onPressed: () => _openLink(
                        context,
                        deletionUrl,
                        'Account deletion page',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _AnimatedSection(
                delay: const Duration(milliseconds: 270),
                child: _SessionCard(onLogout: () => session.logout()),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openWorkoutProgressSettings(BuildContext context) async {
    Map<String, dynamic> preferences = const {};
    try {
      final response = await repository.fetchWorkoutPreferences();
      preferences = Map<String, dynamic>.from(
        response['data'] as Map? ?? const <String, dynamic>{},
      );
    } catch (exception) {
      if (!context.mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
      return;
    }

    if (!context.mounted) {
      return;
    }

    final target = TextEditingController(
      text: preferences['target_weight_kg']?.toString() ?? '',
    );
    final minutes = TextEditingController(
      text: preferences['reminder_minutes_before']?.toString() ?? '60',
    );
    final workoutTimeValue =
        preferences['default_workout_time']?.toString() ?? '18:00';
    final workoutTime = TextEditingController(
      text: workoutTimeValue.length >= 5
          ? workoutTimeValue.substring(0, 5)
          : '18:00',
    );
    final quietStartValue = preferences['quiet_hours_start']?.toString() ?? '';
    final quietEndValue = preferences['quiet_hours_end']?.toString() ?? '';
    final quietStart = TextEditingController(
      text: quietStartValue.length >= 5 ? quietStartValue.substring(0, 5) : '',
    );
    final quietEnd = TextEditingController(
      text: quietEndValue.length >= 5 ? quietEndValue.substring(0, 5) : '',
    );
    var showGoal = preferences['show_weight_goal'] != false;
    var reminderEnabled =
        preferences['scheduled_workout_reminder_enabled'] == true;
    var missedFollowUp =
        preferences['missed_workout_follow_up_enabled'] == true;
    var streakEncouragement =
        preferences['streak_encouragement_enabled'] == true;

    final save = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Workout progress settings'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: target,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Target weight (kg)',
                    helperText: 'Leave blank to clear the goal.',
                  ),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Show goal on chart'),
                  value: showGoal,
                  onChanged: (value) => setDialogState(() => showGoal = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Scheduled workout reminders'),
                  value: reminderEnabled,
                  onChanged: (value) =>
                      setDialogState(() => reminderEnabled = value),
                ),
                if (reminderEnabled)
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: workoutTime,
                          decoration: const InputDecoration(
                            labelText: 'Workout time HH:mm',
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextField(
                          controller: minutes,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(
                            labelText: 'Minutes before',
                          ),
                        ),
                      ),
                    ],
                  ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Missed-workout follow-up'),
                  value: missedFollowUp,
                  onChanged: (value) =>
                      setDialogState(() => missedFollowUp = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Consistency encouragement'),
                  value: streakEncouragement,
                  onChanged: (value) =>
                      setDialogState(() => streakEncouragement = value),
                ),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: quietStart,
                        decoration: const InputDecoration(
                          labelText: 'Quiet from HH:mm',
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextField(
                        controller: quietEnd,
                        decoration: const InputDecoration(
                          labelText: 'Quiet until HH:mm',
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );

    try {
      if (save == true) {
        await repository.updateWorkoutPreferences({
          'target_weight_kg': target.text.trim().isEmpty
              ? null
              : double.tryParse(target.text.trim()),
          'show_weight_goal': showGoal,
          'scheduled_workout_reminder_enabled': reminderEnabled,
          'reminder_minutes_before': int.tryParse(minutes.text.trim()) ?? 60,
          'default_workout_time': workoutTime.text.trim(),
          'missed_workout_follow_up_enabled': missedFollowUp,
          'streak_encouragement_enabled': streakEncouragement,
          'quiet_hours_start': quietStart.text.trim().isEmpty
              ? null
              : quietStart.text.trim(),
          'quiet_hours_end': quietEnd.text.trim().isEmpty
              ? null
              : quietEnd.text.trim(),
          'timezone': 'Asia/Kolkata',
        });
        await onPreferencesChanged();
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Workout progress settings updated.')),
          );
        }
      }
    } catch (exception) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      target.dispose();
      minutes.dispose();
      workoutTime.dispose();
      quietStart.dispose();
      quietEnd.dispose();
    }
  }

  Future<void> _openLink(
    BuildContext context,
    String value,
    String label,
  ) async {
    final uri = Uri.tryParse(value);
    final opened =
        uri != null &&
        await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (opened || !context.mounted) {
      return;
    }

    await Clipboard.setData(ClipboardData(text: value));
    if (!context.mounted) {
      return;
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text('$label link copied.')));
  }
}

class _SettingsTopBar extends StatelessWidget {
  const _SettingsTopBar({required this.title, required this.subtitle});

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

class _AnimatedSection extends StatelessWidget {
  const _AnimatedSection({required this.child, this.delay = Duration.zero});

  final Widget child;
  final Duration delay;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
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

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({
    required this.name,
    required this.email,
    required this.role,
    required this.isActive,
    required this.onEdit,
  });

  final String name;
  final String email;
  final String role;
  final bool isActive;
  final Future<void> Function() onEdit;

  @override
  Widget build(BuildContext context) {
    final initials = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part[0].toUpperCase())
        .join();

    return PremiumCard(
      child: Row(
        children: [
          Container(
            width: 54,
            height: 54,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.surfaceSoft,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: AppColors.stroke),
            ),
            child: Text(
              initials.isEmpty ? 'M' : initials,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: AppColors.primaryBright,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.md),
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
                const SizedBox(height: 4),
                Text(
                  '$role • ${isActive ? 'Active account' : email}',
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
          const SizedBox(width: AppSpacing.sm),
          InkWell(
            onTap: onEdit,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.stroke),
              ),
              child: Text(
                'Edit',
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TitleSubtitleCell extends StatelessWidget {
  const _TitleSubtitleCell({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 10),
      child: Column(
        children: [
          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
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

class _SettingsGroup extends StatelessWidget {
  const _SettingsGroup({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 10),
          ...children,
        ],
      ),
    );
  }
}

class _SettingsRow extends StatelessWidget {
  const _SettingsRow({
    required this.icon,
    required this.title,
    required this.onPressed,
  });

  final IconData icon;
  final String title;
  final Future<void> Function() onPressed;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        height: 48,
        padding: const EdgeInsets.symmetric(horizontal: 8),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            _RowIcon(icon: icon),
            const SizedBox(width: 15),
            Expanded(
              child: Text(
                title,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              size: 18,
              color: AppColors.textMuted,
            ),
          ],
        ),
      ),
    );
  }
}

class _SessionCard extends StatelessWidget {
  const _SessionCard({required this.onLogout});

  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Session',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Sign out securely. Your stored member token will be cleared.',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          InkWell(
            onTap: onLogout,
            borderRadius: BorderRadius.circular(18),
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 14),
              decoration: BoxDecoration(
                color: AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.stroke),
              ),
              child: Text(
                'Logout',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _RowIcon extends StatelessWidget {
  const _RowIcon({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 30,
      width: 30,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Icon(icon, size: 16, color: AppColors.primaryBright),
    );
  }
}
