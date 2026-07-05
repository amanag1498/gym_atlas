import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../health/step_health_service.dart';
import '../health/step_health_types.dart';
import '../member_repository.dart';

class StepSyncService {
  StepSyncService({
    required MemberRepository repository,
    StepHealthService? healthService,
  }) : _repository = repository,
       _healthService = healthService ?? StepHealthService();

  final MemberRepository _repository;
  final StepHealthService _healthService;
  DateTime? _lastSyncedAt;
  StepHealthSnapshot? _lastSnapshot;

  Future<StepSyncResult> syncToday({bool force = false}) async {
    if (!force && _isThrottled()) {
      return StepSyncResult(
        synced: false,
        throttled: true,
        snapshot: _lastSnapshot ??
            const StepHealthSnapshot(
              steps: 0,
              distanceMeters: 0,
              caloriesEstimated: 0,
              source: 'health_connect',
              permissionStatus: StepPermissionStatus.unknown,
            ),
        readAt: DateTime.now(),
      );
    }

    final snapshot = await _healthService.readTodaySummary();
    _lastSnapshot = snapshot;
    if (!snapshot.canSync) {
      return StepSyncResult(
        synced: false,
        throttled: false,
        snapshot: snapshot,
        readAt: DateTime.now(),
      );
    }

    try {
      await _repository.syncDailySteps(snapshot.toApiPayload(DateTime.now()));
      _lastSyncedAt = DateTime.now();
      return StepSyncResult(
        synced: true,
        throttled: false,
        snapshot: snapshot,
        readAt: _lastSyncedAt!,
      );
    } catch (exception, stackTrace) {
      debugPrint('[steps][sync] failed to sync daily steps: $exception');
      debugPrintStack(stackTrace: stackTrace);
      return StepSyncResult(
        synced: false,
        throttled: false,
        snapshot: snapshot,
        readAt: DateTime.now(),
        errorMessage: _resolveErrorMessage(exception),
      );
    }
  }

  Future<StepHealthSnapshot> requestPermission() async {
    final snapshot = await _healthService.requestStepPermission();
    _lastSnapshot = snapshot;
    return snapshot;
  }

  bool _isThrottled() {
    final lastSyncedAt = _lastSyncedAt;
    if (lastSyncedAt == null) {
      return false;
    }

    return DateTime.now().difference(lastSyncedAt) < const Duration(minutes: 15);
  }

  String? _resolveErrorMessage(Object exception) {
    if (exception is DioException) {
      final response = exception.response?.data;
      if (response is Map<String, dynamic>) {
        final message = response['message']?.toString();
        final errors = response['errors'];

        if (errors is Map && errors.isNotEmpty) {
          final firstGroup = errors.values.first;
          if (firstGroup is List && firstGroup.isNotEmpty) {
            return firstGroup.first?.toString() ?? message;
          }
        }

        return message;
      }
    }

    return null;
  }
}
