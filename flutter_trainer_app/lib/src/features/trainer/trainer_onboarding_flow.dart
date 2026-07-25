import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:timelines_plus/timelines_plus.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import 'trainer_photo_picker.dart';
import 'trainer_repository.dart';

class TrainerOnboardingFlow extends StatefulWidget {
  const TrainerOnboardingFlow({
    super.key,
    required this.repository,
    required this.contextData,
    required this.onFinished,
  });

  final TrainerRepository repository;
  final Map<String, dynamic> contextData;
  final Future<void> Function() onFinished;

  @override
  State<TrainerOnboardingFlow> createState() => _TrainerOnboardingFlowState();
}

class _TrainerOnboardingFlowState extends State<TrainerOnboardingFlow> {
  static const _defaultSpecializations = [
    'Strength',
    'Fat loss',
    'Body recomposition',
    'Mobility',
    'Sports conditioning',
  ];
  static const _totalSteps = 6;

  int _step = 1;
  bool _saving = false;
  bool _uploadingPhoto = false;
  bool _uploadingCertification = false;
  late final TextEditingController _photoController;
  late final TextEditingController _bioController;
  late final TextEditingController _experienceController;
  late final TextEditingController _certificationNameController;
  late final TextEditingController _certificationIssuerController;
  late final TextEditingController _certificationYearController;
  late final TextEditingController _languagesController;
  late final TextEditingController _availabilityController;
  final Set<String> _selectedSpecializations = <String>{};
  final List<Map<String, dynamic>> _certifications = <Map<String, dynamic>>[];
  Map<String, dynamic>? _pendingCertificationProof;
  Uint8List? _profilePhotoPreviewBytes;

