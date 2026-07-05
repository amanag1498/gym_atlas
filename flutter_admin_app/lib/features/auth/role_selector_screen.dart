import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/widgets/common_widgets.dart';
import 'session_controller.dart';

class RoleSelectorScreen extends StatelessWidget {
  const RoleSelectorScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionController>();
    final user = session.user;

    if (user == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => context.go('/login'));
      return const SizedBox.shrink();
    }

    return AppGradientScaffold(
      appBar: PremiumAppBar(
        title: 'Select Active Role',
        subtitle: 'Switch the backend-controlled workspace for this session.',
        actions: [
          TextButton(
            onPressed: () => context.read<SessionController>().logout(),
            child: const Text('Logout'),
          ),
        ],
      ),
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            'Choose the workspace you want to use right now.',
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          ...user.adminRoles.map(
            (role) => SectionCard(
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 10,
                ),
                title: Text(role.replaceAll('_', ' ').toUpperCase()),
                subtitle: Text(_descriptionFor(role)),
                trailing: user.activeRole == role
                    ? const Icon(Icons.check_circle)
                    : null,
                onTap: () async {
                  await context.read<SessionController>().switchRole(role);
                  if (context.mounted) {
                    context.go('/home');
                  }
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _descriptionFor(String role) {
    switch (role) {
      case 'platform_admin':
        return 'Platform-wide dashboards, gym approvals, facilities, banners, and listings.';
      case 'gym_owner':
        return 'Gym-wide operations, branches, staffing, memberships, billing, attendance, and announcements.';
      case 'branch_manager':
        return 'Branch-level operations, trainers, members, collections, and attendance.';
      case 'gym_staff':
        return 'Operational access for attendance, member support, and assigned workflows.';
      default:
        return 'Role-based access to admin capabilities.';
    }
  }
}
