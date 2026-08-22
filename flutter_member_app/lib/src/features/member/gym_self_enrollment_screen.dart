import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import '../auth/session_controller.dart';
import 'member_repository.dart';

class GymSelfEnrollmentScreen extends StatefulWidget {
  const GymSelfEnrollmentScreen({
    super.key,
    required this.token,
    required this.repository,
  });

  final String token;
  final MemberRepository repository;

  @override
  State<GymSelfEnrollmentScreen> createState() =>
      _GymSelfEnrollmentScreenState();
}

class _GymSelfEnrollmentScreenState extends State<GymSelfEnrollmentScreen> {
  Map<String, dynamic>? _preview;
  bool _loading = true;
  bool _joining = false;
  bool _reuseProfile = true;
  int? _branchId;
  String? _error;

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
      final response = await widget.repository.fetchSelfEnrollmentPreview(
        widget.token,
      );
      final data = Map<String, dynamic>.from(response['data'] as Map);
      final gym = Map<String, dynamic>.from(data['gym'] as Map);
      final branch = gym['branch'] as Map?;
      if (branch != null) {
        _branchId = (branch['id'] as num?)?.toInt();
      }
      if (mounted) setState(() => _preview = data);
    } catch (exception) {
      if (mounted) {
        setState(
          () => _error = exception.toString().replaceFirst('Exception: ', ''),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _join() async {
    final preview = _preview;
    if (preview == null) return;
    final gym = Map<String, dynamic>.from(preview['gym'] as Map);
    final branches = (gym['branches'] as List<dynamic>? ?? const []);
    if (gym['branch'] == null && branches.isNotEmpty && _branchId == null) {
      setState(() => _error = 'Choose the gym branch you are joining.');
      return;
    }

    setState(() {
      _joining = true;
      _error = null;
    });
    try {
      final response = await widget.repository.joinGymFromSelfEnrollment(
        widget.token,
        branchId: _branchId,
        reuseProfile: _reuseProfile,
      );
      final data = Map<String, dynamic>.from(response['data'] as Map);
      final gymId = (data['gym_id'] as num?)?.toInt();
      if (data['outcome'] == 'inactive_member') {
        throw Exception(response['message']?.toString());
      }
      if (!mounted) return;
      await context.read<MemberSessionController>().selectGymContext(gymId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            response['message']?.toString() ?? 'Gym joined successfully.',
          ),
        ),
      );
      context.go('/home');
    } catch (exception) {
      if (mounted) {
        setState(
          () => _error = exception.toString().replaceFirst('Exception: ', ''),
        );
      }
    } finally {
      if (mounted) setState(() => _joining = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Join gym',
      subtitle: 'Atlas self-enrollment',
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null && _preview == null
            ? ErrorStateView(message: _error!, onRetry: _load)
            : _buildContent(),
      ),
    );
  }

  Widget _buildContent() {
    final data = _preview!;
    final gym = Map<String, dynamic>.from(data['gym'] as Map);
    final profile = Map<String, dynamic>.from(data['profile'] as Map);
    final fixedBranch = gym['branch'] == null
        ? null
        : Map<String, dynamic>.from(gym['branch'] as Map);
    final branches = (gym['branches'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    final goals = (profile['fitness_goals'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => item['name']?.toString())
        .whereType<String>()
        .toList();
    final blocked = data['requires_gym_assistance'] == true;
    final already = data['already_enrolled'] == true;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 680),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                PremiumCard(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  borderRadius: 28,
                  child: Column(
                    children: [
                      Container(
                        width: 72,
                        height: 72,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [
                              AppColors.primaryBright,
                              AppColors.primary,
                            ],
                          ),
                          borderRadius: BorderRadius.circular(24),
                        ),
                        child: const Icon(
                          Icons.storefront_rounded,
                          color: Colors.white,
                          size: 34,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        gym['name']?.toString() ?? 'Gym',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        fixedBranch?['name']?.toString() ??
                            'Choose the branch you are joining',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                PremiumCard(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  borderRadius: 28,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Your Atlas profile',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        profile['name']?.toString() ?? '',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        profile['email']?.toString() ?? '',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          if (goals.isNotEmpty) _ProfileChip(goals.join(', ')),
                          if (profile['experience_level'] != null)
                            _ProfileChip(
                              profile['experience_level'].toString(),
                            ),
                          if (profile['height_cm'] != null)
                            _ProfileChip('${profile['height_cm']} cm'),
                          if (profile['weight_kg'] != null)
                            _ProfileChip('${profile['weight_kg']} kg'),
                          if (profile['has_health_notes'] == true)
                            const _ProfileChip('Health context saved'),
                        ],
                      ),
                      if (branches.isNotEmpty) ...[
                        const SizedBox(height: AppSpacing.lg),
                        DropdownButtonFormField<int>(
                          initialValue: _branchId,
                          decoration: const InputDecoration(
                            labelText: 'Branch',
                            prefixIcon: Icon(Icons.location_on_outlined),
                          ),
                          items: branches
                              .map(
                                (branch) => DropdownMenuItem<int>(
                                  value: (branch['id'] as num).toInt(),
                                  child: Text(
                                    branch['name']?.toString() ?? 'Branch',
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: (value) =>
                              setState(() => _branchId = value),
                        ),
                      ],
                      const SizedBox(height: AppSpacing.md),
                      SwitchListTile.adaptive(
                        contentPadding: EdgeInsets.zero,
                        value: _reuseProfile,
                        onChanged: blocked || already
                            ? null
                            : (value) => setState(() => _reuseProfile = value),
                        title: const Text('Use my current Atlas profile'),
                        subtitle: const Text(
                          'Copy your goals, baseline, and health context to this gym profile.',
                        ),
                      ),
                    ],
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: AppSpacing.md),
                  PremiumCard(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Text(
                      _error!,
                      style: const TextStyle(color: AppColors.error),
                    ),
                  ),
                ],
                const SizedBox(height: AppSpacing.md),
                GradientButton(
                  label: blocked
                      ? 'Ask the gym desk for help'
                      : already
                      ? 'Open this gym'
                      : 'Join ${gym['name']}',
                  icon: blocked
                      ? Icons.support_agent_rounded
                      : Icons.check_circle_outline_rounded,
                  loading: _joining,
                  onPressed: blocked ? null : _join,
                  expanded: true,
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  'No approval or second account is required.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _ProfileChip extends StatelessWidget {
  const _ProfileChip(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.14)),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: AppColors.primary,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
