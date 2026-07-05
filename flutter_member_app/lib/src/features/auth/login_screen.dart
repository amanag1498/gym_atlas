import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_gradients.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import 'session_controller.dart';

class MemberLoginScreen extends StatelessWidget {
  const MemberLoginScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<MemberSessionController>();

    return Scaffold(
      backgroundColor: AppColors.backgroundAlt,
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppGradients.pageBackground),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: Center(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    const SizedBox(height: 8),
                    Container(
                      width: 170,
                      height: 170,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [
                            AppColors.primaryBright.withValues(alpha: 0.9),
                            AppColors.primary.withValues(alpha: 0.92),
                            AppColors.accentPurple.withValues(alpha: 0.92),
                          ],
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primary.withValues(alpha: 0.24),
                            blurRadius: 44,
                            offset: const Offset(0, 18),
                          ),
                        ],
                      ),
                      child: Stack(
                        alignment: Alignment.center,
                        children: [
                          Container(
                            width: 122,
                            height: 122,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.white.withValues(alpha: 0.18),
                            ),
                          ),
                          const Icon(
                            Icons.fitness_center_rounded,
                            size: 66,
                            color: Colors.white,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 28),
                    Text(
                      'Hey there,',
                      style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Welcome Back',
                      style: Theme.of(context).textTheme.displaySmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 12),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 360),
                      child: Text(
                        'Sign in to continue your member journey with workouts, progress tracking, attendance, gym discovery, and trainer-connected fitness flow.',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ),
                    const SizedBox(height: 22),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      alignment: WrapAlignment.center,
                      children: const [
                        StatusBadge(label: 'Google Sign-In'),
                        StatusBadge(label: 'Member Access'),
                        StatusBadge(label: 'Workout Tracking'),
                      ],
                    ),
                    const SizedBox(height: 28),
                    PremiumCard(
                      padding: const EdgeInsets.all(24),
                      glowColor: AppColors.primary,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Continue with Google',
                            style: Theme.of(context).textTheme.headlineSmall,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Your account, onboarding state, membership context, and workout data will continue exactly where you left off.',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                          if (session.error != null) ...[
                            const SizedBox(height: AppSpacing.lg),
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(AppSpacing.md),
                              decoration: BoxDecoration(
                                color: AppColors.error.withValues(alpha: 0.10),
                                borderRadius: BorderRadius.circular(
                                  AppSpacing.radiusMd,
                                ),
                                border: Border.all(
                                  color: AppColors.error.withValues(alpha: 0.22),
                                ),
                              ),
                              child: Text(
                                session.error!,
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                  color: AppColors.textPrimary,
                                ),
                              ),
                            ),
                          ],
                          const SizedBox(height: 22),
                          GradientButton(
                            expanded: true,
                            label: session.busy
                                ? 'Signing in...'
                                : 'Continue with Google',
                            icon: session.busy ? null : Icons.login_rounded,
                            loading: session.busy,
                            onPressed: session.busy
                                ? null
                                : () => context
                                    .read<MemberSessionController>()
                                    .login(),
                          ),
                          const SizedBox(height: 18),
                          Row(
                            children: [
                              Expanded(
                                child: Container(
                                  height: 1,
                                  color: AppColors.strokeStrong,
                                ),
                              ),
                              Padding(
                                padding:
                                    const EdgeInsets.symmetric(horizontal: 14),
                                child: Text(
                                  'Secure member access',
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodySmall
                                      ?.copyWith(color: AppColors.textMuted),
                                ),
                              ),
                              Expanded(
                                child: Container(
                                  height: 1,
                                  color: AppColors.strokeStrong,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 18),
                          Row(
                            children: [
                              Expanded(
                                child: _LoginInfoTile(
                                  icon: Icons.workspace_premium_rounded,
                                  title: 'Membership',
                                  subtitle: 'Track dues, status and access',
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _LoginInfoTile(
                                  icon: Icons.insights_rounded,
                                  title: 'Progress',
                                  subtitle: 'See workouts, weight and trends',
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _LoginInfoTile extends StatelessWidget {
  const _LoginInfoTile({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceStrong,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AppColors.primary, size: 22),
          const SizedBox(height: 12),
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}
