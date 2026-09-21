/// Client-side checks for the workout day contract shared by member plans and
/// trainer templates/assignments. The API remains the source of truth.
String? validateWorkoutBuilderPayload(Map<String, dynamic> payload) {
  final weeks = _integer(payload['duration_weeks']);
  if (weeks == null || weeks < 1 || weeks > 52) {
    return 'Duration must be from 1 to 52 weeks.';
  }

  final days = payload['days'];
  if (days is! List || days.isEmpty) {
    return 'Select at least one training day.';
  }

  final seenDays = <int>{};
  for (final rawDay in days) {
    if (rawDay is! Map) return 'Check the workout day details.';
    final day = Map<String, dynamic>.from(rawDay);
    final dayNumber = _integer(day['day_number']);
    if (dayNumber == null || dayNumber < 1 || dayNumber > 7) {
      return 'Choose a valid weekday for every workout day.';
    }
    if (!seenDays.add(dayNumber)) {
      return 'Each weekday can appear only once in a workout plan.';
    }
    final dayLabel = _weekday(dayNumber);
    final exercises = day['exercises'];
    if (exercises is! List || exercises.isEmpty) {
      return 'Add at least one exercise for $dayLabel.';
    }

    final groups = <String, List<int>>{};
    final groupTypes = <String, Set<String>>{};
    final groupRounds = <String, Set<int>>{};
    final groupOrders = <String, Set<int>>{};
    for (var index = 0; index < exercises.length; index++) {
      final rawExercise = exercises[index];
      if (rawExercise is! Map) return 'Check the exercises for $dayLabel.';
      final exercise = Map<String, dynamic>.from(rawExercise);
      final exerciseLabel = 'Exercise ${index + 1} on $dayLabel';
      if ((_integer(exercise['exercise_id']) ?? 0) < 1) {
        return '$exerciseLabel needs an exercise selection.';
      }
      if ((_integer(exercise['sets']) ?? 0) < 1) {
        return '$exerciseLabel needs at least one set.';
      }
      final mode = exercise['tracking_mode']?.toString() ?? 'reps';
      final duration = _integer(exercise['planned_duration_seconds']) ?? 0;
      final distance = _number(exercise['planned_distance_meters']) ?? 0;
      if (mode == 'timed' && duration < 1) {
        return '$exerciseLabel needs a planned duration.';
      }
      if (mode == 'distance' && distance <= 0) {
        return '$exerciseLabel needs a planned distance.';
      }
      if (mode == 'cardio' && duration < 1 && distance <= 0) {
        return '$exerciseLabel needs a planned duration or distance.';
      }
      final progression = exercise['progression_policy']?.toString() ?? 'off';
      if (progression != 'off' && mode != 'reps') {
        return '$exerciseLabel can use progression only with reps.';
      }
      if (progression == 'double_progression') {
        final config = exercise['progression_config'];
        final values = config is Map ? config : const {};
        final min = _integer(values['min_reps']) ?? 0;
        final max = _integer(values['max_reps']) ?? 0;
        if (min < 1 || max < min) {
          return '$exerciseLabel needs a valid progression rep range.';
        }
      }

      final key = exercise['group_key']?.toString().trim() ?? '';
      if (key.isEmpty) continue;
      groups.putIfAbsent(key, () => []).add(index);
      final type = exercise['group_type']?.toString() ?? '';
      if (type.isNotEmpty) groupTypes.putIfAbsent(key, () => {}).add(type);
      final rounds = _integer(exercise['group_rounds']);
      if (rounds != null) groupRounds.putIfAbsent(key, () => {}).add(rounds);
      final order = _integer(exercise['group_order']);
      if (order == null || order < 1) {
        return '$exerciseLabel needs an order within group $key.';
      }
      if (!groupOrders.putIfAbsent(key, () => {}).add(order)) {
        return 'Group $key on $dayLabel has duplicate exercise orders.';
      }
    }
    for (final entry in groups.entries) {
      final positions = entry.value;
      if (positions.length < 2) {
        return 'Group ${entry.key} on $dayLabel needs at least two exercises.';
      }
      if (positions.last - positions.first + 1 != positions.length) {
        return 'Keep group ${entry.key} exercises together on $dayLabel.';
      }
      if ((groupTypes[entry.key]?.length ?? 0) > 1 ||
          (groupRounds[entry.key]?.length ?? 0) > 1) {
        return 'Group ${entry.key} on $dayLabel needs one type and round count.';
      }
    }
  }
  return null;
}

int? _integer(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.isFinite && value == value.toInt()
      ? value.toInt()
      : null;
  return int.tryParse('$value');
}
double? _number(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('$value');

String _weekday(int day) => const [
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
  'Sunday',
][day - 1];
