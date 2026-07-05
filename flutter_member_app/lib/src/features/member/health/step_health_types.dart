enum StepPermissionStatus {
  unknown,
  granted,
  denied,
  unavailable,
}

class StepHealthSnapshot {
  const StepHealthSnapshot({
    required this.steps,
    required this.distanceMeters,
    required this.caloriesEstimated,
    required this.source,
    required this.permissionStatus,
  });

  final int steps;
  final int distanceMeters;
  final int caloriesEstimated;
  final String source;
  final StepPermissionStatus permissionStatus;

  bool get canSync => permissionStatus == StepPermissionStatus.granted;

  Map<String, dynamic> toApiPayload(DateTime date) {
    final localDate = DateTime(date.year, date.month, date.day);
    return <String, dynamic>{
      'date':
          '${localDate.year.toString().padLeft(4, '0')}-'
          '${localDate.month.toString().padLeft(2, '0')}-'
          '${localDate.day.toString().padLeft(2, '0')}',
      'steps': steps,
      'goalSteps': 10000,
      'distanceMeters': distanceMeters,
      'caloriesEstimated': caloriesEstimated,
      'source': source,
    };
  }
}

class StepSyncResult {
  const StepSyncResult({
    required this.synced,
    required this.throttled,
    required this.snapshot,
    required this.readAt,
    this.errorMessage,
  });

  final bool synced;
  final bool throttled;
  final StepHealthSnapshot snapshot;
  final DateTime readAt;
  final String? errorMessage;
}
