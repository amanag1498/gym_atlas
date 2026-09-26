/// Returns the route the member app should navigate to for the current
/// authentication state, or `null` when no redirect is required.
///
/// Only known in-app destinations are carried through login. This keeps deep
/// link continuity without introducing an open-redirect path.
String? memberRouteRedirect({
  required Uri uri,
  required bool initializing,
  required bool isAuthenticated,
  bool requiresConsent = false,
}) {
  final path = uri.path.isEmpty ? '/' : uri.path;
  final requestedDestination = _supportedDestination(uri);
  final savedDestination = _savedDestination(uri);
  final destination = requestedDestination ?? savedDestination;

  if (initializing) {
    if (path == '/') return null;
    if (destination != null) {
      return _routeWithContinuation('/', destination);
    }
    return '/';
  }

  if (!isAuthenticated) {
    if (path == '/login') return null;
    if (destination != null) {
      return _routeWithContinuation('/login', destination);
    }
    return '/login';
  }

  if (requiresConsent && path != '/consent') {
    return destination == null
        ? '/consent'
        : _routeWithContinuation('/consent', destination);
  }
  if (!requiresConsent && path == '/consent') return destination ?? '/home';

  if (path == '/' || path == '/login') {
    return destination ?? '/home';
  }

  return null;
}

/// Returns the claim token only when it is the sole query value on an event
/// route and matches the server-issued 64-character token format.
String? eventClaimToken(Uri uri) {
  final segments = uri.pathSegments;
  if (segments.length != 2 || segments.first != 'events') return null;
  final claimValues = uri.queryParametersAll['claim'];
  final hasOnlyClaim =
      uri.queryParametersAll.length == 1 &&
      claimValues != null &&
      claimValues.length == 1;
  final claim = hasOnlyClaim ? claimValues.single : null;
  return claim != null && _claimTokenPattern.hasMatch(claim) ? claim : null;
}

String? _savedDestination(Uri uri) {
  final continuation = uri.queryParameters['continue'];
  if (continuation != null) {
    return _validatedDestination(Uri.tryParse(continuation));
  }

  // Keep old enrollment login links working while all deployed links move to
  // the generic continuation parameter.
  final legacyJoinToken = uri.queryParameters['join'];
  if (legacyJoinToken == null || legacyJoinToken.isEmpty) return null;
  return _validatedDestination(
    Uri.tryParse('/join/${Uri.encodeComponent(legacyJoinToken)}'),
  );
}

String? _supportedDestination(Uri uri) => memberDeepLinkDestination(uri);

/// Converts an HTTPS, custom-scheme, or internal Gym Atlas link into a safe
/// member-app destination. Unknown hosts, schemes, and routes are rejected.
String? memberDeepLinkDestination(Uri uri) {
  Uri candidate = uri;
  if (uri.hasScheme || uri.hasAuthority) {
    if (uri.scheme == 'https' &&
        const {'gymatlas.in', 'www.gymatlas.in'}.contains(uri.host)) {
      candidate = Uri(path: uri.path, query: uri.query);
    } else if (uri.scheme == 'gymatlasmember') {
      final path = uri.host.isEmpty
          ? uri.path
          : '/${[uri.host, ...uri.pathSegments].join('/')}';
      candidate = Uri(path: path, query: uri.query);
    } else {
      return null;
    }
  }

  return _validatedDestination(candidate);
}

String? _validatedDestination(Uri? candidate) {
  if (candidate == null ||
      candidate.hasScheme ||
      candidate.hasAuthority ||
      candidate.fragment.isNotEmpty) {
    return null;
  }

  final segments = candidate.pathSegments;
  if (segments.length == 3 &&
      segments[0] == 'workouts' &&
      segments[1] == 'shared' &&
      _workoutShareTokenPattern.hasMatch(segments[2]) &&
      candidate.query.isEmpty) {
    return '/workouts/shared/${segments[2]}';
  }

  if (segments.length != 2) return null;

  if (segments.first == 'events') {
    final eventId = int.tryParse(segments.last);
    final eventReference = eventId != null && eventId > 0
        ? '$eventId'
        : _uuidPattern.hasMatch(segments.last)
        ? segments.last.toLowerCase()
        : null;
    if (eventReference == null) return null;

    final claim = eventClaimToken(candidate);
    return Uri(
      path: '/events/$eventReference',
      queryParameters: claim == null ? null : <String, String>{'claim': claim},
    ).toString();
  }

  if (segments.first == 'join' && segments.last.isNotEmpty) {
    return '/join/${Uri.encodeComponent(segments.last)}';
  }

  return null;
}

final RegExp _uuidPattern = RegExp(
  r'^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$',
);
final RegExp _claimTokenPattern = RegExp(r'^[A-Za-z0-9]{64}$');
final RegExp _workoutShareTokenPattern = RegExp(r'^[A-Za-z0-9]{48}$');

String _routeWithContinuation(String route, String destination) {
  return Uri(
    path: route,
    queryParameters: <String, String>{'continue': destination},
  ).toString();
}
