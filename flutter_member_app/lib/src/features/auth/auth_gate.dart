import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';

class AuthGateScreen extends StatefulWidget {
  const AuthGateScreen({super.key});

  @override
  State<AuthGateScreen> createState() => _AuthGateScreenState();
}

class _AuthGateScreenState extends State<AuthGateScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 7),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: LayoutBuilder(
        builder: (context, constraints) {
          final compact =
              constraints.maxWidth < 380 || constraints.maxHeight < 720;
          final horizontalPadding = compact ? 22.0 : 28.0;

          return Stack(
            children: [
              _InitializingBackground(controller: _controller),
              SafeArea(
                child: Center(
                  child: SingleChildScrollView(
                    padding: EdgeInsets.symmetric(
                      horizontal: horizontalPadding,
                      vertical: 24,
                    ),
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 430),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          const RevealOnBuild(
                            delay: Duration(milliseconds: 40),
                            child: _InitializingBrand(),
                          ),
                          SizedBox(height: compact ? 34 : 46),
                          RevealOnBuild(
                            delay: const Duration(milliseconds: 120),
                            child: Text(
                              'Initializing\nyour session',
                              textAlign: TextAlign.center,
                              style: theme.textTheme.displaySmall?.copyWith(
                                fontSize: compact ? 32 : 38,
                                height: 0.95,
                                letterSpacing: -1.0,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),
                          RevealOnBuild(
                            delay: const Duration(milliseconds: 200),
                            child: ConstrainedBox(
                              constraints: const BoxConstraints(maxWidth: 320),
                              child: Text(
                                'Restoring your access and loading the member workspace.',
                                textAlign: TextAlign.center,
                                style: theme.textTheme.bodyLarge?.copyWith(
                                  color: AppColors.textSecondary,
                                  height: 1.45,
                                ),
                              ),
                            ),
                          ),
                          SizedBox(height: compact ? 24 : 30),
                          const RevealOnBuild(
                            delay: Duration(milliseconds: 280),
                            offset: Offset(0, 0.05),
                            duration: Duration(milliseconds: 500),
                            child: _InitializingPanel(),
                          ),
                        ],
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

class _InitializingBrand extends StatelessWidget {
  const _InitializingBrand();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 58,
          height: 58,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                AppColors.primary,
                AppColors.primaryBright,
              ],
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.18),
                blurRadius: 26,
                offset: const Offset(0, 14),
              ),
            ],
          ),
          alignment: Alignment.center,
          child: const BrandMark(size: 30),
        ),
        const SizedBox(width: 14),
        Text(
          'GymAtlas',
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _InitializingPanel extends StatelessWidget {
  const _InitializingPanel();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
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
        border: Border.all(color: Colors.white.withValues(alpha: 0.9)),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.10),
            blurRadius: 34,
            offset: const Offset(0, 24),
          ),
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.08),
            blurRadius: 42,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          children: [
            Container(
              width: 62,
              height: 62,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.primary.withValues(alpha: 0.08),
              ),
              alignment: Alignment.center,
              child: SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(
                  strokeWidth: 2.6,
                  valueColor: const AlwaysStoppedAnimation<Color>(
                    AppColors.primary,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Text(
              'Preparing your member workspace',
              textAlign: TextAlign.center,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 16),
            const _SessionLine(
              icon: Icons.security_rounded,
              label: 'Restoring saved sign-in',
            ),
            const SizedBox(height: 10),
            const _SessionLine(
              icon: Icons.verified_user_rounded,
              label: 'Verifying member access',
            ),
            const SizedBox(height: 10),
            const _SessionLine(
              icon: Icons.dashboard_customize_rounded,
              label: 'Loading your latest dashboard context',
            ),
          ],
        ),
      ),
    );
  }
}

class _SessionLine extends StatelessWidget {
  const _SessionLine({
    required this.icon,
    required this.label,
  });

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      decoration: BoxDecoration(
        color: AppColors.surfaceStrong,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: AppColors.primaryBright.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            alignment: Alignment.center,
            child: Icon(icon, size: 18, color: AppColors.primary),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              label,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w700,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InitializingBackground extends StatelessWidget {
  const _InitializingBackground({required this.controller});

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
              colors: [
                Color(0xFFF7FAFF),
                Color(0xFFFFFFFF),
              ],
            ),
          ),
          child: Stack(
            children: [
              Positioned(
                top: -130 + (18 * drift),
                right: -110 - (14 * drift),
                child: _HalfOrb(
                  size: 280,
                  color: AppColors.primary.withValues(alpha: 0.10),
                ),
              ),
              Positioned(
                top: 90 - (12 * drift),
                left: -120 + (10 * drift),
                child: _HalfOrb(
                  size: 240,
                  color: AppColors.accentPurple.withValues(alpha: 0.08),
                ),
              ),
              Positioned(
                bottom: -150 + (16 * drift),
                left: -80,
                child: _HalfOrb(
                  size: 300,
                  color: AppColors.primaryBright.withValues(alpha: 0.08),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _HalfOrb extends StatelessWidget {
  const _HalfOrb({
    required this.size,
    required this.color,
  });

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
              color.withValues(alpha: color.a * 0.45),
              Colors.transparent,
            ],
            stops: const [0, 0.58, 1],
          ),
        ),
      ),
    );
  }
}
