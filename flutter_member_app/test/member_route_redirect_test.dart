import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_member_app/src/core/member_route_redirect.dart';

void main() {
  group('memberRouteRedirect', () {
    test('preserves an event deep link while the session initializes', () {
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/events/42'),
          initializing: true,
          isAuthenticated: false,
        ),
        '/?continue=%2Fevents%2F42',
      );
    });

    test('moves the saved event destination from bootstrap to login', () {
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/?continue=%2Fevents%2F42'),
          initializing: false,
          isAuthenticated: false,
        ),
        '/login?continue=%2Fevents%2F42',
      );
    });

    test('opens the saved event after authentication', () {
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/login?continue=%2Fevents%2F42'),
          initializing: false,
          isAuthenticated: true,
        ),
        '/events/42',
      );
    });

    test('preserves a public event UUID through login', () {
      const token = '123e4567-e89b-42d3-a456-426614174000';
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/events/$token'),
          initializing: false,
          isAuthenticated: false,
        ),
        '/login?continue=%2Fevents%2F$token',
      );
    });

    test('preserves an allowlisted event claim token through login', () {
      const eventToken = '123e4567-e89b-42d3-a456-426614174000';
      const claimToken =
          'AbCdEf0123456789AbCdEf0123456789AbCdEf0123456789AbCdEf0123456789';
      final claimUri = Uri.parse('/events/$eventToken?claim=$claimToken');
      expect(eventClaimToken(claimUri), claimToken);
      expect(
        memberRouteRedirect(
          uri: claimUri,
          initializing: false,
          isAuthenticated: false,
        ),
        '/login?continue=%2Fevents%2F$eventToken%3Fclaim%3D$claimToken',
      );
      expect(
        memberRouteRedirect(
          uri: Uri.parse(
            '/login?continue=%2Fevents%2F$eventToken%3Fclaim%3D$claimToken',
          ),
          initializing: false,
          isAuthenticated: true,
        ),
        '/events/$eventToken?claim=$claimToken',
      );
    });

    test('drops a malformed claim token but keeps the event destination', () {
      final uri = Uri.parse('/events/42?claim=too-short');
      expect(
        memberRouteRedirect(
          uri: uri,
          initializing: false,
          isAuthenticated: false,
        ),
        '/login?continue=%2Fevents%2F42',
      );
      expect(eventClaimToken(uri), isNull);
    });

    test('drops non-allowlisted event query parameters', () {
      const claimToken =
          'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
      final uri = Uri.parse(
        '/events/42?claim=$claimToken&redirect=https://evil.test',
      );
      expect(
        memberRouteRedirect(
          uri: uri,
          initializing: false,
          isAuthenticated: false,
        ),
        '/login?continue=%2Fevents%2F42',
      );
      expect(eventClaimToken(uri), isNull);
    });

    test('preserves enrollment links through the same flow', () {
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/join/a-token'),
          initializing: false,
          isAuthenticated: false,
        ),
        '/login?continue=%2Fjoin%2Fa-token',
      );
    });

    test('preserves enrollment through required consent', () {
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/login?continue=%2Fjoin%2Fa-token'),
          initializing: false,
          isAuthenticated: true,
          requiresConsent: true,
        ),
        '/consent?continue=%2Fjoin%2Fa-token',
      );
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/consent?continue=%2Fjoin%2Fa-token'),
          initializing: false,
          isAuthenticated: true,
        ),
        '/join/a-token',
      );
    });

    test('normalizes enrollment app links from every supported format', () {
      expect(
        memberDeepLinkDestination(
          Uri.parse('https://gymatlas.in/join/a-token'),
        ),
        '/join/a-token',
      );
      expect(
        memberDeepLinkDestination(Uri.parse('gymatlasmember:///join/a-token')),
        '/join/a-token',
      );
      expect(
        memberDeepLinkDestination(Uri.parse('gymatlasmember://join/a-token')),
        '/join/a-token',
      );
      expect(
        memberDeepLinkDestination(
          Uri.parse('https://gymatlas.in/join/a-token?gym=1'),
        ),
        '/join/a-token',
      );
    });

    test('rejects enrollment links from another web host', () {
      expect(
        memberDeepLinkDestination(
          Uri.parse('https://malicious.example/join/a-token'),
        ),
        isNull,
      );
    });

    test('supports legacy login join parameters', () {
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/login?join=a-token'),
          initializing: false,
          isAuthenticated: true,
        ),
        '/join/a-token',
      );
    });

    test('preserves a workout share link through login', () {
      const token = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
      expect(token.length, 48);
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/workouts/shared/$token'),
          initializing: false,
          isAuthenticated: false,
        ),
        '/login?continue=%2Fworkouts%2Fshared%2F$token',
      );
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/login?continue=%2Fworkouts%2Fshared%2F$token'),
          initializing: false,
          isAuthenticated: true,
        ),
        '/workouts/shared/$token',
      );
    });

    test('rejects malformed workout share links', () {
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/workouts/shared/too-short'),
          initializing: false,
          isAuthenticated: false,
        ),
        '/login',
      );
    });

    test('does not accept an external continuation', () {
      expect(
        memberRouteRedirect(
          uri: Uri.parse(
            '/login?continue=https%3A%2F%2Fmalicious.example%2Fevents%2F42',
          ),
          initializing: false,
          isAuthenticated: true,
        ),
        '/home',
      );
    });

    test('does not carry malformed event ids through login', () {
      expect(
        memberRouteRedirect(
          uri: Uri.parse('/events/not-an-id'),
          initializing: false,
          isAuthenticated: false,
        ),
        '/login',
      );
    });
  });
}
