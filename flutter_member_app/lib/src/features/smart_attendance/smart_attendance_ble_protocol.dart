import 'dart:convert';
import 'dart:typed_data';

class SmartAttendanceBleProtocol {
  const SmartAttendanceBleProtocol._();

  static const serviceUuid = '8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1';
  static const protocolVersion = 1;
  static const publicIdMaxBytes = 20;
  static final RegExp publicIdPattern = RegExp(r'^[A-Z0-9_-]+$');

  static SmartAttendanceBlePayload? parseServiceData(List<int>? serviceData) {
    if (serviceData == null || serviceData.length < 2) {
      return null;
    }

    final version = serviceData.first;
    if (version != protocolVersion) {
      return null;
    }

    final idBytes = Uint8List.fromList(serviceData.skip(1).toList());
    if (idBytes.isEmpty || idBytes.length > publicIdMaxBytes) {
      return null;
    }

    final String publicId;
    try {
      publicId = utf8.decode(idBytes, allowMalformed: false);
    } on FormatException {
      return null;
    }
    if (publicId.isEmpty || !publicIdPattern.hasMatch(publicId)) {
      return null;
    }

    return SmartAttendanceBlePayload(
      protocolVersion: version,
      publicId: publicId,
    );
  }
}

class SmartAttendanceBlePayload {
  const SmartAttendanceBlePayload({
    required this.protocolVersion,
    required this.publicId,
  });

  final int protocolVersion;
  final String publicId;
}