  Map<String, dynamic> get _profile => Map<String, dynamic>.from(
    widget.contextData['trainer_profile'] as Map? ?? const {},
  );
  Map<String, dynamic> get _user =>
      Map<String, dynamic>.from(widget.contextData['user'] as Map? ?? const {});
  List<String> get _specializationOptions {
    final catalog =
        (widget.contextData['trainer_specializations'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => item['name']?.toString().trim() ?? '')
            .where((item) => item.isNotEmpty)
            .toList();

    return catalog.isEmpty ? _defaultSpecializations : catalog;
  }

  @override
  void initState() {
    super.initState();
    _step = ((_user['trainer_onboarding_step'] as num?)?.toInt() ?? 1).clamp(
      1,
      _totalSteps,
    );
    _photoController = TextEditingController(
      text: _profile['profile_photo_url']?.toString() ?? '',
    );
    _bioController = TextEditingController(
      text: _profile['bio']?.toString() ?? '',
    );
    _experienceController = TextEditingController(
      text: _profile['experience_years']?.toString() ?? '',
    );
    _certificationNameController = TextEditingController();
    _certificationIssuerController = TextEditingController();
    _certificationYearController = TextEditingController();
    _languagesController = TextEditingController(
      text: _list(_profile['languages']).join(', '),
    );
    _availabilityController = TextEditingController(
      text: _profile['availability_notes']?.toString() ?? '',
    );
    _certifications.addAll(
      _normalizeCertifications(
        _profile['certifications'] as List<dynamic>? ?? const [],
      ),
    );
    _selectedSpecializations.addAll(
      _normalizeSpecializationValues(
        _profile['specializations'] as List<dynamic>? ?? const [],
      ),
    );
  }

  @override
  void dispose() {
    _photoController.dispose();
    _bioController.dispose();
    _experienceController.dispose();
    _certificationNameController.dispose();
    _certificationIssuerController.dispose();
    _certificationYearController.dispose();
    _languagesController.dispose();
    _availabilityController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: widget.onFinished,
        child: LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxWidth < 390;
            final horizontalPadding = compact ? AppSpacing.sm : AppSpacing.lg;

            return ListView(
              padding: EdgeInsets.fromLTRB(
                horizontalPadding,
                AppSpacing.md,
                horizontalPadding,
                AppSpacing.lg,
              ),
              children: [
                Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      children: [
                        _OnboardingHero(step: _step, totalSteps: _totalSteps),
                        const SizedBox(height: AppSpacing.md),
                        AnimatedSwitcher(
                          duration: const Duration(milliseconds: 240),
                          transitionBuilder: (child, animation) {
                            final curved = CurvedAnimation(
                              parent: animation,
                              curve: Curves.easeOutCubic,
                            );
                            return FadeTransition(
                              opacity: curved,
                              child: SlideTransition(
                                position: Tween<Offset>(
                                  begin: const Offset(0.04, 0.015),
                                  end: Offset.zero,
                                ).animate(curved),
                                child: child,
                              ),
                            );
                          },
                          child: Padding(
                            key: ValueKey<int>(_step),
                            padding: EdgeInsets.symmetric(
                              horizontal: compact ? 4 : 8,
                              vertical: 2,
                            ),
                            child: _buildStep(context),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        LayoutBuilder(
                          builder: (context, actionConstraints) {
                            final stacked = actionConstraints.maxWidth < 420;

                            if (stacked) {
                              return Column(
                                children: [
                                  GradientButton(
                                    label: _primaryLabel(_step),
                                    icon: _step == _totalSteps
                                        ? Icons.check_rounded
                                        : Icons.arrow_forward_rounded,
                                    onPressed: _saving ? null : _submitStep,
                                    loading: _saving,
                                    expanded: true,
                                  ),
                                  if (_step > 1) ...[
                                    const SizedBox(height: AppSpacing.sm),
                                    GradientButton(
                                      label: 'Back',
                                      icon: Icons.arrow_back_rounded,
                                      onPressed: _saving ? null : _goBack,
                                      expanded: true,
                                      variant: GradientButtonVariant.secondary,
                                    ),
                                  ],
                                ],
                              );
                            }

                            return Row(
                              children: [
                                if (_step > 1)
                                  Expanded(
                                    child: GradientButton(
                                      label: 'Back',
                                      icon: Icons.arrow_back_rounded,
                                      onPressed: _saving ? null : _goBack,
                                      expanded: true,
                                      variant: GradientButtonVariant.secondary,
                                    ),
                                  ),
                                if (_step > 1)
                                  const SizedBox(width: AppSpacing.md),
                                Expanded(
                                  flex: 2,
                                  child: GradientButton(
                                    label: _primaryLabel(_step),
                                    icon: _step == _totalSteps
                                        ? Icons.check_rounded
                                        : Icons.arrow_forward_rounded,
                                    onPressed: _saving ? null : _submitStep,
                                    loading: _saving,
                                    expanded: true,
                                  ),
                                ),
                              ],
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _buildStep(BuildContext context) {
    switch (_step) {
      case 1:
        return const _StepShell(
          eyebrow: 'Welcome',
          title: 'Set up your trainer profile.',
          description: 'Complete the required details to continue.',
          child: SizedBox.shrink(),
        );
      case 2:
        return _StepShell(
          eyebrow: 'Profile',
          title: 'Add your profile details.',
          description: 'Upload a photo and write a short bio.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _TrainerIdentityCard(
                photoUrl: _photoController.text.trim(),
                previewBytes: _profilePhotoPreviewBytes,
                uploading: _uploadingPhoto,
                onUpload: _uploadingPhoto ? null : _pickAndUploadPhoto,
              ),
              const SizedBox(height: AppSpacing.md),
              _PremiumBioComposer(controller: _bioController),
            ],
          ),
        );
      case 3:
        return _StepShell(
          eyebrow: 'Specialties',
          title: 'Choose your specialties.',
          description: 'Select one or more specialties.',
          child: _SpecializationGrid(
            options: _specializationOptions,
            selected: _selectedSpecializations,
            onToggle: (option) {
              setState(() {
                if (_selectedSpecializations.contains(option)) {
                  _selectedSpecializations.remove(option);
                } else {
                  _selectedSpecializations.add(option);
                }
              });
            },
          ),
        );
      case 4:
        return _StepShell(
          eyebrow: 'Credentials',
          title: 'Add your credentials.',
          description: 'Enter experience, languages, and certifications.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _ExperienceHeroCard(controller: _experienceController),
              const SizedBox(height: AppSpacing.md),
              _LanguageInputCard(controller: _languagesController),
              const SizedBox(height: AppSpacing.md),
              _CertificationBuilder(
                certifications: _certifications,
                nameController: _certificationNameController,
                issuerController: _certificationIssuerController,
                yearController: _certificationYearController,
                pendingProof: _pendingCertificationProof,
                uploading: _uploadingCertification,
                onUpload: _uploadingCertification
                    ? null
                    : _pickAndUploadCertification,
                onAdd: _addCertification,
                onRemove: (index) {
                  setState(() => _certifications.removeAt(index));
                },
              ),
            ],
          ),
        );
      case 5:
        return _StepShell(
          eyebrow: 'Availability',
          title: 'Add your availability.',
          description: 'Enter your schedule notes.',
          child: _LabeledTextarea(
            title: 'Availability notes',
            icon: Icons.schedule_rounded,
            controller: _availabilityController,
            hintText: 'Morning batches, evening slots, weekly off...',
          ),
        );
      case 6:
        return _StepShell(
          eyebrow: 'Finish',
          title: 'Finish setup.',
          description: 'Review and continue.',
          child: SizedBox.shrink(),
        );
      default:
        return const SizedBox.shrink();
    }
  }

  String _primaryLabel(int step) {
    switch (step) {
      case 1:
        return 'Start Setup';
      case 2:
        return 'Save Profile';
      case 3:
        return 'Save Specialization';
      case 4:
        return 'Save Credentials';
      case 5:
        return 'Save Availability';
      case 6:
        return 'Finish Setup';
      default:
        return 'Continue';
    }
  }

  void _goBack() {
    FocusScope.of(context).unfocus();
    setState(() {
      _step = (_step - 1).clamp(1, _totalSteps);
    });
  }

  Future<void> _submitStep() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
    });

    try {
      switch (_step) {
        case 1:
          await _persist({'trainer_onboarding_step': 2});
          break;
        case 2:
          await _persist({
            'bio': _emptyToNull(_bioController.text),
            'trainer_onboarding_step': 3,
          });
          break;
        case 3:
          await _persist({
            'specializations': _selectedSpecializations.toList(),
            'trainer_onboarding_step': 4,
          });
          break;
        case 4:
          await _persist({
            'experience_years': int.tryParse(_experienceController.text.trim()),
            'certifications': _certificationPayload(),
            'languages': _languagePayload(),
            'trainer_onboarding_step': 5,
          });
          break;
        case 5:
          await _persist({
            'availability_notes': _emptyToNull(_availabilityController.text),
            'trainer_onboarding_step': 6,
          });
          break;
        case 6:
          await _persist({
            'trainer_onboarding_step': 6,
            'trainer_onboarding_completed': true,
          });
          await widget.onFinished();
          return;
      }

      if (mounted) {
        setState(() => _step = (_step + 1).clamp(1, _totalSteps));
      }
    } catch (exception) {
      if (mounted) {
        final message = exception.toString().replaceFirst('Exception: ', '');
        await _showErrorDialog(message);
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _persist(Map<String, dynamic> payload) async {
    final cleaned = <String, dynamic>{};
    payload.forEach((key, value) {
      if (value != null) {
        cleaned[key] = value;
      }
    });
    await widget.repository.updateProfile(cleaned);
  }

  Future<void> _pickAndUploadPhoto() async {
    if (_uploadingPhoto) {
      return;
    }

    setState(() => _uploadingPhoto = true);
    try {
      final picked = await TrainerPhotoPicker().pickCompressedProfilePhoto();
      if (picked == null) {
        return;
      }
      if (mounted) {
        setState(() => _profilePhotoPreviewBytes = picked.bytes);
      }
      final response = await widget.repository.uploadProfilePhoto(
        bytes: picked.bytes,
        filename: picked.filename,
      );
      final data = _map(response['data']);
      final photoUrl =
          data['profile_photo_url']?.toString() ??
          _map(data['trainer_profile'])['profile_photo_url']?.toString();
      if (photoUrl == null || photoUrl.trim().isEmpty) {
        throw Exception('Photo uploaded but no image URL was returned.');
      }
      if (!mounted) {
        return;
      }
      final trainerProfile = Map<String, dynamic>.from(
        widget.contextData['trainer_profile'] as Map? ?? const {},
      );
      final user = Map<String, dynamic>.from(
        widget.contextData['user'] as Map? ?? const {},
      );
      widget.contextData['trainer_profile'] = {
        ...trainerProfile,
        'profile_photo_url': photoUrl,
      };
      widget.contextData['user'] = {...user, 'avatar': photoUrl};
      setState(() => _photoController.text = photoUrl);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Profile photo uploaded.')));
    } catch (exception) {
      if (!mounted) {
        return;
      }
      await _showErrorDialog(
        exception.toString().replaceFirst('Exception: ', ''),
      );
    } finally {
      if (mounted) {
        setState(() => _uploadingPhoto = false);
      }
    }
  }

  Future<void> _pickAndUploadCertification() async {
    if (_uploadingCertification) {
      return;
    }

    setState(() => _uploadingCertification = true);
    try {
      final picked = await TrainerPhotoPicker()
          .pickCompressedCertificationImage();
      if (picked == null) {
        return;
      }
      final response = await widget.repository.uploadCertificationFile(
        bytes: picked.bytes,
        filename: picked.filename,
      );
      final data = _map(response['data']);
      final fileUrl = data['certification_file_url']?.toString();
      if (fileUrl == null || fileUrl.trim().isEmpty) {
        throw Exception(
          'Certificate uploaded but no storage reference was returned.',
        );
      }
      if (!mounted) {
        return;
      }
      setState(() {
        _pendingCertificationProof = {
          'file_url': fileUrl,
          'file_name': data['file_name']?.toString() ?? picked.filename,
          'mime_type': data['mime_type']?.toString(),
          'file_size': data['file_size'],
          'file_type':
              data['file_type']?.toString() ??
              _proofTypeFromName(picked.filename),
        }..removeWhere((_, value) => value == null);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Certification proof uploaded.')),
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }
      await _showErrorDialog(
        exception.toString().replaceFirst('Exception: ', ''),
      );
    } finally {
      if (mounted) {
        setState(() => _uploadingCertification = false);
      }
    }
  }

  void _addCertification() {
    final name = _certificationNameController.text.trim();
    final issuer = _certificationIssuerController.text.trim();
    final year = int.tryParse(_certificationYearController.text.trim());

    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter the certification name first.')),
      );
      return;
    }

    setState(() {
      _certifications.add({
        'name': name,
        if (issuer.isNotEmpty) 'issuer': issuer,
        if (year != null) 'issued_year': year,
        if (_pendingCertificationProof != null) ..._pendingCertificationProof!,
      });
      _certificationNameController.clear();
      _certificationIssuerController.clear();
      _certificationYearController.clear();
      _pendingCertificationProof = null;
    });
  }

  String? _emptyToNull(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  Map<String, dynamic> _map(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }
    return const <String, dynamic>{};
  }

  List<String> _list(dynamic value) {
    if (value is List) {
      return value
          .map((item) {
            if (item is Map) {
              return item['name']?.toString().trim() ?? '';
            }
            return item.toString().trim();
          })
          .where((item) => item.isNotEmpty)
          .toList();
    }
    return const <String>[];
  }

  List<Map<String, dynamic>> _normalizeCertifications(List<dynamic> values) {
    return values
        .map((item) {
          if (item is Map) {
            final mapped = Map<String, dynamic>.from(item);
            final name = mapped['name']?.toString().trim() ?? '';
            if (name.isEmpty) {
              return null;
            }

            return <String, dynamic>{
              'name': name,
              if ((mapped['issuer']?.toString().trim() ?? '').isNotEmpty)
                'issuer': mapped['issuer']?.toString().trim(),
              if (mapped['issued_year'] != null)
                'issued_year': int.tryParse(mapped['issued_year'].toString()),
              if ((mapped['file_url']?.toString().trim() ?? '').isNotEmpty)
                'file_url': mapped['file_url']?.toString().trim(),
              if ((mapped['file_name']?.toString().trim() ?? '').isNotEmpty)
                'file_name': mapped['file_name']?.toString().trim(),
              if ((mapped['mime_type']?.toString().trim() ?? '').isNotEmpty)
                'mime_type': mapped['mime_type']?.toString().trim(),
              if (mapped['file_size'] != null)
                'file_size': int.tryParse(mapped['file_size'].toString()),
              if ((mapped['file_type']?.toString().trim() ?? '').isNotEmpty)
                'file_type': mapped['file_type']?.toString().trim(),
            }..removeWhere((_, value) => value == null);
          }

          final name = item.toString().trim();
          if (name.isEmpty) {
            return null;
          }

          return <String, dynamic>{'name': name};
        })
        .whereType<Map<String, dynamic>>()
        .toList();
  }

  List<Map<String, dynamic>> _certificationPayload() {
    return _certifications
        .map((item) => Map<String, dynamic>.from(item))
        .where((item) => (item['name']?.toString().trim() ?? '').isNotEmpty)
        .toList();
  }

  List<String> _languagePayload() {
    return _languagesController.text
        .split(',')
        .map((item) => item.trim())
        .where((item) => item.isNotEmpty)
        .toSet()
        .toList();
  }

  List<String> _normalizeSpecializationValues(List<dynamic> values) {
    final options = _specializationOptions;
    final byKey = <String, String>{
      for (final option in options) _specializationKey(option): option,
    };

    return values
        .map((item) => item.toString().trim())
        .where((item) => item.isNotEmpty)
        .map((item) => byKey[_specializationKey(item)])
        .whereType<String>()
        .toSet()
        .toList();
  }

  String _specializationKey(String value) {
    return value.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), '');
  }

  String _proofTypeFromName(String filename) {
    return filename.toLowerCase().endsWith('.pdf') ? 'pdf' : 'image';
  }

  Future<void> _showErrorDialog(String message) {
    return showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: const EdgeInsets.symmetric(horizontal: 24),
          child: PremiumCard(
            padding: const EdgeInsets.all(18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [
                            AppColors.accentNeon.withValues(alpha: 0.84),
                            AppColors.accentPurple.withValues(alpha: 0.84),
                          ],
                        ),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      alignment: Alignment.center,
                      child: const Icon(
                        Icons.info_outline_rounded,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'We could not continue',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(13),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceStrong,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: AppColors.strokeStrong),
                  ),
                  child: Text(
                    message,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textSecondary,
                      height: 1.3,
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                GradientButton(
                  label: 'Dismiss',
                  onPressed: () => Navigator.of(dialogContext).pop(),
                  expanded: true,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _StepShell extends StatelessWidget {
  const _StepShell({
    required this.eyebrow,
    required this.title,
    required this.description,
    required this.child,
  });

  final String eyebrow;
  final String title;
  final String description;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: AppColors.primaryBright.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: AppColors.primaryBright.withValues(alpha: 0.18),
            ),
          ),
          child: Text(
            eyebrow.toUpperCase(),
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.45,
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: AppSpacing.xs),
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 560),
          child: Text(
            description,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: AppColors.textSecondary,
              height: 1.45,
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        child,
      ],
    );
  }
}

class _OnboardingHero extends StatelessWidget {
  const _OnboardingHero({required this.step, required this.totalSteps});

