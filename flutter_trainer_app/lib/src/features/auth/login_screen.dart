import 'dart:math' as math;

import 'package:flutter/material.dart';
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
              ? 30.0
              : width < 430
              ? 36.0
              : 40.0;
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
                              child: _BrandLockup(logoSize: logoSize),
                            ),
                            SizedBox(height: compact ? 28 : 40),
                            RevealOnBuild(
                              delay: const Duration(milliseconds: 120),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  Text(
                                    'Your coaching,\nall in one place.',
                                    textAlign: TextAlign.center,
                                    style: theme.textTheme.displaySmall
                                        ?.copyWith(
                                          fontSize: headlineSize,
                                          height: 0.94,
                                          letterSpacing: -1.2,
                                          fontWeight: FontWeight.w700,
                                        ),
                                  ),
                                  const SizedBox(height: 12),
                                  ConstrainedBox(
                                    constraints: const BoxConstraints(
                                      maxWidth: 340,
                                    ),
                                    child: Text(
                                      'Members, workouts, follow-ups, and trainer tasks in one focused coach app.',
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
                              ),
                            ),
                            const SizedBox(height: 14),
                            RevealOnBuild(
                              delay: const Duration(milliseconds: 340),
                              child: Center(
                                child: Text(
                                  'Trainers only',
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    letterSpacing: 0.2,
                                  ),
                                ),
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
}

class _BrandLockup extends StatelessWidget {
  const _BrandLockup({required this.logoSize});

  final double logoSize;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      children: [
        Container(
          width: logoSize,
          height: logoSize,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [AppColors.primary, AppColors.primaryBright],
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.22),
                blurRadius: 28,
                offset: const Offset(0, 14),
              ),
            ],
          ),
          alignment: Alignment.center,
          child: Icon(
            Icons.sports_gymnastics_rounded,
            color: Colors.white,
            size: math.max(24, logoSize * 0.4),
          ),
        ),
        const SizedBox(width: 14),
        Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Text(
              'GymAtlas',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _LoginPanel extends StatelessWidget {
  const _LoginPanel({
    required this.compact,
    required this.busy,
    required this.error,
    required this.onPressed,
  });

  final bool compact;
  final bool busy;
  final String? error;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Colors.white.withValues(alpha: 0.96),
            const Color(0xFFF8FBFF),
          ],
        ),
        border: Border.all(color: Colors.white.withValues(alpha: 0.88)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.10),
            blurRadius: 32,
            offset: const Offset(0, 22),
          ),
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.08),
            blurRadius: 40,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(28),
        child: Stack(
          children: [
            Positioned(
              top: -28,
              right: -8,
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(
                    colors: [
                      AppColors.primary.withValues(alpha: 0.16),
                      AppColors.primaryBright.withValues(alpha: 0.04),
                      Colors.transparent,
                    ],
                  ),
                ),
              ),
            ),
            Padding(
              padding: EdgeInsets.all(compact ? 18 : 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Text(
                    'Continue with Google',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontSize: compact ? 22 : 24,
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 16),
                    _LoginError(message: error!),
                  ],
                  const SizedBox(height: 22),
                  GradientButton(
                    expanded: true,
                    label: busy ? 'Signing in...' : 'Continue',
                    icon: busy ? null : Icons.arrow_forward_rounded,
                    loading: busy,
                    onPressed: onPressed,
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
