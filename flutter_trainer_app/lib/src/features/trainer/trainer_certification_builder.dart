import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/common_widgets.dart';

class TrainerCertificationBuilder extends StatelessWidget {
  const TrainerCertificationBuilder({
    super.key,
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