  final int step;
  final int totalSteps;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 76,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final itemExtent = constraints.maxWidth / totalSteps;

          return FixedTimeline.tileBuilder(
            theme: TimelineThemeData(
              direction: Axis.horizontal,
              nodePosition: 0.22,
              connectorTheme: const ConnectorThemeData(
                thickness: 3,
                color: AppColors.strokeStrong,
              ),
              indicatorTheme: const IndicatorThemeData(
                position: 0.22,
                size: 24,
              ),
            ),
            builder: TimelineTileBuilder.connected(
              connectionDirection: ConnectionDirection.before,
              itemExtentBuilder: (_, __) => itemExtent,
              contentsBuilder: (context, index) => Padding(
                padding: const EdgeInsets.only(top: 12),
                child: _AnimatedStepLabel(
                  label: _stepTitle(index + 1),
                  active: index + 1 == step,
                ),
              ),
              connectorBuilder: (_, index, __) =>
                  _AnimatedTimelineConnector(complete: index < step - 1),
              indicatorBuilder: (_, index) {
                final itemStep = index + 1;
                return _AnimatedStepIndicator(
                  stepNumber: itemStep,
                  active: itemStep == step,
                  complete: itemStep < step,
                );
              },
              itemCount: totalSteps,
            ),
          );
        },
      ),
    );
  }
}

