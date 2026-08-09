List<Map<String, dynamic>> workoutPlanDays(Map<String, dynamic> plan) {
  final days = (plan['days'] as List<dynamic>? ?? const [])
      .whereType<Map>()
      .map((day) => Map<String, dynamic>.from(day))
      .where((day) => day['id'] is num)
      .toList();
  days.sort(
    (left, right) => ((left['day_number'] as num?)?.toInt() ?? 0).compareTo(
      (right['day_number'] as num?)?.toInt() ?? 0,
    ),
  );
  return days;
}

List<Map<String, dynamic>> workoutPlansForRelationship(
  List<Map<String, dynamic>> plans,
  int? relationshipId,
) {
  if (relationshipId == null) return plans;
  return plans
      .where(
        (plan) =>
            (plan['independent_trainer_member_relationship_id'] as num?)
                ?.toInt() ==
            relationshipId,
      )
      .toList();
}

int? selectedWorkoutPlanId({
  required List<Map<String, dynamic>> plans,
  required int? relationshipId,
  int? requestedPlanId,
}) {
  final visible = workoutPlansForRelationship(plans, relationshipId);
  if (requestedPlanId != null &&
      visible.any((plan) => (plan['id'] as num?)?.toInt() == requestedPlanId)) {
    return requestedPlanId;
  }
  return visible.isEmpty ? null : (visible.first['id'] as num?)?.toInt();
}

Map<String, dynamic>? recommendedWorkoutDay({
  required Map<String, dynamic> plan,
  required List<Map<String, dynamic>> history,
}) {
  final days = workoutPlanDays(plan);
  if (days.isEmpty) {
    return null;
  }

  final planId = (plan['id'] as num?)?.toInt();
  Map<String, dynamic>? lastCompleted;
  for (final session in history) {
    if ((session['workout_plan_id'] as num?)?.toInt() == planId &&
        session['status']?.toString().toLowerCase() == 'completed' &&
        (session['workout_plan_day_id'] is num ||
            session['plan_day_number'] is num)) {
      lastCompleted = session;
      break;
    }
  }

  final lastDayId = (lastCompleted?['workout_plan_day_id'] as num?)?.toInt();
  var lastIndex = days.indexWhere(
    (day) => (day['id'] as num?)?.toInt() == lastDayId,
  );
  if (lastIndex < 0) {
    final lastDayNumber = (lastCompleted?['plan_day_number'] as num?)?.toInt();
    lastIndex = days.indexWhere(
      (day) => (day['day_number'] as num?)?.toInt() == lastDayNumber,
    );
  }
  return lastIndex < 0 ? days.first : days[(lastIndex + 1) % days.length];
}

String? workoutSessionDayLabel(Map<String, dynamic> session) {
  final label = session['plan_day_label']?.toString().trim() ?? '';
  final number = (session['plan_day_number'] as num?)?.toInt();
  if (label.isNotEmpty && number != null) {
    return 'Day $number — $label';
  }
  if (label.isNotEmpty) {
    return label;
  }
  if (number != null) {
    return 'Day $number';
  }
  return null;
}

Map<String, dynamic> workoutStartPayload({
  required String sessionDate,
  int? workoutPlanId,
  int? workoutPlanDayId,
}) {
  return <String, dynamic>{
    if (workoutPlanId != null) 'workout_plan_id': workoutPlanId,
    if (workoutPlanId != null && workoutPlanDayId != null)
      'workout_plan_day_id': workoutPlanDayId,
    'session_date': sessionDate,
  };
}
