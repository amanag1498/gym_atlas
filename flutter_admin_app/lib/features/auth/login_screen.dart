import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/widgets/common_widgets.dart';
import 'session_controller.dart';

class LoginScreen extends StatelessWidget {
  const LoginScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionController>();

    if (session.isAuthenticated) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        context.go('/home');
      });
    }

    return AppGradientScaffold(
      child: AuthPanel(
        panelLabel: 'Admin Panel',
        title: 'Connected Gym',
        description:
            'A premium multi-role control center for platform governance, gym operations, billing, attendance, and live coordination.',
        highlights: const [
          'Platform approvals, featured listings, and facilities governance',
          'Gym operations with members, trainers, staff, memberships, and collections',
          'Realtime announcements, notifications, and activity visibility',
        ],
        error: session.error,
        buttonLabel: 'Continue with Google',
        loading: session.loggingIn,
        onPressed: session.loggingIn
            ? null
            : () => context.read<SessionController>().login(),
      ),
    );
  }
}