class _AnimatedStepLabel extends StatelessWidget {
  const _AnimatedStepLabel({required this.label, required this.active});

  final String label;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return AnimatedDefaultTextStyle(
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
      textAlign: TextAlign.center,
      style:
          Theme.of(context).textTheme.labelSmall?.copyWith(
            color: active ? AppColors.textPrimary : AppColors.textSecondary,
            fontWeight: active ? FontWeight.w800 : FontWeight.w600,
            height: 1.15,
          ) ??
          const TextStyle(),
      child: Text(label, maxLines: 2, overflow: TextOverflow.ellipsis),
    );
  }
}

class _AnimatedTimelineConnector extends StatelessWidget {
  const _AnimatedTimelineConnector({required this.complete});

  final bool complete;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOutCubic,
        height: 3,
        decoration: BoxDecoration(
          color: complete ? AppColors.primary : AppColors.strokeStrong,
          borderRadius: BorderRadius.circular(999),
          boxShadow: complete
              ? [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.18),
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  ),
                ]
              : const [],
        ),
      ),
    );
  }
}

class _AnimatedStepIndicator extends StatelessWidget {
  const _AnimatedStepIndicator({
    required this.stepNumber,
    required this.active,
    required this.complete,
  });

  final int stepNumber;
  final bool active;
  final bool complete;

