import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/common_widgets.dart';

class TrainerNotificationPreferencesSheet extends StatefulWidget {
  const TrainerNotificationPreferencesSheet({
    super.key,
    required this.onLoad,
    required this.onSave,
  });

  final Future<List<Map<String, dynamic>>> Function() onLoad;
  final Future<List<Map<String, dynamic>>> Function(List<Map<String, dynamic>>)
  onSave;

  @override
  State<TrainerNotificationPreferencesSheet> createState() =>
      _TrainerNotificationPreferencesSheetState();
}

class _TrainerNotificationPreferencesSheetState
    extends State<TrainerNotificationPreferencesSheet> {
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

  Map<String, List<Map<String, dynamic>>> get _groupedPreferences {
    final categories = <String, List<Map<String, dynamic>>>{};
    for (final item in _preferences) {
      final category = item['category']?.toString().trim();
      final label = category == null || category.isEmpty ? 'General' : category;
      categories.putIfAbsent(label, () => <Map<String, dynamic>>[]).add(item);
    }
    return categories;
  }

  @override
  Widget build(BuildContext context) {
    final screenHeight = MediaQuery.sizeOf(context).height;

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 0, 10, 10),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: screenHeight * 0.9),
          child: ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(34)),
            child: Material(
              color: Colors.transparent,
              child: Stack(
                children: [
                  const Positioned.fill(child: _PreferencesBackdrop()),
                  Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const SizedBox(height: 10),
                      Container(
                        width: 46,
                        height: 5,
                        decoration: BoxDecoration(
                          color: AppColors.textMuted.withValues(alpha: 0.34),
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                      Expanded(
                        child: AnimatedSwitcher(
                          duration: const Duration(milliseconds: 260),
                          switchInCurve: Curves.easeOutCubic,
                          switchOutCurve: Curves.easeInCubic,
                          child: _loading
                              ? const _PreferenceLoadingState(
                                  key: ValueKey('trainer-pref-loading'),
                                )
                              : _error != null
                              ? _PreferenceErrorState(
                                  key: const ValueKey('trainer-pref-error'),
                                  message: _error!,
                                  onRetry: _load,
                                )
                              : _PreferenceContent(
                                  key: const ValueKey('trainer-pref-content'),
                                  categories: _groupedPreferences,
                                  preferences: _preferences,
                                  saving: _saving,
                                  onClose: _saving
                                      ? null
                                      : () => Navigator.of(context).pop(false),
                                  onSave: _saving ? null : _save,
                                  onChanged: (item, value) {
                                    setState(() {
                                      item['is_enabled'] = value;
                                    });
                                  },
                                ),
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

class _PreferenceContent extends StatelessWidget {
  const _PreferenceContent({
    super.key,
    required this.categories,
    required this.preferences,
    required this.saving,
    required this.onClose,
    required this.onSave,
    required this.onChanged,
  });

  final Map<String, List<Map<String, dynamic>>> categories;
  final List<Map<String, dynamic>> preferences;
  final bool saving;
  final VoidCallback? onClose;
  final VoidCallback? onSave;
  final void Function(Map<String, dynamic> item, bool value) onChanged;

  @override
  Widget build(BuildContext context) {
    final enabledCount = preferences
        .where((item) => item['is_enabled'] == true)
        .length;
    final criticalCount = preferences
        .where((item) => item['is_critical'] == true)
        .length;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 0),
          child: _PreferenceHero(
            enabledCount: enabledCount,
            totalCount: preferences.length,
            criticalCount: criticalCount,
          ),
        ),
        const SizedBox(height: 18),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 18),
            physics: const BouncingScrollPhysics(),
            children: [
              for (final categoryEntry in categories.entries)
                _PreferenceCategorySection(
                  title: categoryEntry.key,
                  items: categoryEntry.value,
                  onChanged: onChanged,
                ),
              const SizedBox(height: 84),
            ],
          ),
        ),
        _PreferenceActionBar(saving: saving, onClose: onClose, onSave: onSave),
      ],
    );
  }
}

