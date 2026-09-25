import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:gym_flutter_core/guides.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/user_facing_error.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';
import '../../../core/widgets/premium_card.dart';
import 'trainer_certification_builder.dart';
import 'trainer_photo_picker.dart';
import 'trainer_repository.dart';
import '../auth/session_controller.dart';
import 'trainer_verification_requirements.dart';

class TrainerProfileScreen extends StatefulWidget {
  const TrainerProfileScreen({super.key, required this.repository});

  final TrainerRepository repository;

  @override
  State<TrainerProfileScreen> createState() => _TrainerProfileScreenState();
}

class _TrainerProfileScreenState extends State<TrainerProfileScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _photoController = TextEditingController();
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _dateOfBirthController = TextEditingController();
  final TextEditingController _bioController = TextEditingController();
  final TextEditingController _specializationController =
      TextEditingController();
  final TextEditingController _experienceController = TextEditingController();
  final TextEditingController _certificationNameController =
      TextEditingController();
  final TextEditingController _certificationIssuerController =
      TextEditingController();
  final TextEditingController _certificationYearController =
      TextEditingController();
  final TextEditingController _languagesController = TextEditingController();
  final TextEditingController _gymController = TextEditingController();
  final TextEditingController _branchController = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  bool _uploadingPhoto = false;
  bool _uploadingCertification = false;
  bool _submittingVerification = false;
  bool _editing = true;
  String? _error;
  String? _photoError;
  Map<String, dynamic> _profile = const {};
  Map<String, dynamic> _trainerUser = const {};
  final List<Map<String, dynamic>> _certifications = <Map<String, dynamic>>[];
  Map<String, dynamic>? _pendingCertificationProof;
  Uint8List? _profilePhotoPreviewBytes;
  String? _gender;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  @override
  void dispose() {
    _photoController.dispose();
    _nameController.dispose();
    _phoneController.dispose();
    _dateOfBirthController.dispose();
    _bioController.dispose();
    _specializationController.dispose();
    _experienceController.dispose();
    _certificationNameController.dispose();
    _certificationIssuerController.dispose();
    _certificationYearController.dispose();
    _languagesController.dispose();
    _gymController.dispose();
    _branchController.dispose();
    super.dispose();
  }

  Future<void> _loadProfile() async {
    if (!mounted) {
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await widget.repository.fetchProfile();
      final data = _map(response['data']);
      final profile = _map(data['trainer_profile']);
      final trainerUser = _map(data['trainer_user']);

      if (!mounted) {
        return;
      }
      _profile = profile;
      _trainerUser = trainerUser;
      _photoController.text = profile['profile_photo_url']?.toString() ?? '';
      _nameController.text = trainerUser['name']?.toString() ?? 'Trainer';
      _phoneController.text = trainerUser['phone']?.toString() ?? '';
      _dateOfBirthController.text =
          trainerUser['date_of_birth']?.toString() ?? '';
      _gender = trainerUser['gender']?.toString();
      _bioController.text = profile['bio']?.toString() ?? '';
      _specializationController.text = _list(
        profile['specializations'],
      ).join(', ');
      _experienceController.text =
          profile['experience_years']?.toString() ?? '';
      _certifications
        ..clear()
        ..addAll(_certificationMaps(profile['certifications']));
      _certificationNameController.clear();
      _certificationIssuerController.clear();
      _certificationYearController.clear();
      _pendingCertificationProof = null;
      _languagesController.text = _list(profile['languages']).join(', ');
      _gymController.text =
          _map(profile['assigned_gym'])['name']?.toString() ?? 'Not assigned';
      _branchController.text =
          _map(profile['assigned_branch'])['name']?.toString() ??
          'Not assigned';
    } catch (exception) {
      _error = userFacingError(exception);
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _saveProfile() async {
    if (!_editing || _saving) {
      return;
    }
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() => _saving = true);

    try {
      await widget.repository.updateProfile({
        'name': _nameController.text.trim(),
        'phone': _emptyToNull(_phoneController.text),
        'gender': _gender,
        'date_of_birth': _emptyToNull(_dateOfBirthController.text),
        'profile_photo_url': _emptyToNull(_photoController.text),
        'bio': _emptyToNull(_bioController.text),
        'specializations': _splitList(_specializationController.text),
        'experience_years':
            int.tryParse(_experienceController.text.trim()) ?? 0,
        'certifications': _certificationPayload(),
        'languages': _splitList(_languagesController.text),
      });

      if (!mounted) {
        return;
      }

      Navigator.of(context).pop(true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _saving = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(userFacingError(exception))));
    }
  }

  Future<void> _submitVerification() async {
    if (_submittingVerification) return;
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final missingRequirements = missingTrainerVerificationRequirements(
      bio: _bioController.text,
      specializations: _splitList(_specializationController.text),
      experienceYears: _experienceController.text,
      certifications: _certificationPayload(),
    );
    if (missingRequirements.isNotEmpty) {
      setState(() => _editing = true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Complete before submitting: ${missingRequirements.join(', ')}.',
          ),
        ),
      );
      return;
    }

    setState(() => _submittingVerification = true);
    try {
      if (_editing) {
        await widget.repository.updateProfile({
          'name': _nameController.text.trim(),
          'phone': _emptyToNull(_phoneController.text),
          'gender': _gender,
          'date_of_birth': _emptyToNull(_dateOfBirthController.text),
          'bio': _emptyToNull(_bioController.text),
          'specializations': _splitList(_specializationController.text),
          'experience_years':
              int.tryParse(_experienceController.text.trim()) ?? 0,
          'certifications': _certificationPayload(),
          'languages': _splitList(_languagesController.text),
        });
      }
      final response = await widget.repository.submitTrainerVerification();
      final profile = _map(_map(response['data'])['trainer_profile']);
      if (!mounted) return;
      setState(() {
        if (profile.isNotEmpty) _profile = profile;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Verification application submitted for review.'),
        ),
      );
      await _loadProfile();
    } catch (exception) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(userFacingError(exception))));
    } finally {
      if (mounted) setState(() => _submittingVerification = false);
    }
  }

  Future<void> _showPhotoSourceSheet() async {
    if (_uploadingPhoto || _saving) return;
    setState(() => _photoError = null);
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => SafeArea(
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
                  ),
                ),
                const SizedBox(height: 18),
                ListTile(
                  leading: const Icon(Icons.photo_library_outlined),
                  title: const Text('Gallery'),
                  subtitle: const Text('Choose a saved photo'),
                  onTap: () {
                    Navigator.of(sheetContext).pop();
                    _pickAndUploadPhoto(ImageSource.gallery);
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.photo_camera_outlined),
                  title: const Text('Camera'),
                  subtitle: const Text('Capture a new photo'),
                  onTap: () {
                    Navigator.of(sheetContext).pop();
                    _pickAndUploadPhoto(ImageSource.camera);
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _pickAndUploadPhoto(ImageSource source) async {
    if (_uploadingPhoto) {
      return;
    }

    final session = context.read<TrainerSessionController>();
    if (!session.hasConsent('photos')) {
      if (mounted) {
        setState(
          () => _photoError =
              'Turn on Photos in Settings > Privacy & consent to upload a photo.',
        );
      }
      return;
    }

    setState(() {
      _uploadingPhoto = true;
      _photoError = null;
    });
    try {
      final picked = await TrainerPhotoPicker().pickCompressedProfilePhoto(
        source: source,
      );
      if (picked == null) {
        return;
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
      setState(() {
        _photoController.text = photoUrl;
        _profile = {..._profile, 'profile_photo_url': photoUrl};
        _profilePhotoPreviewBytes = picked.bytes;
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Profile photo uploaded.')));
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _photoError = userFacingError(exception));
    } finally {
      if (mounted) {
        setState(() => _uploadingPhoto = false);
      }
    }
  }

  void _removePhoto() {
    setState(() {
      _photoController.clear();
      _profilePhotoPreviewBytes = null;
      _photoError = null;
    });
  }

  Future<void> _pickAndUploadCertification() async {
    if (_uploadingCertification) return;

    setState(() => _uploadingCertification = true);
    try {
      final picked = await TrainerPhotoPicker()
          .pickCompressedCertificationImage();
      if (picked == null) return;

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
      if (!mounted) return;

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
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(userFacingError(exception))));
    } finally {
      if (mounted) setState(() => _uploadingCertification = false);
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

  @override
  Widget build(BuildContext context) {
    final verificationStatus =
        _profile['verification_status']?.toString().toLowerCase() ?? 'pending';
    final verificationReason = _profile['verification_rejection_reason']
        ?.toString()
        .trim();
    final verificationSubmitted = _profile['verification_submitted'] == true;
    final displayName = _trainerUser['name']?.toString() ?? 'Trainer';
    return AppGradientScaffold(
      title: 'Edit Profile',
      bottomNavigationBar: GuideTarget(
        id: 'trainer_profile_edit_v1/save',
        child: _TrainerProfileSaveBar(
          saving: _saving,
          onSave:
              _saving ||
                  _submittingVerification ||
                  _uploadingPhoto ||
                  _loading ||
                  _error != null
              ? null
              : _saveProfile,
        ),
      ),
      body: SafeArea(
        bottom: false,
        child: _loading
            ? const LoadingStateView(label: 'Loading trainer profile...')
            : _error != null
            ? ErrorStateView(message: _error!, onRetry: _loadProfile)
            : Form(
                key: _formKey,
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
                    const _TrainerEditorTopBar(),
                    const SizedBox(height: AppSpacing.md),
                    _FitProfileHeader(
                      name: displayName,
                      email: _trainerUser['email']?.toString() ?? '',
                      imageUrl: _photoController.text.trim(),
                      previewBytes: _profilePhotoPreviewBytes,
                      uploading: _uploadingPhoto,
                      error: _photoError,
                      onPhotoTap: _uploadingPhoto
                          ? null
                          : _showPhotoSourceSheet,
                      onRemovePhoto: _photoController.text.trim().isEmpty
                          ? null
                          : _removePhoto,
                    ),
                    const SizedBox(height: 25),
                    GuideTarget(
                      id: 'trainer_profile_edit_v1/basic',
                      child: _FitProfileCard(
                        title: 'Basic Details',
                        subtitle:
                            'Keep your account details accurate. Birth date and gender are optional.',
                        child: Column(
                          children: <Widget>[
                            TextFormField(
                              controller: _nameController,
                              textCapitalization: TextCapitalization.words,
                              decoration: _fitInputDecoration(
                                'Name',
                                icon: Icons.person_outline_rounded,
                              ),
                              validator: (value) => (value ?? '').trim().isEmpty
                                  ? 'Name is required'
                                  : null,
                            ),
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: _phoneController,
                              readOnly: !_editing,
                              keyboardType: TextInputType.phone,
                              autofillHints: const [
                                AutofillHints.telephoneNumber,
                              ],
                              decoration: _fitInputDecoration(
                                'Phone number',
                                icon: Icons.phone_outlined,
                              ),
                              validator: (value) {
                                final phone = (value ?? '').trim();
                                if (phone.isEmpty) {
                                  return 'Phone number is required';
                                }
                                final digitCount = phone.codeUnits
                                    .where((unit) => unit >= 48 && unit <= 57)
                                    .length;
                                if (digitCount < 7 || digitCount > 15) {
                                  return 'Enter a valid phone number';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 14),
                            DropdownButtonFormField<String>(
                              initialValue:
                                  const [
                                    'female',
                                    'male',
                                    'non_binary',
                                    'prefer_not_to_say',
                                  ].contains(_gender)
                                  ? _gender
                                  : null,
                              decoration: _fitInputDecoration(
                                'Gender (optional)',
                                icon: Icons.person_outline_rounded,
                              ),
                              items:
                                  const {
                                        'female': 'Female',
                                        'male': 'Male',
                                        'non_binary': 'Non-binary',
                                        'prefer_not_to_say':
                                            'Prefer not to say',
                                      }.entries
                                      .map(
                                        (entry) => DropdownMenuItem<String>(
                                          value: entry.key,
                                          child: Text(entry.value),
                                        ),
                                      )
                                      .toList(),
                              onChanged: _editing
                                  ? (value) => setState(() => _gender = value)
                                  : null,
                            ),
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: _dateOfBirthController,
                              readOnly: true,
                              onTap: _editing ? _pickDateOfBirth : null,
                              decoration: _fitInputDecoration(
                                'Date of birth (optional)',
                                icon: Icons.cake_outlined,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 25),
                    GuideTarget(
                      id: 'trainer_profile_edit_v1/coaching',
                      child: _FitProfileCard(
                        title: 'Coaching Details',
                        subtitle:
                            'Tell members about your specialties and experience.',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            TextFormField(
                              controller: _bioController,
                              readOnly: !_editing,
                              maxLines: 4,
                              decoration: _fitInputDecoration(
                                'Bio',
                                icon: Icons.notes_rounded,
                              ),
                              validator: (value) {
                                if ((value ?? '').trim().length > 5000) {
                                  return 'Bio is too long';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: _specializationController,
                              readOnly: !_editing,
                              decoration: _fitInputDecoration(
                                'Specializations',
                                hint: 'Strength, Fat Loss, Mobility',
                                icon: Icons.fitness_center_rounded,
                              ),
                            ),
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: _experienceController,
                              readOnly: !_editing,
                              keyboardType: TextInputType.number,
                              decoration: _fitInputDecoration(
                                'Experience years',
                                icon: Icons.timeline_rounded,
                              ),
                              validator: (value) {
                                final trimmed = value?.trim() ?? '';
                                if (trimmed.isEmpty) {
                                  return null;
                                }
                                final years = int.tryParse(trimmed);
                                if (years == null || years < 0) {
                                  return 'Enter a valid number of years';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 14),
                            if (_editing)
                              TrainerCertificationBuilder(
                                certifications: _certifications,
                                nameController: _certificationNameController,
                                issuerController:
                                    _certificationIssuerController,
                                yearController: _certificationYearController,
                                pendingProof: _pendingCertificationProof,
                                uploading: _uploadingCertification,
                                onUpload: _uploadingCertification
                                    ? null
                                    : _pickAndUploadCertification,
                                onAdd: _addCertification,
                                onRemove: (index) => setState(
                                  () => _certifications.removeAt(index),
                                ),
                              )
                            else
                              _CertificationPreviewList(
                                certifications: _certificationPayload(),
                                editing: false,
                              ),
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: _languagesController,
                              readOnly: !_editing,
                              decoration: _fitInputDecoration(
                                'Languages',
                                hint: 'English, Hindi',
                                icon: Icons.translate_rounded,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 25),
                    GuideTarget(
                      id: 'trainer_profile_edit_v1/verification',
                      child: _FitProfileCard(
                        title: 'Personal Coaching Verification',
                        subtitle: verificationStatus == 'verified'
                            ? 'Your personal coaching profile is verified.'
                            : verificationStatus == 'suspended'
                            ? 'Personal coaching access is suspended. Contact support for help.'
                            : verificationStatus == 'rejected'
                            ? 'Update the requested details, then resubmit for review.'
                            : verificationSubmitted
                            ? 'Your application is under review.'
                            : 'Complete your coaching details and certifications to apply.',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (verificationReason?.isNotEmpty == true)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 14),
                                child: Text(
                                  verificationReason!,
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ),
                            if (verificationStatus != 'verified' &&
                                verificationStatus != 'suspended' &&
                                (verificationStatus == 'rejected' ||
                                    !verificationSubmitted))
                              OutlinedButton.icon(
                                onPressed: _submittingVerification || _saving
                                    ? null
                                    : _submitVerification,
                                icon: _submittingVerification
                                    ? const SizedBox.square(
                                        dimension: 18,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                        ),
                                      )
                                    : const Icon(Icons.verified_user_outlined),
                                label: Text(
                                  verificationStatus == 'rejected'
                                      ? 'Resubmit verification'
                                      : 'Submit for verification',
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }

  Future<void> _pickDateOfBirth() async {
    final now = DateTime.now();
    final current = DateTime.tryParse(_dateOfBirthController.text);
    final selected = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime(now.year - 18, now.month, now.day),
      firstDate: DateTime(1900),
      lastDate: DateTime(now.year, now.month, now.day),
    );
    if (selected != null && mounted) {
      setState(() {
        _dateOfBirthController.text =
            '${selected.year.toString().padLeft(4, '0')}-${selected.month.toString().padLeft(2, '0')}-${selected.day.toString().padLeft(2, '0')}';
      });
    }
  }

  static List<String> _splitList(String raw) {
    return raw
        .split(',')
        .map((item) => item.trim())
        .where((item) => item.isNotEmpty)
        .toList();
  }

  List<Map<String, dynamic>> _certificationPayload() {
    return _certifications
        .map((item) => Map<String, dynamic>.from(item))
        .where((item) => (item['name']?.toString().trim() ?? '').isNotEmpty)
        .toList();
  }

  static List<Map<String, dynamic>> _certificationMaps(dynamic value) {
    if (value is! List) {
      return const <Map<String, dynamic>>[];
    }

    return value
        .map((item) {
          if (item is Map) {
            final mapped = _map(item);
            final name = mapped['name']?.toString().trim() ?? '';
            if (name.isEmpty) {
              return null;
            }
            return <String, dynamic>{...mapped, 'name': name};
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

  static String _proofTypeFromName(String filename) {
    return filename.toLowerCase().endsWith('.pdf') ? 'pdf' : 'image';
  }

  static String? _emptyToNull(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }
}

class _TrainerEditorTopBar extends StatelessWidget {
  const _TrainerEditorTopBar();

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
              'Edit Profile',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              'Keep your account and coaching details accurate.',
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

class _TrainerProfileSaveBar extends StatelessWidget {
  const _TrainerProfileSaveBar({required this.saving, required this.onSave});

  final bool saving;
  final VoidCallback? onSave;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.surface,
    elevation: 10,
    shadowColor: Colors.black.withValues(alpha: 0.10),
    child: SafeArea(
      top: false,
      minimum: const EdgeInsets.fromLTRB(AppSpacing.lg, 12, AppSpacing.lg, 12),
      child: FilledButton(
        onPressed: onSave,
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
        ),
        child: saving
            ? const SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.4,
                  color: Colors.white,
                ),
              )
            : const Text('Save changes'),
      ),
    ),
  );
}

class _FitProfileHeader extends StatelessWidget {
  const _FitProfileHeader({
    required this.name,
    required this.email,
    required this.imageUrl,
    required this.previewBytes,
    required this.uploading,
    required this.error,
    required this.onPhotoTap,
    required this.onRemovePhoto,
  });

  final String name;
  final String email;
  final String imageUrl;
  final Uint8List? previewBytes;
  final bool uploading;
  final String? error;
  final VoidCallback? onPhotoTap;
  final VoidCallback? onRemovePhoto;

  @override
  Widget build(BuildContext context) {
    final hasPhoto = imageUrl.isNotEmpty || previewBytes != null;
    return PremiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Semantics(
                label: hasPhoto ? 'Current profile photo' : 'Profile avatar',
                image: hasPhoto,
                child: AppNetworkImage(
                  imageUrl: imageUrl,
                  memoryBytes: previewBytes,
                  height: 72,
                  width: 72,
                  borderRadius: 36,
                  fallbackIcon: Icons.person_outline_rounded,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Profile photo',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      uploading ? 'Uploading photo…' : email,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
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
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              OutlinedButton.icon(
                onPressed: uploading ? null : onPhotoTap,
                icon: uploading
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(
                        hasPhoto
                            ? Icons.photo_camera_outlined
                            : Icons.add_a_photo_rounded,
                      ),
                label: Text(hasPhoto ? 'Change photo' : 'Add photo'),
              ),
              if (onRemovePhoto != null)
                TextButton.icon(
                  onPressed: uploading ? null : onRemovePhoto,
                  icon: const Icon(Icons.delete_outline_rounded),
                  label: const Text('Remove photo'),
                ),
            ],
          ),
          if (error != null) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(
              error!,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.error),
            ),
          ],
        ],
      ),
    );
  }
}

class _FitProfileCard extends StatelessWidget {
  const _FitProfileCard({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  final String title;
  final String subtitle;
  final Widget child;

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
          const SizedBox(height: 5),
          Text(
            subtitle,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _CertificationPreviewList extends StatelessWidget {
  const _CertificationPreviewList({
    required this.certifications,
    required this.editing,
  });

  final List<Map<String, dynamic>> certifications;
  final bool editing;

  @override
  Widget build(BuildContext context) {
    if (certifications.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: _FitProfileColor.field,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: _FitProfileColor.border),
        ),
        child: Text(
          editing
              ? 'No certifications added yet. Type certificate names above, separated by commas.'
              : 'No certifications added yet.',
          style: TextStyle(
            color: _FitProfileColor.gray,
            fontSize: 12,
            height: 1.35,
            fontWeight: FontWeight.w600,
          ),
        ),
      );
    }

    return Column(
      children: certifications.map((certification) {
        final name =
            certification['name']?.toString().trim() ?? 'Certification';
        final issuer = certification['issuer']?.toString().trim() ?? '';
        final year = certification['issued_year']?.toString().trim() ?? '';
        final fileName = certification['file_name']?.toString().trim() ?? '';
        final hasProof =
            (certification['file_url']?.toString().trim() ?? '').isNotEmpty ||
            fileName.isNotEmpty;
        final detailParts = <String>[
          if (issuer.isNotEmpty) issuer,
          if (year.isNotEmpty) year,
          if (hasProof) 'Proof attached',
        ];

        return Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: _FitProfileColor.field,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: _FitProfileColor.border),
            ),
            child: Row(
              children: <Widget>[
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    gradient: _FitProfileColor.primaryGradient,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.workspace_premium_rounded,
                    color: Colors.white,
                    size: 19,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: _FitProfileColor.black,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (detailParts.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          detailParts.join(' • '),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: _FitProfileColor.gray,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }
}

InputDecoration _fitInputDecoration(
  String label, {
  String? hint,
  IconData? icon,
}) {
  return InputDecoration(
    labelText: label,
    hintText: hint,
    prefixIcon: icon == null ? null : Icon(icon, size: 20),
    filled: true,
    fillColor: _FitProfileColor.field,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(color: _FitProfileColor.border),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(color: _FitProfileColor.border),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(18),
      borderSide: BorderSide(color: _FitProfileColor.primaryEnd, width: 1.5),
    ),
  );
}

class _FitProfileColor {
  static const Color black = AppColors.textPrimary;
  static const Color gray = AppColors.textSecondary;
  static const Color field = AppColors.surfaceSoft;
  static const Color border = AppColors.stroke;
  static const Color primaryStart = AppColors.primary;
  static const Color primaryEnd = AppColors.primaryBright;

  static const LinearGradient primaryGradient = LinearGradient(
    colors: <Color>[primaryStart, primaryEnd],
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
  );
}

Map<String, dynamic> _map(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, item) => MapEntry(key.toString(), item));
  }
  return <String, dynamic>{};
}

List<String> _list(dynamic value) {
  if (value is List) {
    return value
        .map((item) {
          if (item is Map) {
            return _map(item)['name']?.toString().trim() ?? '';
          }
          return item?.toString().trim() ?? '';
        })
        .where((item) => item.isNotEmpty)
        .toList();
  }
  return const <String>[];
}
