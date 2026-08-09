List<String> missingTrainerVerificationRequirements({
  required String bio,
  required List<String> specializations,
  required String experienceYears,
  required List<Map<String, dynamic>> certifications,
}) {
  final missing = <String>[];

  if (bio.trim().isEmpty) {
    missing.add('professional bio');
  }
  if (!specializations.any((value) => value.trim().isNotEmpty)) {
    missing.add('specialization');
  }
  if (experienceYears.trim().isEmpty) {
    missing.add('years of experience');
  }
  if (!certifications.any(_hasCertificationEvidence)) {
    missing.add('certification or qualification');
  }

  return missing;
}

bool _hasCertificationEvidence(Map<String, dynamic> certification) {
  final name = certification['name']?.toString().trim() ?? '';
  final fileUrl = certification['file_url']?.toString().trim() ?? '';
  return name.isNotEmpty || fileUrl.isNotEmpty;
}
