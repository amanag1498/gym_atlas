import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import 'trainer_profile_screen.dart';
import 'trainer_repository.dart';

class TrainerProfileOverviewScreen extends StatefulWidget {
  const TrainerProfileOverviewScreen({super.key, required this.repository});

  final TrainerRepository repository;

  @override
  State<TrainerProfileOverviewScreen> createState() =>
      _TrainerProfileOverviewScreenState();
}

class _TrainerProfileOverviewScreenState
    extends State<TrainerProfileOverviewScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _profile = const {};
  Map<String, dynamic> _user = const {};

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  Future<void> _loadProfile() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.repository.fetchProfile();
      final data = _map(response['data']);
      _profile = _map(data['trainer_profile']);
      _user = _map(data['trainer_user']);
    } catch (error) {
      _error = error.toString();
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _openEditor() async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => TrainerProfileScreen(repository: widget.repository),
      ),
    );
    if (mounted) await _loadProfile();
  }

  @override
  Widget build(BuildContext context) {
    final name = _value(_user['name'], fallback: 'Trainer profile');
    final email = _value(_user['email'], fallback: 'Email unavailable');
    final completion =
        ((_profile['profile_completion_percentage'] as num?)?.toDouble() ?? 0)
            .clamp(0, 100);
    final specializations = _names(_profile['specializations']);
    final languages = _names(_profile['languages']);
    final certifications = _names(_profile['certifications']);
    final gym = _map(_profile['assigned_gym']);
    final branch = _map(_profile['assigned_branch']);
    final verificationStatus = _value(
      _profile['verification_status'],
      fallback: 'Not submitted',
    );

    return AppGradientScaffold(
      title: 'Profile',
      body: SafeArea(
        bottom: false,
        child: _loading
            ? const LoadingStateView(label: 'Loading trainer profile...')
            : _error != null
            ? ErrorStateView(message: _error!, onRetry: _loadProfile)
            : RefreshIndicator(
                onRefresh: _loadProfile,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg,
                    AppSpacing.sm,
                    AppSpacing.lg,
                    AppSpacing.xl,
                  ),
                  children: [
                    _OverviewTopBar(onRefresh: _loadProfile),
                    const SizedBox(height: AppSpacing.md),
                    PremiumCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Row(
                            children: [
                              _TrainerAvatar(
                                imageUrl: _value(
                                  _profile['profile_photo_url'],
                                  fallback: '',
                                ),
                                name: name,
                              ),
                              const SizedBox(width: AppSpacing.md),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      name,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleMedium
                                          ?.copyWith(
                                            color: AppColors.textPrimary,
                                            fontWeight: FontWeight.w800,
                                          ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      email,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(
                                            color: AppColors.textSecondary,
                                            fontWeight: FontWeight.w600,
                                          ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: AppSpacing.md),
                          LayoutBuilder(
                            builder: (context, constraints) {
                              final progress = Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '${completion.toStringAsFixed(0)}% complete',
                                    style: Theme.of(context)
                                        .textTheme
                                        .labelLarge
                                        ?.copyWith(
                                          color: AppColors.textPrimary,
                                          fontWeight: FontWeight.w800,
                                        ),
                                  ),
                                  const SizedBox(height: 7),
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(999),
                                    child: LinearProgressIndicator(
                                      value: completion / 100,
                                      minHeight: 7,
                                      backgroundColor: AppColors.surfaceSoft,
                                      color: AppColors.primaryBright,
                                    ),
                                  ),
                                ],
                              );
                              final edit = FilledButton.icon(
                                onPressed: _openEditor,
                                icon: const Icon(Icons.edit_outlined, size: 18),
                                label: const Text('Edit profile'),
                              );
                              if (constraints.maxWidth < 310) {
                                return Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.stretch,
                                  children: [
                                    progress,
                                    const SizedBox(height: AppSpacing.md),
                                    edit,
                                  ],
                                );
                              }
                              return Row(
                                children: [
                                  Expanded(child: progress),
                                  const SizedBox(width: AppSpacing.md),
                                  edit,
                                ],
                              );
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 25),
                    _OverviewGroup(
                      title: 'Personal Details',
                      subtitle:
                          'Your account and trainer identity information.',
                      children: [
                        _OverviewRow(
                          icon: Icons.phone_outlined,
                          title: 'Phone Number',
                          value: _value(_user['phone']),
                        ),
                        _OverviewRow(
                          icon: Icons.cake_outlined,
                          title: 'Date of Birth',
                          value: _value(_user['date_of_birth']),
                        ),
                        _OverviewRow(
                          icon: Icons.person_outline_rounded,
                          title: 'Gender',
                          value: _gender(_user['gender']),
                        ),
                      ],
                    ),
                    const SizedBox(height: 25),
                    _OverviewGroup(
                      title: 'Coaching Profile',
                      subtitle:
                          'What members and gyms see about your coaching.',
                      children: [
                        _OverviewRow(
                          icon: Icons.notes_rounded,
                          title: 'Bio',
                          value: _value(_profile['bio']),
                          multiline: true,
                        ),
                        _OverviewRow(
                          icon: Icons.fitness_center_rounded,
                          title: 'Specializations',
                          value: specializations.isEmpty
                              ? 'Not added'
                              : specializations.join(', '),
                          multiline: true,
                        ),
                        _OverviewRow(
                          icon: Icons.timeline_rounded,
                          title: 'Experience',
                          value: _profile['experience_years'] == null
                              ? 'Not added'
                              : '${_profile['experience_years']} years',
                        ),
                        _OverviewRow(
                          icon: Icons.workspace_premium_outlined,
                          title: 'Certifications',
                          value: certifications.isEmpty
                              ? 'Not added'
                              : certifications.join(', '),
                          multiline: true,
                        ),
                        _OverviewRow(
                          icon: Icons.translate_rounded,
                          title: 'Languages',
                          value: languages.isEmpty
                              ? 'Not added'
                              : languages.join(', '),
                        ),
                      ],
                    ),
                    const SizedBox(height: 25),
                    _OverviewGroup(
                      title: 'Gym Access',
                      children: [
                        _OverviewRow(
                          icon: Icons.apartment_rounded,
                          title: 'Assigned Gym',
                          value: _value(gym['name']),
                        ),
                        _OverviewRow(
                          icon: Icons.location_on_outlined,
                          title: 'Assigned Branch',
                          value: _value(branch['name']),
                        ),
                        _OverviewRow(
                          icon: Icons.groups_outlined,
                          title: 'Clients',
                          value: _value(
                            _profile['client_count'],
                            fallback: '0',
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 25),
                    _OverviewGroup(
                      title: 'Personal Coaching Verification',
                      subtitle:
                          'Your eligibility to coach members independently.',
                      children: [
                        _OverviewRow(
                          icon: Icons.verified_outlined,
                          title: 'Status',
                          value: _titleCase(verificationStatus),
                        ),
                        if (_value(
                          _profile['verification_rejection_reason'],
                          fallback: '',
                        ).isNotEmpty)
                          _OverviewRow(
                            icon: Icons.info_outline_rounded,
                            title: 'Changes requested',
                            value: _value(
                              _profile['verification_rejection_reason'],
                            ),
                            multiline: true,
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

class _OverviewTopBar extends StatelessWidget {
  const _OverviewTopBar({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      IconButton(
        tooltip: 'Back',
        onPressed: () => Navigator.of(context).maybePop(),
        icon: const Icon(Icons.arrow_back_rounded),
        style: IconButton.styleFrom(
          backgroundColor: AppColors.surface,
          side: const BorderSide(color: AppColors.stroke),
          fixedSize: const Size(42, 42),
        ),
      ),
      const SizedBox(width: AppSpacing.md),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Profile Overview',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w800,
              ),
            ),
            Text(
              'Coaching details, gym access, and verification.',
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
            ),
          ],
        ),
      ),
      IconButton(
        tooltip: 'Refresh profile',
        onPressed: onRefresh,
        icon: const Icon(Icons.refresh_rounded),
      ),
    ],
  );
}

class _TrainerAvatar extends StatelessWidget {
  const _TrainerAvatar({required this.imageUrl, required this.name});

  final String imageUrl;
  final String name;

  @override
  Widget build(BuildContext context) {
    final initials = name
        .trim()
        .split(' ')
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part[0].toUpperCase())
        .join();
    return Container(
      width: 64,
      height: 64,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.surfaceSoft,
        border: Border.all(color: AppColors.stroke),
      ),
      child: Padding(
        padding: const EdgeInsets.all(2),
        child: CircleAvatar(
          backgroundColor: AppColors.surface,
          backgroundImage: imageUrl.isEmpty ? null : NetworkImage(imageUrl),
          child: imageUrl.isNotEmpty
              ? null
              : Text(
                  initials.isEmpty ? 'T' : initials,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                ),
        ),
      ),
    );
  }
}

class _OverviewGroup extends StatelessWidget {
  const _OverviewGroup({
    required this.title,
    required this.children,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => PremiumCard(
    padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.w800,
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 5),
          Text(
            subtitle!,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              height: 1.35,
            ),
          ),
        ],
        const SizedBox(height: 12),
        ...children,
      ],
    ),
  );
}