  @override
  Widget build(BuildContext context) {
    final highlighted = active || complete;

    return AnimatedScale(
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutBack,
      scale: active ? 1.12 : 1,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 260),
        curve: Curves.easeOutCubic,
        width: active ? 28 : 22,
        height: active ? 28 : 22,
        decoration: BoxDecoration(
          gradient: highlighted
              ? const LinearGradient(
                  colors: [AppColors.primaryBright, AppColors.primary],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
              : null,
          color: highlighted ? null : Colors.white,
          shape: BoxShape.circle,
          border: Border.all(
            color: highlighted ? Colors.transparent : AppColors.strokeStrong,
            width: 1.5,
          ),
          boxShadow: highlighted
              ? [
                  BoxShadow(
                    color: AppColors.primary.withValues(
                      alpha: active ? 0.26 : 0.16,
                    ),
                    blurRadius: active ? 18 : 10,
                    offset: const Offset(0, 6),
                  ),
                ]
              : const [],
        ),
        alignment: Alignment.center,
        child: AnimatedDefaultTextStyle(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          style:
              Theme.of(context).textTheme.labelSmall?.copyWith(
                color: highlighted ? Colors.white : AppColors.textSecondary,
                fontWeight: FontWeight.w800,
              ) ??
              const TextStyle(),
          child: Text('$stepNumber'),
        ),
      ),
    );
  }
}

