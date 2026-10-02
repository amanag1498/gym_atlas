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

  test('unauthenticated 401 does not clear an existing app session', () async {
    var unauthorizedCalls = 0;
    final client = MemberApiClient(
      onUnauthorized: (rejectedToken, requestUri) async {
        unauthorizedCalls++;
      },
    );
    client.dio.httpClientAdapter = _UnauthorizedAdapter();

    await expectLater(client.get('/public/me'), throwsA(isA<DioException>()));

    expect(unauthorizedCalls, 0);
  });

  test('authenticated 401 identifies the rejected token and request', () async {
    String? rejectedToken;
    Uri? rejectedRequest;
    final client = MemberApiClient(
      token: 'current-member-token',
      onUnauthorized: (token, requestUri) async {
        rejectedToken = token;
        rejectedRequest = requestUri;
      },
    );
    client.dio.httpClientAdapter = _UnauthorizedAdapter();

    await expectLater(
      client.get('/member/attendance/status'),
      throwsA(isA<DioException>()),
    );

    expect(rejectedToken, 'current-member-token');
    expect(rejectedRequest?.path, '/api/member/attendance/status');
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

class _UnauthorizedAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    return ResponseBody.fromString(
      jsonEncode(<String, dynamic>{
        'success': false,
        'message': 'Unauthenticated.',
      }),
      401,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
