import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/error_state.dart';
import '../../../core/widgets/loading_state.dart';
import 'member_repository.dart';

class MemberWhatsAppPreferencesSheet extends StatefulWidget {
  const MemberWhatsAppPreferencesSheet({
    super.key,
    required this.repository,
    required this.gymId,
    required this.scopeName,
  });

  final MemberRepository repository;
  final int? gymId;
  final String scopeName;

  @override
  State<MemberWhatsAppPreferencesSheet> createState() =>
      _MemberWhatsAppPreferencesSheetState();
}

class _MemberWhatsAppPreferencesSheetState
    extends State<MemberWhatsAppPreferencesSheet> {
  bool _loading = true;
  String? _error;
  final Map<String, bool> _values = {'utility': false, 'marketing': false};
  final Set<String> _saving = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final consents = await widget.repository.fetchWhatsAppConsents();
      for (final consent in consents) {
        if ((consent['gym_id'] as num?)?.toInt() == widget.gymId) {
          final purpose = consent['purpose']?.toString();
          if (_values.containsKey(purpose)) {
            _values[purpose!] = consent['status'] == 'granted';
          }
        }
      }
    } catch (exception) {
      _error = exception.toString();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _toggle(String purpose, bool value) async {
    setState(() => _saving.add(purpose));
    try {
      await widget.repository.updateWhatsAppConsent(
        gymId: widget.gymId,
        purpose: purpose,
        granted: value,
      );
      if (mounted) setState(() => _values[purpose] = value);
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => _saving.remove(purpose));
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        margin: const EdgeInsets.all(10),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(30)),
        ),
        child: _loading
            ? const SizedBox(
                height: 260,
                child: LoadingState(label: 'Loading WhatsApp preferences...'),
              )
            : _error != null
            ? SizedBox(
                height: 260,
                child: ErrorState(message: _error!, onRetry: _load),
              )
            : Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 46,
                      height: 5,
                      decoration: BoxDecoration(
                        color: AppColors.textMuted.withValues(alpha: 0.3),
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
                  const SizedBox(height: 22),
                  Text(
                    'WhatsApp notifications',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Choose what ${widget.scopeName} may send to your account phone number. You can opt out anytime here or by replying STOP.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textMuted,
                    ),
                  ),
                  const SizedBox(height: 18),
                  _ConsentTile(
                    title: 'Service reminders',
                    subtitle:
                        'Membership, payment, schedule, booking, and account updates.',
                    value: _values['utility']!,
                    busy: _saving.contains('utility'),
                    onChanged: (value) => _toggle('utility', value),
                  ),
                  _ConsentTile(
                    title: 'Offers and campaigns',
                    subtitle:
                        'Promotions, gym offers, challenges, and marketing campaigns.',
                    value: _values['marketing']!,
                    busy: _saving.contains('marketing'),
                    onChanged: (value) => _toggle('marketing', value),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: _saving.isEmpty
                          ? () => Navigator.of(context).pop(true)
                          : null,
                      child: const Text('Done'),
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}

class _ConsentTile extends StatelessWidget {
  const _ConsentTile({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.busy,
    required this.onChanged,
  });

  final String title;
  final String subtitle;
  final bool value;
  final bool busy;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Text(subtitle),
      value: value,
      onChanged: busy ? null : onChanged,
      secondary: busy
          ? const SizedBox.square(
              dimension: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : null,
    );
  }
}