class _TrainerIdentityCard extends StatelessWidget {
  const _TrainerIdentityCard({
    required this.photoUrl,
    required this.previewBytes,
    required this.uploading,
    required this.onUpload,
  });

  final String photoUrl;
  final Uint8List? previewBytes;
  final bool uploading;
  final VoidCallback? onUpload;

  @override
  Widget build(BuildContext context) {
    final hasPhoto = previewBytes != null || photoUrl.trim().isNotEmpty;

    return PremiumCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 104,
                height: 128,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: AppColors.strokeStrong),
                  color: AppColors.surfaceStrong,
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(24),
                  child: hasPhoto
                      ? AppNetworkImage(
                          imageUrl: photoUrl,
                          memoryBytes: previewBytes,
                          height: 128,
                          width: 104,
                          borderRadius: 24,
                          fallbackIcon: Icons.person_outline_rounded,
                        )
                      : Container(
                          color: AppColors.surfaceSoft,
                          child: const Icon(
                            Icons.person_4_rounded,
                            color: AppColors.textSecondary,
                            size: 40,
                          ),
                        ),
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Profile photo',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                        height: 1.05,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      hasPhoto ? 'Photo selected' : 'No photo selected',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          GradientButton(
            label: uploading
                ? 'Uploading photo...'
                : hasPhoto
                ? 'Replace gallery photo'
                : 'Choose gallery photo',
            icon: uploading ? null : Icons.photo_library_rounded,
            loading: uploading,
            expanded: true,
            variant: GradientButtonVariant.secondary,
            onPressed: onUpload,
          ),
        ],
      ),
    );
  }
}

class _PremiumBioComposer extends StatelessWidget {
  const _PremiumBioComposer({required this.controller});

  final TextEditingController controller;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Bio',
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: AppSpacing.sm),
          TextField(
            controller: controller,
            minLines: 5,
            maxLines: 7,
            textInputAction: TextInputAction.newline,
            decoration: InputDecoration(
              hintText: 'Write a short bio',
              prefixIcon: const Padding(
                padding: EdgeInsets.only(bottom: 82),
                child: Icon(Icons.edit_note_rounded),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SpecializationGrid extends StatelessWidget {
  const _SpecializationGrid({
    required this.options,
    required this.selected,
    required this.onToggle,
  });

  final List<String> options;
  final Set<String> selected;
  final ValueChanged<String> onToggle;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        const spacing = AppSpacing.sm;
        final columns = constraints.maxWidth >= 560 ? 3 : 2;
        final itemWidth =
            (constraints.maxWidth - (spacing * (columns - 1))) / columns;

        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: options.map((option) {
            return _SpecializationCard(
              width: itemWidth,
              label: option,
              selected: selected.contains(option),
              onTap: () => onToggle(option),
            );
          }).toList(),
        );
      },
    );
  }
}

