import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import '../../core/config.dart';
import '../auth/session_controller.dart';
import 'notification_preferences_sheet.dart';
import 'trainer_profile_screen.dart';
import 'trainer_repository.dart';

class TrainerSettingsScreen extends StatelessWidget {
  const TrainerSettingsScreen({super.key, required this.repository});

  final TrainerRepository repository;

  @override
  Widget build(BuildContext context) {
    final session = context.watch<TrainerSessionController>();
    final user = session.user;
    final name = user?.name.trim().isNotEmpty == true ? user!.name : 'Trainer';
    final email = user?.email.trim().isNotEmpty == true
        ? user!.email
        : 'trainer account';
    final role = user?.activeRole.trim().isNotEmpty == true
        ? user!.activeRole
        : 'trainer';
    final baseUri = Uri.tryParse(TrainerConfig.apiBaseUrl);
    final webBase = baseUri == null
        ? null
        : Uri(
            scheme: baseUri.scheme,
            host: baseUri.host,
            port: baseUri.hasPort ? baseUri.port : null,
          ).toString();

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
                subtitle: 'Profile, access, notifications, and preferences.',
              ),
              const SizedBox(height: AppSpacing.md),
              _RevealSettings(
                child: _ProfileHeader(
                  name: name,
                  email: email,
                  role: role,
                  isActive: user?.isActive == true,
                  onEdit: () => _openProfile(context),
                ),
              ),
              const SizedBox(height: 15),
              _RevealSettings(
                delay: const Duration(milliseconds: 70),
                child: Row(
                  children: [
                    Expanded(
                      child: _StatusCell(
                        title: _titleCase(role),
                        subtitle: 'Role',
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _StatusCell(
                        title: user?.isActive == true ? 'Active' : 'Limited',
                        subtitle: 'Session',
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _StatusCell(
                        title: '${user?.permissions.length ?? 0}',
                        subtitle: 'Access',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              _RevealSettings(
                delay: const Duration(milliseconds: 110),
                child: _SettingsGroup(
                  title: 'Account',
                  children: [
                    _SettingsRow(
                      icon: Icons.person_outline_rounded,
                      title: 'Trainer profile',
                      onPressed: () => _openProfile(context),
                    ),
                    _SettingsRow(
                      icon: Icons.verified_user_outlined,
                      title: 'Role and access',
                      onPressed: () => _showAccess(context, session),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _RevealSettings(
                delay: const Duration(milliseconds: 150),
                child: _SettingsGroup(
                  title: 'Notifications',
                  children: [
                    _SettingsRow(
                      icon: Icons.notifications_none_rounded,
                      title: 'Notification preferences',
                      onPressed: () => _openNotificationPreferences(context),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _RevealSettings(
                delay: const Duration(milliseconds: 190),
                child: _SettingsGroup(
                  title: 'Support and legal',
                  children: [
                    _SettingsRow(
                      icon: Icons.support_agent_rounded,
                      title: 'Contact us',
                      onPressed: () => _openLink(
                        context,
                        _webUrl(webBase, '/contact'),
                        'Contact page',
                      ),
                    ),
                    _SettingsRow(
                      icon: Icons.privacy_tip_outlined,
                      title: 'Privacy policy',
                      onPressed: () => _openLink(
                        context,
                        _webUrl(webBase, '/privacy-policy'),
                        'Privacy policy',
                      ),
                    ),
                    _SettingsRow(
                      icon: Icons.gavel_rounded,
                      title: 'Terms',
                      onPressed: () => _openLink(
                        context,
                        _webUrl(webBase, '/terms'),
                        'Terms',
                      ),
                    ),
                    _SettingsRow(
                      icon: Icons.person_remove_outlined,
                      title: 'Delete account',
                      destructive: true,
                      onPressed: () => _openLink(
                        context,
                        _webUrl(webBase, '/account-deletion?app=trainer'),
                        'Account deletion page',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _RevealSettings(
                delay: const Duration(milliseconds: 230),
                child: _SessionCard(
                  onLogout: () => _confirmLogout(context, session),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _webUrl(String? webBase, String path) =>
      webBase == null ? path : '$webBase$path';

  Future<void> _openProfile(BuildContext context) async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => TrainerProfileScreen(repository: repository),
      ),
    );
  }

  Future<void> _openNotificationPreferences(BuildContext context) async {
    await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => TrainerNotificationPreferencesSheet(
        onLoad: repository.fetchNotificationPreferences,
        onSave: repository.updateNotificationPreferences,
      ),
    );
  }

  Future<void> _showAccess(
    BuildContext context,
    TrainerSessionController session,
  ) {
    final user = session.user;
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        margin: const EdgeInsets.all(12),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: AppColors.stroke),
        ),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.strokeStrong,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Text(
                'Role and access',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                'Your current trainer roles and enabled capabilities.',
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
              const SizedBox(height: 18),
              _AccessBlock(
                label: 'Active role',
                values: [_titleCase(user?.activeRole ?? 'trainer')],
              ),
              const SizedBox(height: 12),
              _AccessBlock(
                label: 'Roles',
                values: (user?.roles ?? const <String>[])
                    .map(_titleCase)
                    .toList(),
              ),
              const SizedBox(height: 12),
              _AccessBlock(
                label: 'Permissions',
                values: user?.permissions ?? const <String>[],
              ),
            ],
          ),
        ),
      ),
    );
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
    if (context.mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('$label link copied.')));
    }
  }

  Future<void> _confirmLogout(
    BuildContext context,
    TrainerSessionController session,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Sign out?'),
        content: const Text(
          'Your trainer session will be removed from this device.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Sign out'),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) {
      return;
    }
    await session.logout();
    if (context.mounted) {
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
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
        _SquareButton(
          icon: Icons.arrow_back_rounded,
          onTap: () => Navigator.of(context).maybePop(),
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
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
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

class _SquareButton extends StatelessWidget {
  const _SquareButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.stroke),
          ),
          child: Icon(icon, color: AppColors.textPrimary, size: 20),
        ),
      ),
    );
  }
}

class _RevealSettings extends StatelessWidget {
  const _RevealSettings({required this.child, this.delay = Duration.zero});

  final Widget child;
  final Duration delay;

  @override
  Widget build(BuildContext context) {
    return RevealOnBuild(delay: delay, child: child);
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
              initials.isEmpty ? 'T' : initials,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: AppColors.primary,
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
                  '${_titleCase(role)} • ${isActive ? 'Active account' : email}',
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
          TextButton(onPressed: onEdit, child: const Text('Edit')),
        ],
      ),
    );
  }
}

class _StatusCell extends StatelessWidget {
  const _StatusCell({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
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
          const SizedBox(height: 8),
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
    this.destructive = false,
  });

  final IconData icon;
  final String title;
  final Future<void> Function() onPressed;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final color = destructive ? AppColors.error : AppColors.textPrimary;
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(16),
      child: SizedBox(
        height: 50,
        child: Row(
          children: [
            Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                color: destructive
                    ? AppColors.error.withValues(alpha: 0.07)
                    : AppColors.surfaceSoft,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color: destructive
                      ? AppColors.error.withValues(alpha: 0.12)
                      : AppColors.stroke,
                ),
              ),
              child: Icon(
                icon,
                size: 16,
                color: destructive ? AppColors.error : AppColors.primary,
              ),
            ),
            const SizedBox(width: 13),
            Expanded(
              child: Text(
                title,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: color,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              size: 19,
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
      padding: const EdgeInsets.all(16),
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
          const SizedBox(height: 6),
          Text(
            'Sign out securely from this trainer device.',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 14),
          OutlinedButton.icon(
            onPressed: onLogout,
            icon: const Icon(Icons.logout_rounded, size: 18),
            label: const Text('Sign out'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(48),
            ),
          ),
        ],
      ),
    );
  }
}

class _AccessBlock extends StatelessWidget {
  const _AccessBlock({required this.label, required this.values});

  final String label;
  final List<String> values;

  @override
  Widget build(BuildContext context) {
    final visible = values.where((value) => value.trim().isNotEmpty).toList();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 9),
          if (visible.isEmpty)
            Text(
              'No entries',
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.textMuted),
            )
          else
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: visible
                  .map(
                    (value) => Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(99),
                        border: Border.all(color: AppColors.stroke),
                      ),
                      child: Text(
                        value,
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: AppColors.textSecondary,
                          fontWeight: FontWeight.w700,
                        ),
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

String _titleCase(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .where((part) => part.isNotEmpty)
    .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
    .join(' ');
