import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/widgets/common_widgets.dart';

class AdminNotificationPreferencesSheet extends StatefulWidget {
  const AdminNotificationPreferencesSheet({
    super.key,
    required this.title,
    required this.subtitle,
    required this.onLoad,
    required this.onSave,
  });

  final String title;
  final String subtitle;
  final Future<List<Map<String, dynamic>>> Function() onLoad;
  final Future<List<Map<String, dynamic>>> Function(List<Map<String, dynamic>>)
  onSave;

  @override
  State<AdminNotificationPreferencesSheet> createState() =>
      _AdminNotificationPreferencesSheetState();
}

class _AdminNotificationPreferencesSheetState
    extends State<AdminNotificationPreferencesSheet> {
  bool _loading = true;
  bool _saving = false;
  String? _error;
  List<Map<String, dynamic>> _preferences = const [];

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
      final preferences = await widget.onLoad();
      if (mounted) {
        setState(() => _preferences = preferences);
      }
    } catch (exception) {
      if (mounted) {
        setState(() => _error = exception.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final updated = await widget.onSave(_preferences);
      if (!mounted) {
        return;
      }
      setState(() => _preferences = updated);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Notification preferences updated.')),
      );
      Navigator.of(context).pop(true);
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final categories = <String, List<Map<String, dynamic>>>{};
    for (final item in _preferences) {
      final category = item['category']?.toString() ?? 'General';
      categories
          .putIfAbsent(category, () => <Map<String, dynamic>>[])
          .add(item);
    }

    return FitModalSurface(
      title: widget.title,
      subtitle: widget.subtitle,
      icon: Icons.tune_rounded,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.86,
          maxWidth: 780,
        ),
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 220),
          child: _loading
              ? const LoadingState(
                  key: ValueKey('admin-pref-loading'),
                  label: 'Loading your operations alerts...',
                )
              : _error != null
              ? ErrorState(
                  key: const ValueKey('admin-pref-error'),
                  message: _error!,
                  onRetry: _load,
                )
              : Column(
                  key: const ValueKey('admin-pref-content'),
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _SurfaceCard(
                      child: Row(
                        children: [
                          Icon(
                            Icons.tune_rounded,
                            color: Theme.of(context).colorScheme.secondary,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              'Non-critical summaries and ops alerts can be muted. Billing-critical reminders can still appear when the platform needs them.',
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Expanded(
                      child: ListView(
                        children: [
                          for (final entry in categories.entries) ...[
                            Padding(
                              padding: const EdgeInsets.only(
                                bottom: AppSpacing.sm,
                              ),
                              child: Text(
                                entry.key,
                                style: Theme.of(context).textTheme.titleMedium
                                    ?.copyWith(fontWeight: FontWeight.w700),
                              ),
                            ),
                            ...entry.value.asMap().entries.map(
                              (itemEntry) => Padding(
                                padding: const EdgeInsets.only(bottom: 12),
                                child: RevealOnBuild(
                                  delay: Duration(
                                    milliseconds: 40 * itemEntry.key,
                                  ),
                                  child: _PreferenceTile(
                                    item: itemEntry.value,
                                    onChanged: (value) {
                                      setState(() {
                                        itemEntry.value['is_enabled'] = value;
                                      });
                                    },
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 4),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: _saving
                                ? null
                                : () => Navigator.of(context).pop(false),
                            child: const Text('Close'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: GradientButton(
                            onPressed: _saving ? null : _save,
                            label: 'Save preferences',
                            icon: Icons.tune_rounded,
                            loading: _saving,
                            expanded: true,
                          ),
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

class _PreferenceTile extends StatelessWidget {
  const _PreferenceTile({required this.item, required this.onChanged});

  final Map<String, dynamic> item;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final isCritical = item['is_critical'] == true;
    final isPlaceholder = item['is_placeholder'] == true;

    return _SurfaceCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Text(
                      item['label']?.toString() ?? 'Preference',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    if (isCritical)
                      const StatusBadge(
                        label: 'Critical fallback',
                        color: AppColors.warning,
                      ),
                    if (isPlaceholder)
                      const StatusBadge(
                        label: 'Placeholder',
                        color: AppColors.primary,
                      ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  item['description']?.toString() ?? '',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Switch.adaptive(
            value: item['is_enabled'] == true,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

class _SurfaceCard extends StatelessWidget {
  const _SurfaceCard({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        gradient: const LinearGradient(
          colors: [Color(0xFFF1F5FF), Color(0xFFFDF2F8)],
        ),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: child,
    );
  }
}
