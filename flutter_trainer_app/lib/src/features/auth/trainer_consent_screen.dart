import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../trainer/trainer_settings_screen.dart';
import 'session_controller.dart';

class TrainerConsentScreen extends StatefulWidget {
  const TrainerConsentScreen({super.key, required this.session});

  final TrainerSessionController session;

  @override
  State<TrainerConsentScreen> createState() => _TrainerConsentScreenState();
}

class _TrainerConsentScreenState extends State<TrainerConsentScreen> {
  static const _trainerPurposes = {'photos', 'notifications', 'whatsapp'};
  bool _saving = false;
  bool _signingOut = false;
  String? _error;
  final Set<String> _selected = <String>{};

  @override
  void initState() {
    super.initState();
    for (final item
        in (widget.session.consentState['items'] as List<dynamic>? ?? const [])
            .whereType<Map>()) {
      if (item['purpose'] != 'core_account' && item['granted'] == true) {
        _selected.add(item['purpose'].toString());
      }
    }
  }

  Future<void> _continue() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final items =
          (widget.session.consentState['items'] as List<dynamic>? ?? const [])
              .whereType<Map>()
              .toList();
      for (final item in items.where(
        (item) => _trainerPurposes.contains(item['purpose']),
      )) {
        final purpose = item['purpose'].toString();
        if (_selected.contains(purpose) && item['granted'] != true) {
          await widget.session.grantConsent(purpose);
        } else if (!_selected.contains(purpose) && item['granted'] == true) {
          await widget.session.withdrawConsent(purpose);
        }
      }
      await widget.session.grantConsent('core_account');
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'We could not save your choice. Please try again.',
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _signOut() async {
    if (_saving || _signingOut) return;
    setState(() => _signingOut = true);
    await widget.session.logout();
    if (mounted) setState(() => _signingOut = false);
  }

  void _setAllOptional(bool enabled, List<Map<String, dynamic>> optionalItems) {
    setState(() {
      for (final item in optionalItems) {
        final purpose = item['purpose'].toString();
        if (enabled) {
          _selected.add(purpose);
        } else {
          _selected.remove(purpose);
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final items =
        (widget.session.consentState['items'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((value) => Map<String, dynamic>.from(value))
            .toList();
    final optionalItems = items
        .where((value) => _trainerPurposes.contains(value['purpose']))
        .toList();
    final allOptionalSelected =
        optionalItems.isNotEmpty &&
        optionalItems.every((value) => _selected.contains(value['purpose']));
    final busy = _saving || _signingOut;

    return AppGradientScaffold(
      title: 'Privacy',
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Center(
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxWidth: 520,
                  minHeight: constraints.maxHeight > (AppSpacing.lg * 2)
                      ? constraints.maxHeight - (AppSpacing.lg * 2)
                      : 0,
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Align(
                          alignment: Alignment.centerLeft,
                          child: Container(
                            width: 56,
                            height: 56,
                            decoration: BoxDecoration(
                              color: AppColors.primary.withValues(alpha: 0.10),
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: const Icon(
                              Icons.verified_user_rounded,
                              color: AppColors.primary,
                              size: 28,
                            ),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.xl),
                        Text(
                          'One last step',
                          style: theme.textTheme.headlineMedium?.copyWith(
                            color: AppColors.textPrimary,
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.8,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          'Review your privacy choice, then continue to Gym Atlas Coach.',
                          style: theme.textTheme.bodyLarge?.copyWith(
                            color: AppColors.textSecondary,
                            height: 1.45,
                          ),
                        ),
                        if (optionalItems.isNotEmpty) ...[
                          const SizedBox(height: AppSpacing.xxl),
                          Material(
                            color: allOptionalSelected
                                ? AppColors.primary.withValues(alpha: 0.07)
                                : AppColors.surface,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(
                                AppSpacing.radiusMd,
                              ),
                              side: BorderSide(
                                color: allOptionalSelected
                                    ? AppColors.primary.withValues(alpha: 0.30)
                                    : AppColors.stroke,
                              ),
                            ),
                            clipBehavior: Clip.antiAlias,
                            child: CheckboxListTile(
                              value: allOptionalSelected,
                              onChanged: busy
                                  ? null
                                  : (value) => _setAllOptional(
                                      value == true,
                                      optionalItems,
                                    ),
                              controlAffinity: ListTileControlAffinity.leading,
                              contentPadding: const EdgeInsets.symmetric(
                                horizontal: AppSpacing.md,
                                vertical: AppSpacing.sm,
                              ),
                              title: Text(
                                'Enable optional features',
                                style: theme.textTheme.titleMedium?.copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              subtitle: Text(
                                'Photos and useful updates, including WhatsApp. Leave this off if you prefer—you can still continue.',
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  color: AppColors.textSecondary,
                                  height: 1.4,
                                ),
                              ),
                            ),
                          ),
                        ],
                        if (_error != null) ...[
                          const SizedBox(height: AppSpacing.md),
                          Semantics(
                            liveRegion: true,
                            child: Container(
                              padding: const EdgeInsets.all(AppSpacing.md),
                              decoration: BoxDecoration(
                                color: AppColors.error.withValues(alpha: 0.08),
                                borderRadius: BorderRadius.circular(
                                  AppSpacing.radiusSm,
                                ),
                              ),
                              child: Text(
                                _error!,
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  color: AppColors.error,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpacing.xxl),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          FilledButton(
                            onPressed: busy ? null : _continue,
                            style: FilledButton.styleFrom(
                              minimumSize: const Size.fromHeight(54),
                            ),
                            child: _saving
                                ? const SizedBox.square(
                                    dimension: 22,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2.4,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text('Continue to Gym Atlas Coach'),
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          Text(
                            'By continuing, you agree to our Terms of Service and acknowledge our Privacy Policy.',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: AppColors.textSecondary,
                              height: 1.4,
                            ),
                          ),
                          Wrap(
                            alignment: WrapAlignment.center,
                            spacing: AppSpacing.xs,
                            children: [
                              TextButton(
                                onPressed: busy
                                    ? null
                                    : () =>
                                          TrainerSettingsScreen.openTermsOfService(
                                            context,
                                          ),
                                child: const Text('Terms'),
                              ),
                              TextButton(
                                onPressed: busy
                                    ? null
                                    : () =>
                                          TrainerSettingsScreen.openPrivacyPolicy(
                                            context,
                                          ),
                                child: const Text('Privacy'),
                              ),
                              TextButton(
                                onPressed: busy ? null : _signOut,
                                child: Text(
                                  _signingOut ? 'Signing out…' : 'Sign out',
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
