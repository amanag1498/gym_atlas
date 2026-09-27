import 'dart:convert';

import 'package:flutter_member_app/src/features/smart_attendance/smart_attendance_ble_protocol.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('parses compact V2 service data with public hub id', () {
    final payload = SmartAttendanceBleProtocol.parseServiceData(const [
      2,
      0x02,
      0xa6,
      0x49,
      0x21,
      0xb5,
      0x36,
      0x6c,
      0xea,
      0x2f,
    ]);

    expect(payload, isNotNull);
    expect(payload!.protocolVersion, 2);
    expect(payload.publicId, 'SAHABC123DEF4567');
  });

  test('keeps parsing legacy V1 service data during rollout', () {
    final bytes = [
      SmartAttendanceBleProtocol.legacyProtocolVersion,
      ...utf8.encode('SAHABC123DEF4567'),
    ];

    final payload = SmartAttendanceBleProtocol.parseServiceData(bytes);

    expect(payload, isNotNull);
    expect(payload!.protocolVersion, 1);
    expect(payload.publicId, 'SAHABC123DEF4567');
  });

  test('compact V2 preserves leading zeroes in the public id suffix', () {
    final payload = SmartAttendanceBleProtocol.parseServiceData(const [
      2,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
    ]);

    expect(payload, isNotNull);
    expect(payload!.publicId, 'SAH0000000000000');
  });

  test('rejects missing, unknown, oversized, and unsafe payloads', () {
    expect(SmartAttendanceBleProtocol.parseServiceData(null), isNull);
    expect(SmartAttendanceBleProtocol.parseServiceData(const []), isNull);
    expect(
      SmartAttendanceBleProtocol.parseServiceData([
        3,
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
