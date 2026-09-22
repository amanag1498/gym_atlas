import 'package:flutter/material.dart';

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
  bool _customizing = false;
  bool _showDetails = false;
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
          () => _error = 'Could not save your choice. Please try again.',
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
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
    return AppGradientScaffold(
      title: 'Your privacy choices',
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Text(
                        'Make Gym Atlas Coach yours',
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      const Text(
                        'Choose the extras you want. Your account can be created without them.',
                      ),
                      const SizedBox(height: AppSpacing.md),
                      const Text(
                        'Optional features',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const Text(
                        'You can change these anytime in Settings. Features left off may be unavailable.',
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        optionalItems
                            .map((value) => value['title'])
                            .join(' · '),
                      ),
                      TextButton(
                        onPressed: () =>
                            setState(() => _showDetails = !_showDetails),
                        child: Text(
                          _showDetails
                              ? 'Hide details'
                              : 'See what this includes',
                        ),
                      ),
                      if (_showDetails)
                        ...optionalItems.map(
                          (value) => Padding(
                            padding: const EdgeInsets.only(
                              bottom: AppSpacing.sm,
                            ),
                            child: Text(
                              '${value['title']}: ${value['description']}',
                            ),
                          ),
                        ),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        value: allOptionalSelected,
                        title: const Text('Turn on all optional features'),
                        subtitle: const Text('Not required to continue'),
                        onChanged: (checked) => setState(() {
                          for (final value in optionalItems) {
                            final purpose = value['purpose'].toString();
                            if (checked == true) {
                              _selected.add(purpose);
                            } else {
                              _selected.remove(purpose);
                            }
                          }
                        }),
                      ),
                      TextButton(
                        onPressed: () =>
                            setState(() => _customizing = !_customizing),
                        child: Text(
                          _customizing
                              ? 'Hide individual choices'
                              : 'Choose individually instead',
                        ),
                      ),
                      if (_customizing)
                        ...optionalItems.map(
                          (value) => CheckboxListTile(
                            contentPadding: EdgeInsets.zero,
                            value: _selected.contains(value['purpose']),
                            title: Text(
                              value['title']?.toString() ?? 'Optional data use',
                            ),
                            subtitle: Text(
                              value['description']?.toString() ?? '',
                            ),
                            onChanged: (checked) => setState(() {
                              if (checked == true) {
                                _selected.add(value['purpose'].toString());
                              } else {
                                _selected.remove(value['purpose'].toString());
                              }
                            }),
                          ),
                        ),
                      if (_error != null) ...[
                        const SizedBox(height: AppSpacing.md),
                        Text(
                          _error!,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ],
                      const SizedBox(height: AppSpacing.md),
                    ],
                  ),
                ),
              ),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _saving ? null : _continue,
                  child: _saving
                      ? const CircularProgressIndicator()
                      : const Text('Continue'),
                ),
              ),
              const SizedBox(height: AppSpacing.sm),
              const Text(
                'By continuing, you agree to our Terms of Service and Privacy Policy. We use your account and training details to provide Gym Atlas Coach.',
                textAlign: TextAlign.center,
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  TextButton(
                    onPressed: () =>
                        TrainerSettingsScreen.openTermsOfService(context),
                    child: const Text('Terms of Service'),
                  ),
                  TextButton(
                    onPressed: () =>
                        TrainerSettingsScreen.openPrivacyPolicy(context),
                    child: const Text('Privacy Policy'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
