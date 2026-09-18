import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:gym_flutter_core/gym_flutter_core.dart'
    show AtlasBrandLockup, DemoLogoTapTracker;
import 'package:provider/provider.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import 'session_controller.dart';

class TrainerLoginScreen extends StatefulWidget {
  const TrainerLoginScreen({super.key});

  @override
  State<TrainerLoginScreen> createState() => _TrainerLoginScreenState();
}

class _TrainerLoginScreenState extends State<TrainerLoginScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  final DemoLogoTapTracker _demoLogoTapTracker = DemoLogoTapTracker();

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 10),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<TrainerSessionController>();
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: LayoutBuilder(
        builder: (context, constraints) {
          final width = constraints.maxWidth;
          final height = constraints.maxHeight;
          final compact = height < 720 || width < 370;
          final horizontalPadding = width < 360
              ? 20.0
              : width < 430
              ? 24.0
              : 32.0;
          final headlineSize = width < 360
              ? 28.0
              : width < 430
              ? 32.0
              : 34.0;
          final logoSize = compact ? 56.0 : 64.0;

          return Stack(
            children: [
              _AnimatedLoginBackground(controller: _controller),
              SafeArea(
                child: Center(
                  child: SingleChildScrollView(
                    padding: EdgeInsets.fromLTRB(
                      horizontalPadding,
                      compact ? 16 : 28,
                      horizontalPadding,
                      24,
                    ),
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 430),
                      child: SizedBox(
                        height: math.max(
                          constraints.maxHeight - 48,
                          compact ? 560 : 620,
                        ),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            RevealOnBuild(
                              delay: const Duration(milliseconds: 40),
                              offset: const Offset(0, 0.04),
                              child: GestureDetector(
                                behavior: HitTestBehavior.opaque,
                                onTap: _handleBrandTap,
                                child: AtlasBrandLockup(
                                  audience: 'Trainer',
                                  markSize: logoSize,
                                ),
                              ),
                            ),
                            SizedBox(height: compact ? 28 : 36),
                            RevealOnBuild(
                              delay: const Duration(milliseconds: 120),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  Text(
                                    'Sign in to continue',
                                    textAlign: TextAlign.center,
                                    style: theme.textTheme.displaySmall
                                        ?.copyWith(
                                          fontSize: headlineSize,
                                          height: 1.02,
                                          letterSpacing: 0,
                                          fontWeight: FontWeight.w700,
                                        ),
                                  ),
                                  const SizedBox(height: 12),
                                  ConstrainedBox(
                                    constraints: const BoxConstraints(
                                      maxWidth: 280,
                                    ),
                                    child: Text(
                                      'Use your coaching account to open Atlas.',
                                      textAlign: TextAlign.center,
                                      style: theme.textTheme.bodyLarge
                                          ?.copyWith(
                                            color: AppColors.textSecondary,
                                            height: 1.45,
                                          ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            SizedBox(height: compact ? 22 : 28),
                            RevealOnBuild(
                              delay: const Duration(milliseconds: 260),
                              offset: const Offset(0, 0.06),
                              duration: const Duration(milliseconds: 500),
                              child: _LoginPanel(
                                compact: compact,
                                busy: session.busy,
                                error: session.error,
                                onPressed: session.busy
                                    ? null
                                    : () => context
                                          .read<TrainerSessionController>()
                                          .login(),
                                onApplePressed: session.busy
                                    ? null
                                    : () => context
                                          .read<TrainerSessionController>()
                                          .loginWithApple(),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _handleBrandTap() async {
    if (!_demoLogoTapTracker.register()) {
      return;
    }

    final session = context.read<TrainerSessionController>();
    final enabled = await session.fetchDemoLoginEnabled();
    if (!mounted || !enabled) {
      return;
    }

    final email = await _showDemoLoginDialog(
      context: context,
      title: 'Reviewer sign-in',
      helperText: 'Enter the Trainer App demo email configured in Atlas.',
    );
    if (!mounted || email == null || email.trim().isEmpty) {
      return;
    }

    await context.read<TrainerSessionController>().loginWithDemoEmail(email);
  }
}

Future<String?> _showDemoLoginDialog({
  required BuildContext context,
  required String title,
  required String helperText,
}) async {
  final controller = TextEditingController();
  final result = await showDialog<String>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(title),
      content: TextField(
        controller: controller,
        autofocus: true,
        keyboardType: TextInputType.emailAddress,
        textInputAction: TextInputAction.done,
        decoration: InputDecoration(
          labelText: 'Reviewer email',
          helperText: helperText,
        ),
        onSubmitted: (value) => Navigator.of(dialogContext).pop(value),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(dialogContext).pop(controller.text),
          child: const Text('Continue'),
        ),
      ],
    ),
  );
  controller.dispose();
  return result;
}

class _LoginPanel extends StatelessWidget {
  const _LoginPanel({
    required this.compact,
    required this.busy,
    required this.error,
    required this.onPressed,
    required this.onApplePressed,
  });

  final bool compact;
  final bool busy;
  final String? error;
  final VoidCallback? onPressed;
  final VoidCallback? onApplePressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final showApple = !kIsWeb && defaultTargetPlatform == TargetPlatform.iOS;

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        color: Colors.white.withValues(alpha: 0.96),
        border: Border.all(color: Colors.white.withValues(alpha: 0.88)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.10),
            blurRadius: 24,
            offset: const Offset(0, 16),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(24),
        child: Padding(
          padding: EdgeInsets.all(compact ? 16 : 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              if (error != null) ...[
                _LoginError(message: error!),
                SizedBox(height: compact ? 14 : 16),
              ],
              _GoogleSignInButton(
                label: busy ? 'Signing in...' : 'Sign in with Google',
                loading: busy,
                onPressed: onPressed,
              ),
              if (showApple) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    const Expanded(child: Divider()),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Text(
                        'or',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ),
                    const Expanded(child: Divider()),
                  ],
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: onApplePressed,
                    icon: const Icon(Icons.apple, size: 20),
                    label: const Text('Sign in with Apple'),
                    style: FilledButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                      backgroundColor: Colors.black,
                      foregroundColor: Colors.white,
                      disabledBackgroundColor: Colors.black54,
                      disabledForegroundColor: Colors.white70,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _GoogleSignInButton extends StatelessWidget {
  const _GoogleSignInButton({
    required this.label,
    required this.loading,
    required this.onPressed,
  });

  final String label;
  final bool loading;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !loading;

    return SizedBox(
      width: double.infinity,
      height: 54,
      child: Material(
        color: enabled ? Colors.white : const Color(0xFFF2F4F7),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: onPressed,
          borderRadius: BorderRadius.circular(14),
          child: DecoratedBox(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (loading)
                    SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.4,
                        color: AppColors.primary,
                      ),
                    )
                  else
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const _GoogleMark(),
                        const SizedBox(width: 12),
                        Text(
                          label,
                          style: Theme.of(context).textTheme.labelLarge
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ],
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _GoogleMark extends StatelessWidget {
  const _GoogleMark();

  @override
  Widget build(BuildContext context) {
    return const Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text('G', style: TextStyle(color: Color(0xFF4285F4), fontSize: 20)),
        Text('o', style: TextStyle(color: Color(0xFFDB4437), fontSize: 20)),
        Text('o', style: TextStyle(color: Color(0xFFF4B400), fontSize: 20)),
        Text('g', style: TextStyle(color: Color(0xFF4285F4), fontSize: 20)),
        Text('l', style: TextStyle(color: Color(0xFF0F9D58), fontSize: 20)),
        Text('e', style: TextStyle(color: Color(0xFFDB4437), fontSize: 20)),
      ],
    );
  }
}

class _LoginError extends StatelessWidget {
  const _LoginError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.error.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.18)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.error_outline_rounded, size: 18, color: AppColors.error),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.textPrimary,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AnimatedLoginBackground extends StatelessWidget {
  const _AnimatedLoginBackground({required this.controller});

  final Animation<double> controller;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final drift = Curves.easeInOut.transform(controller.value);

        return DecoratedBox(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0xFFF7FAFF), Color(0xFFFFFFFF)],
            ),
          ),
          child: Stack(
            children: [
              Positioned(
                top: -140 + (22 * drift),
                right: -120 - (16 * drift),
                child: _HalfCircleGlow(
                  size: 300,
                  color: AppColors.primary.withValues(alpha: 0.11),
                ),
              ),
              Positioned(
                top: 70 - (14 * drift),
                left: -130 + (14 * drift),
                child: _HalfCircleGlow(
                  size: 260,
                  color: AppColors.accentPurple.withValues(alpha: 0.08),
                ),
              ),
              Positioned(
                bottom: -160 + (18 * drift),
                left: -100,
                child: _HalfCircleGlow(
                  size: 320,
                  color: AppColors.primaryBright.withValues(alpha: 0.09),
                ),
              ),
              Positioned(
                bottom: -130 + (16 * drift),
                right: -100,
                child: _HalfCircleGlow(
                  size: 240,
                  color: AppColors.primary.withValues(alpha: 0.06),
                ),
              ),
              Positioned.fill(
                child: IgnorePointer(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.white.withValues(alpha: 0),
                          Colors.white.withValues(alpha: 0.32),
                          Colors.white.withValues(alpha: 0.72),
                        ],
                        stops: const [0, 0.55, 1],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _HalfCircleGlow extends StatelessWidget {
  const _HalfCircleGlow({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [
              color,
              color.withValues(alpha: color.a * 0.48),
              Colors.transparent,
            ],
            stops: const [0, 0.38, 1],
          ),
        ),
      ),
    );
  }
}
