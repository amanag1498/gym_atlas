import 'smart_attendance_detection.dart';

abstract class SmartAttendanceCheckInClient {
  Future<SmartAttendanceCheckInResponse> recordSmartAttendanceCheckIn(
    SmartAttendanceDetection detection,
  );

  Future<void> recordSmartAttendanceCheckOut({
    required int attendanceLogId,
    required DateTime lastPresenceAt,
  });
}

class SmartAttendanceCheckInResponse {
  const SmartAttendanceCheckInResponse({
    required this.checkedInToday,
    required this.checkInMethod,
    this.gymId,
    this.attendanceDate,
    this.duplicateSuppressionUntil,
    this.attendanceLogId,
    this.checkedInAt,
    this.lastPresenceAt,
    this.checkedOutAt,
    this.attendanceWindowEndsAt,
  });

  final bool checkedInToday;
  final String? checkInMethod;
  final int? gymId;
  final String? attendanceDate;
  final DateTime? duplicateSuppressionUntil;
  final int? attendanceLogId;
  final DateTime? checkedInAt;
  final DateTime? lastPresenceAt;
  final DateTime? checkedOutAt;
  final DateTime? attendanceWindowEndsAt;

  bool get recordedSmartAttendance =>
      attendanceLogId != null && checkInMethod == 'smart_attendance';

  factory SmartAttendanceCheckInResponse.fromApi(
    Map<String, dynamic> response,
  ) {
    final data = Map<String, dynamic>.from(
      response['data'] as Map? ?? const {},
    );
    final attendance = Map<String, dynamic>.from(
      data['attendance'] as Map? ?? const {},
    );
    final status = Map<String, dynamic>.from(
      data['check_in_status'] as Map? ?? const {},
    );
    return SmartAttendanceCheckInResponse(
      checkedInToday: status['checked_in_today'] == true,
      checkInMethod:
          status['check_in_method']?.toString() ??
          attendance['check_in_method']?.toString(),
      gymId: (attendance['gym_id'] as num?)?.toInt(),
      attendanceDate: data['attendance_date']?.toString(),
      duplicateSuppressionUntil: DateTime.tryParse(
        data['duplicate_suppression_until']?.toString() ?? '',
      ),
      attendanceLogId: (attendance['id'] as num?)?.toInt(),
      checkedInAt: DateTime.tryParse(
        attendance['checked_in_at']?.toString() ?? '',
      ),
      lastPresenceAt: DateTime.tryParse(
        attendance['last_presence_at']?.toString() ?? '',
      ),
      checkedOutAt: DateTime.tryParse(
        attendance['checked_out_at']?.toString() ?? '',
      ),
      attendanceWindowEndsAt: DateTime.tryParse(
        attendance['attendance_window_ends_at']?.toString() ?? '',
      ),
    );
  }
}
