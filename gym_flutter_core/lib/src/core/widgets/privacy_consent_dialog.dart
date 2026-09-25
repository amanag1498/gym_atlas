import 'package:flutter/material.dart';

class PrivacyConsentDialog extends StatefulWidget {
  const PrivacyConsentDialog({
    super.key,
    required this.appName,
    required this.items,
    required this.onGrant,
    required this.onWithdraw,
  });

  final String appName;
  final List<Map<String, dynamic>> items;
  final Future<void> Function(String purpose) onGrant;
  final Future<void> Function(String purpose) onWithdraw;

  @override
  State<PrivacyConsentDialog> createState() => _PrivacyConsentDialogState();
}

class _PrivacyConsentDialogState extends State<PrivacyConsentDialog> {
  late final List<Map<String, dynamic>> _items;
  String? _savingPurpose;
  String? _error;

  @override
  void initState() {
    super.initState();
    _items = widget.items
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<void> _setConsent(Map<String, dynamic> item, bool value) async {
    final purpose = item['purpose']?.toString() ?? '';
    if (purpose.isEmpty || _savingPurpose != null) return;

    final required = item['required'] == true;
    if (required && !value) {
      final confirmed = await _confirmAccountWithdrawal();
      if (confirmed != true || !mounted) return;
    }

    setState(() {
      _savingPurpose = purpose;
      _error = null;
    });

    try {
      if (value) {
        await widget.onGrant(purpose);
      } else {
        await widget.onWithdraw(purpose);
      }
      if (!mounted) return;
      setState(() => item['granted'] = value);
      if (required && !value) {
        Navigator.of(context).pop();
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'We could not save that change. Please try again.',
        );
      }
    } finally {
      if (mounted) setState(() => _savingPurpose = null);
    }
  }

