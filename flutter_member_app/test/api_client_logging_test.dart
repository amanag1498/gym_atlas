import 'dart:async';
import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_member_app/src/core/api_client.dart';

void main() {
  test('API diagnostics never print authentication payload values', () async {
    final messages = <String>[];
    final originalDebugPrint = debugPrint;
    debugPrint = (message, {wrapWidth}) {
      if (message != null) messages.add(message);
    };
    addTearDown(() => debugPrint = originalDebugPrint);

    final client = MemberApiClient();
    client.dio.httpClientAdapter = _SuccessfulAuthAdapter();

    await client.post(
      '/public/auth/firebase/login',
      data: const <String, dynamic>{
        'id_token': 'private-firebase-token',
        'email': 'reviewer@example.com',
      },
    );

    final output = messages.join('\n');
    expect(output, isNot(contains('private-firebase-token')));
    expect(output, isNot(contains('issued-bearer-token')));
    expect(output, isNot(contains('reviewer@example.com')));
    expect(output, contains('Map(keys=id_token,email)'));
    expect(output, contains('Map(keys=success,data)'));
  });
}

class _SuccessfulAuthAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    return ResponseBody.fromString(
      jsonEncode(<String, dynamic>{
        'success': true,
        'data': <String, dynamic>{'token': 'issued-bearer-token'},
      }),
      200,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
