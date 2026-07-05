import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

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
    final permissionCount = user?.permissions.length ?? 0;
    final baseUri = Uri.tryParse(TrainerConfig.apiBaseUrl);
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

    return Scaffold(
      backgroundColor: _FitColor.white,
      appBar: AppBar(
        backgroundColor: _FitColor.white,
        centerTitle: true,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, color: _FitColor.black),
          onPressed: () => Navigator.of(context).maybePop(),
        ),
        title: Text(
          'Settings',
          style: TextStyle(
            color: _FitColor.black,
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: _FitIconButton(
              icon: Icons.refresh_rounded,
              onTap: () => ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Settings are already synced.')),
              ),
            ),
          ),
        ],
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(25, 15, 25, 30),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _AnimatedSection(
                child: _ProfileHeader(
                  name: name,
                  email: email,
                  role: role,
                  isActive: user?.isActive == true,
                  onEdit: () => _openProfile(context),
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
                    Expanded(
                      child: _TitleSubtitleCell(
                        title: '$permissionCount',
                        subtitle: 'Permissions',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              _AnimatedSection(
                delay: const Duration(milliseconds: 100),
                child: _AccessSummaryCard(
                  activeRole: role,
                  roles: user?.roles ?? const <String>[],
                  permissions: user?.permissions ?? const <String>[],
                  isActive: user?.isActive == true,
                  onOpen: () => _showAccessSheet(context, session),
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
                      title: 'Trainer Profile',
                      onPressed: () => _openProfile(context),
                    ),
                    _SettingsRow(
                      icon: Icons.verified_user_outlined,
                      title: 'Role & Access',
                      onPressed: () => _showAccessSheet(context, session),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _AnimatedSection(
                delay: const Duration(milliseconds: 170),
                child: _SettingsGroup(
                  title: 'Notification',
                  children: [
                    _NotificationPreferenceRow(
                      onPressed: () => _openNotificationPreferences(context),
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
                      onPressed: () => _copyLink(
                        context,
                        contactUrl,
                        'Contact page link copied.',
                      ),
                    ),
                    _SettingsRow(
                      icon: Icons.privacy_tip_outlined,
                      title: 'Privacy Policy',
                      onPressed: () => _copyLink(
                        context,
                        privacyUrl,
                        'Privacy policy link copied.',
                      ),
                    ),
                    _SettingsRow(
                      icon: Icons.gavel_rounded,
                      title: 'Terms',
                      onPressed: () =>
                          _copyLink(context, termsUrl, 'Terms link copied.'),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _AnimatedSection(
                delay: const Duration(milliseconds: 270),
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

  Future<void> _openProfile(BuildContext context) async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (context) => TrainerProfileScreen(repository: repository),
      ),
    );
  }

  Future<void> _openNotificationPreferences(BuildContext context) async {
    await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => TrainerNotificationPreferencesSheet(
        onLoad: repository.fetchNotificationPreferences,
        onSave: repository.updateNotificationPreferences,
      ),
    );
  }

  Future<void> _showAccessSheet(
    BuildContext context,
    TrainerSessionController session,
  ) async {
    final user = session.user;
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: _AccessDetailSheet(
            activeRole: user?.activeRole ?? 'trainer',
            roles: user?.roles ?? const <String>[],
            permissions: user?.permissions ?? const <String>[],
            isActive: user?.isActive == true,
          ),
        ),
      ),
    );
  }

  Future<void> _copyLink(
    BuildContext context,
    String value,
    String message,
  ) async {
    await Clipboard.setData(ClipboardData(text: value));
    if (!context.mounted) {
      return;
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _confirmLogout(
    BuildContext context,
    TrainerSessionController session,
  ) async {
    final confirmed =
        await showModalBottomSheet<bool>(
          context: context,
          backgroundColor: Colors.transparent,
          builder: (context) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: _LogoutConfirmCard(
                onCancel: () => Navigator.of(context).pop(false),
                onLogout: () => Navigator.of(context).pop(true),
              ),
            ),
          ),
        ) ??
        false;
    if (!confirmed || !context.mounted) {
      return;
    }
    await session.logout();
    if (!context.mounted) {
      return;
    }
    Navigator.of(context).popUntil((route) => route.isFirst);
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

    return Row(
      children: [
        Container(
          width: 50,
          height: 50,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: _FitColor.primaryG),
            borderRadius: BorderRadius.circular(30),
          ),
          child: Text(
            initials.isEmpty ? 'T' : initials,
            style: TextStyle(
              color: _FitColor.white,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        const SizedBox(width: 15),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: _FitColor.black,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                '$role • ${isActive ? 'Active account' : email}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: _FitColor.gray, fontSize: 12),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        SizedBox(
          width: 70,
          height: 25,
          child: _RoundButton(
            title: 'Edit',
            fontSize: 12,
            fontWeight: FontWeight.w400,
            onPressed: onEdit,
          ),
        ),
      ],
    );
  }
}

class _TitleSubtitleCell extends StatelessWidget {
  const _TitleSubtitleCell({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
      decoration: BoxDecoration(
        color: _FitColor.white,
        borderRadius: BorderRadius.circular(15),
        boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 2)],
      ),
      child: Column(
        children: [
          ShaderMask(
            blendMode: BlendMode.srcIn,
            shaderCallback: (bounds) => LinearGradient(
              colors: _FitColor.primaryG,
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
            ).createShader(Rect.fromLTWH(0, 0, bounds.width, bounds.height)),
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: _FitColor.white.withValues(alpha: 0.7),
                fontWeight: FontWeight.w600,
                fontSize: 13,
              ),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(color: _FitColor.gray, fontSize: 12),
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
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 15),
      decoration: BoxDecoration(
        color: _FitColor.white,
        borderRadius: BorderRadius.circular(15),
        boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 2)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
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

class _AccessSummaryCard extends StatelessWidget {
  const _AccessSummaryCard({
    required this.activeRole,
    required this.roles,
    required this.permissions,
    required this.isActive,
    required this.onOpen,
  });

  final String activeRole;
  final List<String> roles;
  final List<String> permissions;
  final bool isActive;
  final Future<void> Function() onOpen;

  @override
  Widget build(BuildContext context) {
    final visibleRoles = roles.isEmpty ? <String>[activeRole] : roles;
    final capabilityCount = _accessCapabilities(permissions).length;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: _FitColor.primaryG),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: _FitColor.primaryColor1.withValues(alpha: 0.28),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.22),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.22),
                  ),
                ),
                child: const Icon(
                  Icons.verified_user_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Role & Access',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.82),
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${_accessTitle(activeRole)} access',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              _AccessStatusPill(
                label: isActive ? 'Active' : 'Limited',
                inverted: true,
              ),
            ],
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: visibleRoles
                .map(
                  (role) =>
                      _AccessChip(label: _accessTitle(role), inverted: true),
                )
                .toList(),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _AccessMetric(
                  value: '${permissions.length}',
                  label: 'Permissions',
                  inverted: true,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _AccessMetric(
                  value: '$capabilityCount',
                  label: 'Access areas',
                  inverted: true,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _RoundButton(
            title: 'View access details',
            type: _RoundButtonType.secondaryGradient,
            fontSize: 13,
            onPressed: onOpen,
          ),
        ],
      ),
    );
  }
}

