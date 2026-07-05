import 'package:flutter/material.dart';

import 'empty_state.dart';
import 'gradient_button.dart';

class ErrorState extends StatelessWidget {
  const ErrorState({
    super.key,
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return EmptyState(
      title: 'Something needs attention',
      message: message,
      icon: Icons.warning_amber_rounded,
      action: GradientButton(
        label: 'Retry',
        icon: Icons.refresh_rounded,
        onPressed: onRetry,
      ),
    );
  }
}
