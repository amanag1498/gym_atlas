import 'dart:typed_data';

class SmartAttendanceDetection {
  const SmartAttendanceDetection({
    required this.publicId,
    required this.protocolVersion,
    required this.rssi,
    required this.detectedAt,
    required this.source,
    this.hubId,
    this.isExit = false,
    this.rawServiceData,
  });

  final String publicId;
  final int protocolVersion;
  final int? rssi;
  final DateTime detectedAt;
  final String source;
  final int? hubId;
  final bool isExit;
  final Uint8List? rawServiceData;

  Map<String, Object?> toJson() => {
    'public_id': publicId,
    'hub_id': hubId,
    'protocol_version': protocolVersion,
    'rssi': rssi,
    'detected_at': detectedAt.toIso8601String(),
    'source': source,
    'is_exit': isExit,
  };
}

class SmartAttendanceScanDiagnostic {
  const SmartAttendanceScanDiagnostic({
    required this.message,
    required this.detectedAt,
    this.rssi,
    this.source,
  });

  final String message;
  final DateTime detectedAt;
  final int? rssi;
  final String? source;
}