class _AccessDetailSheet extends StatelessWidget {
  const _AccessDetailSheet({
    required this.activeRole,
    required this.roles,
    required this.permissions,
    required this.isActive,
  });

  final String activeRole;
  final List<String> roles;
  final List<String> permissions;
  final bool isActive;

  @override
  Widget build(BuildContext context) {
    final visibleRoles = roles.isEmpty ? <String>[activeRole] : roles;
    final capabilities = _accessCapabilities(permissions);
    final groups = _groupPermissions(permissions);

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.86,
      ),
      decoration: BoxDecoration(
        color: _FitColor.white,
        borderRadius: BorderRadius.circular(28),
      ),
      clipBehavior: Clip.antiAlias,
      child: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 22),
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: _FitColor.primaryG),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 52,
                        height: 52,
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.22),
                          borderRadius: BorderRadius.circular(18),
                        ),
                        child: const Icon(
                          Icons.admin_panel_settings_rounded,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 13),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Role & Access',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Active role: ${_accessTitle(activeRole)}',
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.78),
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.of(context).pop(),
                        icon: const Icon(Icons.close_rounded),
                        color: Colors.white,
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: _AccessMetric(
                          value: '${permissions.length}',
                          label: 'Permissions',
                          inverted: true,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _AccessMetric(
                          value: isActive ? 'Active' : 'Limited',
                          label: 'Account',
                          inverted: true,
                        ),
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
                  _AccessBlock(
                    title: 'Assigned roles',
                    subtitle:
                        'These roles come from the backend session and control which app areas are available.',
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: visibleRoles
                          .map((role) => _AccessChip(label: _accessTitle(role)))
                          .toList(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  _AccessBlock(
                    title: 'What you can do',
                    subtitle: 'Readable access summary for this account.',
                    child: Column(
                      children: capabilities
                          .map(
                            (item) => Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: _AccessCapabilityTile(
                                icon: item.icon,
                                title: item.title,
                                subtitle: item.subtitle,
                                enabled: item.enabled,
                              ),
                            ),
                          )
                          .toList(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  _AccessBlock(
                    title: 'Permission groups',
                    subtitle:
                        'Detailed permission keys grouped by feature area for debugging and support.',
                    child: Column(
                      children: groups.entries
                          .map(
                            (entry) => _PermissionGroupTile(
                              title: _accessTitle(entry.key),
                              permissions: entry.value,
                            ),
                          )
                          .toList(),
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

class _AccessBlock extends StatelessWidget {
  const _AccessBlock({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  final String title;
  final String subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _FitColor.lightGray,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(
              color: _FitColor.black,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            style: TextStyle(
              color: _FitColor.gray,
              fontSize: 11,
              height: 1.35,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

class _AccessMetric extends StatelessWidget {
  const _AccessMetric({
    required this.value,
    required this.label,
    this.inverted = false,
  });

  final String value;
  final String label;
  final bool inverted;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: inverted
            ? Colors.white.withValues(alpha: 0.18)
            : _FitColor.lightGray,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: inverted
              ? Colors.white.withValues(alpha: 0.22)
              : Colors.transparent,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: inverted ? Colors.white : _FitColor.black,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: inverted
                  ? Colors.white.withValues(alpha: 0.76)
                  : _FitColor.gray,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _AccessChip extends StatelessWidget {
  const _AccessChip({required this.label, this.inverted = false});

  final String label;
  final bool inverted;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
      decoration: BoxDecoration(
        color: inverted
            ? Colors.white.withValues(alpha: 0.18)
            : _FitColor.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: inverted
              ? Colors.white.withValues(alpha: 0.25)
              : _FitColor.primaryColor2.withValues(alpha: 0.30),
        ),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: inverted ? Colors.white : _FitColor.black,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _AccessStatusPill extends StatelessWidget {
  const _AccessStatusPill({required this.label, this.inverted = false});

  final String label;
  final bool inverted;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: inverted
            ? Colors.white.withValues(alpha: 0.20)
            : _FitColor.lightGray,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: inverted ? Colors.white : _FitColor.black,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _AccessCapabilityTile extends StatelessWidget {
  const _AccessCapabilityTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.enabled,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final color = enabled ? _FitColor.primaryColor1 : _FitColor.gray;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _FitColor.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: color, size: 19),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: _FitColor.black,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: TextStyle(
                    color: _FitColor.gray,
                    fontSize: 10,
                    height: 1.3,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          Icon(
            enabled ? Icons.check_circle_rounded : Icons.lock_outline_rounded,
            color: color,
            size: 20,
          ),
        ],
      ),
    );
  }
}

class _PermissionGroupTile extends StatelessWidget {
  const _PermissionGroupTile({required this.title, required this.permissions});

  final String title;
  final List<String> permissions;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: _FitColor.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 12),
        childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        collapsedShape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
        ),
        title: Text(
          title,
          style: TextStyle(
            color: _FitColor.black,
            fontSize: 12,
            fontWeight: FontWeight.w800,
          ),
        ),
        subtitle: Text(
          '${permissions.length} permission${permissions.length == 1 ? '' : 's'}',
          style: TextStyle(color: _FitColor.gray, fontSize: 10),
        ),
        children: [
          Align(
            alignment: Alignment.centerLeft,
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: permissions
                  .map((permission) => _PermissionToken(label: permission))
                  .toList(),
            ),
          ),
        ],
      ),
    );
  }
}

class _PermissionToken extends StatelessWidget {
  const _PermissionToken({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
      decoration: BoxDecoration(
        color: _FitColor.primaryColor2.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: _FitColor.black,
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
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
      child: SizedBox(
        height: 42,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            _RowIcon(icon: icon),
            const SizedBox(width: 15),
            Expanded(
              child: Text(
                title,
                style: TextStyle(color: _FitColor.black, fontSize: 12),
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              size: 18,
              color: _FitColor.gray.withValues(alpha: 0.65),
            ),
          ],
        ),
      ),
    );
  }
}

class _NotificationPreferenceRow extends StatelessWidget {
  const _NotificationPreferenceRow({required this.onPressed});

  final Future<void> Function() onPressed;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      child: SizedBox(
        height: 42,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            const _RowIcon(icon: Icons.notifications_none_rounded),
            const SizedBox(width: 15),
            Expanded(
              child: Text(
                'Pop-up Notification',
                style: TextStyle(color: _FitColor.black, fontSize: 12),
              ),
            ),
            _GradientSwitchPreview(onTap: onPressed),
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
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 15),
      decoration: BoxDecoration(
        color: _FitColor.white,
        borderRadius: BorderRadius.circular(15),
        boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 2)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Session',
            style: TextStyle(
              color: _FitColor.black,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Sign out securely. Your stored trainer token will be cleared.',
            style: TextStyle(color: _FitColor.gray, fontSize: 12),
          ),
          const SizedBox(height: 14),
          _RoundButton(
            title: 'Logout',
            type: _RoundButtonType.secondaryGradient,
            onPressed: onLogout,
          ),
        ],
      ),
    );
  }
}

class _FitIconButton extends StatelessWidget {
  const _FitIconButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        height: 40,
        width: 40,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: _FitColor.lightGray,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(icon, size: 20, color: _FitColor.black),
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
      height: 24,
      width: 24,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: _FitColor.lightGray,
        borderRadius: BorderRadius.circular(8),
      ),
      child: ShaderMask(
        blendMode: BlendMode.srcIn,
        shaderCallback: (bounds) => LinearGradient(
          colors: _FitColor.primaryG,
        ).createShader(Rect.fromLTWH(0, 0, bounds.width, bounds.height)),
        child: Icon(icon, size: 15),
      ),
    );
  }
}

