import 'dart:async';

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
    required this.onOpenMembership,
    required this.onOpenAttendance,
    required this.onPreferencesChanged,
  });

  final MemberRepository repository;
  final MemberSessionController session;
  final Future<void> Function() onOpenProfile;
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
                subtitle: 'Manage your account, training, and support options.',
              ),
              const SizedBox(height: AppSpacing.md),
              _AnimatedSection(
                child: _ProfileHeader(
                  name: name,
                  email: email,
                  isActive: user?.isActive == true,
                  onView: onOpenProfile,
                ),
              ),
              const SizedBox(height: 25),
              _AnimatedSection(
                delay: const Duration(milliseconds: 120),
                child: _SettingsGroup(
                  title: 'Account',
                  subtitle: 'Your profile, membership, and attendance records.',
                  children: [
                    _SettingsRow(
                      icon: Icons.workspace_premium_rounded,
                      title: 'Membership',
                      subtitle: 'Plans, status, and membership history',
                      onPressed: onOpenMembership,
                    ),
                    _SettingsRow(
                      icon: Icons.fact_check_outlined,
                      title: 'Activity History',
                      subtitle: 'View gym check-ins and attendance',
                      onPressed: onOpenAttendance,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _AnimatedSection(
                delay: const Duration(milliseconds: 170),
                child: _SettingsGroup(
                  title: 'Training & Progress',
                  subtitle: 'Goals, workout reminders, and quiet hours.',
                  children: [
                    _SettingsRow(
                      icon: Icons.insights_rounded,
                      title: 'Progress & Reminders',
                      subtitle: 'Weight goal and workout reminder schedule',
                      onPressed: () => _openWorkoutProgressSettings(context),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _AnimatedSection(
                delay: const Duration(milliseconds: 220),
                child: _SettingsGroup(
                  title: 'Help & Legal',
                  subtitle: 'Support and information about your account.',
                  children: [
                    _SettingsRow(
                      icon: Icons.support_agent_rounded,
                      title: 'Contact Us',
                      subtitle: 'Get help from Gym Atlas support',
                      onPressed: () =>
                          _openLink(context, contactUrl, 'Contact page'),
                    ),
                    _SettingsRow(
                      icon: Icons.privacy_tip_outlined,
                      title: 'Privacy Policy',
                      subtitle: 'How your information is handled',
                      onPressed: () =>
                          _openLink(context, privacyUrl, 'Privacy policy'),
                    ),
                    _SettingsRow(
                      icon: Icons.gavel_rounded,
                      title: 'Terms of Service',
                      subtitle: 'Rules for using Gym Atlas',
                      onPressed: () => _openLink(context, termsUrl, 'Terms'),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _AnimatedSection(
                delay: const Duration(milliseconds: 270),
                child: _SettingsGroup(
                  title: 'Account Controls',
                  subtitle: 'Security and permanent account actions.',
                  children: [
                    _SettingsRow(
                      icon: Icons.person_remove_outlined,
                      title: 'Delete Account',
                      subtitle: 'Review the permanent deletion process',
                      destructive: true,
                      onPressed: () =>
                          _confirmAccountDeletion(context, deletionUrl),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _AnimatedSection(
                delay: const Duration(milliseconds: 320),
                child: _SessionCard(onLogout: () => _confirmLogout(context)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openWorkoutProgressSettings(BuildContext context) async {
    Map<String, dynamic> preferences = const {};
    unawaited(
      showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (_) => const PopScope(
          canPop: false,
          child: AlertDialog(
            content: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircularProgressIndicator(),
                SizedBox(width: 18),
                Flexible(child: Text('Loading progress settings...')),
              ],
            ),
          ),
        ),
      ),
    );
    await Future<void>.delayed(Duration.zero);
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
      ).showSnackBar(SnackBar(content: Text(_settingsError(exception))));
      return;
    } finally {
      if (context.mounted) {
        Navigator.of(context, rootNavigator: true).pop();
      }
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
    final formKey = GlobalKey<FormState>();

    final save = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Progress & reminders'),
          content: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: SingleChildScrollView(
              child: Form(
                key: formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Weight goal',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: target,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: const InputDecoration(
                        labelText: 'Target weight (kg)',
                        helperText: 'Leave blank to clear the goal.',
                      ),
                      validator: (value) {
                        final trimmed = value?.trim() ?? '';
                        if (trimmed.isEmpty) return null;
                        final parsed = double.tryParse(trimmed);
                        if (parsed == null || parsed < 20 || parsed > 500) {
                          return 'Enter a weight between 20 and 500 kg.';
                        }
                        return null;
                      },
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Show goal on chart'),
                      subtitle: const Text(
                        'Display the target beside your weight trend.',
                      ),
                      value: showGoal,
                      onChanged: (value) =>
                          setDialogState(() => showGoal = value),
                    ),
                    const Divider(height: 28),
                    Text(
                      'Workout reminders',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Scheduled workout reminders'),
                      subtitle: const Text(
                        'Remind me before my usual workout time.',
                      ),
                      value: reminderEnabled,
                      onChanged: (value) =>
                          setDialogState(() => reminderEnabled = value),
                    ),
                    if (reminderEnabled)
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: workoutTime,
                              readOnly: true,
                              onTap: () async {
                                await _pickClockTime(
                                  dialogContext,
                                  workoutTime,
                                );
                                setDialogState(() {});
                              },
                              decoration: const InputDecoration(
                                labelText: 'Workout time',
                                suffixIcon: Icon(Icons.schedule_rounded),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: TextFormField(
                              controller: minutes,
                              keyboardType: TextInputType.number,
                              decoration: const InputDecoration(
                                labelText: 'Minutes before',
                              ),
                              validator: (value) {
                                final parsed = int.tryParse(
                                  value?.trim() ?? '',
                                );
                                if (parsed == null ||
                                    parsed < 0 ||
                                    parsed > 10080) {
                                  return 'Use 0–10080';
                                }
                                return null;
                              },
                            ),
                          ),
                        ],
                      ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Missed-workout follow-up'),
                      subtitle: const Text(
                        'Send a helpful follow-up after a missed session.',
                      ),
                      value: missedFollowUp,
                      onChanged: (value) =>
                          setDialogState(() => missedFollowUp = value),
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Consistency encouragement'),
                      subtitle: const Text(
                        'Celebrate training streaks and consistency.',
                      ),
                      value: streakEncouragement,
                      onChanged: (value) =>
                          setDialogState(() => streakEncouragement = value),
                    ),
                    const Divider(height: 28),
                    Text(
                      'Quiet hours',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Optional. Reminders will wait until quiet hours end.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            controller: quietStart,
                            readOnly: true,
                            onTap: () async {
                              await _pickClockTime(dialogContext, quietStart);
                              setDialogState(() {});
                            },
                            decoration: InputDecoration(
                              labelText: 'Start',
                              suffixIcon: quietStart.text.isEmpty
                                  ? const Icon(Icons.schedule_rounded)
                                  : IconButton(
                                      tooltip: 'Clear quiet start',
                                      onPressed: () =>
                                          setDialogState(quietStart.clear),
                                      icon: const Icon(Icons.close_rounded),
                                    ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: TextFormField(
                            controller: quietEnd,
                            readOnly: true,
                            onTap: () async {
                              await _pickClockTime(dialogContext, quietEnd);
                              setDialogState(() {});
                            },
                            decoration: InputDecoration(
                              labelText: 'End',
                              suffixIcon: quietEnd.text.isEmpty
                                  ? const Icon(Icons.schedule_rounded)
                                  : IconButton(
                                      tooltip: 'Clear quiet end',
                                      onPressed: () =>
                                          setDialogState(quietEnd.clear),
                                      icon: const Icon(Icons.close_rounded),
                                    ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () {
                if (formKey.currentState?.validate() == true) {
                  Navigator.pop(dialogContext, true);
                }
              },
              child: const Text('Save changes'),
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
        ).showSnackBar(SnackBar(content: Text(_settingsError(exception))));
      }
    } finally {
      target.dispose();
      minutes.dispose();
      workoutTime.dispose();
      quietStart.dispose();
      quietEnd.dispose();
    }
  }

  Future<void> _confirmAccountDeletion(
    BuildContext context,
    String deletionUrl,
  ) async {
    final continueToDeletion = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Review account deletion?'),
        content: const Text(
          'Account deletion is permanent and may remove access to your profile. The next page explains what is deleted and how to submit the request.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: FilledButton.styleFrom(backgroundColor: AppColors.error),
            child: const Text('Review deletion'),
          ),
        ],
      ),
    );

    if (continueToDeletion == true && context.mounted) {
      await _openLink(context, deletionUrl, 'Account deletion page');
    }
  }

  Future<void> _pickClockTime(
    BuildContext context,
    TextEditingController controller,
  ) async {
    final parts = controller.text.split(':');
    final initial = parts.length == 2
        ? TimeOfDay(
            hour: int.tryParse(parts[0])?.clamp(0, 23) ?? 18,
            minute: int.tryParse(parts[1])?.clamp(0, 59) ?? 0,
          )
        : const TimeOfDay(hour: 18, minute: 0);
    final selected = await showTimePicker(
      context: context,
      initialTime: initial,
      helpText: 'Choose time',
    );
    if (selected != null) {
      controller.text =
          '${selected.hour.toString().padLeft(2, '0')}:${selected.minute.toString().padLeft(2, '0')}';
    }
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Sign out?'),
        content: const Text(
          'You will need to sign in again to view your membership and training data on this device.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Stay signed in'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Sign out'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await session.logout();
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
    required this.isActive,
    required this.onView,
  });

  final String name;
  final String email;
  final bool isActive;
  final Future<void> Function() onView;

  @override
  Widget build(BuildContext context) {
    final initials = name
        .trim()
        .split(' ')
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
                  email,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: isActive
                        ? AppColors.success.withValues(alpha: 0.10)
                        : AppColors.warning.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    isActive
                        ? 'Active member account'
                        : 'Account access limited',
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: isActive ? AppColors.success : AppColors.warning,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          InkWell(
            onTap: onView,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.stroke),
              ),
              child: Text(
                'View',
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

class _SettingsGroup extends StatelessWidget {
  const _SettingsGroup({
    required this.title,
    required this.children,
    this.subtitle,
  });

  final String title;
  final List<Widget> children;
  final String? subtitle;

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
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(
              subtitle!,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                height: 1.35,
              ),
            ),
          ],
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
    required this.subtitle,
    required this.onPressed,
    this.destructive = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Future<void> Function() onPressed;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        constraints: const BoxConstraints(minHeight: 62),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            _RowIcon(icon: icon, destructive: destructive),
            const SizedBox(width: 15),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: destructive
                          ? AppColors.error
                          : AppColors.textPrimary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      height: 1.25,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              size: 20,
              color: destructive ? AppColors.error : AppColors.textMuted,
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
  const _RowIcon({required this.icon, this.destructive = false});

  final IconData icon;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 30,
      width: 30,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: destructive
            ? AppColors.error.withValues(alpha: 0.08)
            : AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Icon(
        icon,
        size: 16,
        color: destructive ? AppColors.error : AppColors.primaryBright,
      ),
    );
  }
}

String _settingsError(Object exception) {
  final message = exception.toString().replaceFirst('Exception: ', '').trim();
  return message.isEmpty ? 'Something went wrong. Please try again.' : message;
}
