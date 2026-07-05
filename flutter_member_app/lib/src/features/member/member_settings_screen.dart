import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/config.dart';
import '../auth/session_controller.dart';
import 'member_repository.dart';
import 'notification_preferences_sheet.dart';

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
    final privacyUrl =
        webBase == null ? '/privacy-policy' : '$webBase/privacy-policy';
    final termsUrl = webBase == null ? '/terms' : '$webBase/terms';
    final user = session.user;
    final name = user?.name.trim().isNotEmpty == true ? user!.name : 'Member';
    final email = user?.email.trim().isNotEmpty == true
        ? user!.email
        : 'member account';
    final role = user?.activeRole.trim().isNotEmpty == true
        ? user!.activeRole
        : 'member';

    return Scaffold(
      backgroundColor: _FitColor.white,
      appBar: AppBar(
        backgroundColor: _FitColor.white,
        centerTitle: true,
        elevation: 0,
        leadingWidth: 0,
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
              icon: Icons.more_horiz_rounded,
              onTap: () {},
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
                      onPressed: () => _openPreferences(context),
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
                  onLogout: () => session.logout(),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openPreferences(BuildContext context) async {
    final changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => MemberNotificationPreferencesSheet(
        onLoad: repository.fetchNotificationPreferences,
        onSave: repository.updateNotificationPreferences,
      ),
    );

    if (changed == true) {
      await onPreferencesChanged();
    }
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
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _AnimatedSection extends StatelessWidget {
  const _AnimatedSection({
    required this.child,
    this.delay = Duration.zero,
  });

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
            initials.isEmpty ? 'M' : initials,
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
                style: TextStyle(
                  color: _FitColor.gray,
                  fontSize: 12,
                ),
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
  const _TitleSubtitleCell({
    required this.title,
    required this.subtitle,
  });

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
            style: TextStyle(
              color: _FitColor.gray,
              fontSize: 12,
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
  });

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
                style: TextStyle(
                  color: _FitColor.black,
                  fontSize: 12,
                ),
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
  const _NotificationPreferenceRow({
    required this.onPressed,
  });

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
                style: TextStyle(
                  color: _FitColor.black,
                  fontSize: 12,
                ),
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
  const _SessionCard({
    required this.onLogout,
  });

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
            'Sign out securely. Your stored member token will be cleared.',
            style: TextStyle(
              color: _FitColor.gray,
              fontSize: 12,
            ),
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
  const _FitIconButton({
    required this.icon,
    required this.onTap,
  });

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
  const _RowIcon({
    required this.icon,
  });

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
  const _GradientSwitchPreview({
    required this.onTap,
  });

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
