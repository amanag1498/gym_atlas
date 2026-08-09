import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_trainer_app/src/features/trainer/trainer_coaching_mode.dart';

void main() {
  test('gym trainer never enters independent verification flow', () {
    final profile = <String, dynamic>{
      'gym_id': 42,
      'verification_status': 'pending',
    };

    expect(trainerIsIndependent(profile), isFalse);
    expect(
      trainerMemberAction(
        isIndependent: trainerIsIndependent(profile),
        verificationStatus: 'pending',
        pendingInvitationCount: 0,
      ),
      TrainerMemberAction.gymInvitation,
    );
  });

  test('independent trainer routes pending verification to profile', () {
    expect(
      trainerMemberAction(
        isIndependent: true,
        verificationStatus: 'pending',
        pendingInvitationCount: 0,
      ),
      TrainerMemberAction.verification,
    );
  });

  test(
    'verified independent trainer with no invitations starts invite flow',
    () {
      expect(
        trainerMemberAction(
          isIndependent: true,
          verificationStatus: 'verified',
          pendingInvitationCount: 0,
        ),
        TrainerMemberAction.independentInvitation,
      );
    },
  );
}
