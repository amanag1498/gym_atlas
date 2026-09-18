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

class TrainerSettingsScreen extends StatelessWidget {
  const TrainerSettingsScreen({super.key, required this.onEditProfile});

  final Future<void> Function() onEditProfile;

  @override
  Widget build(BuildContext context) {
    final session = context.watch<TrainerSessionController>();
    final user = session.user;
    final name = user?.name.trim().isNotEmpty == true ? user!.name : 'Trainer';
    final email = user?.email.trim().isNotEmpty == true
        ? user!.email
        : 'trainer account';
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
                subtitle:
                    'Profile, help, legal information, and account controls.',
              ),
              const SizedBox(height: AppSpacing.md),
              _RevealSettings(
                child: _ProfileHeader(
                  name: name,
                  email: email,
                  isActive: user?.isActive == true,
                  onEdit: onEditProfile,
                ),
              ),
              const SizedBox(height: 15),
              _RevealSettings(
                delay: const Duration(milliseconds: 50),
                child: Row(
                  children: [
                    const Expanded(
                      child: _TitleSubtitleCell(
                        title: 'Trainer',
                        subtitle: 'Account',
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
              _RevealSettings(
                delay: const Duration(milliseconds: 90),
                child: _SettingsGroup(
                  title: 'Support and legal',
                  children: [
                    _SettingsRow(
                      icon: Icons.help_outline_rounded,
                      title: 'Help & FAQ',
                      onPressed: () => _openLink(
                        context,
                        _webUrl(webBase, '/faq'),
                        'Help and FAQ page',
                      ),
                    ),
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
                delay: const Duration(milliseconds: 130),
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

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({
    required this.name,
    required this.email,
    required this.isActive,
    required this.onEdit,
  });

  final String name;
  final String email;
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
                  isActive ? 'Trainer account active' : email,
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

class _RevealSettings extends StatelessWidget {
  const _RevealSettings({required this.child, this.delay = Duration.zero});

  final Widget child;
  final Duration delay;

  @override
  Widget build(BuildContext context) {
    return RevealOnBuild(delay: delay, child: child);
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