class _SpecializationCard extends StatelessWidget {
  const _SpecializationCard({
    required this.width,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final double width;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final compact = width < 170;

    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOutCubic,
        width: width,
        constraints: BoxConstraints(minHeight: compact ? 112 : 120),
        padding: EdgeInsets.all(compact ? 9 : 12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(26),
          color: selected
              ? AppColors.primary.withValues(alpha: 0.10)
              : AppColors.surfaceStrong,
          border: Border.all(
            color: selected ? AppColors.primary : AppColors.strokeStrong,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: compact ? 30 : 34,
                  height: compact ? 30 : 34,
                  decoration: BoxDecoration(
                    color: selected
                        ? AppColors.primary.withValues(alpha: 0.12)
                        : AppColors.surfaceSoft,
                    borderRadius: BorderRadius.circular(compact ? 12 : 14),
                  ),
                  alignment: Alignment.center,
                  child: Icon(
                    _specializationIcon(label),
                    size: compact ? 18 : 22,
                    color: selected
                        ? AppColors.primary
                        : AppColors.textSecondary,
                  ),
                ),
                const Spacer(),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  width: compact ? 18 : 20,
                  height: compact ? 18 : 20,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: selected ? AppColors.primary : AppColors.surfaceSoft,
                  ),
                  alignment: Alignment.center,
                  child: Icon(
                    selected ? Icons.check_rounded : Icons.add_rounded,
                    size: compact ? 12 : 14,
                    color: selected ? Colors.white : AppColors.textSecondary,
                  ),
                ),
              ],
            ),
            SizedBox(height: compact ? 8 : AppSpacing.sm),
            Text(
              label,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w800,
                fontSize: compact ? 13 : null,
                height: compact ? 1.05 : null,
              ),
            ),
            if (!compact) ...[
              const SizedBox(height: 6),
              Container(
                width: 34,
                height: 3,
                decoration: BoxDecoration(
                  color: selected ? AppColors.primary : AppColors.strokeStrong,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ExperienceHeroCard extends StatelessWidget {
  const _ExperienceHeroCard({required this.controller});

  final TextEditingController controller;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(26),
            ),
            alignment: Alignment.center,
            child: const Icon(
              Icons.workspace_premium_rounded,
              color: AppColors.primary,
              size: 34,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Coaching experience',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Use completed coaching years. Keep this honest because admins and members use it for trust.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    height: 1.28,
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceSoft,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: AppColors.strokeStrong),
                  ),
                  child: TextField(
                    controller: controller,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      hintText: 'Years of experience',
                      border: InputBorder.none,
                      suffixText: 'years',
                    ),
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

class _LanguageInputCard extends StatelessWidget {
  const _LanguageInputCard({required this.controller});

  final TextEditingController controller;

  @override
  Widget build(BuildContext context) {
    return PremiumCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: const Icon(
                  Icons.translate_rounded,
                  color: AppColors.success,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Languages you coach in',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      'Add at least one language to complete your trainer profile.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: controller,
            decoration: const InputDecoration(
              labelText: 'Languages',
              hintText: 'English, Hindi, Kannada',
              prefixIcon: Icon(Icons.language_rounded),
            ),
          ),
        ],
      ),
    );
  }
}

class _CertificationBuilder extends StatelessWidget {
  const _CertificationBuilder({
    required this.certifications,
    required this.nameController,
    required this.issuerController,
    required this.yearController,
    required this.pendingProof,
    required this.uploading,
    required this.onUpload,
    required this.onAdd,
    required this.onRemove,
  });

  final List<Map<String, dynamic>> certifications;
  final TextEditingController nameController;
  final TextEditingController issuerController;
  final TextEditingController yearController;
  final Map<String, dynamic>? pendingProof;
  final bool uploading;
  final VoidCallback? onUpload;
  final VoidCallback onAdd;
  final ValueChanged<int> onRemove;

