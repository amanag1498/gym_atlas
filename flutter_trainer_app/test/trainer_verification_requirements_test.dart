import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_verification_requirements.dart';

void main() {
  test('reports every missing verification requirement', () {
    expect(
      missingTrainerVerificationRequirements(
        bio: ' ',
        specializations: const [],
        experienceYears: '',
        certifications: const [],
      ),
      const [
        'professional bio',
        'specialization',
        'years of experience',
        'certification or qualification',
      ],
    );
  });

  test('accepts a complete trainer verification profile', () {
    expect(
      missingTrainerVerificationRequirements(
        bio: 'Strength and mobility coach.',
        specializations: const ['Strength'],
        experienceYears: '0',
        certifications: const [
          {'name': 'Certified Personal Trainer'},
        ],
      ),
      isEmpty,
    );
  });

  test('accepts uploaded evidence without a certification name', () {
    expect(
      missingTrainerVerificationRequirements(
        bio: 'Strength and mobility coach.',
        specializations: const ['Mobility'],
        experienceYears: '4',
        certifications: const [
          {'file_url': 'https://example.com/certificate.pdf'},
        ],
      ),
      isEmpty,
    );
  });
}