class _GradientSwitchPreview extends StatelessWidget {
  const _GradientSwitchPreview({required this.onTap});

  final Future<void> Function() onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(50),
      child: SizedBox(
        width: 52,
        height: 30,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Positioned(
              left: 4,
              right: 4,
              height: 30,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(colors: _FitColor.secondaryG),
                  borderRadius: BorderRadius.circular(50),
                ),
              ),
            ),
            Positioned(
              right: 4,
              child: Container(
                width: 26,
                height: 26,
                decoration: BoxDecoration(
                  color: _FitColor.white,
                  borderRadius: BorderRadius.circular(50),
                  boxShadow: const [
                    BoxShadow(
                      color: Colors.black38,
                      spreadRadius: 0.05,
                      blurRadius: 1.1,
                      offset: Offset(0, 0.8),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LogoutConfirmCard extends StatelessWidget {
  const _LogoutConfirmCard({required this.onCancel, required this.onLogout});

  final VoidCallback onCancel;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: _FitColor.white,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Logout',
            style: TextStyle(
              color: _FitColor.black,
              fontSize: 18,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            'This signs you out of the Trainer App on this device.',
            style: TextStyle(color: _FitColor.gray, fontSize: 13),
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: onCancel,
                  child: const Text('Cancel'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _RoundButton(
                  title: 'Logout',
                  type: _RoundButtonType.secondaryGradient,
                  onPressed: onLogout,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AccessCapability {
  const _AccessCapability({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.enabled,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool enabled;
}

List<_AccessCapability> _accessCapabilities(List<String> permissions) {
  bool hasAny(Iterable<String> values) {
    return values.any(permissions.contains);
  }

  return [
    _AccessCapability(
      icon: Icons.person_outline_rounded,
      title: 'Trainer profile',
      subtitle: 'View and update your trainer bio, photo, and credentials.',
      enabled: hasAny(['trainer.view', 'trainer.self.manage']),
    ),
    _AccessCapability(
      icon: Icons.groups_2_rounded,
      title: 'Assigned members',
      subtitle: 'View assigned clients, attendance, progress, and profiles.',
      enabled: hasAny(['member.view', 'trainer.view']),
    ),
    _AccessCapability(
      icon: Icons.fitness_center_rounded,
      title: 'Workout management',
      subtitle: 'Create library workouts and assign plans to members.',
      enabled: hasAny([
        'workout_plan.manage',
        'workout_template.manage',
        'exercise.manage',
      ]),
    ),
    _AccessCapability(
      icon: Icons.edit_note_rounded,
      title: 'Follow-ups and notes',
      subtitle: 'Create trainer notes and manage follow-up actions.',
      enabled: hasAny(['trainer.self.manage', 'progress.manage']),
    ),
    _AccessCapability(
      icon: Icons.notifications_active_rounded,
      title: 'Notifications',
      subtitle: 'Receive and manage trainer/member notifications.',
      enabled: hasAny(['notification.manage', 'trainer.view']),
    ),
  ];
}

Map<String, List<String>> _groupPermissions(List<String> permissions) {
  final groups = <String, List<String>>{};
  for (final permission in permissions) {
    final key = permission.split('.').first.trim();
    final group = key.isEmpty ? 'other' : key;
    groups.putIfAbsent(group, () => <String>[]).add(permission);
  }
  if (groups.isEmpty) {
    groups['none'] = <String>['No permission keys returned'];
  }
  final sortedEntries = groups.entries.toList()
    ..sort((a, b) => a.key.compareTo(b.key));
  return Map<String, List<String>>.fromEntries(sortedEntries);
}

String _accessTitle(String value) {
  final normalized = value.trim().replaceAll('-', '_');
  if (normalized.isEmpty) {
    return 'Unknown';
  }
  return normalized
      .split(RegExp(r'[_.\s]+'))
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

enum _RoundButtonType { primaryGradient, secondaryGradient }

class _RoundButton extends StatelessWidget {
  const _RoundButton({
    required this.title,
    required this.onPressed,
    this.type = _RoundButtonType.primaryGradient,
    this.fontSize = 16,
    this.fontWeight = FontWeight.w700,
  });

  final String title;
  final _RoundButtonType type;
  final VoidCallback onPressed;
  final double fontSize;
  final FontWeight fontWeight;

  @override
  Widget build(BuildContext context) {
    final gradient = type == _RoundButtonType.secondaryGradient
        ? _FitColor.secondaryG
        : _FitColor.primaryG;

    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: gradient),
        borderRadius: BorderRadius.circular(25),
        boxShadow: const [
          BoxShadow(
            color: Colors.black26,
            blurRadius: 0.5,
            offset: Offset(0, 0.5),
          ),
        ],
      ),
      child: MaterialButton(
        onPressed: onPressed,
        height: 50,
        minWidth: double.maxFinite,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(25)),
        color: Colors.transparent,
        textColor: _FitColor.white,
        child: Text(
          title,
          style: TextStyle(
            color: _FitColor.white,
            fontSize: fontSize,
            fontWeight: fontWeight,
          ),
        ),
      ),
    );
  }
}

class _FitColor {
  static Color get primaryColor1 => const Color(0xff92A3FD);
  static Color get primaryColor2 => const Color(0xff9DCEFF);
  static Color get secondaryColor1 => const Color(0xffC58BF2);
  static Color get secondaryColor2 => const Color(0xffEEA4CE);
  static List<Color> get primaryG => [primaryColor2, primaryColor1];
  static List<Color> get secondaryG => [secondaryColor2, secondaryColor1];
  static Color get black => const Color(0xff1D1617);
  static Color get gray => const Color(0xff786F72);
  static Color get white => Colors.white;
  static Color get lightGray => const Color(0xffF7F8F8);
}
