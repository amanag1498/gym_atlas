import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/error_state.dart';
import '../../../core/widgets/premium_card.dart';
import 'member_repository.dart';

class MemberProfileScreen extends StatefulWidget {
  const MemberProfileScreen({
    super.key,
    required this.repository,
    required this.onProfileUpdated,
    this.openEditOnLoad = false,
  });

  final MemberRepository repository;
  final Future<void> Function() onProfileUpdated;
  final bool openEditOnLoad;

  @override
  State<MemberProfileScreen> createState() => _MemberProfileScreenState();
}

class _MemberProfileScreenState extends State<MemberProfileScreen> {
  bool _loading = true;
  bool _showSuccess = false;
  bool _openedInitialEditor = false;
  bool _leavingGym = false;
  String? _error;
  Map<String, dynamic> _profile = const <String, dynamic>{};
  bool _hasCurrentGymMembership = false;
  int _activeGymRelationshipCount = 0;

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
      _profile = Map<String, dynamic>.from(
        response['data'] as Map? ?? const <String, dynamic>{},
      );
      final contextResponse = await widget.repository.fetchContext();
      final contextData = Map<String, dynamic>.from(
        contextResponse['data'] as Map? ?? const <String, dynamic>{},
      );
      final membership = contextData['current_membership'];
      _activeGymRelationshipCount =
          (contextData['gym_relationships'] as List? ?? const []).length;
      _hasCurrentGymMembership =
          membership is Map &&
          membership['current_gym'] is Map &&
          (membership['status'] == 'active' ||
              membership['status'] == 'frozen');
    } catch (exception) {
      _error = exception.toString();
    }

    if (mounted) {
      setState(() => _loading = false);
      if (widget.openEditOnLoad && !_openedInitialEditor && _error == null) {
        _openedInitialEditor = true;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) {
            _openEditProfile();
          }
        });
      }
    }
  }

  Future<void> _openEditProfile() async {
    final updated = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (context) => _MemberProfileEditScreen(
          repository: widget.repository,
          initialProfile: _profile,
        ),
      ),
    );

    if (widget.openEditOnLoad) {
      if (updated == true) {
        await widget.onProfileUpdated();
      }
      if (mounted) {
        Navigator.of(context).pop();
      }
      return;
    }

    if (updated == true) {
      await _loadProfile();
      await widget.onProfileUpdated();
      if (!mounted) {
        return;
      }
      setState(() => _showSuccess = true);
      await Future<void>.delayed(const Duration(milliseconds: 1800));
      if (mounted) {
        setState(() => _showSuccess = false);
      }
    }
  }

  Future<void> _openCompletionDetails() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => _ProfileCompletionScreen(profile: _profile),
      ),
    );
  }

  Future<void> _confirmLeaveGym(String gymName) async {
    final hasOtherGyms = _activeGymRelationshipCount > 1;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Leave gym?'),
        content: Text(
          hasOtherGyms
              ? 'This removes only your active access to $gymName. Your other gym memberships, their trainers, and every independent trainer relationship stay unchanged. Historical records remain available for audit.'
              : 'This removes your active access to $gymName and makes your account independent. Membership, payment, attendance, and workout history remain available for audit.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Leave Gym'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    setState(() => _leavingGym = true);

    try {
      final response = await widget.repository.leaveCurrentGym();
      final result = response['data'] is Map
          ? Map<String, dynamic>.from(response['data'] as Map)
          : const <String, dynamic>{};
      final remainsGymMember = result['status'] == 'gym_member';
      await _loadProfile();
      await widget.onProfileUpdated();
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            remainsGymMember
                ? 'You left $gymName. Your other gym access remains active.'
                : 'You left $gymName and are now an independent member.',
          ),
        ),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _leavingGym = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final completion = _completionData(_profile);
    final completionPercent = completion.percent;
    final photoUrl = _stringValue(_profile['photo']);
    final currentGym =
        _hasCurrentGymMembership && _profile['current_gym'] is Map
        ? Map<String, dynamic>.from(_profile['current_gym'] as Map)
        : const <String, dynamic>{};
    final currentBranch =
        _hasCurrentGymMembership && _profile['current_branch'] is Map
        ? Map<String, dynamic>.from(_profile['current_branch'] as Map)
        : const <String, dynamic>{};
    final assignedTrainer =
        _hasCurrentGymMembership && _profile['assigned_trainer'] is Map
        ? Map<String, dynamic>.from(_profile['assigned_trainer'] as Map)
        : const <String, dynamic>{};
    final currentGymName = _stringValue(currentGym['name']);
    final hasCurrentGym = currentGym.isNotEmpty && currentGym['id'] != null;

    return AppGradientScaffold(
      title: 'Profile',
      body: SafeArea(
        bottom: false,
        child: _loading
            ? const _ProfileSkeleton()
            : _error != null
            ? ErrorState(message: _error!, onRetry: _loadProfile)
            : RefreshIndicator(
                onRefresh: _loadProfile,
                color: AppColors.primaryBright,
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
                  children: <Widget>[
                    _ProfileTopBar(
                      title: 'Profile Overview',
                      subtitle:
                          'Training details, gym access, and health notes.',
                      onRefresh: _loading ? null : _loadProfile,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _EditAnimatedSection(
                      child: PremiumCard(
                        child: Row(
                          children: <Widget>[
                            _ProfileAvatar(
                              imageUrl: photoUrl,
                              name: _stringValue(_profile['name']),
                              size: 56,
                            ),
                            const SizedBox(width: AppSpacing.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  Text(
                                    _stringValue(
                                      _profile['name'],
                                      fallback: 'Member profile',
                                    ),
                                    maxLines: 1,
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
                                    _stringValue(
                                      _profile['email'],
                                      fallback: 'Email unavailable',
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: AppColors.textSecondary,
                                          fontWeight: FontWeight.w600,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            InkWell(
                              onTap: _openEditProfile,
                              borderRadius: BorderRadius.circular(16),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 14,
                                  vertical: 10,
                                ),
                                decoration: BoxDecoration(
                                  color: AppColors.surfaceSoft,
                                  borderRadius: BorderRadius.circular(16),
                                  border: Border.all(color: AppColors.stroke),
                                ),
                                child: Text(
                                  'Edit',
                                  style: Theme.of(context).textTheme.labelLarge
                                      ?.copyWith(
                                        color: AppColors.textPrimary,
                                        fontWeight: FontWeight.w800,
                                      ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 15),
                    _EditAnimatedSection(
                      delay: const Duration(milliseconds: 70),
                      child: Row(
                        children: <Widget>[
                          Expanded(
                            child: _EditTitleCell(
                              title: _numericLabel(_profile['height_cm'], 'cm'),
                              subtitle: 'Height',
                            ),
                          ),
                          const SizedBox(width: 15),
                          Expanded(
                            child: _EditTitleCell(
                              title: _numericLabel(_profile['weight_kg'], 'kg'),
                              subtitle: 'Weight',
                            ),
                          ),
                          const SizedBox(width: 15),
                          Expanded(
                            child: _EditTitleCell(
                              title: '$completionPercent%',
                              subtitle: 'Ready',
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (_showSuccess) ...<Widget>[
                      const SizedBox(height: 15),
                      const _SuccessPill(),
                    ],
                    const SizedBox(height: 25),
                    _EditAnimatedSection(
                      delay: const Duration(milliseconds: 120),
                      child: _EditGroup(
                        title: 'Training Profile',
                        children: <Widget>[
                          _OverviewValueRow(
                            icon: Icons.trending_up_rounded,
                            title: 'Experience Level',
                            value: _stringValue(_profile['experience_level']),
                          ),
                          _OverviewGoalsRow(goals: _fitnessGoalNames(_profile)),
                          _OverviewValueRow(
                            icon: Icons.track_changes_rounded,
                            title: 'Profile Completion',
                            value: completion.missingLabels.isEmpty
                                ? 'Complete'
                                : '${completion.missingLabels.length} missing',
                            onPressed: _openCompletionDetails,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 25),
                    _EditAnimatedSection(
                      delay: const Duration(milliseconds: 170),
                      child: _EditGroup(
                        title: 'Gym Access',
                        children: <Widget>[
                          _OverviewValueRow(
                            icon: Icons.fitness_center_rounded,
                            title: 'Current Gym',
                            value: currentGymName,
                          ),
                          if (_activeGymRelationshipCount > 1)
                            _OverviewValueRow(
                              icon: Icons.account_tree_outlined,
                              title: 'Active Gym Relationships',
                              value:
                                  '$_activeGymRelationshipCount gyms · switch from Home',
                            ),
                          _OverviewValueRow(
                            icon: Icons.location_on_outlined,
                            title: 'Current Branch',
                            value: _stringValue(currentBranch['name']),
                          ),
                          _OverviewValueRow(
                            icon: Icons.support_agent_rounded,
                            title: 'Assigned Trainer',
                            value: _stringValue(assignedTrainer['name']),
                          ),
                          if (hasCurrentGym)
                            _OverviewValueRow(
                              icon: Icons.logout_rounded,
                              title: _leavingGym
                                  ? 'Leaving Gym...'
                                  : 'Leave Gym',
                              value: 'Keep history, remove active app access',
                              onPressed: _leavingGym
                                  ? null
                                  : () => _confirmLeaveGym(currentGymName),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 25),
                    _EditAnimatedSection(
                      delay: const Duration(milliseconds: 220),
                      child: _EditGroup(
                        title: 'Health Notes',
                        children: <Widget>[
                          _OverviewValueRow(
                            icon: Icons.healing_rounded,
                            title: 'Injuries / Limitations',
                            value: _stringValue(
                              _profile['injuries_limitations'],
                            ),
                            multiline: true,
                          ),
                          _OverviewValueRow(
                            icon: Icons.medical_information_outlined,
                            title: 'Medical Notes',
                            value: _stringValue(_profile['medical_notes']),
                            multiline: true,
                          ),
                        ],
                      ),
                    ),
                    if (_isProfileEffectivelyEmpty(_profile)) ...<Widget>[
                      const SizedBox(height: 25),
                      _EditAnimatedSection(
                        delay: const Duration(milliseconds: 270),
                        child: _EditInlineNote(
                          icon: Icons.person_add_alt_1_rounded,
                          title: 'Complete your profile',
                          message:
                              'Add goals, metrics and safety notes for a better member experience.',
                        ),
                      ),
                    ],
                  ],
                ),
              ),
      ),
    );
  }
}

class _ProfileTopBar extends StatelessWidget {
  const _ProfileTopBar({
    required this.title,
    required this.subtitle,
    required this.onRefresh,
  });

  final String title;
  final String subtitle;
  final VoidCallback? onRefresh;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        InkWell(
          onTap: () => Navigator.of(context).maybePop(),
          borderRadius: BorderRadius.circular(16),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.stroke),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 10,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: const Icon(
              Icons.arrow_back_rounded,
              color: AppColors.textPrimary,
              size: 20,
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
        if (onRefresh != null) ...[
          const SizedBox(width: AppSpacing.md),
          MemberHeaderActionButton(
            icon: Icons.refresh_rounded,
            onTap: onRefresh!,
          ),
        ],
      ],
    );
  }
}

class _MemberProfileEditScreen extends StatefulWidget {
  const _MemberProfileEditScreen({
    required this.repository,
    required this.initialProfile,
  });

  final MemberRepository repository;
  final Map<String, dynamic> initialProfile;

  @override
  State<_MemberProfileEditScreen> createState() =>
      _MemberProfileEditScreenState();
}

class _MemberProfileEditScreenState extends State<_MemberProfileEditScreen> {
  static const List<String> _experienceLevels = <String>[
    'beginner',
    'intermediate',
    'advanced',
  ];

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final ImagePicker _imagePicker = ImagePicker();
  bool _saving = false;
  bool _uploadingPhoto = false;
  String? _error;
  late final TextEditingController _nameController;
  late final TextEditingController _photoController;
  late final TextEditingController _heightController;
  late final TextEditingController _weightController;
  late final TextEditingController _injuriesController;
  late final TextEditingController _medicalController;
  late final List<Map<String, dynamic>> _availableGoals;
  late final Set<int> _selectedGoalIds;
  late String _experienceLevel;

  @override
  void initState() {
    super.initState();
    final profile = widget.initialProfile;
    _nameController = TextEditingController(
      text: _stringValue(profile['name'], fallback: ''),
    );
    _photoController = TextEditingController(
      text: _stringValue(profile['photo'], fallback: ''),
    );
    _heightController = TextEditingController(
      text: _editableNumber(profile['height_cm']),
    );
    _weightController = TextEditingController(
      text: _editableNumber(profile['weight_kg']),
    );
    final initialExperience = _stringValue(
      profile['experience_level'],
      fallback: '',
    ).toLowerCase();
    _experienceLevel = _experienceLevels.contains(initialExperience)
        ? initialExperience
        : 'beginner';
    _injuriesController = TextEditingController(
      text: _stringValue(profile['injuries_limitations'], fallback: ''),
    );
    _medicalController = TextEditingController(
      text: _stringValue(profile['medical_notes'], fallback: ''),
    );
    _availableGoals =
        (profile['available_fitness_goals'] as List<dynamic>? ?? const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
    _selectedGoalIds = (profile['fitness_goals'] as List<dynamic>? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .map((item) => (item['id'] as num?)?.toInt())
        .whereType<int>()
        .toSet();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _photoController.dispose();
    _heightController.dispose();
    _weightController.dispose();
    _injuriesController.dispose();
    _medicalController.dispose();
    super.dispose();
  }

  Future<void> _showPhotoSourceSheet() async {
    if (_saving || _uploadingPhoto) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              0,
              AppSpacing.lg,
              AppSpacing.lg,
            ),
            child: PremiumCard(
              padding: const EdgeInsets.all(18),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Choose profile photo',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Use a clear profile image from your gallery or camera.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 18),
                  _EditPickerAction(
                    icon: Icons.photo_library_outlined,
                    title: 'Gallery',
                    subtitle: 'Choose a saved photo',
                    onTap: () {
                      Navigator.of(context).pop();
                      _pickAndUploadPhoto(ImageSource.gallery);
                    },
                  ),
                  const SizedBox(height: 10),
                  _EditPickerAction(
                    icon: Icons.photo_camera_outlined,
                    title: 'Camera',
                    subtitle: 'Capture a new photo',
                    onTap: () {
                      Navigator.of(context).pop();
                      _pickAndUploadPhoto(ImageSource.camera);
                    },
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Future<void> _pickAndUploadPhoto(ImageSource source) async {
    try {
      final file = await _imagePicker.pickImage(
        source: source,
        imageQuality: 88,
        maxWidth: 1600,
      );

      if (file == null) {
        return;
      }

      final bytes = await file.readAsBytes();
      await _uploadSelectedPhoto(bytes: bytes, filename: file.name);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_profileSaveError(exception))));
    }
  }

  Future<void> _uploadSelectedPhoto({
    required Uint8List bytes,
    required String filename,
  }) async {
    setState(() {
      _uploadingPhoto = true;
      _error = null;
    });

    try {
      final response = await widget.repository.uploadProfilePhoto(
        bytes: bytes,
        filename: filename.isEmpty ? 'profile-photo.jpg' : filename,
      );
      final data = Map<String, dynamic>.from(
        response['data'] as Map? ?? const <String, dynamic>{},
      );
      final uploadedPhotoUrl = _stringValue(data['photo'], fallback: '');

      if (!mounted) {
        return;
      }

      setState(() {
        _photoController.text = uploadedPhotoUrl;
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Profile photo updated.')));
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _error = _profileSaveError(exception));
    } finally {
      if (mounted) {
        setState(() => _uploadingPhoto = false);
      }
    }
  }

  void _removePhoto() {
    setState(() {
      _photoController.clear();
    });
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await widget.repository.updateProfile(<String, dynamic>{
        'name': _nameController.text.trim(),
        'avatar': _nullableText(_photoController.text),
        'height_cm': _nullableDouble(_heightController.text),
        'weight_kg': _nullableDouble(_weightController.text),
        'fitness_goal_ids': _selectedGoalIds.toList()..sort(),
        'experience_level': _experienceLevel,
        'injury_notes': _nullableText(_injuriesController.text),
        'medical_notes': _nullableText(_medicalController.text),
        'member_onboarding_completed': true,
      });

      if (!mounted) {
        return;
      }
      Navigator.of(context).pop(true);
    } catch (exception) {
      if (mounted) {
        setState(() => _error = _profileSaveError(exception));
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppGradientScaffold(
      title: 'Edit Profile',
      body: SafeArea(
        bottom: false,
        child: Form(
          key: _formKey,
          child: ListView(
            physics: const BouncingScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.sm,
              AppSpacing.lg,
              AppSpacing.xl,
            ),
            children: <Widget>[
              const _EditProfileTopBar(
                title: 'Edit Profile',
                subtitle: 'Update your details, goals, and training notes.',
              ),
              const SizedBox(height: AppSpacing.md),
              _EditAnimatedSection(
                child: _EditProfileHeader(
                  imageUrl: _photoController.text.trim(),
                  name: _nameController.text.trim().isEmpty
                      ? 'Member'
                      : _nameController.text.trim(),
                  subtitle: _selectedGoalIds.isEmpty
                      ? 'Fitness profile'
                      : '${_selectedGoalIds.length} active goals',
                ),
              ),
              const SizedBox(height: 15),
              _EditAnimatedSection(
                delay: const Duration(milliseconds: 70),
                child: Row(
                  children: <Widget>[
                    Expanded(
                      child: _EditTitleCell(
                        title: _heightController.text.trim().isEmpty
                            ? '--'
                            : '${_heightController.text.trim()}cm',
                        subtitle: 'Height',
                      ),
                    ),
                    const SizedBox(width: 15),
                    Expanded(
                      child: _EditTitleCell(
                        title: _weightController.text.trim().isEmpty
                            ? '--'
                            : '${_weightController.text.trim()}kg',
                        subtitle: 'Weight',
                      ),
                    ),
                    const SizedBox(width: 15),
                    Expanded(
                      child: _EditTitleCell(
                        title: _experienceLabel(_experienceLevel),
                        subtitle: 'Level',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _EditAnimatedSection(
                delay: const Duration(milliseconds: 120),
                child: _EditGroup(
                  title: 'Basic Details',
                  children: <Widget>[
                    _EditTextField(
                      controller: _nameController,
                      label: 'Name',
                      icon: Icons.person_outline_rounded,
                      textCapitalization: TextCapitalization.words,
                      validator: (value) {
                        if ((value ?? '').trim().isEmpty) {
                          return 'Name is required.';
                        }
                        return null;
                      },
                      onChanged: (_) => setState(() {}),
                    ),
                    _EditPhotoPickerField(
                      imageUrl: _photoController.text.trim(),
                      uploading: _uploadingPhoto,
                      onChoosePhoto: _showPhotoSourceSheet,
                      onRemovePhoto: _photoController.text.trim().isEmpty
                          ? null
                          : _removePhoto,
                    ),
                    const SizedBox(height: 2),
                    _EditOptionSelector(
                      label: 'Experience Level',
                      icon: Icons.trending_up_rounded,
                      options: _experienceLevels,
                      selectedValue: _experienceLevel,
                      onChanged: (value) {
                        setState(() => _experienceLevel = value);
                      },
                      labelBuilder: _experienceLabel,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _EditAnimatedSection(
                delay: const Duration(milliseconds: 170),
                child: _EditGroup(
                  title: 'Body Metrics',
                  children: <Widget>[
                    _EditTextField(
                      controller: _heightController,
                      label: 'Height (cm)',
                      icon: Icons.height_rounded,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      onChanged: (_) => setState(() {}),
                    ),
                    _EditTextField(
                      controller: _weightController,
                      label: 'Weight (kg)',
                      icon: Icons.monitor_weight_outlined,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      onChanged: (_) => setState(() {}),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _EditAnimatedSection(
                delay: const Duration(milliseconds: 220),
                child: _EditGroup(
                  title: 'Fitness Goals',
                  children: <Widget>[
                    if (_availableGoals.isEmpty)
                      const _EditInlineNote(
                        icon: Icons.flag_outlined,
                        title: 'No goals available',
                        message:
                            'The platform goal catalog is empty right now.',
                      )
                    else
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: _availableGoals.map((goal) {
                          final id = (goal['id'] as num?)?.toInt();
                          final selected =
                              id != null && _selectedGoalIds.contains(id);

                          return _EditGoalChip(
                            label: goal['name']?.toString() ?? 'Goal',
                            selected: selected,
                            onSelected: id == null
                                ? null
                                : (value) {
                                    setState(() {
                                      if (value) {
                                        _selectedGoalIds.add(id);
                                      } else {
                                        _selectedGoalIds.remove(id);
                                      }
                                    });
                                  },
                          );
                        }).toList(),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 25),
              _EditAnimatedSection(
                delay: const Duration(milliseconds: 270),
                child: _EditGroup(
                  title: 'Training Notes',
                  children: <Widget>[
                    _EditTextField(
                      controller: _injuriesController,
                      label: 'Injuries / Limitations',
                      icon: Icons.healing_rounded,
                      minLines: 2,
                      maxLines: 3,
                    ),
                    _EditTextField(
                      controller: _medicalController,
                      label: 'Medical Notes',
                      icon: Icons.medical_information_outlined,
                      minLines: 2,
                      maxLines: 3,
                    ),
                  ],
                ),
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 18),
                _EditInlineError(message: _error!, onRetry: _save),
              ],
              const SizedBox(height: 25),
              _EditAnimatedSection(
                delay: const Duration(milliseconds: 320),
                child: _EditRoundButton(
                  title: _saving ? 'Saving...' : 'Save Profile',
                  onPressed: _saving ? null : _save,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EditProfileTopBar extends StatelessWidget {
  const _EditProfileTopBar({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        InkWell(
          onTap: () => Navigator.of(context).maybePop(),
          borderRadius: BorderRadius.circular(16),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.stroke),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 10,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: const Icon(
              Icons.arrow_back_rounded,
              color: AppColors.textPrimary,
              size: 20,
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _OverviewValueRow extends StatelessWidget {
  const _OverviewValueRow({
    required this.icon,
    required this.title,
    required this.value,
    this.onPressed,
    this.multiline = false,
  });

  final IconData icon;
  final String title;
  final String value;
  final VoidCallback? onPressed;
  final bool multiline;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
        child: Row(
          crossAxisAlignment: multiline
              ? CrossAxisAlignment.start
              : CrossAxisAlignment.center,
          children: <Widget>[
            _EditRowIcon(icon: icon),
            const SizedBox(width: 15),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
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
            if (onPressed != null) ...<Widget>[
              const SizedBox(width: 10),
              Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textMuted,
                size: 20,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _OverviewGoalsRow extends StatelessWidget {
  const _OverviewGoalsRow({required this.goals});

  final List<String> goals;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const _EditRowIcon(icon: Icons.flag_rounded),
          const SizedBox(width: 15),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Fitness Goals',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                if (goals.isEmpty)
                  Text(
                    'No goals selected',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  )
                else
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: goals.map((goal) {
                      return Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.surfaceSoft,
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(color: AppColors.stroke),
                        ),
                        child: Text(
                          goal,
                          style: Theme.of(context).textTheme.labelSmall
                              ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      );
                    }).toList(),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EditAnimatedSection extends StatelessWidget {
  const _EditAnimatedSection({required this.child, this.delay = Duration.zero});

  final Widget child;
  final Duration delay;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0, end: 1),
      duration: Duration(milliseconds: 420 + delay.inMilliseconds),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) {
        final delayed = delay == Duration.zero
            ? value
            : ((value * (420 + delay.inMilliseconds) - delay.inMilliseconds) /
                      420)
                  .clamp(0.0, 1.0);
        return Opacity(
          opacity: delayed,
          child: Transform.translate(
            offset: Offset(0, 18 * (1 - delayed)),
            child: child,
          ),
        );
      },
      child: child,
    );
  }
}

class _EditProfileHeader extends StatelessWidget {
  const _EditProfileHeader({
    required this.imageUrl,
    required this.name,
    required this.subtitle,
  });

  final String imageUrl;
  final String name;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      child: Row(
        children: <Widget>[
          _ProfileAvatar(imageUrl: imageUrl, name: name, size: 54),
          const SizedBox(width: 15),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
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

class _EditTitleCell extends StatelessWidget {
  const _EditTitleCell({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _EditGroup extends StatelessWidget {
  const _EditGroup({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }
}

class _EditTextField extends StatelessWidget {
  const _EditTextField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboardType,
    this.textCapitalization = TextCapitalization.none,
    this.validator,
    this.onChanged,
    this.minLines = 1,
    this.maxLines = 1,
  });

  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboardType;
  final TextCapitalization textCapitalization;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onChanged;
  final int minLines;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        textCapitalization: textCapitalization,
        validator: validator,
        onChanged: onChanged,
        minLines: minLines,
        maxLines: maxLines,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          color: AppColors.textPrimary,
          fontWeight: FontWeight.w700,
        ),
        decoration: InputDecoration(
          labelText: label,
          labelStyle: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
          prefixIcon: Icon(icon, color: AppColors.primaryBright, size: 18),
          filled: true,
          fillColor: AppColors.surfaceSoft,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 14,
            vertical: 14,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppColors.stroke),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppColors.stroke),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppColors.primaryBright),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppColors.error),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppColors.error),
          ),
        ),
      ),
    );
  }
}

class _EditGoalChip extends StatelessWidget {
  const _EditGoalChip({
    required this.label,
    required this.selected,
    required this.onSelected,
  });

  final String label;
  final bool selected;
  final ValueChanged<bool>? onSelected;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: onSelected,
      showCheckmark: false,
      labelStyle: Theme.of(context).textTheme.bodySmall?.copyWith(
        color: selected ? Colors.white : AppColors.textPrimary,
        fontWeight: FontWeight.w700,
      ),
      selectedColor: AppColors.primaryBright,
      backgroundColor: AppColors.surfaceSoft,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(999),
        side: BorderSide(
          color: selected ? AppColors.primaryBright : AppColors.stroke,
        ),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
    );
  }
}

class _EditOptionSelector extends StatelessWidget {
  const _EditOptionSelector({
    required this.label,
    required this.icon,
    required this.options,
    required this.selectedValue,
    required this.onChanged,
    required this.labelBuilder,
  });

  final String label;
  final IconData icon;
  final List<String> options;
  final String selectedValue;
  final ValueChanged<String> onChanged;
  final String Function(String value) labelBuilder;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(icon, color: AppColors.primaryBright, size: 18),
              const SizedBox(width: 10),
              Text(
                label,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: options.map((option) {
              return _EditGoalChip(
                label: labelBuilder(option),
                selected: selectedValue == option,
                onSelected: (_) => onChanged(option),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }
}

class _EditPhotoPickerField extends StatelessWidget {
  const _EditPhotoPickerField({
    required this.imageUrl,
    required this.uploading,
    required this.onChoosePhoto,
    required this.onRemovePhoto,
  });

  final String imageUrl;
  final bool uploading;
  final VoidCallback onChoosePhoto;
  final VoidCallback? onRemovePhoto;

  @override
  Widget build(BuildContext context) {
    final hasPhoto = imageUrl.trim().isNotEmpty;

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.stroke),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                const Icon(
                  Icons.photo_camera_back_outlined,
                  color: AppColors.primaryBright,
                  size: 18,
                ),
                const SizedBox(width: 10),
                Text(
                  'Profile Photo',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (hasPhoto)
              ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: SizedBox(
                  width: 88,
                  height: 88,
                  child: Image.network(
                    imageUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(
                      color: AppColors.surface,
                      child: const Icon(
                        Icons.person_outline_rounded,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ),
                ),
              )
            else
              Container(
                width: 88,
                height: 88,
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: AppColors.stroke),
                ),
                child: const Icon(
                  Icons.person_outline_rounded,
                  color: AppColors.textMuted,
                  size: 28,
                ),
              ),
            const SizedBox(height: 12),
            Text(
              uploading
                  ? 'Uploading photo...'
                  : hasPhoto
                  ? 'Choose a new image or remove the current one.'
                  : 'Add a clean profile image from your device.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: <Widget>[
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: uploading ? null : onChoosePhoto,
                    icon: Icon(
                      hasPhoto
                          ? Icons.sync_alt_rounded
                          : Icons.add_a_photo_outlined,
                    ),
                    label: Text(hasPhoto ? 'Change Photo' : 'Choose Photo'),
                  ),
                ),
                if (onRemovePhoto != null) ...<Widget>[
                  const SizedBox(width: 10),
                  OutlinedButton(
                    onPressed: uploading ? null : onRemovePhoto,
                    child: const Text('Remove'),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _EditPickerAction extends StatelessWidget {
  const _EditPickerAction({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surfaceSoft,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.stroke),
        ),
        child: Row(
          children: <Widget>[
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: AppColors.stroke),
              ),
              child: Icon(icon, color: AppColors.primaryBright, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    title,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(
              Icons.chevron_right_rounded,
              color: AppColors.textMuted,
              size: 20,
            ),
          ],
        ),
      ),
    );
  }
}

class _EditInlineNote extends StatelessWidget {
  const _EditInlineNote({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Row(
        children: <Widget>[
          _EditRowIcon(icon: icon),
          const SizedBox(width: 15),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  message,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                    height: 1.4,
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

class _EditInlineError extends StatelessWidget {
  const _EditInlineError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: AppColors.error.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.16)),
      ),
      child: Row(
        children: <Widget>[
          const Icon(Icons.error_outline_rounded, color: AppColors.error),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.error,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          TextButton(
            onPressed: onRetry,
            child: const Text(
              'Retry',
              style: TextStyle(color: AppColors.error),
            ),
          ),
        ],
      ),
    );
  }
}

class _EditIconButton extends StatelessWidget {
  const _EditIconButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.stroke),
        ),
        child: Icon(icon, color: AppColors.textPrimary, size: 18),
      ),
    );
  }
}

class _EditRowIcon extends StatelessWidget {
  const _EditRowIcon({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 30,
      height: 30,
      decoration: BoxDecoration(
        color: AppColors.surfaceSoft,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.stroke),
      ),
      child: Icon(icon, color: AppColors.primaryBright, size: 16),
    );
  }
}

class _EditRoundButton extends StatelessWidget {
  const _EditRoundButton({required this.title, required this.onPressed});

  final String title;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onPressed == null ? 0.65 : 1,
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          height: 50,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.primaryBright,
            borderRadius: BorderRadius.circular(18),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: AppColors.primaryBright.withValues(alpha: 0.18),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Text(
            title,
            style: TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}

class _EditFitColor {
  static const Color black = Color(0xFF1D1617);
  static const Color white = Colors.white;
  static const Color lightGray = Color(0xFFF7F8F8);
  static const Color primaryEnd = Color(0xFF92A3FD);
}

class _ProfileCompletionScreen extends StatelessWidget {
  const _ProfileCompletionScreen({required this.profile});

  final Map<String, dynamic> profile;

  @override
  Widget build(BuildContext context) {
    final completion = _completionData(profile);
    final currentGym = profile['current_gym'] is Map
        ? Map<String, dynamic>.from(profile['current_gym'] as Map)
        : const <String, dynamic>{};
    final currentBranch = profile['current_branch'] is Map
        ? Map<String, dynamic>.from(profile['current_branch'] as Map)
        : const <String, dynamic>{};
    final assignedTrainer = profile['assigned_trainer'] is Map
        ? Map<String, dynamic>.from(profile['assigned_trainer'] as Map)
        : const <String, dynamic>{};

    return Scaffold(
      backgroundColor: _EditFitColor.white,
      appBar: AppBar(
        backgroundColor: _EditFitColor.white,
        centerTitle: true,
        elevation: 0,
        title: Text(
          'Profile Completion',
          style: TextStyle(
            color: _EditFitColor.black,
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
        actions: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: _EditIconButton(
              icon: Icons.close_rounded,
              onTap: () => Navigator.of(context).maybePop(),
            ),
          ),
        ],
      ),
      body: ListView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(25, 15, 25, 30),
        children: <Widget>[
          _EditAnimatedSection(
            child: _EditGroup(
              title: 'Completion Snapshot',
              children: <Widget>[
                _OverviewValueRow(
                  icon: Icons.verified_user_rounded,
                  title: 'Profile Ready',
                  value: '${completion.percent}% complete',
                ),
                ClipRRect(
                  borderRadius: BorderRadius.circular(999),
                  child: LinearProgressIndicator(
                    value: completion.percent / 100,
                    minHeight: 8,
                    backgroundColor: _EditFitColor.lightGray,
                    valueColor: const AlwaysStoppedAnimation<Color>(
                      _EditFitColor.primaryEnd,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 25),
          _EditAnimatedSection(
            delay: const Duration(milliseconds: 70),
            child: _EditGroup(
              title: 'Gym Access',
              children: <Widget>[
                _OverviewValueRow(
                  icon: Icons.fitness_center_rounded,
                  title: 'Current Gym',
                  value: _stringValue(currentGym['name']),
                ),
                _OverviewValueRow(
                  icon: Icons.location_on_outlined,
                  title: 'Current Branch',
                  value: _stringValue(currentBranch['name']),
                ),
                _OverviewValueRow(
                  icon: Icons.support_agent_rounded,
                  title: 'Assigned Trainer',
                  value: _stringValue(assignedTrainer['name']),
                ),
              ],
            ),
          ),
          const SizedBox(height: 25),
          _EditAnimatedSection(
            delay: const Duration(milliseconds: 120),
            child: _EditGroup(
              title: completion.missingLabels.isEmpty
                  ? 'Profile Complete'
                  : 'Recommended Updates',
              children: completion.missingLabels.isEmpty
                  ? <Widget>[
                      const _EditInlineNote(
                        icon: Icons.celebration_rounded,
                        title: 'Everything looks complete',
                        message:
                            'Your supported profile fields are filled for the current member experience.',
                      ),
                    ]
                  : completion.missingLabels
                        .map(
                          (label) => _OverviewValueRow(
                            icon: Icons.radio_button_unchecked_rounded,
                            title: label,
                            value: 'Pending',
                          ),
                        )
                        .toList(),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileAvatar extends StatelessWidget {
  const _ProfileAvatar({
    required this.imageUrl,
    required this.name,
    required this.size,
  });

  final String imageUrl;
  final String name;
  final double size;

  @override
  Widget build(BuildContext context) {
    final initials = name.trim().isEmpty
        ? 'M'
        : name
              .trim()
              .split(RegExp(r'\s+'))
              .where((part) => part.isNotEmpty)
              .take(2)
              .map((part) => part[0].toUpperCase())
              .join();

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.surfaceSoft,
        border: Border.all(color: AppColors.stroke),
      ),
      child: Padding(
        padding: const EdgeInsets.all(2),
        child: CircleAvatar(
          backgroundColor: AppColors.surface,
          backgroundImage: imageUrl.trim().isNotEmpty
              ? NetworkImage(imageUrl.trim())
              : null,
          child: imageUrl.trim().isNotEmpty
              ? null
              : Text(
                  initials,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                ),
        ),
      ),
    );
  }
}

class _ProfileSkeleton extends StatelessWidget {
  const _ProfileSkeleton();

  @override
  Widget build(BuildContext context) {
    return SkeletonPulse(
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: const <Widget>[
          SkeletonProfileHeader(),
          SizedBox(height: AppSpacing.lg),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonWorkoutCard(),
          SizedBox(height: AppSpacing.md),
          SkeletonWorkoutCard(),
        ],
      ),
    );
  }
}

class _SuccessPill extends StatelessWidget {
  const _SuccessPill();

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0.88, end: 1),
      duration: const Duration(milliseconds: 360),
      curve: Curves.easeOutBack,
      builder: (context, value, child) => Transform.scale(
        scale: value,
        child: Opacity(opacity: value.clamp(0, 1), child: child),
      ),
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.sm,
        ),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(999),
          color: AppColors.statusCompleted.withValues(alpha: 0.14),
          border: Border.all(
            color: AppColors.statusCompleted.withValues(alpha: 0.26),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: const <Widget>[
            Icon(
              Icons.check_circle_rounded,
              color: AppColors.statusCompleted,
              size: 18,
            ),
            SizedBox(width: AppSpacing.xs),
            Text('Profile updated successfully'),
          ],
        ),
      ),
    );
  }
}

class _CompletionData {
  const _CompletionData({required this.percent, required this.missingLabels});

  final int percent;
  final List<String> missingLabels;
}

_CompletionData _completionData(Map<String, dynamic> profile) {
  final fields = <MapEntry<String, String>>[
    MapEntry<String, String>('name', 'Add your name'),
    MapEntry<String, String>('photo', 'Add a profile photo URL'),
    MapEntry<String, String>('height_cm', 'Add your height'),
    MapEntry<String, String>('weight_kg', 'Add your weight'),
    const MapEntry<String, String>(
      'fitness_goals',
      'Set at least one fitness goal',
    ),
    MapEntry<String, String>('experience_level', 'Set your experience level'),
    MapEntry<String, String>(
      'injuries_limitations',
      'Add injury or limitation notes',
    ),
  ];

  final missing = <String>[];
  var completed = 0;

  for (final field in fields) {
    final value = profile[field.key];
    final hasValue = field.key == 'fitness_goals'
        ? _fitnessGoalNames(profile).isNotEmpty
        : value is num
        ? true
        : value != null && value.toString().trim().isNotEmpty;

    if (hasValue) {
      completed += 1;
    } else {
      missing.add(field.value);
    }
  }

  final percent = ((completed / fields.length) * 100).round();
  return _CompletionData(percent: percent, missingLabels: missing);
}

bool _isProfileEffectivelyEmpty(Map<String, dynamic> profile) {
  return _completionData(profile).percent <= 12;
}

List<String> _fitnessGoalNames(Map<String, dynamic> profile) {
  final goals = profile['fitness_goals'] as List<dynamic>? ?? const [];

  if (goals.isNotEmpty) {
    return goals
        .map((item) => Map<String, dynamic>.from(item as Map))
        .map((item) => item['name']?.toString().trim() ?? '')
        .where((value) => value.isNotEmpty)
        .toList();
  }

  final fallback = profile['fitness_goal']?.toString().trim() ?? '';
  if (fallback.isEmpty) {
    return const [];
  }

  return fallback
      .split(',')
      .map((value) => value.trim())
      .where((value) => value.isNotEmpty)
      .toList();
}

String _stringValue(Object? value, {String fallback = 'Not added yet'}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

String _numericLabel(Object? value, String suffix) {
  if (value == null) {
    return 'Not added yet';
  }

  final number = num.tryParse(value.toString());
  if (number == null) {
    return 'Not added yet';
  }

  final normalized = number % 1 == 0
      ? number.toInt().toString()
      : number.toString();
  return '$normalized $suffix';
}

String _editableNumber(Object? value) {
  if (value == null) {
    return '';
  }
  final number = num.tryParse(value.toString());
  if (number == null) {
    return '';
  }
  return number % 1 == 0 ? number.toInt().toString() : number.toString();
}

double? _nullableDouble(String value) {
  final trimmed = value.trim();
  if (trimmed.isEmpty) {
    return null;
  }
  return double.tryParse(trimmed);
}

String? _nullableText(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

String _profileSaveError(Object exception) {
  if (exception is DioException) {
    final data = exception.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) {
          return first.first.toString();
        }
        return first.toString();
      }

      final message = data['message']?.toString();
      if (message != null && message.trim().isNotEmpty) {
        return message;
      }
    }

    switch (exception.type) {
      case DioExceptionType.connectionError:
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.sendTimeout:
        return 'Network error. Please check your connection and try again.';
      default:
        return 'Profile update failed. Please check the details and try again.';
    }
  }

  return exception.toString().replaceFirst('Exception: ', '');
}

String _experienceLabel(String value) {
  switch (value.trim().toLowerCase()) {
    case 'beginner':
      return 'Beginner';
    case 'intermediate':
      return 'Intermediate';
    case 'advanced':
      return 'Advanced';
    default:
      return 'Beginner';
  }
}
