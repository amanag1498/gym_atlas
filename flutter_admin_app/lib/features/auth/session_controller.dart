import 'package:dio/dio.dart';
import 'package:firebase_auth/firebase_auth.dart' as firebase;
import 'package:flutter/foundation.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../../core/models/session_models.dart';
import '../../core/network/api_client.dart';
import '../../core/storage/token_storage.dart';
import 'auth_repository.dart';

class SessionController extends ChangeNotifier {
  SessionController({
    TokenStorage? tokenStorage,
    ApiClient? apiClient,
    GoogleSignIn? googleSignIn,
  }) : _tokenStorage = tokenStorage ?? const TokenStorage(),
       _apiClient =
           apiClient ??
           ApiClient(token: null, onUnauthorized: () async {}),
       _googleSignIn =
           googleSignIn ??
           GoogleSignIn(
             scopes: const ['email', 'profile'],
           ) {
    _apiClient.updateUnauthorizedHandler(_handleUnauthorized);
  }

  final TokenStorage _tokenStorage;
  final ApiClient _apiClient;
  final GoogleSignIn _googleSignIn;

  AppUser? user;
  String? _token;
  bool bootstrapping = false;
  bool loggingIn = false;
  String? error;

  static const List<String> _allowedRoles = [
    'platform_admin',
    'gym_owner',
    'branch_manager',
    'gym_staff',
  ];

  bool get isAuthenticated => _token != null && user != null;
  bool get hasMultipleRoles => (user?.adminRoles.length ?? 0) > 1;
  String? get token => _token;
  ApiClient get authenticatedClient => _apiClient;

  Future<void> bootstrap() async {
    bootstrapping = true;
    notifyListeners();

    final storedToken = await _tokenStorage.readToken();
    final storedUser = await _tokenStorage.readUser();

    if (storedToken == null || storedToken.isEmpty) {
      await _clearLocalState(notify: false);
      bootstrapping = false;
      notifyListeners();
      return;
    }

    _token = storedToken;
    user = storedUser;
    _apiClient.setBearerToken(storedToken);

    try {
      final repository = AuthRepository(_apiClient);
      var me = await repository.fetchMe();
      me = await _ensureAdminRole(repository, me);
      _ensureEligibleAdmin(me);
      user = me;
      await _tokenStorage.writeSession(token: storedToken, user: me);
    } on DioException catch (exception) {
      if (exception.response?.statusCode == 401) {
        await _clearLocalState(notify: false);
      } else {
        error = _mapAuthError(exception);
      }
    } catch (exception) {
      await _clearLocalState(notify: false);
      error = exception.toString().replaceFirst('Exception: ', '');
    }

    bootstrapping = false;
    notifyListeners();
  }

  Future<void> login() async {
    loggingIn = true;
    error = null;
    notifyListeners();

    try {
      final account = await _googleSignIn.signIn();
      if (account == null) {
        throw Exception('Google sign-in was cancelled.');
      }

      final authentication = await account.authentication;
      final credential = firebase.GoogleAuthProvider.credential(
        idToken: authentication.idToken,
        accessToken: authentication.accessToken,
      );
      final firebaseUserCredential = await firebase.FirebaseAuth.instance
          .signInWithCredential(credential);
      final idToken = await firebaseUserCredential.user?.getIdToken(true);
      if (idToken == null || idToken.isEmpty) {
        throw Exception('Firebase ID token was not returned.');
      }

      final repository = AuthRepository(_apiClient);
      final session = await repository.signInWithFirebase(
        idToken: idToken,
        appType: 'admin',
      );
      if (session.token.isEmpty) {
        throw Exception('Authentication token missing from server response.');
      }

      _apiClient.setBearerToken(session.token);

      var me = await repository.fetchMe();
      me = await _ensureAdminRole(repository, me);
      _ensureEligibleAdmin(me);

      _token = session.token;
      user = me;
      await _tokenStorage.writeSession(token: session.token, user: me);
    } on DioException catch (exception) {
      await _googleSafeSignOut();
      await _clearLocalState(notify: false);
      error = _mapAuthError(exception);
    } catch (exception) {
      await _googleSafeSignOut();
      await _clearLocalState(notify: false);
      error = exception.toString().replaceFirst('Exception: ', '');
    }

    loggingIn = false;
    notifyListeners();
  }

  Future<void> switchRole(String role) async {
    if (_token == null) {
      return;
    }
    error = null;
    notifyListeners();

    try {
      if (!_allowedRoles.contains(role)) {
        throw Exception('This role is not supported in the Admin App.');
      }
      final repository = AuthRepository(_apiClient);
      var me = await repository.switchRole(role);
      me = await _ensureAdminRole(repository, me);
      _ensureEligibleAdmin(me);
      user = me;
      await _tokenStorage.writeSession(token: _token!, user: me);
    } on DioException catch (exception) {
      error = _mapAuthError(exception);
    } catch (exception) {
      error = exception.toString().replaceFirst('Exception: ', '');
    }

    notifyListeners();
  }

  Future<void> logout({bool remote = true}) async {
    error = null;

    if (remote && _token != null && _token!.isNotEmpty) {
      try {
        await AuthRepository(_apiClient).logout();
      } catch (_) {
        // Preserve logout flow on API failure.
      }
    }

    await _googleSafeSignOut();
    await _clearLocalState(notify: false);
    notifyListeners();
  }

  Future<void> _handleUnauthorized() async {
    await logout(remote: false);
  }

  Future<AppUser> _ensureAdminRole(
    AuthRepository repository,
    AppUser currentUser,
  ) async {
    if (_allowedRoles.contains(currentUser.activeRole)) {
      return currentUser;
    }

    final fallbackRole = currentUser.adminRoles.firstOrNull;
    if (fallbackRole != null) {
      return repository.switchRole(fallbackRole);
    }

    return currentUser;
  }

  void _ensureEligibleAdmin(AppUser currentUser) {
    if (!currentUser.isActive) {
      throw Exception('Your admin account is inactive. Please contact support.');
    }

    if (!_allowedRoles.contains(currentUser.activeRole) &&
        currentUser.adminRoles.isEmpty) {
      throw Exception('This Google account is not allowed in the Admin App.');
    }
  }

  Future<void> _clearLocalState({required bool notify}) async {
    await _tokenStorage.clear();
    _apiClient.clearBearerToken();
    _token = null;
    user = null;
    if (notify) {
      notifyListeners();
    }
  }

  Future<void> _googleSafeSignOut() async {
    try {
      await _googleSignIn.signOut();
    } catch (_) {
      // Ignore Google SDK sign-out failures during local cleanup.
    }
    try {
      await firebase.FirebaseAuth.instance.signOut();
    } catch (_) {
      // Ignore Firebase sign-out failures during local cleanup.
    }
  }

  String _mapAuthError(DioException exception) {
    final response = exception.response;
    final data = response?.data;

    if (response?.statusCode == 401) {
      return 'Your session is invalid or expired. Please sign in again.';
    }

    if (response?.statusCode == 403) {
      return 'This Google account is not allowed in the Admin App.';
    }

    if (data is Map<String, dynamic>) {
      final message = data['message']?.toString();
      if (message != null && message.isNotEmpty) {
        return message;
      }
    } else if (data is Map) {
      final message = data['message']?.toString();
      if (message != null && message.isNotEmpty) {
        return message;
      }
    }

    switch (exception.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.sendTimeout:
        return 'Network timeout while contacting the admin API.';
      case DioExceptionType.connectionError:
        return 'Unable to reach the admin API. Check your network connection.';
      default:
        return exception.message ?? 'Authentication failed.';
    }
  }
}

extension<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
