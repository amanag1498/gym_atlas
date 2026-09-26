import 'smart_attendance_detection.dart';

abstract class SmartAttendanceCheckInClient {
  Future<SmartAttendanceCheckInResponse> recordSmartAttendanceCheckIn(
    SmartAttendanceDetection detection,
  );
}

class SmartAttendanceCheckInResponse {
  const SmartAttendanceCheckInResponse({
    required this.checkedInToday,
    required this.checkInMethod,
    this.gymId,
    this.attendanceDate,
    this.duplicateSuppressionUntil,
  });

  final bool checkedInToday;
  final String? checkInMethod;
  final int? gymId;
  final String? attendanceDate;
  final DateTime? duplicateSuppressionUntil;

  bool get recordedSmartAttendance =>
      checkedInToday && checkInMethod == 'smart_attendance';

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
    );
  }
}
