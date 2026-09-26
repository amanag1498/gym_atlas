import 'dart:convert';

import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_ble_protocol.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('parses V1 service data with public hub id', () {
    final bytes = [
      SmartAttendanceBleProtocol.protocolVersion,
      ...utf8.encode('SAHABC123DEF4567'),
    ];

    final payload = SmartAttendanceBleProtocol.parseServiceData(bytes);

    expect(payload, isNotNull);
    expect(payload!.protocolVersion, 1);
    expect(payload.publicId, 'SAHABC123DEF4567');
  });

  test('rejects missing, unknown, oversized, and unsafe payloads', () {
    expect(SmartAttendanceBleProtocol.parseServiceData(null), isNull);
    expect(SmartAttendanceBleProtocol.parseServiceData(const []), isNull);
    expect(
      SmartAttendanceBleProtocol.parseServiceData([
        2,
        ...utf8.encode('SAHABC'),
      ]),
      isNull,
    );
    expect(
      SmartAttendanceBleProtocol.parseServiceData([
        1,
        ...utf8.encode('SAH12345678901234567890'),
      ]),
      isNull,
    );
    expect(
      SmartAttendanceBleProtocol.parseServiceData([
        1,
        ...utf8.encode('bad id'),
      ]),
      isNull,
    );
  });
}
