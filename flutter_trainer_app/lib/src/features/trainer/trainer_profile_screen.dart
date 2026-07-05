import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../../../core/widgets/common_widgets.dart';
import 'trainer_photo_picker.dart';
import 'trainer_repository.dart';

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
  final TextEditingController _bioController = TextEditingController();
  final TextEditingController _specializationController =
      TextEditingController();
  final TextEditingController _experienceController = TextEditingController();
  final TextEditingController _certificationsController =
      TextEditingController();
  final TextEditingController _languagesController = TextEditingController();
  final TextEditingController _gymController = TextEditingController();
  final TextEditingController _branchController = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  bool _uploadingPhoto = false;
  bool _editing = true;
  String? _error;
  Map<String, dynamic> _profile = const {};
  Map<String, dynamic> _trainerUser = const {};
  Uint8List? _profilePhotoPreviewBytes;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  @override
  void dispose() {
    _photoController.dispose();
    _nameController.dispose();
    _bioController.dispose();
    _specializationController.dispose();
    _experienceController.dispose();
    _certificationsController.dispose();
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
      _bioController.text = profile['bio']?.toString() ?? '';
      _specializationController.text = _list(
        profile['specializations'],
      ).join(', ');
      _experienceController.text =
          profile['experience_years']?.toString() ?? '';
      _certificationsController.text = _list(
        profile['certifications'],
      ).join(', ');
      _languagesController.text = _list(profile['languages']).join(', ');
      _gymController.text =
          _map(profile['assigned_gym'])['name']?.toString() ?? 'Not assigned';
      _branchController.text =
          _map(profile['assigned_branch'])['name']?.toString() ??
          'Not assigned';
    } catch (exception) {
      _error = exception.toString();
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

      setState(() {
        _editing = false;
        _saving = false;
      });
      await _loadProfile();
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Trainer profile updated.')));
      Navigator.of(context).pop(true);
    } catch (exception) {
      if (!mounted) {
        return;
      }
      setState(() => _saving = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    }
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
      setState(() {
        _photoController.text = photoUrl;
        _profile = {..._profile, 'profile_photo_url': photoUrl};
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Profile photo uploaded.')));
    } catch (exception) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.toString())));
    } finally {
      if (mounted) {
        setState(() => _uploadingPhoto = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final completion =
        (_profile['profile_completion_percentage'] as num?)?.toDouble() ?? 0;
    final displayName = _trainerUser['name']?.toString() ?? 'Trainer';
    final specialization = _profile['primary_specialization']
        ?.toString()
        .trim();
    final canShowImage =
        (_profilePhotoPreviewBytes != null ||
        _photoController.text.trim().isNotEmpty ||
        (_profile['profile_photo_url']?.toString().trim().isNotEmpty == true));

    return Scaffold(
      backgroundColor: _FitProfileColor.white,
      appBar: AppBar(
        backgroundColor: _FitProfileColor.white,
        elevation: 0,
        centerTitle: true,
        leadingWidth: 72,
        leading: Padding(
          padding: const EdgeInsets.only(left: 25),
          child: _FitIconButton(
            icon: Icons.arrow_back_ios_new_rounded,
            onTap: () => Navigator.of(context).pop(),
          ),
        ),
        title: Text(
          'Trainer Profile',
          style: TextStyle(
            color: _FitProfileColor.black,
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
        actions: <Widget>[
          if (!_loading && _error == null)
            Padding(
              padding: const EdgeInsets.only(right: 18),
              child: TextButton(
                onPressed: _saving
                    ? null
                    : () => setState(() => _editing = !_editing),
                style: TextButton.styleFrom(
                  foregroundColor: _FitProfileColor.primaryEnd,
                  textStyle: const TextStyle(fontWeight: FontWeight.w700),
                ),
                child: Text(_editing ? 'Cancel' : 'Edit'),
              ),
            ),
        ],
      ),
      body: _loading
          ? const LoadingStateView(label: 'Loading trainer profile...')
          : _error != null
          ? ErrorStateView(message: _error!, onRetry: _loadProfile)
          : RefreshIndicator(
              onRefresh: _loadProfile,
              color: _FitProfileColor.primaryEnd,
              child: Form(
                key: _formKey,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  padding: const EdgeInsets.fromLTRB(25, 15, 25, 32),
                  children: <Widget>[
                    _FitProfileHeader(
                      name: displayName,
                      email: _trainerUser['email']?.toString() ?? '',
                      specialization: specialization,
                      imageUrl: _photoController.text.trim(),
                      previewBytes: _profilePhotoPreviewBytes,
                      uploading: _uploadingPhoto,
                      editing: _editing,
                      onPhotoTap: _uploadingPhoto ? null : _pickAndUploadPhoto,
                    ),
                    const SizedBox(height: 24),
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: _FitProfileStatCell(
                            title: '${completion.toStringAsFixed(0)}%',
                            subtitle: 'Complete',
                          ),
                        ),
                        Container(
                          height: 48,
                          width: 1,
                          color: _FitProfileColor.border,
                        ),
                        Expanded(
                          child: _FitProfileStatCell(
                            title: _experienceController.text.trim().isEmpty
                                ? '--'
                                : '${_experienceController.text.trim()}y',
                            subtitle: 'Experience',
                          ),
                        ),
                        Container(
                          height: 48,
                          width: 1,
                          color: _FitProfileColor.border,
                        ),
                        Expanded(
                          child: _FitProfileStatCell(
                            title: _profile['client_count']?.toString() ?? '0',
                            subtitle: 'Clients',
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 28),
                    _FitProfileCard(
                      title: 'Profile completion',
                      subtitle:
                          'Keep the essentials complete so gyms and clients can trust the profile at a glance.',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          ClipRRect(
                            borderRadius: BorderRadius.circular(999),
                            child: LinearProgressIndicator(
                              value: (completion.clamp(0, 100)) / 100,
                              minHeight: 10,
                              backgroundColor: _FitProfileColor.field,
                              valueColor: AlwaysStoppedAnimation<Color>(
                                _FitProfileColor.primaryEnd,
                              ),
                            ),
                          ),
                          const SizedBox(height: 14),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: <Widget>[
                              _FitChip(
                                label:
                                    '${completion.toStringAsFixed(0)}% complete',
                                icon: Icons.auto_awesome_rounded,
                              ),
                              _FitChip(
                                label:
                                    _profile['verification_status']
                                        ?.toString() ??
                                    'verification pending',
                                icon: Icons.verified_outlined,
                              ),
                              _FitChip(
                                label: _profile['is_active'] == true
                                    ? 'active trainer'
                                    : 'inactive trainer',
                                icon: Icons.flash_on_rounded,
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    _FitProfileCard(
                      title: 'Identity',
                      subtitle:
                          'These fields come from the user account and assigned gym context.',
                      child: Column(
                        children: <Widget>[
                          TextFormField(
                            controller: _nameController,
                            readOnly: true,
                            decoration: _fitInputDecoration(
                              'Name',
                              icon: Icons.person_outline_rounded,
                            ),
                          ),
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _gymController,
                            readOnly: true,
                            decoration: _fitInputDecoration(
                              'Assigned gym',
                              icon: Icons.apartment_rounded,
                            ),
                          ),
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _branchController,
                            readOnly: true,
                            decoration: _fitInputDecoration(
                              'Assigned branch',
                              icon: Icons.location_on_outlined,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    _FitProfileCard(
                      title: 'Coaching details',
                      subtitle: _editing
                          ? 'Update the fields that are saved to the trainer profile API.'
                          : 'Review the trainer profile information currently visible to the gym.',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          if (canShowImage) ...[
                            ClipRRect(
                              borderRadius: BorderRadius.circular(24),
                              child: AppNetworkImage(
                                imageUrl: _photoController.text.trim(),
                                memoryBytes: _profilePhotoPreviewBytes,
                                height: 180,
                                width: double.infinity,
                                fallbackIcon: Icons.person_outline_rounded,
                              ),
                            ),
                            const SizedBox(height: 14),
                          ],
                          if (_editing) ...[
                            GradientButton(
                              label: _uploadingPhoto
                                  ? 'Uploading photo...'
                                  : 'Choose photo from gallery',
                              icon: _uploadingPhoto
                                  ? null
                                  : Icons.photo_library_rounded,
                              loading: _uploadingPhoto,
                              expanded: true,
                              onPressed: _uploadingPhoto
                                  ? null
                                  : _pickAndUploadPhoto,
                            ),
                            const SizedBox(height: 14),
                          ],
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
                          TextFormField(
                            controller: _certificationsController,
                            readOnly: !_editing,
                            maxLines: 2,
                            decoration: _fitInputDecoration(
                              'Add or edit certifications',
                              hint: 'ACE CPT, NASM, CPR',
                              icon: Icons.workspace_premium_rounded,
                            ),
                          ),
                          const SizedBox(height: 12),
                          _CertificationPreviewList(
                            certifications: _certificationPayload(),
                            editing: _editing,
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
                    if (_editing) ...[
                      const SizedBox(height: 24),
                      GradientButton(
                        label: _saving ? 'Saving...' : 'Save profile',
                        icon: Icons.check_circle_rounded,
                        expanded: true,
                        onPressed: _saving ? null : _saveProfile,
                      ),
                    ],
                  ],
                ),
              ),
            ),
    );
  }

  static List<String> _splitList(String raw) {
    return raw
        .split(',')
        .map((item) => item.trim())
        .where((item) => item.isNotEmpty)
        .toList();
  }

  List<Map<String, dynamic>> _certificationPayload() {
    final existing = <String, Map<String, dynamic>>{
      for (final item in _certificationMaps(_profile['certifications']))
        _certificationKey(item['name']?.toString() ?? ''): item,
    };

    return _splitList(_certificationsController.text).map((name) {
      final existingItem = existing[_certificationKey(name)];
      return {if (existingItem != null) ...existingItem, 'name': name};
    }).toList();
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

  static String _certificationKey(String value) {
    return value.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), '');
  }

  static String? _emptyToNull(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }
}

class _FitProfileHeader extends StatelessWidget {
  const _FitProfileHeader({
    required this.name,
    required this.email,
    required this.specialization,
    required this.imageUrl,
    required this.previewBytes,
    required this.uploading,
    required this.editing,
    required this.onPhotoTap,
  });

  final String name;
  final String email;
  final String? specialization;
  final String imageUrl;
  final Uint8List? previewBytes;
  final bool uploading;
  final bool editing;
  final VoidCallback? onPhotoTap;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Stack(
          clipBehavior: Clip.none,
          children: <Widget>[
            Container(
              width: 68,
              height: 68,
              decoration: BoxDecoration(
                color: _FitProfileColor.field,
                borderRadius: BorderRadius.circular(22),
              ),
              clipBehavior: Clip.antiAlias,
              child: AppNetworkImage(
                imageUrl: imageUrl,
                memoryBytes: previewBytes,
                height: 68,
                width: 68,
                borderRadius: 22,
                fallbackIcon: Icons.person_outline_rounded,
              ),
            ),
            if (editing)
              Positioned(
                right: -6,
                bottom: -6,
                child: InkWell(
                  onTap: onPhotoTap,
                  borderRadius: BorderRadius.circular(999),
                  child: Container(
                    width: 30,
                    height: 30,
                    decoration: BoxDecoration(
                      gradient: _FitProfileColor.primaryGradient,
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: _FitProfileColor.white,
                        width: 3,
                      ),
                    ),
                    child: Icon(
                      uploading
                          ? Icons.hourglass_empty_rounded
                          : Icons.camera_alt_rounded,
                      color: _FitProfileColor.white,
                      size: 15,
                    ),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(width: 16),
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
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                email.isNotEmpty ? email : 'Trainer account',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: _FitProfileColor.gray,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 9),
              _FitChip(
                label: specialization?.isNotEmpty == true
                    ? specialization!
                    : 'Add specialization',
                icon: Icons.bolt_rounded,
                compact: true,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _FitProfileStatCell extends StatelessWidget {
  const _FitProfileStatCell({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: _FitProfileColor.black,
            fontSize: 15,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          subtitle,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: _FitProfileColor.gray,
            fontSize: 11,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
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
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: _FitProfileColor.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _FitProfileColor.border),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: _FitProfileColor.black.withValues(alpha: 0.05),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: TextStyle(
              color: _FitProfileColor.black,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: TextStyle(
              color: _FitProfileColor.gray,
              fontSize: 12,
              height: 1.35,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 18),
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

class _FitChip extends StatelessWidget {
  const _FitChip({
    required this.label,
    required this.icon,
    this.compact = false,
  });

  final String label;
  final IconData icon;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 9 : 12,
        vertical: compact ? 6 : 8,
      ),
      decoration: BoxDecoration(
        color: _FitProfileColor.field,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: compact ? 13 : 15, color: _FitProfileColor.accent),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: _FitProfileColor.black,
                fontSize: compact ? 11 : 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FitIconButton extends StatelessWidget {
  const _FitIconButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: _FitProfileColor.field,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, size: 18, color: _FitProfileColor.black),
        ),
      ),
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
  static const Color white = Colors.white;
  static const Color black = Color(0xFF1D1617);
  static const Color gray = Color(0xFF7B6F72);
  static const Color field = Color(0xFFF7F8F8);
  static const Color border = Color(0xFFEDEDED);
  static const Color accent = Color(0xFF92A3FD);
  static const Color primaryStart = Color(0xFF9DCEFF);
  static const Color primaryEnd = Color(0xFF92A3FD);

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
