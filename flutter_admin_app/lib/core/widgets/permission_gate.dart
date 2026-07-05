import 'package:flutter/material.dart';

import 'empty_state.dart';

class PermissionGate extends StatelessWidget {
  const PermissionGate({
    super.key,
    required this.allowed,
    required this.child,
    this.permissionLabel,
    this.fallbackTitle,
    this.fallbackMessage,
    this.fallbackAction,
  });

  final bool allowed;
  final Widget child;
  final String? permissionLabel;
  final String? fallbackTitle;
  final String? fallbackMessage;
  final Widget? fallbackAction;

  @override
  Widget build(BuildContext context) {
    if (allowed) {
      return child;
    }

    final requiredPermission = permissionLabel?.trim();
    return EmptyState(
      title: fallbackTitle ?? 'Permission required',
      message: fallbackMessage ??
          (requiredPermission == null || requiredPermission.isEmpty
              ? 'You do not have access to this admin section.'
              : 'You need $requiredPermission access to open this admin section.'),
      icon: Icons.lock_outline_rounded,
      action: fallbackAction,
    );
  }
}