class _OverviewRow extends StatelessWidget {
  const _OverviewRow({
    required this.icon,
    required this.title,
    required this.value,
    this.multiline = false,
  });

  final IconData icon;
  final String title;
  final String value;
  final bool multiline;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
    child: Row(
      crossAxisAlignment: multiline
          ? CrossAxisAlignment.start
          : CrossAxisAlignment.center,
      children: [
        Container(
          width: 30,
          height: 30,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.surfaceSoft,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: AppColors.stroke),
          ),
          child: Icon(icon, color: AppColors.primaryBright, size: 16),
        ),
        const SizedBox(width: 15),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                value,
                maxLines: multiline ? 3 : 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

String _value(dynamic value, {String fallback = 'Not added'}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

List<String> _names(dynamic value) => value is List
    ? value
          .map(
            (item) => item is Map
                ? _value(item['name'], fallback: '')
                : _value(item, fallback: ''),
          )
          .where((item) => item.isNotEmpty)
          .toList()
    : const [];

String _gender(dynamic value) => switch (value?.toString()) {
  'female' => 'Female',
  'male' => 'Male',
  'non_binary' => 'Non-binary',
  'prefer_not_to_say' => 'Prefer not to say',
  _ => 'Not added',
};

String _titleCase(String value) => value
    .split('_')
    .map(
      (part) =>
          part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');