class _PreferenceHero extends StatelessWidget {
  const _PreferenceHero({
    required this.enabledCount,
    required this.totalCount,
    required this.criticalCount,
  });

  final int enabledCount;
  final int totalCount;
  final int criticalCount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFF6F8FF), Color(0xFFFFF4FB)],
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.18),
            blurRadius: 26,
            offset: const Offset(0, 16),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -18,
            top: -18,
            child: Container(
              width: 106,
              height: 106,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  colors: [
                    AppColors.primaryBright.withValues(alpha: 0.42),
                    AppColors.accentNeon.withValues(alpha: 0.24),
                  ],
                ),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: LinearGradient(colors: _FitLifeColors.primaryG),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.primary.withValues(alpha: 0.28),
                          blurRadius: 18,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: const Icon(
                      Icons.notifications_active_rounded,
                      color: Colors.white,
                    ),
                  ),
                  const Spacer(),
                  _HeroCounterBadge(
                    value: '$enabledCount/$totalCount',
                    label: 'active',
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Text(
                'Notification Preferences',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 7),
              Text(
                'Choose the coaching alerts that should reach you immediately.',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                  height: 1.35,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  _HeroInfoPill(
                    icon: Icons.tune_rounded,
                    label: '${totalCount == 0 ? 0 : totalCount} controls',
                  ),
                  _HeroInfoPill(
                    icon: Icons.health_and_safety_rounded,
                    label: '$criticalCount critical',
                    gradient: _FitLifeColors.secondaryG,
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PreferenceCategorySection extends StatelessWidget {
  const _PreferenceCategorySection({
    required this.title,
    required this.items,
    required this.onChanged,
  });

  final String title;
  final List<Map<String, dynamic>> items;
  final void Function(Map<String, dynamic> item, bool value) onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _CategoryIcon(title: title),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Text(
                '${items.where((item) => item['is_enabled'] == true).length}/${items.length}',
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: AppColors.textMuted,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (final itemEntry in items.asMap().entries)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: RevealOnBuild(
                delay: Duration(milliseconds: 45 * itemEntry.key),
                child: _PreferenceTile(
                  item: itemEntry.value,
                  onChanged: (value) => onChanged(itemEntry.value, value),
                ),
              ),
            ),
        ],
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
    final enabled = item['is_enabled'] == true;
    final isCritical = item['is_critical'] == true;
    final isPlaceholder = item['is_placeholder'] == true;
    final accentGradient = isCritical
        ? _FitLifeColors.secondaryG
        : _FitLifeColors.primaryG;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: enabled ? 0.98 : 0.76),
        borderRadius: BorderRadius.circular(26),
        border: Border.all(
          color: enabled
              ? accentGradient.first.withValues(alpha: 0.30)
              : AppColors.stroke,
        ),
        boxShadow: [
          BoxShadow(
            color: enabled
                ? accentGradient.first.withValues(alpha: 0.16)
                : AppColors.shadow,
            blurRadius: enabled ? 22 : 14,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 240),
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(17),
              gradient: enabled
                  ? LinearGradient(colors: accentGradient)
                  : const LinearGradient(
                      colors: [Color(0xFFF7F8F8), Color(0xFFFFFFFF)],
                    ),
            ),
            child: Icon(
              _iconForLabel(item['label']?.toString() ?? ''),
              color: enabled ? Colors.white : AppColors.textMuted,
              size: 22,
            ),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        item['label']?.toString() ?? 'Preference',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.w800,
                          height: 1.15,
                        ),
                      ),
                    ),
                    if (isCritical || isPlaceholder) ...[
                      const SizedBox(width: 8),
                      _PreferenceTag(
                        label: isCritical ? 'Critical' : 'Beta',
                        gradient: isCritical
                            ? _FitLifeColors.secondaryG
                            : _FitLifeColors.primaryG,
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  item['description']?.toString() ?? '',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    height: 1.35,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          _GradientPreferenceSwitch(
            value: enabled,
            gradient: accentGradient,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

class _GradientPreferenceSwitch extends StatelessWidget {
  const _GradientPreferenceSwitch({
    required this.value,
    required this.gradient,
    required this.onChanged,
  });

  final bool value;
  final List<Color> gradient;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      toggled: value,
      child: GestureDetector(
        onTap: () => onChanged(!value),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          width: 54,
          height: 32,
          padding: const EdgeInsets.all(3),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            gradient: value ? LinearGradient(colors: gradient) : null,
            color: value ? null : const Color(0xFFEDEFF5),
          ),
          child: AnimatedAlign(
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOutCubic,
            alignment: value ? Alignment.centerRight : Alignment.centerLeft,
            child: Container(
              width: 26,
              height: 26,
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.16),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
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

class _PreferenceActionBar extends StatelessWidget {
  const _PreferenceActionBar({
    required this.saving,
    required this.onClose,
    required this.onSave,
  });

  final bool saving;
  final VoidCallback? onClose;
  final VoidCallback? onSave;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.92),
        border: Border(
          top: BorderSide(color: AppColors.stroke.withValues(alpha: 0.8)),
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadow.withValues(alpha: 0.8),
            blurRadius: 26,
            offset: const Offset(0, -12),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: _SoftActionButton(
              label: 'Close',
              onPressed: onClose,
              icon: Icons.close_rounded,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            flex: 2,
            child: _GradientSaveButton(
              label: saving ? 'Saving...' : 'Save preferences',
              icon: Icons.done_rounded,
              loading: saving,
              onPressed: onSave,
            ),
          ),
        ],
      ),
    );
  }
}

class _GradientSaveButton extends StatelessWidget {
  const _GradientSaveButton({
    required this.label,
    required this.icon,
    required this.loading,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final bool loading;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onPressed == null && !loading ? 0.55 : 1,
      child: GestureDetector(
        onTap: onPressed,
        child: Container(
          height: 52,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            gradient: LinearGradient(colors: _FitLifeColors.primaryG),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.30),
                blurRadius: 18,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Center(
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 180),
              child: loading
                  ? const SizedBox(
                      key: ValueKey('saving'),
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.4,
                        color: Colors.white,
                      ),
                    )
                  : Row(
                      key: const ValueKey('save-label'),
                      mainAxisAlignment: MainAxisAlignment.center,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(icon, color: Colors.white, size: 19),
                        const SizedBox(width: 8),
                        Flexible(
                          child: Text(
                            label,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.labelLarge
                                ?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
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

class _SoftActionButton extends StatelessWidget {
  const _SoftActionButton({
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onPressed == null ? 0.55 : 1,
      child: GestureDetector(
        onTap: onPressed,
        child: Container(
          height: 52,
          decoration: BoxDecoration(
            color: const Color(0xFFF7F8F8),
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: AppColors.stroke),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, color: AppColors.textSecondary, size: 19),
              const SizedBox(width: 7),
              Text(
                label,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PreferenceLoadingState extends StatelessWidget {
  const _PreferenceLoadingState({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          const _PreferenceHeroSkeleton(),
          const SizedBox(height: 18),
          Expanded(
            child: LoadingStateView(label: 'Loading your trainer alerts...'),
          ),
        ],
      ),
    );
  }
}

class _PreferenceErrorState extends StatelessWidget {
  const _PreferenceErrorState({
    super.key,
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          const _PreferenceHeroSkeleton(error: true),
          const SizedBox(height: 18),
          Expanded(
            child: ErrorStateView(message: message, onRetry: onRetry),
          ),
        ],
      ),
    );
  }
}

class _PreferenceHeroSkeleton extends StatelessWidget {
  const _PreferenceHeroSkeleton({this.error = false});

  final bool error;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        gradient: LinearGradient(
          colors: error
              ? [const Color(0xFFFFF4FB), const Color(0xFFFFFFFF)]
              : [const Color(0xFFF6F8FF), const Color(0xFFFFF4FB)],
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                colors: error
                    ? _FitLifeColors.secondaryG
                    : _FitLifeColors.primaryG,
              ),
            ),
            child: Icon(
              error ? Icons.cloud_off_rounded : Icons.notifications_rounded,
              color: Colors.white,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  height: 14,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.75),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                const SizedBox(height: 10),
                Container(
                  height: 10,
                  width: 160,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.70),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PreferencesBackdrop extends StatelessWidget {
  const _PreferencesBackdrop();

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFFFFFFF), Color(0xFFF7F8F8), Color(0xFFF7F1FF)],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            top: 42,
            right: -38,
            child: _BackdropOrb(
              size: 148,
              colors: [
                AppColors.primaryBright.withValues(alpha: 0.22),
                AppColors.primary.withValues(alpha: 0.08),
              ],
            ),
          ),
          Positioned(
            left: -42,
            bottom: 124,
            child: _BackdropOrb(
              size: 132,
              colors: [
                AppColors.accentNeon.withValues(alpha: 0.20),
                AppColors.accentPurple.withValues(alpha: 0.08),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BackdropOrb extends StatelessWidget {
  const _BackdropOrb({required this.size, required this.colors});

  final double size;
  final List<Color> colors;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(colors: colors),
      ),
    );
  }
}

class _HeroCounterBadge extends StatelessWidget {
  const _HeroCounterBadge({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.82),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white.withValues(alpha: 0.9)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            value,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w900,
              height: 1,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroInfoPill extends StatelessWidget {
  const _HeroInfoPill({required this.icon, required this.label, this.gradient});

  final IconData icon;
  final String label;
  final List<Color>? gradient;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: gradient == null ? Colors.white.withValues(alpha: 0.78) : null,
        gradient: gradient == null ? null : LinearGradient(colors: gradient!),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            size: 16,
            color: gradient == null ? AppColors.primary : Colors.white,
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: gradient == null ? AppColors.textPrimary : Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryIcon extends StatelessWidget {
  const _CategoryIcon({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 36,
      height: 36,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        gradient: LinearGradient(colors: _gradientForCategory(title)),
      ),
      child: Icon(_iconForLabel(title), color: Colors.white, size: 18),
    );
  }
}

class _PreferenceTag extends StatelessWidget {
  const _PreferenceTag({required this.label, required this.gradient});

  final String label;
  final List<Color> gradient;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: gradient),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: Colors.white,
          fontWeight: FontWeight.w800,
          height: 1,
        ),
      ),
    );
  }
}

class _FitLifeColors {
  static const primaryG = [Color(0xFF9DCEFF), Color(0xFF92A3FD)];
  static const secondaryG = [Color(0xFFEEA4CE), Color(0xFFC58BF2)];
}

List<Color> _gradientForCategory(String label) {
  final normalized = label.toLowerCase();
  if (normalized.contains('member') || normalized.contains('client')) {
    return _FitLifeColors.secondaryG;
  }
  if (normalized.contains('trainer') || normalized.contains('coach')) {
    return const [Color(0xFFFFC6A5), Color(0xFFFF8D77)];
  }
  if (normalized.contains('attendance') || normalized.contains('check')) {
    return const [Color(0xFF95E3D7), Color(0xFF4CB8C4)];
  }
  if (normalized.contains('workout') || normalized.contains('training')) {
    return _FitLifeColors.primaryG;
  }
  return const [Color(0xFFB8C0FF), Color(0xFF92A3FD)];
}

IconData _iconForLabel(String label) {
  final normalized = label.toLowerCase();
  if (normalized.contains('member') || normalized.contains('client')) {
    return Icons.groups_rounded;
  }
  if (normalized.contains('trainer') || normalized.contains('coach')) {
    return Icons.sports_rounded;
  }
  if (normalized.contains('attendance') || normalized.contains('check')) {
    return Icons.qr_code_scanner_rounded;
  }
  if (normalized.contains('workout') || normalized.contains('training')) {
    return Icons.fitness_center_rounded;
  }
  if (normalized.contains('progress') || normalized.contains('goal')) {
    return Icons.track_changes_rounded;
  }
  if (normalized.contains('announcement') || normalized.contains('news')) {
    return Icons.campaign_rounded;
  }
  if (normalized.contains('message') || normalized.contains('chat')) {
    return Icons.chat_bubble_rounded;
  }
  return Icons.notifications_active_rounded;
}