  Future<bool?> _confirmAccountWithdrawal() {
    final theme = Theme.of(context);
    return showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        icon: Icon(
          Icons.warning_amber_rounded,
          color: theme.colorScheme.error,
          size: 32,
        ),
        title: const Text('Stop using this account?'),
        content: Text(
          '${widget.appName} features will become unavailable on this account. You can agree again when you next sign in.',
          textAlign: TextAlign.center,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Keep account active'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: theme.colorScheme.error,
            ),
            child: const Text('Withdraw consent'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final accountItems = _items
        .where((item) => item['required'] == true)
        .toList();
    final optionalItems = _items
        .where((item) => item['required'] != true)
        .toList();
    final enabledCount = optionalItems
        .where((item) => item['granted'] == true)
        .length;
    final screenHeight = MediaQuery.sizeOf(context).height;

    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      clipBehavior: Clip.antiAlias,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: 540,
          maxHeight: screenHeight > 48 ? screenHeight - 48 : screenHeight,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 24, 12, 16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ExcludeSemantics(
                    child: Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: scheme.primary.withValues(alpha: 0.10),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Icon(
                        Icons.shield_outlined,
                        color: scheme.primary,
                        size: 25,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Privacy & consent',
                          style: theme.textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.4,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Choose which optional features can use your information.',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: scheme.onSurfaceVariant,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close privacy settings',
                    onPressed: _savingPurpose == null
                        ? () => Navigator.of(context).pop()
                        : null,
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            Flexible(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (accountItems.isNotEmpty) ...[
                      _AccountConsentCard(
                        item: accountItems.first,
                        saving:
                            _savingPurpose ==
                            accountItems.first['purpose']?.toString(),
                        onChange: (value) =>
                            _setConsent(accountItems.first, value),
                      ),
                      const SizedBox(height: 22),
                    ],
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Optional features',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        Text(
                          '$enabledCount of ${optionalItems.length} on',
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: scheme.onSurfaceVariant,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Turn these off anytime. Core account access stays separate.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 12),
                    if (optionalItems.isEmpty)
                      _MessageCard(
                        icon: Icons.check_circle_outline_rounded,
                        message: 'No optional choices are available right now.',
                        color: scheme.primary,
                      )
                    else
                      DecoratedBox(
                        decoration: BoxDecoration(
                          border: Border.all(color: scheme.outlineVariant),
                          borderRadius: BorderRadius.circular(18),
                        ),
                        child: Column(
                          children: [
                            for (
                              var index = 0;
                              index < optionalItems.length;
                              index++
                            ) ...[
                              _ConsentRow(
                                item: optionalItems[index],
                                saving:
                                    _savingPurpose ==
                                    optionalItems[index]['purpose']?.toString(),
                                enabled: _savingPurpose == null,
                                onChange: (value) =>
                                    _setConsent(optionalItems[index], value),
                              ),
                              if (index < optionalItems.length - 1)
                                Divider(
                                  height: 1,
                                  indent: 16,
                                  endIndent: 16,
                                  color: scheme.outlineVariant,
                                ),
                            ],
                          ],
                        ),
                      ),
                    if (_error != null) ...[
                      const SizedBox(height: 14),
                      Semantics(
                        liveRegion: true,
                        child: _MessageCard(
                          icon: Icons.error_outline_rounded,
                          message: _error!,
                          color: scheme.error,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(24, 12, 24, 20),
              decoration: BoxDecoration(
                color: scheme.surface,
                border: Border(top: BorderSide(color: scheme.outlineVariant)),
              ),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _savingPurpose == null
                      ? () => Navigator.of(context).pop()
                      : null,
                  child: const Text('Done'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AccountConsentCard extends StatelessWidget {
  const _AccountConsentCard({
    required this.item,
    required this.saving,
    required this.onChange,
  });

  final Map<String, dynamic> item;
  final bool saving;
  final ValueChanged<bool> onChange;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final granted = item['granted'] == true;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: scheme.primary.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.primary.withValues(alpha: 0.20)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                granted
                    ? Icons.verified_user_rounded
                    : Icons.gpp_maybe_outlined,
                color: granted ? scheme.primary : scheme.error,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Account services',
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      granted
                          ? 'Active · required while you use the app'
                          : 'Account access is currently paused',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (granted)
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton(
                onPressed: saving ? null : () => onChange(false),
                style: TextButton.styleFrom(foregroundColor: scheme.error),
                child: saving
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Withdraw account consent'),
              ),
            )
          else
            FilledButton(
              onPressed: saving ? null : () => onChange(true),
              child: saving
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Enable account access'),
            ),
        ],
      ),
    );
  }
}

class _ConsentRow extends StatelessWidget {
  const _ConsentRow({
    required this.item,
    required this.saving,
    required this.enabled,
    required this.onChange,
  });

  final Map<String, dynamic> item;
  final bool saving;
  final bool enabled;
  final ValueChanged<bool> onChange;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final value = item['granted'] == true;
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      title: Text(
        item['title']?.toString() ?? 'Optional feature',
        style: theme.textTheme.titleSmall?.copyWith(
          fontWeight: FontWeight.w800,
        ),
      ),
      subtitle: Padding(
        padding: const EdgeInsets.only(top: 3),
        child: Text(
          item['description']?.toString() ?? '',
          style: theme.textTheme.bodySmall?.copyWith(
            color: scheme.onSurfaceVariant,
            height: 1.35,
          ),
        ),
      ),
      trailing: saving
          ? const SizedBox.square(
              dimension: 22,
              child: CircularProgressIndicator(strokeWidth: 2.2),
            )
          : Switch.adaptive(value: value, onChanged: enabled ? onChange : null),
      onTap: enabled && !saving ? () => onChange(!value) : null,
    );
  }
}

class _MessageCard extends StatelessWidget {
  const _MessageCard({
    required this.icon,
    required this.message,
    required this.color,
  });

  final IconData icon;
  final String message;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(message)),
        ],
      ),
    );
  }
}
