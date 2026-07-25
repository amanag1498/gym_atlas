import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/loading_state.dart';
import '../../../core/widgets/premium_card.dart';
import 'member_repository.dart';

class MemberDietPlanScreen extends StatefulWidget {
  const MemberDietPlanScreen({super.key, required this.repository});
  final MemberRepository repository;

  @override
  State<MemberDietPlanScreen> createState() => _MemberDietPlanScreenState();
}

class _MemberDietPlanScreenState extends State<MemberDietPlanScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _plans = const [];
  final Set<int> _completedMealIds = <int>{};

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
      final response = await widget.repository.fetchDietPlans();
      _plans = (response['data'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      _completedMealIds
        ..clear()
        ..addAll(
          _meals(_activePlan())
              .where((meal) => meal['completed_for'] != null)
              .map((meal) => (meal['id'] as num).toInt()),
        );
    } catch (error) {
      _error = error.toString();
    }
    if (mounted) setState(() => _loading = false);
  }

  Map<String, dynamic> _activePlan() => _plans.firstWhere(
    (plan) => plan['status']?.toString() == 'active',
    orElse: () => _plans.isEmpty ? <String, dynamic>{} : _plans.first,
  );
  List<Map<String, dynamic>> _meals(Map<String, dynamic> plan) =>
      (plan['meals'] as List<dynamic>? ?? const [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();

  Future<void> _toggleMeal(
    Map<String, dynamic> plan,
    Map<String, dynamic> meal,
  ) async {
    final mealId = (meal['id'] as num?)?.toInt();
    final planId = (plan['id'] as num?)?.toInt();
    if (mealId == null || planId == null) return;
    final completed = !_completedMealIds.contains(mealId);
    setState(
      () => completed
          ? _completedMealIds.add(mealId)
          : _completedMealIds.remove(mealId),
    );
    try {
      await widget.repository.updateDietMealLog(
        planId,
        mealId,
        completed: completed,
      );
    } catch (_) {
      if (mounted) {
        setState(
          () => completed
              ? _completedMealIds.remove(mealId)
              : _completedMealIds.add(mealId),
        );
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Could not update this meal. Try again.'),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final plan = _activePlan();
    final meals = _meals(plan);
    return AppGradientScaffold(
      title: 'Diet Plan',
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
      body: _loading
          ? const LoadingState(label: 'Loading your diet plan...')
          : _error != null
          ? ErrorStateView(message: _error!, onRetry: _load)
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  if (plan.isEmpty)
                    const EmptyStateView(
                      title: 'No diet plan assigned',
                      message: 'Your trainer has not assigned a diet plan yet.',
                      icon: Icons.restaurant_menu_rounded,
                    )
                  else ...[
                    _Hero(plan: plan),
                    const SizedBox(height: AppSpacing.lg),
                    if (_hasGuidance(plan)) ...[
                      _Guidance(plan: plan),
                      const SizedBox(height: AppSpacing.lg),
                    ],
                    Row(
                      children: [
                        _Macro(
                          label: 'Protein',
                          value: '${plan['protein_target_g'] ?? 0}g',
                          color: AppColors.primary,
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        _Macro(
                          label: 'Carbs',
                          value: '${plan['carbs_target_g'] ?? 0}g',
                          color: AppColors.info,
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        _Macro(
                          label: 'Fats',
                          value: '${plan['fats_target_g'] ?? 0}g',
                          color: AppColors.accent,
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Today\'s meals',
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                        ),
                        Text(
                          '${_completedMealIds.length}/${meals.length} done',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: AppColors.success,
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    ...meals.map((meal) {
                      final done = _completedMealIds.contains(
                        (meal['id'] as num?)?.toInt(),
                      );
                      final items =
                          (meal['items'] as List<dynamic>? ?? const [])
                              .map(
                                (item) =>
                                    Map<String, dynamic>.from(item as Map),
                              )
                              .toList();
                      return Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: PremiumCard(
                          padding: const EdgeInsets.all(AppSpacing.md),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Icon(
                                    done
                                        ? Icons.check_circle_rounded
                                        : Icons.restaurant_rounded,
                                    color: done
                                        ? AppColors.success
                                        : AppColors.primary,
                                  ),
                                  const SizedBox(width: AppSpacing.sm),
                                  Expanded(
                                    child: Text(
                                      meal['name']?.toString() ?? 'Meal',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.titleMedium,
                                    ),
                                  ),
                                  Checkbox(
                                    value: done,
                                    onChanged: (_) => _toggleMeal(plan, meal),
                                  ),
                                ],
                              ),
                              if (meal['scheduled_time'] != null)
                                Text(
                                  meal['scheduled_time'].toString(),
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ...items.map(
                                (item) => Padding(
                                  padding: const EdgeInsets.only(top: 6),
                                  child: Text(
                                    '• ${item['name']}${item['quantity'] != null ? ' — ${item['quantity']}' : ''}',
                                  ),
                                ),
                              ),
                              if (meal['notes']?.toString().isNotEmpty ?? false)
                                Padding(
                                  padding: const EdgeInsets.only(top: 8),
                                  child: Text(meal['notes'].toString()),
                                ),
                            ],
                          ),
                        ),
                      );
                    }),
                  ],
                ],
              ),
            ),
    );
  }
}

bool _hasGuidance(Map<String, dynamic> plan) => [
  'dietary_preferences',
  'allergies_and_restrictions',
  'notes',
].any((key) => plan[key]?.toString().trim().isNotEmpty ?? false);

class _Guidance extends StatelessWidget {
  const _Guidance({required this.plan});

  final Map<String, dynamic> plan;

  @override
  Widget build(BuildContext context) {
    final entries = <MapEntry<String, String>>[
      MapEntry('Preferences', plan['dietary_preferences']?.toString() ?? ''),
      MapEntry(
        'Restrictions',
        plan['allergies_and_restrictions']?.toString() ?? '',
      ),
      MapEntry('Coach notes', plan['notes']?.toString() ?? ''),
    ].where((entry) => entry.value.trim().isNotEmpty).toList();
    return PremiumCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Plan guidance', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: AppSpacing.sm),
          ...entries.map(
            (entry) => Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.sm),
              child: RichText(
                text: TextSpan(
                  style: Theme.of(context).textTheme.bodyMedium,
                  children: [
                    TextSpan(
                      text: '${entry.key}: ',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    TextSpan(text: entry.value),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.plan});
  final Map<String, dynamic> plan;
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: AppColors.primary,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            plan['name']?.toString() ?? 'Your nutrition plan',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(color: Colors.white),
          ),
          const SizedBox(height: 6),
          Text(
            plan['goal']?.toString() ?? 'Daily nutrition targets',
            style: const TextStyle(color: Colors.white70),
          ),
          const SizedBox(height: 18),
          Text(
            '${plan['daily_calorie_target'] ?? '--'} kcal / day',
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    ),
  );
}

class _Macro extends StatelessWidget {
  const _Macro({required this.label, required this.value, required this.color});
  final String label, value;
  final Color color;
  @override
  Widget build(BuildContext context) => Expanded(
    child: PremiumCard(
      padding: const EdgeInsets.all(AppSpacing.sm),
      child: Padding(
        padding: EdgeInsets.zero,
        child: Column(
          children: [
            Text(
              value,
              style: TextStyle(fontWeight: FontWeight.bold, color: color),
            ),
            Text(label, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    ),
  );
}
