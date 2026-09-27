import 'dart:convert';
import 'dart:typed_data';

class SmartAttendanceBleProtocol {
  const SmartAttendanceBleProtocol._();

  static const serviceUuid = '8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1';
  static const protocolVersion = 2;
  static const legacyProtocolVersion = 1;
  static const publicIdMaxBytes = 20;
  static const compactPublicIdBytes = 9;
  static const publicIdSuffixLength = 13;
  static const _base36Alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  static final RegExp publicIdPattern = RegExp(r'^[A-Z0-9_-]+$');

  static SmartAttendanceBlePayload? parseServiceData(List<int>? serviceData) {
    if (serviceData == null || serviceData.length < 2) {
      return null;
    }

    final version = serviceData.first;
    if (version == protocolVersion) {
      return _parseV2(serviceData);
    }
    if (version == legacyProtocolVersion) {
      return _parseV1(serviceData);
    }
    return null;
  }

  static SmartAttendanceBlePayload? _parseV1(List<int> serviceData) {
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
      protocolVersion: legacyProtocolVersion,
      publicId: publicId,
    );
  }

  static SmartAttendanceBlePayload? _parseV2(List<int> serviceData) {
    final compact = serviceData.skip(1).toList();
    if (compact.length != compactPublicIdBytes ||
        compact.any((byte) => byte < 0 || byte > 255)) {
      return null;
    }

    var value = BigInt.zero;
    for (final byte in compact) {
      value = (value << 8) | BigInt.from(byte);
    }

    final suffix = List<String>.filled(publicIdSuffixLength, '0');
    final radix = BigInt.from(36);
    for (var index = suffix.length - 1; index >= 0; index--) {
      suffix[index] = _base36Alphabet[(value % radix).toInt()];
      value ~/= radix;
    }
    if (value != BigInt.zero) {
      return null;
    }

    return SmartAttendanceBlePayload(
      protocolVersion: protocolVersion,
      publicId: 'SAH${suffix.join()}',
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