  @override
  Widget build(BuildContext context) {
    final hasPendingFile =
        (pendingProof?['file_url']?.toString().trim() ?? '').isNotEmpty;
    final pendingFileName =
        pendingProof?['file_name']?.toString().trim() ?? 'Proof file';
    final pendingFileType =
        pendingProof?['file_type']?.toString().toLowerCase() == 'pdf'
        ? 'PDF'
        : 'Image';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        color: AppColors.surfaceStrong,
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.primaryBright.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: const Icon(
                  Icons.verified_rounded,
                  color: AppColors.primaryBright,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Certification vault',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      'Add credentials one by one and attach image or PDF proof.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: nameController,
            decoration: const InputDecoration(
              labelText: 'Certification name',
              hintText: 'ACE CPT, NASM, CPR...',
              prefixIcon: Icon(Icons.badge_rounded),
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: issuerController,
                  decoration: const InputDecoration(
                    labelText: 'Issuer',
                    hintText: 'ACE',
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              SizedBox(
                width: 112,
                child: TextField(
                  controller: yearController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Year',
                    hintText: '2025',
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Expanded(
                child: GradientButton(
                  label: uploading
                      ? 'Uploading...'
                      : hasPendingFile
                      ? 'Proof attached'
                      : 'Attach proof file',
                  icon: uploading
                      ? null
                      : hasPendingFile
                      ? Icons.check_circle_rounded
                      : Icons.upload_file_rounded,
                  loading: uploading,
                  expanded: true,
                  variant: GradientButtonVariant.secondary,
                  onPressed: onUpload,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: GradientButton(
                  label: 'Add certification',
                  icon: Icons.add_rounded,
                  expanded: true,
                  onPressed: onAdd,
                ),
              ),
            ],
          ),
          if (hasPendingFile) ...[
            const SizedBox(height: AppSpacing.sm),
            _CertificationStatusPill(
              icon: Icons.cloud_done_rounded,
              label: 'Proof uploaded and ready to attach',
              detail: '$pendingFileType • $pendingFileName',
            ),
          ],
          if (certifications.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            ...certifications.asMap().entries.map((entry) {
              return Padding(
                padding: EdgeInsets.only(
                  bottom: entry.key == certifications.length - 1
                      ? 0
                      : AppSpacing.sm,
                ),
                child: _CertificationItemCard(
                  certification: entry.value,
                  onRemove: () => onRemove(entry.key),
                ),
              );
            }),
          ],
        ],
      ),
    );
  }
}

class _CertificationStatusPill extends StatelessWidget {
  const _CertificationStatusPill({
    required this.icon,
    required this.label,
    required this.detail,
  });

  final IconData icon;
  final String label;
  final String detail;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.accentNeon.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppColors.accentNeon.withValues(alpha: 0.22)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppColors.accentNeon),
          const SizedBox(width: 6),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  detail,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: AppColors.textSecondary,
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

class _CertificationItemCard extends StatelessWidget {
  const _CertificationItemCard({
    required this.certification,
    required this.onRemove,
  });

  final Map<String, dynamic> certification;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final issuer = certification['issuer']?.toString() ?? '';
    final year = certification['issued_year']?.toString() ?? '';
    final hasFile = (certification['file_url']?.toString() ?? '').isNotEmpty;
    final fileName = certification['file_name']?.toString() ?? 'Proof file';
    final fileType =
        certification['file_type']?.toString().toLowerCase() == 'pdf'
        ? 'PDF'
        : 'Image';
    final meta = [
      issuer,
      year,
    ].where((item) => item.trim().isNotEmpty).join(' • ');

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(15),
            ),
            child: Icon(
              hasFile
                  ? fileType == 'PDF'
                        ? Icons.picture_as_pdf_rounded
                        : Icons.image_rounded
                  : Icons.workspace_premium_rounded,
              color: AppColors.primary,
              size: 20,
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  certification['name']?.toString() ?? 'Certification',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(
                    context,
                  ).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                Text(
                  meta.isEmpty
                      ? (hasFile
                            ? '$fileType attached • $fileName'
                            : 'No proof attached')
                      : '$meta${hasFile ? ' • $fileType attached' : ''}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Remove certification',
            onPressed: onRemove,
            icon: const Icon(Icons.close_rounded),
          ),
        ],
      ),
    );
  }
}

class _LabeledTextarea extends StatelessWidget {
  const _LabeledTextarea({
    required this.title,
    required this.icon,
    required this.controller,
    required this.hintText,
  });

  final String title;
  final IconData icon;
  final TextEditingController controller;
  final String hintText;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceStrong,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.strokeStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: AppColors.primaryBright),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          TextField(
            controller: controller,
            minLines: 2,
            maxLines: 3,
            decoration: InputDecoration(hintText: hintText),
          ),
        ],
      ),
    );
  }
}

String _stepTitle(int step) {
  switch (step) {
    case 1:
      return 'Welcome';
    case 2:
      return 'Profile';
    case 3:
      return 'Focus';
    case 4:
      return 'Proof';
    case 5:
      return 'Time';
    case 6:
      return 'Gym';
    default:
      return 'Profile';
  }
}

IconData _specializationIcon(String value) {
  final normalized = value.toLowerCase();
  if (normalized.contains('strength')) return Icons.fitness_center_rounded;
  if (normalized.contains('fat')) return Icons.local_fire_department_rounded;
  if (normalized.contains('mobility')) return Icons.self_improvement_rounded;
  if (normalized.contains('sport')) return Icons.sports_gymnastics_rounded;
  if (normalized.contains('recomposition')) return Icons.auto_graph_rounded;
  return Icons.workspace_premium_rounded;
}
