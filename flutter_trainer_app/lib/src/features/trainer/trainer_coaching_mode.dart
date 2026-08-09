enum TrainerMemberAction {
  gymInvitation,
  verification,
  independentInvitation,
  manageIndependentInvitations,
}

int? trainerAssignedGymId(Map<String, dynamic> profile) {
  final assignedGym = profile['assigned_gym'];
  final nestedId = assignedGym is Map ? assignedGym['id'] : null;
  final value = nestedId ?? profile['gym_id'];
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}

bool trainerIsIndependent(Map<String, dynamic> profile) =>
    trainerAssignedGymId(profile) == null;

TrainerMemberAction trainerMemberAction({
  required bool isIndependent,
  required String verificationStatus,
  required int pendingInvitationCount,
}) {
  if (!isIndependent) return TrainerMemberAction.gymInvitation;
  if (verificationStatus != 'verified') {
    return TrainerMemberAction.verification;
  }
  if (pendingInvitationCount == 0) {
    return TrainerMemberAction.independentInvitation;
  }
  return TrainerMemberAction.manageIndependentInvitations;
}
